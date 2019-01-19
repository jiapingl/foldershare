<?php

namespace Drupal\foldershare;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;

/**
 * Manages a hierarchy of folders, subfolders, and files.
 *
 * Implementations of this class support operations on folders and files
 * within folders. Operations include create, delete, move, copy, rename,
 * and changes to specific fields, such as names, descriptions, dates,
 * and owners.
 *
 * Object instances represent top-level root folders, subfolders, and specific
 * entries in the folders, such as files.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\Folder
 */
interface FolderShareInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /*---------------------------------------------------------------------
   *
   * Constants - Special entity IDs.
   *
   *---------------------------------------------------------------------*/

  /**
   * Indicates that any user ID matches, when used as a method argument.
   *
   * @var int
   */
  const ANY_USER_ID = (-1000);

  /**
   * Indicates that the current user ID matches, when used as a method argument.
   *
   * @var int
   */
  const CURRENT_USER_ID = (-1001);

  /**
   * Indicates that any item ID matches, when used as a method argument.
   *
   * @var int
   */
  const ANY_ITEM_ID = (-2000);

  /**
   * Indicates the user's own root list (a.k.a. personal root list).
   *
   * @var int
   */
  const USER_ROOT_LIST = (-100);

  /**
   * Indicates the public root list.
   *
   * @var int
   */
  const PUBLIC_ROOT_LIST = (-101);

  /**
   * Indicates the any-user ("all") root list for admins.
   *
   * @var int
   */
  const ALL_ROOT_LIST = (-102);

  /*---------------------------------------------------------------------
   *
   * Constants - Kinds.
   *
   *---------------------------------------------------------------------*/

  /**
   * The kind for a folder.
   *
   * @var string
   */
  const FOLDER_KIND = 'folder';

  /**
   * The kind for a file.
   *
   * The file is a managed File entity referenced by the FolderShare entity
   * via a 'file' field type.
   *
   * @var string
   */
  const FILE_KIND = 'file';

  /**
   * The kind for an image.
   *
   * The file is a managed File entity referenced by the FolderShare entity
   * via a 'image' field type.
   *
   * @var string
   */
  const IMAGE_KIND = 'image';

  /**
   * The kind for a media holder.
   *
   * The media is a Media entity referenced by the FolderShare entity
   * via an 'entity_reference' field type.
   *
   * @var string
   */
  const MEDIA_KIND = 'media';

  /*---------------------------------------------------------------------
   *
   * Constants - MIME types
   *
   *---------------------------------------------------------------------*/

  /**
   * The custom MIME type for folders.
   *
   * There is no official Internet standard for a MIME type for folders,
   * so we are forced to create one. This is primarily used for selecting
   * an icon in the user interface, and this module's styling provides
   * that icon.
   *
   * @var string
   */
  const FOLDER_MIME = 'folder/directory';

  /*---------------------------------------------------------------------
   *
   * Constants - Path schemes.
   *
   *---------------------------------------------------------------------*/

  /**
   * The personal scheme for the user's own files, or those shared with them.
   *
   * The scheme is used in virtual file and folder paths of the form:
   * - SCHEME://UID/PATH...
   *
   * @var string
   */
  const PERSONAL_SCHEME = "personal";

  /**
   * The public scheme for the site's publically accessible files.
   *
   * The scheme is used in virtual file and folder paths of the form:
   * - SCHEME://UID/PATH...
   *
   * The public scheme refers to content owned by another user and shared
   * with the anonymous user, which makes it publically accessible.
   *
   * @var string
   */
  const PUBLIC_SCHEME = "public";

  /*---------------------------------------------------------------------
   *
   * Constants - Parameters.
   *
   *---------------------------------------------------------------------*/

  /**
   * The maximum number of UTF-8 characters in a file or folder name.
   *
   * The File module imposes an internal limit of 255 characters for
   * file names. FolderShare uses this same limit for consistency.
   *
   * This limit is in *characters*, not bytes. The File and FolderShare
   * modules support UTF-8 names which include multi-byte characters.
   * So, a 255 character name may be use more than 255 bytes.
   *
   * All name handling must be multi-byte character safe. This often
   * means the use of the 'mb_' functions in PHP, such as 'mb_strlen()'.
   *
   * @var int
   */
  const MAX_NAME_LENGTH = 255;

  /*---------------------------------------------------------------------
   *
   * Kind field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item's kind.
   *
   * An item's kind characterizes the functionality the item supports:
   * - 'folder': A group of child files and folders.
   * - 'file': An arbitrary data file.
   * - 'image': An image file.
   * - 'media': A media entity, such as a URL to an external video.
   *
   * @return string
   *   Returns the kind of item.
   *
   * @section examples Example usage
   * Loop through all children of this item and group them by kind:
   * @code
   *   $groups = [
   *     'folder' => [],
   *     'file'   => [],
   *     'image'  => [],
   *     'media'  => [],
   *   ];
   *   foreach ($item->findChildren() as $child) {
   *     $groups[$child->getKind()][] = $child;
   *   }
   * @endcode
   *
   * @see ::isFile()
   * @see ::isFolder()
   * @see ::isImage()
   * @see ::isMedia()
   * @see ::getMimeType()
   */
  public function getKind();

  /**
   * Returns TRUE if this item is a file, and FALSE otherwise.
   *
   * @return bool
   *   Returns TRUE if this item is a file.
   */
  public function isFile();

  /**
   * Returns TRUE if this item is a folder, and FALSE otherwise.
   *
   * @return bool
   *   Returns TRUE if this item is a folder, but not a root folder.
   */
  public function isFolder();

  /**
   * Returns TRUE if this item is an image, and FALSE otherwise.
   *
   * @return bool
   *   Returns TRUE if this item is an image.
   */
  public function isImage();

  /**
   * Returns TRUE if this item is a media, and FALSE otherwise.
   *
   * @return bool
   *   Returns TRUE if this item is a media.
   */
  public function isMedia();

  /*---------------------------------------------------------------------
   *
   * MIME type field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item's MIME type.
   *
   * If this item is a file or image, the returned MIME type is expected to
   * be that of the underlying stored file or image. If this item is a folder,
   * the FOLDER_MIME string is usually returned.
   *
   * @return string
   *   Returns the MIME type.
   *
   * @see \Drupal\file\FileInterface::getMimeType()
   * @see ::isMimeTypeImage()
   * @see ::FOLDER_MIME
   */
  public function getMimeType();

  /*---------------------------------------------------------------------
   *
   * File, Image, and Media fields.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item's file ID, if any.
   *
   * For file items, this method returns the File entity ID for the
   * underlying stored file. For all other item kinds, a FALSE is returned.
   *
   * While image items also have a File entity ID, file and image items
   * store their File entity IDs separately. Use getImageId() to get an
   * image item's File entity ID.
   *
   * @return bool|int
   *   Returns the entity ID of the underlying File entity for file items,
   *   or FALSE if this item is not for a file.
   *
   * @section examples Example usage
   * Get the File entity ID for file or image items:
   * @code
   *   $fileId = FALSE;
   *   if ($item->isFile() === TRUE) {
   *     $fileId = $item->getFileId();
   *   }
   *   elseif ($item->isImage() === TRUE) {
   *     $fileId = $item->getImageId();
   *   }
   * @endcode
   *
   * @see ::getFile()
   * @see ::getKind()
   * @see ::isFile()
   */
  public function getFileId();

  /**
   * Returns the item's File entity, if any.
   *
   * For file items, this method returns the File entity for the
   * underlying stored file. For all other item kinds, a NULL is returned.
   *
   * While image items also have a File entity, file and image items
   * store their File entities separately. Use getImage() to get an
   * image item's File entity.
   *
   * @return \Drupal\file\FileInterface
   *   Returns the File entity for the underlying file for file items,
   *   or a NULL if this item is not for a file.
   *
   * @section examples Example usage
   * Get the File entity for file or image items:
   * @code
   *   $file = NULL;
   *   if ($item->isFile() === TRUE) {
   *     $file = $item->getFile();
   *   }
   *   elseif ($item->isImage() === TRUE) {
   *     $file = $item->getImage();
   *   }
   * @endcode
   *
   * @see ::getFileId()
   * @see ::getKind()
   * @see ::isFile()
   */
  public function getFile();

  /**
   * Returns the item's image ID, if any.
   *
   * For image items, this method returns the File entity ID for the
   * underlying stored file. For all other item kinds, a FALSE is returned.
   *
   * While file items also have a File entity ID, file and image items
   * store their File entity IDs separately. Use getFileId() to get a
   * file item's File entity ID.
   *
   * @return bool|int
   *   Returns the entity ID of the underlying File entity for image items,
   *   or FALSE if this item is not for an image file.
   *
   * @section examples Example usage
   * Get the File entity ID for file or image items:
   * @code
   *   $fileId = FALSE;
   *   if ($item->isFile() === TRUE) {
   *     $fileId = $item->getFileId();
   *   }
   *   elseif ($item->isImage() === TRUE) {
   *     $fileId = $item->getImageId();
   *   }
   * @endcode
   *
   * @see ::getImage()
   * @see ::getKind()
   * @see ::isImage()
   */
  public function getImageId();

  /**
   * Returns the item's image File entity, if any.
   *
   * For image items, this method returns the File entity for the
   * underlying stored file. For all other item kinds, a NULL is returned.
   *
   * While file items also have a File entity, file and image items
   * store their File entities separately. Use getFile() to get a
   * file item's File entity.
   *
   * @return \Drupal\file\FileInterface
   *   Returns the File entity for the underlying image file for image items,
   *   or a NULL if this item is not for an image.
   *
   * @section examples Example usage
   * Get the File entity for file or image items:
   * @code
   *   $file = NULL;
   *   if ($item->isFile() === TRUE) {
   *     $file = $item->getFile();
   *   }
   *   elseif ($item->isImage() === TRUE) {
   *     $file = $item->getImage();
   *   }
   * @endcode
   *
   * @see ::getImageId()
   * @see ::getKind()
   * @see ::isImage()
   */
  public function getImage();

  /**
   * Returns the item's media ID, if any.
   *
   * For media items, this method returns the Media entity ID for the
   * underlying media object. For all other item kinds, a FALSE is returned.
   *
   * @return int
   *   Returns the entity ID of the underlying Media entity for media items,
   *   or FALSE if this item is not for media.
   *
   * @see ::getMedia()
   * @see ::getKind()
   * @see ::isMedia()
   */
  public function getMediaId();

  /**
   * Returns the item's Media entity, if any.
   *
   * For media items, this method returns the Media entity for the
   * media object. For all other item kinds, a NULL is returned.
   *
   * @return \Drupal\media\MediaInterface
   *   Returns the Media entity for the underlying media object for media
   *   items, or a NULL if this item is not for media.
   *
   * @see ::getMediaId()
   * @see ::getKind()
   * @see ::isMedia()
   */
  public function getMedia();

  /*---------------------------------------------------------------------
   *
   * Name field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item's name.
   *
   * This function is equivalent to Entity::label(), but faster since it
   * does not require entity type introspection to find the field containing
   * the name.
   *
   * @return string
   *   Returns the name of the item.
   *
   * @see \Drupal\Core\Entity\label()
   * @see ::rename()
   */
  public function getName();

  /**
   * Returns TRUE if a proposed name is unique among this item's children.
   *
   * The $name argument specifies a proposed name for an existing or new
   * child of this item. The name is not validated and is presumed to be
   * of legal length and structure.
   *
   * The optional $inUseId indicates the ID of an existing child of this
   * item that is already using the name. If the value is not given, negative,
   * or FolderShareInterface::ANY_ITEM_ID, then it is presumed that no
   * current child has the proposed name.
   *
   * This function looks through the names of its children and returns TRUE
   * if the proposed name is not in use by any child, except the indicated
   * child, if any. If the name is in use by a child that is not $inUseId,
   * then FALSE is returned.
   *
   * @param string $name
   *   A proposed name.
   * @param int $inUseId
   *   (optional, default = FolderShareInterface::ANY_ITEM_ID) The ID of an
   *   existing FolderShare item child that is already using the proposed name.
   *
   * @return bool
   *   Returns TRUE if the name is unique among the item's children, and
   *   FALSE otherwise.
   *
   * @see ::findChildrenNames()
   * @see ::getName()
   * @see ::isNameLegal()
   * @see ::isRootNameUnique()
   * @see ::createUniqueName()
   */
  public function isNameUnique(string $name, int $inUseId = self::ANY_ITEM_ID);

  /*---------------------------------------------------------------------
   *
   * File name extensions.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item's filename extension.
   *
   * If the item is a folder, an empty string is returned.
   *
   * The extension is always converted to lower case.
   *
   * @return string
   *   The file name extension (the portion of the name after the last ".").
   */
  public function getExtension();

  /*---------------------------------------------------------------------
   *
   * Size field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item's storage size, in bytes.
   *
   * For file, image, and media items, this function returns the size of
   * the underlying entity. For File and Image items, this is the size of
   * the locally stored file.
   *
   * For folders, this returns the sum of the sizes for all descendants.
   * The field is initialized to zero when a folder is created, and updated
   * each time a descendant is added, deleted, or moved. For some operations,
   * the size is temporarily cleared while the sum of descendant sizes is
   * recalculated. During this time, this function will return FALSE.
   *
   * @return bool|int
   *   Returns the size in bytes, or FALSE if the size is currently unknown.
   *
   * @see ::getKind()
   * @see \Drupal\file\FileInterface::getSize()
   */
  public function getSize();

  /*---------------------------------------------------------------------
   *
   * Created date/time field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the creation timestamp.
   *
   * @return int
   *   Returnsthe creation time stamp for this item.
   *
   * @see ::setCreatedTime()
   */
  public function getCreatedTime();

  /**
   * Sets the creation timestamp.
   *
   * The timestamp is not validated and is presumed to be valid.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param int $timestamp
   *   The creation time stamp for this item. The value is not validated
   *   and is assumed to be a valid timestamp.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getCreatedTime()
   */
  public function setCreatedTime($timestamp);

  /*---------------------------------------------------------------------
   *
   * Owner field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the item is owned by the indicated user.
   *
   * @param int $uid
   *   The user ID to check.
   *
   * @return bool
   *   Returns TRUE if the item is owned by the indicated user, and FALSE
   *   otherwise.
   *
   * @see ::getOwnerId()
   */
  public function isOwnedBy(int $uid);

  /**
   * Sets the owner of this item.
   *
   * If this item is a file, the underlying file's ownership is also changed.
   *
   * If this item is a folder, the ownership of the folder's children
   * is not affected.
   *
   * This item is automatically saved after changes are made.
   *
   * System hidden and disabled items are also affected.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account of the new owner of the folder. If the value is NULL,
   *   the current user's account is used.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns this item.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see \Drupal\user\EntityOwnerInterface::getOwner()
   * @see \Drupal\user\EntityOwnerInterface::getOwnerId()
   */
  public function setOwner(UserInterface $account = NULL);

  /**
   * Sets the owner ID of this item.
   *
   * If this item is a file, the underlying file's ownership is also changed.
   *
   * If this item is a folder, the ownership of the folder's children
   * is not affected.
   *
   * The user ID is not validated and is presumed to be a valid ID for a
   * User entity.
   *
   * This item is automatically saved after changes are made.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int $ownerUid
   *   The user ID of the new owner of the folder. The ID is not validated
   *   and is presumed to be for a valid User entity.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns this item.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity. See changeOwnerId().
   *
   * @see ::changeOwnerId()
   * @see \Drupal\user\EntityOwnerInterface::getOwner()
   * @see \Drupal\user\EntityOwnerInterface::getOwnerId()
   */
  public function setOwnerId($ownerUid);

  /*---------------------------------------------------------------------
   *
   * Grant fields.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns user IDs for users granted author access to this root item.
   *
   * The returned unordered array has one entry for each user ID currently
   * granted author (create, update, delete) access to this root item.
   * The owner of the root item is always included in this list.
   *
   * If this is not a root item, an empty array is returned.
   *
   * @return int[]
   *   Returns an unordered array of user IDs for users with author access to
   *   this root item. If this is not a root item, an empty array is returned.
   *
   * @see ::getAccessGrantViewUserIds()
   * @see ::getAccessGrants()
   * @see ::isAccessGranted()
   */
  public function getAccessGrantAuthorUserIds();

  /**
   * Returns user IDs for users granted view access to this root item.
   *
   * The returned unordered array has one entry for each user ID currently
   * granted view access to this root item. The owner of the root item is
   * always included in this list.
   *
   * If this is not a root item, an empty array is returned.
   *
   * @return int[]
   *   Returns an unordered array of user IDs for users with view access to
   *   this root item. If this is not a root item, an empty array is returned.
   *
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrants()
   * @see ::isAccessGranted()
   */
  public function getAccessGrantViewUserIds();

  /**
   * Returns all user IDs and access to this root item.
   *
   * The returned unordered associative array has user IDs as keys. For each
   * user, the array value is an array that contains either one or two string
   * values:
   * - ['view'] = user only has view access.
   * - ['author'] = user only has author access (which is odd).
   * - ['author', 'view'] = user has view and author access.
   *
   * The owner of the root folder is always included with both view and
   * author access.
   *
   * If this is not a root item, an empty array is returned.
   *
   * The returned array is in the same format as that used by
   * share().
   *
   * @return array
   *   Returns an unordered associative array with user IDs as keys and
   *   arrays as values. Array values contain strings indicating 'view' or
   *   'author' access. If this is not a root item, an empty array is
   *   returned.
   *
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   * @see ::isAccessGranted()
   * @see ::share()
   */
  public function getAccessGrants();

  /**
   * Returns TRUE if access is explicitly granted to a user on this root item.
   *
   * The $uid argument indicates the user to look up to check if they have been
   * explicitly granted access to the root item and its descendants.
   *
   * The $access argument selects whether 'view' or 'author' access is
   * checked. For any other value, FALSE is returned.
   *
   * If this is not a root item, FALSE is returned. Use getRootItem() to
   * get the root item ancestor of this item, and then query it for access.
   *
   * If a root item grants access to the anonymous user (uid = 0), then
   * this method returns TRUE for all user IDs for 'author' and 'view'.
   *
   * A system hidden or disabled item may be queried for its access grants.
   * However the module's access control will deny access anyway if the user
   * is not an administrator.
   *
   * @param int $uid
   *   The user ID of a user granted access.
   * @param string $access
   *   The access to test. One of 'author' or 'view'.
   *
   * @return bool
   *   Returns TRUE if the access is granted, and FALSE otherwise. If this
   *   is not a root item, FALSE is returned.
   *
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   * @see ::getAccessGrants()
   * @see ::share()
   */
  public function isAccessGranted(int $uid, string $access);

  /**
   * Returns TRUE if no-one but the owner has access to this root item.
   *
   * If the root item's owner is the only user ID listed in the item's
   * access grants, then the root item and its descendants are private
   * and this method returns TRUE. Otherwise it returns FALSE.
   *
   * If this is not a root item, FALSE is returned.
   *
   * A system hidden or disabled item may be queried for its access grants.
   * However the module's access control will deny access anyway if the user
   * is not an administrator.
   *
   * @return bool
   *   Returns TRUE if the owner is the only user granted access, and
   *   FALSE otherwise.
   *
   * @see ::isAccessGranted()
   * @see ::isAccessPublic()
   * @see ::isAccessShared()
   */
  public function isAccessPrivate();

  /**
   * Returns TRUE if access is granted to anonymous on this root item.
   *
   * If the root item's 'view' or 'author' grants include the anonymous
   * user (UID = 0), then the root item and its descendants are public and
   * this method returns TRUE. Otherwise it returns FALSE.
   *
   * If this is not a root item, FALSE is returned.
   *
   * A system hidden or disabled item may be queried for its access grants.
   * However the module's access control will deny access anyway if the user
   * is not an administrator.
   *
   * @return bool
   *   Returns TRUE if access is granted to the anonymous user, and
   *   FALSE otherwise.
   *
   * @see ::isAccessGranted()
   * @see ::isAccessPrivate()
   * @see ::isAccessShared()
   */
  public function isAccessPublic();

  /**
   * Returns TRUE if access is granted to a non-owner of this root item.
   *
   * If the root item grants 'view' or 'author' access to anyone other
   * than the item's owner, the item is being shared and this method returns
   * TRUE. Otherwise it returns FALSE.
   *
   * If this is not a root item, FALSE is returned.
   *
   * A system hidden or disabled item may be queried for its access grants.
   * However the module's access control will deny access anyway if the user
   * is not an administrator.
   *
   * @return bool
   *   Returns TRUE if access is granted to anyone beyond the owner, and
   *   FALSE otherwise.
   *
   * @see ::isAccessGranted()
   * @see ::isAccessPrivate()
   * @see ::isAccessPublic()
   */
  public function isAccessShared();

  /**
   * Returns TRUE if the item is shared by the indicated user.
   *
   * An item is shared by a user if:
   * - It is owned by that user.
   * - It has view or author access grants for anybody else.
   *
   * @param int $uid
   *   The user ID of the user to check.
   *
   * @return bool
   *   Returns TRUE if the item is shared by the indicated user, and
   *   FALSE otherwise.
   *
   * @see ::isAccessGranted()
   * @see ::isOwnedBy()
   * @see ::isSharedWith()
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   */
  public function isSharedBy(int $uid);

  /**
   * Returns TRUE if the item is shared with the indicated user.
   *
   * An item is shared with a user if:
   * - It is NOT owned by that user.
   * - It has the indicated access grants for that user.
   *
   * @param int $uid
   *   The user ID of the user to check.
   * @param string $access
   *   The access to test. One of 'author' or 'view'.
   *
   * @return bool
   *   Returns TRUE if the item is shared by the indicated user, and
   *   FALSE otherwise.
   *
   * @see ::isAccessGranted()
   * @see ::isOwnedBy()
   * @see ::isSharedBy()
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   */
  public function isSharedWith(int $uid, string $access);

  /*---------------------------------------------------------------------
   *
   * Sharing status.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a simplified statement about the entity's sharing status.
   *
   * Returned values include:
   * - "personal" = the item is not shared and it is owned by the
   *   current user.
   *
   * - "private" = the item is not shared and it is not owned by the
   *   current user.
   *
   * - "shared by you" = the item is shared and it is owned by the
   *   current user.
   *
   * - "shared with you" = the item is shared with the current user and it
   *   is not owned by the current user.
   *
   * - "public" = the item is owned by Anonymous or is shared with Anonymous.
   *
   * System hidden and disabled items are always "private".
   *
   * @return string
   *   Returns one of the above sharing status values.
   */
  public function getSharingStatus();

  /*---------------------------------------------------------------------
   *
   * SystemDisabled field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the item is disabled by the system.
   *
   * Items are temporarily disabled during long-running operations, such as
   * copies and moves. Disabled items are normally visible, but non-functional
   * for users.
   *
   * @return bool
   *   Returns TRUE if the item is disabled, and FALSE otherwise.
   */
  public function isSystemDisabled();

  /*---------------------------------------------------------------------
   *
   * SystemHidden field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the item is hidden by the system.
   *
   * Items are hidden during long-running destructive operations, such as
   * deletion. Hidden items are normally not visible to users.
   *
   * @return bool
   *   Returns TRUE if the item is hidden, and FALSE otherwise.
   */
  public function isSystemHidden();

  /*---------------------------------------------------------------------
   *
   * Description field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the item description.
   *
   * @return string
   *   Returns the description string for the item, or an empty string if
   *   there is no description.
   *
   * @see ::setDescription()
   */
  public function getDescription();

  /**
   * Sets the item description.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param string $text
   *   The description string for the item, or an empty string if there is no
   *   description. The description is not validated or filtered.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::getDescription()
   */
  public function setDescription(string $text);

  /*---------------------------------------------------------------------
   *
   * Child items.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a list of children IDs of this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return int[]
   *   Returns an unordered array of integer entity IDs for children
   *   of this item, or an empty array if there are no children.
   *
   * @section examples Example usage
   * Loop through all children of this item:
   * @code
   *   foreach ($item->findChildrenIds() as $id) { ... }
   * @endcode
   *
   * @see ::findFileChildrenIds()
   * @see ::findFolderChildrenIds()
   * @see ::findChildrenNames()
   */
  public function findChildrenIds();

  /**
   * Returns a list of children of this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return array
   *   Returns a list of entities for children of this item, or an empty
   *   array if there are no children.
   *
   * @section examples Example usage
   * Loop through all children of this item:
   * @code
   *   foreach ($item->findChildren() as $child) { ... }
   * @endcode
   *
   * @see ::findFileChildren()
   * @see ::findFolderChildren()
   */
  public function findChildren();

  /**
   * Returns a list of names for children of this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return array
   *   Returns an unordered associative array where keys are item names and
   *   values are entity IDs.
   *
   * @section examples Example usage
   * Loop through all children names of this item:
   * @code
   *   foreach ($item->findChildrenNames() as $name) { ... }
   * @endcode
   *
   * @see ::findChildren()
   * @see ::findChildrenIds()
   * @see ::findFileChildren()
   * @see ::findFileChildrenIds()
   * @see ::findFolderChildren()
   * @see ::findFolderChildrenIds()
   */
  public function findChildrenNames();

  /**
   * Returns a list of file, image, and media IDs for children of this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return int[]
   *   Returns an unordered array of integer entity IDs for file, image,
   *   or media children of this item, or an empty array if there are no
   *   matching children.
   *
   * @section examples Example usage
   * Loop through all file, image, or media children of this item:
   * @code
   *   foreach ($item->findFileChildrenIds() as $id) { ... }
   * @endcode
   *
   * @see ::findChildrenIds()
   * @see ::findFileChildren()
   * @see ::findChildrenNames()
   * @see ::getFileId()
   */
  public function findFileChildrenIds();

  /**
   * Returns the total size, in bytes, of all file children.
   *
   * System hidden and disabled children are ignored.
   *
   * @return int
   *   Returns the size, in bytes, of the sum of the sizes of all children
   *   that are not folders (i.e. they are files, images, or media).
   *
   * @see ::findChildrenIds()
   * @see ::findFileChildren()
   * @see ::findChildrenNames()
   * @see ::getFileId()
   */
  public function findFileChildrenNumberOfBytes();

  /**
   * Returns a list of entities for files, images, or media in this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return array
   *   Returns an unordered array of entities for file, image,
   *   or media children of this item, or an empty array if there are no
   *   matching children.
   *
   * @section examples Example usage
   * Loop through all file, image, or media children of this item:
   * @code
   *   foreach ($item->findFileChildren() as $child) { ... }
   * @endcode
   *
   * @see ::findChildren()
   * @see ::findFileChildrenIds()
   * @see ::findChildrenNames()
   * @see ::getFile()
   * @see ::getImage()
   * @see ::getMedia()
   */
  public function findFileChildren();

  /**
   * Returns a list of folder IDs for children of this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return array
   *   Returns an unordered array of integer entity IDs for folder
   *   children of this item, or an empty array if there are no folder
   *   children.
   *
   * @section examples Example usage
   * Loop through all folder children of this item:
   * @code
   *   foreach ($item->findFolderChildrenIds() as $id) { ... }
   * @endcode
   *
   * @see ::findChildrenIds()
   * @see ::findFolderChildren()
   * @see ::findChildrenNames()
   */
  public function findFolderChildrenIds();

  /**
   * Returns a list of entities for folder children of this item.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return array
   *   Returns an unordered array of entities for folder children of
   *   this item, or an empty array if there are no folder children.
   *
   * @section examples Example usage
   * Loop through all folder children of this item:
   * @code
   *   foreach ($item->findFolderChildren() as $child) { ... }
   * @endcode
   *
   * @see ::findFolderChildrenIds()
   * @see ::findChildrenNames()
   * @see ::findChildren()
   */
  public function findFolderChildren();

  /*---------------------------------------------------------------------
   *
   * Parent field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the parent folder, or NULL if there is no parent.
   *
   * Since only folders can have children, only folders can be parents,
   * and the returned entity is always a folder.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the parent entity, or NULL if this item is a root item and
   *   therefore has no parent.
   *
   * @section examples Example usage
   * Build a list of ancestors by successively getting the parent, then
   * the parent's parent, and so forth:
   * @code
   *   $ancestors = [];
   *   $parent = $item->getParentFolder();
   *   while ($parent !== NULL) {
   *     $ancestors[] = $parent;
   *     $parent = $parent->getParentFolder();
   *   }
   * @endcode
   *
   * @see ::getParentFolderId()
   * @see ::getRootItem()
   * @see ::getRootItemId()
   * @see ::isFolder()
   * @see ::isRootItem()
   */
  public function getParentFolder();

  /**
   * Returns the parent folder ID, or a flag if there is no parent.
   *
   * If the item has no parent, it is a root list item and
   * FolderShareInterface::USER_ROOT_LIST is returned.
   *
   * Since only folders can have children, only folders can be parents,
   * and the returned entity ID is always for a folder.
   *
   * A system hidden or disabled item may be returned.
   *
   * @return int
   *   Returns the parent ID, or FolderShareInterface::USER_ROOT_LIST if this
   *   item is a root item and therefore has no parent.
   *
   * @see ::getParentFolder()
   * @see ::getRootItem()
   * @see ::getRootItemId()
   * @see ::isFolder()
   * @see ::isRootItem()
   */
  public function getParentFolderId();

  /*---------------------------------------------------------------------
   *
   * Ancestors.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a list of ancestor folder IDs.
   *
   * The returned list is ordered with this item's root first, then each
   * ancestor leading down to this item. This item's ID is not included.
   * If this item is a root, then the returned array is empty.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @return int[]
   *   Returns an ordered list of entity IDs as array values, starting
   *   with this item's root.
   *
   * @see ::findAncestorFolders()
   * @see ::findAncestorFolderNames()
   * @see ::findDescendantIds()
   * @see ::isAncestorOfFolderId()
   * @see ::isDescendantOfFolderId()
   */
  public function findAncestorFolderIds();

  /**
   * Returns a list of ancestor folder names.
   *
   * The returned list is ordered with this item's root first, then each
   * ancestor leading down to this item. This item's name is not included.
   * If this item is a root, then the returned array is empty.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @return string[]
   *   Returns an ordered list of entity IDs as array values, starting
   *   with this item's root.
   *
   * @section examples Example usage
   * Build a path of ancestor names for this item:
   * @code
   *   $path = '/' . implode('/', $item->findAncestorFolderNames());
   * @endcode
   *
   * @see ::findAncestorFolders()
   * @see ::findAncestorFolderIds()
   */
  public function findAncestorFolderNames();

  /**
   * Returns a list of ancestor folders.
   *
   * The returned list is ordered with this item's root first, then each
   * ancestor leading down to this item. This item is not included.
   * If this item is a root, then the returned array is empty.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns an ordered list of entities as array values, starting
   *   with this item's root.
   *
   * @see ::findAncestorFolderIds()
   * @see ::findAncestorFolderNames()
   * @see ::findDescendantIds()
   * @see ::isAncestorOfFolderId()
   * @see ::isDescendantOfFolderId()
   */
  public function findAncestorFolders();

  /**
   * Returns TRUE if this item is an ancestor of the given item.
   *
   * System hidden and disabled items are considered in this test.
   *
   * @param int $folderId
   *   The ID of the item to check to see if this item is one of its ancestors.
   *
   * @return bool
   *   Returns TRUE if this item is an ancestor of the given item,
   *   and FALSE otherwise. If the given entity ID is invalid, FALSE
   *   is returned.
   *
   * @see ::findAncestorFolderIds()
   * @see ::findDescendantIds()
   * @see ::isDescendantOfFolderId()
   */
  public function isAncestorOfFolderId(int $folderId);

  /*---------------------------------------------------------------------
   *
   * Descendants.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a list of descendant item IDs.
   *
   * The returned list includes the entity IDs of children, their children,
   * and so on for all descendants of this item.
   *
   * The $ownerUid and $match arguments work together to constrain this list.
   * By default, all descendants for any owner are returned. If $ownerUid is
   * set and $match is TRUE, only those descendants with $ownerUid as the
   * owner are returned. If $ownerUid is set and $match is FALSE, only those
   * descendants that are NOT owned by $ownerUid are returned.
   *
   * System hidden and disabled items may be included in the list.
   *
   * @param int $onwerUid
   *   (optional, default = FolderShareInterface::ANY_USER_ID) The user ID
   *   to use when looking up descendants. If the ID is not given or
   *   FolderShareInterface::ANY_USER_ID, then all descendants are returned,
   *   regardless of their owner.
   * @param bool $match
   *   (optional, default = TRUE = include matching owner IDs) When TRUE,
   *   all entities with an owner ID that matches the given $ownerUid are
   *   returned. When FALSE all entities that DO NOT match the given
   *   $ownerUid are returned.
   *
   * @return int[]
   *   Returns an unordered list of descendant entity IDs.
   *
   * @section examples Example usage
   * Find all descendants of this item, all descendants owned by the current
   * user, and all descendants not owned by the current user:
   * @code
   *   $allIds = $this->findDescendantIds();
   *   $allIdsOwnedBy = $this->FindDescendantIds(\Drupal::currentUser()->id());
   *   $allIdsNotOwnedBy = $this->FindDescendantIds(\Drupal::currentUser()->id(), FALSE);
   * @endcode
   *
   * @see ::findAncestorFolderIds()
   * @see ::findDescendantIds()
   * @see ::isAncestorOfFolderId()
   * @see ::isDescendantOfFolderId()
   */
  public function findDescendantIds(
    int $onwerUid = self::ANY_USER_ID,
    bool $match = TRUE);

  /**
   * Returns TRUE if this item is a descendant of the indicated entity.
   *
   * System hidden and disabled items are considered in this test.
   *
   * @param int $proposedAncestorId
   *   The entity ID of a proposed ancestor of this item.
   *
   * @return bool
   *   TRUE if this item is a descendant of the proposed ancestor, and
   *   FALSE otherwise. If the proposed ancestor's ID is invalid,
   *   FALSE is returned.
   *
   * @see ::findAncestorFolderIds()
   * @see ::findDescendantIds()
   * @see ::isAncestorOfFolderId()
   */
  public function isDescendantOfFolderId(int $proposedAncestorId);

  /*---------------------------------------------------------------------
   *
   * Root folder field.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns this item's root item.
   *
   * If this item is a root item, it returns itself.
   *
   * Applications may check if this item is a root item by any of the
   * following, in fastest to slowest order:
   *
   * @code
   *   $isRoot = ($this->getParentFolderId() == FALSE);
   *   $isRoot = $this->isRootItem();
   *   $isRoot = ($this->id() == $this->getRootItemId());
   *   $isRoot = ($this == $this->getRootItem());
   * @endcode
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the root item, or this item if the item is a root item.
   *
   * @see ::getParentFolder()
   * @see ::getParentFolderId()
   * @see ::getRootItemId()
   * @see ::isRootItem()
   */
  public function getRootItem();

  /**
   * Returns this item's root ID.
   *
   * If this item is a root item, it returns its own ID.
   *
   * Applications may check if this item is a root item by any
   * of the following, in fastest to slowest order:
   *
   * @code
   *   $isRoot = ($this->getParentFolderId() == FolderShareInterface::USER_ROOT_LIST);
   *   $isRoot = $this->isRootItem();
   *   $isRoot = ($this->id() == $this->getRootItemId());
   *   $isRoot = ($this == $this->getRootItem());
   * @endcode
   *
   * @return int
   *   Returns the root ID, or this item's ID if this item is a root item.
   *
   * @see ::getParentFolder()
   * @see ::getParentFolderId()
   * @see ::getRootItem()
   * @see ::isRootItem()
   */
  public function getRootItemId();

  /**
   * Returns TRUE if this item is a root item, and FALSE otherwise.
   *
   * Applications may check if this item is a root item by any
   * of the following, in fastest to slowest order:
   *
   * @code
   *   $isRoot = ($this->getParentFolderId() == FolderShareInterface::USER_ROOT_LIST);
   *   $isRoot = $this->isRootItem();
   *   $isRoot = ($this->id() == $this->getRootItemId());
   *   $isRoot = ($this == $this->getRootItem());
   * @endcode
   *
   * @return bool
   *   Returns TRUE if this item is a root item.
   *
   * @see ::getParentFolder()
   * @see ::getParentFolderId()
   * @see ::getRootItem()
   * @see ::getRootItemId()
   * @see ::isParentFolder()
   */
  public function isRootItem();

  /*---------------------------------------------------------------------
   *
   * Paths.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds a string path to the item.
   *
   * The returned path is a slash (/) followed by a series of slash-separated
   * ancestor names, ending with the item's own name.
   *
   * @return string
   *   Returns a string path to the item.
   */
  public function getPath();

  /*---------------------------------------------------------------------
   *
   * Folder operations.
   *
   *---------------------------------------------------------------------*/

  /**
   * Changes the owner of this item, and optionally all descendants.
   *
   * The owner user ID of this item is changed to the indicated user.
   * If $changeDescendants is TRUE, the owner user ID of all of this item's
   * descendants is changed as well. All items are saved.
   *
   * The user ID is not validated. It is presumed to be a valid entity ID
   * for a User entity. It should not be negative.
   *
   * If this is a root item, then the item's name is checked for a collision
   * with another root item with the same name in the new owner's root list.
   * If there is a collision, an exception is thrown and no change is made.
   *
   * System hidden and disabled items are also affected.
   *
   * @param int $uid
   *   The owner user ID for the new owner of the folder tree. The ID
   *   is not validated and is presumed to be that of a User entity.
   * @param bool $changeDescendants
   *   (optional, default = FALSE) When FALSE, only this item's ownership
   *   is changed. When TRUE, the ownership of all of this item's descendants
   *   is updated as well.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock could not be acquired on this item,
   *   or any descendants.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if this is a root item and it's name is
   *   already in use in the root list of the new user.
   *
   * @section examples Example usage
   * Change the owner ID for a folder and all of its descendants:
   * @code
   *   $folder->changeOwnerId($uid, TRUE);
   * @endcode
   *
   * @section queue Queued change ownership
   * When $changeDescendants is TRUE, this method tries to change the
   * ownership of all descendants immediately. If there are a lot of
   * descendants to change, this may take too long for the site's PHP or
   * web server timeouts and the change will be interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are changed.
   *
   * @section locking Process locks
   * This method locks items as they are updated. If this item is a root item,
   * the new user's root list is locked while it is checked for a name
   * collision with this item. If a lock on this item or the root list cannot
   * be acquired, an exception is thrown and no further work is done.
   *
   * If $changeDescendants is TRUE, this method also processes each of this
   * item's descendants. The order of processing is not defined. If a
   * descendant is locked, changing its ownership will be delayed until the
   * lock is available.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_change_owner" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::setOwnerId()
   * @see \Drupal\user\EntityOwnerInterface::getOwner()
   * @see \Drupal\user\EntityOwnerInterface::getOwnerId()
   */
  public function changeOwnerId(int $uid, bool $changeDescendants = FALSE);

  /**
   * Creates a new folder with the given name as a child of this folder.
   *
   * If the name is empty, it is set to a default.
   *
   * The name is checked for uniqueness within the parent folder.
   * If needed, a sequence number is appended before the extension(s) to
   * make it unique (e.g. 'My folder 12').
   *
   * @param string $name
   *   (optional, default = '') The name for the new folder. If the name is
   *   empty, it is set to a default name.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, the entity will be automatically
   *   renamed, if needed, to insure that it is unique within the folder.
   *   When FALSE, non-unique names cause an exception to be thrown.
   *
   * @return \Drupal\foldershare\Entity\FolderShareInterface
   *   Returns the new folder. The folder has been saved.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if the name is already in use.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entity has been created, but
   *   before it has been saved.
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_insert" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_new_folder" hook.
   *
   * @section locking Process locks
   * The parent folder is locked for exclusive editing access by this
   * function for the duration of the operation.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   */
  public function createFolder(string $name = '', bool $allowRename = TRUE);

  /**
   * Copies this item into the user's root list.
   *
   * A copy of this item is created and added to the current user's root list.
   * The copy is owned by the current user and given default access grants
   * that give the user, and only the user, access.
   *
   * If this item is a folder, copying recurses through all descendants to
   * replicate the folder tree. Copied descendants retain the same names
   * and field values as the originals, and organized in the same tree
   * structure.
   *
   * File, image, and media items are copied along with copies of their
   * underlying File and Media entities.
   *
   * Copying marks each new folder as disabled until all children have been
   * copied into the folder. UIs may show disabled items specially and prevent
   * operations until the item is enabled.
   *
   * The copy will fail if a lock cannot be obtained on the original and
   * the root list. The copy will fail and be incomplete if any of the
   * children cannot be locked as they are copied. Everything that can be
   * copied will be.
   *
   * System hidden and disabled items are also affected.
   *
   * @param string $newName
   *   (optional, default = '' = no name change) When empty, the copy will
   *   have the same name as the original. When a new name is not empty,
   *   the given name will be used as the name for the copy.
   *
   * @return \Drupal\foldershare\Entity\FolderShareInterface
   *   Returns the new root item.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If $newName is not empty, an exception is thrown if the name is not
   *   legal or if this is a file or image and the name's filename extension
   *   is not allowed for the site. Whether or not $newName is empty, an
   *   exception is thrown if the name is in use already in the user's
   *   root list.
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurs, such as a
   *   file system becomes unreadable/unwritable, gets full, or gores offline.
   *
   * @section queue Queued copy
   * This method tries to perform the full copy immediately. If there are a
   * lot of descendants to copy, this may take too long for the site's PHP or
   * web server timeouts and the copy will be interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are copied.
   *
   * @section hooks Hooks
   * This method creates one or more new file and folder entities. Multiple
   * hooks are invoked as a side effect. See ::createFolder() and ::addFile().
   *
   * Hooks are called for the entity immediately, and for descendants as
   * they are processed. If portions of the task are deferred, the hooks
   * will be called when the task is executed by CRON.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section locking Process locks
   * The root list and this item are locked as the item is copied. Thereafter,
   * recursion locks each child item to copy and its new parent as each copy
   * is done.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   * @see ::addFile()
   * @see ::copyToFolder()
   * @see ::createFolder()
   */
  public function copyToRoot(string $newName = '');

  /**
   * Copies this item into a selected folder.
   *
   * A copy of this item is created and added to the given folder.
   * The copy is owned by the current user.
   *
   * If this item is a folder, copying recurses through all descendants to
   * replicate the folder tree. Copied descendants retain the same names
   * and field values as the originals, and organized in the same tree
   * structure.
   *
   * File, image, and media items are copied along with copies of their
   * underlying File and Media entities.
   *
   * Copying marks each new folder as disabled until all children have been
   * copied into the folder. UIs may show disabled items specially and prevent
   * operations until the item is enabled.
   *
   * The copy will fail if a lock cannot be obtained on the original and
   * the new parent. The copy will fail and be incomplete if any of the
   * children cannot be locked as they are copied. Everything that can be
   * copied will be.
   *
   * System hidden and disabled items are also affected.
   *
   * @param \Drupal\foldershare\FolderShareInterface $parent
   *   (optional, default = NULL = copy to the root list) The parent folder
   *   for the copy. When NULL, the copy is added to the root list.
   * @param string $newName
   *   (optional, default = '' = no name change) When empty, the copy will
   *   have the same name as the original. When a new name is not empty,
   *   the given name will be used as the name for the copy.
   *
   * @return \Drupal\foldershare\Entity\FolderShareInterface
   *   Returns the new item.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If $newName is not empty, an exception is thrown if the name is not
   *   legal or if this is a file or image and the name's filename extension
   *   is not allowed for the site. Whether or not $newName is empty, an
   *   exception is thrown if the name is in use already in the new parent.
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurs, such as a
   *   file system becomes unreadable/unwritable, gets full, or gores offline.
   *
   * @section queue Queued copy
   * This method tries to perform the full copy immediately. If there are a
   * lot of descendants to copy, this may take too long for the site's PHP or
   * web server timeouts and the copy will be interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are copied.
   *
   * @section hooks Hooks
   * This method creates one or more new file and folder entities. Multiple
   * hooks are invoked as a side effect. See ::createFolder() and ::addFile().
   *
   * Hooks are called for the entity immediately, and for descendants as
   * they are processed. If portions of the task are deferred, the hooks
   * will be called when the task is executed by CRON.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section locking Process locks
   * This item and the new parent are locked as the item is copied. This
   * repeats for each item copied, recursing through all children of this item.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   *
   * @see ::addFile()
   * @see ::copyToRoot()
   * @see ::createFolder()
   */
  public function copyToFolder(
    FolderShareInterface $parent = NULL,
    string $newName = '');

  /**
   * Deletes this item and all of its descendants.
   *
   * Implements \Drupal\Core\Entity\EntityInterface::delete.
   * Overrides \Drupal\Core\Entity\Entity::delete.
   *
   * If this item is a file, image, or media item, the underlying File or
   * Media entity is deleted along with the item.
   *
   * If this item is a folder, a recursive traversal of the folder's children
   * deletes them first, followed by deletion of the folder itself.
   *
   * This item, and each of its descendants, are marked as hidden while
   * they are being deleted. This removes them from the UI.
   *
   * System hidden and disabled items are also affected.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use,
   *   or if one or more descendants cannot be locked.
   *
   * @section queue Queued delete
   * This method tries to delete this item and all descendants immediately.
   * If there are a lot of descendants to delete, this may take too long for
   * the site's PHP or web server timeouts and the delete will be interrupted.
   *
   * In order to guarantee that the task will complete, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are deleted.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_delete" hook.
   *
   * @section locking Process locks
   * This method attempts to lock this item, and the parent folder, for
   * exclusive access before it is deleted. If this item cannot be locked,
   * a LockException is thrown and the item is not deleted.
   *
   * For folders, this method attempts to lock each descendant before it is
   * deleted. If one or more items cannot be locked, everything else is
   * deleted, a task to delete the locked items is queued, and a LockException
   * is thrown.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::isSystemHidden()
   */
  public function delete();

  /**
   * Duplicates this item or folder tree into this folder's parent.
   *
   * If this folder is a root folder, then the new copy of this folder
   * is also a root folder.
   *
   * If one or more child files or folders cannot be copied, the
   * rest are copied and an exception is thrown.
   *
   * The copied files and folders are owned by the current user.
   *
   * System hidden and disabled items are also affected.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   If an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws an exception if a unique name for the duplicate could not be
   *   created.
   *
   * @section hooks Hooks
   * This method creates one or more new file and folder entities. Multiple
   * hooks are invoked as a side effect. See ::createFolder() and ::addFile().
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_copy" hook for
   * each item copied.
   *
   * @section locking Process locks
   * This folder, the parent folder, and all subfolders are locked
   * for exclusive editing access by this function for the duration
   * of the copy.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each item copied.
   *
   * @see ::addFile()
   * @see ::copyToRoot()
   * @see ::copyToFolder()
   * @see ::createFolder()
   */
  public function duplicate();

  /**
   * Moves this item into the user's root list.
   *
   * This item's parent and root IDs are updated to move it into the root list.
   * The item is then given default access grants that give the user, and
   * only the user, access.
   *
   * Once the item has been moved to the root list, the operation recurses
   * through all descendants to update their root IDs.
   *
   * The move will fail if a lock cannot be obtained on the item and the
   * root list.
   *
   * System hidden and disabled items are also affected.
   *
   * @param string $newName
   *   (optional, default = '' = don't rename) The proposed new name for
   *   entity once it is moved into the root folder list. If the name is
   *   empty, the folder is not renamed.
   *
   * @return \Drupal\foldershare\Entity\FolderShareInterface
   *   Returns $this.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If $newName is not empty, an exception is thrown if the name is not
   *   legal or if this is a file or image and the name's filename extension
   *   is not allowed for the site. Whether or not $newName is empty, an
   *   exception is thrown if the name is in use already in the user's
   *   root list.
   *
   * @section queue Queued move
   * This method tries to perform the full move immediately. If there are a
   * lot of descendants to move, this may take too long for the site's PHP or
   * web server timeouts and the move will be interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are moved. During this delay, descendants may have the wrong root ID
   * and incorrect access grants.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_update" after the entity has been saved.
   *
   * Hooks are called for the entity immediately, and for descendants as
   * they are processed. If portions of the task are deferred, the hooks
   * will be called when the task is executed by CRON.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_move" hook.
   *
   * @section locking Process locks
   * The root list and this item are locked briefly as they are changed.
   * After the item is moved, its descendants are updated to use the item
   * as their root. This descendant change does not use locks.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::moveToFolder()
   */
  public function moveToRoot(string $newName = '');

  /**
   * Moves this item to a new destination folder.
   *
   * The moved files and folders retain their original ownership.
   *
   * An exception is thrown if the destination contains a file or
   * folder with the same name as this folder, or the proposed new name,
   * or if the destination is a descendant of this folder.
   *
   * System hidden and disabled items are also affected.
   *
   * @param \Drupal\foldershare\FolderShareInterface $dstParent
   *   (optional, default = NULL = move to root) The destination parent
   *   for this folder.
   * @param string $newName
   *   (optional, default = '' = don't rename) The proposed new name for
   *   entity once it is moved into the root folder list. If the name is
   *   empty, the folder is not renamed.
   *
   * @return \Drupal\foldershare\Entity\FolderShareInterface
   *   Returns $this.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock could not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If $newName is not empty, an exception is thrown if the name is not
   *   legal or if this is a file or image and the name's filename extension
   *   is not allowed for the site. Whether or not $newName is empty, an
   *   exception is thrown if the name is in use already in the destination
   *   folder.
   *
   * @section queue Queued move
   * This method tries to perform the full move immediately. If there are a
   * lot of descendants to move, this may take too long for the site's PHP or
   * web server timeouts and the move will be interrupted.
   *
   * In order to guarantee that the task is completed, this method enqueues
   * a background task serviced later by CRON. Depending upon the site's
   * scheduling of CRON, this will cause a delay before the remaining items
   * are moved. During this delay, descendants may have the wrong root ID
   * and incorrect access grants.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_update" after the entity has been saved.
   *
   * Hooks are called for the entity immediately, and for descendants as
   * they are processed. If portions of the task are deferred, the hooks
   * will be called when the task is executed by CRON.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_move" hook.
   *
   * @section locking Process locks
   * The destination folder and this item are locked briefly as they are
   * changed.  After the item is moved, its descendants are updated to use
   * the item as their root. This descendant change does not use locks.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method.
   *
   * @see ::moveToRoot()
   */
  public function moveToFolder(
    FolderShareInterface $dstParent = NULL,
    string $newName = '');

  /**
   * Renames this item to use the given name.
   *
   * If this item is a file, the underlying file's name is also changed.
   * File names are subject to file name extension restrictions, if any.
   *
   * System hidden and disabled items are also affected.
   *
   * @param string $newName
   *   The new name for this folder.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   If an access lock on the item, or its parent folder, could not
   *   be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If the item could not be renamed because the name is
   *   invalid or already in use in the parent folder or root list.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_update" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_rename" hook.
   *
   * @section locking Process locks
   * This item is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::setName()
   */
  public function rename(string $newName);

  /**
   * Shares this root item by setting user IDs and access grants.
   *
   * The given associative array must have user IDs as keys. For each user,
   * the array value must be an array of strings with one or two values:
   * - ['view'] = user only has view access.
   * - ['author'] = user only has author access (which is odd).
   * - ['author', 'view'] = user has view and author access.
   *
   * The given array is used to completely reset all access grants for
   * this root item. Any prior grants are removed.
   *
   * The owner of the root item is automaticaly included with both view and
   * author access, whether or not they are included in the given array.
   *
   * The given array is in the same format as that returned by
   * getAccessGrants().
   *
   * If this is not a root item, this call has no effect.
   *
   * System hidden and disabled entities may be changed, however the module's
   * access control will deny access for any user that is not an administrator.
   *
   * The caller must call save() for the change to take effect.
   *
   * @param array $grants
   *   An unordered associative array with user IDs as keys and
   *   arrays as values. Array values contain strings indicating 'view' or
   *   'author' access.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use.
   *
   * @section locking Process locks
   * This item is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_share" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::getAccessGrantAuthorUserIds()
   * @see ::getAccessGrantViewUserIds()
   * @see ::isAccessGranted()
   */
  public function share(array $grants);

  /**
   * Unshares this root item for the indicated user and access.
   *
   * Access grants are adjusted to remove the indicated user for shared access.
   * The $access argument may be 'view' or 'author', or left empty to unshare
   * for both.
   *
   * @param int $uid
   *   The user ID for the user to remove from the item's access grants.
   * @param string $access
   *   (optional, default = '' = view and author) The access grant to remove.
   *   This is either 'view' or 'author', or an empty string to remove both.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if this item cannot be locked for exclusive use.
   *
   * @section locking Process locks
   * This item is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_share" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::share()
   */
  public function unshare(int $uid, string $access);

  /*---------------------------------------------------------------------
   *
   * File operations.
   *
   *---------------------------------------------------------------------*/

  /**
   * Adds a file to this folder.
   *
   * If $allowRename is FALSE, an exception is thrown if the file's
   * name is not unique within the folder. But if $allowRename is
   * TRUE and the name is not unique, the file's name is adjusted
   * to include a sequence number immediately before the first "."
   * in the name, or at the end of the name if there is no "."
   * (e.g. "myfile.png" becomes "myfile 1.png").
   *
   * An exception is thrown if the file is already in a folder.
   *
   * System hidden and disabled items are also affected.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to be added to this folder.
   * @param bool $allowRename
   *   (optional) When TRUE, the file's name should be automatically renamed to
   *   insure it is unique within the folder. When FALSE, non-unique
   *   file names cause an exception to be thrown.  Defaults to FALSE.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the new FolderShare entity that wraps the given File entity.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   If an access lock on the folder could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If the file could not be added because the file is already
   *   in a folder, or if the file doesn't pass validation because
   *   the name is invalid or already in use (if $allowRename FALSE).
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entity has been created, but
   *   before it has been saved.
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_insert" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFiles()
   */
  public function addFile(FileInterface $file, bool $allowRename = FALSE);

  /**
   * Adds files to this folder.
   *
   * If $allowRename is FALSE, an exception is thrown if the file's
   * name is not unique within the folder. But if $allowRename is
   * TRUE and the name is not unique, the file's name is adjusted
   * to include a sequence number immediately before the first "."
   * in the name, or at the end of the name if there is no "."
   * (e.g. "myfile.png" becomes "myfile 1.png").
   *
   * All files are checked and renamed before any files are added
   * to the folder. If any file is invalid, an exception is thrown
   * and no files are added.
   *
   * An exception is thrown if any file is already in a folder.
   *
   * System hidden and disabled items are also affected.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   An array of files to be added to this folder.  NULL files
   *   are silently skipped.
   * @param bool $allowRename
   *   (optional) When TRUE, each file's name should be automatically renamed
   *   to insure it is unique within the folder. When FALSE, non-unique
   *   file names cause an exception to be thrown.  Defaults to FALSE.
   *
   * @return \Drupal\foldershare\FolderShareInterface[]
   *   Returns an array of new FolderShare entities that wrap the given
   *   File entities.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   If an access lock on the folder could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If the file could not be added because the file is already
   *   in a folder, or if the file doesn't pass validation because
   *   the name is invalid or already in use (if $allowRename FALSE).
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entities have been created, but
   *   before they have been saved.
   * - "hook_foldershare_presave" before the entities are saved.
   * - "hook_foldershare_insert" after the entities have been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFile()
   */
  public function addFiles(array $files, bool $allowRename = FALSE);

  /**
   * Adds uploaded files for the named form field into this folder.
   *
   * When a file is uploaded via an HTTP form post from a browser, PHP
   * automatically saves the data into "upload" files saved in a
   * PHP-managed temporary directory. This method sweeps those uploaded
   * files, pulls out the ones associated with the named form field,
   * and adds them to this folder with their original names.
   *
   * If there are no uploaded files, this method returns immediately.
   *
   * Files may be automatically renamed, if needed, to insure they have
   * unique names within the folder.
   *
   * System hidden and disabled items are also affected.
   *
   * @param string $formFieldName
   *   The name of the form field with associated uploaded files pending.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, if a file's name collides with the
   *   name of an existing entity, the name is modified by adding a number on
   *   the end so that it doesn't collide. When FALSE, a file name collision
   *   throws an exception.
   *
   * @return array
   *   The returned array contains one entry per uploaded file.
   *   An entry may be a File object uploaded into the current folder,
   *   or a string containing an error message about that file and
   *   indicating that it could not be uploaded and added to the folder.
   *   An empty array is returned if there were no files to upload
   *   and add to the folder.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entities have been created, but
   *   before they have been saved.
   * - "hook_foldershare_presave" before the entities are saved.
   * - "hook_foldershare_insert" after the entities have been saved.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @see ::addFile()
   * @see ::addFiles()
   *
   * @todo The error messages returned here should be removed in favor of
   * error codes and arguments so that the caller can know which file
   * had which error and do something about it or report their own
   * error message. Returning text messages here is not flexible.
   */
  public function addUploadFiles(
    string $formFieldName,
    bool $allowRename = TRUE);

  /**
   * Adds a PHP input stream file into this folder.
   *
   * When a file is uploaded via an HTTP post handled by a web services
   * "REST" resource, the file's data is available via the PHP input
   * stream. This method reads that stream, creates a file, and adds
   * that file to this folder with the given name.
   *
   * System hidden and disabled items are also affected.
   *
   * @param string $filename
   *   The name for the new file.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, if $filename collides with the
   *   name of an existing entity, the name is modified by adding a number on
   *   the end so that it doesn't collide. When FALSE, a file name collision
   *   throws an exception.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the newly added FolderShare entity wrapping the file.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entity has been created, but
   *   before it has been saved.
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_insert" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFile()
   * @see ::addFiles()
   * @see ::addUploadedFiles()
   */
  public function addInputFile(string $filename, bool $allowRename = TRUE);

  /*---------------------------------------------------------------------
   *
   * Archive operations.
   *
   *---------------------------------------------------------------------*/

  /**
   * Archives the given items to a new archive added to this folder.
   *
   * A new ZIP archive is created in this folder and all of the given,
   * items, and their children, recursively, are added to the archive.
   *
   * All items must be children of this item.
   *
   * @param \Drupal\foldershare\FoldershareInterface[] $items
   *   An array of children of this folder that are to be included in a
   *   new archive added as a new child of this folder.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the FolderShare entity for the new archive.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Thrown if this item is not a folder or root folder, or if any of
   *   the children in the array are not children of this folder.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock on this folder could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if the archive file could not be created, such as if the
   *   temporary directory does not exist or is not writable, if a temporary
   *   file for the archive could not be created, or if any of the file
   *   children of this item could not be read and saved into the archive.
   *
   * @section locking Process locks
   * Each child file or folder and all of their subfolders and files are
   * locked for exclusive editing access by this function while they are
   * being added to a new archive. This folder is locked while the new
   * archive is added.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   */
  public function archiveToFolder(array $items);

  /**
   * Extracts all contents of this archive and adds them to the parent folder.
   *
   * This item must be a ZIP file. All of the contents of the ZIP file are
   * extracted and added as new files and folders into this file's parent
   * folder.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock on this folder could not be acquired.
   *   This exception is never thrown if $lock is FALSE.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Thrown if the archive is not a valid archive or it has become
   *   corrpted.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if the archive file could not be accessed or there was a
   *   problem creating any of the new files and folders from the archive.
   *
   * @section locking Process locks
   * This file and the parent folder are locked as items are added. If new
   * subfolders are created, those are locked too while items are added
   * to them.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook
   * for each added file from the archive.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message for each added file from the archive.
   */
  public function unarchiveFromZip();

}
