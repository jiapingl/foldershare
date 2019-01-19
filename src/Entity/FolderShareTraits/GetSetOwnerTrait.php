<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\user\UserInterface;

/**
 * Get/set FolderShare entity owner field.
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
trait GetSetOwnerTrait {

  /*---------------------------------------------------------------------
   *
   * Owner field.
   *
   * Implements EntityOwnerInterface.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function isOwnedBy(int $uid) {
    return ($this->getOwnerId() === $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return (int) $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }

    return $this->setOwnerId($account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($ownerUid) {
    // The generic EntityOwnerInterface implemented by this class forces
    // this method to be public. But setting the owner field by code
    // outside of this class could corrupt the file system by failing to
    // keep other values uptodate.
    //
    // Set the item's owner.
    $this->setOwnerIdInternal($ownerUid);

    // If this item is a root item, clear its access grants back to their
    // defaults (which only grant the owner access).
    if ($this->isRootItem() === TRUE) {
      $this->clearAccessGrants();
    }

    $this->save();

    // For files, images, and media, update the underlying entity as well.
    switch ($this->getKind()) {
      default:
      case self::FOLDER_KIND:
        break;

      case self::FILE_KIND:
      case self::IMAGE_KIND:
        if ($this->isFile() === TRUE) {
          $file = $this->getFile();
        }
        else {
          $file = $this->getImage();
        }

        if ($file !== NULL) {
          $file->setOwnerId($ownerUid);
          $file->save();
        }
        break;

      case self::MEDIA_KIND:
        $media = $this->getMedia();
        if ($media !== NULL) {
          $media->setOwnerId($ownerUid);
          $media->save();
        }
        break;
    }

    return $this;
  }

  /**
   * Sets the owner ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * This function changes the owner ID and does not update access grants,
   * wrapped items, or usage tracking. This is the responsability of the
   * caller.
   *
   * The user ID is not validated. It is presumed to be a valid entity
   * ID for a User entity.
   *
   * The caller must call save() for the change to take effect.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int $ownerUid
   *   The user ID of the new owner. The value is not validated and is
   *   assumed to be a valid user ID.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   */
  private function setOwnerIdInternal(int $ownerUid) {
    $this->set('uid', $ownerUid);
    return $this;
  }

}
