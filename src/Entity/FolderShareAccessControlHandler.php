<?php

namespace Drupal\foldershare\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\FolderShareInterface;

/**
 * Provides access control for operations on files and folders.
 *
 * Access to a folder and its files is controlled by three mechanisms:
 *
 * - Permission-based access control.
 * - Root item-based access control.
 * - Module settings to enable/disable sharing for the entire site.
 *
 * @section permission Permission-based access control
 * The module uses the standard Drupal role-based permissions mechanism and
 * these module-specific permissions:
 *
 * - "view foldershare" enables users to view and download content.
 *
 * - "author foldershare" enables users to create, delete, and modify content.
 *
 * - "share foldershare" enables users to share root items with other users.
 *
 * - "share public foldershare" enables users to share root items with the
 *   public (anonymous users).
 *
 * - "administer foldershare" enables users to modify anyone's content,
 *   change content ownership, or modify content share settings.
 *
 * Additionally, users designated as site administrators always have
 * full access to all content regardless of permissions.
 *
 * @section root Root item-based access control
 * Root items have access control lists that designate specific users
 * that may view and author content. These access control lists act as a
 * boolean AND with permission access controls so that a user must have
 * both the site-wide generic view permission AND a root item's view
 * access grant in order to view content.
 *
 * Content owners are always included in the access control lists of their
 * own content and therefore always have full view and author access.
 *
 * The generic "anonymous" user may be granted access as well. This
 * publishes content for public access.
 *
 * Access grants are always on the root item of a folder tree, and they
 * apply to all content within that folder tree.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\FolderShare
 */
class FolderShareAccessControlHandler extends EntityAccessControlHandler {

  /*---------------------------------------------------------------------
   *
   * Field view and edit access.
   *
   *---------------------------------------------------------------------*/

  /**
   * Fields that should never be viewed.
   *
   * These are internal fields that don't have a meaningful presentation
   * and should NEVER be shown on a page.
   *
   * Note that we *do not* exclude the module's internal parentid and
   * rootid fields. While these are not something users should normally
   * view directly, there is nothing wrong with doing so.
   */
  const FIELDS_VIEW_NEVER = [
    'langcode',
    'uuid',
    'systemhidden',
    'systemdisabled',
  ];

  /**
   * Fields that should never be edited directly.
   *
   * These are internal fields with values set programmatically. They
   * should NEVER be edited directly by a user. There are, however,
   * entity API calls that can set these while maintaining the integrity
   * of the data model.
   *
   * The 'id' and 'uuid' are assigned by Drupal and need to remain unchanged
   * for the life of the entity.
   *
   * The 'uid' is the owner of the content and should not be edited
   * directly.
   *
   * The 'parentid and 'rootid are internal fields used to connect folders
   * into a folder hierarchy. They are only changed when content is moved
   * from place to place in the hierarchy.
   *
   * The 'size' is computed automatically and should never be edited.
   *
   * The 'kind' field indicates whether the entity is a file or folder
   * and should never be edited.
   *
   * The 'file' field gives the entity ID of an underlying File object,
   * if any, and obviously should never be edited.
   *
   * The 'grantauthoruids' and 'grantviewuids' store the user IDs of users
   * granted specific access to view or author content in a root folder.
   * None of these should be edited.
   */
  const FIELDS_EDIT_NEVER = [
    'id',
    'uid',
    'uuid',
    'created',
    'changed',
    'langcode',
    'parentid',
    'rootid',
    'size',
    'kind',
    'mime',
    'file',
    'image',
    'media',
    'grantauthoruids',
    'grantviewuids',
    'systemhidden',
    'systemdisabled',
  ];

  /*---------------------------------------------------------------------
   *
   * Construct.
   *
   *---------------------------------------------------------------------*/

