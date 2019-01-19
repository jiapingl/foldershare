<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Form\FormStateInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\RuntimeExceptionWithMarkup;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Defines a command plugin to rename a file or folder.
 *
 * The command sets the name of a single selected entity.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entity to rename.
 * - 'name': the new name.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_rename",
 *  label           = @Translation("Rename"),
 *  menuNameDefault = @Translation("Rename..."),
 *  menuName        = @Translation("Rename..."),
 *  description     = @Translation("Rename files and folders"),
 *  category        = "edit",
 *  weight          = 20,
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
 *    },
 *    "kinds"   = {
 *      "any",
 *    },
 *    "access"  = "update",
 *  },
 * )
 */
class Rename extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // Include room for the new name in the configuration.
    $config = parent::defaultConfiguration();
    $config['name'] = '';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function validateParameters() {
    if ($this->parametersValidated === TRUE) {
      return;
    }

    // Get the parent folder, if any.
    $parent = $this->getParent();

    // Get the selected item. If none, use the parent instead.
    $itemIds = $this->getSelectionIds();
    if (empty($itemIds) === TRUE) {
      $item = $this->getParent();
    }
    else {
      $item = FolderShare::load(reset($itemIds));
    }

    // Check if the name is legal. This throws an exception if not valid.
    $newName = $this->configuration['name'];
    $item->checkName($newName);

    // Check if the new name is unique within the parent folder or root list.
    if ($parent !== NULL) {
      if ($parent->isNameUnique($newName, (int) $item->id()) === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'The name "@name" is already in use in the parent folder.',
            [
              '@name' => $newName,
            ]),
          t('Please choose a different name.')));
      }
    }
    elseif (FolderShare::isRootNameUnique($newName, (int) $item->id()) === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" is already in use in the list of top-level items.',
          [
            '@name' => $newName,
          ]),
        t('Please choose a different name.')));
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
    // - Dialog: The title is short and has the form "Rename OPERAND",
    //   where OPERAND is the kind of item (e.g. "file"). By not putting
    //   the item's name in the title, we keep the dialog title short and
    //   avoid cropping or wrapping.
    //
    // - Page: The title is longer and has the form "Rename "NAME"?"
    //   This follows Drupal convention.
    $selectionIds = $this->getSelectionIds();
    if (empty($selectionIds) === TRUE) {
      $item = $this->getParent();
    }
    else {
      $item = FolderShare::load(reset($selectionIds));
    }

    if ($forPage === TRUE) {
      // Page title. Include the name of the item. Question mark.
      return t(
        'Rename "@name"?',
        [
          '@name' => $item->getName(),
        ]);
    }

    // Dialog title. Include the operand kind. No question mark.
    return t(
      'Rename @operand',
      [
        '@operand' => Utilities::translateKind($item->getKind()),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitButtonName() {
    return t('Rename');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {

    // Get the current item name to use as the form default value.
    $itemIds = $this->getSelectionIds();
    if (empty($itemIds) === TRUE) {
      $item = $this->getParent();
    }
    else {
      $item = FolderShare::load(reset($itemIds));
    }

    $this->configuration['name'] = $item->getName();

    // The command wrapper provides form basics:
    // - Attached libraries.
    // - Page title (if not an AJAX dialog).
    // - Description (from ::getDescription()).
    // - Submit buttion (labeled with ::getSubmitButtonName()).
    // - Cancel button (if AJAX dialog).
    //
    // Add a text field prompt for the new name.
    $form['rename'] = [
      '#type'          => 'textfield',
      '#name'          => 'rename',
      '#weight'        => 10,
      '#title'         => t('New name:'),
      '#size'          => 30,
      '#maxlength'     => 255,
      '#required'      => TRUE,
      '#default_value' => $this->configuration['name'],
      '#attributes'    => [
        'autofocus'    => 'autofocus',
      ],
    ];
    $form['rename-description'] = [
      '#type'          => 'html_tag',
      '#name'          => 'rename-description',
      '#weight'        => 20,
      '#tag'           => 'p',
      '#value'         => t(
        'Use any mix of characters except ":", "/", and "\\".'),
      '#attributes'    => [
        'class'        => [
          'rename-description',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    $this->configuration['name'] = $formState->getValue('rename');

    try {
      $this->validateParameters();
    }
    catch (RuntimeExceptionWithMarkup $e) {
      $formState->setErrorByName('rename', $e->getMarkup());
    }
    catch (\Exception $e) {
      $formState->setErrorByName('rename', $e->getMessage());
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
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $itemIds = $this->getSelectionIds();
    if (empty($itemIds) === TRUE) {
      $item = $this->getParent();
    }
    else {
      $item = FolderShare::load(reset($itemIds));
    }

    try {
      $item->rename($this->configuration['name']);
    }
    catch (RuntimeExceptionWithMarkup $e) {
      \Drupal::messenger()->addMessage($e->getMarkup(), 'error', TRUE);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        t(
          "The @kind has been renamed.",
          [
            '@kind' => Utilities::translateKind($item->getKind()),
          ]),
        'status');
    }
  }

}
