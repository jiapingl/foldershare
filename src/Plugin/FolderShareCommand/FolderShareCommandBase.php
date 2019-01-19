<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;

/**
 * Defines the base class for plugins for shared folder commands.
 *
 * A command performs an operation within the context of a parent folder
 * or list of folders, and operates upon a selection of FolderShare objects.
 * Typical operations are to create, delete, edit, rename, copy, move,
 * share, or change the ownership of items.
 *
 * A command implementation always includes three parts:
 *
 * - Validation that checks if a given configuration of parameters is
 *   consistent and valid for the command.
 *
 * - Access checks that confirm the user has permission to do the command.
 *
 * - Execution that performs the command.
 *
 * All commands use a configuration containing named parameters used as
 * operands for the command. These parameters may include the ID of the
 * parent folder, the IDs of operand files and folders, and so forth.
 *
 * Some commands may provide an optional form where a user may enter
 * additional parameters. A "folder rename" command, for instance, may
 * offer a form for entering a new name. When used, the form is expected
 * to create a configuration that is then passed to validation and
 * execution methods to do the operation.
 *
 * Callers may skip the command form and use their own mechanism to prompt
 * for configuration parameters. They may then call the command's validation
 * and execution methods directly to do the operation.
 *
 * For example, here are several situations that might all use the same
 * command:
 *
 * - Direct use. Code may instantiate a command plugin, set the configuration,
 *   then call the plugin's validation, and execution methods directly
 *   to perform an operation. No command form or UI would be used.
 *
 * - REST use. Code that responds to REST commands may marshal arguments from
 *   the REST interface, then invoke the command as above for direct use.
 *
 * - Custom UI. Code that creates its own user interface, with or without
 *   Javascript, may prompt the user for parameters, the invoke the command
 *   as above for direct use. The command's own forms would not be used.
 *
 * - Form page UI. Drupal's convention of full-page forms may be used to
 *   host a plugin command's forms plus a mechanism to choose a parent
 *   folder context and select files and folders to operate upon (these
 *   are not normally part of a command's own forms). On a submit, the
 *   command maps the form's values to its configuration, then validates,
 *   and executes.
 *
 * - Embedded form UI. When an overlay/dialog can be used, the plugin
 *   command's forms may be embedded within the overlay. Any mechanism to
 *   select the parent folder context and select files and folders must be
 *   done before this overlay. On a submit of the overlay form, the
 *   command maps the form's values to its configuration, then validates
 *   and executes.
 *
 * @ingroup foldershare
 */
abstract class FolderShareCommandBase extends PluginBase implements ConfigurablePluginInterface, FolderShareCommandInterface {

  /*--------------------------------------------------------------------
   *
   * Fields.
   *
   * These fields provide state set during different stages of command
   * handling. The parent, destination, and selection are set as the
   * command is validatedfor a specific configuration, which is held
   * in the parent class. The validation flags are set during stages
   * of validation so that repeated calls to validate don't repeat
   * the associated work.
   *
   *--------------------------------------------------------------------*/

  /**
   * The loaded parent folder, if any.
   *
   * @var \Drupal\foldershare\FolderShareInterface
   */
  private $parent;

  /**
   * The loaded destination folder, if any.
   *
   * @var \Drupal\foldershare\FolderShareInterface
   */
  private $destination;

  /**
   * The loaded selection, if any.
   *
   * The selection is an associative array where keys are kinds, and
   * values are arrays of FolderShare entities.
   *
   * @var \Drupal\foldershare\FolderShareInterface[]
   */
  private $selection;

  /**
   * A flag indicating if command is allowed on the site.
   *
   * @var bool
   */
  protected $commandAllowed;

  /**
   * A flag indicating if user constraints have been validated.
   *
   * @var bool
   */
  protected $userValidated;

  /**
   * A flag indicating if parent constraints have been validated.
   *
   * @var bool
   */
  protected $parentValidated;

  /**
   * A flag indicating if selection constraints have been validated.
   *
   * @var bool
   */
  protected $selectionValidated;

  /**
   * A flag indicating if destination constraints have been validated.
   *
   * @var bool
   */
  protected $destinationValidated;

  /**
   * A flag indicating if additional parameters have been validated.
   *
   * @var bool
   */
  protected $parametersValidated;

