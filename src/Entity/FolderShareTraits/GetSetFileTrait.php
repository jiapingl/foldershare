<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Get/set FolderShare entity file, image, and media fields.
 *
 * This trait includes get methods for FolderShare entity file, image,
 * and media fields. Each field is an entity reference that stores
 * a target entity ID. At most one of these three fields may be set.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetFileTrait {

  /*---------------------------------------------------------------------
   *
   * File field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears the item's file ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() for the change to take effect.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::setFileId()
   */
  private function clearFileId() {
    $this->file->setValue(['target_id' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    $id = $this->getFileId();
    if ($id === FALSE) {
      return NULL;
    }

    return File::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileId() {
    if ($this->isFile() === TRUE) {
      $value = $this->get('file')->target_id;
      if ($value === NULL) {
        return FALSE;
      }

      return (int) $value;
    }

    return FALSE;
  }

  /**
   * Sets the item's file ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The entity ID is not validated and is presumed to be a File entity ID.
   * This item is presumed to be a file kind (i.e. isFile() returns TRUE).
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $id
   *   The new File entity ID. The value is not validated but is expected
   *   to be a valid File entity ID. If the value is negative, the file ID
   *   is cleared.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearFileId()
   */
  private function setFileId(int $id) {
    if ($id < 0) {
      $this->file->setValue(['target_id' => NULL]);
    }
    else {
      $this->file->setValue(['target_id' => $id]);
    }
  }

  /*---------------------------------------------------------------------
   *
   * Image field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears the item's image ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() for the change to take effect.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::setImageId()
   */
  private function clearImageId() {
    $this->image->setValue(['target_id' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    $id = $this->getImageId();
    if ($id === FALSE) {
      return NULL;
    }

    // The image module does not define an Image entity type.
    // Instead it uses the File entity type, but references it
    // via an image field type. So, to load an image, we need
    // to use the File module.
    return File::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getImageId() {
    if ($this->isImage() === TRUE) {
      $value = $this->get('image')->target_id;
      if ($value === NULL) {
        return FALSE;
      }

      return (int) $value;
    }

    return FALSE;
  }

  /**
   * Sets the item's image ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The entity ID is not validated and is presumed to be a File entity ID.
   * This item is presumed to be an image kind (i.e. isImage() returns TRUE).
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $id
   *   The new File entity ID. The value is not validated but is expected
   *   to be a valid File entity ID. If the value is negative, the image ID
   *   is cleared.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearImageId()
   */
  private function setImageId(int $id) {
    if ($id < 0) {
      $this->image->setValue(['target_id' => NULL]);
    }
    else {
      $this->image->setValue(['target_id' => $id]);
    }
  }

  /*---------------------------------------------------------------------
   *
   * Media field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears the item's media ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() for the change to take effect.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::setMediaId()
   */
  private function clearMediaId() {
    $this->media->setValue(['target_id' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function getMedia() {
    $id = $this->getMediaId();
    if ($id === FALSE) {
      return NULL;
    }

    return Media::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaId() {
    if ($this->isMedia() === TRUE) {
      $value = $this->get('media')->target_id;
      if ($value === NULL) {
        return FALSE;
      }

      return (int) $value;
    }

    return FALSE;
  }

  /**
   * Sets the item's media ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The entity ID is not validated and is presumed to be a Media entity ID.
   * This item is presumed to be a media kind (i.e. isMedia() returns TRUE).
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $id
   *   The new Media entity ID. The value is not validated but is expected
   *   to be a valid Media entity ID. If the vlaue is negative, the media ID
   *   is cleared.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearMediaId()
   */
  private function setMediaId(int $id) {
    if ($id < 0) {
      $this->media->setValue(['target_id' => NULL]);
    }
    else {
      $this->media->setValue(['target_id' => $id]);
    }
  }

}
