<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;

/**
 * Manages an administrator form to adjust the module's configuration.
 *
 * This class builds a multi-page form with which administrators may adjust
 * module settings, including how files are stored and restrictions based upon
 * file name extensions. The form also includes button to reset the
 * configurations for the module's search plugin, views, REST resource,
 * entity form, and entity display.
 *
 * @section internal Internal class
 * This class is internal to the FolderShare module. The class's existance,
 * name, and content may change from release to release without any promise
 * of backwards compatability.
 *
 * @section access Access control
 * The route to this form must restrict access to those with the
 * FolderShare administration permission.
 *
 * @section parameters Route parameters
 * The route to this form has no parameters.
 *
 * @ingroup foldershare
 */
final class AdminSettings extends ConfigFormBase {
  use AdminSettingsTraits\AdminSettingsAboutTab;
  use AdminSettingsTraits\AdminSettingsFieldsTab;
  use AdminSettingsTraits\AdminSettingsFilesTab;
  use AdminSettingsTraits\AdminSettingsInterfaceTab;
  use AdminSettingsTraits\AdminSettingsSearchTab;
  use AdminSettingsTraits\AdminSettingsServicesTab;
  use AdminSettingsTraits\AdminSettingsSystemTab;

  /*--------------------------------------------------------------------
   *
   * Constants.
   *
   *--------------------------------------------------------------------*/

  /**
   * Enables number of files and file size limit settings.
   *
   * When TRUE, settings fields are included to set the maximum number
   * of files in an upload, and the maximum uploaded file size. When
   * FALSE, these settings are not included.
   *
   * @var bool
   */
  const ENABLE_FILE_LIMIT_SETTINGS = FALSE;

  /*--------------------------------------------------------------------
   *
   * Fields - dependency injection.
   *
   *--------------------------------------------------------------------*/

  /**
   * The module handler, set at construction time.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new form.
   *
   * @param Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ModuleHandlerInterface $moduleHandler) {

    parent::__construct($configFactory);

    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /*---------------------------------------------------------------------
   *
   * Form interface.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // Base the form ID on the namespace-qualified class name, which
    // already has the module name as a prefix.  PHP's get_class()
    // returns a string with "\" separators. Replace them with underbars.
    return str_replace('\\', '_', get_class($this));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [Constants::SETTINGS];
  }

  /*---------------------------------------------------------------------
   *
   * Utilities.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a lower case CSS-friendly version of a string.
   *
   * The string is converted to lower case. White space is replaced with
   * dashes. Punctuation characters are removed.
   *
   * @param string $str
   *   The string to convert.
   *
   * @return string
   *   The converted string.
   */
  private static function makeCssSafe(string $str) {
    $str = mb_convert_case($str, MB_CASE_LOWER);
    $str = preg_replace('/[[:space:]]+/', '-', $str);
    return preg_replace('/[^-_[:alnum:]]+/', '', $str);
  }

  /**
   * Returns markup for an entry in a related settings section.
   *
   * Arguments to the method indicate the machine name of a related
   * module (if any) and the module's title or settings section title.
   * Additional arguments provide the route and arguments to settings
   * (if any), a description, a flag indicating if the module or settings
   * are required, and an optional array of Drupal.org documentation
   * sections (primarily for core modules).
   *
   * @param string $module
   *   (optional) The machine name of a module.
   * @param string $moduleTitle
   *   The title of a module or settings section.
   * @param string $settingsRoute
   *   (optional) The route to a settings page.
   * @param array $settingsArguments
   *   (optional) An array of arguments for the settings page route.
   * @param string $description
   *   The description of the module and/or settings.
   * @param bool $isCore
   *   TRUE if the module/settings are part of Drupal core.
   * @param array $doc
   *   (optional) An associative array of Drupal.org documentation sections.
   *
   * @return string
   *   Returns HTML markup for the entry.
   */
  private function buildRelatedSettings(
    string $module,
    string $moduleTitle,
    string $settingsRoute,
    array $settingsArguments,
    string $description,
    bool $isCore,
    array $doc) {

    // Create an entry in a list of entries underneath a section title.
    // Each entry has a module/settings page name that, ideally, links
    // to the module/settings page. If the referenced module is not
    // currently enabled at the site, there is no link and the module
    // name is shown alone.
    //
    // Following the module name is a provided description of the module
    // or settings page and why it is relevant here. If provided,
    // links to Drupal.org documentation is added after the description.
    //
    // Module name
    // -----------
    // Get the module name and a possible link to the module or settings page.
    $addNotEnabled = FALSE;
    if (empty($module) === FALSE &&
        $this->moduleHandler->moduleExists($module) === FALSE) {
      // A module/settings machine name was provided, but that
      // module is not enabled at the site. Show the module title.
      $addNotEnabled = TRUE;
      $moduleLink = $moduleTitle;
    }
    elseif (empty($settingsRoute) === TRUE) {
      // The relevant module is enabled, but there is no settings page for it.
      // Show the module title.
      $moduleLink = $moduleTitle;
    }
    else {
      // The relevant module is enabled and it has a settings page. Link
      // to the page, adding in arguments if provided.
      if (empty($settingsArguments) === TRUE) {
        // The settings page requires no route arguments.
        $moduleLink = Utilities::createRouteLink(
          $settingsRoute,
          '',
          $moduleTitle);
      }
      elseif (isset($settingsArguments[0]) === TRUE) {
        // The settings page requires a simple fragment argument.
        $moduleLink = Utilities::createRouteLink(
          $settingsRoute,
          $settingsArguments[0],
          $moduleTitle);
      }
      else {
        // The settings page requires a named argument.
        $moduleLink = Link::createFromRoute(
          $moduleTitle,
          $settingsRoute,
          $settingsArguments)->toString();
      }
    }

    //
    // Core note
    // ---------
    // Create a note indicating if the module is core or contributed.
    if ($isCore === TRUE) {
      $coreNote = '<em>' . $this->t('(Core module)') . '</em>';
    }
    else {
      $coreNote = '<em>' . $this->t('(Third-party module)') . '</em>';
    }

    //
    // Enabled note
    // ------------
    // Create a note indicating if the module is enabled.
    if ($addNotEnabled === TRUE) {
      $enabledNote = '<em>' . $this->t('(Not enabled)') . '</em>';
    }
    else {
      $enabledNote = '';
    }

    //
    // Documentation links
    // -------------------
    // Get the doc links, if any.
    $docLinks = '';
    if (empty($doc) === FALSE) {
      // Run through the given list of Drupal.org documentation pages.
      // Each one has a page name and a page title.
      $links = '';
      $n = count($doc);
      $i = 0;
      foreach ($doc as $pageName => $pageTitle) {
        ++$i;
        $link = Utilities::createDocLink($pageName, $pageTitle);

        if ($i === $n) {
          if ($n === 2) {
            $links .= ' ' . $this->t('and') . ' ';
          }
          elseif ($n > 2) {
            $links .= ', ' . $this->t('and') . ' ';
          }
        }
        elseif ($i !== 1) {
          $links .= ', ';
        }

        $links .= $link;
      }

      // Assemble this into "(See this, that, and whatever documentation)".
      // We can't call t() with an argument containing the links because
      // t() "sanitizes" those links into text, rather than leaving them
      // as HTML.
      $seeText = $this->t('See the');
      $docText = $this->t('documentation');
      $docLinks = $seeText . ' ' . $links . ' ' . $docText . '.';
    }

    //
    // Assemble
    // --------
    // Put it all together.
    return '<dt>' . $moduleLink . ' ' . $enabledNote . '</dt><dd>' .
      $description . ' ' . $docLinks . ' ' . $coreNote . '</dd>';
  }

