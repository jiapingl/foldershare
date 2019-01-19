<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;

/**
 * Share Foldershare entities.
 *
 * This trait includes methods to share FolderShare entities.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationShareTrait {

  /*---------------------------------------------------------------------
   *
   * Share FolderShare entity.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function share(array $grants) {
    //
    // Lock, share, & unlock.
    // ----------------------
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be updated at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    $oldGrants = $this->getAccessGrants();
    $this->setAccessGrants($grants);
    $this->save();

    // UNLOCK THIS ITEM.
    $this->releaseLock();

    self::postOperationHook(
      'share',
      [
        $this,
        $oldGrants,
        $grants,
      ]);
    self::log(
      'notice',
      'Changed sharing for entity @id ("%name"). <br>Old grants: %oldGrants. <br>New grants: %newGrants.',
      [
        '@id'        => $this->id(),
        '%name'      => $this->getName(),
        '%oldGrants' => self::accessGrantsToString($oldGrants),
        '%newGrants' => self::accessGrantsToString($grants),
        'link'       => $this->toLink(t('View'))->toString(),
      ]);
  }

  /**
   * Formats an access grants array as a string.
   *
   * This function is strictly used for logging changes to access grants.
   *
   * @param array $grants
   *   The access grants to format.
   *
   * @return string
   *   A string representation of the access grants.
   */
  private static function accessGrantsToString(array $grants) {
    $string = '';
    foreach ($grants as $uid => $g) {
      if (empty($g) === FALSE) {
        $string .= ' ' . $uid . '(' . implode(',', $g) . ')';
      }
    }
    return $string;
  }

  /*---------------------------------------------------------------------
   *
   * Unshare FolderShare entity.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function unshare(int $uid, string $access = '') {
    //
    // Lock, unshare, & unlock.
    // ----------------------
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be updated at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    $oldGrants = $this->getAccessGrants();
    if (empty($access) === TRUE) {
      $this->deleteAccessGrant($uid, 'view');
      $this->deleteAccessGrant($uid, 'author');
      $access = 'view & author';
    }
    else {
      $this->deleteAccessGrant($uid, $access);
    }

    $newGrants = $this->getAccessGrants();
    $this->save();

    // UNLOCK THIS ITEM.
    $this->releaseLock();

    self::postOperationHook(
      'share',
      [
        $this,
        $oldGrants,
        $newGrants,
      ]);
    self::log(
      'notice',
      'Release sharing for entity @id ("%name") for user @uid to @access. <br>Old grants: %oldGrants. <br>New grants: %newGrants.',
      [
        '@id'        => $this->id(),
        '%name'      => $this->getName(),
        '@uid'       => $uid,
        '@access'    => $access,
        '%oldGrants' => self::accessGrantsToString($oldGrants),
        '%newGrants' => self::accessGrantsToString($newGrants),
        'link'       => $this->toLink(t('View'))->toString(),
      ]);
  }

  /**
   * Unshares multiple items.
   *
   * Each of the indicated items has its access grants adjusted to remove
   * the indicated user for shared access. The $access argument may be
   * 'view' or 'author', or left empty to unshare for both.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs to unshare. Invalid IDs
   *   are silently skipped.
   * @param int $uid
   *   The user ID of the user to unshare for.
   * @param string $access
   *   The access grant to unshare. One of 'view' or 'author'. An empty
   *   string unshares for 'view' AND 'author'.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use.
   *
   * @section locking Process locks
   * Each item is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_share" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::unshare()
   */
  public static function unshareMultiple(
    array $ids,
    int $uid,
    string $access = '') {

    $nLockExceptions = 0;
    foreach ($ids as $id) {
      $item = self::load($id);
      if ($item !== NULL) {
        try {
          $item->unshare($uid, $access);
        }
        catch (LockException $e) {
          ++$nLockExceptions;
        }
      }
    }

    if ($nLockExceptions !== 0) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot have sharing released at this time.')));
    }
  }

}