  /**
   * Constructs and initializes an access control handler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type definition.
   */
  public function __construct(EntityTypeInterface $entityType) {
    parent::__construct($entityType);

    // File and folder names (labels) may be viewed by users granted
    // permission using the generic view operation.
    $this->viewLabelOperation = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(EntityTypeInterface $entityType) {
    return new static($entityType);
  }

  /*---------------------------------------------------------------------
   *
   * Check access.
   *
   * Implements EntityAccessControlHandler and overrides selected methods.
   *
   * These are the primary entrance points for this class.
   *
   *---------------------------------------------------------------------*/

  /**
   * Checks if a user has permission for an operation on an item.
   *
   * The following operations are supported:
   *
   * - 'chown'. Change ownership of the file or folder and all of its contents.
   *
   * - 'delete'.  Delete the file or folder, and all of its subfolders
   *   and files.
   *
   * - 'share'.  Change access grants to share/unshare a root item, and
   *   its folder tree, for view or author access by other users.
   *
   * - 'update'.  Edit the file's or folder's fields.
   *
   * - 'view'.  View or copy the file's or folder's fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which access checking is required.
   * @param string $operation
   *   The name of the operation being considered.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user for which to check access.  Defaults to the
   *   current user.
   * @param bool $returnAsObject
   *   (optional) When TRUE, an access object is returned. When FALSE
   *   (default), a boolean is returned.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The result of the access control check.
   *
   * @section hooks Hooks
   * Via the parent class, this method invokes the "hook_foldershare_access"
   * hook after checking permissions and access grants. This hook is not
   * invoked for administrators, which always have access.
   *
   * @see ::checkAccess()
   */
  public function access(
    EntityInterface $entity,
    $operation,
    AccountInterface $account = NULL,
    $returnAsObject = FALSE) {

    // This method, and those it calls, implement four steps for access
    // control:
    //
    // 1. If the user is a site or content administrator, they are granted
    //    immediate access for all content and all operations.
    //
    // 2. If the user does not have the specific module permission required
    //    for an operation (e.g. view, author, share), they are denied
    //    access.
    //
    // 3. If the user does not own the content and sharing is disabled for
    //    the module, they are denied access.
    //
    // 4. If the user does not own the content and they are not granted
    //    specific access by the content's root item, they are denied
    //    access.
    //
    // Otherwise the user is granted access.
    //
    // This method handles step (1) only. It then forwards to the parent
    // class, which forwards to our checkAccess() method below which
    // handles steps (2), (3), and (4).
    $account = $this->prepareUser($account);

    //
    // Allow site & content administrators everything.
    // -----------------------------------------------
    // Drupal insures that users marked as site administrators always have
    // all permissions. The check below, then, will always return TRUE for
    // site admins.
    //
    // Content administrators have this module's ADMINISTER_PERMISSION,
    // which grants them access to all content for all operations.
    $perm = $this->entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    $access = AccessResult::allowedIfHasPermission($account, $perm);
    if ($access->isAllowed() === TRUE) {
      // The user is either a site admin or they have the module's
      // content admin permission. Grant full access.
      $access->cachePerPermissions();
      return $returnAsObject === TRUE ? $access : $access->isAllowed();
    }

    //
    // Check entity-based ACLs.
    // ------------------------
    // At this point, the user is NOT a site administrator and they do not
    // have module content admin permission. Access to the content is
    // determined now by a mix of permissions, module settings, and content
    // access controls.
    //
    // Forward to the parent class, which implements cache checks and
    // module hooks to override access controls. Afterwards the parent
    // calls our checkAccess() method to handle permissions and root item
    // access control lists.
    $access = parent::access($entity, $operation, $account, TRUE);
    $access->cachePerPermissions();
    return $returnAsObject === TRUE ? $access : $access->isAllowed();
  }

  /**
   * Checks if a given user has permission to create a new entity.
   *
   * This method handles access checks for create operations.
   * Access is granted for creating root items if:
   *
   * - The user has the administer permission OR
   * - The user has the author permission.
   *
   * Note that this method is NOT passed an entity, so it cannot check
   * if creation is allowed within an entity. To do that, use access()
   * on the entity and pass 'create' as the operation.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional, default = NULL = current user) The user for which to
   *   check access.
   * @param array $context
   *   (optional, default = []) An array of key-value pairs to pass additional
   *   context to this method. Ignored by this method.
   * @param string|null $bundle
   *   (optional, default = NULL) An optional bundle of the entity, for
   *   entities that support bundles, and NULL otherwise.  Ignored by this
   *   method since file/folder entities do not support bundles.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The result of the access control check.
   *
   * @see ::checkAccess()
   */
  protected function checkCreateAccess(
    AccountInterface $account = NULL,
    array $context = [],
    $bundle = NULL) {

    $account = $this->prepareUser($account);

    //
    // Allow site & content administrators everything.
    // -----------------------------------------------
    // Drupal insures that users marked as site administrators always have
    // all permissions. The check below, then, will always return TRUE for
    // site admins.
    //
    // Content administrators have this module's ADMINISTER_PERMISSION,
    // which grants them access to everything.
    $perm = $this->entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    $access = AccessResult::allowedIfHasPermission($account, $perm);
    if ($access->isAllowed() === TRUE) {
      // The user is either a site admin or they have the module's
      // content admin permission. Access granted.
      $access->cachePerPermissions()
        ->cachePerUser();
      return $access;
    }

    //
    // Allow author users.
    // -------------------
    // Require AUTHOR_PERMISSION.
    $perm = Constants::AUTHOR_PERMISSION;
    $access = AccessResult::allowedIfHasPermission($account, $perm);
    if ($access->isAllowed() === FALSE) {
      // Access denied.
      return AccessResult::forbidden()
        ->cachePerPermissions()
        ->cachePerUser();
    }

    return AccessResult::allowed()
      ->cachePerPermissions()
      ->cachePerUser();
  }

  /**
   * Checks if a user has permission to view or edit a field.
   *
   * If the entity allows access (based on permissions and root item
   * access grants), then most fields may be viewed and some may be
   * edited. Some internal fields may be viewed by administrators,
   * but not edited directly.
   *
   * The table below indicates which fields are restricted.
   * The fields themselves are defined in the FolderShare class.
   *
   * | Field            | Allow for view | Allow for edit |
   * | ---------------- | -------------- | -------------- |
   * | id               | yes            | no             |
   * | uuid             | yes            | no             |
   * | uid              | yes            | no             |
   * | langcode         | no             | yes            |
   * | created          | yes            | no             |
   * | changed          | yes            | no             |
   * | size             | yes            | no             |
   * | name             | yes            | yes            |
   * | description      | yes            | yes            |
   * | parentid         | yes            | no             |
   * | rootid           | yes            | no             |
   * | kind             | yes            | no             |
   * | file             | yes            | no             |
   * | grantauthoruids  | yes            | no             |
   * | grantviewuids    | yes            | no             |
   * | systemhidden     | no             | no             |
   * | systemdisabled   | no             | no             |
   *
   * Any additional fields created by third-parties or by the field_ui
   * module can be viewed and edited.
   *
   * @param string $operation
   *   The name of the operation being considered, which is always
   *   'view' or 'edit' (defined by Drupal core).
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field being checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user for which to check access.  Defaults to
   *   the current user.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   (optional) The list of field values for which to check access.
   *   Ignored by this method.
   *
   * @return\Drupal\Core\Access\AccessResultInterface
   *   The result of the access control check.
   */
  protected function checkFieldAccess(
    $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account = NULL,
    FieldItemListInterface $items = NULL) {

    // This method is called for every field of every row when showing
    // lists of files and folders. It is therefore important that it run as
    // quickly as possible for view operations.
    $fieldName = $field->getName();
    switch ($operation) {
      case 'view':
        // Any field in FIELDS_VIEW_NEVER is NEVER viewable by any type
        // of user, including admins.
        if (in_array($fieldName, self::FIELDS_VIEW_NEVER, TRUE) === TRUE) {
          // Access denied.
          return AccessResult::forbidden();
        }

        // Access granted.
        return AccessResult::allowed();

      case 'edit':
        // Any field in FIELDS_EDIT_NEVER is NEVER editable directly by
        // any type of user, including admins.
        if (in_array($fieldName, self::FIELDS_EDIT_NEVER, TRUE) === TRUE) {
          // Access denied.
          return AccessResult::forbidden();
        }

        // Access granted.
        return AccessResult::allowed();

      default:
        // Unknown operation. Access denied.
        return AccessResult::forbidden();
    }
  }

  /**
   * Checks if a user can do an operation on an item.
   *
   * This is the principal access control method for this class. It
   * checks permissions and per-root item access control lists to decide
   * if access is granted or denied.
   *
   * The following operations are supported:
   *
   * - 'create'.  Create a new item within a folder.
   *
   * - 'delete'.  Delete the file or folder, and all its subfolders and files.
   *
   * - 'share'.  Change access grants to share/unshare
   *   this item's root item for view or author access by other users.
   *
   * - 'update'.  Edit the file's or folder's fields or add children to
   *   a folder.
   *
   * - 'view'.  View and copy the file's or folder's fields.
   *
   * Each of these operations require an appropriate mix of module
   * permissions, file/folder ownership, and/or access grants:
   *
   * - Create requires that the user have module author permission AND
   *   either own the file/folder OR have been granted author access
   *   on the root item.
   *
   * - Delete requires that the user have module author permission AND
   *   either own the file/folder OR have been granted author access
   *   on the root item.
   *
   * - Share requires that the user have module share permission AND
   *   own the file/folder's root item, where access grants are stored.
   *
   * - Update requires that the user have module author permission AND
   *   either own the file/folder OR have been granted author access
   *   on the root item.
   *
   * - View requires that the user have module view permission AND
   *   either own the file/folder OR have been granted view access
   *   on the root item.
   *
   * - View tree requires that the user have module view permission AND
   *   either own the entire folder tree OR have been granted view access
   *   on the root item.
   *
   * Note that update and delete only check the file/folder for access and
   * NOT all subfolders. This means that a user that is allowed to write to
   * a file/folder, can delete that file/folder and all of its contents,
   * even if they do not have write permission on all of that content.
   * Ownership of a file/folder is always an override of the per-file/folder
   * access controls.
   *
   * @param \Drupal\Core\Entity\EntityInterface $item
   *   The entity for which access checking is required.
   * @param string $operation
   *   The name of the operation being considered.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   (optional) The user for which to check access.  Defaults to
   *   the current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The result of the access control check.
   */
  protected function checkAccess(
    EntityInterface $item,
    $operation,
    AccountInterface $user = NULL) {

    //
    // Validate.
    // ---------
    // If the given entity is missing or invalid, we cannot check access.
    if ($item === NULL) {
      // No entity. Access denied.
      return AccessResult::forbidden();
    }

    $entityTypeId = $item->getEntityTypeId();
    if ($entityTypeId !== FolderShare::ENTITY_TYPE_ID) {
      // Invalid entity. Access denied.
      return AccessResult::forbidden();
    }

    // Get current user info.
    $user = $this->prepareUser($user);
    $userId = (int) $user->id();

    // Get item owner info.
    $ownerId = $item->getOwnerId();

    //
    // Allow site & content administrators everything.
    // -----------------------------------------------
    // Drupal insures that users marked as site administrators always have
    // all permissions. The check below, then, will always return TRUE for
    // site admins.
    //
    // Content administrators have this module's ADMINISTER_PERMISSION,
    // which grants them access to everything.
    $perm = $this->entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    $access = AccessResult::allowedIfHasPermission($user, $perm);
    if ($access->isAllowed() === TRUE) {
      // The user is either a site admin or they have the module's
      // content admin permission. Access granted.
      $access->cachePerPermissions()
        ->cachePerUser();
      return $access;
    }

    //
    // Check for disabled and hidden.
    // ------------------------------
    // Disabled and hidden items are never accessible.
    if ($item->isSystemHidden() === TRUE ||
        $item->isSystemDisabled() === TRUE) {
      return AccessResult::forbidden()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($item);
    }

    //
    // Check access.
    // -------------
    // For each known operation, check permissions and per-root item access
    // control grants.
    switch ($operation) {
      case 'view':
      case 'update':
      case 'delete':
      case 'create':
        // View, update, delete, or create-within content.
        //
        // Allowed if:
        // - User has view/update/delete permission.
        // - And one of:
        //   - User owns the content.
        //   - Owner has sharing permission AND
        //     user has been granted appropriate view or author access.
        if ($operation === 'view') {
          // View.
          $userPermissionNeeded = Constants::VIEW_PERMISSION;
          $userGrantNeeded = 'view';
        }
        elseif ($operation === 'create') {
          // Create. If the item is not a folder, then forbidden.
          if ($item->isFolder() === FALSE) {
            return AccessResult::forbidden()
              ->cachePerPermissions()
              ->cachePerUser()
              ->addCacheableDependency($item);
          }

          $userPermissionNeeded = Constants::AUTHOR_PERMISSION;
          $userGrantNeeded = 'author';
        }
        else {
          // Update and delete.
          $userPermissionNeeded = Constants::AUTHOR_PERMISSION;
          $userGrantNeeded = 'author';
        }

        // Check that the current user has permission.
        $access = AccessResult::allowedIfHasPermission(
          $user,
          $userPermissionNeeded);

        if ($access->isAllowed() === FALSE) {
          // Access denied. The user does not have the required permissions.
          return $access->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($item);
        }

        // Check if the current user is the owner.
        if ($ownerId === $userId) {
          // Success! The owner of an item always has access. No further
          // checking is needed.
          return $access->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($item);
        }

        // Check if the root item is hidden or disabled.
        $rootItem = $item->getRootItem();
        if ($rootItem->isSystemHidden() === TRUE ||
            $rootItem->isSystemDisabled() === TRUE) {
          return AccessResult::forbidden()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($item)
            ->addCacheableDependency($rootItem);
        }

        // Check if the root item's owner has explicitly granted shared
        // access to the current user.
        if ($rootItem->isAccessGranted($userId, $userGrantNeeded) === FALSE) {
          // Access denied. The item is not owned by the current user and
          // the owner of the item's root has not granted the user access.
          return AccessResult::forbidden()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($item)
            ->addCacheableDependency($rootItem);
        }

        // Success!
        return $access->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($item)
          ->addCacheableDependency($rootItem);

      case 'share':
        // Share content.
        //
        // Allowed if:
        // - User has share permission.
        // - User owns the root item.
        //
        // Check that the current user has one of the share permissions.
        $access = AccessResult::allowedIfHasPermission(
          $user,
          Constants::SHARE_PERMISSION);

        if ($access->isAllowed() === FALSE) {
          $access = AccessResult::allowedIfHasPermission(
            $user,
            Constants::SHARE_PUBLIC_PERMISSION);

          if ($access->isAllowed() === FALSE) {
            // Access denied. The user does not have the required permissions.
            return $access->cachePerPermissions()
              ->cachePerUser()
              ->addCacheableDependency($item);
          }
        }

        // Check if the root item is hidden or disabled.
        $rootItem = $item->getRootItem();
        if ($rootItem->isSystemHidden() === TRUE ||
            $rootItem->isSystemDisabled() === TRUE) {
          return AccessResult::forbidden()->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($item)
            ->addCacheableDependency($rootItem);
        }

        // Check if the item is owned by the current user.
        $rootOwnerId = (int) $rootItem->getOwnerId();
        if ($rootOwnerId !== $userId) {
          // Access denied. The current user does not own the item, so they
          // cannot change its sharing grants.
          return AccessResult::forbidden()->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($item)
            ->addCacheableDependency($rootItem);
        }

        // Success!
        return $access->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($item)
          ->addCacheableDependency($rootItem);

      case 'chown':
        // Only site or module content administrators may change ownership
        // on content. Above we already checked for admins and granted access
        // to them. So if we are here, the user is not an admin and access
        // denied.
        return AccessResult::forbidden()
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($item);

      default:
        // Unknown operation. Access denied.
        return AccessResult::forbidden()
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($item);
    }
  }

  /**
   * Checks if a user has permission for an operation.
   *
   * The following operations are supported:
   *
   * - 'chown'
   * - 'delete'
   * - 'share'
   * - 'update'
   * - 'view'
   *
   * Unlike access(), this function only checks permissions, and not
   * entity-based access controls. It therefore returns the *potential*
   * for access, but not an absolute yes/no on access because that
   * would require checking entity access controls too for a specific
   * file or folder.
   *
   * @param string $operation
   *   The name of the operation being considered.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user for which to check access.  Defaults to
   *   the current user.
   *
   * @return bool
   *   The result of the access control check.
   */
  public static function mayAccess(
    string $operation,
    AccountInterface $account = NULL) {

    //
    // Validate.
    // ---------
    // Get the account to check.
    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }

    // Get the entity type.
    $entityType = \Drupal::entityTypeManager()->getDefinition(
      FolderShare::ENTITY_TYPE_ID);

    //
    // Allow site & content administrators everything.
    // -----------------------------------------------
    // Drupal insures that users marked as site administrators always have
    // all permissions. The check below, then, will always return TRUE for
    // site admins.
    //
    // Content administrators have this module's ADMINISTER_PERMISSION,
    // which grants them access to everything.
    $perm = $entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    $access = AccessResult::allowedIfHasPermission($account, $perm);
    if ($access->isAllowed() === TRUE) {
      // The user is either a site admin or they have the module's
      // content admin permission. Access granted.
      return TRUE;
    }

    //
    // Check permissions.
    // ------------------
    // For each known operation, check permissions only.
    switch ($operation) {
      case 'view':
        // View content.
        $perm = Constants::VIEW_PERMISSION;
        break;

      case 'update':
      case 'delete':
        // Change or delete content.
        $perm = Constants::AUTHOR_PERMISSION;
        break;

      case 'share':
        // Alter sharing of content.
        return AccessResult::allowedIfHasPermission($account, Constants::SHARE_PERMISSION)->isAllowed() ||
          AccessResult::allowedIfHasPermission($account, Constants::SHARE_PUBLIC_PERMISSION)->isAllowed();

      case 'create':
        // Authors may create files and subfolders, but the share permission
        // is required to create root items. Unfortunately, we do not
        // know which case the caller intends. Just check author.
        $perm = Constants::AUTHOR_PERMISSION;
        break;

      case 'chown':
        // Only administrators can change ownership, which was already
        // checked above. Access denied.
        return FALSE;

      default:
        // Unrecognized operation. Access denied.
        return FALSE;
    }

    return AccessResult::allowedIfHasPermission($account, $perm)->isAllowed();
  }

