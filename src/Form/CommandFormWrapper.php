<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\RedirectCommand;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Plugin\FolderShareCommandManager;
use Drupal\foldershare\Ajax\OpenErrorDialogCommand;
use Drupal\foldershare\Entity\Exception\RuntimeExceptionWithMarkup;

/**
 * Creates a form that wraps a command plugin to prompt for its parameters.
 *
 * This form is invoked to prompt the user for additional plugin
 * command-specific parameters, beyond primary parameters for the parent
 * folder (if any), destination folder (if any), and selection (if any).
 * Each plugin has its own (optional) form fragment that prompts for its
 * parameters. This form page incorporates that fragment and adds a
 * page title and submit button. It further orchestrates creation of
 * the plugin and invokation of its functions.
 *
 * The form page accepts a URL with a single required 'encoded' parameter
 * that includes a base64-encoded JSON-encoded associative array that
 * has the plugin ID, configuration (parent, destination, selection),
 * and URL to get back to the invoking page.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 */
class CommandFormWrapper extends FormBase {

  /*--------------------------------------------------------------------
   *
   * Fields - cached from dependency injection.
   *
   *--------------------------------------------------------------------*/

  /**
   * The plugin manager for folder shared commands.
   *
   * @var \Drupal\foldershare\FolderShareCommand\FolderShareCommandManager
   */
  protected $commandPluginManager;

  /**
   * The command's plugin ID from URL parameters.
   *
   * The plugin ID is a unique string that identifies the plugin within
   * the set of loaded plugins in the plugin manager.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * An instance of the command plugin.
   *
   * The command instance is created using the plugin ID from the
   * URL parameters.
   *
   * @var \Drupal\foldershare\FolderShareCommand\FolderShareCommandInterface
   */
  protected $command;

  /**
   * The URL to redirect to (or back to) after form submission.
   *
   * The URL comes from the URL parameters and indicates the original
   * page to return to after the command executes.
   *
   * @var string
   */
  protected $url;

