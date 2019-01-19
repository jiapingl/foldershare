<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\Exception\SystemException;

/**
 * Copy FolderShare entities.
 *
 * This trait includes methods to copy FolderShare entities and place
 * them in a folder or at the root level.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationCopyTrait {

  /*---------------------------------------------------------------------
   *
   * Copy to root.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function copyToRoot(string $newName = '') {
    //
    // Check name legality.
    // --------------------
    // If there is a new name, throw an exception if it is not suitable.
    $name = $this->getName();
    if (empty($newName) === FALSE) {
      // The checkName() function throws an exception if the name is too
      // long or uses illegal characters.
      $this->checkName($newName);
      $name = $newName;
    }

    //
    // Lock, copy, and unlock.
    // -----------------------
    // Copy this item to the root list immediately.
    //
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be copied at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    // LOCK ROOT LIST.
    if (self::acquireRootLock() === FALSE) {
      // UNLOCK THIS ITEM.
      $this->releaseLock();
      throw new LockException(Utilities::createFormattedMessage(
        t('The system is busy updating and cannot copy the item at this time.')));
    }

    // Check if there is already a root item with the same name.
    $uid = \Drupal::currentUser()->id();
    if (empty(self::findAllRootItemIds($uid, $name)) === FALSE) {
      // UNLOCK ROOT LIST.
      // UNLOCK THIS ITEM.
      self::releaseRootLock();
      $this->releaseLock();
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" is already in use among top-level items.',
          [
            '@name' => $name,
          ]),
        t('Please rename the item before copying it, or copy it to some other place.')));
    }

    // Copy this item into the root list, reset access grants, and set
    // the name, if needed. For a folder, enable it.
    try {
      $copy = $this->duplicateInternal(
        $uid,
        self::USER_ROOT_LIST,
        self::USER_ROOT_LIST,
        $name,
        TRUE);
    }
    catch (SystemException $e) {
      // A file could not be copied.
      //
      // UNLOCK ROOT LIST.
      // UNLOCK THIS ITEM.
      self::releaseRootLock();
      $this->releaseLock();

      // On a system exception, the copy aborts while trying to create the
      // first item. We cannot finish it because we cannot fix the underlying
      // system problem. Nothing will have been copied so far.
      throw $e;
    }
    catch (\Exception $e) {
      // Unknown error
      //
      // UNLOCK ROOT LIST.
      // UNLOCK THIS ITEM.
      self::releaseRootLock();
      $this->releaseLock();
      throw $e;
    }

    // UNLOCK ROOT LIST.
    // UNLOCK THIS ITEM.
    self::releaseRootLock();
    $this->releaseLock();

    self::postOperationHook(
      'copy',
      [
        $copy,
        $this,
      ]);
    self::log(
      'notice',
      'Copied entity @id ("%name") to entity @copyid ("%copyname").',
      [
        '@id'       => $this->id(),
        '%name'     => $this->getName(),
        '@copyid'   => $copy->id(),
        '%copyname' => $copy->getName(),
        'link'      => $copy->toLink(t('View'))->toString(),
      ]);

    // Get a list of children.
    if ($this->isFolder() === FALSE) {
      return $copy;
    }

    $childIds = $this->findChildrenIds();
    if (empty($childIds) === TRUE) {
      return $copy;
    }

    //
    // Queue and execute.
    // ------------------
    // Queue a task to recursively copy children and then do same task
    // immediately.
    //
    // If the immediate execution is interrupted, the queued task will finish
    // the work.
    $parameters = [
      'operation'     => 'copy',
      'ids'           => $childIds,
      'destinationid' => $copy->id(),
    ];

    $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
    $queue->createQueue();
    $queue->createItem($parameters);

    if (Constants::ENABLE_SET_PHP_TIMEOUT_UNLIMITED === TRUE) {
      // Before running a recursive task that we'd rather not have
      // interrupted, try to set the PHP timeout to "unlimited".
      // This may not work if the site has PHP timeout changes blocked,
      // and it does nothing to stop web server timeouts.
      drupal_set_time_limit(0);
    }

    self::processCopyTask($parameters);

    return $copy;
  }

  /**
   * Copies multiple items to the root.
   *
   * Each of the indicated items is copied. If an item is a folder, the
   * folder's descendants are copied as well. See copyToRoot() for
   * details.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to coopy. Invalid IDs
   *   are silently skipped.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if a name is already in use in the user's root list.
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurs, such as a
   *   file system becomes unreadable/unwritable, gets full, or gores offline.
   *
   * @section locking Process locks
   * The root list and this item are locked as the item is copied. Thereafter,
   * recursion locks each child item to copy and its new parent as each copy
   * is done.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   * @see ::copyToRoot()
   */
  public static function copyToRootMultiple(array $ids) {
    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->copyToRoot();
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be copied at this time.')));
    }
  }

  /*---------------------------------------------------------------------
   *
   * Copy to folder.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function copyToFolder(
    FolderShareInterface $parent = NULL,
    string $newName = '') {
    //
    // Validate.
    // ---------
    // If there is no parent, copy to the root. Otherwise confirm that the
    // parent is a folder and that it is not a descendant of this item.
    if ($parent === NULL) {
      return $this->copyToRoot();
    }

    if ($parent->isFolder() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
         '@method was called with a copy destination that is not a folder.',
          [
            '@method' => 'FolderShare::moveToFolder',
          ])));
    }

    $parentId = (int) $parent->id();
    if ($parentId === (int) $this->id() ||
        $this->isAncestorOfFolderId($parentId) === TRUE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The item "@name" cannot be copied into one of its own descendants.',
          [
            '@name' => $this->getName(),
          ])));
    }

    //
    // Check name legality.
    // --------------------
    // If there is a new name, throw an exception if it is not suitable.
    if (empty($newName) === FALSE) {
      // The checkName() function throws an exception if the name is too
      // long or uses illegal characters.
      $this->checkName($newName);
    }
    else {
      $newName = $this->getName();
    }

    //
    // Lock, copy, and unlock.
    // -----------------------
    // Copy this item to the root list immediately.
    //
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be copied at this time.',
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
          'The destination folder "@name" is in use and cannot be changed at this time.',
          [
            '@name' => $parent->getName(),
          ])));
    }

    // Check if there is already a root item with the same name.
    if (self::findNamedChildId($parentId, $newName) !== FALSE) {
      // UNLOCK NEW PARENT FOLDER.
      // UNLOCK THIS ITEM.
      $parent->releaseLock();
      $this->releaseLock();
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" is already in use in the destination folder.',
          [
            '@name' => $newName,
          ]),
        t('Please rename the item before copying it, or copy it to some other place.')));
    }

    // Copy this item into the parent, and set the name, if needed.
    // For a folder, enable it.
    try {
      $rootId = $parent->getRootItemId();
      $uid = \Drupal::currentUser()->id();
      $copy = $this->duplicateInternal($uid, $parentId, $rootId, $newName, TRUE);
    }
    catch (SystemException $e) {
      // A file could not be copied.
      //
      // UNLOCK NEW PARENT FOLDER.
      // UNLOCK THIS ITEM.
      $parent->releaseLock();
      $this->releaseLock();

      // On a system exception, the copy aborts while trying to create the
      // first item. We cannot finish it because we cannot fix the underlying
      // system problem. Nothing will have been copied so far.
      throw $e;
    }
    catch (\Exception $e) {
      // An unknown error occurred.
      //
      // UNLOCK NEW PARENT FOLDER.
      // UNLOCK THIS ITEM.
      $parent->releaseLock();
      $this->releaseLock();
      throw $e;
    }

    // Clear the parent's size field. This marks the parent as in need of
    // size recalculations to account for the addition of the above copy and
    // any children copied next.
    $parent->clearSize();
    $parent->save();

    // UNLOCK NEW PARENT FOLDER.
    // UNLOCK THIS ITEM.
    $parent->releaseLock();
    $this->releaseLock();

    // Update the parent's size field and propagate changes upwards
    // through ancestors. The copy already has a size field appropriate
    // for its children, even though they haven't been copied yet.
    self::updateSizes([(int) $parent->id()], FALSE);

    self::postOperationHook(
      'copy',
      [
        $copy,
        $this,
      ]);
    self::log(
      'notice',
      'Copied entity @id ("%name") to entity @copyid ("%copyname").',
      [
        '@id'       => $this->id(),
        '%name'     => $this->getName(),
        '@copyid'   => $copy->id(),
        '%copyname' => $copy->getName(),
        'link'      => $copy->toLink(t('View'))->toString(),
      ]);

    // Get a list of children.
    if ($this->isFolder() === FALSE) {
      return $copy;
    }

    $childIds = $this->findChildrenIds();
    if (empty($childIds) === TRUE) {
      return $copy;
    }

    //
    // Queue and execute.
    // ------------------
    // Queue a task to recursively copy children and then do same task
    // immediately.
    //
    // If the immediate execution is interrupted, the queued task will finish
    // the work.
    $parameters = [
      'operation'     => 'copy',
      'ids'           => $childIds,
      'destinationid' => $copy->id(),
    ];

    $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
    $queue->createQueue();
    $queue->createItem($parameters);

    if (Constants::ENABLE_SET_PHP_TIMEOUT_UNLIMITED === TRUE) {
      // Before running a recursive task that we'd rather not have
      // interrupted, try to set the PHP timeout to "unlimited".
      // This may not work if the site has PHP timeout changes blocked,
      // and it does nothing to stop web server timeouts.
      drupal_set_time_limit(0);
    }

    self::processCopyTask($parameters);

    return $copy;
  }

  /**
   * Copies multiple items to a folder.
   *
   * Each of the indicated items is copied. If an item is a folder, the
   * folder's descendants are copied as well. See copyToFolder() for
   * details.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to copy. Invalid IDs
   *   are silently skipped.
   * @param \Drupal\foldershare\FolderShareInterface $parent
   *   (optional, default = NULL = copy to the root list) The parent folder
   *   for the copy. When NULL, the copy is added to the root list.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if a name is already in use in the user's root list.
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurs, such as a
   *   file system becomes unreadable/unwritable, gets full, or gores offline.
   *
   * @section locking Process locks
   * This item and the new parent are locked as the item is copied. This
   * repeats for each item copied, recursing through all children of this item.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   * @see ::copyToFolder()
   */
  public static function copyToFolderMultiple(
    array $ids,
    FolderShareInterface $parent = NULL) {

    if (empty($ids) === TRUE) {
      return;
    }

    if ($parent === NULL) {
      self::copyToRootMultiple($ids);
      return;
    }

    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->copyToFolder($parent);
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be copied at this time.')));
    }
  }

  /*---------------------------------------------------------------------
   *
   * Duplicate.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    // This method's actions are slightly in conflict with the base Entity
    // class's definition for the createDuplicate() method.
    //
    // createDuplicate() is supposed to:
    // - Create and return a clone of $this with all fields filled in,
    //   except for an ID, so that saving it will insert the new entity
    //   into the storage system.
    //
    // This is not practical for FolderShare, where duplicating the entity
    // also needs to duplicate its children. And those children need to
    // point back to the parent, so the parent has to have been fully
    // created first.
    //
    // Therefore, this entity's implementation of createDuplicate() creates
    // AND saves the entity, thereby creating the ID and commiting the new
    // entity to storage. If the caller calls save() again on the returned
    // entity, this won't hurt anything. But if they think that skipping a
    // call to save() will avoid saving the entity, that won't be true.
    return $this->duplicate();
  }

  /**
   * {@inheritdoc}
   */
  public function duplicate() {
    $uid = \Drupal::currentUser()->id();
    $name = $this->getName();
    $parent = $this->getParentFolder();

    // Since duplication always creates a new item in the same location as
    // the current item, the new item's name always collides with the
    // current item. We must create a new name, and then copy to it.
    if ($parent === NULL) {
      // Create a duplicate with a unique name within the root list.
      $rootNames = self::findAllRootItemNames($uid);
      $newName = self::createUniqueName($rootNames, $name);
      if ($newName === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t('A unique name could not be created for the duplicate because too many names are in use.')));
      }

      return $this->copyToRoot($newName);
    }
    else {
      // Create a duplicate with a unique name within the parent.
      $siblingNames = $parent->findChildrenNames();
      $newName = self::createUniqueName($siblingNames, $name);
      if ($newName === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t('A unique name could not be created for the duplicate because too many names are in use.')));
      }

      return $this->copyToFolder($parent, $newName);
    }
  }

  /**
   * Duplicates multiple items.
   *
   * Each of the indicated items is duplicated. If an item is a folder, the
   * folder's descendants are duplicated as well.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to duplicate. Invalid IDs
   *   are silently skipped.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if a name is already in use in the user's root list.
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurs, such as a
   *   file system becomes unreadable/unwritable, gets full, or gores offline.
   *
   * @section hooks Hooks
   * This method creates one or more new file and folder entities. Multiple
   * hooks are invoked as a side effect. See ::createFolder() and ::addFile().
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section locking Process locks
   * This folder, the parent folder, and all subfolders are locked
   * for exclusive editing access by this function for the duration
   * of the copy.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   * @see ::duplicate()
   */
  public static function duplicateMultiple(array $ids) {
    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->duplicate();
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be copied at this time.')));
    }
  }

  /*---------------------------------------------------------------------
   *
   * Copy implementation.
   *
   *---------------------------------------------------------------------*/

  /**
   * Duplicates this item, but not its children.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The item is copied and its owner ID, parent folder ID, root ID, and
   * name updated to the given values. Folders are marked disabled. Access
   * grants are reset to defaults, and the item is saved.
   *
   * @param int $newUid
   *   The duplicate's user ID.
   * @param int $newParentId
   *   The duplicate's parent folder ID. USER_ROOT_LIST indicates it has
   *   no parent and is a root item.
   * @param int $newRootId
   *   The duplicate's root ID. USER_ROOT_LIST indicates it is a root item.
   * @param string $newName
   *   The duplicate's new name.
   * @param bool $enabled
   *   For folders only, whether to mark the folder enabled.
   *
   * @return \Drupal\foldershare\Entity\FolderShare
   *   Returns the duplicate. It will already have been set and saved.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   For files, throws an exception if a serious system error occurs while
   *   duplicating the underlying local file. System errors may indicate a
   *   file system has become unreadable/unwritable, is full, or is offline.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should have locked this
   * item first.
   *
   * @see ::copyToRoot()
   * @see ::copyToFolder()
   */
  private function duplicateInternal(
    int $newUid,
    int $newParentId,
    int $newRootId,
    string $newName,
    bool $enabled = TRUE) {

    // Duplicate all fields as-is, then set the new owner, parent, root,
    // and name. For root items, set access grants to defaults.
    $copy = parent::createDuplicate();
    $copy->setOwnerIdInternal($newUid);
    $copy->setParentFolderId($newParentId);
    $copy->setRootItemId($newRootId);
    $copy->setName($newName);
    $copy->clearAccessGrants();

    // Copy file fields.
    switch ($copy->getKind()) {
      case self::FOLDER_KIND:
        $copy->setSystemDisabled(!$enabled);
        $copy->save();
        return $copy;

      case self::FILE_KIND:
      case self::IMAGE_KIND:
        if ($this->isFile() === TRUE) {
          $file = $this->getFile();
        }
        else {
          $file = $this->getImage();
        }

        // Duplicate the file entity, then set the new owner and name.
        $newFile = self::duplicateFileEntityInternal($file);
        $newFile->setOwnerId($newUid);
        $newFile->setFilename($newName);
        $newFile->setFileUri(FileUtilities::getFileUri($newFile));
        $newFile->save();

        if ($this->isFile() === TRUE) {
          $copy->setFileId($newFile->id());
        }
        else {
          $copy->setImageId($newFile->id());
        }

        $copy->save();
        return $copy;

      case self::MEDIA_KIND:
        // Duplicate the media entity, then set the new owner and name.
        $newMedia = $this->getMedia()->createDuplicate();
        $newMedia->setOwnerId($newUid);
        $newMedia->setName($newName);
        $newMedia->save();

        $copy->setMediaId($newMedia->id());
        $copy->save();
        return $copy;

      default:
        $copy->save();
        return $copy;
    }
  }

  /**
   * Copies this file or folder into a parent folder, recursing as needed.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * If an item with the same name does not exist already in the parent,
   * it is created and copying recurses into children, if any.
   *
   * If an item with the same name already exists in the parent, it may be
   * from a previous copy that was interrupted. If that copy is enabled, that
   * previous copy finished and this function returns.
   *
   * Otherwise, a previous copy left a disabled folder in the parent to
   * indicate an incomplete copy. Thisfunction recurses into that folder
   * and completes the copy.
   *
   * Whenever a folder is copied, it is initially disabled, then reenabled
   * after all children have been copied.
   *
   * @param \Drupal\foldershare\FolderShareInterface $parent
   *   The destination parent folder.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the new copy.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurs, such as a
   *   file system becomes unreadable/unwritable, gets full, or gores offline.
   *
   * @section locking Process locks
   * This item and the parent folder are locked during the copy.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   * @see ::copyToRoot()
   * @see ::copyToFolder()
   */
  private function copyToFolderInternal(FolderShareInterface $parent) {
    //
    // Check if done.
    // --------------
    // If the item already exists in the parent, and it is enabled, then
    // we're done.
    $copy = NULL;
    $copyId = self::findNamedChildId($parent->id(), $this->getName());

    if ($copyId !== FALSE) {
      $copy = self::load($copyId);
      if ($copy->isFolder() === FALSE || $copy->isSystemDisabled() === FALSE) {
        return $copy;
      }
    }

    if ($copy === NULL) {
      //
      // Lock, duplicate, and unlock.
      // ----------------------------
      // Lock this item and the parent, duplicate the item without copying
      // children, and unlock. Put the duplicate in the parent, giving it
      // the new user ID and name. If a folder, disable it during the copy.
      //
      // LOCK THIS ITEM.
      if ($this->acquireLock() === FALSE) {
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The item "@name" is in use and cannot be copied at this time.',
            [
              '@name' => $this->getName(),
            ])));
      }

      // LOCK PARENT FOLDER.
      if ($parent->acquireLock() === FALSE) {
        // UNLOCK THIS ITEM.
        $this->releaseLock();
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The item "@name" is in use and cannot be changed at this time.',
            [
              '@name' => $parent->getName(),
            ])));
      }

      try {
        $copy = $this->duplicateInternal(
          \Drupal::currentUser()->id(),
          (int) $parent->id(),
          $parent->getRootItemId(),
          $this->getName(),
          FALSE);

        // UNLOCK PARENT FOLDER.
        // UNLOCK THIS ITEM.
        $parent->releaseLock();
        $this->releaseLock();
      }
      catch (SystemException $e) {
        // A file could not be copied.
        //
        // UNLOCK PARENT FOLDER.
        // UNLOCK THIS ITEM.
        $parent->releaseLock();
        $this->releaseLock();

        // On a system exception, the copy aborts while trying to create a
        // file. We cannot finish it because we cannot fix the underlying
        // system problem. Nothing more can be copied.
        throw $e;
      }
      catch (\Exception $e) {
        // An unknown exception occurred.
        //
        // UNLOCK PARENT FOLDER.
        // UNLOCK THIS ITEM.
        $parent->releaseLock();
        $this->releaseLock();
        throw $e;
      }
    }

    self::postOperationHook(
      'copy',
      [
        $copy,
        $this,
      ]);
    self::log(
      'notice',
      'Copied entity @id ("%name") to entity @copyid ("%copyname").',
      [
        '@id'       => $this->id(),
        '%name'     => $this->getName(),
        '@copyid'   => $copy->id(),
        '%copyname' => $copy->getName(),
        'link'      => $copy->toLink(t('View'))->toString(),
      ]);

    if ($copy->isFolder() === FALSE) {
      return $copy;
    }

    //
    // Copy children, if needed.
    // -------------------------
    // Recurse to copy children, if any. The folder is enabled after all
    // children have been copied.
    $nLockExceptions = 0;
    foreach ($this->findChildrenIds() as $id) {
      $child = self::load($id);
      if ($child !== NULL) {
        try {
          $child->copyToFolderInternal($copy);
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
        catch (SystemException $e) {
          // A file could not be copied.
          $copy->setSystemDisabled(FALSE);
          $copy->save();

          // On a system exception, the copy aborts while trying to create a
          // file. We cannot finish it because we cannot fix the underlying
          // system problem. Nothing more can be copied.
          throw $e;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be copied at this time.')));
    }

    $copy->setSystemDisabled(FALSE);
    $copy->save();
    return $copy;
  }

  /*---------------------------------------------------------------------
   *
   * Queue task.
   *
   *---------------------------------------------------------------------*/

  /**
   * Processes a copy task from the work queue.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from the module's work queue handler.
   *
   * A copy task provides a list of IDs for entities to copy. For
   * entities that are folders, all of their descendants are copied as well.
   *
   * There are two conditions under which copying may not complete fully:
   * - One or more descendants are locked by another process.
   * - The folder tree is too large to copy before hitting a timeout.
   *
   * If a descendant is locked, this method will copy all descendants
   * that it can, leave each incomplete folder marked as "disabled", then
   * add a new copy task to the queue to try copying again.
   *
   * If the folder tree is too large to finish before the process is
   * interrupted by a PHP or web server timeout, then the queued task that
   * called this method will be restarted by CRON at a later time. A repeat
   * of the task will traverse and only process subtrees marked as "disabled".
   * Any item already copied will not be copied again.  This can be
   * interrupted again again, and eventually the entire folder tree will be
   * copied, this method will return normally, and the queued task will
   * complete.
   *
   * @param array $parameters
   *   An associative array of the task's parameters:
   *   - 'operation': Must be 'copy'.
   *   - 'ids': An array of integer FolderShare entity IDs to copy.
   *   - 'destinationid': The integer FolderShare entity ID of the folder to
   *     contain the copies.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   */
  public static function processCopyTask(array $parameters) {
    //
    // Validate parameters.
    // --------------------
    // The parameters must be an array of entity IDs to copy, and a new
    // destination ID for where to copy them to.
    if (isset($parameters['ids']) === FALSE ||
        is_array($parameters['ids']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'ids' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Copy',
        ]);
      return;
    }

    if (isset($parameters['destinationid']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'destinationid' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Copy',
        ]);
      return;
    }

    $destinationId = (int) $parameters['destinationid'];
    $destination = self::load($destinationId);
    if ($destination === NULL) {
      // Destination is gone. Cannot finish.
      return;
    }

    //
    // Copy folder.
    // ------------
    // Recursively copy each item into the destination. Copying returns
    // immediately if the item already exists in the destination.
    // Requeue on lock exceptions.
    $exceptionIds = [];
    foreach ($parameters['ids'] as $id) {
      $item = self::load((int) $id);
      if ($item !== NULL) {
        try {
          $item->copyToFolderInternal($destination);
        }
        catch (LockException $e) {
          $exceptionIds[] = (int) $id;
        }
        catch (SystemException $e) {
          // A file could not be copied.
          //
          // On a system exception, the copy aborts and is incomplete.
          // We cannot finish it because we cannot fix the underlying
          // system problem. Anything copied so far is good. The
          // folder tree is not corrupted, just incomplete.
          break;
        }
      }
    }

    //
    // Re-queue unupdated items.
    // -------------------------
    // Re-queue anything that couldn't be completed due to a lock exception.
    if (empty($exceptionIds) === FALSE) {
      $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
      $queue->createItem(
        [
          'operation'     => 'copy',
          'ids'           => $exceptionIds,
          'destinationid' => $destinationId,
        ]);
    }
  }

}
