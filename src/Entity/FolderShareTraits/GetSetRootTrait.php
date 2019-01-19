<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity root fields.
 *
 * This trait includes get and set methods for FolderShare entity
 * root fields.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetRootTrait {

  /*---------------------------------------------------------------------
   *
   * Root field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Clears the root ID.
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
   * @see ::setRootItemId()
   */
  private function clearRootItemId() {
    $this->rootid->setValue(['target_id' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function getRootItem() {
    $id = $this->getRootItemId();
    if ($id === (int) $this->id()) {
      return $this;
    }

    return self::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getRootItemId() {
    $rootField = $this->rootid->getValue();

    if ((empty($rootField) === TRUE) ||
        (isset($rootField[0]) === FALSE) ||
        (empty($rootField[0]) === TRUE) ||
        (isset($rootField[0]['target_id']) === FALSE) ||
        (empty($rootField[0]['target_id']) === TRUE)) {
      // Empty. No root.
      //
      // Freshly loaded entities tend to have empty root fields for root
      // items. But entities during construction or after having a root
      // ID cleared have non-empty root fields with empty target IDs.
      // We need to check all of the possibilities to be sure this is a
      // root item.
      return (int) $this->id();
    }

    return (int) $rootField[0]['target_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function isRootItem() {
    // Root items have empty parent and root IDs.
    $rootField = $this->rootid->getValue();
    return (empty($rootField) === TRUE) ||
      (isset($rootField[0]) === FALSE) ||
      (empty($rootField[0]) === TRUE) ||
      (isset($rootField[0]['target_id']) === FALSE) ||
      (empty($rootField[0]['target_id']) === TRUE);
  }

  /**
   * Sets the root ID.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $rootId
   *   The root ID for the new root ancestor of this item. The value is
   *   not validated and is expected to be a valid entity ID. If the value
   *   is FolderShareInterface::USER_ROOT_LISTi, negative, or the ID of this
   *   item, the root ID is cleared to NULL, indicating the item is in the
   *   root list.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::clearRootItemId()
   * @see ::setRootItemIdRecursively()
   */
  private function setRootItemId(int $rootId) {
    if ($rootId < 0 || (int) $this->id() === $rootId) {
      $this->rootid->setValue(['target_id' => NULL]);
    }
    else {
      $this->rootid->setValue(['target_id' => $rootId]);
    }
  }

}