  /*--------------------------------------------------------------------
   *
   * Construct.
   *
   * These functions create an instance of a command. Every instance
   * has a definition that describes the command, and a configuration
   * that provides operands for the command. The definition never
   * changes, but the configuration may change as the command is
   * prepared for execution. Validation focuses on the configuration,
   * while the plugin manager has already insured that the command
   * definition is proper.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new plugin with the given configuration.
   *
   * @param array $configuration
   *   The array of named parameters for the new command instance.
   * @param string $pluginId
   *   The ID for the new plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin's implementation definition.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition) {

    parent::__construct($configuration, $pluginId, $pluginDefinition);

    // Set the configuration, which adds in defaults and clears
    // cached values.
    $this->setConfiguration($configuration);
  }

  /*--------------------------------------------------------------------
   *
   * Configuration.
   * (Implements ConfigurablePluginInterface)
   *
   * These functions handle the command's configuration, which is an
   * associative array of named operands for the command. All commands
   * support several well-known operands:
   *
   * - parentId: The entity ID of the parent item, such as a folder.
   *
   * - destinationId: The entity ID of the destination item, such as a folder.
   *
   * - selectionIds: An array of entity IDs of selected items, such as
   *   files and folders.
   *
   * For parentId and destinationId, two special negative values are supported:
   * - FolderShareInterface::USER_ROOT_LIST indicates the user's root list.
   * - self::EMPTY_ITEM_ID indicates no ID has been provided (yet?).
   *
   * For selectionIds, the array may be empty, but the individual values must
   * all be valid positive entity IDs.
   *
   * When a command is created, an initial configuration is set by the
   * caller, combined with a default configuration. Thereafter the caller
   * may change the configuration. On any change, internal state is reset
   * to insure that the new configuration gets validated and used in
   * further calls to the command.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns a required default configuration.
   *
   * The related function, defaultConfiguration(), is intended to provide
   * a default initial configuration for the command. Subclasses may
   * override this to change the default. Unfortunately, they also may
   * override this and fail to provide a default.
   *
   * To insure that there is always a valid starting default configuration,
   * this function returns the required minimal default configuration.
   *
   * @return array
   *   Returns an associative array with keys and values set for a
   *   required minimal configuration.
   *
   * @see defaultConfiguration()
   */
  private function requiredDefaultConfiguration() {
    //
    // The required minimal default configuration has the well-known
    // common configuration keys and initial values for:
    // - no parent.
    // - no destination.
    // - no selection.
    return [
      'parentId'      => self::EMPTY_ITEM_ID,
      'destinationId' => self::EMPTY_ITEM_ID,
      'selectionIds'  => [],
      'uploadClass'   => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    //
    // Return a default fully-initialized configuration used during
    // construction of the command, or to reset it for repeated use.
    //
    // Subclasses may override this function to add further configuration
    // defaults.
    return $this->requiredDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    //
    // Return the current configuration, which is an associative array
    // with well-known and subclass-specific keys for command operands.
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    //
    // Set the configuration.
    //
    // To insure that the configuration always has minimal default settings,
    // the given configuration is merged with the command's defaults and
    // a minimal required set of defaults.
    $this->configuration = ($configuration + $this->defaultConfiguration());
    $this->configuration += $this->requiredDefaultConfiguration();

    // Clear cached values for the parent (if any), destination (if any),
    // and selection (if any).
    $this->parent      = NULL;
    $this->destination = NULL;
    $this->selection   = [];

    // Clear validation flags. The new configuration is not validated until
    // the validation functions are called explicitly, or until the
    // configuration is used.
    $this->validated            = FALSE;
    $this->commandAllowed       = FALSE;
    $this->parentValidated      = FALSE;
    $this->selectionValidated   = FALSE;
    $this->destinationValidated = FALSE;
    $this->parametersValidated  = FALSE;

    // If the configuration has a list of selected entity IDs, clean the
    // list by converting all values to integers and tossing anything
    // invalid. This makes later use of the list easier.
    $ids = $this->configuration['selectionIds'];
    if (empty($ids) === FALSE) {
      foreach ($ids as $index => $id) {
        $iid = (int) $id;
        if ($iid >= 0) {
          $ids[$index] = $iid;
        }
      }
    }
    else {
      $ids = [];
    }

    $this->configuration['selectionIds'] = $ids;

    // If the configuration has parent or destination IDs, make sure
    // they are integers.
    if (empty($this->configuration['parentId']) === FALSE) {
      $this->configuration['parentId'] = (int) $this->configuration['parentId'];
    }

    if (empty($this->configuration['destinationId']) === FALSE) {
      $this->configuration['destinationId'] = (int) $this->configuration['destinationId'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /*--------------------------------------------------------------------
   *
   * Configuration shortcuts - Misc.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns true if the command uses file uploads.
   *
   * @return bool
   *   Returns TRUE if the command's special handling includes 'upload'.
   */
  public function doesFileUploads() {
    $def = $this->getPluginDefinition();
    return (in_array('upload', $def['specialHandling']));
  }

  /*--------------------------------------------------------------------
   *
   * Configuration shortcuts - Parent.
   *
   *--------------------------------------------------------------------*/

  /**
   * Gets and loads the configuration's parent folder, if any.
   *
   * If the parent folder has not been loaded yet, it is loaded and
   * cached.
   *
   * If there is no parent folder specified, or if a root list is specified,
   * then a NULL is returned.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the parent folder, or NULL if there is no parent or the parent
   *   is a root list.
   *
   * @see ::getParentId()
   */
  protected function getParent() {
    // If there is a loaded parent, return it.
    if ($this->parent !== NULL) {
      return $this->parent;
    }

    // If there is no parent or the parent is the root list, return NULL.
    $parentId = $this->getParentId();
    if ($parentId < 0) {
      return NULL;
    }

    // Load the parent and cache the result.
    $this->parent = FolderShare::load($parentId);
    return $this->parent;
  }

  /**
   * Gets the configuration's parent folder ID, if any.
   *
   * @return int
   *   Returns the positive integer folder ID of the parent folder.
   *   The negative self::EMPTY_ITEM_ID indicates that no parent has been set.
   *   The negative FolderShareInterface::USER_ROOT_LIST indicates the
   *   user's root list.
   *
   * @see ::getParent()
   */
  protected function getParentId() {
    if (empty($this->configuration['parentId']) === TRUE) {
      return self::EMPTY_ITEM_ID;
    }

    return (int) $this->configuration['parentId'];
  }

  /**
   * Returns TRUE if the configuration's parent folder ID has been set.
   *
   * @return bool
   *   Returns TRUE if the configuration's parent ID field has been set
   *   and is not EMPTY_ITEM_ID.
   */
  protected function isParentIdSet() {
    return ($this->getParentId() !== self::EMPTY_ITEM_ID);
  }

  /**
   * Sets the configuration's parent folder ID.
   *
   * @param int $id
   *   The positive integer folder ID of the destination folder. Setting
   *   the value to self::EMPTY_ITEM_ID marks the field as empty. Setting
   *   the value to FolderShareInterface::USER_ROOT_LIST indicates the
   *   user's root list.
   */
  protected function setParentId(int $id) {
    $this->configuration['parentId'] = $id;
    $this->parent = NULL;
  }

  /*--------------------------------------------------------------------
   *
   * Configuration shortcuts - Parent.
   *
   *--------------------------------------------------------------------*/

  /**
   * Gets and loads the configuration's destination folder, if any.
   *
   * If the destination folder has not been loaded yet, it is loaded
   * and cached.
   *
   * If there is no destination folder specified, or if a root list is
   * specified, then a NULL is returned.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the destination folder, or NULL if there is no destination
   *   or the destination is a root list.
   *
   * @see ::getDestinationId()
   */
  protected function getDestination() {
    // If there is a loaded destination, return it.
    if ($this->destination !== NULL) {
      return $this->destination;
    }

    // If there is no destination in the configuration, return NULL.
    $destinationId = $this->getDestinationId();
    if ($destinationId < 0) {
      return NULL;
    }

    // Load the destination and cache the result.
    $this->destination = FolderShare::load($destinationId);
    return $this->destination;
  }

  /**
   * Gets the configuration's destination folder ID, if any.
   *
   * @return int
   *   Returns the positive integer folder ID of the destination folder.
   *   The negative self::EMPTY_ITEM_ID indicates that no destination has
   *   been set. The negative FolderShareInterface::USER_ROOT_LIST indicates
   *   the user's root list.
   *
   * @see ::getDestination()
   */
  protected function getDestinationId() {
    if (empty($this->configuration['destinationId']) === TRUE) {
      return self::EMPTY_ITEM_ID;
    }

    return (int) $this->configuration['destinationId'];
  }

  /**
   * Returns TRUE if the configuration's destination folder ID has been set.
   *
   * @return bool
   *   Returns TRUE if the configuration's destination ID field has been set
   *   and is not EMPTY_ITEM_ID.
   */
  protected function isDestinationIdSet() {
    return ($this->getDestinationId() !== self::EMPTY_ITEM_ID);
  }

  /**
   * Sets the configuration's destination folder ID.
   *
   * @param int $id
   *   The positive integer folder ID of the destination folder. Setting
   *   the value to self::EMPTY_ITEM_ID marks the field as empty. Setting
   *   the value to FolderShareInterface::USER_ROOT_LIST indicates the
   *   user's root list.
   */
  protected function setDestinationId(int $id) {
    $this->configuration['destinationId'] = $id;
    $this->destination = NULL;
  }

  /*--------------------------------------------------------------------
   *
   * Configuration shortcuts - Selection.
   *
   *--------------------------------------------------------------------*/

  /**
   * Gets and loads the configuration's array of selected items, if any.
   *
   * If the selected items have not been loaded yet, they are loaded
   * and cached. If there are no selected items, an empty array is returned.
   * If any file cannot be loaded, a NULL is included in the array.
   *
   * The returned associative array has FolderShare kind names as keys,
   * and values that are arrays of loaded FolderShare objects.
   *
   * @return \Drupal\foldershare\FolderShareInterface[]
   *   Returns an array of selected items, or an empty array if there
   *   are no selected items.
   */
  protected function getSelection() {
    // If there are loaded selected items, return them.
    if (empty($this->selection) === FALSE) {
      return $this->selection;
    }

    // If there is no selection in the configuration, return an empty array.
    $ids = $this->getSelectionIds();
    if (empty($ids) === TRUE) {
      return [];
    }

    // Load the items, sort them by kind, and cache the result.
    $items = FolderShare::loadMultiple($ids);
    $sel = [];
    foreach ($items as $item) {
      if ($item === NULL) {
        continue;
      }

      $kind = $item->getKind();
      if (isset($sel[$kind]) === FALSE) {
        $sel[$kind] = [$item];
      }
      else {
        $sel[$kind][] = $item;
      }
    }

    $this->selection = $sel;
    return $this->selection;
  }

  /**
   * Gets the configuration's array of selected entity IDs.
   *
   * @return int[]
   *   Returns an array of positive integer entity IDs for selected items,
   *   or an empty array if there are no selected items. The returned IDs
   *   cannot be negative.
   */
  protected function getSelectionIds() {
    $ids = $this->configuration['selectionIds'];
    if (empty($ids) === TRUE) {
      return [];
    }

    return $ids;
  }

  /*--------------------------------------------------------------------
   *
   * Validate.
   *
   * Validation may be partial or complete. Partial validation means there
   * are errors or something is missing. Complete validation means the
   * configuration is ready for use in executing a command.
   *
   * To keep track of partial validation, the configuration can be validated
   * in stages, in this order:
   * - Validate command use.
   * - Validate parent.
   * - Validate selection.
   * - Validate destination.
   * - Validate additional parameters.
   *
   * Command use validation checks if the site allows the command at all.
   *
   * Parent validation insures the parent folder ID in the configuration
   * meets the command's criteria (see command annotation).
   *
   * Selection validation insures the selection IDs in the configuration
   * meets the command's criteria (see command annotation).
   *
   * Destination validation insures the destination folder ID in the
   * configuration meets the command's criteria (see command annotation).
   *
   * Additional parameter validation lets the command validate any special
   * arguments it may need. These are usually collected from forms.
   *
   * Each validation stage sets flags upon completion so that the
   * validation doesn't have to be repeated if nothing changes in the
   * configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function isValidated() {
    return $this->commandAllowed === TRUE &&
      $this->userValidated === TRUE &&
      $this->parentValidated === TRUE &&
      $this->selectionValidated === TRUE &&
      $this->destinationValidated === TRUE &&
      $this->parametersValidated === TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfiguration() {
    // Validate command is enabled by site.
    $this->validateCommandAllowed();

    // Validate the user constraints.
    $this->validateUserConstraints();

    // Validate the parent constraints.
    $this->validateParentConstraints();

    // Validate the selection constraints.
    $this->validateSelectionConstraints();

    // Validate the destination constraints.
    $this->validateDestinationConstraints();

    // Validate any other parameters for the command.
    $this->validateParameters();
  }

  /**
   * {@inheritdoc}
   */
  public function validateCommandAllowed() {
    if ($this->commandAllowed === TRUE) {
      return;
    }

    // Check site settings to insure the command is allowed.
    $commandId = $this->getPluginDefinition()['id'];
    $allowedIds = Settings::getCommandMenuAllowed();
    if (in_array($commandId, $allowedIds) === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The @command operation is not available.',
          [
            '@command' => $this->getPluginDefinition()['label'],
          ]),
        t('The site administrator has disabled this activity.')));
    }

    // Validated!
    $this->commandAllowed = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateUserConstraints() {
    if ($this->userValidated === TRUE) {
      return;
    }

    if ($this->commandAllowed === FALSE) {
      $this->validateCommandAllowed();
    }

    //
    // Setup.
    // ------
    // Get the command's definition and its constraints.
    $def = $this->getPluginDefinition();
    $userConstraints = $def['userConstraints'];
    $user = \Drupal::currentUser();

    //
    // Check user constraints.
    // -----------------------
    // If the "any" constraint is present, no checking is needed. Otherwise,
    // check each of the well-known user constraints.
    if (in_array('any', $userConstraints) === FALSE) {
      $allowed = FALSE;
      if (in_array('authenticated', $userConstraints) === TRUE &&
          $user->isAuthenticated() === TRUE) {
        $allowed = TRUE;
      }
      elseif (in_array('anonymous', $userConstraints) === TRUE &&
          $user->isAnonymous() === TRUE) {
        $allowed = TRUE;
      }
      elseif (in_array('adminpermission', $userConstraints) === TRUE &&
          $user->hasPermission(Constants::ADMINISTER_PERMISSION) === TRUE) {
        $allowed = TRUE;
      }
      elseif (in_array('noadminpermission', $userConstraints) === TRUE &&
          $user->hasPermission(Constants::ADMINISTER_PERMISSION) === FALSE) {
        $allowed = TRUE;
      }
      elseif (in_array('viewpermission', $userConstraints) === TRUE &&
          $user->hasPermission(Constants::VIEW_PERMISSION) === TRUE) {
        $allowed = TRUE;
      }
      elseif (in_array('authorpermission', $userConstraints) === TRUE &&
          $user->hasPermission(Constants::AUTHOR_PERMISSION) === TRUE) {
        $allowed = TRUE;
      }
      elseif (in_array('sharepermission', $userConstraints) === TRUE &&
          $user->hasPermission(Constants::SHARE_PERMISSION) === TRUE) {
        $allowed = TRUE;
      }
      elseif (in_array('sharepublicpermission', $userConstraints) === TRUE &&
          $user->hasPermission(Constants::SHARE_PUBLIC_PERMISSION) === TRUE) {
        $allowed = TRUE;
      }

      if ($allowed === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t('You do not have permission to complete this operation.'),
          t('This command is restricted to specific types of users.')));
      }
    }

    // Validated!
    $this->userValidated = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateParentConstraints() {
    if ($this->parentValidated === TRUE) {
      return;
    }

    if ($this->userValidated === FALSE) {
      $this->validateUserConstraints();
    }

    //
    // Setup.
    // ------
    // Get the command's definition and its constraints.
    $def = $this->getPluginDefinition();
    $label = $def['label'];

    // Get the parent's kind.
    //
    // At this stage, the parent ID can be one of:
    // - FolderShareInterface::USER_ROOT_LIST.
    // - FolderShareInterface::PUBLIC_ROOT_LIST.
    // - FolderShareInterface::ALL_ROOT_LIST.
    // - A valid entity ID.
    $parentId = $this->getParentId();
    $parent = NULL;

    if ($parentId < 0) {
      // Simplify special entity IDs.
      switch ($parentId) {
        default:
        case FolderShareInterface::ALL_ROOT_LIST:
          // While an admin can operate on anybody's root items in the "all"
          // list, if they create anything (new folder, duplicate, upload,
          // compress, uncompress, etc.), it goes into their own root list.
          // So, treat the "all" list as the user's root list.
          $parentId = FolderShareInterface::USER_ROOT_LIST;
          $this->setParentId($parentId);
          break;

        case FolderShareInterface::USER_ROOT_LIST:
        case FolderShareInterface::PUBLIC_ROOT_LIST:
          // Continue to distinguish between the user and public lists.
          break;

        case self::EMPTY_ITEM_ID:
          // This should not happen. It means the UI did not fill in the
          // parent ID.
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'Missing parent folder ID for the "@command" command.',
              [
                '@command' => $label,
              ]),
            t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }

      $parentKind = 'rootlist';
    }
    else {
      $parent = $this->getParent();
      if ($parent === NULL) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Missing parent folder ID for the "@command" command.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }

      if ($parent->isSystemHidden() === TRUE) {
        // Hidden items do not exist.
        throw new NotFoundHttpException();
      }

      if ($parent->isSystemDisabled() === TRUE) {
        // Disabled items cannot be used.
        throw new AccessDeniedHttpException();
      }

      $parentKind = $parent->getKind();
    }