  /**
   * Whether to enable AJAX.
   *
   * The flag comes from the command parameters given to buildForm().
   * By default, this is FALSE, but it can be set TRUE if the caller
   * sets the flag in the parameters. This would be the case if the
   * caller has embedded the form within an AJAX dialog and therefore
   * requires that this form continue to use AJAX as well.
   *
   * @var bool
   */
  protected $enableAjax;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new form.
   *
   * @param \Drupal\foldershare\FolderShareCommand\FolderShareCommandManager $commandPluginManager
   *   The command plugin manager.
   */
  public function __construct(FolderShareCommandManager $commandPluginManager) {
    // Save the plugin manager, which we'll need to create a plugin
    // instance based upon a parameter on the form's URL.
    $this->commandPluginManager = $commandPluginManager;
    $this->enableAjax = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('foldershare.plugin.manager.foldersharecommand')
    );
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
    return str_replace('\\', '_', get_class($this));
  }

  /*--------------------------------------------------------------------
   *
   * Form build.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $formState = NULL,
    string $encoded = NULL) {

    //
    // Decode parameters.
    // ------------------
    // Decode the incoming URL's parameters, which include:
    // - The command plugin's ID.
    // - The configuration for that command, including a selection, parent,
    //   destination, etc.
    // - The URL of the page to return to.
    // - Whethe AJAX is enabled.
    $parameters = $this->decodeParameters($encoded);
    if (empty($parameters) === TRUE) {
      return $form;
    }

    $this->pluginId = $parameters['pluginId'];
    $configuration  = $parameters['configuration'];
    $this->url      = $parameters['url'];
    if (isset($parameters['enableAjax']) === TRUE) {
      $this->enableAjax = $parameters['enableAjax'];
    }
    else {
      $this->enableAjax = FALSE;
    }

    $forPage = ($this->enableAjax === FALSE);

    //
    // Instantiate plugin.
    // -------------------
    // The plugin ID gives us the name of the plugin. Create one and pass
    // in the configuration. This initializes the plugin's internal
    // structure, but does not validate or execute it. The plugin's form
    // below may prompt the user for additional configuration values.
    $this->command = $this->commandPluginManager->createInstance(
      $this->pluginId,
      $configuration);
    $def = $this->command->getPluginDefinition();

    //
    // Pre-validate.
    // -------------
    // The invoker of this form should already have checked that the
    // parent and selection are valid for this command. But validate
    // again now to insure that internal cached values from validation
    // are set before we continue. If validation fails, there is no
    // point in building much of a form.
    try {
      $this->command->validateParentConstraints();
      $this->command->validateSelectionConstraints();
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $this->messenger()->addMessage($e->getMarkup(), 'error');
      return NULL;
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), 'error');
      return NULL;
    }

    //
    // Set up form.
    // ------------
    // The command provides its own form fragment to prompt for the
    // values it needs to complete its configuration.
    //
    // Attach libraries.
    $form['#attached']['library'][] = Constants::LIBRARY_MODULE;
    $form['#attributes']['class'][] = $def['id'];
    $form['#attributes']['class'][] = 'foldershare-dialog';

    // Mark form as tree-structured and non-collapsable.
    $form['#tree'] = TRUE;

    //
    // Set the page title, if any.
    // ---------------------------
    // Get the command's page title. This may vary based on whether the
    // form is in a stand-alone page or in a dialog.
    $title = $this->command->getTitle($forPage);
    if (empty($title) === FALSE) {
      $form['#title'] = $title;
    }

    //
    // Set the description, if any.
    // ----------------------------
    // Get the command's description. This may vary based on whether the
    // form is in a stand-alone page or in a dialog.
    $description = $this->command->getDescription($forPage);
    if (empty($description) === FALSE) {
      $form['description'] = [
        '#type'   => 'html_tag',
        '#name'   => 'description',
        '#weight' => 0,
        '#tag'    => 'p',
        '#value'  => $description,
      ];
    }

    //
    // Add actions.
    // ------------
    // Add the submit button.
    $form['actions'] = [
      '#type'          => 'actions',
      '#weight'        => 1000,
      'submit'         => [
        '#type'        => 'submit',
        '#name'        => 'submit',
        '#value'       => $this->command->getSubmitButtonName(),
        '#button_type' => 'primary',
      ],
    ];

    // When AJAX is enabled, add an AJAX callback to the submit button
    // and a cancel button.
    if ($this->enableAjax === TRUE) {
      $form['actions']['cancel'] = [
        '#type'  => 'button',
        '#name'  => 'cancel',
        '#value' => $this->t('Cancel'),
      ];

      $form['actions']['submit']['#ajax'] = [
        'callback'   => [$this, 'submitFormAjax'],
        'event'      => 'click',
        'url'        => Url::fromRoute(
          'entity.foldersharecommand.plugin',
          [
            'encoded' => base64_encode(json_encode($parameters)),
          ]),
        'options'    => [
          'query'    => [
            'ajax_form' => 1,
          ],
        ],
        'progress'   => [
          'type'     => 'throbber',
        ],
      ];
    }

    //
    // Add command form.
    // -----------------
    // Add the command's form.
    $form = $this->command->buildConfigurationForm($form, $formState);

    return $form;
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
    // Validate the user's input for the form by passing them to the
    // command to validate.
    if ($this->command === NULL) {
      // Fail. No command?
      return;
    }

    //
    // Validate additions
    // ------------------
    // Let the command's form validate the user's input.
    $this->command->validateConfigurationForm($form, $formState);
    if ($formState->hasAnyErrors() === TRUE) {
      // The command's validation failed. There is no point in continuing.
      return;
    }

    if ($formState->isRebuilding() === TRUE) {
      // The command directs that the form should be rebuilt and form use
      // should continue.
      return;
    }

    //
    // Validate rest
    // -------------
    // The command's configuration form should have validated whatever
    // additional parameters it has added. And earlier we validated the
    // parent and selection. But just to be sure, validate everything
    // one last time. Since validation skips work if it has already
    // been done, this will be fast if everything is validated fine already.
    try {
      $this->command->validateConfiguration();
    }
    catch (\Exception $e) {
      // Validation failed. This should not be possible if all prior
      // validation checks occurred as they were supposed to.
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /*--------------------------------------------------------------------
   *
   * Form submit.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    if ($this->enableAjax === TRUE) {
      return;
    }

    switch ($formState->getTriggeringElement()['#name']) {
      case 'cancel':
        // On cancel, return to the starting page.
        if ($this->url !== NULL) {
          $formState->setRedirectUrl(Url::fromUri($this->url));
        }
        break;

      case 'submit':
        // On submit, execute command, then return to the starting page.
        if ($this->command !== NULL) {
          $this->command->submitConfigurationForm($form, $formState);
        }

        if ($this->url !== NULL) {
          $formState->setRedirectUrl(Url::fromUri($this->url));
        }
        break;

      default:
        // On any unrecognized trigger, do nothing.
        // TODO This won't work. How does page get updated with
        // new destination list?
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormAjax(array &$form, FormStateInterface $formState) {

    $response = new AjaxResponse();

    switch ($formState->getTriggeringElement()['#name']) {
      case 'cancel':
        // On cancel, close the dialog and return to the starting page.
        $response->addCommand(new CloseModalDialogCommand());
        return $response;

      case 'submit':
        // On submit, execute command, then close dialog and return
        // to the starting page.
        if ($this->command === NULL) {
          // This should not be possible.
          $response->addCommand(new CloseModalDialogCommand());
          return $response;
        }
        break;

      default:
        // On any unrecognized trigger, update the entire form.
        // TODO How to make this generic?
        $response->addCommand(
          new ReplaceCommand(
            '#foldershare-refresh',
            $form['foldershare-folder-selection']));
        return $response;
    }

    //
    // Execute.
    // --------
    // The command doesn't need more operands. Validate, check permissions,
    // then execute. Failures either set form element errors or set page
    // errors. Pull out those errors and report them.
    $response = new AjaxResponse();
    try {
      $this->command->validateConfiguration();
      if ($this->command->isValidated() === TRUE) {
        $this->command->submitConfigurationForm($form, $formState);
        $this->command = NULL;
      }
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
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), 'error');
    }

    $response->addCommand(new CloseModalDialogCommand());

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
      if ($this->url !== NULL) {
        $response->addCommand(new RedirectCommand($this->url));
      }
      else {
        $url = Url::fromRoute('<current>');
        $response->addCommand(new RedirectCommand($url->toString()));
      }
    }

    return $response;
  }

  /*--------------------------------------------------------------------
   *
   * Utilities.
   *
   *--------------------------------------------------------------------*/

  /**
   * Decodes plugin information passed to the form via its URL.
   *
   * @param string $encoded
   *   The base64 encoded string included as a parameter on the form URL.
   */
  private function decodeParameters(string $encoded) {
    //
    // The incoming parameters are an encoded associative array that provides
    // the plugin ID, configuration, and redirect URL. Encoding has expressed
    // the array as a base64 string so that it could be included as a
    // URL parameter.  Decoding reverses the base64 encode, and a JSON decode
    // to return an object, which we cast to an array as needed.
    //
    // Decode parameters.
    $parameters = (array) json_decode(base64_decode($encoded), TRUE);

    if (empty($parameters) === TRUE) {
      // Fail. No parameters?
      //
      // This should not be possible because the route requires that there
      // be parameters. At a minimum, we need the command plugin ID.
      $this->messenger()->addMessage($this->t(
        "Communications problem.\nThe '@operation' command could not be completed because it is missing one or more required parameters.",
        [
          '@operation' => $this->command->label(),
        ]),
        'error');
      return [];
    }

    // Get plugin ID.
    if (empty($parameters['pluginId']) === TRUE) {
      // Fail. No plugin ID?
      //
      // The parameters are malformed and missing the essential plugin ID.
      $this->messenger()->addMessage($this->t(
        "Communications problem.\nThe '@operation' command could not be completed because it is missing one or more required parameters.",
        [
          '@operation' => $this->command->label(),
        ]),
        'error');
      return [];
    }

    // Get initial configuration.
    if (isset($parameters['configuration']) === FALSE) {
      // Fail. No configuration?
      //
      // The parameters are malformed and missing the essential configuration.
      $this->messenger()->addMessage($this->t(
        "Communications problem.\nThe '@operation' command could not be completed because it is missing one or more required parameters.",
        [
          '@operation' => $this->command->label(),
        ]),
        'error');
      return [];
    }

    // Insure the configuration is an array.
    $parameters['configuration'] = (array) $parameters['configuration'];

    return $parameters;
  }

}
