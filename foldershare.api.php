<?php

/**
 * @file
 * Documents the principal hooks available in the module.
 *
 * This file provides sample definitions for hooks that are available
 * to respond to and override access control requests and operations
 * that create, delete, save, load, insert, update, view, index, and search
 * file and folder entities.
 *
 * Access control hooks are provided by the Drupal core
 * EntityAccessControlHandler used as a base class for FolderShare
 * access control. Please see that class for further discussion of
 * these hooks.
 *
 * Entity create, delete, etc., hooks are provided by the Drupal core
 * EntityStorageBase and ContentEntityStorageBase classes used by
 * the core Entity and ContentEntity classes, respectively. And ContentEntity
 * is the base class for FolderShare entity mangement. Please see those
 * classes for further discussion of these hooks.
 *
 * View and form hooks are provided by the Drupal core EntityViewBuilder
 * and EntityForm base classes used by FolderShare. Please see those classes
 * for further discussion of these hooks.
 *
 * Search index and result hooks are provided by FolderShare's
 * FolderShareSearch class that defines a Drupal core Search plugin for
 * searching files and folders. Please see that class for further
 * discussion of these hooks.
 *
 * @addtogroup hooks
 * @{
 */

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

use Drupal\foldershare\FolderShareInterface;

/*----------------------------------------------------------------------
 *
 * Operations.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds after completion of operations that add files.
 *
 * This includes operations that upload files, create a new ZIP archive
 * file from existing files, or unarchive a ZIP file into multiple new files.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The parent entity. When NULL, the parent is a root list.
 * @param \Drupal\foldershare\FolderShareInterface[] $fileEntities
 *   An array of added entities.
 */
function hook_foldershare_post_operation_add_files(
  FolderShareInterface $entity = NULL,
  array $fileEntities) {
}

/**
 * Responds after completion of a "Change owner" operation.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The changed entity.
 * @param int $fromUid
 *   The user ID of the entity before it was changed.
 * @param int $toUid
 *   The user ID of the entity after it was changed. This will equal
 *   $entity->getOwnerId().
 */
function hook_foldershare_post_operation_change_owner(
  FolderShareInterface $entity,
  int $fromUid,
  int $toUid) {
}

/**
 * Responds after completion of a "Copy" operation.
 *
 * Folder tree copies will call this hook after each individual file or
 * folder is copied.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The new entity.
 * @param \Drupal\foldershare\FolderShareInterface $origEntity
 *   The original entity that was copied.
 */
function hook_foldershare_post_operation_copy(
  FolderShareInterface $entity,
  FolderShareInterface $origEntity) {
}

/**
 * Responds after completion of a "Delete" operation.
 *
 * @param int $entityId
 *   The delete entity's ID.
 */
function hook_foldershare_post_operation_delete(int $entityId) {
}

/**
 * Responds after completion of a "Download" operation.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The downloaded entity.
 */
function hook_foldershare_post_operation_download(
  FolderShareInterface $entity) {
}

/**
 * Responds after completion of an "Edit" operation.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The edited entity.
 */
function hook_foldershare_post_operation_edit(
  FolderShareInterface $entity) {
}

/**
 * Responds after completion of a "Move" operation.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The moved entity.
 * @param \Drupal\foldershare\FolderShareInterface $oldParent
 *   The old parent.
 * @param \Drupal\foldershare\FolderShareInterface $newParent
 *   The new parent.
 */
function hook_foldershare_post_operation_move(
  FolderShareInterface $entity,
  FolderShareInterface $oldParent = NULL,
  FolderShareInterface $newParent = NULL) {
}

/**
 * Responds after completion of a "New folder" operation.
 *
 * If the operation creates a new root folder, $entity->isRootItem() will
 * be TRUE. Otherwise the operation created a new subfolder.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The newly created entity.
 */
function hook_foldershare_post_operation_new_folder(
  FolderShareInterface $entity) {
}

/**
 * Responds after completion of a "Rename" operation.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The newly created entity.
 * @param string $oldName
 *   The previous name for the entity.
 * @param string $newName
 *   The new name for the entity. This will equal $entity->getName().
 */
