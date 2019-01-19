<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;

/**
 * Systems the "System" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsSystemTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * Settings:
   * - Checkbox to enable logging.
   * - Checkbox to enable process locks.
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
  private function buildSystemTab(
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

    $dblogInstalled = $mh->moduleExists('dblog');

    $moduleName = Constants::MODULE;

    $tabName = self::makeCssSafe($moduleName . '_' . $tabMachineName . '_tab');
    $cssBase = self::makeCssSafe($moduleName);

    $tabSubtitleClass        = $moduleName . '-settings-subtitle';
    $sectionTitleClass       = $moduleName . '-settings-section-title';
    $sectionClass            = $moduleName . '-settings-section';
    $sectionDescriptionClass = $moduleName . '-settings-section-description';
    $warningClass            = $moduleName . '-warning';

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
            'Manage system services.'),
          '#attributes' => [
            'class'     => [$tabSubtitleClass],
          ],
        ],
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            'File and folder activity optionally may be logged and process locks used to reduce problems with user collisions when editing shared content.'),
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
    // Logging
    // -------
    // Add a checkbox for logging.
    $form[$tabName]['manage-logging'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('Logging') . '</h3>',
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
            "File and folder activity may be reported to the site's log, including each add, delete, move, copy, edit, and share."),
          '#attributes'    => [
            'class'        => [$sectionDescriptionClass],
          ],
        ],
        'enable-logging'   => [
          '#type'          => 'checkbox',
          '#title'         => $this->t('Enable activity logging'),
          '#default_value' => Settings::getActivityLogEnable(),
          '#return_value'  => 'enabled',
          '#required'      => FALSE,
          '#name'          => 'enable-logging',
          '#description'   => $this->t('For performance reasons, it is recommended that sites running on production environments do not enable logging.'),
        ],
      ],
    ];

    if ($dblogInstalled === FALSE) {
      $disabledWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'The @dblog is currently disabled. To see logged messages by this module, and others, you must enable the module.',
          [
            '@dblog' => Utilities::createRouteLink(
              'system.modules_list',
              'module-dblog',
              'Database logging module'),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];

      $form[$tabName]['manage-logging']['section']['warning'] = $disabledWarning;
    }

    //
    // Process locks
    // -------------
    // Add a checkbox for process locks.
    $form[$tabName]['manage-process-locks'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('Process locks') . '</h3>',
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
            'Rapid activity on shared files and folders can collide when multiple users try to change the same items at the same time. This module can use process locks to give each user exclusive access during critical operations.'),
          '#attributes'    => [
            'class'        => [$sectionDescriptionClass],
          ],
        ],
        'enable-process-locks' => [
          '#type'          => 'checkbox',
          '#title'         => $this->t('Enable process locks'),
          '#default_value' => Settings::getProcessLocksEnable(),
          '#return_value'  => 'enabled',
          '#required'      => FALSE,
          '#name'          => 'enable-process-locks',
          '#description'   => $this->t('For performance reasons, it is recommended that sites running on production environments do not enable process locks. However, if problems occur, locks may be enabled to reduce update collisions.'),
        ],
      ],
    ];

    //
    // Related settings
    // ----------------
    // Add links to settings.
    $this->buildSystemRelatedSettings($form, $formState, $tabGroup, $tabName);
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
  private function buildSystemRelatedSettings(
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
    // Database logging.
    $markup .= $this->buildRelatedSettings(
      '',
      'Logging',
      'system.logging_settings',
      [],
      "Configure the site's logging and error reporting",
      TRUE,
      []);

    $markup .= $this->buildRelatedSettings(
      'dblog',
      'Recent log messages',
      'dblog.overview',
      [],
      'Show recent log messages',
      TRUE,
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
  private function validateSystemTab(
    array &$form,
    FormStateInterface $formState) {

    // Nothing to do.
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
  private function submitSystemTab(
    array &$form,
    FormStateInterface $formState) {

    $value = $formState->getValue('enable-logging');
    $enabled = ($value === 'enabled') ? TRUE : FALSE;
    Settings::setActivityLogEnable($enabled);

    $value = $formState->getValue('enable-process-locks');
    $enabled = ($value === 'enabled') ? TRUE : FALSE;
    Settings::setProcessLocksEnable($enabled);
  }

}