    //
    // Check parent kind.
    // ------------------
    // Compare the parent kind against the list of supported kinds
    // for the command.
    $allowedKinds = $def['parentConstraints']['kinds'];

    if (in_array('any', $allowedKinds) === FALSE) {
      if ($parentKind === 'rootlist' &&
          in_array('rootlist', $allowedKinds) === FALSE) {
        // Parent is a root list but the command doesn't support that.
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Root list provided instead of a required parent folder ID for the "@command" command.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
      elseif (in_array($parentKind, $allowedKinds) === FALSE) {
        // Parent provided but its kind is not supported by the command.
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Parent item provided does not meet the "@command" command\'s requirements.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
    }

    //
    // Check parent ownership.
    // -----------------------
    // Confirm that the parent meets ownership constraints.
    // Skip the check if the command supports 'any' as an ownership choice
    // (which is the default).
    $allowedOwnership = $def['parentConstraints']['ownership'];
    $user             = \Drupal::currentUser();

    if (in_array('any', $allowedOwnership) === FALSE) {
      $userId    = (int) $user->id();
      $anonymous = User::getAnonymousUser();
      $anonId    = (int) $anonymous->id();
      $allowed   = FALSE;

      if ($parent !== NULL) {
        // There is a parent entity. Check it against the allowed
        // ownership constraints.
        //
        // Since access grants are set on the root only, use it to determine
        // the sharing status below.
        $root = $parent->getRootItem();

        if (in_array('ownedbyuser', $allowedOwnership) === TRUE &&
            $parent->isOwnedBy($userId) === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('ownedbyanonymous', $allowedOwnership) === TRUE &&
            $parent->isOwnedBy($anonId) === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('ownedbyanother', $allowedOwnership) === TRUE &&
            $parent->isOwnedBy($userId) === FALSE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedbyuser', $allowedOwnership) === TRUE &&
            $root->isSharedBy($userId) === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithusertoview', $allowedOwnership) === TRUE &&
            $root->isSharedWith($userId, 'view') === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithusertoauthor', $allowedOwnership) === TRUE &&
            $root->isSharedWith($userId, 'author') === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithanonymoustoview', $allowedOwnership) === TRUE &&
            $root->isSharedWith($anonId, 'view') === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithanonymoustoauthor', $allowedOwnership) === TRUE &&
            $root->isSharedWith($anonId, 'author') === TRUE) {
          $allowed = TRUE;
        }
      }
      else {
        // There is no parent entity. The parent is a rootlist. For purposes
        // of parent ownership checking:
        // - USER_ROOT_LIST is owned by the current user.
        // - PUBLIC_ROOT_LIST is owned by anonymous.
        if ($parentId === FolderShareInterface::USER_ROOT_LIST) {
          if (in_array('ownedbyuser', $allowedOwnership) === TRUE) {
            $allowed = TRUE;
          }
        }
        elseif ($parentId === FolderShareInterface::PUBLIC_ROOT_LIST) {
          if (in_array('ownedbyanonymous', $allowedOwnership) === TRUE) {
            $allowed = TRUE;
          }
          elseif (in_array('sharedwithanonymoustoview', $allowedOwnership) === TRUE &&
              $anonymous->hasPermission(Constants::VIEW_PERMISSION) === TRUE) {
            $allowed = TRUE;
          }
          elseif (in_array('sharedwithanonymoustoauthor', $allowedOwnership) === TRUE &&
              $anonymous->hasPermission(Constants::AUTHOR_PERMISSION) === TRUE) {
            $allowed = TRUE;
          }
        }
      }

      if ($allowed === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Parent item provided does not meet the "@command" command\'s ownership requirements.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
    }

    //
    // Check parent access.
    // --------------------
    // Check if the required access is allowed for the parent or root list.
    // Handle 'create' specially since it is not available via access().
    $allowedAccess = $def['parentConstraints']['access'];
    if ($parent !== NULL) {
      $allowed = $parent->access($allowedAccess, $user, FALSE);
    }
    else {
      $summary = FolderShareAccessControlHandler::getRootAccessSummary($parentId, $user);
      if (isset($summary[$allowedAccess]) === TRUE &&
          $summary[$allowedAccess] === TRUE) {
        $allowed = TRUE;
      }
      else {
        $allowed = FALSE;
      }
    }

    if ($allowed === FALSE) {
      if ($parentId >= 0) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t('You do not have permission to complete this operation.'),
          t('The current folder is restricted and cannot be accessed.')));
      }

      throw new ValidationException(Utilities::createFormattedMessage(
        t('You do not have permission to complete this operation.'),
        t('The current top-level list is restricted and cannot be accessed.')));
    }