  /*---------------------------------------------------------------------
   *
   * Build form.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds a form to adjust the module settings.
   *
   * The form has multiple vertical tabs, each built by a separate function.
   *
   * @param array $form
   *   An associative array containing the renderable structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   (optional) The current state of the form.
   *
   * @return array
   *   The form renderable array.
   */
  public function buildForm(
    array $form,
    FormStateInterface $formState = NULL) {

    //
    // Vertical tabs
    // -------------
    // Setup vertical tabs. For these to work, all of the children
    // must be of type 'details' and refer to the 'tabs' group.
    $form['tabs'] = [
      '#type'     => 'vertical_tabs',
      '#attached' => [
        'library' => [
          Constants::LIBRARY_MODULE,
          Constants::LIBRARY_ADMIN,
        ],
      ],
    ];

    // Create each of the tabs.
    $this->buildAboutTab($form, $formState, 'tabs', 'about', $this->t('About'));
    $this->buildFieldsTab($form, $formState, 'tabs', 'fields', $this->t('Fields'));
    $this->buildFilesTab($form, $formState, 'tabs', 'files', $this->t('Files'));
    $this->buildInterfaceTab($form, $formState, 'tabs', 'interface', $this->t('Interface'));
    $this->buildSearchTab($form, $formState, 'tabs', 'search', $this->t('Search'));
    $this->buildSystemTab($form, $formState, 'tabs', 'system', $this->t('System'));
    $this->buildServicesTab($form, $formState, 'tabs', 'webservices', $this->t('Web services'));

    // Build and return the form.
    return parent::buildForm($form, $formState);
  }

  /*---------------------------------------------------------------------
   *
   * Validate.
   *
   *---------------------------------------------------------------------*/

  /**
   * Validates the form values.
   *
   * @param array $form
   *   The form configuration.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The entered values for the form.
   */
  public function validateForm(array &$form, FormStateInterface $formState) {

    $this->validateAboutTab($form, $formState);
    $this->validateFieldsTab($form, $formState);
    $this->validateFilesTab($form, $formState);
    $this->validateInterfaceTab($form, $formState);
    $this->validateSearchTab($form, $formState);
    $this->validateServicesTab($form, $formState);
    $this->validateSystemTab($form, $formState);
  }

  /*---------------------------------------------------------------------
   *
   * Submit.
   *
   *---------------------------------------------------------------------*/

  /**
   * Stores the submitted form values.
   *
   * @param array $form
   *   The form configuration.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The entered values for the form.
   */
  public function submitForm(array &$form, FormStateInterface $formState) {

    parent::submitForm($form, $formState);

    $this->submitAboutTab($form, $formState);
    $this->submitFieldsTab($form, $formState);
    $this->submitFilesTab($form, $formState);
    $this->submitInterfaceTab($form, $formState);
    $this->submitSearchTab($form, $formState);
    $this->submitServicesTab($form, $formState);
    $this->submitSystemTab($form, $formState);
  }

}
