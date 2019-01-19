<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\Core\Database\Database;
use Drupal\file\FileInterface;
use Drupal\user\Entity\User;

/**
 * Find FolderShare entities.
 *
 * This trait includes find methods that query across all FolderShare
 * entities, or within a specific folder, and return IDs, entities, or
 * other values that match criteria.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait FindTrait {

  /*---------------------------------------------------------------------
   *
   * Find root-level items.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a list of root items that meet user and name requirements.
   *
   * An optional user ID may be provided to constrain root items to only
   * those owned by the specified user. If the ID is not given, or it is
   * FolderShareInterface::ANY_USER_ID, root items for any user are returned.
   *
   * An optional name may be provided to constrain root items to only those
   * with the given name. If a name is not given, or it is an empty string,
   * root items with any name are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID for
   *   the root items' owner.
   * @param string $name
   *   (optional, default = '' = any) The name for the root items.
   *
   * @return \Drupal\foldershare\FolderShareInterface[]
   *   Returns an unordered associative array where keys are entity IDs
   *   and values are entities for root items.
   *
   * @section examples Example usage
   * Find all root items for all users, for a specific user, or
   * for a specific user and named "data":
   * @code
   *   $allRootItems = FolderShare::findAllRootItems();
   *
   *   $user = \Drupal::currentUser();
   *   $allRootItemsByUser = FolderShare::findAllRootItems($user->id());
   *
   *   $allRootItemsByUserNamedData = FolderShare::findAllRootItems($user->id(), "data");
   * @endcode
   *
   * @see ::findAllIds()
   * @see ::findAllRootItemNames()
   * @see ::findAllPublicRootItems()
   * @see ::findAllSharedRootItems()
   */
  public static function findAllRootItems(
    int $ownerUid = self::ANY_USER_ID,
    string $name = '') {

    return self::loadMultiple(self::findAllRootItemIds($ownerUid, $name));
  }

  /**
   * Returns a list of root item IDs that meet user and name requirements.
   *
   * An optional user ID may be provided to constrain root items to only
   * those owned by the specified user. If the ID is not given, or it is
   * FolderShareInterface::ANY_USER_ID, root items for any user are returned.
   *
   * An optional name may be provided to constrain root items to only those
   * with the given name. If a name is not given, or it is an empty string,
   * root items with any name are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the root items' owner.
   * @param string $name
   *   (optional, default = '' = any) The name for the root items.
   *
   * @return int[]
   *   An unordered list of integer entity IDs for root items.
   *
   * @section examples Example usage
   * Find all root item IDs for all users, for a specific user, or
   * for a specific user and named "data":
   * @code
   *   $allRootItemIds = FolderShare::findAllRootItemIds();
   *
   *   $user = \Drupal::currentUser();
   *   $allRootItemIdsByUser = FolderShare::findAllRootItemIds($user->id());
   *
   *   $allRootItemIdsByUserNamedData = FolderShare::findAllRootItemIds($user->id(), "data");
   * @endcode
   *
   * @see ::findAllIds()
   * @see ::findAllRootItems()
   * @see ::findAllRootItemNames()
   */
  public static function findAllRootItemIds(
    int $ownerUid = self::ANY_USER_ID,
    string $name = '') {

    // Root items have no parent ID.
    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->isNull('parentid');

    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    if (empty($name) === FALSE) {
      $query->condition('name', $name, '=');
    }

    $ids = [];
    foreach ($query->execute() as $result) {
      $ids[] = (int) $result->id;
    }

    return $ids;
  }

  /**
   * Returns a list of root item names that meet user requirements.
   *
   * An optional user ID may be provided to constrain root items to only
   * those owned by the specified user. If the ID is not given, or it is
   * FolderShareInterface::ANY_USER_ID, root items for any user are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface:ANY_USER_ID) The user ID
   *   for the root item's owner.
   *
   * @return array
   *   Returns an unordered associative array where keys are entity names
   *   and values are entity IDs for root items.
   *
   * @section examples Example usage
   * Find all root item names for all users or for a specific user:
   * @code
   *   $allRootItems = FolderShare::findAllRootItemNames();
   *
   *   $user = \Drupal::currentUser();
   *   $rootItemNames = FolderShare::findAllRootItemNames($user->id());
   * @endcode
   *
   * @see ::findAllIds()
   * @see ::findAllRootItems()
   * @see ::findAllRootItemIds()
   */
  public static function findAllRootItemNames(
    int $ownerUid = self::ANY_USER_ID) {

    // Root items have no parent ID.
    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->addField('fs', 'uid', 'uid');
    $query->addField('fs', 'name', 'name');
    $query->isNull('parentid');

    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    $names = [];
    foreach ($query->execute() as $result) {
      $names[$result->name] = (int) $result->id;
    }

    return $names;
  }

  /**
   * Returns a list of public root items that meet user and name requirements.
   *
   * A public root item is one that is owned by the anonymous user OR one that
   * grants view access to anonymous.
   *
   * An optional user ID may be provided to constrain root items to only
   * those owned by the specified user. If the ID is not given, or it is
   * FolderShareInterface::ANY_USER_ID, root items for any user are returned.
   *
   * An optional name may be provided to constrain root items to only those
   * with the given name. If a name is not given, or it is an empty string,
   * root items with any name are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the root items' owner.
   * @param string $name
   *   (optional, default = '' = any) The name for the root items.
   *
   * @return \Drupal\foldershare\FolderShareInterface[]
   *   Returns an unordered associative array where keys are entity IDs
   *   and values are entities for root items.
   *
   * @section examples Example usage
   * Find all public root items for all users, for a specific user, or
   * for a specific user and named "data":
   * @code
   *   $allPublicRootItems = FolderShare::findAllPublicRootItems();
   *
   *   $user = \Drupal::currentUser();
   *   $allPublicRootItemsByUser = FolderShare::findAllPublicRootItems($user->id());
   *
   *   $allPublicRootItemsByUserNamedData = FolderShare::findAllPublicRootItems($user->id(), "data");
   * @endcode
   *
   * @see ::findAllRootItems()
   * @see ::findAllSharedRootItems()
   */
  public static function findAllPublicRootItems(
    int $ownerUid = self::ANY_USER_ID,
    string $name = '') {

    // Root items have no parent ID.
    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->condition('parentid', NULL, 'IS NULL');

    // Join with the list of people granted view access.
    $query->join(
      'foldershare__grantviewuids',
      'view',
      '(view.entity_id = fs.id)');

    // Then require one of these cases:
    // - The item's owner is anonymous.
    // - The grants for view access include anonymous.
    $anonymousUid = (int) User::getAnonymousUser()->id();
    $group = $query->orConditionGroup()
      ->condition('uid', $anonymousUid, '=')
      ->condition('view.grantviewuids_target_id', $anonymousUid, '=');
    $query = $query->condition($group);

    // Constrain results to those owned by a specified user, or with
    // the specified item name.
    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    if (empty($name) === FALSE) {
      $query->condition('name', $name, '=');
    }

    $ids = [];
    foreach ($query->execute() as $result) {
      $ids[] = (int) $result->id;
    }

    return self::loadMultiple($ids);
  }

  /**
   * Returns a list of shared root items that meet user and name requirements.
   *
   * A shared root item is one that is not owned by the current user AND one
   * that grants view access to the current user.
   *
   * An optional user ID may be provided to constrain root items to only
   * those owned by the specified user. If the ID is not given, or it is
   * FolderShareInterface::ANY_USER_ID, root items for any user are returned.
   *
   * An optional viewer ID may be provided to constrain root items to only
   * those viewable by the specified user. If the ID is not given, or it is
   * FolderShareInterface::CURRENT_USER_ID, root items viewable by the current
   * user are returned.
   *
   * An optional name may be provided to constrain root items to only those
   * with the given name. If a name is not given, or it is an empty string,
   * root items with any name are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USR_ID) The user ID
   *   for the root items owner.
   * @param int $viewerUid
   *   (optional, default = FolderShareInterface::CURRENT_USER_ID) The user ID
   *   for the user that must have view permission (defaults to current user).
   * @param string $name
   *   (optional, default = '' = any) The name for the root items.
   *
   * @return \Drupal\foldershare\FolderShareInterface[]
   *   Returns an unordered associative array where keys are entity IDs
   *   and values are entities for root items.
   *
   * @section examples Example usage
   * Find all shared root items for all users, for a specific user, or
   * for a specific user and named "data":
   * @code
   *   $allSharedRootItems = FolderShare::findAllSharedRootItems();
   *
   *   $user = \Drupal::currentUser();
   *   $allSharedRootItemsByUser = FolderShare::findAllSharedRootItems($user->id());
   *
   *   $allSharedRootItemsByUserNamedData = FolderShare::findAllSharedRootItems($user->id(), "data");
   * @endcode
   *
   * @see ::findAllRootItems()
   * @see ::findAllPublicRootItems()
   */
  public static function findAllSharedRootItems(
    int $ownerUid = self::ANY_USER_ID,
    int $viewerUid = self::CURRENT_USER_ID,
    string $name = '') {

    // Root items have no parent ID.
    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->addField('fs', 'uid', 'uid');
    $query->addField('fs', 'name', 'name');
    $query->isNull('fs.parentid');

    // If a required owner ID is given, add a condition.
    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    // If a required item name is given, add a condition.
    if (empty($name) === FALSE) {
      $query->condition('name', $name, '=');
    }

    // If a required viewer ID is given, add a join and condition.
    if ($viewerUid >= 0) {
      if ($ownerUid < 0) {
        $query->condition('uid', $viewerUid, '!=');
      }

      $query->join(
        'foldershare__grantviewuids',
        'view',
        '(view.entity_id = fs.id)');
      $query->condition('view.grantviewuids_target_id', $viewerUid, '=');
    }

    // Pull out the entity IDs.
    $ids = [];
    foreach ($query->execute() as $result) {
      $ids[] = (int) $result->id;
    }

    return self::loadMultiple($ids);
  }

  /*---------------------------------------------------------------------
   *
   * All items.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a list of item IDs that meet user requirements.
   *
   * An optional user ID may be provided to constrain items to only
   * those owned by the specified user. If the ID is not given, or it is
   * FolderShareInterface::ANY_USER_ID, items for any user are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * Warning: This function can return a list of ALL items at the site.
   * For a large site, this can be a large list that can require a large
   * amount time to query and a large amount of memory to store.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the items' owner.
   *
   * @return int[]
   *   An unordered list of integer entity IDs for items.
   *
   * @section examples Example usage
   * Find all item IDs for all users, or all item IDs owned by a specific user:
   * @code
   *   $allItemIds = FolderShare::findAllIds();
   *
   *   $user = \Drupal::currentUser();
   *   $allItemIdsForUser = FolderShare::findAllIds($user->id());
   * @endcode
   *
   * @see ::findAllFoldersAndUserIds()
   * @see ::findAllRootItemIds()
   * @see ::countNumberOfFolders()
   */
  public static function findAllIds(int $ownerUid = self::ANY_USER_ID) {

    $query = \Drupal::entityQuery(self::BASE_TABLE);
    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    $ids = [];
    foreach ($query->execute() as $result) {
      $ids[] = (int) $result;
    }

    return $ids;
  }

  /*---------------------------------------------------------------------
   *
   * Count queries.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the total number of bytes used by all files or images.
   *
   * When an optional user ID is provided, the returned total only includes
   * items owned by that user. When the user ID is not provided or is
   * FolderShareInterface::ANY_USER_ID, the returned total is for all users.
   *
   * The returned total only includes space used by stored files or images.
   * Folders and media items are not included. The storage space used for
   * database entries for files and images is not included.
   *
   * System hidden and disabled items may be included in the count.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the user for whome the count is returned. A value of
   *   FolderShareInterface::ANY_USER_ID returns a total for all users.
   *
   * @return int
   *   Returns the total number of bytes used for files and images.
   *
   * @section examples Example usage
   * Get the total amount of non-volatile (e.g. disk) storage space in use
   * for files (but not counting the database itself):
   * @code
   *   $bytes = FolderShare::countNumberOfBytes();
   * @endcode
   *
   * @see ::countNumberOfFiles()
   * @see ::countNumberOfFolders()
   * @see ::hasFiles()
   * @see ::hasFolders()
   */
  public static function countNumberOfBytes(
    int $ownerUid = self::ANY_USER_ID) {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $group = $query->orConditionGroup()
      ->condition('kind', self::FILE_KIND, '=')
      ->condition('kind', self::IMAGE_KIND, '=')
      ->condition('kind', self::MEDIA_KIND, '=');
    $query->condition($group);
    $query->addExpression('SUM(size)');

    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    return (int) $query->execute()->fetchField();
  }

  /**
   * Counts the total number of items.
   *
   * The returned value counts all FolderShare entities of any kind,
   * including folders, files, images, and media.
   *
   * System hidden and disabled items may be included in the count.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the user for whome the count is returned. A value of
   *   FolderShareInterface::ANY_USER_ID returns a total for all users.
   *
   * @return int
   *   Returns the total number of items.
   *
   * @section examples Example usage
   * Get the total number of items of any kind:
   * @code
   *   $count = FolderShare::countNumberOfItems();
   * @endcode
   *
   * @see ::findAllIds()
   * @see ::countNumberOfBytes()
   * @see ::countNumberOfFiles()
   * @see ::countNumberOfFolders()
   * @see ::hasFiles()
   * @see ::hasFolders()
   */
  public static function countNumberOfItems(
    int $ownerUid = self::ANY_USER_ID) {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');

    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts the total number of file, image, and media items.
   *
   * When an optional user ID is given, the returned total only includes
   * items owned by that user. When the user ID is not provided or is
   * FolderShareInterface::ANY_USER_ID, the returned total is for all users.
   *
   * The returned total counts all FolderShare file, image, and media kinds.
   * Folders are not included.
   *
   * System hidden and disabled items may be included in the count.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the user for whome the count is returned. A value of
   *   FolderShareInterface::ANY_USER_ID returns a total for all users.
   *
   * @return int
   *   Returns the total number of file, image, and media items.
   *
   * @section examples Example usage
   * Get the total number of file, image, or media items;
   * @code
   *   $count = FolderShare::countNumberOfFiles();
   * @endcode
   *
   * @see ::findAllIds()
   * @see ::countNumberOfBytes()
   * @see ::countNumberOfFolders()
   * @see ::hasFiles()
   * @see ::hasFolders()
   */
  public static function countNumberOfFiles(
    int $ownerUid = self::ANY_USER_ID) {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');

    $group = $query->orConditionGroup()
      ->condition('kind', self::FILE_KIND, '=')
      ->condition('kind', self::IMAGE_KIND, '=')
      ->condition('kind', self::MEDIA_KIND, '=');

    $query = $query->condition($group);

    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts the total number of folders managed by the module.
   *
   * When an optional user ID is given, the returned total only includes
   * items owned by that user. When the user ID is not provided or is
   * FolderShareInterface::ANY_USER_ID, the returned total is for all users.
   *
   * The returned total counts all FolderShare folders. Files, images,
   * and media items are not included.
   *
   * System hidden and disabled items may be included in the count.
   *
   * @param int $ownerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   for the user for whome the count is returned. A value of
   *   FolderShareInterface::ANY_USER_ID returns a total for all users.
   *
   * @return int
   *   Returns the total number of folders.
   *
   * @section examples Example usage
   * Get the total number of folders:
   * @code
   *   $count = FolderShare::countNumberOfFolders();
   * @endcode
   *
   * @see ::findAllIds()
   * @see ::countNumberOfBytes()
   * @see ::countNumberOfFiles()
   * @see ::hasFiles()
   * @see ::hasFolders()
   */
  public static function countNumberOfFolders(
    int $ownerUid = self::ANY_USER_ID) {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs')
      ->condition('kind', self::FOLDER_KIND, '=');

    if ($ownerUid >= 0) {
      $query->condition('uid', $ownerUid, '=');
    }

    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Returns TRUE if there are any files, images, or media.
   *
   * System hidden and disabled items may be included.
   *
   * @return bool
   *   Returns TRUE if there are any files, and FALSE otherwise.
   *
   * @see ::countNumberOfBytes()
   * @see ::countNumberOfFiles()
   * @see ::countNumberOfFolders()
   * @see ::hasFolders()
   */
  public static function hasFiles() {
    return (self::countNumberOfFiles() !== 0);
  }

  /**
   * Returns TRUE if there are any folders.
   *
   * System hidden and disabled items may be included.
   *
   * @return bool
   *   Returns TRUE if there are any folders, and FALSE otherwise.
   *
   * @see ::countNumberOfBytes()
   * @see ::countNumberOfFiles()
   * @see ::countNumberOfFolders()
   * @see ::hasFiles()
   */
  public static function hasFolders() {
    return (self::countNumberOfFolders() !== 0);
  }

  /*---------------------------------------------------------------------
   *
   * Find FolderShare entity by wrapped file or media.
   *
   *---------------------------------------------------------------------*/

  /**
   * Finds the FolderShare entity ID wrapping the given File entity.
   *
   * The database is queried to find a file or image FolderShare entity
   * that uses an entity reference to the given file. The ID of the entity
   * is returned, or (1) if no entity is found.
   *
   * A system hidden or disabled item may be returned.
   *
   * @param \Drupal\file\FileInterface $file
   *   The File entity used to search for a FolderShare entity that wraps it.
   *
   * @return bool|int
   *   Returns the FolderShare entity ID of the file or image item that
   *   wraps the given File entity, or a FALSE if not found. If $file is NULL,
   *   a FALSE is returned.
   */
  public static function findFileWrapperId(FileInterface $file) {
    if ($file === NULL) {
      return FALSE;
    }

    // Search the database for use of the file's ID in the target ID
    // of the 'file' or 'image' fields.
    $id = $file->id();

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $group = $query->orConditionGroup()
      ->condition('file__target_id', $id, '=')
      ->condition('image__target_id', $id, '=');
    $query = $query->condition($group);
    $results = $query->execute()
      ->fetchAll();

    if (empty($results) === TRUE) {
      return FALSE;
    }

    // There should be at most one entry.
    return (int) $results[0]->id;
  }

  /**
   * Finds the FolderShare entity ID wrapping the given Media entity.
   *
   * The database is queried to find a media FolderShare entity that uses an
   * entity reference to the given media entity. The ID of the entity
   * is returned, or (1) if no entity is found.
   *
   * A system hidden or disabled item may be returned.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The Media entity used to search for a FolderShare entity that wraps it.
   *
   * @return int
   *   Returns the FolderShare entity ID of the media item that
   *   wraps the given File entity, or a FALSE if not found. If $media is NULL,
   *   a FALSE is returned.
   */
  public static function findMediaWrapperId(MediaInterface $media) {
    if ($media === NULL) {
      return FALSE;
    }

    // Search the database for use of the media's ID in the target ID
    // of the 'media' field.
    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->condition('media__target_id', $media->id(), '=');
    $results = $query->execute()
      ->fetchAll();

    if (empty($results) === TRUE) {
      return FALSE;
    }

    // There should be at most one entry.
    return (int) $results[0]->id;
  }

  /*---------------------------------------------------------------------
   *
   * Find FolderShare entity by name.
   *
   *---------------------------------------------------------------------*/

  /**
   * Finds the ID for a named child within a parent folder.
   *
   * The $parentId argument selects the parent folder to search. If the
   * ID is not valid, the search will not find a match and FALSE is returned.
   *
   * A $name argument indicates the name to child name to search for. If the
   * name is empty, the search will not find a match and FALSE is returned.
   *
   * Since each child of a folder must have a unique name within the folder,
   * this search through a folder's children can return at most one matching
   * child. That single entity ID is returned, or FALSE if no matching
   * child was found.
   *
   * A system hidden or disabled item may be returned.
   *
   * @param int $parentId
   *   The parent folder ID.
   * @param string $name
   *   The name of an item to find.
   *
   * @return bool|int
   *   Returns the entity ID if the item with the given name and parent
   *   folder, or a FALSE if not found.
   */
  public static function findNamedChildId(int $parentId, string $name) {
    if ($parentId < 0 || empty($name) === TRUE) {
      return FALSE;
    }

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->condition('parentid', $parentId, '=');
    $query->condition('name', $name, '=');
    $results = $query->execute()
      ->fetchAll();

    if (empty($results) === TRUE) {
      return FALSE;
    }

    // There should be at most one entry.
    return (int) $results[0]->id;
  }

  /*---------------------------------------------------------------------
   *
   * Find chilren.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function findChildrenIds() {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->condition('parentid', $this->id(), '=');

    $ids = [];
    foreach ($query->execute() as $result) {
      $ids[] = (int) $result->id;
    }

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function findChildrenNames() {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->addField('fs', 'name', 'name');
    $query->condition('parentid', $this->id(), '=');

    // Process names into an array where keys are names and values are IDs.
    $names = [];
    foreach ($query->execute() as $result) {
      $names[$result->name] = (int) $result->id;
    }

    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public function findChildren() {
    return self::loadMultiple($this->findChildrenIds());
  }

  /*---------------------------------------------------------------------
   *
   * Find children that are files.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function findFileChildrenIds() {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->condition('parentid', $this->id(), '=');
    $group = $query->orConditionGroup()
      ->condition('kind', self::FILE_KIND, '=')
      ->condition('kind', self::IMAGE_KIND, '=')
      ->condition('kind', self::MEDIA_KIND, '=');
    $query = $query->condition($group);

    $ids = [];
    foreach ($query->execute() as $result) {
      $ids[] = (int) $result->id;
    }

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function findFileChildrenNumberOfBytes() {

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->condition('fs.parentid', $this->id(), '=');
    $query->condition('fs.systemhidden', TRUE, '!=');
    $group = $query->orConditionGroup()
      ->condition('fs.kind', self::FILE_KIND, '=')
      ->condition('fs.kind', self::IMAGE_KIND, '=')
      ->condition('fs.kind', self::MEDIA_KIND, '=');
    $query = $query->condition($group);
    $query->addExpression('SUM(fs.size)', 'size');

    return (int) $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function findFileChildren() {
    return self::loadMultiple($this->findFileChildrenIds());
  }

  /*---------------------------------------------------------------------
   *
   * Find children that are folders.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function findFolderChildrenIds() {

    // Query all folders that list this folder as a parent.
    $results = \Drupal::entityQuery(self::ENTITY_TYPE_ID)
      ->condition('kind', self::FOLDER_KIND, '=')
      ->condition('parentid', $this->id(), '=')
      ->execute();

    $ids = [];
    foreach ($results as $result) {
      $ids[] = (int) $result;
    }

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function findFolderChildren() {
    return self::loadMultiple($this->findFolderChildrenIds());
  }

  /*---------------------------------------------------------------------
   *
   * Ancestors.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function findAncestorFolderIds() {

    // If this is a root item, it has no parent and thus no ancestors.
    $parentId = $this->getParentFolderId();
    if ($parentId === self::USER_ROOT_LIST) {
      return [];
    }

    $ancestorIds = [$parentId];
    $connection = Database::getConnection();

    // Repeatedly query to get each ancestor, saving their IDs.
    while (TRUE) {
      $query = $connection->select(self::BASE_TABLE, 'fs');
      $query->addField('fs', 'parentid', 'parentid');
      $query->condition('id', $parentId, '=');
      $results = $query->execute()->fetchAll();

      if (empty($results) === TRUE) {
        break;
      }

      $parentId = (int) $results[0]->parentid;
      $ancestorIds[] = $parentId;
    }

    // Reverse so that the list is root first.
    return array_reverse($ancestorIds);
  }

  /**
   * {@inheritdoc}
   */
  public function findAncestorFolderNames() {
    // If this is a root item, it has no parent and thus no ancestors.
    $parentId = $this->getParentFolderId();
    if ($parentId === self::USER_ROOT_LIST) {
      return [];
    }

    $connection = Database::getConnection();
    $ancestorNames = [];

    // Repeatedly query to get each ancestor, saving their IDs.
    while ($parentId !== self::USER_ROOT_LIST) {
      $query = $connection->select(self::BASE_TABLE, 'fs');
      $query->addField('fs', 'parentid', 'parentid');
      $query->addField('fs', 'name', 'name');
      $query->condition('id', $parentId, '=');
      $results = $query->execute()->fetchAll();

      if (empty($results) === TRUE) {
        break;
      }

      $parentId = (int) $results[0]->parentid;
      $ancestorNames[] = $results[0]->name;
    }

    // Reverse so that the list is root first.
    return array_reverse($ancestorNames);
  }

  /**
   * {@inheritdoc}
   */
  public function findAncestorFolders() {
    return self::loadMultiple($this->findAncestorFolderIds());
  }

  /**
   * {@inheritdoc}
   */
  public function isAncestorOfFolderId(int $folderId) {
    if ($folderId < 0) {
      return FALSE;
    }

    $folder = self::load($folderId);
    if ($folder === NULL) {
      return FALSE;
    }

    // This folder is an ancestor of the given folder if that folder's
    // list of ancestor folder IDs contains this folder's ID.
    return in_array((int) $this->id(), $folder->findAncestorFolderIds());
  }

  /*---------------------------------------------------------------------
   *
   * Descendants.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function findDescendantIds(
    int $ownerUid = self::ANY_USER_ID,
    bool $match = TRUE) {

    // If this is not a folder, there are no descendants.
    if ($this->isFolder() === FALSE) {
      return [];
    }

    $connection = Database::getConnection();

    // When this is a root, do a query for all items with this item as
    // their root ID. No looping through children is required.
    if ($this->isRootItem() === TRUE) {
      $query = $connection->select(self::BASE_TABLE, 'fs');
      $query->addField('fs', 'id', 'id');
      $query->condition('rootid', $this->id(), '=');

      if ($ownerUid >= 0) {
        $op = ($match === TRUE) ? '=' : '!=';
        $query->condition('uid', $ownerUid, $op);
      }

      $descendants = [];
      foreach ($query->execute() as $result) {
        $descendants[] = (int) $result->id;
      }

      return $descendants;
    }

    // Otherwise when this is not a root, loop doing queries to get children,
    // and their children, and so on. Initially, all children are on a pending
    // list. During the loop, pop from the list, add to the descendants list,
    // and query to add more children to the pending list. When the pending
    // list is finally exhausted, all descendants have been found.
    $descendants = $this->findFileChildrenIds();
    $pending = $this->findFolderChildrenIds();

    while (empty($pending) === FALSE) {
      // Get the next pending ID.
      $id = array_shift($pending);

      // Add it to the descendant list.
      $descendants[] = $id;

      // Get any non-folder children of the ID. Add them directly to the
      // descendant list since they cannot have further children.
      $query = $connection->select(self::BASE_TABLE, 'fs');
      $query->addField('fs', 'id', 'id');
      $query->condition('parentid', $id, '=');
      $query->condition('kind', self::FOLDER_KIND, '!=');

      foreach ($query->execute() as $result) {
        $descendants[] = (int) $result->id;
      }

      // Get any folder children of the ID. Add them to the pending list
      // since they may have children that need to be retreived by a
      // further query.
      $query = $connection->select(self::BASE_TABLE, 'fs');
      $query->addField('fs', 'id', 'id');
      $query->condition('parentid', $id, '=');
      $query->condition('kind', self::FOLDER_KIND, '=');

      foreach ($query->execute() as $result) {
        $pending[] = (int) $result->id;
      }
    }

    return $descendants;
  }

  /**
   * {@inheritdoc}
   */
  public function isDescendantOfFolderId(int $proposedAncestorId) {
    // There are two ways to implement this:
    //
    // - Get a list of all descendant IDs of the proposed ancestor,
    //   then see if this entity's ID is in that list.
    //
    // - Get a list of all ancestor IDs of this entity, then see if
    //   the proposed ancestor is one of those.
    //
    // The second implementation looks up the folder tree and is much
    // faster than the first implementation, which looks down through
    // the folder tree.
    return in_array($proposedAncestorId, $this->findAncestorFolderIds());
  }

  /*---------------------------------------------------------------------
   *
   * Find kinds.
   *
   *---------------------------------------------------------------------*/

  /**
   * Finds the kind for each of the given entity IDs.
   *
   * The returned associative array has one key for each FolderShare
   * entity kind present in the ID list. The associated value is an array
   * of integer entity IDs for items of that kind from the ID list.
   * Invalid IDs are silently ignored and will not be included in the
   * returned array.
   *
   * @param int[] $ids
   *   An array of integer FolderShare entity IDs for entities to look up.
   *
   * @return array
   *   Returns an associative array with FolderShare kinds as keys, and
   *   an array of IDs for each kind. If a kind is not present in the
   *   array, then no entity IDs of that kind were found.
   */
  public static function findKindsForIds(array $ids) {
    if (empty($ids) === TRUE) {
      return [];
    }

    $connection = Database::getConnection();
    $query = $connection->select(self::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->addField('fs', 'kind', 'kind');
    $query->condition('id', $ids, 'IN');

    $ordered = [];
    foreach ($query->execute() as $result) {
      $ordered[$result->kind][] = (int) $result->id;
    }

    return $ordered;
  }

}