    // Validated!
    $this->parentValidated = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSelectionConstraints() {
    if ($this->selectionValidated === TRUE) {
      return;
    }

    if ($this->parentValidated === FALSE) {
      $this->validateParentConstraints();
    }

    $def          = $this->getPluginDefinition();
    $label        = $def['label'];
    $allowedTypes = $def['selectionConstraints']['types'];

    if (in_array('none', $allowedTypes) === TRUE) {
      // No selection required!
      //
      // Validated!
      $this->selectionValidated = TRUE;
      return;
    }

    //
    // Setup.
    // ------
    // Get the parent ID. Parent validation has already simplified the
    // range of values to:
    // - FolderShareInterface::USER_ROOT_LIST.
    // - FolderShareInterface::PUBLIC_ROOT_LIST.
    // - A valid entity ID.
    $parentId = $this->getParentId();

    // Count the total number of selected items.
    $selectionIds      = $this->getSelectionIds();
    $nSelected         = count($selectionIds);
    $selectionIsParent = FALSE;

    //
    // Check selection size.
    // ---------------------
    // Insure the selection size is compatible with the command.
    //
    // Config selection   Command constraints   Result
    // ----------------   -------------------   ------
    // none, one, many    none needed           allowed.
    // none               can have parent       allowed, use parent.
    // none               cannot have parent    fail, need selection.
    // one                can have one          allowed, use selection.
    // one                cannot have one       fail, need more.
    // many               can have many         allowed, use selection.
    // many               cannot have many      fail, need fewer.
    $canHaveOne    = in_array('one', $allowedTypes);
    $canHaveMany   = in_array('many', $allowedTypes);
    $canHaveParent = in_array('parent', $allowedTypes);

    $allowed = TRUE;
    $failureIsMultiple = TRUE;

    if ($nSelected === 0) {
      if ($canHaveParent === TRUE && $parentId >= 0) {
        // There is no selection, the command can default to the parent,
        // and there is a parent!
        $nSelected = 1;
        $selectionIsParent = TRUE;
      }
      else {
        // There is no selection, but either the command cannot default to
        // the parent, or there is no parent. Fail.
        $allowed = FALSE;
        $failureIsMultiple = ($canHaveMany === TRUE);
      }
    }
    elseif ($nSelected > 1 && $canHaveMany === FALSE) {
      // There are multiple items selected, but the command does not allow
      // that. Fail.
      $allowed = FALSE;
      $failureIsMultiple = FALSE;
    }
    elseif ($nSelected === 1 && $canHaveOne === FALSE && $canHaveMany === FALSE) {
      // There is just one item selected, but the command does not allow
      // having just one or even lots of items selected. Fail.
      $allowed = FALSE;
      $failureIsMultiple = TRUE;
    }

    if ($allowed === FALSE) {
      if ($failureIsMultiple === TRUE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Multiple selection IDs required for the "@command" command.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }

      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'Single selection ID required for the "@command" command.',
          [
            '@command' => $label,
          ]),
        t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
    }

