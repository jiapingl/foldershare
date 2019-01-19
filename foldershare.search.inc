<?php

/**
 * @file
 * Implements search hooks for the module.
 */

use Drupal\user\RoleInterface;
use Drupal\Core\Entity\EntityInterface;

use Drupal\foldershare\Entity\FolderShare;

/**
 * Implements hook_foldershare_update_index().
 */
function comment_foldershare_update_index(EntityInterface $item) {
  // This function is closely modeled after comment_node_update_index() in
  // the Drupal core Comment module. That function applies only to node
  // entities, while this function applies to FolderShare entities.
  //
  if (\Drupal::hasService('comment.manager') === FALSE) {
    // While this hook should only be called when the comment module is
    // installed, and when it is installed there should be a comment
    // manager, let us be extra sure and do nothing if the service
    // is absent.
    return '';
  }

  $indexComments = &drupal_static(__FUNCTION__);

  // Determine if comments should be included in the search index. It would
  // be inappropriate to include them if general users cannot view comments.
  if ($indexComments === NULL) {
    // Do not index in the following three cases:
    //
    // 1. 'Authenticated user' can search content but can't access comments.
    //
    // 2. 'Anonymous user' can search content but can't access comments.
    //
    // 3. Any role can search content but can't access comments and access
    //    comments is not granted by the 'authenticated user' role. In this
    //    case all users might have both permissions from various roles but
    //    it is also possible to set up a user to have only search content
    //    and so a user edit could change the security situation so it is
    //    not safe to index the comments.
    $indexComments = TRUE;

    // Get the current roles.
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();

    // Can authenticated users access comments?
    $authCanAccess = $roles[RoleInterface::AUTHENTICATED_ID]->hasPermission('access comments');

    // Loop through all of the roles.
    foreach ($roles as $rid => $role) {
      if ($role->hasPermission('search content') === TRUE &&
          $role->hasPermission('access comments') === FALSE) {
        // The role supports searching, but not comments.
        if ($rid === RoleInterface::AUTHENTICATED_ID ||
            $rid === RoleInterface::ANONYMOUS_ID ||
            $authCanAccess === FALSE) {
          // The role is either for authenticated users or anonymous users,
          // and they cannot access comments. Do not include them in the
          // search index.
          $indexComments = FALSE;
          break;
        }
      }
    }
  }

  if ($indexComments === FALSE) {
    // Do not include comments. Return nothing.
    return '';
  }

  // Do include comments in the search index.
  //
  // Loop through all the comment fields for our entity type.
  $mgr     = \Drupal::service('comment.manager');
  $builder = \Drupal::entityTypeManager()->getViewBuilder('comment');
  $storage = \Drupal::entityTypeManager()->getStorage('comment');
  $fields  = $mgr->getFields(FolderShare::ENTITY_TYPE_ID);
  $build   = [];

  foreach ($fields as $fieldName => $info) {
    // Skip fields that the entity does not have.
    if ($item->hasField($fieldName) === FALSE) {
      continue;
    }

    // Get the definition and characteristics.
    $def     = $item->getFieldDefinition($fieldName);
    $mode    = $def->getSetting('default_mode');
    $perPage = $def->getSetting('per_page');

    // If the field is visible, get the field's comment, build a
    // view of it, and append it to the build we'll be returning.
    if ($item->get($fieldName)->status === TRUE) {
      $comments = $storage->loadThread($item, $fieldName, $mode, $perPage);
      if ($comments !== NULL) {
        $build[] = $builder->viewMultiple($comments);
      }
    }
  }

  // Render and return the comments.
  return \Drupal::service('renderer')->renderPlain($build);
}
