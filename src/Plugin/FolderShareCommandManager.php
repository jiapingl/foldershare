<?php

namespace Drupal\foldershare\Plugin;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Logger\LoggerChannelTrait;

use Drupal\foldershare\Constants;
use Drupal\foldershare\entity\FolderShare;

/**
 * Defines a manager for plugins for commands on a shared folder.
 *
 * Similar to Drupal core Actions, shared folder commands encapsulate
 * logic to perform a single operation, such as creating, editing, or
 * deleting files or folders.
 *
 * This plugin manager keeps a list of all plugins after validating
 * that their definitions are well-formed.
 *
 * @ingroup foldershare
 */
class FolderShareCommandManager extends DefaultPluginManager implements CategorizingPluginManagerInterface {

  use CategorizingPluginManagerTrait;
  use LoggerChannelTrait;

  /*--------------------------------------------------------------------
   *
   * Construct.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new command manager.
   *
   * @param \Traversable $namespaces
   *   An object containing the root paths, keyed by the corresponding
   *   namespace, in which to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The backend cache to use to store plugin information.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler) {

    // Create a manager and indicate the name of the directory for plugins,
    // the namespaces to look through, the module handler, the interface
    // plugins should implement, and the annocation class that describes
    // the plugins.
    parent::__construct(
      'Plugin/FolderShareCommand',
      $namespaces,
      $moduleHandler,
      'Drupal\foldershare\Plugin\FolderShareCommand\FolderShareCommandInterface',
      'Drupal\foldershare\Annotation\FolderShareCommand',
      []);

    // Define module hooks that alter FolderShareCommand information to be
    // named "XXX_foldersharecommand_info_alter", where XXX is the module's
    // name.
    $this->alterInfo('foldersharecommand_info');

    // Clear cache definitions and set up the cache backend.
    $this->clearCachedDefinitions();
    $this->setCacheBackend($cacheBackend, 'foldersharecommand_info');
  }

  /*--------------------------------------------------------------------
   *
   * Validate fields.
   *
   *--------------------------------------------------------------------*/

  /**
   * Validates 'kinds' field for parent, destination, & selection constraints.
   *
   * The values must be one of:
   * - 'any'.
   * - 'rootlist'.
   * - one of the FolderShare kinds (e.g. 'file', 'image', 'media', 'folder').
   *
   * The kinds are converted to lower case.
   *
   * If 'any' is present, the constraints are simplified to just 'any'.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   * @param array $constraints
   *   The constraints array with a 'kinds' field. The field is added if
   *   missing, and adjusted to use lower case if needed.
   * @param string $default
   *   The default value to use if 'kinds' is omitted.
   */
  private function validateKinds(
    array &$pluginDefinition,
    array &$constraints,
    string $default = 'any') {

    // If no kind is provided, use the default.
    if (empty($constraints['kinds']) === TRUE) {
      $constraints['kinds'] = [$default];
      return TRUE;
    }

    // If the kind is not an array, error.
    if (is_array($constraints['kinds']) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'kinds' must be an array.");
      return FALSE;
    }

    // Check for known kinds, and convert to lower case.
    $anyPresent = FALSE;
    foreach ($constraints['kinds'] as $index => $k) {
      $lower = mb_convert_case($k, MB_CASE_LOWER);

      switch ($lower) {
        case 'any':
          $anyPresent = TRUE;
          break;

        case 'none':
        case 'rootlist':
        case FolderShare::FILE_KIND:
        case FolderShare::IMAGE_KIND:
        case FolderShare::MEDIA_KIND:
        case FolderShare::FOLDER_KIND:
          break;

        default:
          $pid = $pluginDefinition['id'];
          $this->getLogger(Constants::MODULE)->error(
            "Malformed FolderShareCommand plugin \"$pid\": Unrecognied kind constraint: \"$k\".");
          return FALSE;
      }

      $constraints['kinds'][$index] = $lower;
    }

    // Simplify if 'any' is present.
    if ($anyPresent === TRUE) {
      $constraints['kinds'] = ['any'];
    }

    return TRUE;
  }

