<?php

/**
 * @file
 * Implements hooks to update the module.
 *
 * Note that comments on update functions are parsed and shown to the
 * site admin to describe an update before it is applied.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\Entity\Role;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareUsage;

/*----------------------------------------------------------------------
 *
 * 0.5.0 to 0.5.1
 *
 *----------------------------------------------------------------------*/

/**
 * Update the configurations for views, search, and entity views.
 *
 * These mandatory updates support a substantial overhaul of the user
 * interface that revises the module's views, entity view page, and search
 * indexing. Upon completion, the search index will be cleared and all site
 * caches flushed.
 */
function foldershare_update_8001(&$sandbox) {
  // Restore the search configuration and clear the index.
  if (\Drupal::service('module_handler')->moduleExists('search') === TRUE) {
    Utilities::revertConfiguration('core', 'search.page.foldershare_search');
    search_index_clear(Constants::SEARCH_INDEX);
  }

  // Restore the view configuration.
  Utilities::revertConfiguration('view', Constants::VIEW_LISTS);

  // Restore the entity display configuration.
  Utilities::revertConfiguration('core', 'entity_view_display.foldershare.foldershare.default');

  // Flush all caches.
  drupal_flush_all_caches();
}

/*----------------------------------------------------------------------
 *
 * 0.5.4 to 0.6.0
 *
 *----------------------------------------------------------------------*/

/**
 * Update entity structure, entity view, and views tables.
 *
 * This mandatory update removes obsolete internal entity fields, adds new
 * internal entity fields, removes a text field formatter, adds new internal
 * field formatters, adds new menu commands, and adds a share-with-public
 * permission.
 *
 * Note that the entity view page and views tables are necessarily reset.
 * Any site customizations will be lost.
 */
function foldershare_update_8002() {
  $updateManager = \Drupal::entityDefinitionUpdateManager();
  $connection    = Database::getConnection();
  $schema        = $connection->schema();
  $baseTable     = FolderShare::BASE_TABLE;
  $usageTable    = FolderShareUsage::USAGE_TABLE;

  //
  // Update all entities with 'rootfolder' to use 'folder' instead.
  // --------------------------------------------------------------
  // For all entities that use the old value, update to the new value.
  $connection->update($baseTable)
    ->condition('kind', 'rootfolder', '=')
    ->fields(['kind' => FolderShare::FOLDER_KIND])
    ->execute();

  //
  // Remove 'nRootFolders' column from usage table.
  // ----------------------------------------------
  // Remove the column.
  if ($schema->fieldExists($usageTable, 'nRootFolders') === TRUE) {
    $schema->dropField($usageTable, 'nRootFolders');
  }

  //
  // Add 'systemhidden' and 'systemdisabled' fields.
  // -----------------------------------------------
  // Update the base field definition. These definitions are copied from
  // the entity's base field definition for new installs.
  if ($schema->fieldExists($baseTable, 'systemhidden') === FALSE) {
    // System hidden field flag.
    // - Boolean.
    // - Default is FALSE.
    // - 'view' unsupported.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler for all users.
    $definition = BaseFieldDefinition::create('boolean')
      ->setLabel(t('System hidden'))
      ->setDescription(t('A TRUE/FALSE value indicating if the entity has been hidden by the system during special operations.'))
      ->setRequired(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    // Add the field.
    $updateManager->installFieldStorageDefinition(
      'systemhidden',
      FolderShare::ENTITY_TYPE_ID,
      FolderShare::ENTITY_TYPE_ID,
      $definition);
  }

  if ($schema->fieldExists($baseTable, 'systemdisabled') === FALSE) {
    // System disabled field flag.
    // - Boolean.
    // - Default is FALSE.
    // - 'view' unsupported.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler for all users.
    $definition = BaseFieldDefinition::create('boolean')
      ->setLabel(t('System disabled'))
      ->setDescription(t('A TRUE/FALSE value indicating if the entity has been disabled by the system during special operations.'))
      ->setRequired(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    // Add the field.
    $updateManager->installFieldStorageDefinition(
      'systemdisabled',
      FolderShare::ENTITY_TYPE_ID,
      FolderShare::ENTITY_TYPE_ID,
      $definition);
  }

  //
  // Update all entities to default 'systemhidden' and 'systemdisabled' values.
  // --------------------------------------------------------------------------
  // Default to FALSE.
  $connection->update(FolderShare::BASE_TABLE)
   ->fields([
     'systemhidden'   => (int) 0,
     'systemdisabled' => (int) 0,
   ])
   ->execute();


  //
  // Remove 'grantdisableduids' field.
  // ---------------------------------
  // Update the base field definition.
  $definition = $updateManager->getFieldStorageDefinition(
    'grantdisableduids',
    FolderShare::ENTITY_TYPE_ID);
  if ($definition !== NULL) {
    $updateManager->uninstallFieldStorageDefinition($definition);

    // Uninstallling a field marks the field as deleted data. Future
    // runs of CRON will delete the field data in batches until all
    // entities have been updated, and then the multi-value table
    // will be automatically dropped.
  }

  //
  // Flush all caches.
  // -----------------
  // Render and views now use 'systemhidden' and 'systemdisabled', so their
  // caches need to be flushed. The breadcrumb builder's interface has changed,
  // and the field formatter plugins have been updated. There are also new
  // menu commands and a new permission.
  //
  // The cache must be flushed now before we install configurations that use
  // the new field formatters, use the new menu commands, or use the new
  // permission.
  drupal_flush_all_caches();

  //
  // Reset views.
  // ------------
  // The new views use new field formatters, check the 'systemhidden' flag,
  // and support lists in dialogs.
  Utilities::revertConfiguration('view', Constants::VIEW_LISTS);

  //
  // Reset entity view.
  // ------------------
  // The previous released included a text display field formatter that has
  // been removed.
  Utilities::revertConfiguration(
    'core',
    'entity_view_display.foldershare.foldershare.default');

  //
  // Enable new menu commands.
  // -------------------------
  // Insure the new menu commands are enabled by default.
  $currentCommands = Settings::getCommandMenuAllowed();
  if (in_array('foldersharecommand_delete_as_admin', $currentCommands) === FALSE) {
    $currentCommands[] = 'foldersharecommand_delete_as_admin';
  }

  if (in_array('foldersharecommand_delete_on_rootlist', $currentCommands) === FALSE) {
    $currentCommands[] = 'foldersharecommand_delete_on_rootlist';
  }

  if (in_array('foldersharecommand_release_share', $currentCommands) === FALSE) {
    $currentCommands[] = 'foldersharecommand_release_share';
  }

  Settings::setCommandMenuAllowed($currentCommands);

  //
  // Enable the share-with-public permission for roles with share permission.
  // ------------------------------------------------------------------------
  // The old "share" permission shared with others or public. The new one only
  // shares with others, and a new "share with public" permission shares with
  // the public. If user roles had the old "share" permission, give them both
  // share permissions now.
  foreach (\Drupal::entityQuery('user_role')->execute() as $roleId) {
    $role = Role::load($roleId);
    if ($role !== NULL) {
      if ($role->hasPermission(Constants::SHARE_PERMISSION) === TRUE) {
        $role->grantPermission(Constants::SHARE_PUBLIC_PERMISSION);
        $role->save();
      }
    }
  }

  return t('Update complete. Note that the entity view page and view lists configurations have been necessarily reset to defaults.');
}
