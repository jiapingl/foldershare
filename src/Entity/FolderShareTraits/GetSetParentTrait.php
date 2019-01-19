<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity parent field.
 *
 * This trait includes get and set methods for FolderShare entity parent field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetParentTrait {

  /*---------------------------------------------------------------------
   *
   * Parent field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears the parent folder ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() in order for the change to take effect.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::setParentFolderId()
   */
  private function clearParentFolderId() {
    $this->parentid->setValue(['target_id' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentFolder() {
    $id = $this->getParentFolderId();
    if ($id < 0) {
      return NULL;
    }

    return self::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentFolderId() {
    $parentField = $this->parentid->getValue();

    if ((empty($parentField) === TRUE) ||
        (isset($parentField[0]) === FALSE) ||
        (empty($parentField[0]) === TRUE) ||
        (isset($parentField[0]['target_id']) === FALSE) ||
        (empty($parentField[0]['target_id']) === TRUE)) {
      // Empty. No parent. This is a root item.
      //
      // Freshly loaded entities tend to have empty parent fields for root
      // items. But entities during construction or after having a parent
      // ID cleared have non-empty parent fields with empty target IDs.
      // We need to check all of the possibilities to be sure that this is a
      // root item that has no parent ID.
      return self::USER_ROOT_LIST;
    }

    return (int) $parentField[0]['target_id'];
  }

  /**
   * Sets the parent folder ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() in order for the change to take effect.
   *
   * @param int $id
   *   The parent ID for the new parent of this item. The value is
   *   not validated and is expected to be a valid entity ID. If the value
   *   is FolderShareInterface::USER_ROOT_LIST or negative, the parent
   *   folder ID is cleared to NULL, which marks this item as a root item.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearParentFolderId()
   */
  private function setParentFolderId(int $id) {
    if ($id < 0) {
      $this->parentid->setValue(['target_id' => NULL]);
    }
    else {
      $this->parentid->setValue(['target_id' => $id]);
    }
  }

}