  /**
   * Validates 'access' field for parent, destination, & selection constraints.
   *
   * The value must be one of:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view'.
   *
   * The access are converted to lower case.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   * @param array $constraints
   *   The constraints array with an 'access' field. The field is added if
   *   missing, and adjusted to use lower case if needed.
   * @param string $default
   *   The default value to use if 'access' is omitted.
   */
  private function validateAccess(
    array &$pluginDefinition,
    array &$constraints,
    string $default = 'none') {

    // If no access is provided, use the default.
    if (empty($constraints['access']) === TRUE) {
      $constraints['access'] = $default;
      return TRUE;
    }

    // If the access is not a string, error.
    if (is_string($constraints['access']) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'access' must be an scalar string.");
      return FALSE;
    }

    $access = $constraints['access'];
    $lower = mb_convert_case($access, MB_CASE_LOWER);
    switch ($lower) {
      case 'chown':
      case 'create':
      case 'delete':
      case 'share':
      case 'update':
      case 'view':
        break;

      default:
        $pid = $pluginDefinition['id'];
        $this->getLogger(Constants::MODULE)->error(
          "Malformed FolderShareCommand plugin \"$pid\": Unrecognied access constraint: \"$access\".");
        return FALSE;
    }

    $constraints['access'] = $lower;
    return TRUE;
  }

  /**
   * Validates 'types' field for selection constraints.
   *
   * The values must be one of:
   * - 'none' (default).
   * - 'parent'.
   * - 'one'.
   * - 'many'.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   * @param array $constraints
   *   The constraints array with a 'types' field. The field is added if
   *   missing, and adjusted to use lower case if needed.
   * @param string $default
   *   The default value to use if 'types' is omitted.
   */
  private function validateSelectionTypes(
    array &$pluginDefinition,
    array &$constraints,
    string $default = 'none') {

    // If no type is provided, use the default.
    if (empty($constraints['types']) === TRUE) {
      $constraints['types'] = [$default];
      return TRUE;
    }

    // If the types is not an array, error.
    if (is_array($constraints['types']) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'types' must be an array.");
      return FALSE;
    }

    // Check for known types, and convert to lower case.
    foreach ($constraints['types'] as $index => $t) {
      $t = mb_convert_case($t, MB_CASE_LOWER);

      switch ($t) {
        case 'none':
        case 'parent':
        case 'one':
        case 'many':
          break;

        default:
          $pid = $pluginDefinition['id'];
          $this->getLogger(Constants::MODULE)->error(
            "Malformed FolderShareCommand plugin \"$pid\": Unrecognied selection type constraint: \"$t\".");
          return FALSE;
      }

      $constraints['types'][$index] = $t;
    }

    return TRUE;
  }

  /**
   * Validates 'ownership' field for parent, dest, & selection constraints.
   *
   * The values must be one of:
   * - 'any' (default).
   * - 'ownedbyuser'.
   * - 'ownedbyanonymous'.
   * - 'ownedbyanother'.
   * - 'sharedwithusertoview'.
   * - 'sharedwithusertoauthor'.
   * - 'sharedbyuser'.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   * @param array $constraints
   *   The constraints array with a 'ownership' field. The field is added if
   *   missing, and adjusted to use lower case if needed.
   * @param string $default
   *   The default value to use if 'ownership' is omitted.
   */
  private function validateOwnership(
    array &$pluginDefinition,
    array &$constraints,
    string $default = 'any') {

    // If no ownership is provided, use the default.
    if (empty($constraints['ownership']) === TRUE) {
      $constraints['ownership'] = [$default];
      return TRUE;
    }

    // If the ownership is not an array, error.
    if (is_array($constraints['ownership']) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'ownership' must be an array.");
      return FALSE;
    }

    // Check for known ownership, and convert to lower case.
    foreach ($constraints['ownership'] as $index => $t) {
      $t = mb_convert_case($t, MB_CASE_LOWER);

      switch ($t) {
        case 'any':
        case 'ownedbyuser':
        case 'ownedbyanonymous':
        case 'ownedbyanother':
        case 'sharedbyuser':
        case 'sharedwithusertoview':
        case 'sharedwithusertoauthor':
        case 'sharedwithanonymoustoview':
        case 'sharedwithanonymoustoauthor':
          break;

        default:
          $pid = $pluginDefinition['id'];
          $this->getLogger(Constants::MODULE)->error(
            "Malformed FolderShareCommand plugin \"$pid\": Unrecognied ownership constraint: \"$t\".");
          return FALSE;
      }

      $constraints['ownership'][$index] = $t;
    }

    return TRUE;
  }

