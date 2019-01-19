<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\File;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\user\Entity\User;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;
use Drupal\foldershare\Plugin\FolderShareCommandManager;
use Drupal\foldershare\Plugin\FolderShareCommand\FolderShareCommandInterface;
use Drupal\foldershare\Ajax\OpenErrorDialogCommand;
use Drupal\foldershare\Entity\Exception\RuntimeExceptionWithMarkup;

/**
 * Creates a form for the file and folder table menu.
 *
 * This form manages a menu of commands (e.g. new, delete, copy) and their
 * operands. The available commands are defined by command plugins whose
 * attributes sort commands into categories and define the circumstances
 * under which a command may be envoked (e.g. for one item or many, on files,
 * on folders, etc.).
 *
 * This form includes:
 * - A field to specify the command.
 * - A field containing the current selection, if any.
 * - A set of fields with command operands, such as the parent and destination.
 * - A file field used to specify uploaded files.
 * - A submit button.
 *
 * This form is hidden and none of its fields are intended to be directly
 * set by a user. Instead, Javascript fills in the form based upon the
 * current row selection, the results of a drag-and-drop, or the file choices
 * from a browser-provided file dialog.
 *
 * Javascript creates a menu button and pull-down menu of commands.
 * Javascript also creates a context menu of commands for table rows.
 * Scripting handles row selection, multi-row selection, double-click to
 * open, drag-and-drop of rows, and drag-and-drop of files from the desktop
 * into the table for uploading.
 *
 * This form *requires* that a view nearby on the page contain a base UI
 * view field plugin that adds selection checkboxes and entity attributes
 * to all rows in the view.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 */
class UIFolderTableMenu extends FormBase {

  use RedirectDestinationTrait;

  /*--------------------------------------------------------------------
   *
   * Fields - cached from dependency injection.
   *
   *--------------------------------------------------------------------*/

  /**
   * The module handler, set at construction time.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The plugin command manager.
   *
   * @var \Drupal\foldershare\FolderShareCommand\FolderShareCommandManager
   */
  private $commandPluginManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private $formBuilder;

  /*--------------------------------------------------------------------
   *
   * Fields - set by menu choice.
   *
   *--------------------------------------------------------------------*/

