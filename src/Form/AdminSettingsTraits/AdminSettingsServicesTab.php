<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;

/**
 * Manages the "Services" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsServicesTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * Settings:
   * - Link to REST configuration.
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
  private function buildServicesTab(
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

    $restuiInstalled = $mh->moduleExists('restui');
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

    $inputRestRestoreName = $moduleName . '_rest_restore_rest';

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
            'Manage REST web services.'),
          '#attributes' => [
            'class'     => [$tabSubtitleClass],
          ],
        ],
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'Files and folders are always accessible via a web browser, but additional web services may be enabled to allow client applications to access content without a browser. You may configure these services to enable or disable specific types of access.'),
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
    // Manage web services
    // -------------------
    // The REST configuration is adjustable via the REST UI module.
    if ($restuiInstalled === TRUE && $helpInstalled === TRUE) {
      $restuiHelpLink = Utilities::createHelpLink('restui', 'REST UI module');
    }
    else {
      $restuiHelpLink = $this->t('REST UI module');
    }

    $restInstalled = $mh->moduleExists('rest');
    if ($restInstalled === TRUE && $helpInstalled === TRUE) {
      $restHelpLink = Utilities::createHelpLink('rest', 'REST module');
    }
    else {
      $restHelpLink = $this->t('REST module');
    }

    $form[$tabName]['manage-rest'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('REST web services') . '</h3>',
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
            'The @rest manages REST (Representational State Transfer) web services, such as those provided by @moduletitle. While you can edit YML files to customize a configuration, the optional @restui provides a better user interface. Using the @restui you may enable and disable specific services and adjust their features.',
            [
              '@moduletitle' => $moduleTitle,
              '@restui'      => $restuiHelpLink,
              '@rest'        => $restHelpLink,
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
    // If the REST module is not installed, links to it will
    // not work. Create a warning.
    if ($restInstalled === FALSE) {
      $disabledWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'These items are disabled because they require the @rest. To use these settings, you must enable the @rest.',
          [
            '@rest' => Utilities::createRouteLink(
              'system.modules_list',
              'module-rest',
              'REST module'),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];
      $restDisabled = TRUE;

      $form[$tabName]['manage-rest']['section']['warning'] = $disabledWarning;
    }
    else {
      // If the user does not have REST administation permission,
      // links to REST config pages will not work. Create a warning.
      $account = \Drupal::currentUser();

      $administerRest = $account->hasPermission(
        'administer rest resources');

      if ($administerRest === FALSE) {
        $disabledWarning = [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'These items are disabled because they require that you have additional permissions. To use these settings, you need @rest permissions to administer REST resources.',
            [
              '@rest' => Utilities::createRouteLink(
                'user.admin_permissions',
                'module-rest',
                'REST module'),
            ]),
          '#attributes' => [
            'class'     => [$warningClass],
          ],
        ];
        $restDisabled = TRUE;

        $form[$tabName]['manage-view-ui']['section']['warning'] = $disabledWarning;
      }
      else {
        $restDisabled = FALSE;
      }
    }

    //
    // Create links
    // ------------
    // Create links to the REST module, if possible.
    if ($restDisabled === FALSE) {
      // The module is installed but the module has no configuration page.
      // Use the help link already created above.
      $resetDisabled = FALSE;
    }
    else {
      // The module is NOT installed. Just use text.
      $resetDisabled = TRUE;
    }

    $form[$tabName]['manage-rest']['section']['rest'] = [
      '#type'    => 'container',
      '#prefix'  => '<dl>',
      '#suffix'  => '</dl>',
      'title'    => [
        '#type'  => 'html_tag',
        '#tag'   => 'dt',
        '#value' => $restHelpLink,
      ],
      'data'     => [
        '#type'  => 'html_tag',
        '#tag'   => 'dd',
        '#value' => $this->t('Provide REST web services for entities and other features at the site.'),
      ],
      $inputRestRestoreName => [
        '#type'     => 'button',
        '#value'    => $this->t('Restore original settings'),
        '#disabled' => $resetDisabled,
        '#name'     => $inputRestRestoreName,
        '#prefix'   => '<dd>',
        '#suffix'   => '</dd>',
      ],
    ];

    //
    // Related settings
    // ----------------
    // Add links to settings.
    $this->buildServicesRelatedSettings($form, $formState, $tabGroup, $tabName);
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
  private function buildServicesRelatedSettings(
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
    // REST permissions.
    $markup .= $this->buildRelatedSettings(
      'rest',
      'REST administration permissions',
      'user.admin_permissions',
      ['module-rest'],
      'Grant administrative permissions for REST services.',
      TRUE,
      ['rest' => 'REST']);

    // REST UI.
    $markup .= $this->buildRelatedSettings(
      'restui',
      'REST UI',
      'restui.list',
      [],
      "Manage the site's REST configuration.",
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
  private function validateServicesTab(
    array &$form,
    FormStateInterface $formState) {

    $moduleName = Constants::MODULE;

    $inputRestoreRestName = $moduleName . '_rest_restore_rest';

    $restores = [
      $inputRestoreRestName => 'rest.resource.entity.foldershare',
    ];

    //
    // Restores
    // --------
    // Restore to an original configuration.
    $userInput = $formState->getUserInput();
    $formFieldName = '';
    $configName = '';
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

    // Restore the view.
    $status = Utilities::revertConfiguration('core', $configName);

    // Unset the button in form state since it has already been handled.
    unset($userInput[$formFieldName]);
    $formState->setUserInput($userInput);

    // Rebuild the page.
    $formState->setRebuild(TRUE);
    if ($status === TRUE) {
      \Drupal::messenger()->addMessage(
        t('The web services configuration has been restored.'),
        'status');
    }
    else {
      \Drupal::messenger()->addMessage(
        t('An unexpected error occurred and the web services configuration could not be restored.'),
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
  private function submitServicesTab(
    array &$form,
    FormStateInterface $formState) {

    // Nothing to do.
  }

}
