<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\FolderShareInterface;

/**
 * Defines a command plugin base class to copy or move files and folders.
 *
 * @ingroup foldershare
 */
abstract class CopyMoveBase extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasConfigurationForm() {
    // Copy and move both require a destination ID. If there isn't one
    // yet, then a configuration form is required.
    return ($this->getDestinationId() === self::EMPTY_ITEM_ID);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {

    //
    // Validate destination.
    // ---------------------
    // Get the destination ID from the form state, or use a default.
    $destinationId = NULL;

    // If there is prior form state, and it includes a destination ID,
    // then get it. The ID might still be NULL, or it might be a word
    // naming a root list.
    if ($formState !== NULL) {
      $userInput = $formState->getUserInput();
      if (isset($userInput['destinationid']) === TRUE) {
        $destinationId = $userInput['destinationid'];
        // The destination ID coming back from the UI could be an integer
        // ID, or one of several words like "personal", "public", and "all",
        // to indicate specific root lists.
      }
    }

    // If the destination ID is still NULL, then default to the current parent.
    if ($destinationId === NULL) {
      $destinationId = $this->getParentId();
      if ($destinationId < 0) {
        $destinationId = FolderShareInterface::USER_ROOT_LIST;
      }
    }

    // Convert special root list names to negative numbers that flag the
    // equivalent root lists. This lets us pass around and compare integers,
    // rather than consider string values as well.
    switch ($destinationId) {
      case FolderShareInterface::USER_ROOT_LIST:
      case 'personal':
        $destinationId = FolderShareInterface::USER_ROOT_LIST;
        $displayName = Constants::VIEW_DISPLAY_DIALOG_PERSONAL;
        break;

      case FolderShareInterface::PUBLIC_ROOT_LIST:
      case 'public':
        // The public root list, and its children, is never an allowed
        // destination. Public items are created as shared-with-anonymous
        // and then managed via the personal list by the owner and those
        // the owner allows author access.
        //
        // So, treat 'public' as 'personal'.
        $destinationId = FolderShareInterface::USER_ROOT_LIST;
        $displayName = Constants::VIEW_DISPLAY_DIALOG_PERSONAL;
        break;

      case FolderShareInterface::ALL_ROOT_LIST:
      case 'all':
        $destinationId = FolderShareInterface::ALL_ROOT_LIST;
        $displayName = Constants::VIEW_DISPLAY_DIALOG_ALL;
        break;

      default:
        $destinationId = (int) $destinationId;
        $displayName = Constants::VIEW_DISPLAY_DIALOG_FOLDER;
        if ($destinationId < 0) {
          $destinationId = FolderShareInterface::USER_ROOT_LIST;
          $displayName = Constants::VIEW_DISPLAY_DIALOG_PERSONAL;
        }
        break;
    }

    // And save the destination ID.
    $this->setDestinationId($destinationId);

    //
    // Set up view.
    // ------------
    // Find the embedded view and display, confirming that both exist and
    // that the user has access. Log errors if something is wrong.
    $error    = FALSE;
    $view     = NULL;
    $viewName = Constants::VIEW_LISTS;

    if (($view = Views::getView($viewName)) === NULL) {
      // Unknown view!
      \Drupal::logger(Constants::MODULE)->emergency(
        "Misconfigured web site. The required view \"@viewName\" is missing.\nPlease check the views module configuration and, if needed, restore the view from the @moduleName module's settings page.",
        [
          '@viewName'   => $viewName,
          '@moduleName' => Constants::MODULE,
        ]);
      $error = TRUE;
    }
    elseif ($view->setDisplay($displayName) === FALSE) {
      // Unknown display!
      \Drupal::logger(Constants::MODULE)->emergency(
        "Misconfigured web site. The required \"@displayName\" display for the \"@viewName\" view is missing.\nPlease check the views module configuration and, if needed, restore the view from the @moduleName module's settings page.",
        [
          '@viewName'    => $viewName,
          '@displayName' => $displayName,
          '@moduleName'  => Constants::MODULE,
        ]);
      $error = TRUE;
    }
    elseif ($view->access($displayName) === FALSE) {
      // Access denied to view display.
      $error = TRUE;
    }

    // If the view could not be found, there is nothing to embed and there
    // is no point in adding a UI. Return an error message in place of the
    // view's content.
    if ($error === TRUE) {
      $form['destinationselector'] = [
        '#attributes' => [
          'class'   => [
            'foldershare-error',
          ],
        ],

        // Do not cache this page. If any of the above conditions change,
        // the page needs to be regenerated.
        '#cache' => [
          'max-age' => 0,
        ],

        '#weight'   => 10,

        'error'     => [
          '#type'   => 'item',
          '#markup' => t(
            "The web site has encountered a problem with this page.\nPlease report this to the site administrator."),
        ],
      ];
      return $form;
    }

    //
    // Build view.
    // -----------
    // Add an embedded view to show the destination folder.
    //
    // Include hidden fields containing the current destination ID,
    // and the current destination selection ID. Add a hidden refresh
    // button that, when clicked by Javascript, triggers use of the
    // destination ID to create a new folder list.
    $form['foldershare-folder-selection'] = [
      '#type'       => 'container',
      '#name'       => 'foldershare-folder-selection',
      '#weight'     => 10,
      '#attributes' => [
        'class'     => [
          'foldershare-folder-selection',
        ],
      ],

      // Add prefix/suffix so that this entire section of the content is
      // replaced whenever the view needs to be refreshed for a new
      // destination.
      '#prefix'     => '<div id="foldershare-refresh"',
      '#suffix'     => '</div>',

      // Do not cache this. If anybody adds or removes a folder or changes
      // sharing, the view will change and this needs to be regenerated.
      '#cache'        => [
        'max-age'     => 0,
      ],

      // Include a hidden text field filled in by Javascript as the user
      // selects new destination folders by double-clicks or selecting from
      // the ancestor menu.
      'destinationid' => [
        '#type'           => 'textfield',
        '#name'           => 'destinationid',
        '#default_value'  => $destinationId,
        '#attributes'     => [
          'class'         => [
            'hidden',
          ],
        ],
      ],

      // Include a hidden refresh button "clicked" by Javascript each time the
      // destination ID above is changed. Below we add AJAX callbacks
      // to trigger a refresh of this part of the form.
      'refresh'           => [
        '#type'           => 'button',
        '#name'           => 'refresh',
        '#value'          => 'Refresh',
        '#attributes'     => [
          'class'         => [
            'hidden',
          ],
        ],
      ],

      // Include a hidden text field filled in by Javascript if the user
      // selects, but does not double-click, a folder in the list. This
      // becomes the selected destination. If nothing is selected, then
      // the parent of the current view is the destination.
      'selectionid' => [
        '#type'           => 'textfield',
        '#name'           => 'selectionid',
        '#default_value'  => self::EMPTY_ITEM_ID,
        '#attributes'     => [
          'class'         => [
            'hidden',
          ],
        ],
      ],

      // Include a toolbar with an ancestor menu like that found on
      // regular folder lists. Javascript adjusts the ancestor menu
      // so that it selects destination folders rather than jumping
      // to a new page.
      'toolbar'       => [
        '#type'       => 'container',
        '#attributes' => [
          'class'     => [
            'foldershare-toolbar',
          ],
        ],

        'ancestormenu' => Utilities::createAncestorMenu($destinationId, FALSE),
      ],

      // Add the view showing the destination folder.
      'view'          => [
        '#type'       => 'view',
        '#name'       => $viewName,
        '#embed'      => TRUE,
        '#display_id' => $displayName,
        '#arguments'  => [$destinationId],
        '#attributes' => [
          'autofocus' => 'autofocus',
          'class'     => [
            'foldershare-folder-selection-table',
          ],
        ],
      ],
    ];

    // When AJAX is in use, copy the AJAX configuration from the submit
    // button and use it for the refresh button as well.
    // TODO What to do if AJAX is not in use?
    if (isset($form['actions']['submit']['#ajax']) === TRUE) {
      $form['foldershare-folder-selection']['refresh']['#ajax'] =
        $form['actions']['submit']['#ajax'];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    //
    // Validate trigger.
    // -----------------
    // Ignore triggers other than 'submit' and 'refresh'.
    $triggerName = $formState->getTriggeringElement()['#name'];
    if ($triggerName !== 'refresh' && $triggerName !== 'submit') {
      // TODO Is this right? Simply returning from validation will cause
      // the caller to call submit function next.
      return;
    }

    //
    // Validate form input.
    // --------------------
    // Get the user's input to the form, if any, and the current parent.
    $userInput     = $formState->getUserInput();
    $destinationId = $userInput['destinationid'];
    $selectionId   = $userInput['selectionid'];

    if ($selectionId === NULL) {
      $selectionId = self::EMPTY_ITEM_ID;
    }

    // If the destination ID is NULL, then use a default.
    // - For a submit, if there is a selection then use it as the destination.
    // - Otherwise use the current parent as the destination.
    if ($destinationId === NULL) {
      if ($triggerName === 'submit' && $selectionId >= 0) {
        $destinationId = $selectionId;
      }
      else {
        $destinationId = $this->getParentId();
        if ($destinationId < 0) {
          $destinationId = FolderShareInterface::USER_ROOT_LIST;
        }
      }
    }

    // Convert special root list names to negative numbers that flag the
    // equivalent root lists. This lets us pass around and compare integers,
    // rather than consider string values as well.
    switch ($destinationId) {
      case FolderShareInterface::USER_ROOT_LIST:
      case 'personal':
        $destinationId = FolderShareInterface::USER_ROOT_LIST;
        break;

      case FolderShareInterface::PUBLIC_ROOT_LIST:
      case 'public':
        // The public root list is not supported. Map it to the personal list.
        $destinationId = FolderShareInterface::USER_ROOT_LIST;
        break;

      case FolderShareInterface::ALL_ROOT_LIST:
      case 'all':
        $destinationId = FolderShareInterface::ALL_ROOT_LIST;
        break;

      default:
        if (is_numeric($destinationId) === FALSE) {
          throw new NotFoundHttpException();
        }

        $destinationId = (int) $destinationId;
        if ($destinationId < 0) {
          $destinationId = FolderShareInterface::USER_ROOT_LIST;
        }
        else {
          $destination = FolderShare::load($destinationId);

          if ($destination === NULL ||
              $destination->isSystemHidden() === TRUE) {
            throw new NotFoundHttpException();
          }

          if ($destination->isSystemDisabled() === TRUE) {
            throw new AccessDeniedHttpException();
          }
        }
        break;
    }

    // Save the destination.
    $this->configuration['destinationId'] = $destinationId;

    //
    // Dispatch based on trigger.
    // --------------------------
    // For a refresh, rebuild the form. For a submit, validate it.
    if ($triggerName === 'refresh') {
      $formState->setRebuild(TRUE);
      return;
    }

    // Submit button pressed. Validate everything.
    try {
      $this->validateConfiguration();
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $formState->setErrorByName('destinationid', $e->getMarkup());
    }
    catch (\Exception $e) {
      $formState->setErrorByName('destinationid', $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    if ($this->isValidated() === TRUE) {
      if ($this->getDestinationId() === $this->getParentId()) {
        // Move to same location. Do nothing. No error.
        return;
      }

      $this->execute();
    }
  }

}
