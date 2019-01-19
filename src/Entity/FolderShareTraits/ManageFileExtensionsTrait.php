<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

/**
 * Manages filename extensions.
 *
 * This trait includes get and set methods for file name extensions
 * associated with the FolderShare entity. During file upload and rename,
 * file name extensions may be restricted to those on an approved list.
 * If the list is empty, all extensions are allowed.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManageFileExtensionsTrait {

  /*---------------------------------------------------------------------
   *
   * Extension parsing.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the file name extension in lower case.
   *
   * This function uses multi-byte functions to support UTF-8 paths.
   *
   * @param string $path
   *   The URI or local path to parse.
   *
   * @return string
   *   Returns the part of the path after the last '.', converted to
   *   lower case. If there is no '.', an empty string is returned.
   */
  private static function getExtensionFromPath(string $path) {
    $lastDotIndex = mb_strrpos($path, '.');
    if ($lastDotIndex === FALSE) {
      return '';
    }

    return mb_convert_case(
      mb_substr($path, ($lastDotIndex + 1)),
      MB_CASE_LOWER);
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension() {
    if ($this->isFolder() === TRUE) {
      return '';
    }

    return self::getExtensionFromPath($this->getName());
  }

  /*---------------------------------------------------------------------
   *
   * Extension testing.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the file name is using an allowed file extension.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The text following the last '.' in the given file name is extracted
   * as the name's extension, then checked against the given array of
   * allowed extensions. If the name is found, TRUE is returned.
   *
   * If the file name has no '.', it has no extension, and TRUE is
   * returned.
   *
   * If the extensions array is empty, all extensions are accepted and
   * TRUE is returned.
   *
   * @param string $path
   *   The local path to parse.
   * @param array $extensions
   *   An array of allowed file name extensions. If the extensions array
   *   is empty, all extensions are allowed.
   *
   * @return bool
   *   Returns TRUE if the name has no extension, the extensions array is
   *   empty, or if it uses an allowed extension, and FALSE otherwise.
   *
   * @see ::getAllowedNameExtensions()
   */
  private static function isNameExtensionAllowed(
    string $path,
    array $extensions) {

    // If there are no extensions to check, then any name is allowed.
    if (count($extensions) === 0) {
      return TRUE;
    }

    $ext = self::getExtensionFromPath($path);
    if (empty($ext) === TRUE) {
      // No extension. Default to allowed.
      return TRUE;
    }

    // Look for in allowed extensions array.
    return in_array($ext, $extensions);
  }

  /**
   * Returns TRUE if the ZIP file name extension is allowed.
   *
   * @return bool
   *   Returns TRUE if it is allowed, and FALSE otherwise.
   */
  private static function isZipExtensionAllowed() {
    $extensionsString = self::getAllowedNameExtensions();
    if (empty($extensionsString) === TRUE) {
      // No extension restrictions.
      return TRUE;
    }

    $extensions = mb_split(' ', $extensionsString);
    foreach ($extensions as $ext) {
      if ($ext === 'zip') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /*---------------------------------------------------------------------
   *
   * Extensions for file and image fields.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns file name extensions allowed for files and images in a folder.
   *
   * The field definition for the 'file' field (which is always the same
   * as for the 'image' field) is queried and its current file name extensions
   * setting returned. This setting is a single string containing a
   * space-separated list of allowed file name extensions. Extensions do
   * not include a leading "dot".
   *
   * File name extensions are always lower case. There are no redundant
   * extensions. Extensions in the list are not ordered.
   *
   * If the list of extensions is empty, then any extension is allowed
   * for uploaded and renamed files.
   *
   * @return string
   *   Returns a string containing a space-separated list of file
   *   extensions (without the leading dot) supported for files.
   *
   * @see ::isNameExtensionAllowed()
   * @see ::setAllowedNameExtensions()
   * @see \Drupal\foldershare\Settings::getAllowedNameExtensionsDefault()
   * @see \Drupal\foldershare\Settings::getAllowedNameExtensions()
   */
  public static function getAllowedNameExtensions() {
    // Get the extensions string on the 'file' field. These will always be
    // the same as on the 'image' field.
    $m = \Drupal::service('entity_field.manager');
    $def = $m->getFieldDefinitions(
      self::ENTITY_TYPE_ID,
      self::ENTITY_TYPE_ID);

    return $def['file']->getSetting('file_extensions');
  }

  /**
   * Sets the file name extensions allowed for files and images in a folder.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> The method is only public so that it may be used by
   * other classes within the module.
   *
   * The field definitions for the 'file' and 'image' fields are changed and
   * their current file name extensions settings updated. This setting is a
   * single string containing a space-separated list of allowed file name
   * extensions. Extensions do not include a leading "dot".
   *
   * File name extensions are automatically folded to lower case.
   * Redundant extensions are removed.
   *
   * If the list of extensions is empty, then any extension is allowed
   * for uploaded and renamed files.
   *
   * @param string $extensions
   *   A string containing a space list of file name extensions
   *   (without the leading dot) supported for folder files.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the field definition entity.
   *
   * @see ::getAllowedNameExtensions()
   * @see \Drupal\foldershare\Settings::getAllowedNameExtensionsDefault()
   * @see \Drupal\foldershare\Settings::setAllowedNameExtensions()
   */
  public static function setAllowedNameExtensions(string $extensions) {
    if (empty($extensions) === TRUE) {
      // The given extensions list is empty, so no further processing
      // is required.
      $uniqueExtensions = '';
    }
    else {
      // Fold the entire string to lower case. Then split it into
      // individual extensions.  Use multi-byte character functions
      // so that we can support UTF-8 file names and extensions.
      $extList = mb_split(' ', mb_convert_case($extensions, MB_CASE_LOWER));

      // Check for and remove any leading dot on extensions.
      foreach ($extList as $key => $value) {
        if (mb_strpos($value, '.') === 0) {
          $extList[$key] = mb_substr($value, 1);
        }
      }

      // Remove redundant extensions and rebuild the list string.
      $uniqueExtensions = implode(' ', array_unique($extList));
    }

    // Set the extensions string on the 'file' and 'image' fields.
    $m = \Drupal::service('entity_field.manager');
    $def = $m->getFieldDefinitions(
      self::ENTITY_TYPE_ID,
      self::ENTITY_TYPE_ID);

    $cfd = $def['file']->getConfig(self::ENTITY_TYPE_ID);
    $cfd->setSetting('file_extensions', $uniqueExtensions);
    $cfd->save();

    $cfd = $def['image']->getConfig(self::ENTITY_TYPE_ID);
    $cfd->setSetting('file_extensions', $uniqueExtensions);
    $cfd->save();
  }

}
