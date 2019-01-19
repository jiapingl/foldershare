<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Get/set FolderShare entity mime field.
 *
 * This trait includes get methods for FolderShare entity mime field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetMimeTrait {

  /*---------------------------------------------------------------------
   *
   * Mime field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getMimeType() {
    return $this->get('mime')->value;
  }

  /**
   * Sets the item's MIME type.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The MIME type is not validated. The caller should insure that it is
   * not empty and has a legal form. When this item is a file or image,
   * the MIME type should match that of the underlying stored file or image.
   * This is often done by using the MIME type guesser service and the
   * file's extension.
   *
   * For folders, FOLDER_MIME should be used since there is no file to
   * guess from and no standard MIME type.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param string $mime
   *   The new MIME type. The value is not validated but is expected to
   *   be a properly formed MIME type.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getMimeType()
   * @see ::isMimeTypeImage()
   * @see \Drupal\file\FileInterface::setMimeType()
   */
  private function setMimeType(string $mime) {
    $this->set('mime', $mime);
  }

  /*---------------------------------------------------------------------
   *
   * Mime utilities.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if a MIME type refers to an image type.
   *
   * MIME types are a concatenation of a top-level type name, a "/",
   * and a subtype name with an optional prefix, suffix, and parameters.
   * The top-level type name is one of several well-known names:
   * - application.
   * - audio.
   * - example.
   * - font.
   * - image.
   * - message.
   * - model.
   * - multipart.
   * - text.
   * - video.
   *
   * This function returns TRUE if the top-level type name is 'image'.
   *
   * @param string $mimeType
   *   The MIME type to check.
   *
   * @return bool
   *   Returns TRUE if the MIME type is for an image, and FALSE otherwise.
   */
  public static function isMimeTypeImage(string $mimeType) {
    list($topLevel,) = explode('/', $mimeType, 2);
    return ($topLevel === 'image');
  }

}
