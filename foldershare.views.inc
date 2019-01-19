<?php

/**
 * @file
 * Implements search hooks for the module.
 */

use Drupal\foldershare\Entity\FolderShare;

/**
 * Implements hook_views_data_alter().
 *
 * The module's views data is primarily handled by the FolderShareViewsData
 * class, which describes the relevant fields and relationships for the
 * FolderShare entity.  Unfortunately, some modules add further views data
 * for the FolderShare entity. Since these additions are made after FolderShare
 * adds its views data, we cannot adjust those additions until later in
 * this hook.
 *
 * We cannot anticipate every module that might modify the FolderShare views
 * data. But we can correct problems introduced by core modules, such as
 * the Comment and Views modules.
 *
 * @see \Drupal\foldershare\Entity\FolderShareViewsData
 */
function foldershare_views_data_alter(&$data) {

  // Hooks are called after all entity types have added their views data.
  // But if somehow this module's entity type has not yet done this, quit now.
  $baseTable = FolderShare::BASE_TABLE;
  if (isset($data[$baseTable]) === FALSE) {
    return;
  }

  //
  // Comment module additions
  // ------------------------
  // The Comment module adds several of its own fields to entities that
  // have comment fields:
  //
  // - 'comments_link' - a field that creates an 'Add comment' link.
  //
  // - 'uid_touch' - a filter that restricts content to items owned by
  //   a user or posted to by a user.
  //
  // - FIELDNAME . '_cid' - a relationship that connects the entity to
  //   its posted comments. FIELDNAME is the name of the comment field.
  //
  // If the Comment module is installed, there will be a comment manager
  // service. And if a comment field has been added to the FolderShare entity,
  // that comment field will be listed by the comment manager.
  //
  if (\Drupal::hasService('comment.manager') === TRUE) {
    $commentManager = \Drupal::service('comment.manager');
    $commentFields = $commentManager->getFields(FolderShare::ENTITY_TYPE_ID);

    if (empty($commentFields) === FALSE) {
      // Link to add a comment.
      if (isset($data[$baseTable]['comments_link']) === TRUE) {
        // Fix the convoluted grammar.
        $data[$baseTable]['comments_link']['field']['help'] =
          t('Display a link to add a comment to the item. The link is only shown for users that have permission to add comments.');
      }

      // Filter to show items with recent posts by a user.
      if (isset($data[$baseTable]['uid_touch']) === TRUE) {
        // Fix the convoluted grammar, and refer to our entity type
        // instead of 'node'.
        $data[$baseTable]['uid_touch']['help'] =
          t('Filter items to only show items the user has created or commented upon.');
      }

      // Relationship for comments on the item.
      foreach ($commentFields as $fieldName => $field) {
        if (isset($data[$baseTable][$fieldName . '_cid']) === TRUE) {
          // Delete this badly considered relationship. Even the Comment
          // module says this in the relationship's description:
          //
          // 'This will create 1 duplicate record for every comment.
          // Usually if you need this it is better to create a comment view'.
          unset($data[$baseTable][$fieldName . '_cid']);
        }
      }
    }
  }

  //
  // Views module additions
  // ----------------------
  // The Views module automatically adds a generic 'Bulk update' field that
  // adds checkboxes to the rows and adds a form to the top that lists all
  // actions that may be applied to the rows. However, we do not define
  // actions and this is a terrible user interface.
  //
  // Delete this.
  if (isset($data[$baseTable][FolderShare::ENTITY_TYPE_ID . '_bulk_form']) === TRUE) {
    unset($data[$baseTable][FolderShare::ENTITY_TYPE_ID . '_bulk_form']);
  }
}
