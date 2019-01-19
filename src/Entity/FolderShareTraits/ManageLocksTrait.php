<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Settings;

/**
 * Manages content locks for exclusive access to FolderShare entities.
 *
 * This trait includes internal aquire and release methods for locks on
 * FolderShare entities or the user's root list, if the site's settings
 * enable them.
 *
 * To prevent (or reduce) collisions made by parallel actions, code needs
 * to lock/unlock around sensitive operations, such as deleting, copying,
 * or moving a folder tree.
 *
 * There are two acquire and release pairs:
 * - Item lock
 *   - Manage the lock on a single item to limit changes to the item's name,
 *     description, etc.
 *
 * - Root lock
 *   - Manage the lock on the root item list to limit changes to the list,
 *     such as to add or remove from the list.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManageLocksTrait {

  /*---------------------------------------------------------------------
   *
   * Lock names.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the name of the item edit lock.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @param int $id
   *   The ID of the FolderShare entity to lock.
   *
   * @return string
   *   Returns the edit lock name.
   *
   * @see ::acquireLock()
   * @see ::getRootEditLockName()
   */
  private static function getItemEditLockName(int $id) {
    return self::EDIT_CONTENT_LOCK_NAME . $id;
  }

  /**
   * Returns the name of the root list edit lock.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @return string
   *   Returns the edit lock name
   *
   * @see ::acquireRootLock()
   * @see ::getItemEditLockName()
   */
  private static function getRootEditLockName() {
    return self::EDIT_CONTENT_LOCK_NAME . 'ROOT';
  }

  /*---------------------------------------------------------------------
   *
   * Entity locks.
   *
   *---------------------------------------------------------------------*/

  /**
   * Acquires a lock on this item.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @return bool
   *   Returns TRUE if a lock on this item was acquired, and FALSE otherwise.
   *
   * @see ::acquireRootLock()
   * @see ::releaseLock()
   */
  private function acquireLock() {
    if (Settings::getProcessLocksEnable() === FALSE) {
      return TRUE;
    }

    return \Drupal::lock()->acquire(
      self::getItemEditLockName($this->id()),
      self::EDIT_CONTENT_LOCK_DURATION);
  }

  /**
   * Releases a lock on this item only.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @see ::acquireLock()
   * @see ::releaseRootLock()
   */
  private function releaseLock() {
    if (Settings::getProcessLocksEnable() === FALSE) {
      return;
    }

    \Drupal::lock()->release(
      self::getItemEditLockName($this->id()));
  }

  /**
   * Acquires a lock on every item in a list.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * This method will deadlock if the same entity ID occurs more than once
   * in the array.
   *
   * If every entity in the list can be locked, this function returns TRUE.
   * Otherwise, when an item cannot be locked, all previously locked items in
   * the list are unlocked and this function returns FALSE.
   *
   * @param int[] $ids
   *   An array of entity IDs to lock.
   *
   * @return bool
   *   Returns TRUE if a lock on this item was acquired, and FALSE otherwise.
   *
   * @see ::acquireLock()
   */
  private static function acquireLockMultiple(array $ids) {
    if (Settings::getProcessLocksEnable() === FALSE) {
      return TRUE;
    }

    $lockedSoFar = [];
    foreach ($ids as $id) {
      $status = \Drupal::lock()->acquire(
        self::getItemEditLockName($id),
        self::EDIT_CONTENT_LOCK_DURATION);

      if ($status === FALSE) {
        // Lock failed. Back out everything locked so far.
        foreach ($lockedSoFar as $locked) {
          \Drupal::lock()->release(
            self::getItemEditLockName($locked));
        }

        return FALSE;
      }

      $lockedSoFar[] = $id;
    }

    return TRUE;
  }

  /**
   * Releases a lock on every item in a list.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @param int[] $ids
   *   An array of entity IDs to unlock.
   *
   * @see ::releaseLock()
   * @see ::acquireLockMultiple()
   */
  private static function releaseLockMultiple(array $ids) {
    if (Settings::getProcessLocksEnable() === FALSE) {
      return;
    }

    foreach ($ids as $id) {
      \Drupal::lock()->release(
        self::getItemEditLockName($id));
    }
  }

  /*---------------------------------------------------------------------
   *
   * Root locks.
   *
   *---------------------------------------------------------------------*/

  /**
   * Acquires a lock on the root list.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @return bool
   *   Returns TRUE if a lock on the root list was acquired,
   *   and FALSE otherwise.
   *
   * @see ::acquireLock()
   * @see ::releaseRootLock()
   */
  private static function acquireRootLock() {
    if (Settings::getProcessLocksEnable() === FALSE) {
      return TRUE;
    }

    return \Drupal::lock()->acquire(
      self::getRootEditLockName(),
      self::EDIT_CONTENT_LOCK_DURATION);
  }

  /**
   * Releases a lock on the root list.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * @see ::acquireLock()
   * @see ::releaseLock()
   */
  private static function releaseRootLock() {
    if (Settings::getProcessLocksEnable() === FALSE) {
      return;
    }

    return \Drupal::lock()->release(
      self::getRootEditLockName());
  }

}
