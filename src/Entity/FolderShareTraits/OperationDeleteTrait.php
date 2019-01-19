<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\LockException;

/**
 * Delete Foldershare entities.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationDeleteTrait {

  /*---------------------------------------------------------------------
   *
   * Delete.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function delete() {
    //
    // Validate.
    // ---------
    // Only delete the item if it has been fully created (i.e. it is not
    // marked as "new"). New items don't have an ID yet, and therefore
    // can't have any subfolders or files yet, so really there's nothing
    // to delete.
    if ($this->isNew() === TRUE) {
      return;
    }

    //
    // Lock, hide, unlock.
    // -------------------
    // Immediately hide the item so that it is no longer visible in the
    // UI or REST listings, and no longer counted in parent folder sizes.
    //
    // Clear access grants for root items. This blocks further non-owner
    // access during deletion.
    //
    // Clear the parent folder's size field so that it can be recalculated
    // to exclude the item being deleted.
    //
    // NOTE:
    // If either of the following lock acquires fail, then some other process
    // has this item or a parent item locked for exclusive access. We cannot
    // safely continue or we risk colliding on entity changes. Since nothing
    // has been delete so far, abort.
    //
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be deleted at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    $parent = NULL;
    if ($this->isRootItem() === FALSE) {
      $parent = $this->getParentFolder();
      // LOCK PARENT FOLDER.
      if ($parent->acquireLock() === FALSE) {
        // UNLOCK THIS ITEM.
        $this->releaseLock();
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The parent folder of the item "@name" is in use and cannot be updated at this time.',
            [
              '@name' => $this->getName(),
            ])));
      }
    }

    $this->setSystemHidden(TRUE);
    if ($this->isRootItem() === TRUE) {
      $this->clearAccessGrants(self::ANY_USER_ID, TRUE);
    }

    $this->save();

    if ($this->isRootItem() === FALSE) {
      // Clear the parent's size field. This marks the parent as in need of
      // size recalculations to account for the deletion of this item.
      $parent->clearSize();
      $parent->save();
    }

    if ($parent !== NULL) {
      // UNLOCK PARENT FOLDER.
      $parent->releaseLock();
    }

    // UNLOCK THIS FOLDER.
    $this->releaseLock();

    //
    // Delete.
    // -------
    // Recurse through this item and its descendants, deleting from the
    // bottom up. Queue a task to do the same delete in case this one is
    // interrupted or cannot complete due to a descendant lock.
    $queue = NULL;
    if ($this->isFolder() === TRUE) {
      $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
      $queue->createQueue();
      $queue->createItem(
        [
          'operation' => 'delete',
          'ids'       => [(int) $this->id()],
        ]);
    }

    // Update the parent's size field and propagate changes upwards
    // through ancestors.
    if ($parent !== NULL) {
      self::updateSizes([(int) $parent->id()], FALSE);
    }

    // Delete. This may throw a lock exception if a descendant is locked.
    // Everything that is not locked will be deleted.
    try {
      $this->deleteInternal(TRUE);
    }
    catch (LockException $e) {
      // If this is a folder, and a task was queued, then that task will
      // finish the delete and we do not need to notify the caller of a
      // problem.
      //
      // But if this is not a folder, or if it is and the task queue was
      // not available, then the item has not been fully deleted and we need
      // to notify the user.
      if ($this->isFolder() === FALSE || $queue === NULL) {
        throw $e;
      }
    }
  }

  /**
   * Deletes multiple items.
   *
   * Each of the indicated items is deleted. If an item is a folder, the
   * folder's descendants are deleted as well.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to delete. Invalid IDs
   *   are silently skipped.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_delete" hook for
   * each item deleted.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item deleted.
   *
   * @see ::delete()
   */
  public static function deleteMultiple(array $ids) {
    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->delete();
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be deleted at this time.')));
    }
  }

  /**
   * Implements item deletion.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * If this item is a file, image, or media item, it is deleted immediately
   * along with its wrapped File or Media entity.
   *
   * If this item is a folder, recursion loops downward through subfolders.
   * Folders are marked as "hidden" as they are encountered.
   *
   * @param bool $useLocks
   *   (optional, default = TRUE) When TRUE, process locks are used around
   *   delete operations. When FALSE, they are not.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   *
   * @section locking Process locks
   * If the $useLocks parameter is TRUE, this method attempts to lock this
   * item for exclusive access before it is deleted. If this item cannot be
   * locked, a LockException is thrown and the item is not deleted.
   *
   * For folders, this method attempts to lock each descendant before it is
   * deleted. If one or more items cannot be locked, everything else is
   * deleted, a task to delete the locked items is queued, and a LockException
   * is thrown.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_delete" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::delete()
   * @see ::deleteAll()
   */
  private function deleteInternal(bool $useLocks = TRUE) {
    if ($this->isFolder() === FALSE) {
      // Delete file, image, or media entity wrapper.
      // --------------------------------------------
      // Lock the item, get its wrapped entity, mark the item as hidden,
      // unlock, delete the item, then delete the wrapped entity.
      //
      // LOCK THIS ITEM, if needed.
      if ($useLocks === TRUE && $this->acquireLock() === FALSE) {
        // NOTE:
        // On a lock exception, this non-folder item cannot be locked
        // because it has been locked for exclusive use by another
        // process. We cannot continue. This item will not be deleted here.
        // The caller may keep track of items with exceptions and queue
        // them for later deletion.
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The item "@name" is in use and cannot be deleted at this time.',
            [
              '@name' => $this->getName(),
            ])));
      }

      // Get attributes. Clear file/media reference. Mark item as hidden.
      //
      // Saving with the file/media reference cleared triggers the File
      // or Media module to decrement the reference count for the underlying
      // entity. When that count reaches zero, it should be deleted (though
      // we force the issue here).
      $file = NULL;
      $media = NULL;

      if ($this->isFile() === TRUE) {
        $file = $this->getFile();
        $this->clearFileId();
      }
      elseif ($this->isImage() === TRUE) {
        $file = $this->getImage();
        $this->clearImageId();
      }
      else {
        $media = $this->getMedia();
        $this->clearMediaId();
      }

      $this->setSystemHidden(TRUE);
      $this->save();

      if ($useLocks === TRUE) {
        // UNLOCK THIS ITEM.
        $this->releaseLock();
      }

      // Let the parent class finish deleting the object.
      $thisId = $this->id();
      $thisName = $this->getName();
      parent::delete();

      self::postOperationHook(
        'delete',
        [
          $thisId,
        ]);
      self::log(
        'notice',
        'Deleted entity @id ("%name").',
        [
          '@id'   => $thisId,
          '%name' => $thisName,
        ]);

      // Force deletion of the file/media since it is supposed to only be
      // referenced by the wrapper we just deleted.
      if ($file !== NULL) {
        $file->delete();
      }

      if ($media !== NULL) {
        $media->delete();
      }
    }
    else {
      // Delete folder and its children.
      // -------------------------------
      // Lock the folder, mark it as hidden, recurse over its children,
      // then unlock and delete the folder.
      //
      // LOCK THIS ITEM, if needed.
      if ($useLocks === TRUE && $this->acquireLock() === FALSE) {
        // NOTE:
        // On a lock exception, this folder item cannot be locked
        // because it has been locked for exclusive use by another
        // process. We cannot continue. This folder and all of its
        // descendants will not be deleted here. The caller may keep
        // track of items with exceptions and queue them for later deletion.
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The item "@name" is in use and cannot be deleted at this time.',
            [
              '@name' => $this->getName(),
            ])));
      }

      // Get attributes. Mark item as hidden.
      $oldHidden = $this->isSystemHidden();

      $this->setSystemHidden(TRUE);
      $this->save();

      $nExceptions = 0;
      $childIds = $this->findChildrenIds();
      foreach ($childIds as $childId) {
        $child = FolderShare::load($childId);
        if ($child !== NULL) {
          try {
            $child->deleteInternal($useLocks);
          }
          catch (LockException $e) {
            ++$nExceptions;
          }
        }
      }

      if ($nExceptions !== 0) {
        $this->setSystemHidden($oldHidden);
        $this->save();
        if ($useLocks === TRUE) {
          // UNLOCK THIS ITEM.
          $this->releaseLock();
        }

        throw new LockException(Utilities::createFormattedMessage(
          t('One or more items are in use and cannot be deleted at this time.')));
      }

      if ($useLocks === TRUE) {
        // UNLOCK THIS ITEM.
        $this->releaseLock();
      }

      // Let the parent class finish deleting the object.
      $thisId = $this->id();
      $thisName = $this->getName();
      parent::delete();

      self::postOperationHook(
        'delete',
        [
          $thisId,
        ]);
      self::log(
        'notice',
        'Deleted entity @id ("%name").',
        [
          '@id'   => $thisId,
          '%name' => $thisName,
        ]);
    }
  }

  /*---------------------------------------------------------------------
   *
   * Delete all.
   *
   *---------------------------------------------------------------------*/

  /**
   * Deletes all items, or just those owned by a user.
   *
   * <B>This method is intended for use during maintenance mode and by
   * site administrators.</B>
   *
   * This function deletes all items, or a specific user's items, and does
   * so quickly and without process locks. It can be used as part of
   * deleting a user's account or prior to uninstalling the module.
   *
   * @param int $uid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   of the user for whome to delete all content. If the user ID is
   *   FolderShareInterface::ANY_USER_ID or negative, all content is
   *   deleted for all users.
   *
   * @section queue Queued delete
   * In most cases, deletion occurs immediately. When this method returns,
   * the relevant item will be gone. However, for very large numbers of
   * items, deletion may be interrupted by a timeout. In order to complete
   * the delete, a task is enqueued on a work queue serviced by the site's
   * CRON configuration. This will delay full deletion until that queue
   * is serviced.
   *
   * @section locking Process locks
   * This method does not lock items before they are deleted. This insures
   * that an administrator can delete the content, even if it is in use.
   * However, it is recommended that the site be in maintenance mode so
   * that there cannot be anyone using the items being deleted.
   *
   * @see ::delete()
   */
  public static function deleteAll(int $uid = self::ANY_USER_ID) {
    //
    // Delete everything.
    // ------------------
    // It is tempting to just truncate the folder table, but this
    // doesn't give other modules a chance to clean up in response to hooks.
    // The primary relevant module is the File module, which keeps reference
    // counts for files. But other modules may have added fields to
    // folders, and they too need a graceful way of handling deletion.
    if (Constants::ENABLE_SET_PHP_TIMEOUT_UNLIMITED === TRUE) {
      // Before running a recursive task that we'd rather not have
      // interrupted, try to set the PHP timeout to "unlimited".
      // This may not work if the site has PHP timeout changes blocked,
      // and it does nothing to stop web server timeouts.
      drupal_set_time_limit(0);
    }

    // Mark all roots as hidden and clear their access grants. This will
    // block any further access to the folder trees.
    $rootIds = self::findAllRootItemIds($uid);
    foreach ($rootIds as $id) {
      $item = FolderShare::load($id);
      if ($item !== NULL) {
        $item->setSystemHidden(TRUE);
        $item->clearAccessGrants();
        $item->save();
      }
    }

    // Queue deletion of roots and isolated items.
    $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
    $queue->createQueue();
    $queue->createItem(
      [
        'operation' => 'delete',
        'ids'       => $rootIds,
      ]);

    if ($uid >= 0) {
      // Also get a list of all IDs for items owned by the user.
      // While we'd like to restrict this list to only isolated items,
      // that isn't possible until the above root deletions have been done.
      // So, this list will be redundant. The queue worker will silently
      // skip items already deleted.
      $queue->createItem(
        [
          'operation' => 'delete',
          'ids'       => self::findAllIds($uid),
        ]);
    }

    // Delete roots and isolated items, recursing through subfolders.
    $rootIds = self::findAllRootItemIds($uid);
    foreach ($rootIds as $id) {
      $item = FolderShare::load($id);
      if ($item !== NULL) {
        $item->deleteInternal(FALSE);
      }
    }

    $isolatedIds = self::findAllIds($uid);
    foreach ($isolatedIds as $id) {
      $item = FolderShare::load($id);
      if ($item !== NULL) {
        $item->deleteInternal(FALSE);
      }
    }
  }

  /*---------------------------------------------------------------------
   *
   * Queue task.
   *
   *---------------------------------------------------------------------*/

  /**
   * Processes a delete task from the work queue.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from the module's work queue handler.
   *
   * A delete task provides a list of IDs for entities to delete. For
   * entities that are folders, all of their descendants are deleted as well.
   *
   * There are two conditions under which deletion may not complete fully:
   * - One or more descendants are locked by another process.
   * - The folder tree is too large to delete before hitting a timeout.
   *
   * If a descendant is locked, this method will delete all descendants
   * that it can, leave the highest undeleted item marked as "hidden", then
   * add a new delete task to the queue to try deletion again.
   *
   * If the folder tree is too large to finish before the process is
   * interrupted by a PHP or web server timeout, then the queued task that
   * called this method will be restarted by CRON at a later time. A repeat
   * of the task will find fewer things to delete and will continue deleting.
   * This can be interrupted again again, and eventually the entire folder
   * tree will be deleted, this method will return normally, and the queued
   * task will complete.
   *
   * @param array $parameters
   *   An associative array of the task's parameters with keys:
   *   with the following fields:
   *   - 'operation': Must be 'delete'.
   *   - 'ids': An array of integer FolderShare entity IDs to delete.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_deleted" hook for
   * each item deleted.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item deleted.
   */
  public static function processDeleteTask(array $parameters) {
    //
    // Validate parameters.
    // --------------
    // The parameters must be an array of entity IDs to delete.
    if (isset($parameters['ids']) === FALSE ||
        is_array($parameters['ids']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'ids' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Delete',
        ]);
      return;
    }

    //
    // Delete each item.
    // -----------------
    // Delete each item, if it hasn't already been deleted. Requeue on
    // lock exceptions.
    $exceptionIds = [];
    foreach ($parameters['ids'] as $id) {
      $item = FolderShare::load((int) $id);
      if ($item !== NULL) {
        try {
          $item->deleteInternal(FALSE);
        }
        catch (LockException $e) {
          $exceptionIds[] = (int) $id;
        }
      }
    }

    if (empty($exceptionIds) === FALSE) {
      $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
      $queue->createItem(
        [
          'operation' => 'delete',
          'ids'       => $exceptionIds,
        ]);
    }
  }

}
