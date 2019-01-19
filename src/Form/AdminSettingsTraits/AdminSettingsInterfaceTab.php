<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\views\Entity\View;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;

/**
 * Manages the "Interface" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsInterfaceTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * Settings:
   * - Checkboxes to enable menu commands.
   * - Button to reset views.
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
  private function buildInterfaceTab(
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
    $viewsUiInstalled = ($mh->moduleExists('views_ui') === TRUE);

    $moduleName = Constants::MODULE;

    $tabName = self::makeCssSafe($moduleName . '_' . $tabMachineName . '_tab');
    $cssBase = self::makeCssSafe($moduleName);

    $tabSubtitleClass        = $moduleName . '-settings-subtitle';
    $sectionTitleClass       = $moduleName . '-settings-section-title';
    $sectionClass            = $moduleName . '-settings-section';
    $sectionDescriptionClass = $moduleName . '-settings-section-description';
    $warningClass            = $moduleName . '-warning';

    $inputAllowRestrictionsName = $moduleName . '_menu_allow_restrictions';
    $inputAllowedCommandsName = $moduleName . '_allowed_commands';

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
            'Manage the user interface.'),
          '#attributes' => [
            'class'     => [$tabSubtitleClass],
          ],
        ],
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'Tabular file and folder views may be adjusted to select which fields to include and how to present them. Each table also includes a toolbar with a configurable menu of commands to create, upload, edit, and delete items.'),
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
          $cssBase . '-interface-tab',
        ],
      ],
    ];

    //
    // Build menu command list
    // -----------------------
    // Get a list of all installed command definitions.
    $allNames = [];
    $allChosen = [];

    // Get the unsorted list of all command definitions. Array keys are
    // IDs and values are definitions.
    $defs = Settings::getAllCommandDefinitions();

    // Sort them, reordering the keys to sort by definition label.
    usort(
      $defs,
      function ($a, $b) {
        return ($a['label'] < $b['label']) ? (-1) : 1;
      });

    // And build a new sorted array with keys as IDs and values as definitions.
    // The new array is built in sort order.
    $sortedDefs = [];
    foreach ($defs as $def) {
      $sortedDefs[$def['id']] = $def;
    }

    //
    // Manage menus
    // ------------
    // Loop through all the commands, in sort order, and create a description
    // for each one for checkboxes.
    foreach ($sortedDefs as $id => $def) {
      $provider    = $def['provider'];
      $description = $def['description'];

      $allNames[$id] = '<dt>' . $def['label'] . '</dt><dd>' . $description .
        ' <em>(' . $provider . ' module)</em></dd>';
      $allChosen[$id] = NULL;
    }

    foreach (Settings::getCommandMenuAllowed() as $id) {
      $allChosen[$id] = $id;
    }

    $form[$tabName]['manage-menus'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('Menus') . '</h3>',
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
            'Commands are plugins provided by this module and others. By default, all available commands are included on menus. You may restrict menus to a specific set of commands.'),
          '#attributes'    => [
            'class'        => [$sectionDescriptionClass],
          ],
        ],
        $inputAllowRestrictionsName => [
          '#type'          => 'checkbox',
          '#title'         => $this->t('Enable command menu restrictions'),
          '#default_value' => Settings::getCommandMenuRestrict(),
          '#return_value'  => 'enabled',
          '#required'      => FALSE,
          '#name'          => $inputAllowRestrictionsName,
        ],
        'foldershare_command_menu_choices' => [
          '#type'          => 'container',
          '#states'        => [
            'invisible'    => [
              'input[name="' . $inputAllowRestrictionsName . '"]' => [
                'checked' => FALSE,
              ],
            ],
          ],
          'description'      => [
            '#type'          => 'html_tag',
            '#tag'           => 'p',
            '#value'         => $this->t('Select the commands to allow:'),
          ],
          '#attributes'    => [
            'class'        => ['foldershare_command_menu_choices'],
          ],
          $inputAllowedCommandsName => [
            '#type'          => 'checkboxes',
            '#options'       => $allNames,
            '#default_value' => $allChosen,
            '#prefix'        => '<dl class="' . $inputAllowedCommandsName . '">',
            '#suffix'        => '</dl>',
          ],
        ],
      ],
    ];

    //
    // Manage views
    // ------------
    // List and reset views.
    if ($viewsUiInstalled === TRUE) {
      $viewsUiLink = Utilities::createRouteLink(
        'entity.view.collection',
        '',
        'Views UI module');
    }
    else {
      $viewsUiLink = $this->t('Views UI module');
    }

    $form[$tabName]['manage-views-ui'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('Views') . '</h3>',
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
            'The layout and presentation of lists may be controlled using the optional @viewsui.',
            [
              '@viewsui'   => $viewsUiLink,
            ]),
          '#attributes'    => [
            'class'        => [$sectionDescriptionClass],
          ],
        ],
      ],
    ];

    //
    // Create views UI warning
    // -----------------------
    // If the view_ui module is not installed, links to the views config
    // pages will not work. Create a warning.
    if ($viewsUiInstalled === FALSE) {
      $disabledWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'These items are disabled because they require the Views UI module. To use these settings, you must enable the @viewsui.',
          [
            '@viewsui' => Utilities::createRouteLink(
              'system.modules_list',
              'module-views-ui',
              'Views UI module'),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];
      $viewsUiDisabled = TRUE;

      $form[$tabName]['manage-views-ui']['section']['warning'] = $disabledWarning;
    }
    else {
      // If the user does not have view administation permission,
      // links to views config pages will not work. Create a warning.
      $account = \Drupal::currentUser();

      $administerViews = $account->hasPermission('administer views');

      if ($administerViews === FALSE) {
        $disabledWarning = [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'These items are disabled because they require that you have additional permissions. To use these settings, you need @viewui permissions to administer views.',
            [
              '@viewui' => Utilities::createRouteLink(
                'user.admin_permissions',
                'module-view-ui',
                'View UI module'),
            ]),
          '#attributes' => [
            'class'     => [$warningClass],
          ],
        ];
        $viewsUiDisabled = TRUE;

        $form[$tabName]['manage-view-ui']['section']['warning'] = $disabledWarning;
      }
      else {
        $viewsUiDisabled = FALSE;
      }
    }

    //
    // Create view links
    // -----------------
    // For each well-known view, create a link to the view. What we have
    // is each view's machine name. We need to load the entity, if any,
    // to get its current name, and load the on-disk configuration, if any,
    // to get its original name.
    //
    // If there is no on-disk configuration, then we can't reset it.
    //
    // If there is no current entity, we can't link to it.
    $viewLinks = [
      // Only one multi-purpose view at this time.
      [
        'viewMachineName' => Constants::VIEW_LISTS,
        'description'     => $this->t('List files, folders, and subfolders.'),
      ],
    ];

    // Collect information about the views, such as whether the view
    // exists and its name.
    foreach ($viewLinks as $index => $viewLink) {
      $viewMachineName = $viewLink['viewMachineName'];

      // By default:
      // - Use the machine name we have for the original and current name.
      // - Do not disable a link to the view.
      // - Do not disable a reset button for the view.
      $viewLinks[$index]['originalName'] = $viewMachineName;
      $viewLinks[$index]['currentName'] = $viewMachineName;
      $viewLinks[$index]['disableLink'] = FALSE;
      $viewLinks[$index]['disableReset'] = FALSE;

      // If the Views UI module is not enabled, or if the user does not
      // have administration permission, then we cannot link to the view
      // or reset it.
      if ($viewsUiDisabled === TRUE) {
        $viewLinks[$index]['disableLink'] = TRUE;
        $viewLinks[$index]['disableReset'] = TRUE;
      }

      // Load the current entity. If the view does not exist, we cannot
      // link to it.
      $viewEntity = View::load($viewMachineName);
      if ($viewEntity === NULL) {
        // No entity. Cannot link to it.
        $viewLinks[$index]['disableLink'] = TRUE;
      }
      else {
        $viewLinks[$index]['currentName'] = $viewEntity->label();
      }

      // Load the configuration from disk. If the configuration does not
      // exist, we cannot reset the view using the disk configuration.
      $viewConfig = Utilities::loadConfiguration('view', $viewMachineName);
      if (empty($viewConfig) === TRUE) {
        // No configuration. Cannot reset it.
        $viewLinks[$index]['disableReset'] = TRUE;
      }
      elseif (isset($viewConfig['label']) === TRUE) {
        $viewLinks[$index]['originalName'] = $viewConfig['label'];
      }
    }

    // Format the views. For each one, link to the view and show a reset
    // button.
    foreach ($viewLinks as $index => $viewLink) {
      $viewMachineName = $viewLink['viewMachineName'];

      // Create the link to the current page, if any.
      if ($viewLink['disableLink'] === TRUE) {
        $link = Html::escape($viewLink['originalName']);
      }
      else {
        $link = Link::createFromRoute(
          Html::escape($viewLink['currentName']),
          Constants::ROUTE_VIEWS_UI_VIEW,
          ['view' => $viewMachineName])->toString();
      }

      // Create notes about creating the view and changing the name.
      $note = '';
      if ($viewLink['disableLink'] === TRUE) {
        $note = $this->t(
          'Resetting the view will re-create the original view.');
      }
      elseif ($viewLink['currentName'] !== $viewLink['originalName']) {
        $note = $this->t(
          'Resetting the view will restore its original "@name" name.',
          [
            '@name' => $viewLink['originalName'],
          ]);
      }

      $form[$tabName]['manage-views-ui']['section']['section-' . $viewMachineName] = [
        '#type'       => 'container',
        '#prefix'     => '<dl>',
        '#suffix'     => '</dl>',
        'title'       => [
          '#type'     => 'html_tag',
          '#tag'      => 'dt',
          '#value'    => $link,
        ],
        'data'        => [
          '#type'     => 'html_tag',
          '#tag'      => 'dd',
          '#value'    => $viewLink['description'] . ' ' . $note,
        ],
        $viewMachineName => [
          '#type'     => 'button',
          '#value'    => $this->t('Restore original settings'),
          '#name'     => $viewMachineName,
          '#disabled' => $viewLink['disableReset'],
          '#prefix'   => '<dd>',
          '#suffix'   => '</dd>',
        ],
      ];
    }

    //
    // Related settings
    // ----------------
    // Add links to settings.
    $this->buildInterfaceRelatedSettings($form, $formState, $tabGroup, $tabName);
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
  private function buildInterfaceRelatedSettings(
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

    // Regional.
    $markup .= $this->buildRelatedSettings(
      'views_ui',
      'Views user interface',
      'entity.view.collection',
      [],
      "Configure the site's views.",
      TRUE,
      [
        'views' => 'Views',
        'views_ui' => 'Views UI',
      ]);

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
  private function validateInterfaceTab(
    array &$form,
    FormStateInterface $formState) {

    $restores = [
      Constants::VIEW_LISTS,
    ];

    //
    // Restores
    // --------
    // Restore to an original configuration.
    $userInput = $formState->getUserInput();
    $viewMachineName = '';
    foreach ($restores as $name) {
      if (isset($userInput[$name]) === TRUE) {
        $viewMachineName = $name;
        break;
      }
    }

    if (empty($viewMachineName) === TRUE) {
      // None of the views were restored.  Perhaps something else
      // on the form was triggered. Return silently.
      return;
    }

    // Restore the view.
    $status = Utilities::revertConfiguration('view', $viewMachineName);

    // Unset the button in form state since it has already been handled.
    unset($userInput[$viewMachineName]);
    $formState->setUserInput($userInput);

    // Rebuild the page.
    $formState->setRebuild(TRUE);
    if ($status === TRUE) {
      \Drupal::messenger()->addMessage(
        t('The view configuration has been restored.'),
        'status');
    }
    else {
      \Drupal::messenger()->addMessage(
        t('An unexpected error occurred and the view configuration could not be restored.'),
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
  private function submitInterfaceTab(
    array &$form,
    FormStateInterface $formState) {

    $moduleName = Constants::MODULE;
    $inputAllowRestrictionsName = $moduleName . '_menu_allow_restrictions';
    $inputAllowedCommandsName = $moduleName . '_allowed_commands';

    // Get whether the command menu is restricted.
    $value = $formState->getValue($inputAllowRestrictionsName);
    $enabled = ($value === 'enabled') ? TRUE : FALSE;
    Settings::setCommandMenuRestrict($enabled);

    // Get allowed menu commands.
    $allChosen = $formState->getValue($inputAllowedCommandsName);
    Settings::setCommandMenuAllowed(array_keys(array_filter($allChosen)));

    Cache::invalidateTags(['rendered']);
  }

}
