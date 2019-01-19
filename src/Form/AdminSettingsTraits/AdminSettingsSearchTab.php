<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;

/**
 * Manages the "Search" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsSearchTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * Settings:
   * - Link to search configuration.
   * - Button to reset search configuration.
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
  private function buildSearchTab(
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

    $searchInstalled = $mh->moduleExists('search');
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

    $inputRestoreCoreSearchName = $moduleName . '_search_restore_coresearch';

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
            'Manage search indexing and results.'),
          '#attributes' => [
            'class'     => [$tabSubtitleClass],
          ],
        ],
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'File and folder names, descriptions, and file content is indexed and shown in search results. You may configure when indexing is done, what is included, and how search results are presented.'),
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
    // Manage search
    // -------------
    // The search configuration is created by the core search module.
    if ($searchInstalled === TRUE && $helpInstalled === TRUE) {
      $searchLink = Utilities::createHelpLink('search', 'Search module');
    }
    else {
      $searchLink = $this->t('Search module');
    }

    $form[$tabName]['manage-search'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('Basic search') . '</h3>',
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
            'The optional @search provides basic support for searching content. @moduletitle provides an optional search plugin to index and present search results for files and folders.',
            [
              '@moduletitle' => $moduleTitle,
              '@search'      => $searchLink,
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
    // If the search module is not installed, links to it will
    // not work. Create a warning.
    if ($searchInstalled === FALSE) {
      $disabledWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'These items are disabled because they require the Search module. To use these settings, you must enable the @search.',
          [
            '@search' => Utilities::createRouteLink(
              'system.modules_list',
              'module-search',
              'Search module'),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];
      $searchDisabled = TRUE;

      $form[$tabName]['manage-search']['section']['warning'] = $disabledWarning;
    }
    else {
      // If the user does not have search administation permission,
      // links to search config pages will not work. Create a warning.
      $account = \Drupal::currentUser();

      $administerSearch = $account->hasPermission(
        'administer search');

      if ($administerSearch === FALSE) {
        $disabledWarning = [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'These items are disabled because they require that you have additional permissions. To use these settings, you need @search permissions to administer search.',
            [
              '@search' => Utilities::createRouteLink(
                'user.admin_permissions',
                'module-search',
                'Search module'),
            ]),
          '#attributes' => [
            'class'     => [$warningClass],
          ],
        ];
        $searchDisabled = TRUE;

        $form[$tabName]['manage-view-ui']['section']['warning'] = $disabledWarning;
      }
      else {
        $searchDisabled = FALSE;
      }
    }

    //
    // Create links
    // ------------
    // Create links to the search module, if possible.
    if ($searchDisabled === FALSE) {
      // The module is installed. Use its routes.
      $searchLink = Utilities::createRouteLink(
        'entity.search_page.collection');

      // Look for the search plugin.
      if (\Drupal::service('plugin.manager.search')->hasDefinition(
        Constants::SEARCH_PLUGIN) === TRUE) {
        // The search plugin is installed.
        $searchConfigLink = Link::createFromRoute(
          'Plugin configuration',
          'entity.search_page.edit_form',
          [
            'search_page' => Constants::SEARCH_PLUGIN,
          ],
          [])->toString();
        $searchLinks = $this->t(
          '@config on @pages',
          [
            '@config' => $searchConfigLink,
            '@pages'  => $searchLink,
          ]);
      }
      else {
        $searchLinks = $searchLink;
      }

      $resetDisabled = FALSE;
    }
    else {
      // The module is NOT installed. Just use text.
      $searchLinks = $this->t('Search');
      $resetDisabled = TRUE;
    }

    $form[$tabName]['manage-search']['section']['coresearch'] = [
      '#type'    => 'container',
      '#prefix'  => '<dl>',
      '#suffix'  => '</dl>',
      'title'    => [
        '#type'  => 'html_tag',
        '#tag'   => 'dt',
        '#value' => $searchLinks,
      ],
      'data'     => [
        '#type'  => 'html_tag',
        '#tag'   => 'dd',
        '#value' => $this->t('Configure how search creates indexes of site content and presents search results.'),
      ],
      $inputRestoreCoreSearchName => [
        '#type'     => 'button',
        '#value'    => $this->t('Restore original settings'),
        '#disabled' => $resetDisabled,
        '#name'     => $inputRestoreCoreSearchName,
        '#prefix'   => '<dd>',
        '#suffix'   => '</dd>',
      ],
    ];

    //
    // Related settings
    // ----------------
    // Add links to settings.
    $this->buildSearchRelatedSettings($form, $formState, $tabGroup, $tabName);
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
  private function buildSearchRelatedSettings(
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
    // Cron.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'Cron',
      'system.cron_settings',
      [],
      "Configure the site's automatic administrative tasks.",
      TRUE,
      [
        'system'                => 'System',
        'automated-cron-module' => 'Automated Cron',
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
  private function validateSearchTab(
    array &$form,
    FormStateInterface $formState) {

    $moduleName = Constants::MODULE;
    $inputRestoreCoreSearchName = $moduleName . '_search_restore_coresearch';

    $restores = [
      $inputRestoreCoreSearchName => 'search.page.foldershare_search',
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

    // Restore the configuration.
    $status = Utilities::revertConfiguration('core', $configName);
    $mh = \Drupal::service('module_handler');
    if ($mh->moduleExists('search') === TRUE) {
      search_index_clear(Constants::SEARCH_INDEX);
    }

    // Unset the button in form state since it has already been handled.
    unset($userInput[$formFieldName]);
    $formState->setUserInput($userInput);

    // Rebuild the page.
    $formState->setRebuild(TRUE);
    if ($status === TRUE) {
      \Drupal::messenger()->addMessage(
        t('The search configuration has been restored and the search index cleared.'),
        'status');
    }
    else {
      \Drupal::messenger()->addMessage(
        t('An unexpected error occurred and the search configuration could not be restored.'),
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
  private function submitSearchTab(
    array &$form,
    FormStateInterface $formState) {

    // Nothing to do.
  }

}
