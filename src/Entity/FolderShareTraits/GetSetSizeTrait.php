<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;

/**
 * Get/set FolderShare entity size field.
 *
 * This trait includes get and set methods for FolderShare entity size field,
 * along with methods to traverse the folder tree and update the field
 * the field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetSizeTrait {

  /*---------------------------------------------------------------------
   *
   * Size field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears this item's storage size to empty, if it is a folder.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * For file, image, and media items, the size field mirrors an equivalent
   * size stored with the underlying File or Media entity. The mirror copy
   * is made when the FolderShare entity is created and does not change
   * thereafter.
   *
   * For folder items, the size field is the sum of size fields for all
   * descendants. The field is initialized to zero when the folder is
   * created. It is cleared to NULL here to flag that the size needs to be
   * recalculated due to changes in the folder's descendants. Size
   * recalculation can be done at the end of a series of descendant
   * operations (such as move, copy, and delete) or by a work queue task.
   *
   * If this item is not a folder, this method has no effect.
   *
   * The caller must call save() for the change to take effect.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getSize()
   * @see ::setSize()
   * @see ::updateSizes()
   */
  private function clearSize() {
    if ($this->isFolder() === TRUE) {
      $this->set('size', NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSize() {
    $value = $this->get('size')->getValue();
    if (empty($value) === TRUE) {
      return FALSE;
    }

    return $value[0]['value'];
  }

  /**
   * Sets the item's storage size, in bytes.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The size value is not validated. It is presumed to be a non-negative
   * value for items with a size, and negative if the size field should be
   * cleared. Size fields are normally only cleared for folders in order
   * to indicate that the folder's descendant sizes need to be recalculated.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $size
   *   The non-negative size in bytes, or negative to clear the field.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearSize()
   * @see ::getSize()
   * @see ::updateSizes()
   */
  private function setSize(int $size) {
    if ($size < 0) {
      $this->set('size', NULL);
    }
    else {
      $this->set('size', $size);
    }

    return $this;
  }

  /*---------------------------------------------------------------------
   *
   * Size updates.
   *
   *---------------------------------------------------------------------*/

  /**
   * Updates folder sizes, in bytes.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The size field of folders contains the sum of the sizes of all
   * descendants. In order to keep this field uptodate, changes to any
   * descendants (e.g. add, delete, move) must cause all ancestor folder
   * sizes to be updated. When multiple descendant changes take place,
   * a recursive traversal downwards to update descendant folders first
   * is required. This function does both.
   *
   * The given array lists the entity IDs for folders who's descendants
   * may have changed and who's sizes now need to be updated. Updating
   * proceeds in two steps:
   * - Traverse downward to update descendant folder sizes.
   * - Traverse upward to update ancestor folder sizes.
   *
   * During a descendant update, a recursive traversal descends down to
   * all children of the current folder. From bottom up, children sizes
   * are summed and used to set their parent's folder size. This proceeds
   * for all subfolders of the current folder, and ends with an update
   * to this folder.
   *
   * During an ancestor update, a recursive traversal rises up to the
   * folder's root. At each step, a parent folder is updated to be
   * the sum of its immediate children. Afterwards, the parent's parent is
   * processed in the same way, up to and including the root. Note
   * that updating a parent *does not* recursively descend through all of
   * that parent's children. This function assumes that the child sizes
   * are already uptodate and simply sums them to set the parent's size.
   *
   * When $overwrite is TRUE, downwards traversals enter every folder,
   * recursively, and sum child sizes in order to compute a new folder size.
   * For a deep or wide folder tree, this can take some time and is best
   * done in maintenance mode and only when none of the current folder
   * sizes are trusted.
   *
   * When $overwrite is FALSE, downwards traversals skip folders with
   * non-NULL sizes. Since adding or deleting content from a folder always sets
   * the folder's size to NULL, this downwards traversal focuses only on
   * changed folders.
   *
   * System hidden items are silently skipped. They are not counted
   * in the size recorded in a parent folder or its ancestors.
   *
   * @param array $itemIds
   *   The entity IDs of folders whose sizes need to be updated. An empty
   *   array or invalid entity IDs are silently skipped.
   * @param bool $overwrite
   *   (optional, default = FALSE) When TRUE, each descendant folder's size is
   *   updated to the summed size of its children, recursively, without
   *   checking if the folder already has a size. When FALSE, a folder's
   *   size is only updated if it is empty.
   *
   * @section queue Queued size update
   * This method tries to perform the full size update immediately. If there
   * are a lot of descendants or ascendants to update, this may take too long
   * for the site's PHP or web server timeouts and the update will be
   * interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining
   * items are updated.
   *
   * @section locking Process locks
   * Each folder in need of an update is locked during the update.
   *
   * @see ::clearSize()
   * @see ::getSize()
   * @see ::setSize()
   */
  private static function updateSizes(array $itemIds, bool $overwrite = FALSE) {
    if (empty($itemIds) === TRUE) {
      return;
    }

    //
    // Queue and execute.
    // ------------------
    // Queue a task to recursively update sizes and then do same task
    // immediately.
    //
    // If the immediate execution is interrupted, the queued task will finish
    // the work.
    $parameters = [
      'operation' => 'updatesizes',
      'ids'       => $itemIds,
      'overwrite' => $overwrite,
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

    self::processUpdateSizesTask($parameters);
  }

  /**
   * Updates folder storage sizes, in bytes, recursing down through children.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * This method changes sizes on folders in a folder tree by recursing
   * downward through a folder's children. After all children of this folder
   * are updated, this folder is updated.
   *
   * If this item is not a folder, this method has no effect and just
   * returns the item's size.
   *
   * If this item is a folder, but it is marked as hidden or disabled, this
   * method has no effect and just returns 0.
   *
   * If the folder has no children, the size is set to zero.
   *
   * If the folder already has a size, and $overwrite is FALSE (the default),
   * the update skips the folder. This avoids wasting time on folders
   * whose sizes don't need an update. To force a size update, set $overwrite
   * to TRUE, or call clearSize() first.
   *
   * NOTE: Setting $overwrite to TRUE is discouraged since it can cause a
   * traversal to do a lot of work on big folder trees. This may hit an
   * execution time limit and cause incomplete results. Queue workers should
   * *never* set $overwrite to TRUE because it will cause aborted worker tasks
   * to have to start over and never complete.
   *
   * After the size is updated, the folder is saved.
   *
   * System hidden items are skipped.
   *
   * @param bool $overwrite
   *   (optional, default = FALSE) When TRUE, each folder's size is
   *   updated to the summed size of its children, recursively, without
   *   checking if the folder already has a size. When FALSE, a folder's
   *   size is only updated if it is empty.
   *
   * @return int
   *   The updated size of the folder, in bytes.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   *
   * @section locking Process locks
   * This item is locked while its size is changed.
   *
   * @see ::clearSize()
   * @see ::getKind()
   * @see ::getSize()
   * @see ::setSize()
   * @see ::updateSizes()
   */
  private function updateSizeDownwardInternal(bool $overwrite = FALSE) {
    // Validate.
    // ---------
    // If not a folder, just return its size.
    $size = $this->getSize();
    if ($this->isFolder() === FALSE) {
      return $size;
    }

    // If $overwrite is FALSE and this folder's size is non-empty, then
    // leave the size as-is and return it.
    if ($overwrite === FALSE && $size !== FALSE) {
      // The item already has a size and we aren't directed to overwrite it,
      // so just return it as-is.
      return $size;
    }

    //
    // Update child folders.
    // ---------------------
    // Loop and recurse through all child folders and sum their sizes.
    //
    // We intentionally loop through subfolders first, before files, because
    // if this operation is invoked from a queue worker and the worker aborts,
    // this traversal will at least update some of the subfolders. The next
    // run of the task will have less work to do. But if we started with
    // file checking, the work has no contribution to the overall task unless
    // the file AND subfolder updates both occur. For a queue worker task that
    // is big and keeps aborting, we'd be wasting a lot of time over and over
    // calculating child file sizes and yet have no chance to use them.
    $nLockExceptions = 0;
    $size = 0;
    foreach ($this->findFolderChildrenIds() as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $childSize = $item->updateSizeDownwardInternal($overwrite);
          if ($item->isSystemHidden() === FALSE) {
            $size += $childSize;
          }
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    // If there were any lock exceptions, the size is incomplete and there
    // is no point in continuing. Throw an exception.
    if ($nLockExceptions > 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be updated at this time.')));
    }

    //
    // Count child files.
    // ------------------
    // Loop through all file children and sum their sizes. This query skips
    // hidden files.
    $size += $this->findFileChildrenNumberOfBytes();

    //
    // Update this folder with the total size.
    // ---------------------------------------
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be updated at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    $this->setSize($size);
    $this->save();

    // UNLOCK THIS ITEM.
    $this->releaseLock();

    return $size;
  }

  /**
   * Updates folder storage sizes, in bytes, recursing up through parents.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * This method changes sizes on folders in a folder tree by recursing
   * upward through an item's ancestors. All ancestors of this folder are
   * updated.
   *
   * After the size is updated, each folder is saved.
   *
   * System hidden items are skipped.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   *
   * @section locking Process locks
   * This item is locked while its size is updated. Then its parent is
   * locked and updated, and so on through all ancestors.
   *
   * @see ::clearSize()
   * @see ::getKind()
   * @see ::getSize()
   * @see ::setSize()
   * @see ::updateSizes()
   */
  private function updateSizeUpwardInternal() {
    //
    // Update this item, if needed.
    // ----------------------------
    // If this item is a folder, not hidden, and the size is cleared,
    // loop through its immediate children to sum their sizes, then
    // update the folder's size.
    //
    // The sizes of hidden children do not count.
    $size = 0;
    if ($this->isFolder() === TRUE &&
        $this->isSystemHidden() === FALSE &&
        $this->getSize() === FALSE) {
      foreach ($this->findChildrenIds() as $id) {
        $item = self::load($id);
        if ($item !== NULL &&
            $item->isSystemHidden() === FALSE) {
          $size += $item->getSize();
        }
      }

      // LOCK THIS ITEM.
      if ($this->acquireLock() === FALSE) {
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The item "@name" is in use and cannot be updated at this time.',
            [
              '@name' => $this->getName(),
            ])));
      }

      $this->setSize($size);
      $this->save();

      // UNLOCK THIS ITEM.
      $this->releaseLock();
    }

    //
    // Update ancestors.
    // -----------------
    // Get the parent and do the same update on it.
    $parent = $this->getParentFolder();
    if ($parent !== NULL) {
      $parent->updateSizeUpwardInternal();
      // Let lock exceptions propagate upwards.
    }
  }

  /*---------------------------------------------------------------------
   *
   * Queue task.
   *
   *---------------------------------------------------------------------*/

  /**
   * Process an update sizes task from the work queue.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from the module's work queue handler.
   *
   * A size update task provides a list of IDs for entities to update to
   * a new size. These are typically folders. Each size update traverses
   * downwards through descendants to sum their sizes and update intermediate
   * folders, then traverses upwards through ancestors to update them.
   *
   * There is one condition under which an update may not complete fully:
   * - The folder tree is too large to update before hitting a timeout.
   *
   * If the folder tree is too large to finish before the process is
   * interrupted by a PHP or web server timeout, then the queued task that
   * called this method will be restarted by CRON at a later time. A repeat
   * of the task will again traverse downwards, then upwards. During
   * downwards traversal, folders that already have non-NULL sizes will be
   * skipped. This enables the task to be interrupted again again, and
   * each time the task will update a few more folders. Eventually the task
   * will complete, this method will return normally, and the enqueued
   * task will complete.
   *
   * @param array $parameters
   *   An associative array of the task's parameters:
   *   - 'operation': Must be 'updatesizes'.
   *   - 'ids': An array of integer FolderShare entity IDs to update.
   *   - 'overwrite': When TRUE, overwrite descendant folder sizes, ignoring
   *     their current values. When FALSE, only change descendant folders
   *     if their size is NULL.
   */
  public static function processUpdateSizesTask(array $parameters) {
    //
    // Validate parameters.
    // --------------------
    // The parameters must be an array of entity IDs to delete.
    if (isset($parameters['ids']) === FALSE ||
        is_array($parameters['ids']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'ids' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Update Sizes',
        ]);
      return;
    }

    $overwrite = FALSE;
    if (isset($parameters['overwrite']) === TRUE) {
      $overwrite = (bool) $parameters['overwrite'];
    }

    //
    // Update.
    // -------
    // Update sizes on the indicated items.
    $exceptionIds = [];
    foreach ($parameters['ids'] as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->updateSizeDownwardInternal($overwrite);
          $item->updateSizeUpwardInternal();
        }
        catch (LockException $e) {
          $exceptionIds[] = $id;
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
          'operation' => 'updatesizes',
          'ids'       => $exceptionIds,
          'overwrite' => $overwrite,
        ]);
    }
  }

}
