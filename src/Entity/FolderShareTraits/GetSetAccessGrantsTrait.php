<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\user\Entity\User;

/**
 * Get/set FolderShare entity access grants fields.
 *
 * This trait includes get and set methods for FolderShare entity
 * access grants fields.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetAccessGrantsTrait {

  /*---------------------------------------------------------------------
   *
   * Get/set access grants.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getAccessGrantAuthorUserIds() {
    $uids = [];
    if ($this->isRootItem() === TRUE) {
      foreach ($this->grantauthoruids->getValue() as $item) {
        $uids[] = (int) $item['target_id'];
      }
    }

    return $uids;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessGrantViewUserIds() {
    $uids = [];
    if ($this->isRootItem() === TRUE) {
      foreach ($this->grantviewuids->getValue() as $item) {
        $uids[] = (int) $item['target_id'];
      }
    }

    return $uids;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessGrants() {
    if ($this->isRootItem() === FALSE) {
      return [];
    }

    $authors = $this->getAccessGrantAuthorUserIds();
    $viewers = $this->getAccessGrantViewUserIds();

    // Create a grant list with one entry per user ID.
    // The entry is an array with the UID as the key
    // and one of these possible entries:
    // - ['view'] = user only has view access.
    // - ['author'] = user only has author access (which is odd).
    // - ['author', 'view'] = user has view and author access.
    //
    // Start by adding all author grants.
    $grants = [];
    foreach ($authors as $uid) {
      $grants[$uid] = ['author'];
    }

    // Add all view grants.
    foreach ($viewers as $uid) {
      if (isset($grants[$uid]) === TRUE) {
        $grants[$uid][] = 'view';
      }
      else {
        $grants[$uid] = ['view'];
      }
    }

    return $grants;
  }

  /**
   * Sets all user IDs and access grants for this root item.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The given associative array must have user IDs as keys. For each user,
   * the array value must be an array of strings with one or two values:
   * - ['view'] = user only has view access.
   * - ['author'] = user only has author access (which is odd).
   * - ['author', 'view'] = user has view and author access.
   *
   * The given array is used to completely reset all access grants for
   * this root item. Any prior grants are removed.
   *
   * The owner of the root item is automaticaly included with both view and
   * author access, whether or not they are included in the given array.
   *
   * The given array is in the same format as that returned by
   * getAccessGrants().
   *
   * If this is not a root item, this call has no effect.
   *
   * System hidden and disabled entities may be changed, however the module's
   * access control will deny access for any user that is not an administrator.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param array $grants
   *   An unordered associative array with user IDs as keys and
   *   arrays as values. Array values contain strings indicating 'view' or
   *   'author' access.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   * @see ::isAccessGranted()
   */
  private function setAccessGrants(array $grants) {
    if ($this->isRootItem() === FALSE) {
      return;
    }

    // Use the given grant list with one entry per user ID.
    // The entry is an array with the UID as the key
    // and one of these possible entries:
    // - ['view'] = user only has view access.
    // - ['author'] = user only has author access (which is odd).
    // - ['author', 'view'] = user has view and author access.
    //
    // Initialize arrays. Always include the folder owner for
    // view and author access.
    $ownerId = $this->getOwnerId();

    $authors = [$ownerId];
    $viewers = [$ownerId];

    // Split the array into separate lists for view and author.
    // Along the way, remove redundant entries.
    foreach ($grants as $uid => $list) {
      $isAuthor = in_array('author', $list);
      $isViewer = in_array('view', $list);

      // If the user isn't already in the author or view, add them.
      if ($isAuthor === TRUE && in_array($uid, $authors, TRUE) === FALSE) {
        $authors[] = $uid;
      }

      if ($isViewer === TRUE && in_array($uid, $viewers, TRUE) === FALSE) {
        $viewers[] = $uid;
      }
    }

    // Sweep through the arrays and switch them to include 'target_id'.
    foreach ($authors as $index => $uid) {
      $authors[$index] = ['target_id' => $uid];
    }

    foreach ($viewers as $index => $uid) {
      $viewers[$index] = ['target_id' => $uid];
    }

    // Set the fields.
    $this->grantauthoruids->setValue($authors, FALSE);
    $this->grantviewuids->setValue($viewers, FALSE);
  }

  /*---------------------------------------------------------------------
   *
   * Test access grants.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function isAccessGranted(int $uid, string $access) {
    if ($uid < 0 || $this->isRootItem() === FALSE) {
      return FALSE;
    }

    // Loop through the appropriate view or author fields and
    // check if the given user has been explicitly granted access.
    //
    // For view and author access, we recognize the special case where the
    // anonumous user (uid = 0) has been granted access. If anonymous has
    // access, then *everybody* has access and this method returns TRUE.
    $anonymousId = User::getAnonymousUser()->id();
    $access = mb_convert_case($access, MB_CASE_LOWER);

    switch ($access) {
      default:
        // Unknown request.
        return FALSE;

      case 'author':
        // Check the list of author UIDs.
        foreach ($this->grantauthoruids->getValue() as $entry) {
          $entryUid = (int) $entry['target_id'];
          if ($entryUid === $anonymousId || $entryUid === $uid) {
            return TRUE;
          }
        }
        return FALSE;

      case 'view':
        // Check the list of view UIDs.
        foreach ($this->grantviewuids->getValue() as $entry) {
          $entryUid = (int) $entry['target_id'];
          if ($entryUid === $anonymousId || $entryUid === $uid) {
            return TRUE;
          }
        }
        return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessPrivate() {
    if ($this->isRootItem() === FALSE) {
      return FALSE;
    }

    // Access is private if there are no users other than the owner
    // or admin in the list of author and view grant UIDs.
    $uid = $this->getOwnerId();

    foreach ($this->grantviewuids->getValue() as $item) {
      $itemUid = (int) $item['target_id'];
      if ($itemUid !== 1 && $itemUid !== $uid) {
        return FALSE;
      }
    }

    foreach ($this->grantauthoruids->getValue() as $item) {
      $itemUid = (int) $item['target_id'];
      if ($itemUid !== 1 && $itemUid !== $uid) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessPublic() {
    if ($this->isRootItem() === FALSE) {
      return FALSE;
    }

    // Access is public if the anonymous user (UID = 0) is listed in
    // author and view grant UIDs.
    $anonymousId = User::getAnonymousUser()->id();
    foreach ($this->grantviewuids->getValue() as $item) {
      if ((int) $item['target_id'] === $anonymousId) {
        return TRUE;
      }
    }

    foreach ($this->grantauthoruids->getValue() as $item) {
      if ((int) $item['target_id'] === $anonymousId) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessShared() {
    if ($this->isRootItem() === FALSE) {
      return FALSE;
    }

    // Access is shared if anyone besides the owner is listed in the
    // author and view grant UIDs.
    return $this->isAccessPrivate() === FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSharedBy(int $uid) {
    if ($uid < 0 || $this->isRootItem() === FALSE ||
        $this->isOwnedBy($uid) === FALSE) {
      return FALSE;
    }

    // If the view or author grants include any user ID other than
    // the owner, then the item is shared.
    foreach ($this->grantviewuids->getValue() as $entry) {
      $entryUid = (int) $entry['target_id'];
      if ($entryUid !== $uid) {
        return TRUE;
      }
    }

    foreach ($this->grantauthoruids->getValue() as $entry) {
      $entryUid = (int) $entry['target_id'];
      if ($entryUid !== $uid) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSharedWith(int $uid, string $access = 'view') {
    if ($uid < 0 ||
        $this->isRootItem() === FALSE ||
        $this->isOwnedBy($uid) === TRUE) {
      return FALSE;
    }

    return $this->isAccessGranted($uid, $access);
  }

  /*---------------------------------------------------------------------
   *
   * Add access grants.
   *
   *---------------------------------------------------------------------*/

  /**
   * Grants a user access to this root folder.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * If this item is not a root folder, no action is taken.
   *
   * Granting a user access adds the user's ID to the root folder's list
   * of users that have the specified access. Recognized access grants are:
   *
   * - 'view': can view folder fields and content.
   * - 'author': can create, update, and delete fields and content.
   *
   * The owner of the root folder always has view and author access granted.
   * Adding the owner, or any user already granted access, has no affect.
   *
   * Access grants may only be added on root folders. Access grants on
   * non-root folders are silently ignored.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $uid
   *   The user ID of a user granted access.
   * @param string $access
   *   The access granted. One of 'author' or 'view'.
   *
   * @see ::clearAccessGrants()
   * @see ::deleteAccessGrant()
   * @see ::setAccessGrants()
   */
  private function addAccessGrant(int $uid, string $access) {
    if ($uid < 0) {
      return;
    }

    if ($this->isRootItem() === FALSE) {
      return;
    }

    //
    // When adding access, if the user ID is already in the access list,
    // they are not added again.
    //
    switch ($access) {
      case 'author':
        // Append to the list of author UIDs.
        if ($this->isAccessGranted($uid, 'author') === FALSE) {
          $this->grantauthoruids->appendItem(['target_id' => $uid]);
        }
        return;

      case 'view':
        // Append to the list of view UIDs.
        if ($this->isAccessGranted($uid, 'view') === FALSE) {
          $this->grantviewuids->appendItem(['target_id' => $uid]);
        }
        return;
    }
  }

  /**
   * Adds default access grants for the item owner.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * Default access grants are used to initialize the grant values
   * when an item is first created. The default grant gives the
   * item's owner view and author access.
   *
   * The caller must call save() for the change to take effect.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getAccessGrantUserIds()
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   */
  private function addDefaultAccessGrants() {
    if ($this->isRootItem() === FALSE) {
      return;
    }

    $ownerUid = $this->getOwnerId();
    $this->addAccessGrant($ownerUid, 'author');
    $this->addAccessGrant($ownerUid, 'view');
  }

  /*---------------------------------------------------------------------
   *
   * Clear and delete access grants.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears all access grants for this root item, or those for a specific user.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * If this item is not a root folder, this call has no effect.
   *
   * If an optional user ID is given, the user is removed from all access
   * grants on the item. If no user ID is given, or if it is
   * FolderShareInterface::ANY_USER_ID, all access grants are removed for
   * the item, leaving only the default access for the item's owner.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $uid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   of a user for whome to clear access. Explicit requests to delete the
   *   owner's access are ignored. Deleting the owner's access as well
   *   requires calling this function with a FolderShareInterface::ANY_USER_ID
   *   or negative user ID and $retainOwnerGrants as FALSE.
   * @param bool $retainOwnerGrants
   *   (optional, default = TRUE) When TRUE, retain the owner's access grants
   *   so that they can still see and operate upon the item. When FALSE, the
   *   owner's own access grants are also cleared.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::setAccessGrants()
   */
  private function clearAccessGrants(
    int $uid = self::ANY_USER_ID,
    bool $retainOwnerGrants = TRUE) {

    // Access grants are only attached to root items.  If this item
    // is not a root item, go ahead and clear access grants anyway to
    // clean up the item in case something leaked through.
    if ($uid < 0) {
      // Clear all of the grant UIDs.
      $this->grantauthoruids->setValue([], FALSE);
      $this->grantviewuids->setValue([], FALSE);

      if ($retainOwnerGrants === TRUE) {
        // Add back defaults. This is silently ignored if the
        // item is not a root item.
        $this->addDefaultAccessGrants();
      }
    }
    elseif ($this->getOwnerId() !== $uid) {
      // Delete the user's access.
      $this->deleteAccessGrant($uid, 'view');
      $this->deleteAccessGrant($uid, 'author');
    }
  }

  /**
   * Deletes a user's access to this root item.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * If this item is not a root folder, this call has no effect.
   *
   * The owner of the root folder always has view and author access granted.
   * Attempting to delete the owner's access has no effect.
   *
   * Deleting a user that currently does not have access has no effect.
   *
   * If the access name is neither 'view' or 'author', the call has no effect.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $uid
   *   The user ID of a user currently granted access.
   * @param string $access
   *   The access grant to be removed. One of 'author' or 'view'.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearAccessGrants()
   * @see ::addAccessGrant()
   * @see ::setAccessGrants()
   */
  private function deleteAccessGrant(int $uid, string $access) {
    if ($uid < 0) {
      return;
    }

    if ($this->isRootItem() === FALSE) {
      return;
    }

    // If the UID to delete is the root item's owner, don't delete
    // them. The owner ALWAYS has access.
    $ownerId = $this->getOwnerId();
    if ($ownerId === $uid) {
      return;
    }

    // If this folder is not a root item, go ahead and try to remove
    // the UID. There should be no access grants to remove the ID from,
    // but it doesn't hurt and could clean out anything that's leaked through.
    switch ($access) {
      case 'author':
        // Remove from the list of author UIDs.
        foreach ($this->grantauthoruids->getValue() as $index => $item) {
          if ((int) $item['target_id'] === $uid) {
            $this->grantauthoruids->removeItem($index);
            return;
          }
        }
        return;

      case 'view':
        // Remove from the list of view UIDs.
        foreach ($this->grantviewuids->getValue() as $index => $item) {
          if ((int) $item['target_id'] === $uid) {
            $this->grantviewuids->removeItem($index);
          }
        }
        return;
    }
  }

  /*---------------------------------------------------------------------
   *
   * Sharing status.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getSharingStatus() {
    //
    // Set up
    // ------
    // Get the root item, its owner, and information about the current
    // and anonymous users.
    if ($this->isSystemHidden() === TRUE ||
        $this->isSystemDisabled() === TRUE) {
      return 'private';
    }

    $root = $this->getRootItem();
    if ($root === NULL) {
      // Malformed entity!
      return 'private';
    }

    $rootOwner = $root->getOwner();
    if ($rootOwner === NULL) {
      // Malformed entity!
      return 'private';
    }

    $rootOwnerId = $rootOwner->id();
    $currentUserId = (int) \Drupal::currentUser()->id();
    $anonymousId = User::getAnonymousUser()->id();

    //
    // Check anonymous ownership
    // -------------------------
    // If the entity is owned by anonymous, it is always public.
    if ($rootOwner->isAnonymous() === TRUE) {
      return 'public';
    }

    //
    // Check private ownership
    // -----------------------
    // If the item is not shared with anyone except the owner, it is either
    // personal (owned by the current user) or private (owned by someone else).
    if ($root->isAccessPrivate() === TRUE) {
      return ($rootOwnerId === $currentUserId) ? 'personal' : 'private';
    }

    //
    // Check anonymous sharing
    // -----------------------
    // If the content is shared with anonymous, then it is public.
    if ($root->isAccessPublic() === TRUE) {
      return 'public';
    }

    //
    // Find non-anonymous sharing
    // --------------------------
    // Because of the previous isAccessPrivate(), at this point the item
    // is shared with someone, and that someone isn't anonymous.
    //
    // Look through the item's grants and see if any of them are for
    // someone other than anonymous and the site admin.
    $uids = array_merge(
      $root->getAccessGrantViewUserIds(),
      $root->getAccessGrantAuthorUserIds());
    $isSharedWithCurrent = FALSE;
    foreach ($uids as $uid) {
      // Ignore grant entries for:
      // - The owner.
      // - The site administrator.
      // - Anonymous.
      if ($uid === 1 || $uid === $rootOwnerId || $uid === $anonymousId) {
        continue;
      }

      // Otherwise the grant gives access to someone. See if it grants
      // access to the current user.
      if ($uid === $currentUserId) {
        $isSharedWithCurrent = TRUE;
        break;
      }
    }

    //
    // Check sharing
    // -------------
    // There are several cases here:
    //
    // - If the item is shared with the current user, then it is shared.
    //
    // - Otherwise the item is not shared with the current user, but it is
    //   shared with someone. If the item is owned by the current user,
    //   then it is shared.
    //
    // - Otherwise the item is not owned by the current user or shared by
    //   them with the current user. The item is private.
    //
    if ($isSharedWithCurrent === TRUE) {
      return 'shared with you';
    }

    if ($currentUserId === $rootOwnerId) {
      return 'shared by you';
    }

    return 'private';
  }

  /*---------------------------------------------------------------------
   *
   * Change sharing.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears shared folder access grants for all of a user's content.
   *
   * When an optional user ID is given, access grants are cleared to disable
   * sharing on all root items owned by the user. When a user ID is not given,
   * it is FolderShareInterface::ANY_USER_ID, or it is negative, access grants
   * are cleared to disable sharing on all root items for all users.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int $uid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   of the owner of root items for which to clear access grants.
   *   If the value is FolderShareInterface::ANY_USER_ID, access is
   *   cleared for all root items owned by any user.
   *
   * @section locking Process locks
   * This method does not lock access. The site should be in maintenance
   * mode, or no users should be accessing the items being changed.
   *
   * @see ::findAllRootItemIds()
   * @see ::clearAccessGrants()
   */
  public static function unshareAll(int $uid = self::ANY_USER_ID) {

    // Shared access grants are only on root items.  Get a list of
    // all root items for the indicated user, or for all users.
    $rootIds = self::findAllRootItemIds($uid);

    // Loop through the folder IDs, load each one, and clear its
    // access controls.
    foreach ($rootIds as $id) {
      $item = self::load($id);

      if ($item !== NULL) {
        $item->clearAccessGrants();
        $item->save();
      }
    }
  }

  /**
   * Removes the user from shared access on all root items.
   *
   * The user is removed from all access grants on all root items the
   * do not own.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int $uid
   *   The user ID of the user to remove from shared access to all root items.
   *
   * @section locking Process locks
   * This method does not lock access. The site should be in maintenance
   * mode, or no users should be accessing the items being changed.
   *
   * @see ::findAllRootItemIds()
   * @see ::getAccessGrants()
   * @see ::share()
   */
  public static function unshareFromAll(int $uid) {
    if ($uid < 0) {
      return;
    }

    // Shared access grants are only on root items.  Get a list of
    // all root items.
    $rootIds = self::findAllRootItemIds();

    // Loop through them and clear the user from the folder's access
    // grants.
    foreach ($rootIds as $id) {
      $item = self::load($id);

      if ($item !== NULL) {
        $grants = $item->getAccessGrants();
        if (isset($grants[$uid]) === TRUE) {
          unset($grants[$uid]);
          $item->setAccessGrants($grants);
          $item->save();
        }
      }
    }
  }

}
