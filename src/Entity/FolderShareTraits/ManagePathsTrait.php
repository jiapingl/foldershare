<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\user\Entity\User;

use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\Exception\NotFoundException;

/**
 * Manage FolderShare entity paths.
 *
 * This trait includes methods to create and parse paths.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManagePathsTrait {

  /*---------------------------------------------------------------------
   *
   * Build paths.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    $names = $this->findAncestorFolderNames();
    if (empty($names) === TRUE) {
      return '/' . $this->getName();
    }

    return '/' . implode('/', $names) . '/' . $this->getName();
  }

  /*---------------------------------------------------------------------
   *
   * Parse paths.
   *
   *---------------------------------------------------------------------*/

  /**
   * Parses a path into its components.
   *
   * Paths have the form:
   * - SCHEME://UID/PATH...
   * - SCHEME://ACCOUNT/PATH...
   *
   * Each component is semi-optional and has defaults:
   * - SCHEME://UID/ refers to the root items of user UID.
   * - SCHEME://ACCOUNT/ refers to the root items of user ACCOUNT.
   * - SCHEME:/PATH assumes the current user.
   * - SCHEME:/ refers to the root items of the current user.
   * - //UID/PATH defaults to the "personal" scheme of user UID.
   * - //ACCOUNT/PATH defaults to the "personal" scheme of user ACCOUNT.
   * - /PATH defaults to the "personal" scheme of the current user.
   * - / defaults to the "personal" scheme of the current user.
   *
   * Malformed paths throw exceptions:
   * - SCHEME:///PATH.
   * - SCHEME:///.
   * - SCHEME://.
   * - SCHEME:.
   * - SCHEME.
   * - :///PATH.
   * - ://PATH.
   * - :/PATH.
   * - :///.
   * - ://
   * - :/.
   * - ///PATH.
   * - ///.
   * - //.
   *
   * The SCHEME selects a category of content from the current user's
   * point of view. The following values are supported:
   * - "personal" = the user's own content.
   * - "public" = the site's public content.
   *
   * The SCHEME is automatically converted to lower case. All other
   * parts of a path are case sensitive.
   *
   * If no SCHEME is provided, the default is "personal".
   *
   * The UID and ACCOUNT indicate the owner of the content. The UID must
   * be numeric. It is not an error for the UID to be invalid - but no
   * content will be found if it is. The ACCOUNT must be valid is is looked
   * up to get the corresponding UID.
   *
   * If no UID or ACCOUNT is provided, the default is the current user.
   *
   * The PATH, starting with '/', gives a chain of folder names, starting
   * with a root item and ending with a file or folder name. A minimal
   * path is '/' alone, which refers to the list of root items in SCHEME.
   * For instance, "personal:/" prefers to user's own list of root items
   * and any root items shared with them.
   *
   * The UID or ACCOUNT are needed to disambiguate among multiple root items
   * available to the current user. Among a user's own root items, this is
   * never a problem because root item names must be unique for a user.
   * But when root items are shared with another user or the public, the
   * set of shared or public root items may include multiple root items
   * from different users, combined into the same namespace. It becomes
   * possible for there to be two root items named "ABC" in a user's
   * shared list of root items. In this case, the UID or ACCOUNT of one
   * of the "ABC" root items is needed to determine which one is desired.
   *
   * @param string $path
   *   The path in the form SCHEME://UID/PATH or SCHEME://ACCOUNT/PATH,
   *   where the SCHEME, UID, ACCOUNT, and PATH are each optional, but
   *   certain combinations that skip them yield a malformed syntax that
   *   causes an exception to be thrown.
   *
   * @return array
   *   Returns an associative array with keys 'scheme', 'uid', and 'path'
   *   that contain the parts of the path, or the defaults if a part was not
   *   provided. If the path includes an ACCOUNT, that ACCOUNT is looked up
   *   and the UID for the account returned in the array.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if the path is malformed:
   *   - The incoming path is empty.
   *   - The SCHEME does not have a known value.
   *   - A UID is provided, but it is invalid.
   *   - An ACCOUNT is provided, but it is invalid.
   *   - A UID or ACCOUNT is provided, but there is no path after it.
   *   - The path does not start with '/'.
   *
   * @internal
   * All text handling here must be multi-byte character safe.
   * @endinternal
   */
  public static function parsePath(string $path) {
    //
    // Look for SCHEME
    // ---------------
    // A path may begin with a SCHEME and colon.
    if (empty($path) === TRUE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t('The folder path is empty.'),
        t('Please enter a path with folder names separated by the "/" character.')));
    }

    $parts = mb_split(':', $path, 2);
    $uid = NULL;
    if (count($parts) === 2) {
      // SCHEME found. Convert it to lower case and use the remainder
      // of the string as the path.
      $scheme = mb_convert_case($parts[0], MB_CASE_LOWER);
      $path = $parts[1];

      switch ($scheme) {
        case self::PERSONAL_SCHEME:
        case self::PUBLIC_SCHEME:
          break;

        default:
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'The folder path uses an invalid scheme "@name".',
              [
                '@name' => $scheme,
              ]),
            t('Paths optionally may start with "personal://" or "public://" to indicate personal or public content.')));
      }
    }
    else {
      // No SCHEME found. Use default.
      $scheme = self::PERSONAL_SCHEME;
    }

    //
    // Look for UID or ACCOUNT
    // -----------------------
    // Following the SCHEME may be '//' and a user UID or ACCOUNT name.
    $slashslash = mb_substr($path, 0, 2);
    if ($slashslash === '//') {
      // UID or ACCOUNT found. Extract it up to the next '/'.
      $endOfUser = mb_stripos($path, '/', 2);
      if ($endOfUser === FALSE) {
        // No further '/' found after the UID. Malformed.
        throw new ValidationException(Utilities::createFormattedMessage(
          t('The folder path is missing a top-level folder name.'),
          t('The path may have several forms. The simplest starts with a "/" and is followed by a top-level folder name and further file and folder names separated by "/". The path may be preceeded with "personal://" or "public://" to select personal or public content. If a path is ambiguous because multiple top-level folders have the same name, then the "//" may be followed by an account name or integer user ID.'),
          t('Examples:'),
          '/myfolder/myfile.txt<br>personal:/myfolder/myfile.txt<br>public:/publicfolder/publicfile.txt<br>//username/theirfolder/theirfile.txt<br>//123/theirfolder/theirfile.txt'));
      }

      // Determine if the value is a positive integer user ID or a
      // string account name.
      $userIdOrAccount = mb_substr($path, 2, ($endOfUser - 2));
      if (mb_ereg_match('^[0-9]?$', $userIdOrAccount) === TRUE) {
        // It's an integer user ID.
        $uid = (int) intval($userIdOrAccount);

        // Make sure the UID is valid.
        $user = User::load($uid);
        if ($user === NULL) {
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'The user ID "@id" is not recognized.',
              [
                '@id' => $uid,
              ]),
            t('Please check that the integer ID is correct for an existing user account.')));
        }
      }
      else {
        // It's a string account name. Look up the account.
        $user = user_load_by_name($userIdOrAccount);
        if ($user === FALSE) {
          // Unknown user!
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'The user account "@name" is not recognized.',
              [
                '@id' => $userIdOrAccount,
              ]),
            t('Please check that the name is correct for an existing user account.')));
        }

        $uid = (int) $user->id();
      }

      // The rest of incoming path is the actual folder path.
      $path = mb_substr($path, $endOfUser);
    }
    else {
      $user = \Drupal::currentUser();
      $uid = (int) $user->id();
    }

    //
    // Look for PATH
    // -------------
    // The path is the remainder after the optional SCHEME, UID, or ACCOUNT.
    // Insure that it starts with a '/'.
    $slash = mb_substr($path, 0, 1);
    if ($slash !== '/') {
      throw new ValidationException(Utilities::createFormattedMessage(
        t('The folder path is missing a top-level folder name.'),
        t('The path may have several forms. The simplest starts with a "/" and is followed by a top-level folder name and further file and folder names separated by "/". The path may be preceeded with "personal://" or "public://" to select personal or public content. If a path is ambiguous because multiple top-level folders have the same name, then the "//" may be followed by an account name or integer user ID.'),
        t('Examples:'),
        '/myfolder/myfile.txt<br>personal:/myfolder/myfile.txt<br>public:/publicfolder/publicfile.txt<br>//username/theirfolder/theirfile.txt<br>//123/theirfolder/theirfile.txt'));
    }

    // Clean the path by removing any embedded '//' or a trailing '/'.
    $cleanedPath = mb_ereg_replace('%//%', '/', $path);
    if ($cleanedPath !== FALSE) {
      $path = $cleanedPath;
    }

    $cleanedPath = mb_ereg_replace('%/?$%', '', $path);
    if ($cleanedPath !== FALSE) {
      $path = $cleanedPath;
    }

    return [
      'scheme' => $scheme,
      'uid'    => $uid,
      'path'   => $path,
    ];
  }

  /**
   * Returns the entity ID identified by a path.
   *
   * Paths have the form:
   * - SCHEME://UID/PATH...
   *
   * where SCHEME is the name of a root item list ("public" or "personal"),
   * UID is the user ID or account name, and PATH is a folder path.
   *
   * The path is broken down into a list of ancestors and each ancestor
   * looked up, starting with the root item. The indicated child is
   * found in that ancestor, and so forth down to the last entity on the
   * path. That entity is returned.
   *
   * @param string $path
   *   The path in the form SCHEME://UID/PATH, or one of its subforms.
   *
   * @return int
   *   Returns the entity ID of the last entity on the path.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if the path is malformed.
   * @throws \Drupal\foldershare\Entity\Exception\NotFoundException
   *   Throws an exception if the path contains a root item, parent
   *   folder, or child that could not be found.
   *
   * @see self::parsePath()
   *
   * @internal
   * All text handling here must be multi-byte character safe.
   * @endinternal
   */
  public static function findPathItemId(string $path) {
    //
    // Parse path
    // ----------
    // Parse the path into SCHEME, UID, ACCOUNT, and PATH components, or their
    // defaults. Throw an exception if the path is malformed.
    $components = self::parsePath($path);

    // Split the path into a chain of folder names, ending with a
    // file or folder name. Since the path must start with '/', the
    // first returned part will be empty. Ignore it.
    if ($components['path'] === '/') {
      throw new NotFoundException(Utilities::createFormattedMessage(
        t('The folder path is missing a top-level folder name.'),
        t('The path may have several forms. The simplest starts with a "/" and is followed by a top-level folder name and further file and folder names separated by "/". The path may be preceeded with "personal://" or "public://" to select personal or public content. If a path is ambiguous because multiple top-level folders have the same name, then the "//" may be followed by an account name or integer user ID.'),
        t('Examples:'),
        '/myfolder/myfile.txt<br>personal:/myfolder/myfile.txt<br>public:/publicfolder/publicfile.txt<br>//username/theirfolder/theirfile.txt<br>//123/theirfolder/theirfile.txt'));
    }

    $parts = mb_split('/', $components['path']);
    array_shift($parts);

    //
    // Find root item
    // --------------
    // The root item name is the first in the list. Use it and a possible
    // user ID to look up candidate root items based upon the scheme.
    $rootName = array_shift($parts);

    // Get a list of matching root items.
    $uid = $components['uid'];
    switch ($components['scheme']) {
      default:
      case self::PERSONAL_SCHEME:
        // If no UID is given, default to the current user.
        if ($uid === NULL || $uid < 0) {
          $uid = \Drupal::currentUser()->id();
        }

        $ownedRootItems = self::findAllRootItems($uid, $rootName);
        $sharedRootItems = self::findAllSharedRootItems(
          self::ANY_USER_ID,
          $uid,
          $rootName);
        $rootItems = array_merge($ownedRootItems, $sharedRootItems);
        break;

      case self::PUBLIC_SCHEME:
        // If no UID is given, default to ANY_USER_ID to get public
        // owned by anyone.
        if ($uid === NULL) {
          $uid = self::ANY_USER_ID;
        }

        $rootItems = self::findAllPublicRootItems($uid, $rootName);
        break;
    }

    if (empty($rootItems) === TRUE) {
      throw new NotFoundException(Utilities::createFormattedMessage(
        t(
          '"@path" could not be found.',
          [
            '@path' => $path,
          ]),
        t('Please check that the file and folder path is correct.')));
    }

    if (count($rootItems) > 1) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The folder path "@path" is ambiguous.',
          [
            '@path' => $path,
          ]),
        t('When there are more than one top-level items with the same name, you can select a specific item by starting the path with "public://" or "personal://", followed by a user account name or integer ID, and then the path for the item you want.'),
        t('Examples:'),
        'personal://username/theirfolder/theirfile.txt<br>public://123/theirfolder/theirfile.txt'));
    }

    $rootItem = reset($rootItems);
    $id = $rootItem->id();

    //
    // Follow descendants
    // ------------------
    // The remaining parts of the path must be descendants of the root item.
    // Follow the path downwards.
    foreach ($parts as $name) {
      $id = self::findNamedChildId($id, $name);
      if ($id === FALSE) {
        throw new NotFoundException(Utilities::createFormattedMessage(
          t(
            '"@path" could not be found.',
            [
              '@path' => $path,
            ]),
          t('Please check that the file and folder path is correct.')));
      }
    }

    return $id;
  }

}