  /**
   * Returns a list of operations and their permissions.
   *
   * While the class's access methods may be called repeatedly to
   * check for permission on each operation, this function consolidates
   * those calls and checks permissions all at once for all known operations.
   * The returned associative array has operation names as keys, and
   * TRUE/FALSE values that indicate if the operation is allowed.
   *
   * Operations include:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view'.
   *
   * The $entity argument names an entity for which access is checked.
   * If this is NULL, access checks are for operations on the root item
   * list for which there is no parent entity.
   *
   * @param \Drupal\foldershare\Entity\FolderShare $entity
   *   (optional, default = NULL = user root list) The entity for which
   *   access grants are checked.  If this is NULL, getRootAccessSummary()
   *   is called for FolderShareInterface::USER_ROOT_LIST.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional, default = NULL = current user) The user for which to check
   *   access.
   *
   * @return boolean[]
   *   The returned associative array has operation names as keys, and
   *   TRUE/FALSE values that indicate if the user has permission for
   *   the associated operation.
   */
  public static function getAccessSummary(
    FolderShare $entity = NULL,
    AccountInterface $account = NULL) {

    if ($entity === NULL) {
      return self::getRootAccessSummary(
        FolderShareInterface::USER_ROOT_LIST,
        $account);
    }

    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }

