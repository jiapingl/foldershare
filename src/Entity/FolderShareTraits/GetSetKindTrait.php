<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity kind field.
 *
 * This trait includes get methods for FolderShare entity kind field,
 * along with utility functions to test for specific kinds of items.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetKindTrait {

  /*---------------------------------------------------------------------
   *
   * Kind field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getKind() {
    return $this->get('kind')->value;
  }

  /**
   * Sets the item kind.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The caller must call save() for the change to take effect.
   *
   * @param string $kind
   *   The new kind. The value is not validated but is expected to
   *   be one of the known kind names (e.g. FOLDER_KIND, FILE_KIND,
   *   IMAGE_KIND, or MEDIA_KIND).
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getKind()
   * @see ::isFile()
   * @see ::isFolder()
   * @see ::isImage()
   * @see ::isMedia()
   */
  private function setKind(string $kind) {
    $this->kind->setValue($kind);
  }

  /*---------------------------------------------------------------------
   *
   * Test kind field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function isFile() {
    return ($this->get('kind')->value === self::FILE_KIND);
  }

  /**
   * {@inheritdoc}
   */
  public function isFolder() {
    return ($this->get('kind')->value === self::FOLDER_KIND);
  }

  /**
   * {@inheritdoc}
   */
  public function isImage() {
    return ($this->get('kind')->value === self::IMAGE_KIND);
  }

  /**
   * {@inheritdoc}
   */
  public function isMedia() {
    return ($this->get('kind')->value === self::MEDIA_KIND);
  }

}