    if ($selectionIsParent === FALSE) {
      $selection = $this->getSelection();
    }
    else {
      $parent = $this->getParent();
      $selection[$parent->getKind()] = [$parent];
    }

    //
    // Check selection kinds.
    // ----------------------
    // Insure the kinds of items in the selection are compatible with
    // the command.
    //
    // Config selection   Command constraints   Result
    // ----------------   -------------------   ------
    // any                any                   allowed, use selection.
    // specific kind      kind                  allowed, if they match.
    $allowedKinds = $def['selectionConstraints']['kinds'];

    if (in_array('any', $allowedKinds) === FALSE) {
      $allowed = TRUE;
      foreach (array_keys($selection) as $kind) {
        if (in_array($kind, $allowedKinds) === FALSE) {
          $allowed = FALSE;
          break;
        }
      }

      if ($allowed === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Selection provided does not meet the "@command" command\'s kind requirements.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
    }

    //
    // Check selection validity and parentage.
    // ---------------------------------------
    // Confirm that all of the selected items are children of the parent,
    // if any. Skip this check if the selection has been defaulted to
    // the parent.
    //
    // Config parent    Item's parent   Result
    // -------------    -------------   ------
    // USER_ROOT_LIST   USER_ROOT_LIST  Allowed.
    // USER_ROOT_LIST   integer         Fail, item should have no parent.
    // PUBLIC_ROOT_LIST USER_ROOT_LIST  Allowed.
    // PUBLIC_ROOT_LIST integer         Fail, item should have no parent.
    // integer          USER_ROOT_LIST  Fail, item should have a parent.
    // integer          integer         Allowed if they match.
    if ($selectionIsParent === FALSE) {
      foreach ($selection as $kind => $items) {
        foreach ($items as $item) {
          if ($item === NULL || $item->isSystemHidden() === TRUE) {
            // Bad items or hidden items do not exist.
            throw new NotFoundHttpException();
          }

          if ($item->isSystemDisabled() === TRUE) {
            // Disabled items cannot be used.
            throw new AccessDeniedHttpException();
          }

          if ($parentId === FolderShareInterface::USER_ROOT_LIST ||
              $parentId === FolderShareInterface::PUBLIC_ROOT_LIST) {
            // The configuration's parent is the user or public root list.
            // If the item is a root item, then allowed.
            $allowed = ($item->isRootItem() === TRUE);
          }
          else {
            // The configuration's parent is a non-root item. If the item
            // is not a root, then allowed.
            $allowed = ($item->isRootItem() === FALSE);
          }

          if ($allowed === FALSE) {
            throw new ValidationException(Utilities::createFormattedMessage(
              t(
                'Selection provided does not meet the "@command" command\'s parentage requirements.',
                [
                  '@command' => $label,
                ]),
              t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
          }
        }
      }
    }

    //
    // Check selection ownership.
    // --------------------------
    // Confirm that all of the selected items meet ownership constraints.
    // Skip the check if the command supports 'any' as an ownership choice
    // (which is the default).
    $allowedOwnership = $def['selectionConstraints']['ownership'];
    $user = \Drupal::currentUser();

    if (in_array('any', $allowedOwnership) === FALSE) {
      $userId    = (int) $user->id();
      $anonymous = User::getAnonymousUser();
      $anonId    = (int) $anonymous->id();

      $checkOwnedByAnonymous = in_array('ownedbyanonymous', $allowedOwnership);
      $checkOwnedByAnother   = in_array('ownedbyanother', $allowedOwnership);
      $checkOwnedByUser      = in_array('ownedbyuser', $allowedOwnership);

      $checkSharedByUser =
        in_array('sharedbyuser', $allowedOwnership);
      $checkSharedWithAnonymousToView =
        in_array('sharedwithanonymoustoview', $allowedOwnership);
      $checkSharedWithAnonymousToAuthor =
        in_array('sharedwithanonymoustoauthor', $allowedOwnership);
      $checkSharedWithUserToView =
        in_array('sharedwithusertoview', $allowedOwnership);
      $checkSharedWithUserToAuthor =
        in_array('sharedwithusertoauthor', $allowedOwnership);

      // Since access grants are set on the root only, use it to determine
      // the sharing status below. Get the root below from the first selected
      // item. All items in the selection list have already been validated to
      // have the same parent, so they'll have the same root too.
      $root = NULL;
      $allowed = TRUE;
      foreach ($selection as $kind => $items) {
        foreach ($items as $item) {
          $itemIsAllowed = FALSE;

          // For the first item in the selection, get the root. It will be
          // the same root for everything in the selection.
          if ($root === NULL) {
            $root = $item->getRootItem();
          }

          if ($checkOwnedByUser === TRUE &&
              $item->isOwnedBy($userId) === TRUE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkOwnedByAnonymous === TRUE &&
              $item->isOwnedBy($anonId) === TRUE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkOwnedByAnother === TRUE &&
              $item->isOwnedBy($userId) === FALSE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkSharedByUser === TRUE &&
              $root->isSharedBy($userId) === TRUE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkSharedWithUserToView === TRUE &&
              $root->isSharedWith($userId, 'view') === TRUE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkSharedWithUserToAuthor === TRUE &&
              $root->isSharedWith($userId, 'author') === TRUE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkSharedWithAnonymousToView === TRUE &&
              $root->isSharedWith($anonId, 'view') === TRUE) {
            $itemIsAllowed = TRUE;
          }
          elseif ($checkSharedWithAnonymousToAuthor === TRUE &&
              $root->isSharedWith($anonId, 'author') === TRUE) {
            $itemIsAllowed = TRUE;
          }

          if ($itemIsAllowed === FALSE) {
            $allowed = FALSE;
            break 2;
          }
        }
      }

      if ($allowed === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Selection provided does not meet the "@command" command\'s ownership requirements.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
    }

    //
    // Check selection file extensions.
    // --------------------------------
    // Confirm that all of the selected items meet file name extension
    // constraints.  Skip the check if the command has no extensions
    // (which is the default).
    $allowedExtensions = $def['selectionConstraints']['fileExtensions'];

    if (empty($allowedExtensions) === FALSE) {
      $allowed = TRUE;
      foreach ($selection as $kind => $items) {
        foreach ($items as $item) {
          $ext = $item->getExtension();
          if (empty($ext) === TRUE) {
            $allowed = FALSE;
            break 2;
          }

          if (in_array($ext, $allowedExtensions) === FALSE) {
            $allowed = FALSE;
            break 2;
          }
        }
      }

      if ($allowed === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Selection provided does not meet the "@command" command\'s file name extension requirements.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
    }

    //
    // Check selection access.
    // -----------------------
    // If there is a selection and access is required, check for access.
    // Commands that do not require a selection, or selection access, have
    // an access operation of 'none'.
    $allowedAccess = $def['selectionConstraints']['access'];
    $allowed == TRUE;
    foreach ($selection as $items) {
      foreach ($items as $item) {
        if ($item->access($allowedAccess, $user, FALSE) === FALSE) {
          $allowed = FALSE;
          break 2;
        }
      }
    }

    if ($allowed === FALSE) {
      if ($selectionIsParent === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t('You do not have permission to complete this operation.'),
          t('One or more selected items are restricted and cannot be accessed.')));
      }

      if ($parentId >= 0) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t('You do not have permission to complete this operation.'),
          t('The current folder is restricted and cannot be accessed.')));
      }

      throw new ValidationException(Utilities::createFormattedMessage(
        t('You do not have permission to complete this operation.'),
        t('The current top-level list is restricted and cannot be accessed.')));
    }

