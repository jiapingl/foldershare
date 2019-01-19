<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Constants;

/**
 * Manages hooks.
 *
 * This trait includes internal methods to invoke module hooks in a
 * standard way.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManageHookTrait {

  /*---------------------------------------------------------------------
   *
   * Operation hooks.
   *
   *---------------------------------------------------------------------*/

  /**
   * Generically invokes a hook.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from classes throughout the module.
   *
   * All exceptions are caught and ignored.
   *
   * @param string $hookName
   *   The name of the hook.
   * @param array $args
   *   Arguments to the hook.
   *
   * @return array
   *   Returns an array of values collected from the hook implementations.
   *   Each implementation may return an array of values, and those arrays
   *   are merged across all hooks and returned.
   */
  public static function hook(
    string $hookName,
    array $args = []) {

    try {
      return \Drupal::moduleHandler()->invokeAll($hookName, $args);
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Invokes a post-operation hook.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from classes throughout the module.
   *
   * The $operationName argument gives the base name of an operation, such
   * as "delete" or "new_folder". This method converts the name to lower case,
   * and prefixes the name with "foldershare_post_operation_".
   *
   * The $item argument gives the FolderShare entity to which the hook applies.
   * This is provided to the hook as its only argument.
   *
   * @param string $operationName
   *   The name of the operation.
   * @param array|\Drupal\foldershare\FolderShareInterface $args
   *   (optional, default = NULL) The FolderShare entity involved in the
   *   operation.
   */
  public static function postOperationHook(
    string $operationName,
    $args = NULL) {

    $op = mb_convert_case($operationName, MB_CASE_LOWER);

    if ($args === NULL) {
      self::hook(Constants::MODULE . '_post_operation_' . $op, []);
    }
    elseif (is_array($args) === TRUE) {
      self::hook(Constants::MODULE . '_post_operation_' . $op, $args);
    }
    else {
      self::hook(Constants::MODULE . '_post_operation_' . $op, [$args]);
    }
  }

}
