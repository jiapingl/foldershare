<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Manages the "Fields" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsFieldsTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * Settings:
   * - Link to manage fields tab.
   * - Link to manage forms tab.
   * - Link to manage displays tab.
   * - Buttons to reset forms and displays.
   * - Links to related module settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form. The form
   *   is modified to include additional render elements for the tab.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param string $tabGroup
   *   The name of the tab group.
   * @param string $tabMachineName
   *   The untranslated name for the tab, used in CSS class names.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $tabTitle
   *   The translated name for the tab.
   */
  private function buildFieldsTab(
    array &$form,
    FormStateInterface $formState,
    string $tabGroup,
    string $tabMachineName,
    TranslatableMarkup $tabTitle) {

    //
    // Setup
    // -----
    // Set up some variables.
    $mh = \Drupal::service('module_handler');

    $fieldUiInstalled = $mh->moduleExists('field_ui');
    $helpInstalled = $mh->moduleExists('help');

    $moduleName = Constants::MODULE;
    $moduleTitle = $mh->getName(Constants::MODULE);

    $tabName = self::makeCssSafe($moduleName . '_' . $tabMachineName . '_tab');
    $cssBase = self::makeCssSafe($moduleName);

    $tabSubtitleClass        = $moduleName . '-settings-subtitle';
    $sectionTitleClass       = $moduleName . '-settings-section-title';
    $sectionClass            = $moduleName . '-settings-section';
    $sectionDescriptionClass = $moduleName . '-settings-section-description';
    $warningClass            = $moduleName . '-warning';

    $inputRestoreFormsName = $moduleName . '_fields_restore_forms';
    $inputRestoreDisplaysName = $moduleName . '_fields_restore_displays';

    //
    // Create the tab
    // --------------
    // Start the tab with a title, subtitle, and description.
    $form[$tabName] = [
      '#type'           => 'details',
      '#open'           => FALSE,
      '#group'          => $tabGroup,
      '#title'          => $tabTitle,
      '#description'    => [
        'subtitle'      => [
          '#type'       => 'html_tag',
          '#tag'        => 'h2',
          '#value'      => $this->t(
            'Manage fields for files and folders.'),
          '#attributes' => [
            'class'     => [$tabSubtitleClass],
          ],
        ],
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'Files and folders contain fields for names, dates, sizes, descriptions, and more. You may add your own fields and adjust how fields are displayed when viewed and edited.'),
          '#attributes' => [
            'class'     => [
              $cssBase . '-settings-description',
            ],
          ],
        ],
      ],
      '#attributes'     => [
        'class'         => [
          $cssBase . '-settings-tab ',
          $cssBase . '-fields-tab',
        ],
      ],
    ];

    //
    // Manage fields, forms, and displays
    // ----------------------------------
    // By default, the Field UI module's forms for managing fields,
    // forms, and displays are in tabs at the top of the page. Include
    // links and comments here too.
    if ($fieldUiInstalled === TRUE && $helpInstalled === TRUE) {
      $fieldUiLink = Utilities::createHelpLink('field_ui', 'Field UI module');
    }
    else {
      $fieldUiLink = $this->t('Field UI module');
    }

    $form[$tabName]['manage-field-ui'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('Fields, forms, and displays') . '</h3>',
      '#attributes'      => [
        'class'          => [$sectionTitleClass],
      ],
      'section'          => [
        '#type'          => 'container',
        '#attributes'    => [
          'class'        => [$sectionClass],
        ],
        'description'      => [
          '#type'          => 'html_tag',
          '#tag'           => 'p',
          '#value'         => $this->t(
            'Built-in fields for files and folders include a name, description, size, and creation and modification dates. Fields may be adjusted with the optional @fieldui to add new fields and manage how fields are viewed and edited.',
            [
              '@moduletitle' => $moduleTitle,
              '@fieldui'   => $fieldUiLink,
            ]),
          '#attributes'    => [
            'class'        => [$sectionDescriptionClass],
          ],
        ],
      ],
    ];

    //
    // Create warning
    // --------------
    // If the field_ui module is not installed, links to "Manage *" will
    // not work. Create a warning.
    if ($fieldUiInstalled === FALSE) {
      $disabledWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'These items are disabled because they require the Field UI module. To use these settings, please enable the @fieldui.',
          [
            '@fieldui' => Utilities::createRouteLink(
              'system.modules_list',
              'module-field-ui',
              'Field UI module'),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];

      $form[$tabName]['manage-field-ui']['section']['warning'] = $disabledWarning;
      $administerFields = FALSE;
      $administerForms = FALSE;
      $administerDisplays = FALSE;
    }
    else {
      // If the user does not have field, form, or display administation
      // permissions, links to "Manage *" will not work. Create a warning.
      $account = \Drupal::currentUser();

      $administerFields = $account->hasPermission(
        'administer ' . Constants::MODULE . ' fields');
      $administerForms = $account->hasPermission(
        'administer ' . Constants::MODULE . ' form display');
      $administerDisplays = $account->hasPermission(
        'administer ' . Constants::MODULE . ' display');

      if ($administerFields === FALSE || $administerForms === FALSE ||
          $administerDisplays === FALSE) {
        $disabledWarning = [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'Some of these items are disabled because they require that you have additional permissions. To use these settings, you need @fieldui permissions to administer fields, forms, and displays for @moduletitle.',
            [
              '@fieldui' => Utilities::createRouteLink(
                'user.admin_permissions',
                'module-field-ui',
                'Field UI module'),
              '@moduletitle' => $moduleTitle,
            ]),
          '#attributes' => [
            'class'     => [$warningClass],
          ],
        ];

        $form[$tabName]['manage-field-ui']['section']['warning'] = $disabledWarning;
      }
    }

    //
    // Create links
    // ------------
    // Create links to the field_ui tabs, if possible.
    if ($fieldUiInstalled === TRUE) {
      // The module is installed. Use its routes.
      if ($administerFields === TRUE) {
        $manageFieldsLink = Utilities::createRouteLink(
            'entity.' . FolderShare::ENTITY_TYPE_ID . '.field_ui_fields');
      }
      else {
        $manageFieldsLink = $this->t('Manage fields');
      }

      if ($administerForms === TRUE) {
        $manageFormsLink = Utilities::createRouteLink(
            'entity.entity_form_display.' . FolderShare::ENTITY_TYPE_ID . '.default');
        $formResetDisabled = FALSE;
      }
      else {
        $manageFormsLink = $this->t('Manage form display');
        $formResetDisabled = TRUE;
      }

      if ($administerDisplays === TRUE) {
        $manageDisplayLink = Utilities::createRouteLink(
            'entity.entity_view_display.' . FolderShare::ENTITY_TYPE_ID . '.default');
        $displayResetDisabled = FALSE;
      }
      else {
        $manageDisplayLink = $this->t('Manage display');
        $displayResetDisabled = TRUE;
      }
    }
    else {
      // The Field UI module is NOT installed. Just use text.
      $manageFieldsLink  = $this->t('Manage fields');
      $manageFormsLink   = $this->t('Manage form display');
      $manageDisplayLink = $this->t('Manage display');

      $formResetDisabled    = TRUE;
      $displayResetDisabled = TRUE;
    }

    $form[$tabName]['manage-field-ui']['section']['manage-fields'] = [
      '#type'    => 'container',
      '#prefix'  => '<dl>',
      '#suffix'  => '</dl>',
      'title'    => [
        '#type'  => 'html_tag',
        '#tag'   => 'dt',
        '#value' => $manageFieldsLink,
      ],
      'data'     => [
        '#type'  => 'html_tag',
        '#tag'   => 'dd',
        '#value' => $this->t('Create, delete, and modify additional fields for files and folders.'),
      ],
    ];

    $form[$tabName]['manage-field-ui']['section']['manage-form'] = [
      '#type'      => 'container',
      '#prefix'    => '<dl>',
      '#suffix'    => '</dl>',
      'title'      => [
        '#type'    => 'html_tag',
        '#tag'     => 'dt',
        '#value'   => $manageFormsLink,
      ],
      'data'       => [
        '#type'    => 'html_tag',
        '#tag'     => 'dd',
        '#value'   => $this->t('Manage forms to edit fields for files and folders.'),
      ],
      $inputRestoreFormsName => [
        '#type'     => 'button',
        '#value'    => $this->t('Restore original settings'),
        '#disabled' => $formResetDisabled,
        '#name'     => $inputRestoreFormsName,
        '#prefix'   => '<dd>',
        '#suffix  ' => '</dd>',
      ],
    ];

    $form[$tabName]['manage-field-ui']['section']['manage-display'] = [
      '#type'      => 'container',
      '#prefix'    => '<dl>',
      '#suffix'    => '</dl>',
      'title'      => [
        '#type'    => 'html_tag',
        '#tag'     => 'dt',
        '#value'   => $manageDisplayLink,
      ],
      'data'       => [
        '#type'    => 'html_tag',
        '#tag'     => 'dd',
        '#value'   => $this->t('Manage how fields are presented on file and folder pages.'),
      ],
      $inputRestoreDisplaysName => [
        '#type'     => 'button',
        '#value'    => $this->t('Restore original settings'),
        '#disabled' => $displayResetDisabled,
        '#name'     => $inputRestoreDisplaysName,
        '#prefix'   => '<dd>',
        '#suffix  ' => '</dd>',
      ],
    ];

    //
    // Related settings
    // ----------------
    // Add links to settings.
    $this->buildFieldsRelatedSettings($form, $formState, $tabGroup, $tabName);
  }

  /**
   * Builds the related settings section of the tab.
   *
   * @param array $form
   *   An associative array containing the structure of the form. The form
   *   is modified to include additional render elements for the tab.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param string $tabGroup
   *   The name of the tab group.
   * @param string $tabName
   *   The CSS-read tab name.
   */
  private function buildFieldsRelatedSettings(
    array &$form,
    FormStateInterface $formState,
    string $tabGroup,
    string $tabName) {

    $moduleName        = Constants::MODULE;
    $sectionTitleClass = $moduleName . '-settings-section-title';
    $seeAlsoClass      = $moduleName . '-settings-seealso-section';
    $relatedClass      = $moduleName . '-related-links';
    $markup            = '';

    //
    // Create links.
    // -------------
    // For each of several modules, create a link to the module's settings
    // page if the module is installed, and no link if it is not.
    //
    // Comment types.
    $markup .= $this->buildRelatedSettings(
      'comment',
      'Comment types',
      'entity.comment_type.collection',
      [],
      'Configure commenting on files and folders.',
      TRUE,
      ['comment-module' => 'Comments']);

    // Datetime.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'Dates and times',
      'entity.date_format.collection',
      [],
      "Configure the site's date and time formats.",
      TRUE,
      ['datetime' => 'Date-Time']);

    // Regional.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'Regional',
      'system.regional_settings',
      [],
      "Configure the site's default locale and time zones.",
      TRUE,
      []);

    // Text editors.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'Text Editors',
      'filter.admin_overview',
      [],
      "Configure the site's WYSIWYG editors for formatted text.",
      TRUE,
      ['editor' => 'Text editor']);

    // Text formats.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'Text Formats',
      'filter.admin_overview',
      [],
      "Configure the site's text filters for formatted text.",
      TRUE,
      ['filter' => 'Filter']);

    // Tokens.
    $markup .= $this->buildRelatedSettings(
      'token',
      'Tokens',
      'help.page',
      ['name' => 'token'],
      "Manage the site's text-replacement tokens.",
      FALSE,
      []);

    //
    // Add to form.
    // ------------
    // Add the links to the end of the form.
    //
    $form[$tabName]['related-settings'] = [
      '#type'            => 'details',
      '#title'           => $this->t('See also'),
      '#open'            => FALSE,
      '#summary_attributes' => [
        'class'          => [$sectionTitleClass],
      ],
      '#attributes'      => [
        'class'          => [$seeAlsoClass],
      ],
      'seealso'          => [
        '#type'          => 'item',
        '#markup'        => '<dl>' . $markup . '</dl>',
        '#attributes'    => [
          'class'        => [$relatedClass],
        ],
      ],
    ];
  }

  /*---------------------------------------------------------------------
   *
   * Validate.
   *
   *---------------------------------------------------------------------*/

  /**
   * Validates form values.
   *
   * @param array $form
   *   The form configuration.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The entered values for the form.
   */
  private function validateFieldsTab(
    array &$form,
    FormStateInterface $formState) {

    $moduleName = Constants::MODULE;
    $inputRestoreFormsName = $moduleName . '_fields_restore_forms';
    $inputRestoreDisplaysName = $moduleName . '_fields_restore_displays';

    // Mapping from form input name to the configuration to restore.
    $restores = [
      $inputRestoreFormsName => 'entity_form_display.foldershare.foldershare.default',
      $inputRestoreDisplaysName => 'entity_view_display.foldershare.foldershare.default',
    ];

    // Restore to an original configuration.
    $userInput     = $formState->getUserInput();
    $formFieldName = '';
    $configName    = '';

    foreach ($restores as $key => $value) {
      if (isset($userInput[$key]) === TRUE) {
        $formFieldName = $key;
        $configName = $value;
        break;
      }
    }

    if (empty($formFieldName) === TRUE) {
      // None of the configurations were restored.  Perhaps something else
      // on the form was triggered. Return silently.
      return;
    }

    // Restore the configuration.
    $status = Utilities::revertConfiguration('core', $configName);

    // Unset the button in form state since it has already been handled.
    unset($userInput[$formFieldName]);
    $formState->setUserInput($userInput);

    // Rebuild the page.
    $formState->setRebuild(TRUE);
    if ($status === TRUE) {
      \Drupal::messenger()->addMessage(
        t('The configuration has been restored.'),
        'status');
    }
    else {
      \Drupal::messenger()->addMessage(
        t('An unexpected error occurred and the configuration could not be restored.'),
        'error');
    }
  }

  /*---------------------------------------------------------------------
   *
   * Submit.
   *
   *---------------------------------------------------------------------*/

  /**
   * Stores submitted form values.
   *
   * @param array $form
   *   The form configuration.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The entered values for the form.
   */
  private function submitFieldsTab(
    array &$form,
    FormStateInterface $formState) {

    // Nothing to do.
  }

}