    // Validated!
    $this->selectionValidated = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateDestinationConstraints() {
    if ($this->destinationValidated === TRUE) {
      return;
    }

    if ($this->selectionValidated === FALSE) {
      $this->validateSelectionConstraints();
    }

    $def          = $this->getPluginDefinition();
    $label        = $def['label'];
    $allowedKinds = $def['destinationConstraints']['kinds'];

    if (in_array('none', $allowedKinds) === TRUE) {
      // No destination required.
      //
      // Validated!
      $this->destinationValidated = TRUE;
      return;
    }

    //
    // Setup.
    // ------
    // Get the destinations's kind.
    $destinationId = $this->getDestinationId();
    $destination = NULL;
    if ($destinationId === self::EMPTY_ITEM_ID) {
      $destinationKind = 'none';
    }
    elseif ($destinationId === FolderShareInterface::USER_ROOT_LIST) {
      $destinationKind = 'rootlist';
    }
    else {
      $destination = $this->getDestination();
      if ($destination === NULL) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Missing destination folder ID for the "@command" command.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }

      if ($destination->isSystemHidden() === TRUE) {
        // Hidden items do not exist.
        throw new NotFoundHttpException();
      }

      if ($destination->isSystemDisabled() === TRUE) {
        // Disabled items cannot be used.
        throw new AccessDeniedHttpException();
      }

      $destinationKind = $destination->getKind();
    }