    // Check if the user can perform a specific operation. This should be
    // read as "can the user OPERATOR the item?". For example,
    // "can the user DELETE the item?"
    //
    // The 'create' operator should be read as "can the user CREATE within
    // the item?".
    $a = [
      'chown'  => $entity->access('chown', $account, FALSE),
      'create' => $entity->access('create', $account, FALSE),
      'delete' => $entity->access('delete', $account, FALSE),
      'share'  => $entity->access('share', $account, FALSE),
      'update' => $entity->access('update', $account, FALSE),
      'view'   => $entity->access('view', $account, FALSE),
    ];
    return $a;
  }

  /**
   * Returns a list of rootlist operations and their permissions.
   *
   * While the class's access methods may be called repeatedly to
   * check for permission on each operation, this function consolidates
   * those calls and checks permissions all at once for all known operations.
   * The returned associative array has operation names as keys, and
   * TRUE/FALSE values that indicate if the operation is allowed.
   *
   * Operations include:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view'.
   *
   * The $rootId argument names a root list for which access is checked.
   *
   * @param int $rootId
   *   (optional, default = FolderShareInterface::USER_ROOT_LIST) The ID
   *   for a root list for which access grants are checked. Values are
   *   expected to be one of the well-known root lists:
   *   FolderShareInterface::USER_ROOT_LIST,
   *   FolderShareInterface::ALL_ROOT_LIST, or
   *   FolderShareInterface::PUBLIC_ROOT_LIST. All other values return
   *   no permissions.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional, default = NULL = current user) The user for which to check
   *   access.
   *
   * @return boolean[]
   *   The returned associative array has operation names as keys, and
   *   TRUE/FALSE values that indicate if the user has permission for
   *   the associated operation.
   */
  public static function getRootAccessSummary(
    int $rootId = FolderShareInterface::USER_ROOT_LIST,
    AccountInterface $account = NULL) {

    //
    // Validate.
    // ---------
    // Get the account to check.
    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }

    // Make sure the parent ID is for a root list.
    switch ($rootId) {
      case FolderShareInterface::ALL_ROOT_LIST:
        // Treat as the user's root list.
        $rootId = FolderShareInterface::USER_ROOT_LIST;
        break;

      case FolderShareInterface::USER_ROOT_LIST:
      case FolderShareInterface::PUBLIC_ROOT_LIST:
        break;

      default:
        // For any other entity ID, no permissions.
        return [
          'chown'  => FALSE,
          'create' => FALSE,
          'delete' => FALSE,
          'share'  => FALSE,
          'update' => FALSE,
          'view'   => FALSE,
        ];
    }

    // Get entity info.
    $entityTypeManager = \Drupal::entityTypeManager();
    $entityType = $entityTypeManager->getDefinition(
      FolderShare::ENTITY_TYPE_ID);
    $accessController = $entityTypeManager->getAccessControlHandler(
      FolderShare::ENTITY_TYPE_ID);

    //
    // Allow site & content administrators everything.
    // -----------------------------------------------
    // Drupal insures that users marked as site administrators always have
    // all permissions. The check below, then, will always return TRUE for
    // site admins.
    //
    // Content administrators have this module's ADMINISTER_PERMISSION,
    // which grants them access to everything.
    $perm = $entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    $access = AccessResult::allowedIfHasPermission($account, $perm);
    if ($access->isAllowed() === TRUE) {
      // The user is either a site admin or they have the module's
      // content admin permission.
      //
      // For the public root list, allow admins all access EXCEPT create.
      // It doesn't make sense to create within the public list - adding to
      // the list is done by sharing an existing root with anonymous.
      if ($rootId === FolderShareInterface::PUBLIC_ROOT_LIST) {
        return [
          'chown'  => TRUE,
          'create' => FALSE,
          'delete' => TRUE,
          'share'  => TRUE,
          'update' => TRUE,
          'view'   => TRUE,
        ];
      }

      // For any other root list, admins can do everything.
      return [
        'chown'  => TRUE,
        'create' => TRUE,
        'delete' => TRUE,
        'share'  => TRUE,
        'update' => TRUE,
        'view'   => TRUE,
      ];
    }

    //
    // Check permissions.
    // ------------------
    // Check if the user can perform a specific operation. This should be
    // read as "can the user OPERATOR items in the root list?". For example,
    // "can the user DELETE items in the root list?"
    //
    // The 'create' permission requires a special call back to the access
    // controller, while the others are based on permissions.
    if ($rootId === FolderShareInterface::PUBLIC_ROOT_LIST) {
      // For the public root list, nobody can create directly into the list.
      $canCreate = FALSE;
    }
    else {
      $canCreate = $accessController->createAccess(NULL, $account);
    }

    $canView = AccessResult::allowedIfHasPermission(
      $account,
      Constants::VIEW_PERMISSION)->isAllowed();

    $canAuthor = AccessResult::allowedIfHasPermission(
      $account,
      Constants::AUTHOR_PERMISSION)->isAllowed();

    $canShare = (AccessResult::allowedIfHasPermission(
        $account,
        Constants::SHARE_PERMISSION)->isAllowed() ||
      AccessResult::allowedIfHasPermission(
        $account,
        Constants::SHARE_PUBLIC_PERMISSION)->isAllowed());

    return [
      // Non-administrators cannot change content ownership.
      'chown'  => FALSE,

      // Other operations depend upon the user's permissions.
      'create' => $canCreate,
      'delete' => $canAuthor,
      'share'  => $canShare,
      'update' => $canAuthor,
      'view'   => $canView,
    ];
  }

}
