<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity created time field.
 *
 * This trait includes get and set methods for FolderShare entity
 * created time field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetCreatedTimeTrait {

  /*---------------------------------------------------------------------
   *
   * Created field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
  }

}