function hook_foldershare_post_operation_rename(
  FolderShareInterface $entity,
  string $oldName,
  string $newName) {
}

/**
 * Responds after completion of a "Share" operation.
 *
 * The access grants array arguments are both unordered associative arrays
 * with user IDs as keys and arrays as values. Array values contain strings
 * indicating 'view' or 'author' access.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The newly created entity.
 * @param array $oldGrants
 *   The previous access grants.
 * @param array $newGrants
 *   The new access grants. This will equal $entity->getAccessGrants().
 */
function hook_foldershare_post_operation_share(
  FolderShareInterface $entity,
  array $oldGrants,
  array $newGrants) {
}

/*----------------------------------------------------------------------
 *
 * Access control.
 *
 *----------------------------------------------------------------------*/

/**
 * Controls access to a FolderShare entity.
 *
 * FolderShare's access control mechanism has these steps:
 * - Check if the user is an admin
 * - Check user permissions.
 * - Check entity access grants.
 * - Invoke this hook.
 *
 * Steps are executed in order and the remaining steps are skipped if there
 * is a definitive answer for a step. For instance, if the user is an
 * administrator, they are granted access and the remaining steps are not
 * executed. If the user does not have permission or the entity does not
 * grant them access, then they are denied access and the remaining steps
 * are not executed. This means this hook is only executed if the user
 * is not an admin and does have permission and access granted. This hook,
 * then, can only make a final allow/deny choice.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The entity the user wishes to access. If the entity is NULL, the user
 *   is requesting access to the root folder list.
 * @param string $op
 *   The operation to be performed. Access operators are one of:
 *   - 'create'.
 *   - 'delete'.
 *   - 'share'.
 *   - 'update'.
 *   - 'view'.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The user's account.
 *
 * @return \Drupal\Core\Access\AccessResultInterface
 *   Returns the access result to allow or forbid access, or have a neutral
 *   opinion.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityAccessControlHandler
 * @see \Drupal\foldershare\Entity\FolderShareAccessControlHandler
 */
function hook_foldershare_access(
  FolderShareInterface $entity,
  string $op,
  AccountInterface $account) {

  // A hook could query additional database tables to determine if access
  // should be denied. For instance, special values could mark an entity
  // as immutable due to some external contract. For instance, a legal
  // or accounting requirement might require that certain entities be locked
  // to limit access or changes.
  return AccessResult::neutral();
}

/**
 * Controls access to create a FolderShare entity.
 *
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The user's account.
 * @param array $context
 *   An associative array with additional context values, including at least:
 *   - 'langcode' - the current language code.
 *   - 'parentId' - the entity ID of the parent folder, or
 *      FolderShareInterface::USER_ROOT_LIST if the entity is a root item and
 *      therefore has no parent folder.
 * @param string $entityBundle
 *   The entity bundle name.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityAccessControlHandler
 * @see \Drupal\foldershare\Entity\FolderShareAccessControlHandler
 */
function hook_foldershare_create_access(
  AccountInterface $account,
  array $context,
  $entityBundle) {

  return AccessResult::neutral();
}

/*----------------------------------------------------------------------
 *
 * Entity create.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds when creating a new FolderShare entity.
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object that has been created, but not yet saved.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 * @see ::hook_foldershare_post_operation_new_folder()
 */
function hook_foldershare_create(EntityInterface $entity) {
}

/**
 * Responds after initializing the fields of a FolderShare entity.
 *
 * This hook runs after a new entity object has just been instantiated.
 * It can be used to set initial values (e.g. defaults).
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
 *   The entity object to be initialized.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\ContentEntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 * @see ::hook_foldershare_post_operation_new_folder()
 */
function hook_foldershare_field_values_init(FieldableEntityInterface $entity) {
}