  /**
   * Validates 'fileExtensions' field for selection constraints.
   *
   * The values are a list of extensions. Leading dots are removed and
   * extensions are converted to lower case.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   * @param array $constraints
   *   The constraints array with a 'fileExtensions' field. The field is
   *   added if missing, and adjusted to use lower case if needed.
   * @param array $default
   *   The default values to use if 'fileExtensions' is omitted.
   */
  private function validateFileExtensions(
    array &$pluginDefinition,
    array &$constraints,
    array $default = NULL) {

    // If no file extension is provided, use the default.
    if (empty($constraints['fileExtensions']) === TRUE) {
      if ($default === NULL) {
        $constraints['fileExtensions'] = [];
      }
      else {
        $constraints['fileExtensions'] = $default;
      }
      return TRUE;
    }

    // If the file extensions is not an array, error.
    if (is_array($constraints['fileExtensions']) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'fileExtensions' must be an array.");
      return FALSE;
    }

    // Remove leading dots, if any, and convert to lower case.
    foreach ($constraints['fileExtensions'] as $index => $f) {
      $f = mb_convert_case($f, MB_CASE_LOWER);
      if (mb_strpos($f, '.') === 0) {
        $f = mb_substr($f, 1);
      }

      $constraints['fileExtensions'][$index] = $f;
    }

    return TRUE;
  }

  /*--------------------------------------------------------------------
   *
   * Validate.
   *
   *--------------------------------------------------------------------*/

