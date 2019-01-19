<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity description field.
 *
 * This trait includes get and set methods for FolderShare entity
 * description field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetDescriptionTrait {

  /*---------------------------------------------------------------------
   *
   * Description field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $text = $this->description->getValue();
    if ($text === NULL) {
      return '';
    }

    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $text) {
    if (empty($text) === TRUE) {
      $this->description->setValue(NULL);
    }
    else {
      $this->description->setValue($text);
    }
  }

}
