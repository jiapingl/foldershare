<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\RuntimeExceptionWithMarkup;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Defines a command plugin to change ownership of files or folders.
 *
 * The command sets the UID for the owner of all selected entities.
 * Owenrship changes recurse through all folder content as well.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to change ownership on.
 * - 'uid': the UID of the new owner.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_change_owner",
 *  label           = @Translation("Change Owner"),
 *  menuNameDefault = @Translation("Change Owner..."),
 *  menuName        = @Translation("Change Owner..."),
 *  description     = @Translation("Change the owner of files and folders"),
 *  category        = "administer",
 *  weight          = 10,
 *  userConstraints = {
 *    "adminpermission",
 *  },
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *      "any",
 *    },
 *    "access"  = "view",
 *  },
 *  selectionConstraints = {
 *    "types"   = {
 *      "parent",
 *      "one",
 *      "many",
 *    },
 *    "kinds"   = {
 *      "any",
 *    },
 *    "access"  = "chown",
 *  },
 * )
 */
class ChangeOwner extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // Add room for the UID and a recursion flag.
    $config = parent::defaultConfiguration();
    $config['uid'] = '';
    $config['changedescendants'] = 'FALSE';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function validateParameters() {
    if ($this->parametersValidated === TRUE) {
      return;
    }

    // Get the new UID from the configuration and check if it is valid.
    $uid = $this->configuration['uid'];
    if ($uid === NULL) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The user ID is not recognized.')));
    }

    $user = User::load($uid);
    if ($user === NULL) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The user ID "@uid" does not match any user account at this site.',
          [
            '@uid' => $uid,
          ]),
        t('Please check that the ID is correct and for an existing account.')));
    }

    $this->parametersValidated = TRUE;
  }

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasConfigurationForm() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(bool $forPage) {
    // The description varies for page vs. dialog:
    //
    // - Dialog: There is no description. Too obvious to include.
    //
    // - Page: There is no description. Too obvious to include.
    //
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(bool $forPage) {
    // The title varies for page vs. dialog:
    //
    // - Dialog: The title is short and has the form "Change owner of OPERAND",
    //   where OPERAND is the kind of item (e.g. "file"). By not putting
    //   the item's name in the title, we keep the dialog title short and
    //   avoid cropping or wrapping.
    //
    // - Page: The title is longer and has the form "Change the owner of
    //   OPERAND?". where OPERAND can be the name of the item if one item
    //   is being changed, or the count and kinds if multiple items are
    //   being changed. This follows Drupal convention.
    $selectionIds = $this->getSelectionIds();
    if (empty($selectionIds) === TRUE) {
      $selectionIds[] = $this->getParentId();
    }

    if ($forPage === TRUE && count($selectionIds) === 1) {
      // Page title. There is only one item. Load it.
      $item = FolderShare::load($selectionIds[0]);
      return t(
        'Change owner of "@name"?',
        [
          '@name' => $item->getName(),
        ]);
    }

    // Find the kinds for each of the selection IDs. Then choose an
    // operand based on the selection's single kind, or "items".
    $selectionKinds = FolderShare::findKindsForIds($selectionIds);
    if (count($selectionIds) === 1) {
      $kind = key($selectionKinds);
      $operand = Utilities::translateKind($kind);
    }
    elseif (count($selectionKinds) === 1) {
      $kind = key($selectionKinds);
      $operand = Utilities::translateKinds($kind);
    }
    else {
      $operand = Utilities::translateKinds('items');
    }

    if ($forPage === TRUE) {
      // Page title. Include the count and operand kind. Question mark.
      return t(
        "Change owner of @count @operand?",
        [
          '@count' => count($selectionIds),
          '@operand' => $operand,
        ]);
    }

    return t(
      // Dialog title. Include the operand kind. No question mark.
      'Change owner of @operand',
      [
        '@operand' => $operand,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitButtonName() {
    return t('Change');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {

    // Find the kinds for each of the selection IDs.
    $selectionIds = $this->getSelectionIds();
    $selectionKinds = FolderShare::findKindsForIds($selectionIds);
    $hasFolders = (isset($selectionKinds[FolderShare::FOLDER_KIND]) === TRUE);

    // Use the UID of the current user as the default value.
    $account = \Drupal::currentUser();
    $this->configuration['uid'] = $account->id();

    // The command wrapper provides form basics:
    // - Attached libraries.
    // - Page title (if not an AJAX dialog).
    // - Description (from ::getDescription()).
    // - Submit buttion (labeled with ::getSubmitButtonName()).
    // - Cancel button (if AJAX dialog).
    $form['owner'] = [
      '#type'          => 'entity_autocomplete',
      '#name'          => 'owner',
      '#weight'        => 10,
      '#title'         => t('New owner:'),
      '#required'      => TRUE,
      '#target_type'   => 'user',
      '#selection_settings' => [
        'include_anonymous' => TRUE,
      ],
      '#default_value' => User::load($account->id()),
      '#validate_reference' => FALSE,
      '#size'          => 30,
      '#attributes'    => [
        'autofocus'    => 'autofocus',
        'class'        => [
          Constants::MODULE . '-changeowneritem-owner',
        ],
      ],
    ];

    if ($hasFolders === TRUE) {
      $form['changedescendants'] = [
        '#type'          => 'checkbox',
        '#name'          => 'changedescendants',
        '#weight'        => 20,
        '#title'         => t('Apply to enclosed items'),
        '#default_value' => ($this->configuration['changedescendants'] === 'TRUE'),
        '#attributes' => [
          'class'     => [
            Constants::MODULE . '-changeowneritem-changedescendants',
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    // Get the form's user ID and descendants flag.
    $this->configuration['uid'] = $formState->getValue('owner');

    $this->configuration['changedescendants'] = 'FALSE';
    if ($formState->hasValue('changedescendants') === TRUE) {
      if ($formState->getValue('changedescendants') === 1) {
        $this->configuration['changedescendants'] = 'TRUE';
      }
    }

    // Validate.
    try {
      $this->validateParameters();
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $formState->setErrorByName('owner', $e->getMarkup());
    }
    catch (\Exception $e) {
      $formState->setErrorByName('owner', $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    if ($this->isValidated() === TRUE) {
      $this->execute();
    }
  }

  /*--------------------------------------------------------------------
   *
   * Execute.
   *
   * These functions execute the command using the current configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $ids = $this->getSelectionIds();
    if (empty($ids) === TRUE) {
      $ids[] = $this->getParentId();
    }

    try {
      FolderShare::changeOwnerIdMultiple(
        $ids,
        $this->configuration['uid'],
        ($this->configuration['changedescendants'] === 'TRUE'));
    }
    catch (RuntimeExceptionWithMarkup $e) {
      \Drupal::messenger()->addMessage($e->getMarkup(), 'error', TRUE);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        \Drupal::translation()->formatPlural(
          count($ids),
          "The item has been changed.",
          "@count items have been changed."),
        'status');
    }
  }

}