  /**
   * Checks that a plugin's definition is complete and valid.
   *
   * A complete definition must have a label, category, parent constraints,
   * selection constraints, destination constraints, and special handling.
   * All values must be valid.
   *
   * The definition is adjusted in-place to create upper/lower case use
   * to standardize values.
   *
   * The definition is regularized in-place to provide default values
   * if the plugin did not specify them explicitly.
   *
   * If the definition is not valid, error messages are logged and FALSE
   * is returned. Otherwise TRUE is returned for valid definitions.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise.
   */
  private function validateDefinition(array &$pluginDefinition) {
    // Label, title, and menu.
    if ($this->validateLabel($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // Category.
    if ($this->validateCategory($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // Weight.
    if ($this->validateWeight($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // User constraints.
    if ($this->validateUserConstraints($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // Parent constraints.
    if ($this->validateParentConstraints($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // Selection constraints.
    if ($this->validateSelectionConstraints($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // Destination constraints.
    if ($this->validateDestinationConstraints($pluginDefinition) === FALSE) {
      return FALSE;
    }

    // Special handling.
    if ($this->validateSpecialHandling($pluginDefinition) === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates the definition's label, title, and menu names.
   *
   * The definition's label must be non-empty.
   *
   * The remaining values may be empty and default to lesser values.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   */
  private function validateLabel(array &$pluginDefinition) {
    //
    // Validate label
    // --------------
    // The label cannot be empty.
    if (empty($pluginDefinition['label']) === TRUE) {
      $this->getLogger(Constants::MODULE)->error(
        'Invalid FolderShareCommand plugin, ID "' .
        $pluginDefinition['id'] .
        '": a "label" value must be defined.');
      return FALSE;
    }

    //
    // Validate menu names
    // -------------------
    // If no menu name is given, use the label.
    if (empty($pluginDefinition['menuNameDefault']) === TRUE) {
      $pluginDefinition['menuNameDefault'] = $pluginDefinition['label'];
    }

    if (empty($pluginDefinition['menuName']) === TRUE) {
      $pluginDefinition['menuName'] = $pluginDefinition['menuNameDefault'];
    }

    if (empty($pluginDefinition['description']) === TRUE) {
      $pluginDefinition['description'] = $pluginDefinition['menuName'];
    }

    return TRUE;
  }

  /**
   * Validates the definition's category.
   *
   * The definition's category must be non-empty. It is automatically updated
   * to use lower case.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   */
  private function validateCategory(array &$pluginDefinition) {
    // The category cannot be empty.
    if (empty($pluginDefinition['category']) === TRUE) {
      $this->getLogger(Constants::MODULE)->error(
        'Invalid FolderShareCommand plugin, ID "' .
        $pluginDefinition['id'] .
        '": the "category" value must be defined.');
      return FALSE;
    }

    // Map category to lower-case.
    $category = mb_convert_case(
      (string) $pluginDefinition['category'],
      MB_CASE_LOWER);
    $pluginDefinition['category'] = $category;

    return TRUE;
  }

  /**
   * Validates the definition's weight.
   *
   * The definition's weight must be an integer and defaults to zero.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   */
  private function validateWeight(array &$pluginDefinition) {
    // Default an empty weight to zero. Otherwise convert to integer.
    if (empty($pluginDefinition['weight']) === TRUE) {
      $pluginDefinition['weight'] = (int) 0;
    }
    else {
      // Insure the stored value is an integer.
      $pluginDefinition['weight'] = (int) $pluginDefinition['weight'];
    }

    return TRUE;
  }

  /**
   * Validates the definition's user constraints.
   *
   * The constraints supported:
   * - 'any' (default).
   * - 'anonymous'.
   * - 'authenticated'.
   * - 'adminpermission'.
   * - 'noadminpermission'.
   * - 'authorpermission'.
   * - 'sharepermission'.
   * - 'sharepublicpermission'.
   * - 'viewpermission'.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   *
   * @see \Drupal\foldershare\Entity\FolderShare
   */
  private function validateUserConstraints(array &$pluginDefinition) {
    // If there are no constraints, use default.
    if (empty($pluginDefinition['userConstraints']) === TRUE) {
      $pluginDefinition['userConstraints'] = ['any'];
      return TRUE;
    }

    // Constraints must be an array.
    $constraints = &$pluginDefinition['userConstraints'];
    if (is_array($constraints) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'userConstraints' must be an array.");
      return FALSE;
    }

    // Check for known user choices, and convert to lower case.
    foreach ($constraints as $index => $u) {
      $u = mb_convert_case($u, MB_CASE_LOWER);

      switch ($u) {
        case 'any':
        case 'anonymous':
        case 'authenticated':
        case 'adminpermission':
        case 'noadminpermission':
        case 'authorpermission':
        case 'sharepermission':
        case 'sharepublicpermission':
        case 'viewpermission':
          break;

        default:
          $pid = $pluginDefinition['id'];
          $this->getLogger(Constants::MODULE)->error(
            "Malformed FolderShareCommand plugin \"$pid\": Unrecognied user constraint: \"$u\".");
          return FALSE;
      }

      $constraints[$index] = $u;
    }

    return TRUE;
  }

  /**
   * Validates the definition's parent constraints.
   *
   * <strong>Parent kinds</strong>
   * The 'kinds' field lists the kinds of parent supported:
   * - 'any' (default).
   * - 'rootlist'.
   * - Any kind supported by FolderShare (e.g. 'file', 'folder').
   *
   * <strong>Parent access</strong>
   * The 'access' field names ONE access operation the parent must support:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view' (default).
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   *
   * @see \Drupal\foldershare\Entity\FolderShare
   * @see \Drupal\foldershare\Entity\FolderShareAccessControlHandler
   */
  private function validateParentConstraints(array &$pluginDefinition) {
    // If there are no constraints, use defaults.
    if (empty($pluginDefinition['parentConstraints']) === TRUE) {
      $pluginDefinition['parentConstraints'] = [
        'kinds'  => ['any'],
        'access' => 'view',
        'ownership' => ['any'],
      ];
      return TRUE;
    }

    // Constraints must be an array.
    $constraints = &$pluginDefinition['parentConstraints'];
    if (is_array($constraints) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'parentConstraints' must be an array.");
      return FALSE;
    }

    // Validate kinds.
    if ($this->validateKinds($pluginDefinition, $constraints, 'any') === FALSE) {
      return FALSE;
    }

    $index = array_search('none', $constraints['kinds']);
    if ($index !== FALSE) {
      unset($constraints['kinds'][$index]);
    }

    // Validate ownership.
    if ($this->validateOwnership($pluginDefinition, $constraints, 'any') === FALSE) {
      return FALSE;
    }

    // Validate access.
    if ($this->validateAccess($pluginDefinition, $constraints, 'view') === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates the definition's selection requirements.
   *
   * Defined by the 'selectionConstraints' annotation, this value is an
   * associative array with keys:
   * - 'types': an optional indicator of the selection size.
   * - 'kinds': an optional list of the kinds of selected items supported.
   * - 'access': an optional access operation that the selection must support.
   *
   * <strong>Selection types</strong>
   * The 'types' field lists types of selection supported:
   * - 'none' (default).
   * - 'parent'.
   * - 'one'.
   * - 'many'.
   *
   * <strong>Selection kinds</strong>
   * The 'kinds' field lists the kinds of selection supported:
   * - 'any' (default).
   * - Any kind supported by FolderShare (e.g. 'file', 'folder').
   *
   * The 'rootlist' kind is not available for selections.
   *
   * <strong>Selection access</strong>
   * The 'access' field names ONE access operation every selected item
   * must support:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view' (default).
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   *
   * @see \Drupal\foldershare\Entity\FolderShare
   * @see \Drupal\foldershare\Entity\FolderShareAccessControlHandler
   */
  private function validateSelectionConstraints(array &$pluginDefinition) {
    // If there are no constraints, use defaults.
    if (empty($pluginDefinition['selectionConstraints']) === TRUE) {
      $pluginDefinition['selectionConstraints'] = [
        'types'  => ['none'],
        'kinds'  => ['any'],
        'access' => 'view',
        'ownership' => ['any'],
      ];
      return TRUE;
    }

    // Constraints must be an array.
    $constraints = &$pluginDefinition['selectionConstraints'];
    if (is_array($constraints) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'selectionConstraints' must be an array.");
      return FALSE;
    }

    // Validate kinds.
    if ($this->validateKinds($pluginDefinition, $constraints, 'any') === FALSE) {
      return FALSE;
    }

    $index = array_search('rootlist', $constraints['kinds']);
    if ($index !== FALSE) {
      unset($constraints['kinds'][$index]);
    }

    // Validate types.
    if ($this->validateSelectionTypes($pluginDefinition, $constraints, 'none') === FALSE) {
      return FALSE;
    }

    // Validate ownership.
    if ($this->validateOwnership($pluginDefinition, $constraints, 'any') === FALSE) {
      return FALSE;
    }

    // Validate file extensions.
    if ($this->validateFileExtensions($pluginDefinition, $constraints, NULL) === FALSE) {
      return FALSE;
    }

    // Validate access.
    if ($this->validateAccess($pluginDefinition, $constraints, 'view') === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates the definition's destination requirements.
   *
   * Defined by the 'destinationConstraints' annotation, this value is an
   * associative array with keys:
   * - 'kinds': an optional list of the kinds of selected items supported.
   * - 'access': an optional access operation that the selection must support.
   *
   * <strong>Destination kinds</strong>
   * The 'kinds' field lists the kinds of destination supported:
   * - 'any' (default).
   * - 'rootlist'.
   * - Any kind supported by FolderShare (e.g. 'file', 'folder').
   *
   * <strong>Destination access</strong>
   * The 'access' field names ONE access operation the destination must support:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view' (default).
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   */
  private function validateDestinationConstraints(array &$pluginDefinition) {
    // If there are no constraints, use defaults.
    if (empty($pluginDefinition['destinationConstraints']) === TRUE) {
      $pluginDefinition['destinationConstraints'] = [
        'kinds'  => ['none'],
        'access' => 'update',
        'ownership' => ['any'],
      ];
      return TRUE;
    }

    // Constraints must be an array.
    $constraints = &$pluginDefinition['destinationConstraints'];
    if (is_array($constraints) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'destinationConstraints' must be an array.");
      return FALSE;
    }

    // Validate kinds.
    if ($this->validateKinds($pluginDefinition, $constraints, 'none') === FALSE) {
      return FALSE;
    }

    // Validate ownership.
    if ($this->validateOwnership($pluginDefinition, $constraints, 'any') === FALSE) {
      return FALSE;
    }

    // Validate access.
    if ($this->validateAccess($pluginDefinition, $constraints, 'update') === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates the definition's special handling.
   *
   * Defined by the 'specialHandling' annotation, this value is an array
   * with known values:
   * - 'upload': the command uploads files.
   *
   * @param array $pluginDefinition
   *   The definition of the plugin.
   *
   * @return bool
   *   Returns TRUE if the plugin definition is complete and valid,
   *   and FALSE otherwise. When FALSE, a message is logged.
   */
  private function validateSpecialHandling(array &$pluginDefinition) {
    // If there is no special handling, use defaults.
    if (empty($pluginDefinition['specialHandling']) === TRUE) {
      $pluginDefinition['specialHandling'] = [];
      return TRUE;
    }

    // Special handling must be an array.
    $handling = &$pluginDefinition['specialHandling'];
    if (is_array($handling) === FALSE) {
      $pid = $pluginDefinition['id'];
      $this->getLogger(Constants::MODULE)->error(
        "Malformed FolderShareCommand plugin \"$pid\": 'specialHandling' must be an array.");
      return FALSE;
    }

    foreach ($handling as $index => $h) {
      $h = mb_convert_case($h, MB_CASE_LOWER);

      switch ($h) {
        case 'upload':
          break;

        default:
          $pid = $pluginDefinition['id'];
          $this->getLogger(Constants::MODULE)->error(
            "Malformed FolderShareCommand plugin \"$pid\": Unrecognied special handling value: \"$h\".");
          return FALSE;
      }

      $handling[$index] = $h;
    }

    return TRUE;
  }

  /*--------------------------------------------------------------------
   *
   * Search.
   *
   * These functions look through the manager's list of plugin commands
   * for those that meet certain criteria.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    // Let the parent class discover definitions, process them, and
    // let other modules alter them. The parent class also filters out
    // definitions for modules that have been uninstalled.
    $definitions = parent::findDefinitions();

    // At this point, the definitions have been created and modules
    // have had a chance to alter them. Now we can check that the
    // definitions are complete and valid. If not, remove them from
    // the definitions list.
    foreach ($definitions as $pluginId => $pluginDefinition) {
      if ($this->validateDefinition($pluginDefinition) === FALSE) {
        // Bad definition. Remove it from the list.
        unset($definitions[$pluginId]);
      }
      else {
        // Validation may have corrected the definition. Save it.
        $definitions[$pluginId] = $pluginDefinition;
      }
    }

    return $definitions;
  }

  /**
   * Get an array of definitions that work with the given parent kinds.
   *
   * The array argument is a list of one or more parent kinds, as defined
   * by the FolderShare entity. The additional kinds are also recognized:
   * - 'any': any FolderShare kind.
   * - 'none': no FolderShare kind.
   * - 'rootlist': the root list context.
   *
   * @param string[] $parentKinds
   *   The kinds of parent for which to get definitions.
   *
   * @return Drupal\foldershare\FolderShareCommand\FolderShareCommandInterface[]
   *   Returns an array of plugin definitions for plugins for which one
   *   of the given parent types meets parent requirements.
   */
  public function getDefinitionsByParentKind(array $parentKinds) {
    // If there are no parent kinds to check, then there are no
    // plugin definitions to return.
    if (empty($parentKinds) === TRUE) {
      return [];
    }

    // Loop through all the definitions and find those that match at
    // least one of the parent kinds.
    $result = [];
    foreach ($this->getDefinitions() as $id => $plugin) {
      foreach ($plugin['parentConstraints']['kinds'] as $k) {
        if ($k === 'none') {
          // Never included.
          continue;
        }

        if ($k === 'any') {
          // Always included.
          $result[$id] = $plugin;
          continue;
        }

        if (in_array($k, $parentKinds, TRUE) === TRUE) {
          // One of the requested kinds.
          $result[$id] = $plugin;
          break;
        }
      }
    }

    return $result;
  }

  /**
   * Returns a list of categorized commands ordered by weight.
   *
   * @return array
   *   Returns an associative array where keys are category names, and
   *   values are arrays of plugins in those categories. Categories are
   *   in an unspecified order. Plugins within a category are sorted
   *   from high to low by weight, and by label within the same weight.
   */
  public function getCategories() {
    // Sift plugins into categories.
    $categories = [];
    foreach ($this->getDefinitions() as $plugin) {
      $cat = $plugin['category'];

      // Add to category.
      if (isset($categories[$cat]) === TRUE) {
        $categories[$cat][] = $plugin;
      }
      else {
        $categories[$cat] = [$plugin];
      }
    }

    // Sort each category by weight.
    foreach ($categories as $cat => &$group) {
      usort(
        $group,
        [
          $this,
          'compareWeight',
        ]);
    }

    return $categories;
  }

  /**
   * Returns a numeric value comparing weights or labels of two plugins.
   *
   * @param mixed $a
   *   The first plugins definition to compare.
   * @param mixed $b
   *   The second plugins definition to compare.
   *
   * @return int
   *   Returns -1 if $a < $b, 1 if $a > $b, and 0 if they are equal.
   *   When weights are equal, labels are are compared to get an alphabetic
   *   order.
   */
  public static function compareWeight($a, $b) {
    $aweight = (int) $a['weight'];
    $bweight = (int) $b['weight'];

    if ($aweight === $bweight) {
      return strcmp($a['label'], $b['label']);
    }

    return ($aweight < $bweight) ? (-1) : 1;
  }

  /*--------------------------------------------------------------------
   *
   * Debug.
   *
   * These functions assist in debugging by printing out information
   * about commands in the manager.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns a string containing a list of plugin commands.
   *
   * @return string
   *   A string containing a table of available plugin commands.
   */
  public function __toString() {
    $s = '';
    foreach ($this->getDefinitions() as $def) {
      // Basics. Always exist.
      $s .= $def['id'] . "\n";
      $s .= '  label:           "' . $def['label'] . "\"\n";
      $s .= '  menuNameDefault: "' . $def['menuNameDefault'] . "\"\n";
      $s .= '  menuName:        "' . $def['menuName'] . "\"\n";
      $s .= '  category:        "' . $def['category'] . "\"\n";
      $s .= '  weight:          ' . $def['weight'] . "\n";

      // Parent constraints.
      $req = $def['parentConstraints'];
      $s .= "  parent constraints:\n";
      if (empty($req['kinds']) === TRUE) {
        $s .= "    kinds: (empty)\n";
      }
      else {
        $s .= '    kinds:';
        foreach ($req['kinds'] as $c) {
          $s .= ' ' . $c;
        }

        $s .= "\n";
      }

      if (empty($req['access']) === TRUE) {
        $s .= "    access: (empty)\n";
      }
      else {
        $s .= '    access: ' . $req['access'] . "\n";
      }

      // Selection constraints.
      $req = $def['selectionConstraints'];
      $s .= "  selection constraints:\n";
      if (empty($req['types']) === TRUE) {
        $s .= "    types: (empty)\n";
      }
      else {
        $s .= '    types:';
        foreach ($req['types'] as $c) {
          $s .= ' ' . $c;
        }

        $s .= "\n";
      }

      if (empty($req['kinds']) === TRUE) {
        $s .= "    kinds: (empty)\n";
      }
      else {
        $s .= '    kinds:';
        foreach ($req['kinds'] as $c) {
          $s .= ' ' . $c;
        }

        $s .= "\n";
      }

      if (empty($req['access']) === TRUE) {
        $s .= "    access: (empty)\n";
      }
      else {
        $s .= '    access: ' . $req['access'] . "\n";
      }

      // Destination constraints.
      $req = $def['destinationConstraints'];
      $s .= "  destination constraints:\n";
      if (empty($req['kinds']) === TRUE) {
        $s .= "    kinds: (empty)\n";
      }
      else {
        $s .= '    kinds:';
        foreach ($req['kinds'] as $c) {
          $s .= ' ' . $c;
        }

        $s .= "\n";
      }

      if (empty($req['access']) === TRUE) {
        $s .= "    access: (empty)\n";
      }
      else {
        $s .= '    access: ' . $req['access'] . "\n";
      }

      // Special handling.
      if (empty($req['specialHandling']) === TRUE) {
        $s .= "    specialHandling: (empty)\n";
      }
      else {
        $s .= '    specialHandling:';
        foreach ($req['specialHandling'] as $c) {
          $s .= ' ' . $c;
        }

        $s .= "\n";
      }
    }

    return $s;
  }

}