/*----------------------------------------------------------------------
 *
 * Entity storage load.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds when loading a FolderShare entity.
 *
 * Loading an entity goes through these steps:
 * - Load entity from storage.
 * - Call Entity's postLoad().
 * - Invoke load hooks.
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param array $entities
 *   An associative array of entities with entity IDs as keys.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_load(array $entities) {

  // A hook could add virtual fields to the loaded entity, retreiving
  // their values from other database tables or computing them.
}

/*----------------------------------------------------------------------
 *
 * Entity storage save.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds before a FolderShare entity is validated and saved.
 *
 * Saving an entity goes through these steps:
 * - Call ContentEntityBase's preSave().
 * - Invoke each field's presave hooks.
 * - Invoke presave hooks.
 * - Save the entity to storage.
 * - Call ContentEntityBase's postSave().
 * - Invoke each field's update or insert hooks.
 * - Invoke update or insert hooks.
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object that has been created, but not yet saved.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_presave(EntityInterface $entity) {
}

/*----------------------------------------------------------------------
 *
 * Entity storage insert and update.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds after a new FolderShare entity has been stored.
 *
 * Saving a new entity goes through these steps:
 * - Call ContentEntityBase's preSave().
 * - Invoke each field's presave hooks.
 * - Invoke presave hooks.
 * - Save the entity to storage.
 * - Call ContentEntityBase's postSave().
 * - Invoke each field's insert hooks.
 * - Invoke insert hooks.
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object that has been created, but not yet saved.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_insert(EntityInterface $entity) {

  // A hook could save any of its own information about the entity into
  // its own database tables. It may not modify the $entity.
}

/**
 * Responds after storage has been updated for a changed FolderShare entity.
 *
 * Saving an updated entity goes through these steps:
 * - Call ContentEntityBase's preSave().
 * - Invoke each field's presave hooks.
 * - Invoke presave hooks.
 * - Save the entity to storage.
 * - Call ContentEntityBase's postSave().
 * - Invoke each field's update hooks.
 * - Invoke update hooks.
 *
 * FolderShare does not override ContentEntityBase's preSave() and postSave().
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object that is being updated.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_update(EntityInterface $entity) {

  // A hook could save any of its own information about the entity into
  // its own database tables. It may not modify the $entity.
}

/*----------------------------------------------------------------------
 *
 * Entity storage delete.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds before a FolderShare entity is deleted from storage.
 *
 * Deletion of an entity follows these steps:
 * - Call ContentEntityBase's preDelete().
 * - Invoke predelete hooks.
 * - Delete the entity from storage.
 * - Call ContentEntityBase's postDelete().
 * - Invoke delete hooks.
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object that is about to be deleted.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_predelete(EntityInterface $entity) {
}

/**
 * Responds after a FolderShare entity has been deleted from storage.
 *
 * Deletion of an entity follows these steps:
 * - Call ContentEntityBase's preDelete().
 * - Invoke predelete hooks.
 * - Delete the entity from storage.
 * - Call ContentEntityBase's postDelete().
 * - Invoke delete hooks.
 *
 * This is a low-level hook provided by Drupal core. See also higher-level
 * hooks provide by the FolderShare module.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object that has been deleted from storage.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityStorageBase
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_delete(EntityInterface $entity) {

  // A hook could delete any externally saved information associated
  // with this entity, such as records in its own database tables.
}

/*----------------------------------------------------------------------
 *
 * View.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds as a FolderShare entity is being assembled before rendering.
 *
 * Rendered view creation follows these steps:
 * - Load entity.
 * - Create renderable view of the entity.
 * - Invoke view hooks.
 * - Invoke view_alter hooks.
 *
 * @param array $build
 *   A renderable array representing the entity content. This may include
 *   render elements for pseudo-fields and embedded views, such as for a
 *   view listing the contents of a folder.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object being rendered.
 * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
 *   The entity view display holding the options configured for the
 *   entity components, such as their weights on a page and their field
 *   formatters.
 * @param string $viewMode
 *   The name of the view mode.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityViewBuilder
 * @see \Drupal\foldershare\Entity\FolderShare
 */
function hook_foldershare_view(
  array &$build,
  EntityInterface $entity,
  EntityViewDisplayInterface $display,
  $viewMode) {

  // A hook could add renderable information about the entity, such as
  // special marks for favorite entities, indicators for popular or
  // heavily used entities, or flags indicating problem content.
}

