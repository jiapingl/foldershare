<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\user\Entity\User;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Change FolderShare entity ownership.
 *
 * This trait includes get and set methods for FolderShare entity
 * owner field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationChangeOwnerTrait {

  /*---------------------------------------------------------------------
   *
   * Change ownership.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function changeOwnerId(int $uid, bool $changeDescendants = FALSE) {
    //
    // Lock, change, unlock.
    // ---------------------
    // Change the owner ID of this item immediately, if needed.
    $fromUid = $this->getOwnerId();
    if ($fromUid !== $uid) {
      // LOCK THIS ITEM.
      if ($this->acquireLock() === FALSE) {
        throw new LockException(Utilities::createFormattedMessage(
          t(
            'The item "@name" is in use and cannot be changed at this time.',
            [
              '@name' => $this->getName(),
            ])));
      }

      // For a root item, check that there is no root item with the same
      // name for the new user, and reject the change if there is.
      if ($this->isRootItem() === TRUE) {
        // LOCK ROOT LIST.
        if (self::acquireRootLock() === FALSE) {
          // UNLOCK THIS ITEM.
          $this->releaseLock();
          throw new LockException(Utilities::createFormattedMessage(
            t('The system is busy updating and cannot be changed at this time.')));
        }

        // With the root list locked, changes to the root list should not be
        // possible. We can now safely check if there is already a root item
        // with the same name and abort if so.
        if (empty(self::findAllRootItemIds($uid, $this->getName())) === FALSE) {
          // NOTE:
          // The item's current name is already in use in the new user's
          // root list. We cannot continue because two items cannot
          // have the same name in the same root list. Since nothing has
          // been changed so far, abort.
          //
          // UNLOCK ROOT LIST.
          // UNLOCK THIS ITEM.
          self::releaseRootLock();
          $this->releaseLock();
          $user = User::load($uid);

          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'The name "@name" is already in use among top-level items for @user.',
              [
                '@name' => $this->getName(),
                '@user' => $user->getDisplayName(),
              ]),
            t("Please rename the item before changing it's owner.")));
        }

        // Change owner ID and automatically save.
        $this->setOwnerId($uid);

        // UNLOCK ROOT LIST.
        // UNLOCK THIS ITEM.
        self::releaseRootLock();
        $this->releaseLock();
      }
      else {
        // Change owner ID and automatically save.
        $this->setOwnerId($uid);

        // UNLOCK THIS ITEM.
        $this->releaseLock();
      }

      self::postOperationHook(
        'change_owner',
        [
          $this,
          $fromUid,
          $uid,
        ]);
      self::log(
        'notice',
        'Changed owner of entity @id ("%name") from @fromUid to @toUid.',
        [
          '@id'      => $this->id(),
          '%name'    => $this->getName(),
          '@fromUid' => $fromUid,
          '@toUid'   => $uid,
          'link'     => $this->toLink(t('View'))->toString(),
        ]);
    }

    // This item now has the desired user ID. If descendants should not be
    // changed, then we're done.
    if ($changeDescendants === FALSE) {
      return;
    }

    // Collect descendant IDs, if any.
    $descendantIds = $this->findDescendantIds($uid, FALSE);
    if (empty($descendantIds) === TRUE) {
      return;
    }

    //
    // Queue and execute.
    // ------------------
    // Queue a task to change ownership and then do same task immediately.
    //
    // If the immediate execution is interrupted, the queued task will finish
    // the work.
    $parameters = [
      'operation' => 'changeowner',
      'ids'       => $descendantIds,
      'ownerid'   => $uid,
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

    self::processChangeOwnerTask($parameters);
  }

  /**
   * Changes the owner of multiple items, and optionally their descendants.
   *
   * For each item in the given list of IDs, the owner user ID is changed to
   * the indicated user. If $changeDescendants is TRUE, the owner user ID of
   * all descendants is changed as well. All items are saved.
   *
   * The user ID is not validated. It is presumed to be a valid entity ID
   * for a User entity. It should not be negative.
   *
   * If an item is a root item, the item's name is checked for a collision
   * with another root item with the same name in the new owner's root list.
   * If there is a collision, an exception is thrown and the remainder of the
   * ID list is not processed.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int[] $ids
   *   A list of integer FolderShare entity IDs for items to change.
   * @param int $uid
   *   The owner user ID for the new owner of the folder tree. The ID
   *   is not validated and is presumed to be that of a User entity.
   * @param bool $changeDescendants
   *   (optional, default = FALSE) When FALSE, only this item's ownership
   *   is changed. When TRUE, the ownership of all of this item's descendants
   *   is updated as well.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock could not be acquired on this item,
   *   or any descendants.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if an item is a root item and it's name is
   *   already in use in the root list of the new user.
   *
   * @section queue Queued change ownership
   * When $changeDescendants is TRUE, this method tries to change the
   * ownership of all descendants immediately. If there are a lot of
   * descendants to change, this may take too long for the site's PHP or
   * web server timeouts and the change will be interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are changed.
   *
   * @section locking Process locks
   * This method locks items as they are updated. If this item is a root item,
   * the new user's root list is locked while it is checked for a name
   * collision with this item. If a lock on this item or the root list cannot
   * be acquired, an exception is thrown and no further work is done.
   *
   * If $changeDescendants is TRUE, this method also processes each of this
   * item's descendants. The order of processing is not defined. If a
   * descendant is locked, changing its ownership will be delayed until the
   * lock is available.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_change_owner" hook
   * for each changed item.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each changed item.
   *
   * @see ::setOwnerId()
   * @see ::changeOwnerId()
   * @see \Drupal\user\EntityOwnerInterface::getOwner()
   * @see \Drupal\user\EntityOwnerInterface::getOwnerId()
   */
  public static function changeOwnerIdMultiple(
    array $ids,
    int $uid,
    bool $changeDescendants = FALSE) {

    if (empty($ids) === TRUE) {
      return;
    }

    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->changeOwnerId($uid, $changeDescendants);
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be changed at this time.')));
    }
  }

  /**
   * Changes the ownership for all items owned by a user.
   *
   * All items owned by the user are found and their ownership changed to
   * the indicated new user, then saved.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int $currentUid
   *   The user ID of the owner of current files and folders that are
   *   to be changed.
   * @param int $newUid
   *   The user ID of the new owner of the files and folders.
   *
   * @section locking Process locks
   * This method does not lock access. The site shouold be in maintenance mode,
   * and/or no users should be accessing the items being changed.
   *
   * @see ::setOwnerId()
   * @see ::changeOwnerId()
   */
  public static function changeAllOwnerIdByUser(int $currentUid, int $newUid) {
    foreach (self::findAllIds($currentUid) as $id) {
      $item = self::load($id);
      if ($item !== NULL && $item->getOwnerId() !== $newUid) {
        $item->setOwnerId($newUid);
      }
    }
  }

  /*---------------------------------------------------------------------
   *
   * Queue task.
   *
   *---------------------------------------------------------------------*/

  /**
   * Processes a change ownership task from the work queue.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from the module's work queue handler.
   *
   * A change ownership task provides a list of IDs for entities to change,
   * and their new owner ID. The change is NOT recursive - only the specific
   * IDs provided are changed. Item IDs MUST NOT be for root items, which
   * may have name collisions that need to be checked before queueing this
   * task (see changeOwnerId()).
   *
   * There are two conditions under which copying may not complete fully:
   * - One or more entities are locked by another process.
   * - The ID list is too large to process before hitting a timeout.
   *
   * If an entity is locked, this method will re-queue the locked item to
   * be changed later.
   *
   * If the ID list is too large to finish before the process is interrupted
   * by a PHP or web server timeout, then the queued task that called this
   * method will be restarted by CRON at a later time. A repeat of the task
   * will skip entities that have already had their ownership changed.
   *
   * @param array $parameters
   *   An associative array of the task's parameters with keys:
   *   - 'operation': Must be 'changeowner'.
   *   - 'ids': An array of integer non-root item FolderShare entity IDs to
   *      change.
   *   - 'ownerid': The new owner user ID.
   *
   * @section locking Process locks
   * This method locks items as they are updated. If a lock cannot be acquired,
   * a new task is enqueued to process those locked items.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_change_owner" hook
   * for each changed item.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each changed item.
   *
   * @see ::setOwnerId()
   * @see ::changeOwnerId()
   * @see ::changeOwnerIdMultiple()
   */
  public static function processChangeOwnerTask(array $parameters) {
    //
    // Validate parameters.
    // --------------------
    // The parameters must be an array of entity IDs to change.
    if (isset($parameters['ids']) === FALSE ||
        is_array($parameters['ids']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'ids' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Change Owner',
        ]);
      return;
    }

    if (isset($parameters['ownerid']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'ownerid' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Change Owner',
        ]);
      return;
    }

    $uid = (int) $parameters['ownerid'];

    //
    // Change ownership.
    // -----------------
    // Change all owner IDs. Requeue on lock exceptions.
    $exceptionIds = [];
    foreach ($parameters['ids'] as $id) {
      $item = self::load($id);
      if ($item !== NULL && $item->getOwnerId() !== $uid) {
        if ($item->acquireLock() === FALSE) {
          $exceptionIds[] = $id;
        }
        else {
          $fromUid = $item->getOwnerId();
          $item->setOwnerId($uid);
          $item->releaseLock();

          self::postOperationHook(
            'change_owner',
            [
              $item,
              $fromUid,
              $uid,
            ]);
          self::log(
            'notice',
            'Changed owner of entity @id ("%name") from @fromUid to @toUid.',
            [
              '@id'      => $item->id(),
              '%name'    => $item->getName(),
              '@fromUid' => $fromUid,
              '@toUid'   => $uid,
              'link'     => $item->toLink(t('View'))->toString(),
            ]);
        }
      }
    }

    if (empty($exceptionIds) === FALSE) {
      $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
      $queue->createQueue();
      $queue->createItem(
        [
          'operation' => 'changeowner',
          'ids'       => $exceptionIds,
          'ownerid'   => $uid,
        ]);
    }
  }

}
