<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity system hidden field.
 *
 * This trait includes get and set methods for FolderShare entity
 * system hidden field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetSystemHiddenTrait {

  /*---------------------------------------------------------------------
   *
   * SystemHidden field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function isSystemHidden() {
    $value = $this->get('systemhidden')->value;
    if (empty($value) === TRUE || $value === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sets the system hidden flag.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() for the change to take effect.
   *
   * @param bool $state
   *   The new flag state.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::isSystemHidden()
   */
  private function setSystemHidden(bool $state) {
    $this->systemhidden->setValue($state);
  }

}
