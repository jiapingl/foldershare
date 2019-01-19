<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

use Drupal\foldershare\Branding;
use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;

/**
 * Manages the "About" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsAboutTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * This tab has no settings. It is strictly informational.
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
  private function buildAboutTab(
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

    $moduleName = Constants::MODULE;
    $moduleTitle = $mh->getName(Constants::MODULE);

    $tabName = self::makeCssSafe($moduleName . '_' . $tabMachineName . '_tab');
    $cssBase = self::makeCssSafe($moduleName);

    //
    // Help link
    // ---------
    // Get a link to the module's help, if the help module is installed.
    $helpLink = '';
    $helpInstalled = $mh->moduleExists('help');
    if ($helpInstalled === TRUE) {
      // The help module exists. Create a link to this module's help.
      $helpLink = Utilities::createHelpLink($moduleName, 'Module help');
    }

    // Get a link to the usage report.
    $usageLink = Utilities::createRouteLink(
      Constants::ROUTE_USAGE,
      '',
      $this->t('Usage report'));

    // Create a "See also" message with links. Include the help link only
    // if the help module is installed.
    $seeAlso = '';
    if (empty($helpLink) === TRUE) {
      $seeAlso = $this->t("See also the module's") . ' ' . $usageLink . '.';
    }
    else {
      $seeAlso = $this->t('See also') . ' ' . $helpLink . ' ' .
        $this->t("and the module's") . ' ' . $usageLink . '.';
    }

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
        'branding'      => Branding::getBannerBranding(),
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            '<strong>@moduletitle</strong> manages a virtual file system with files, folders, and subfolders. Module settings control where files are stored, how they are presented and searched, and how they can be shared with other users.',
            [
              '@moduletitle' => $moduleTitle,
            ]),
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

    if (empty($seeAlso) === FALSE) {
      $form[$tabName]['#description']['seealso'] = [
        '#type'         => 'html_tag',
        '#tag'          => 'p',
        '#value'        => $seeAlso,
      ];
    }
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
  private function validateAboutTab(
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
  private function submitAboutTab(
    array &$form,
    FormStateInterface $formState) {

    // Nothing to do.
  }

}
