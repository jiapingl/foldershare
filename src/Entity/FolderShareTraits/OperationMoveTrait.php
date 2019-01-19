<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Move Foldershare entities.
 *
 * This trait includes methods to move FolderShare entities and place
 * them in a folder or at the root level.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationMoveTrait {

  /*---------------------------------------------------------------------
   *
   * Move to root.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function moveToRoot(string $newName = '') {
    //
    // Check name legality.
    // --------------------
    // If there is a new name, throw an exception if it is not suitable.
    $name = $this->getName();
    $doRename = FALSE;
    if (empty($newName) === FALSE) {
      // The checkName() function throws an exception if the name is too
      // long or uses illegal characters.
      $this->checkName($newName);
      $name = $newName;
      $doRename = TRUE;
    }

    //
    // Lock, move, and unlock.
    // -----------------------
    // Change the parent and root ID of this item immediately.
    //
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be moved at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    // LOCK ROOT LIST.
    if (self::acquireRootLock() === FALSE) {
      // UNLOCK THIS ITEM.
      $this->releaseLock();
      throw new LockException(Utilities::createFormattedMessage(
        t('The system is busy updating and cannot be updated at this time.')));
    }

    // LOCK OLD PARENT FOLDER, if needed.
    $oldParent = NULL;
    if ($this->isRootItem() === FALSE) {
      $oldParent = $this->getParentFolder();
      if ($oldParent->acquireLock() === FALSE) {
        // UNLOCK ROOT LIST.
        // UNLOCK THIS ITEM.
        self::releaseRootLock();
        $this->releaseLock();
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The parent folder "@name" is in use and cannot be updated at this time.',
            [
              '@name' => $oldParent->getName(),
            ])));
      }
    }

    // Check if there is already a root item with the same name.
    $uid = \Drupal::currentUser()->id();
    if (empty(self::findAllRootItemIds($uid, $name)) === FALSE) {
      // UNLOCK OLD PARENT FOLDER.
      // UNLOCK ROOT LIST.
      // UNLOCK THIS ITEM.
      if ($oldParent !== NULL) {
        $oldParent->releaseLock();
      }

      self::releaseRootLock();
      $this->releaseLock();
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" is already in use among top-level items.',
          [
            '@name' => $name,
          ]),
        t('Please rename the item before moving it.')));
    }

    // Update parent and root IDs, reset access grants, and set the name,
    // if needed.
    $rootId = (int) $this->id();

    $this->clearParentFolderId();
    $this->clearRootItemId();
    $this->addDefaultAccessGrants();
    $this->setName($name);
    $this->save();

    if ($doRename === TRUE) {
      // The item is a file, image, or media object. Change the underlying
      // item's name too.
      $this->renameWrappedFile($name);
    }

    if ($oldParent !== NULL) {
      // Clear the old parent's size field. This marks the parent as in need
      // of size recalculations to account for the movement of this item.
      $oldParent->clearSize();
      $oldParent->save();
    }

    // UNLOCK OLD PARENT FOLDER, if needed.
    // UNLOCK ROOT LIST.
    // UNLOCK THIS ITEM.
    if ($oldParent !== NULL) {
      $oldParent->releaseLock();
    }

    self::releaseRootLock();
    $this->releaseLock();

    // Update the size of the old parent, which no longer includes the
    // moved item and its descendants.
    if ($oldParent !== NULL) {
      self::updateSizes([(int) $oldParent->id()], FALSE);
    }

    if ($oldParent !== NULL) {
      self::postOperationHook(
        'move',
        [
          $this,
          $oldParent,
          NULL,
        ]);
      self::log(
        'notice',
        'Moved entity @id ("%name") from @oldId ("%oldName") to top level.',
        [
          '@id'      => $this->id(),
          '%name'    => $this->getName(),
          '@oldId'   => $oldParent->id(),
          '%oldName' => $oldParent->getName(),
          'link'     => $this->toLink(t('View'))->toString(),
        ]);
    }
    else {
      self::postOperationHook(
        'move',
        [
          $this,
          NULL,
          NULL,
        ]);
      self::log(
        'notice',
        'Moved entity @id ("%name") from top level to top level.',
        [
          '@id'      => $this->id(),
          '%name'    => $this->getName(),
          'link'     => $this->toLink(t('View'))->toString(),
        ]);
    }

    // Get a list of descendants. If there are none, we're done.
    //
    // TODO it would be handy if this returned folders first in the list,
    // and then files. That way folders would get changed first, which would
    // reduce the chance that some other process could add a file with the
    // wrong root id copied from the parent.
    $descendantIds = $this->findDescendantIds();
    if (empty($descendantIds) === TRUE) {
      return;
    }

    //
    // Queue and execute.
    // ------------------
    // Queue a task to change root IDs and then do same task immediately.
    //
    // If the immediate execution is interrupted, the queued task will finish
    // the work.
    $parameters = [
      'operation' => 'move',
      'ids'       => $descendantIds,
      'rootid'    => $rootId,
    ];

    $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
    $queue->createQueue();
    $queue->createItem($parameters);

    if (Constants::ENABLE_SET_PHP_TIMEOUT_UNLIMITED === TRUE) {
      // Before running a possibly large task that we'd rather not have
      // interrupted, try to set the PHP timeout to "unlimited".
      // This may not work if the site has PHP timeout changes blocked,
      // and it does nothing to stop web server timeouts.
      drupal_set_time_limit(0);
    }

    self::processMoveTask($parameters);

    return $this;
  }

  /**
   * Moves multiple items to the root.
   *
   * Each of the indicated items is moved. If an item is a folder, the
   * folder's descendants are moved as well.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to move. Invalid IDs
   *   are silently skipped.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if a name is already in use in the user's root list.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_move" hook for
   * each item moved.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item moved.
   *
   * @see ::moveToRoot()
   */
  public static function moveToRootMultiple(array $ids) {
    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->moveToRoot();
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be moved at this time.')));
    }
  }

  /*---------------------------------------------------------------------
   *
   * Move to folder.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function moveToFolder(
    FolderShareInterface $parent = NULL,
    string $newName = '') {
    //
    // Validate.
    // ---------
    // If there is no new parent, move to the root. Otherwise confirm that the
    // parent is a folder and that it is not a descendant of this item.
    if ($parent === NULL) {
      return $this->moveToRoot();
    }

    if ($parent->isFolder() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called with a move destination that is not a folder.',
          [
            '@method' => 'FolderShare::moveToFolder',
          ])));
    }

    $parentId = (int) $parent->id();
    if ($this->isRootItem() === FALSE) {
      // If the destination is this item's parent, then this is really a rename.
      if ($parentId === $this->getParentFolderId()) {
        if (empty($newName) === TRUE) {
          // Move to same location with same name. Do nothing.
          return $this;
        }

        $this->rename($newName);
        return $this;
      }
    }

    // If the destination is a descendant of this item, then the move is
    // circular.
    if ($parentId === (int) $this->id() ||
        $this->isAncestorOfFolderId($parentId) === TRUE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The item "@name" cannot be moved into one of its own descendants.',
          [
            '@name' => $this->getName(),
          ])));
    }

    //
    // Check name legality.
    // --------------------
    // If there is a new name, make sure it is legal.
    if (empty($newName) === FALSE) {
      // The checkName() function throws an exception if the name is too
      // long or uses illegal characters.
      $this->checkName($newName);
      $doRename = TRUE;
    }
    else {
      $newName = $this->getName();
      $doRename = FALSE;
    }

    //
    // Lock, move, and unlock.
    // -----------------------
    // Change the parent and root ID of this item immediately.
    //
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be moved at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    // LOCK NEW PARENT FOLDER.
    if ($parent->acquireLock() === FALSE) {
      // UNLOCK THIS ITEM.
      $this->releaseLock();
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The destination folder "@name" is in use and cannot be updated at this time.',
          [
            '@name' => $parent->getName(),
          ])));
    }

    // LOCK OLD PARENT FOLDER.
    $oldParent = NULL;
    if ($this->isRootItem() === FALSE) {
      $oldParent = $this->getParentFolder();
      if ($oldParent->acquireLock() === FALSE) {
        // UNLOCK NEW PARENT FOLDER.
        // UNLOCK THIS ITEM.
        $parent->releaseLock();
        $this->releaseLock();
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The parent folder "@name" is in use and cannot be updated at this time.',
            [
              '@name' => $oldParent->getName(),
            ])));
      }
    }

    // Check if there is already a child with the same name.
    if (self::findNamedChildId($parentId, $newName) !== FALSE) {
      // UNLOCK OLD PARENT FOLDER.
      // UNLOCK NEW PARENT FOLDER.
      // UNLOCK THIS ITEM.
      if ($oldParent !== NULL) {
        $oldParent->releaseLock();
      }

      $parent->releaseLock();
      $this->releaseLock();
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" is already in use in the destination folder.',
          [
            '@name' => $newName,
          ]),
        t('Please rename the item before moving it.')));
    }

    // Update parent and root IDs, reset access grants, and set the name,
    // if needed. The old and new root IDs may be the same.
    $oldRootId = $this->getRootItemId();
    $newRootId = $parent->getRootItemId();

    $this->setParentFolderId($parentId);
    $this->setRootItemId($newRootId);
    $this->setName($newName);
    $this->clearAccessGrants();
    $this->save();

    if ($doRename === TRUE) {
      // If the item is a file, image, or media object change the underlying
      // item's name too.
      $this->renameWrappedFile($newName);
    }

    // Clear the new parent's size field. The moved item is now a child of
    // the parent, so the parent's size needs updating. Clearing the size
    // marks the parent as in need of size recalculations.
    $parent->clearSize();
    $parent->save();

    if ($oldParent !== NULL) {
      // Clear the old parent's size field. This marks the parent as in need
      // of size recalculations to account for the movement of this item.
      $oldParent->clearSize();
      $oldParent->save();
    }

    // UNLOCK OLD PARENT FOLDER, if needed.
    // UNLOCK NEW PARENT FOLDER.
    // UNLOCK THIS ITEM.
    if ($oldParent !== NULL) {
      $oldParent->releaseLock();
    }

    $parent->releaseLock();
    $this->releaseLock();

    // Update the old parent's size field and propagate changes upwards
    // through ancestors. Update the new parent's size field as well.
    // The moved item already has a correct size field, which is needed
    // for the update.
    if ($oldParent !== NULL) {
      self::updateSizes([(int) $oldParent->id()], FALSE);
    }

    self::updateSizes([(int) $parent->id()], FALSE);

    if ($oldParent !== NULL) {
      self::postOperationHook(
        'move',
        [
          $this,
          $oldParent,
          $parent,
        ]);
      self::log(
        'notice',
        'Moved entity @id ("%name") from @oldId ("%oldName") to @newId ("%newName").',
        [
          '@id'      => $this->id(),
          '%name'    => $this->getName(),
          '@oldId'   => $oldParent->id(),
          '%oldName' => $oldParent->getName(),
          '@newId'   => $parent->id(),
          '%newName' => $parent->getName(),
          'link'     => $this->toLink(t('View'))->toString(),
        ]);
    }
    else {
      self::postOperationHook(
        'move',
        [
          $this,
          NULL,
          $parent,
        ]);
      self::log(
        'notice',
        'Moved entity @id ("%name") from top level to @newId ("%newName").',
        [
          '@id'      => $this->id(),
          '%name'    => $this->getName(),
          '@newId'   => $parent->id(),
          '%newName' => $parent->getName(),
          'link'     => $this->toLink(t('View'))->toString(),
        ]);
    }

    // If the root ID has not changed, there is no need to change
    // descendants. We're done.
    if ($oldRootId === $newRootId) {
      return;
    }

    // Get a list of descendants. If there are none, we're done.
    //
    // TODO it would be handy if this returned folders first in the list,
    // and then files. That way folders would get changed first, which would
    // reduce the chance that some other process could add a file with the
    // wrong root id copied from the parent.
    $descendantIds = $this->findDescendantIds();
    if (empty($descendantIds) === TRUE) {
      return;
    }

    //
    // Queue and execute.
    // ------------------
    // Queue a task to change root IDs and then do same task immediately.
    //
    // If the immediate execution is interrupted, the queued task will finish
    // the work.
    $parameters = [
      'operation' => 'move',
      'ids'       => $descendantIds,
      'rootid'    => $newRootId,
    ];

    $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
    $queue->createQueue();
    $queue->createItem($parameters);

    if (Constants::ENABLE_SET_PHP_TIMEOUT_UNLIMITED === TRUE) {
      // Before running a possibly large task that we'd rather not have
      // interrupted, try to set the PHP timeout to "unlimited".
      // This may not work if the site has PHP timeout changes blocked,
      // and it does nothing to stop web server timeouts.
      drupal_set_time_limit(0);
    }

    self::processMoveTask($parameters);

    return $this;
  }

  /**
   * Moves multiple items to a folder.
   *
   * Each of the indicated items is moved. If an item is a folder, the
   * folder's descendants are moved as well.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to move. Invalid IDs
   *   are silently skipped.
   * @param \Drupal\foldershare\FolderShareInterface $parent
   *   (optional, default = NULL = move to the root list) The parent folder
   *   for the move. When NULL, the moved items are added to the root list.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if a name is already in use in the user's root list.
   *
   * @see ::moveToFolder()
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_move" hook for
   * each item moved.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item moved.
   */
  public static function moveToFolderMultiple(
    array $ids,
    FolderShareInterface $parent = NULL) {
    if ($parent === NULL) {
      self::moveToRootMultiple($ids);
      return;
    }

    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->moveToFolder($parent);
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be moved at this time.')));
    }
  }

  /*---------------------------------------------------------------------
   *
   * Queue task.
   *
   *---------------------------------------------------------------------*/

  /**
   * Processes a move task from the work queue.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from the module's work queue handler.
   *
   * A move task provides a list of IDs for entities to update to a new
   * root ID. The change is NOT recursive - only the specific IDS provided
   * are changed.
   *
   * There is one condition under which an update may not complete fully:
   * - One or more entities are locked by another process.
   * - The folder tree is too large to update before hitting a timeout.
   *
   * If an entity is locked, this method will re-queue the locked item to
   * be updated later.
   *
   * If the ID list is too large to finish before the process is interrupted
   * by a PHP or web server timeout, then the queued task that called this
   * method will be restarted by CRON at a later time. A repeat of the task
   * will skip entities that have already had their root ID changed.
   *
   * @param array $parameters
   *   An associative array of the task's parameters with keys:
   *   - 'operation': Must be 'move'.
   *   - 'ids': An array of integer FolderShare entity IDs to update.
   *   - 'rootid': The new FolderShare entity root ID.
   *
   * @section locking Process locks
   * This method locks items as they are updated. If a lock cannot be acquired,
   * a new task is enqueued to process those locked items.
   *
   * @see ::setRootItemId()
   * @see ::moveToFolder()
   * @see ::moveToFolderMultiple()
   * @see ::moveToRoot()
   * @see ::moveToRootMultiple()
   */
  public static function processMoveTask(array $parameters) {
    //
    // Validate parameters.
    // --------------------
    // The parameters must include an array of entity IDs to update, and
    // the root ID to update them to.
    if (isset($parameters['ids']) === FALSE ||
        is_array($parameters['ids']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'ids' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Move',
        ]);
      return;
    }

    if (isset($parameters['rootid']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'rootid' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Move',
        ]);
      return;
    }

    $rootId = (int) $parameters['rootid'];

    //
    // Change root ID.
    // ---------------
    // Change all root IDs. Requeue on lock exceptions.
    $exceptionIds = [];
    foreach ($parameters['ids'] as $id) {
      $item = self::load((int) $id);
      if ($item !== NULL && $item->getRootItemId() !== $rootId) {
        if ($item->acquireLock() === FALSE) {
          $exceptionIds[] = (int) $id;
        }
        else {
          $item->setRootItemId($rootId);
          $item->save();
          $item->releaseLock();
        }
      }
    }

    if (empty($exceptionIds) === FALSE) {
      $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
      $queue->createItem(
        [
          'operation' => 'move',
          'ids'       => $exceptionIds,
          'rootid'    => $rootId,
        ]);
    }
  }

}