    //
    // Check destination kind.
    // -----------------------
    // Compare the destination kind against the list of supported kinds
    // for the command.
    if ($destinationKind === 'none' &&
        in_array('none', $allowedKinds) === FALSE) {
      // There is no destination but the command doesn't support that.
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'Missing required destination folder ID for the "@command" command.',
          [
            '@command' => $label,
          ]),
        t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
    }
    elseif ($destinationKind === 'rootlist' &&
        in_array('rootlist', $allowedKinds) === FALSE) {
      // Destination is a root list but the command doesn't support that.
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'Root list provided instead of a required destination folder ID for the "@command" command.',
          [
            '@command' => $label,
          ]),
        t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
    }
    elseif (in_array($destinationKind, $allowedKinds) === FALSE &&
        in_array('any', $allowedKinds) === FALSE) {
      // Destination provided but its kind is not supported by the command.
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'Destination item provided does not meet the "@command" command\'s requirements.',
          [
            '@command' => $label,
          ]),
        t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
    }

    //
    // Check destination ownership.
    // ----------------------------
    // Confirm that the destination meets ownership constraints.
    // Skip the check if the command supports 'any' as an ownership choice
    // (which is the default).
    //
    // Rootlists are not owned, so there is no ownership constraint for them.
    $allowedOwnership = $def['destinationConstraints']['ownership'];
    $user = \Drupal::currentUser();

    if (in_array('any', $allowedOwnership) === FALSE) {
      $userId    = (int) $user->id();
      $anonymous = User::getAnonymousUser();
      $anonId    = (int) $anonymous->id();

      if ($destination !== NULL) {
        // There is a destination entity. Check it against the allowed
        // ownership constraints.
        //
        // Since access grants are set on the root only, use it to determine
        // the sharing status below.
        $root = $destination->getRootItem();

        $allowed = FALSE;
        if (in_array('ownedbyuser', $allowedOwnership) === TRUE &&
            $destination->isOwnedBy($userId) === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('ownedbyanonymous', $allowedOwnership) === TRUE &&
            $destination->isOwnedBy($anonId) === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('ownedbyanother', $allowedOwnership) === TRUE &&
            $destination->isOwnedBy($userId) === FALSE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedbyuser', $allowedOwnership) === TRUE &&
            $root->isSharedBy($userId) === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithusertoview', $allowedOwnership) === TRUE &&
            $root->isSharedWith($userId, 'view') === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithusertoauthor', $allowedOwnership) === TRUE &&
            $root->isSharedWith($userId, 'author') === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithanonymoustoview', $allowedOwnership) === TRUE &&
            $root->isSharedWith($anonId, 'view') === TRUE) {
          $allowed = TRUE;
        }
        elseif (in_array('sharedwithanonymoustoauthor', $allowedOwnership) === TRUE &&
            $root->isSharedWith($anonId, 'author') === TRUE) {
          $allowed = TRUE;
        }
      }
      else {
        // There is no destination entity. The destination is a rootlist.
        // For purposes of destination ownership checking:
        // - USER_ROOT_LIST is owned by the current user.
        // - PUBLIC_ROOT_LIST is owned by anonymous.
        if ($destinationId === FolderShareInterface::USER_ROOT_LIST) {
          if (in_array('ownedbyuser', $allowedOwnership) === TRUE) {
            $allowed = TRUE;
          }
        }
        elseif ($destinationId === FolderShareInterface::PUBLIC_ROOT_LIST) {
          if (in_array('ownedbyanonymous', $allowedOwnership) === TRUE) {
            $allowed = TRUE;
          }
          elseif (in_array('sharedwithanonymoustoview', $allowedOwnership) === TRUE &&
              $anonymous->hasPermission(Constants::VIEW_PERMISSION) === TRUE) {
            $allowed = TRUE;
          }
          elseif (in_array('sharedwithanonymoustoauthor', $allowedOwnership) === TRUE &&
              $anonymous->hasPermission(Constants::AUTHOR_PERMISSION) === TRUE) {
            $allowed = TRUE;
          }
        }
      }

      if ($allowed === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'Destination item provided does not meet the "@command" command\'s ownership requirements.',
            [
              '@command' => $label,
            ]),
          t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      }
    }

    //
    // Check destination access
    // ------------------------
    // If there is a destination and access is required, check for access.
    // Commands that do not require a destination, or destination access, have
    // an access operation of 'none'.
    $allowedAccess = $def['destinationConstraints']['access'];
    if ($destination !== NULL) {
      $allowed = $destination->access($allowedAccess, $user, FALSE);
    }
    else {
      $summary = FolderShareAccessControlHandler::getRootAccessSummary($destinationId, $user);
      if (isset($summary[$allowedAccess]) === TRUE &&
          $summary[$allowedAccess] === TRUE) {
        $allowed = TRUE;
      }
      else {
        $allowed = FALSE;
      }
    }

    if ($allowed === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t('You do not have permission to complete this operation.'),
        t('The destination folder is restricted and cannot be accessed.')));
    }

    // Validated!
    $this->destinationValidated = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateParameters() {
    if ($this->parametersValidated === TRUE) {
      return;
    }

    if ($this->destinationValidated === FALSE) {
      $this->validateDestinationConstraints();
    }

    // Validated!
    $this->parametersValidated = TRUE;
  }

  /*--------------------------------------------------------------------
   *
   * Execute.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  abstract public function execute();

  /*---------------------------------------------------------------------
   *
   * Redirects.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasRedirect() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirect() {
    return NULL;
  }

  /*---------------------------------------------------------------------
   *
   * Configuration forms.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getDescription(bool $forPage) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(bool $forPage) {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitButtonName() {
    return t('OK');
  }

  /**
   * {@inheritdoc}
   */
  public function hasConfigurationForm() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $formState) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $formState) {
  }

  /*--------------------------------------------------------------------
   *
   * Debug.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns a string representation of the plugin.
   *
   * @return string
   *   A string representation of the plugin.
   */
  public function __toString() {
    $def = $this->getPluginDefinition();
    $s = 'FolderShareCommand: ' . $def['id'] . "\n";

    $s .= '  parent ID: "' . $this->getParentId() . "\"\n";
    $s .= '  destination ID: "' . $this->getDestinationId() . "\"\n";
    $s .= '  selection IDs: ';
    $ids = $this->getSelectionIds();
    if (empty($ids) === TRUE) {
      $s .= '(empty)';
    }
    else {
      foreach ($ids as $id) {
        $s .= ' "' . $id . '"';
      }
    }

    $s .= "\n";

    $s .= '  configuration keys: ';
    foreach (array_keys($this->configuration) as $key) {
      $s .= ' "' . $key . '"';
    }

    $s .= "\n";

    return $s;
  }

}
