<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Get/set FolderShare entity name field.
 *
 * This trait includes get and set methods for FolderShare entity name field,
 * along with utility functions that check if a name is unique.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetSetNameTrait {

  /*---------------------------------------------------------------------
   *
   * Name field.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * Sets the item's name.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The name is set without validation. The caller should insure that the
   * name is not empty, not too long, does not include illegal characters,
   * and does not use file name extensions that are not allowed by the site.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param string $name
   *   The new name of the item. The name is not validated but is expected
   *   to be of legal content and length and not to collide with any other
   *   name in the item's parent folder or root list.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getName()
   * @see ::rename()
   * @see ::isNameLegal()
   */
  private function setName(string $name) {
    $this->set('name', $name);
  }

  /*---------------------------------------------------------------------
   *
   * Name legality.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the proposed name is legal.
   *
   * A name is legal if:
   * - It is not empty.
   * - It has 255 characters or less.
   * - It does not contain reserved characters ':', '/', and '\'.
   *
   * This function does not check if the name is using any file name
   * extensions that are not allowed by the site.
   *
   * @param string $name
   *   The proposed name.
   *
   * @return bool
   *   Returns TRUE if the name is legal, and FALSE otherwise.
   *
   * @see ::MAX_NAME_LENGTH
   * @see ::checkName()
   */
  public static function isNameLegal(string $name) {
    // Note: Must use multi-byte functions to insure UTF-8 support.
    return (empty($name) === FALSE) &&
      (mb_strlen($name) <= self::MAX_NAME_LENGTH) &&
      (mb_ereg('[:\/\\\]', $name) === FALSE);
  }

  /*---------------------------------------------------------------------
   *
   * Name uniqueness checking.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function isNameUnique(string $name, int $inUseId = self::ANY_ITEM_ID) {
    // Get an array of all child file and folder names. The array has
    // names as keys and IDs as values.
    $childNames = $this->findChildrenNames();

    // If the proposed name is not in the list, return TRUE.
    if (isset($childNames[$name]) === FALSE) {
      return TRUE;
    }

    // If we have an exclusion ID and that's the child name list
    // entry that matches the given name, return TRUE anyway because
    // the name is in use by the intended ID.
    if ($inUseId >= 0 && $childNames[$name] === $inUseId) {
      return TRUE;
    }

    // Otherwise the name is already in use, and not by the exclusion ID.
    return FALSE;
  }

  /**
   * Returns TRUE if a proposed name is unique among root items.
   *
   * The $name argument specifies a proposed name for an existing or new
   * root item. The name is not validated and is presumed to be
   * of legal length and structure.
   *
   * The optional $inUseId indicates the ID of an existing root item that is
   * already using the name. If the value is not given, negative, or
   * FolderShareInterface::ANY_ITEM_ID, then it is presumed that no current
   * root item has the proposed name.
   *
   * The optional $uid selects the user ID for whome root items are checked.
   * If this value is not given or it is FolderShareInterface::ANY_ITEM_ID,
   * the root items for the current user are checked.
   *
   * This function looks through the names of root items and returns TRUE
   * if the proposed name is not in use by any root item, except the indicated
   * $inUseId, if any. If the name is in use by a root item that is not
   * $inUseId, then FALSE is returned.
   *
   * @param string $name
   *   A proposed root item name.
   * @param int $inUseId
   *   (optional, default = FolderShareInterface::ANY_ITEM_ID) The ID of an
   *   existing item that is already using the proposed name.
   * @param int $uid
   *   (optional, default = FolderShareInterface::CURRENT_USER_ID) The user ID
   *   of the user among whose root items the name must be unique. If the
   *   value is negative or FolderShareInterface::CURRENT_USER_ID, the
   *   current user ID is used.
   *
   * @return bool
   *   Returns TRUE if the name is unique among this user's root items,
   *   and FALSE otherwise.
   *
   * @see ::findAllRootItemNames()
   * @see ::getName()
   * @see ::isNameLegal()
   * @see ::isNameUnique()
   * @see ::createUniqueName()
   */
  public static function isRootNameUnique(
    string $name,
    int $inUseId = self::ANY_ITEM_ID,
    int $uid = self::CURRENT_USER_ID) {

    // Get an array of all root item names for this user.
    // The array has names as keys and IDs as values.
    $rootNames = self::findAllRootItemNames(
      ($uid < 0) ? \Drupal::currentUser()->id() : $uid);

    // If the proposed name is not in the list, return TRUE.
    if (isset($rootNames[$name]) === FALSE) {
      return TRUE;
    }

    // If we have an exclusion ID and that's the root name list
    // entry that matches the given name, return TRUE anyway because
    // the name is in use by the intended ID.
    if ($inUseId >= 0 && $rootNames[$name] === $inUseId) {
      return TRUE;
    }

    // Otherwise the name is already in use, and not by the exclusion ID.
    return FALSE;
  }

  /*---------------------------------------------------------------------
   *
   * Unique name creation.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a name, or variant, adjusted to insure uniqueness.
   *
   * The $namesInUse argument provides a list of names already in use,
   * such as the names of root items or children in a folder. The new
   * unique name will avoid all of these names.
   *
   * The $name argument provides the proposed new name. If the name is
   * not in the $namesInUse, then it is already unique and is returned.
   * Otherwise, the $name is modified to make it unique, and then returned.
   *
   * To make the name unique, this function adds an optional $suffix and
   * a number, incrementing the number until the name is unique. If necessary,
   * the name is truncated in order to stay under the maximum name length
   * limit.
   *
   * A typical suffix might be " copy" (note the leading space to separate
   * it from the body of the name).
   *
   * The suffix and numbers are added immediately before the last dot in
   * the name. If there are no dots in the name, the suffix and numbers are
   * added to the end of the name.
   *
   * False is returned under several conditions:
   * - The given name is empty.
   *
   * - The suffix is >= 255 characters, leaving no room for the name
   *   before it.
   *
   * - The suffix + number is >= 255 characters, which leaves no
   *   room for the name before it.
   *
   * - No unique name could be found after running through
   *   all possible name + suffix + number + extension results for
   *   increasing numbers.  However, this is extrordinarily unlikely
   *   since it would require a huge names-in-use list that would probably
   *   exceed the memory limits of a PHP instance, or the time allotted
   *   to the instance.
   *
   * @param array $namesInUse
   *   An associative array of names to in use, where keys are names and values
   *   are entity IDs. Such a name list is returned by findChildreNames() and
   *   findAllRootItemNames().
   * @param string $name
   *   A proposed name.
   * @param string $suffix
   *   (optional, default = '') A suffix to add during tries to find a
   *   new unique name that doesn't collide with any of the exclusion names.
   *
   * @return false|string
   *   Returns a unique name that starts with the given name and
   *   may include the given suffix and a number such that it does not
   *   collide with any of the names in $namesInUse.  FALSE is returned
   *   on failure.
   *
   * @section example Example usage
   * For name "myfile.png" and suffix " copy", the names
   * tested (in order) are:
   * - "myfile.png"
   * - "myfile copy.png"
   * - "myfile copy 1.png"
   * - "myfile copy 2.png"
   * - "myfile copy 3.png"
   * - ...
   *
   * For name "myfolder" and suffix " archive", the names tested (in order)
   * are:
   * - "myfolder"
   * - "myfolder archive"
   * - "myfolder archive 1"
   * - "myfolder archive 2"
   * - "myfolder archive 3"
   * - ...
   *
   * Create a unique name among root items for the current user:
   * @code
   * $uid = \Drupal::currentUser()->id();
   * $names = FolderShare::findAllRootItemNames($uid);
   * $uniqueName = FolderShare::createUniqueName($names, $name);
   * @endcode
   *
   * @see ::isNameLegal()
   * @see ::isNameUnique()
   * @see ::isRootNameUnique()
   * @see ::findChildrenNames()
   * @see ::findAllRootItemNames()
   * @see ::MAX_NAME_LENGTH
   */
  public static function createUniqueName(
    array $namesInUse,
    string $name,
    string $suffix = '') {

    // Validate.
    // ---------
    // If no name, then fail.
    if (empty($name) === TRUE) {
      return FALSE;
    }

    //
    // Check for unmodified name.
    // --------------------------
    // If name is not in use, then allow it.
    if (isset($namesInUse[$name]) === FALSE) {
      return $name;
    }

    //
    // Setup for renaming.
    // -------------------
    // Break down the name into a base name before the LAST '.',
    // and the extension after the LAST '.'.  There may be no
    // extension if there is no '.'.
    //
    // Note:  We must use multi-byte functions to support the
    // multi-byte characters of UTF-8 names.
    $lastDotIndex = mb_strrpos($name, '.');
    if ($lastDotIndex === FALSE) {
      // No '.' found. Base is entire string. Extension is empty.
      $base = $name;
      $ext = '';
    }
    else {
      // Found '.'. Base is everything up to the '.'. Extension
      // is everything after it.
      $base = mb_substr($name, 0, $lastDotIndex);
      $ext = mb_substr($name, $lastDotIndex);
    }

    if ($suffix === NULL) {
      $suffix = '';
    }

    if (mb_strlen($suffix . $ext) >= self::MAX_NAME_LENGTH) {
      // The suffix and/or extension are huge. They leave no
      // room for the base name within the character budget.
      // There is no name modification we can do that will produce
      // a short enough name.
      return FALSE;
    }

    //
    // Check for name + suffix + extension.
    // ------------------------------------
    // If there is a suffix, check that there's room to add it.
    // Then add it and see if that is sufficient to create a
    // unique name.
    if (empty($suffix) === FALSE) {
      $name = $base . $suffix . $ext;

      if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
        // The built name is too long.  Crop the base name.
        $len = (self::MAX_NAME_LENGTH - mb_strlen($name));
        $base = mb_substr($base, 0, $len);
        $name = $base . $suffix . $ext;
      }

      if (isset($namesInUse[$name]) === FALSE) {
        return $name;
      }
    }

    //
    // Check for name + suffix + number + extension.
    // ---------------------------------------------
    // Otherwise, start adding a number, counting up from 1.
    // This search continues indefinitely until a number is found
    // that is not in use.
    $num = 1;

    // Intentional infinite loop.
    while (TRUE) {
      $name = $base . $suffix . ' ' . $num . $ext;

      if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
        // The built name is too long.  Crop the base name.
        $len = (self::MAX_NAME_LENGTH - mb_strlen($name));
        if ($len <= 0) {
          break;
        }

        $base = mb_substr($base, 0, $len);
        $name = $base . $suffix . ' ' . $num . $ext;
      }

      if (isset($namesInUse[$name]) === FALSE) {
        return $name;
      }

      ++$num;
    }

    // One of two errors has occurred:
    // - Every possible name has been generated and found in use.
    //
    // - The suffix + number + extension for a large number has
    //   consumed the entire character budget for names.  There is
    //   no room left for even one character of the original name.
    //
    // These are both very unlikely errors. They require either a
    // huge set of names already in use, or an unreasonably large
    // suffix and extension that left very little room to search for
    // a new name.
    return FALSE;
  }

  /*---------------------------------------------------------------------
   *
   * Name check.
   *
   *---------------------------------------------------------------------*/

  /**
   * Checks if a proposed new name for this item is legal and usable.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The proposed name is checked that it is of legal length and content,
   * and that it does not use any file name extensions restricted on this
   * site (if this item is a file or image). On failure, an exception is
   * thrown with a standard error message.
   *
   * This is a convenience function used during move and rename operations.
   *
   * @param string $newName
   *   The proposed new name for this item.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if the name uses illegal characters, or if the
   *   name uses a file name extension that is not allowed (for file and
   *   image kinds only).
   *
   * @see ::isNameLegal()
   * @see ::isNameExtensionAllowed()
   */
  public function checkName(string $newName) {
    // Check that the new name is legal.
    if (self::isNameLegal($newName) === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" cannot be used.',
          [
            '@name' => $newName,
          ]),
        t('Try using a name with fewer characters and avoid punctuation marks like ":", "/", and "\\".')));
    }

    // If this item is a file or image, verify that the name meets any
    // file name extension restrictions.
    if ($this->isFile() === TRUE || $this->isImage() === TRUE) {
      $extensionsString = self::getAllowedNameExtensions();
      if (empty($extensionsString) === FALSE) {
        $extensions = mb_split(' ', $extensionsString);
        if (self::isNameExtensionAllowed($newName, $extensions) === FALSE) {
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'The file type used by "@name" is not supported.',
              [
                '@name' => $newName,
              ]),
            t(
              'The file name uses a file type extension "@extension" that is not supported on this site.',
              [
                '@extension' => self::getExtensionFromPath($newName),
              ]),
            t('Supported file type extensions:'),
            implode(', ', $extensions)));
        }
      }
    }
  }

}