  /**
   * The current validated command prior to its execution.
   *
   * The command already has its configuration set. It has been
   * validated, but has not yet had access controls checked and it
   * has not been executed.
   *
   * @var \Drupal\foldershare\FolderShareCommand\FolderShareCommandInterface
   */
  protected $command;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new form.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\foldershare\FolderShareCommand\FolderShareCommandManager $pm
   *   The command plugin manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    FolderShareCommandManager $pm,
    FormBuilderInterface $formBuilder) {

    $this->moduleHandler = $moduleHandler;
    $this->commandPluginManager = $pm;
    $this->formBuilder = $formBuilder;
    $this->command = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('foldershare.plugin.manager.foldersharecommand'),
      $container->get('form_builder')
    );
  }

  /*--------------------------------------------------------------------
   *
   * Command utilities.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns an array of well known menu command categories.
   *
   * For each entry, the key is the category machine name, and the value
   * is the translated name for the category.
   *
   * @return array
   *   Returns an associative array with category names as keys and
   *   translated strings as values.
   */
  private function getWellKnownMenuCategories() {
    return [
      'open'            => (string) $this->t('Open'),
      'import & export' => (string) $this->t('Import/Export'),
      'close'           => (string) $this->t('Close'),
      'edit'            => (string) $this->t('Edit'),
      'delete'          => (string) $this->t('Delete'),
      'copy & move'     => (string) $this->t('Copy/Move'),
      'save'            => (string) $this->t('Save'),
      'archive'         => (string) $this->t('Archive'),
      'message'         => (string) $this->t('Message'),
      'settings'        => (string) $this->t('Settings'),
      'administer'      => (string) $this->t('Administer'),
    ];
  }

  /**
   * Creates an instance of a command and prevalidates it.
   *
   * Prevalidation checks configuration fields that must be specified at
   * the time the command is invoked in these forms, prior to any additional
   * configuration changes made by a command's own forms. This includes:
   *
   * - Validate the command is allowed.
   * - Validate parent constraints.
   * - Validate selection constraints.
   *
   * The command's validation methods all throw exceptions. These are caught
   * here and converted to form error messages. The command is returned,
   * or NULL if the command could not be created.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   * @param string $commandClass
   *   The form state entry to which to report command errors.
   * @param string $commandId
   *   The command plugin ID.
   * @param array $configuration
   *   The initial command configuration.
   *
   * @return \Drupal\foldershare\Plugin\FolderShareCommand\FolderShareCommandInterface
   *   Returns an initialized command, or NULL if the command could not be
   *   created. If there were validation errors, form state has been updated.
   */
  private function prevalidateCommand(
    FormStateInterface $formState,
    string $commandClass,
    string $commandId,
    array $configuration) {

    //
    // Get the command
    // ---------------
    // Get the command plugin manager, find the definition, and create an
    // instance of the command.
    if ($this->commandPluginManager === NULL) {
      $formState->setErrorByName(
        $commandClass,
        Utilities::createFormattedMessage(
          $this->t('Missing FolderShare command plugin manager'),
          $this->t('This is a critical site configuration problem. Please report this to the site administrator.')));
      return NULL;
    }

    $commandDef = $this->commandPluginManager->getDefinition($commandId, FALSE);
    if ($commandDef === NULL) {
      $formState->setErrorByName(
        $commandClass,
        Utilities::createFormattedMessage(
          $this->t(
            'Unrecognized FolderShare command ID "@id".',
            [
              '@id' => $commandId,
            ]),
          $this->t('This is probably due to a programming error in the user interface. Please report this to the developers.')));
      return NULL;
    }

    // Create a command instance.
    $command = $this->commandPluginManager->createInstance(
      $commandDef['id'],
      $configuration);

    //
    // Prevalidate
    // -----------
    // Validate command allowed, parent constraints, and selection constraints.
    try {
      $command->validateCommandAllowed();
      $command->validateParentConstraints();
      $command->validateSelectionConstraints();
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $formState->setErrorByName(
        $commandClass,
        $e->getMarkup());
    }
    catch (\Exception $e) {
      $formState->setErrorByName(
        $commandClass,
        $e->getMessage());
    }

    return $command;
  }

  /*--------------------------------------------------------------------
   *
   * Form setup.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $name = str_replace('\\', '_', get_class($this));
    return mb_convert_case($name, MB_CASE_LOWER);
  }

  /*--------------------------------------------------------------------
   *
   * Form build.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $formState = NULL) {
    //
    // Get context attributes
    // ----------------------
    // Get attributes of the current context, including:
    // - the ID of the page entity, if any
    // - the page entity's kind.
    // - the user's access permissions.
    //
    // The page entity ID comes from the form build arguments.
    $args = $formState->getBuildInfo()['args'];

    if (empty($args) === TRUE) {
      $pageEntityId = FolderShareInterface::USER_ROOT_LIST;
    }
    else {
      $pageEntityId = intval($args[0]);
    }

    if ($pageEntityId < 0) {
      // Menu is for a root list page.
      $pageEntity = NULL;
      $kind       = 'rootlist';
      $disabled   = FALSE;

      $perm = FolderShareAccessControlHandler::getRootAccessSummary(
        $pageEntityId);
    }
    else {
      // Menu is for an entity page.
      $pageEntity = FolderShare::load($pageEntityId);
      if ($pageEntity === NULL) {
        // Invalid entity. Revert to root list.
        $kind         = 'rootlist';
        $disabled     = FALSE;
        $pageEntityId = FolderShareInterface::USER_ROOT_LIST;

        $perm = FolderShareAccessControlHandler::getRootAccessSummary(
          $pageEntityId);
      }
      else {
        $kind     = $pageEntity->getKind();
        $disabled = $pageEntity->isSystemDisabled();

        $perm = FolderShareAccessControlHandler::getAccessSummary($pageEntity);
      }
    }

    // Get the command list.
    if ($disabled === TRUE) {
      // No commands are allowed on a disabled entity.
      $commands = [];
    }
    else {
      $commands = Settings::getAllowedCommandDefinitions();
    }

    // Get the user.
    $user      = $this->currentUser();
    $userId    = (int) $user->id();
    $anonymous = User::getAnonymousUser();

    // Create an array that lists the names of access control operators
    // (e.g. "view", "update", "delete") if the user has permission for
    // those operators on this parent entity (if there is one).
    $access = [];
    foreach ($perm as $op => $allowed) {
      if ($allowed === TRUE) {
        $access[] = $op;
      }
    }

    //
    // Form setup
    // ----------
    // Add the form's ID as a class for styling.
    //
    // The 'drupalSettings' attribute defines arbitrary data that can be
    // attached to the page. Here we use it to:
    // - Flag whether AJAX is enabled.
    // - Give the ID and human-readable name of this module.
    // - Give translations for various terms.
    // - Give singular and plural translations of entity kinds.
    // - List all installed commands and their attributes.
    $kinds = [
      FolderShare::FOLDER_KIND,
      FolderShare::FILE_KIND,
      FolderShare::IMAGE_KIND,
      FolderShare::MEDIA_KIND,
      'rootlist',
      'item',
    ];

    $kindTerms = [];
    foreach ($kinds as $k) {
      $kindTerms[$k] = [
        'singular' => Utilities::translateKind($k, MB_CASE_UPPER),
        'plural'   => Utilities::translateKinds($k, MB_CASE_UPPER),
      ];
    }

    $categories = $this->getWellKnownMenuCategories();

    $isAnonymous = ($user->isAnonymous() === TRUE);
    $isAuthenticated = ($user->isAuthenticated() === TRUE);
    $hasAdmin = ($user->hasPermission(Constants::ADMINISTER_PERMISSION) === TRUE);
    $hasAuthor = ($user->hasPermission(Constants::AUTHOR_PERMISSION) === TRUE);
    $hasView = ($user->hasPermission(Constants::VIEW_PERMISSION) === TRUE);
    $hasShare = ($user->hasPermission(Constants::SHARE_PERMISSION) === TRUE);
    $hasSharePublic = ($user->hasPermission(Constants::SHARE_PUBLIC_PERMISSION) === TRUE);

    $extension                   = '';
    $ownerId                     = (-1);
    $ownedByUser                 = FALSE;
    $ownedByAnother              = FALSE;
    $ownedByAnonymous            = FALSE;
    $sharedByUser                = FALSE;
    $sharedWithUserToView        = FALSE;
    $sharedWithUserToAuthor      = FALSE;
    $sharedWithAnonymousToView   = FALSE;
    $sharedWithAnonymousToAuthor = FALSE;

    if ($pageEntity !== NULL) {
      $ownerId = $pageEntity->getOwnerId();
      $anonId = (int) $anonymous->id();

      if ($ownerId === $userId) {
        $ownedByUser = TRUE;
      }
      elseif ($ownerId === $anonId) {
        $ownedByAnonymous = TRUE;
      }
      else {
        $ownedByAnother = TRUE;
      }

      $extension = $pageEntity->getExtension();

      // Since access grants are set on the root only, get the root and
      // use it to determine the sharing state.
      $root = $pageEntity->getRootItem();

      $sharedByUser                = $root->isSharedBy($userId);
      $sharedWithUserToView        = $root->isSharedWith($userId, 'view');
      $sharedWithUserToAuthor      = $root->isSharedWith($userId, 'author');
      $sharedWithAnonymousToView   = $root->isSharedWith($anonId, 'view');
      $sharedWithAnonymousToAuthor = $root->isSharedWith($anonId, 'author');
    }
    elseif ($pageEntityId === FolderShareInterface::USER_ROOT_LIST) {
      $ownerId = $user->id();
      $ownedByUser = TRUE;
    }
    elseif ($pageEntityId === FolderShareInterface::PUBLIC_ROOT_LIST) {
      $ownerId = $anonymous->id();
      $ownedByAnonymous = TRUE;
      $sharedWithAnonymousToView = $anonymous->hasPermission(
        Constants::VIEW_PERMISSION);
      $sharedWithAnonymousToAuthor = $anonymous->hasPermission(
        Constants::AUTHOR_PERMISSION);
    }

    $form['#attached']['drupalSettings']['foldershare'] = [
      'ajaxEnabled'   => Constants::ENABLE_UI_COMMAND_DIALOGS,
      'module'        => [
        'id'          => Constants::MODULE,
        'title'       => $this->moduleHandler->getName(Constants::MODULE),
      ],
      'page'          => [
        'id'          => $pageEntityId,
        'kind'        => $kind,
        'disabled'    => $disabled,
        'extension'   => $extension,
        'ownerid'     => $ownerId,
        'ownedbyuser'                 => $ownedByUser,
        'ownedbyanonymous'            => $ownedByAnonymous,
        'ownedbyanother'              => $ownedByAnother,
        'sharedbyuser'                => $sharedByUser,
        'sharedwithusertoview'        => $sharedWithUserToView,
        'sharedwithusertoauthor'      => $sharedWithUserToAuthor,
        'sharedwithanonymoustoview'   => $sharedWithAnonymousToView,
        'sharedwithanonymoustoauthor' => $sharedWithAnonymousToAuthor,
      ],
      'user'          => [
        'id'          => $user->id(),
        'accountName' => $user->getAccountName(),
        'displayName' => $user->getDisplayName(),
        'pageAccess'  => $access,
        'anonymous'             => $isAnonymous,
        'authenticated'         => $isAuthenticated,
        'adminpermission'       => $hasAdmin,
        'noadminpermission'     => $hasAdmin !== TRUE,
        'authorpermission'      => $hasAuthor,
        'sharepermission'       => $hasShare,
        'sharepublicpermission' => $hasSharePublic,
        'viewpermission'        => $hasView,
      ],
      'terminology'   => [
        'kinds'       => $kindTerms,
        'text'        => [
          'this'      => $this->t('this'),
          'menu'      => $this->t('menu'),
          'upload_dnd_not_supported' => (string) $this->t(
            "<p><strong>Drag-and-drop file upload is not supported.</strong></p><p>This feature is not supported by this web browser.</p>"),
          'upload_dnd_invalid_singular' => (string) $this->t(
            "<p><strong>Drag-and-drop item cannot be uploaded.</strong></p><p>You may not have access to the item, or it may be a folder. Folder upload is not supported.</p>"),
          'upload_dnd_invalid_plural' => (string) $this->t(
            "<p><strong>Drag-and-drop items cannot be uploaded.</strong></p><p>You may not have access to these items, or one of them may be a folder. Folder upload is not supported.</p>"),
        ],
        'categories'  => $categories,
      ],
      'categories'    => array_keys($categories),
      'commands'      => $commands,
    ];

    //
    // Create UI
    // ---------
    // The UI is primarily built by Javascript based upon the command list
    // and other information in the settings attached to the form above.
    // The remainder of the form contains input fields and a submit button
    // for sending a command's ID and operands back to the server.
    //
    // Set up classes.
    $uiClass            = 'foldershare-folder-table-menu';
    $submitClass        = $uiClass . '-submit';
    $uploadClass        = $uiClass . '-upload';
    $commandClass       = $uiClass . '-commandname';
    $selectionClass     = $uiClass . '-selection';
    $parentIdClass      = $uiClass . '-parentId';
    $destinationIdClass = $uiClass . '-destinationId';

    // When AJAX is enabled, add an AJAX callback to the submit button.
    $submitAjax = '';
    if (Constants::ENABLE_UI_COMMAND_DIALOGS === TRUE) {
      $submitAjax = [
        'callback' => '::submitFormAjax',
        'event'    => 'submit',
      ];
    }

    $form['#attributes']['class'][] = 'foldershare-folder-table-menu-form';

    $form[$uiClass] = [
      '#type'              => 'container',
      '#weight'            => -100,
      '#attributes'        => [
        'class'            => [$uiClass],
      ],

      // The form acts as a container for Javascript-generated menus and
      // menu buttons. Those items need to be visible, but the form's
      // inputs for sending a command back to the server never need to
      // be visible. These are grouped into a container that is marked hidden.
      'hiddenGroup'        => [
        '#type'            => 'container',
        '#attributes'      => [
          'class'          => ['hidden'],
        ],

        // Add the command plugin ID field to indicate the selected command.
        // Later, when a user selects a command, Javascript sets this field
        // to the command plugin's unique ID.
        //
        // The command is a required input. There's no point in submitting
        // a form without one.
        //
        // Implementation note: There is no documented maximum plugin ID
        // length, but most Drupal IDs are limited to 256 characters. So
        // we use that limit.
        $commandClass        => [
          '#type'            => 'textfield',
          '#maxlength'       => 256,
          '#size'            => 1,
          '#default_value'   => '',
          '#required'        => TRUE,
        ],

        // Add the selection field that lists the IDs of selected entities.
        // Later, when a user selects a command, Javascript sets this field
        // to a list of entity IDs for zero or more items currently selected
        // from the view.
        //
        // The selection is optional. Some commands have no selection (such
        // as "New folder" and "Upload files").
        //
        // Implementation note: The textfield will be set with a JSON-encoded
        // string containing the list of numeric entity IDs for selected
        // entities in the view. There is no maximum view length if the site
        // disables paging. Drupal's default field maximum is 128 characters,
        // which is probably sufficient, but it is conceivable it could be
        // exceeded for a large selection. The HTML default maximum is
        // 524288 characters, so we use that.
        $selectionClass      => [
          '#type'            => 'textfield',
          '#maxlength'       => 524288,
          '#size'            => 1,
          '#default_value'   => $formState->getValue($selectionClass),
        ],

        // Add the parent field that gives the parent ID of a file or folder.
        //
        // The parent ID is not required, though Javascript always sets it.
        // If not set, the entity ID of the current page is used. This is
        // typical for most command use. Drag-and-drop operations, however,
        // may move/copy/upload items into a selected subfolder, and that
        // subfolder's parent ID is set in this field.
        //
        // Implementation note: The textfield may be left empty or set to
        // a single numeric entity ID. Since entity IDs are 64 bits, the
        // maximum number of characters here is 20 digits.
        $parentIdClass    => [
          '#type'            => 'textfield',
          '#maxlength'       => 24,
          '#size'            => 1,
          '#default_value'   => $formState->getValue($parentIdClass),
        ],

        // Add the destination field that gives the entity ID of a
        // destination folder for move/copy operations. Later, when a user
        // selects a command, Javascript may set this field if the destination
        // is known. If the field is left empty, the command may prompt for
        // the move/copy destination.
        //
        // The destination ID field is optional. Most commands don't use it.
        //
        // Implementation note: The textfield may be left empty or set to
        // a single numeric entity ID. Since entity IDs are 64 bits, the
        // maximum number of characters here is 20 digits.
        $destinationIdClass    => [
          '#type'            => 'textfield',
          '#maxlength'       => 24,
          '#size'            => 1,
          '#default_value'   => $formState->getValue($destinationIdClass),
        ],

        // Add the file field for uploading files. Later, when a user
        // selects a command that needs to upload a file, Javascript invokes
        // the browser's file dialog to set this field.
        //
        // The field needs to have a processing callback to set up file
        // extension filtering, if file extension limitations are enabled
        // for the module.
        //
        // The upload field is optional and it is only used by file upload
        // commands.
        $uploadClass         => [
          '#type'            => 'file',
          '#multiple'        => TRUE,
          '#process'         => [
            [
              get_class($this),
              'processFileField',
            ],
          ],
        ],

        // Add the submit button for the form. Javascript triggers the
        // submit when a command is selected from the menu.
        $submitClass         => [
          '#type'            => 'submit',
          '#value'           => '',
          '#name'            => $submitClass,
          '#ajax'            => $submitAjax,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Process the file field in the view UI form to add extension handling.
   *
   * The 'file' field directs the browser to prompt the user for one or
   * more files to upload. This prompt is done using the browser's own
   * file dialog. When this module's list of allowed file extensions has
   * been set, and this function is added as a processing function for
   * the 'file' field, it adds the extensions to the list of allowed
   * values used by the browser's file dialog.
   *
   * @param mixed $element
   *   The form element to process.
   * @param Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   * @param mixed $completeForm
   *   The full form.
   */
  public static function processFileField(
    &$element,
    FormStateInterface $formState,
    &$completeForm) {

    // Let the file field handle the '#multiple' flag, etc.
    File::processFile($element, $formState, $completeForm);

    // Get the list of allowed file extensions for FolderShare files.
    $extensions = FolderShare::getAllowedNameExtensions();

    // If there are extensions, add them to the form element.
    if (empty($extensions) === FALSE) {
      // The extensions list is space separated without leading dots. But
      // we need comma separated with dots. Map one to the other.
      $list = [];
      foreach (mb_split(' ', $extensions) as $ext) {
        $list[] = '.' . $ext;
      }

      $element['#attributes']['accept'] = implode(',', $list);
    }

    return $element;
  }

  /*--------------------------------------------------------------------
   *
   * Form validate.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState) {
    //
    // Setup
    // -----
    // Set up classes.
    $uiClass            = 'foldershare-folder-table-menu';
    $commandClass       = $uiClass . '-commandname';
    $selectionClass     = $uiClass . '-selection';
    $parentIdClass      = $uiClass . '-parentId';
    $destinationIdClass = $uiClass . '-destinationId';
    $uploadClass        = $uiClass . '-upload';

    //
    // Get parent ID (if any)
    // ----------------------
    // The parent entity ID, if present, is the sole URL argument.
    $args = $formState->getBuildInfo()['args'];
    if (empty($args) === TRUE) {
      $parentId = FolderShareInterface::USER_ROOT_LIST;
    }
    else {
      $parentId = intval($args[0]);
      // The parent ID may still be negative, indicating a root list.
    }

    // If a parent ID is in the form, it overrides the URL, which describes
    // the page the operation began on, and not necessary the context in
    // which it should take place.
    $formParentId = $formState->getValue($parentIdClass);
    if (empty($formParentId) === FALSE) {
      $parentId = intval($formParentId);
      // The parent ID may still be negative, indicating a root list.
    }

    //
    // Get command
    // -----------
    // The command's plugin ID is set in the command field. Get it and
    // the command definition.
    $commandId = $formState->getValue($commandClass);
    if (empty($commandId) === TRUE) {
      // Fail. This should never happen. The field is required, so the form
      // should not be submittable without a command.
      $formState->setErrorByName(
        $commandClass,
        $this->t('Please select a command from the menu.'));
      return;
    }

    //
    // Get selection (if any)
    // ----------------------
    // The selection field contains a JSON encoded array of entity IDs
    // in the selection. The list could be empty.
    $selectionIds = json_decode($formState->getValue($selectionClass), TRUE);

    //
    // Get destination (if any)
    // ------------------------
    // The destination field contains a single numeric entity ID for the
    // destination of a move/copy. The value could be empty.
    $destinationId = $formState->getValue($destinationIdClass);
    if (empty($destinationId) === TRUE) {
      $destinationId = FolderShareCommandInterface::EMPTY_ITEM_ID;
    }
    else {
      $destinationId = intval($destinationId);
    }

    //
    // Create configuration
    // --------------------
    // Create an initial command configuration.
    $configuration = [
      'parentId'      => $parentId,
      'selectionIds'  => $selectionIds,
      'destinationId' => $destinationId,
      'uploadClass'   => $uploadClass,
    ];

    //
    // Prevalidate
    // -----------
    // Create a command instance. Prevalidate and add errors to form state.
    // If the command could not be created, a NULL is returned and a message
    // is already in form state.
    $command = $this->prevalidateCommand(
      $formState,
      $commandClass,
      $commandId,
      $configuration);

    if ($command !== NULL) {
      $this->command = $command;
    }
  }

  /*--------------------------------------------------------------------
   *
   * Form submit (no-AJAX).
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    if (Constants::ENABLE_UI_COMMAND_DIALOGS === TRUE) {
      // AJAX is in use. Let the AJAX submit method handle it.
      return;
    }

    if ($this->command === NULL) {
      return;
    }

    //
    // Redirect to another page.
    // -------------------------
    // If the command needs to redirect to a special page (such as a large
    // entity edit form), go there. That form's submit handler will do the
    // rest.
    if ($this->command->hasRedirect() === TRUE) {
      $url = $this->command->getRedirect();
      if (empty($url) === TRUE) {
        $url = Url::fromRoute('<current>');
      }

      $formState->setRedirectUrl($url);
      return;
    }

    //
    // Redirect to command's form.
    // ---------------------------
    // If the command has its own configuration form, redirect to a page that
    // hosts the form.
    try {
      if ($this->command->hasConfigurationForm() === TRUE) {
        $parameters = [
          'pluginId'      => $this->command->getPluginId(),
          'configuration' => $this->command->getConfiguration(),
          'url'           => $this->getRequest()->getUri(),
          'enableAjax'    => Constants::ENABLE_UI_COMMAND_DIALOGS,
        ];

        $formState->setRedirect(
          'entity.foldersharecommand.plugin',
          [
            'encoded' => base64_encode(json_encode($parameters)),
          ]);
        return;
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), 'error');
      return;
    }

    //
    // Execute.
    // --------
    // The command doesn't need more operands. Validate & execute.
    try {
      $this->command->validateConfiguration();
      $this->command->execute();
    }
    catch (AccessDeniedHttpException $e) {
      $this->messenger()->addMessage(Utilities::createFormattedMessage(
        $this->t('You do not have sufficient permissions.'),
        $this->t('The operation could not be completed.')),
        'error');
    }
    catch (NotFoundHttpException $e) {
      $this->messenger()->addMessage(Utilities::createFormattedMessage(
        $this->t('One or more items could not be found.'),
        $this->t('The operation could not be completed.')),
        'error');
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $this->messenger()->addMessage($e->getMarkup(), 'error');
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }

    $this->command = NULL;
  }

  /*--------------------------------------------------------------------
   *
   * Form submit (AJAX)
   *
   *--------------------------------------------------------------------*/

  /**
   * Handles form submission via AJAX.
   *
   * If the selected command has no configuration form, the command is
   * executed immediately. Any errors are reported in a modal error dialog.
   *
   * If the selected command requires a redirect to a full-page form, the
   * redirect is executed immediately.
   *
   * Otherwise, when the selected command has a configuration form, the
   * form is built and added to a modal dialog.
   *
   * @param array $form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The input values for the form's elements.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns an AJAX response.
   */
  public function submitFormAjax(array &$form, FormStateInterface $formState) {
    if ($this->command === NULL) {
      // Errors that would cause this are catastrophic and will float to
      // the page's messages area.
      $response = new AjaxResponse();
      $url = Url::fromRoute('<current>');
      $response->addCommand(new RedirectCommand($url->toString()));
      return $response;
    }

    //
    // Report form errors.
    // -------------------
    // If prevalidation failed, there will be form errors to report.
    if ($formState->hasAnyErrors() === TRUE) {
      $response = new AjaxResponse();
      $dialog = new OpenErrorDialogCommand('');
      $dialog->setFromFormErrors($formState);
      $response->addCommand($dialog);
      return $response;
    }

    //
    // Redirect to another page.
    // -------------------------
    // If the command needs to redirect to a special page (such as a large
    // entity edit form), go there. That form's submit handler will do the
    // rest.
    if ($this->command->hasRedirect() === TRUE) {
      $url = $this->command->getRedirect();
      if (empty($url) === TRUE) {
        $url = Url::fromRoute('<current>');
      }

      $response = new AjaxResponse();
      $response->addCommand(new RedirectCommand($url->toString()));
      return $response;
    }

    //
    // Embed command's form in dialog.
    // -------------------------------
    // If the command has its own configuration form, add that form to a
    // modal dialog.
    if ($this->command->hasConfigurationForm() === TRUE) {
      $parameters = [
        'pluginId'      => $this->command->getPluginId(),
        'configuration' => $this->command->getConfiguration(),
        'enableAjax'    => Constants::ENABLE_UI_COMMAND_DIALOGS,
      ];

      // The request's URL needs to be sent to the command as a page to
      // return to. Unfortunately, because we are in an AJAX submit form,
      // there are AJAX query parameters on the URL. We won't want them
      // when returning to the page, so strip them off.
      $request = $this->getRequest();
      $urlParts = parse_url($request->getUri());
      if (empty($urlParts['query']) === FALSE) {
        // Parse the query part of the URL.
        $queryArguments = [];
        parse_str($urlParts['query'], $queryArguments);
        $q = [];

        // Ignore AJAX query arguments.
        foreach ($queryArguments as $key => $value) {
          if ($key !== '_wrapper_format' && $key !== 'ajax_form') {
            $q[$key] = $value;
          }
        }

        $urlParts['query'] = http_build_query($q);
      }

      // And reassemble the URL.
      $url = '';
      if (empty($urlParts['scheme']) === FALSE) {
        $url .= $urlParts['scheme'] . ':';
      }

      if (empty($urlParts['host']) === FALSE) {
        $url .= '//' . $urlParts['host'];
        if (empty($urlParts['port']) === FALSE) {
          $url .= ':' . $urlParts['port'];
        }
      }

      $url .= $urlParts['path'];
      if (empty($urlParts['fragment']) === FALSE) {
        $url .= '?' . $urlParts['fragment'];
      }

      if (empty($urlParts['query']) === FALSE) {
        $url .= '?' . $urlParts['query'];
      }

      $parameters['url'] = $url;

      // Encode for inclusion as a route parameter.
      $encoded = base64_encode(json_encode($parameters));

      // Build a form for the command. The returned form may prompts, buttons,
      // attachments, etc.
      $form = $this->formBuilder->getForm(
        CommandFormWrapper::class,
        $encoded);
      if ($form === NULL) {
        // Form build failed, probably due to validation errors.
        $response = new AjaxResponse();
        $cmd = new OpenErrorDialogCommand('');
        $cmd->setFromPageMessages();
        $response->addCommand($cmd);
        return $response;
      }

      if (empty($form['#title']) === TRUE) {
        $title = $this->command->getPluginDefinition()['label'];
      }
      else {
        $title = (string) $form['#title'];
      }

      // Return the form within a modal dialog.
      $response = new AjaxResponse();
      $response->setAttachments($form['#attached']);
      $response->addCommand(new OpenModalDialogCommand(
        $title,
        $form,
        [
          'modal'             => TRUE,
          'draggable'         => FALSE,
          'resizable'         => FALSE,
          'refreshAfterClose' => TRUE,
          'closeOnEscape'     => TRUE,
          'closeText'         => $this->t('Cancel and close'),
          'width'             => 'auto',
          'minWidth'          => 'auto',
          'height'            => 'auto',
          'minHeight'         => 'auto',
          'maxHeight'         => 'auto',
          'classes'           => [
            'ui-dialog'       => ['foldershare-ui-dialog'],
          ],
        ]));
      return $response;
    }

    //
    // Execute.
    // --------
    // The command doesn't need more operands. Validate & execute.
    $response = new AjaxResponse();

    try {
      $this->command->validateConfiguration();
      $this->command->execute();
    }
    catch (AccessDeniedHttpException $e) {
      $this->messenger()->addMessage(Utilities::createFormattedMessage(
        $this->t('You do not have sufficient permissions.'),
        $this->t('The operation could not be completed.')),
        'error');
    }
    catch (NotFoundHttpException $e) {
      $this->messenger()->addMessage(Utilities::createFormattedMessage(
        $this->t('One or more items could not be found.'),
        $this->t('The operation could not be completed.')),
        'error');
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $this->messenger()->addMessage($e->getMarkup(), 'error');
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }

    // If there are any errors, present them in an error dialog.
    $msgs = $this->messenger()->all();
    if (isset($msgs['error']) === TRUE ||
        isset($msgs['warning']) === TRUE) {
      $cmd = new OpenErrorDialogCommand('');
      $cmd->setFromPageMessages();
      $response->addCommand($cmd);
    }
    elseif ($formState->hasAnyErrors() === TRUE) {
      $cmd = new OpenErrorDialogCommand('');
      $cmd->setFromFormErrors($formState);
      $response->addCommand($cmd);
    }
    else {
      // Otherwise the command executed correctly. Just refresh the page.
      $url = $this->command->getRedirect();
      if ($url !== NULL) {
        $response->addCommand(new RedirectCommand($url));
      }
      else {
        $url = Url::fromRoute('<current>');
        $response->addCommand(new RedirectCommand($url->toString()));
      }
    }

    $this->command = NULL;

    return $response;
  }

}
