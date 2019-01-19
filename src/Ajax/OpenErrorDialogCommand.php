<?php

namespace Drupal\foldershare\Ajax;

use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;

use Drupal\foldershare\Constants;

/**
 * Defines an AJAX command to open a dialog and display error messages.
 *
 * This specialized AJAX dialog command creates a modal dialog with an
 * OK button to display a list of error messages. The dialog has a title,
 * an opening message, and a body that lists error messages. For a long
 * list, the body may include scrollbars.
 *
 * @ingroup foldershare
 */
class OpenErrorDialogCommand extends OpenModalDialogCommand {

  /*--------------------------------------------------------------------
   *
   * Construct.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs an OpenDialogCommand object.
   *
   * @param string $title
   *   The title of the dialog.
   * @param string|array $content
   *   The content that will be placed in the dialog, either a render array
   *   or an HTML string.
   * @param array $dialogOptions
   *   (optional) Options to be passed to the dialog implementation. Any
   *   jQuery UI option can be used. See http://api.jqueryui.com/dialog.
   * @param array|null $settings
   *   (optional) Custom settings that will be passed to the Drupal behaviors
   *   on the content of the dialog. If left empty, the settings will be
   *   populated automatically from the current request.
   */
  public function __construct(
    string $title,
    $content = '',
    array $dialogOptions = [],
    $settings = NULL) {

    if (empty($dialogOptions) === TRUE) {
      $dialogOptions = [
        'modal'             => TRUE,
        'draggable'         => FALSE,
        'resizable'         => FALSE,
        'refreshAfterClose' => TRUE,
        'closeOnEscape'     => TRUE,
        'closeText'         => t('Close'),
        'width'             => 'auto',
        'minWidth'          => 'auto',
        'height'            => 'auto',
        'minHeight'         => 'auto',
        'classes'           => [
          'ui-dialog'       => ['foldershare-ui-dialog'],
        ],
      ];
    }

    $body = [
      '#attached'        => [
        'library'        => [
          Constants::LIBRARY_MODULE,
        ],
      ],
      '#attributes'      => [
        'class'          => [
          'foldershare-dialog',
        ],
      ],
      '#tree'            => TRUE,

      'messages'         => [
        '#type'          => 'container',
        '#weight'        => 0,
        '#attributes'    => [
          'class'        => [
            Constants::MODULE . '-error-dialog-body',
          ],
        ],
      ],

      // TODO Including a "Close" button would be appropriate, but the
      // button doesn't default to closing the dialog, no form callback
      // occurs, and adding an AJAX callback doesn't work.
    ];

    if (empty($content) === FALSE) {
      $body['messages']['content'] = [
        '#markup' => $content,
        '#weight' => 0,
      ];
    }

    parent::__construct($title, $body, $dialogOptions, $settings);
  }

  /*--------------------------------------------------------------------
   *
   * Get/Set.
   *
   *--------------------------------------------------------------------*/

  /**
   * Sets dialog content based upon form state errors.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state who's error messages are used to set the dialog's
   *   body.
   * @param bool $clearErrors
   *   (optional, default = TRUE) When TRUE, clears the form errors after
   *   adding them to the dialog body. Default is TRUE.
   */
  public function setFromFormErrors(
    FormStateInterface &$formState,
    bool $clearErrors = TRUE) {

    // Loop through the errors and add them to the container.
    // Errors have already been translated.
    $body = $this->content;
    foreach ($formState->getErrors() as $error) {
      $body['messages'][] = [
        '#type'       => 'html_tag',
        '#tag'        => 'div',
        '#value'      => $error,
      ];
    }

    // Clear errors out of the form.
    if ($clearErrors === TRUE) {
      $formState->clearErrors();
    }

    $this->content = $body;
  }

  /**
   * Sets dialog content based upon page messages.
   *
   * @param array $messageTypes
   *   (optional) An array containing one or more of 'error', 'warning',
   *   and 'status' that indicates the types of messages to include.
   *   Messages are always added in (error, warning, status) order.
   *   A NULL or empty array means to include everything. Default is NULL.
   * @param bool $clearMessages
   *   (optional) When TRUE, clears the page messages after adding them
   *   to the dialog body. Default is TRUE.
   */
  public function setFromPageMessages(
    array $messageTypes = NULL,
    bool $clearMessages = TRUE) {

    // Determine which message types to include.
    if (empty($messageTypes) === TRUE) {
      // Include everything in (error, warning, status) order.
      $messageTypes = [
        'error',
        'warning',
        'status',
      ];
    }
    else {
      // Include named types in (error, warning, status) order.
      // Ignore any other type names.
      $e = [];
      foreach (['error', 'warning', 'status'] as $t) {
        if (in_array($t, $messageTypes) === TRUE) {
          $e[] = $t;
        }
      }

      $messageTypes = $e;
    }

    // Loop through the types and messages and add them to the container.
    // Messages have already been translated.
    $allMessagesByType = \Drupal::messenger()->all();
    $body = $this->content;

    foreach ($allMessagesByType as $mtype => $messages) {
      if (in_array($mtype, $messageTypes) === TRUE) {
        foreach ($messages as $message) {
          $body['messages'][] = [
            '#type'  => 'html_tag',
            '#tag'   => 'div',
            '#value' => $message,
          ];
        }

        \Drupal::messenger()->deleteByType($mtype);
      }
    }

    $this->content = $body;
  }

}