/**
 * Responds after a FolderShare entity has been assembled before rendering.
 *
 * Rendered view creation follows these steps:
 * - Load entity.
 * - Create renderable view of the entity.
 * - Invoke view hooks.
 * - Invoke view_alter hooks.
 *
 * @param array $build
 *   A renderable array representing the entity content. This may include
 *   render elements for pseudo-fields and embedded views, such as for a
 *   view listing the contents of a folder.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object being rendered.
 * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
 *   The entity view display holding the options configured for the
 *   entity components, such as their weights on a page and their field
 *   formatters.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityViewBuilder
 * @see \Drupal\foldershare\Entity\Builder\FolderShareViewBuilder
 */
function hook_foldershare_view_alter(
  array &$build,
  EntityInterface $entity,
  EntityViewDisplayInterface $display) {

  // A hook may be used to do post-processing on a renderable version
  // of the entity. This could re-arrange content or suppress content.
}

/**
 * Responds before a cache has been checked for a renderable FolderShare entity.
 *
 * The hook may modify renderable elements relating to cacheing under the
 * '#cache' key.
 *
 * @param array $build
 *   A renderable array representing the entity content. This may include
 *   render elements for pseudo-fields and embedded views, such as for a
 *   view listing the contents of a folder.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object being rendered.
 * @param string $viewMode
 *   The name of the view mode.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityViewBuilder
 * @see \Drupal\foldershare\Entity\Builder\FolderShareViewBuilder
 */
function hook_foldershare_build_defaults_alter(
  array &$build,
  EntityInterface $entity,
  $viewMode) {

  // A hook may add or remove cache control, but it should not modify
  // the build otherwise.
}

/*----------------------------------------------------------------------
 *
 * Edit form.
 *
 *----------------------------------------------------------------------*/

/**
 * Responds before a FolderShare edit form is created.
 *
 * Form creation follows these steps:
 * - Load entity.
 * - Invoke prepare_form hooks.
 * - Build form.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object being edited.
 * @param string $operation
 *   The current operation.
 * @param \Drupal\Core\Form\FormStateInterface $formState
 *   The current state of the form.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\Core\Entity\EntityForm
 * @see \Drupal\foldershare\Form\EditFolderShare
 */
function hook_foldershare_prepare_form(
  EntityInterface $entity,
  $operation,
  FormStateInterface $formState) {

  // A hook may modify the entity before it a form is created to edit
  // the entity. This could add or suppress portions of the $entity.
}

/*----------------------------------------------------------------------
 *
 * Search.
 *
 *----------------------------------------------------------------------*/

/**
 * Appends to search results for a FolderShare entity.
 *
 * FolderShare's search results are created by adding entity names,
 * field values, and file contents to a search index. This index is
 * then searched to produce a list of search results to present to
 * the user. This hook may be used to append to those search results.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The entity being include in the search results.
 *
 * @return array
 *   Returns an associative array with additional named pieces of information
 *   to be included in the search results. This array, along with FolderShare's
 *   array of values, is passed to the search result theme to present.
 *
 * @see \Drupal\foldershare\Plugin\Search\FolderShareSearch
 */
function hook_foldershare_search_result(FolderShareInterface $entity) {

  // A hook could query additional database tables for attributes to
  // include in the search results, such as to mark an item from a
  // favorites list.
  return [];
}

/**
 * Appends to the search index for a FolderShare entity.
 *
 * FolderShare's search indexing accumulates data extracted from an entity,
 * including its name and the values of its text fields and file content.
 * This hook may be used to append additional values to the search index.
 *
 * @param \Drupal\foldershare\FolderShareInterface $entity
 *   The entity being include in the search index.
 *
 * @return string
 *   Returns a string containing additional information to include in the
 *   search index entry for this entity.
 *
 * @see \Drupal\foldershare\Plugin\Search\FolderShareSearch
 */
function hook_foldershare_update_index(FolderShareInterface $entity) {

  // A hook could query additional database tables to get further attributes
  // of an entity.
  return '';
}

/**
 * @}
 */
