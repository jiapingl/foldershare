<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Component\Plugin\PluginManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Creates a form for the search user interface associated with a view.
 *
 * While a site may have a site-wide generic search feature (often found in
 * the site's page header), THIS search UI provides localized search through
 * a folder tree. The search starts on the folder currently being displayed.
 *
 * The search form includes a search text field and hidden search submit
 * button. On a carriage-return in the search text field, the search is
 * submitted.
 *
 * The search form is disabled if:
 * - Drupal's core "search" module is not enabled.
 * - FolderShare's search plugin is not available.
 * - The current user does not have "search content" permission.
 * - The page's entity is disabled or hidden.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 */
final class UISearchBox extends FormBase {

  use RedirectDestinationTrait;
  use LoggerChannelTrait;

  /*--------------------------------------------------------------------
   *
   * Fields.
   *
   *--------------------------------------------------------------------*/

  /**
   * The module handler, set at construction time.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The search plugin manager, if any, set at construction time.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  private $searchPluginManager;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new form.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $searchPluginManager
   *   The manager of search plugins, including FolderShare's own.
   */
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    PluginManagerInterface $searchPluginManager) {

    $this->moduleHandler = $moduleHandler;
    $this->searchPluginManager = $searchPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.search')
    );
  }

  /*--------------------------------------------------------------------
   *
   * Form setup.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $name = str_replace('\\', '_', get_class($this));
    return mb_convert_case($name, MB_CASE_LOWER);
  }

  /*--------------------------------------------------------------------
   *
   * Form build.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $formState = NULL) {
    //
    // Validate search possible
    // ------------------------
    // Check that the Drupal core Search module is enabled and that this
    // module's own search plugin is available. If not, there is no search
    // form.
    if ($this->moduleHandler->moduleExists('search') === FALSE) {
      // When the search module is not enabled, do not return a search form.
      return NULL;
    }

    if ($this->searchPluginManager === NULL ||
        $this->searchPluginManager->hasDefinition(Constants::SEARCH_PLUGIN) === FALSE) {
      // This is odd. The search module is enabled, which should automatically
      // install this module's search plugin. Something is broken. Log the
      // error, but don't tell the user since that's just confusing.
      $this->getLogger(Constants::MODULE)->emergency($this->t(
        "The module's '@pluginName' search plugin is missing.\nPlease notify the site administrator.",
        [
          '@pluginName' => Constants::SEARCH_PLUGIN,
        ]));
      return NULL;
    }

    $user = $this->currentUser();
    if ($user->hasPermission('search content') === FALSE) {
      // When the user does not have search permission, do not return a
      // search form.
      return NULL;
    }

    // Get parent entity ID, if any.
    $args = $formState->getBuildInfo()['args'];
    if (empty($args) === TRUE || $args[0] === '') {
      // No parent entity. Default to showing a root list.
      $parentId = FolderShareInterface::USER_ROOT_LIST;
      $parent   = NULL;
      $disabled = FALSE;
    }
    else {
      // Parent entity ID should be the sole argument. Load it.
      // Loading could fail and return a NULL if the ID is bad.
      $parentId = (int) $args[0];
      $parent   = FolderShare::load($parentId);
      $disabled = $parent->isSystemDisabled() || $parent->isSystemHidden();
    }

    //
    // Define form classes
    // -------------------
    // Define classes used to mark the form and its items. These classes
    // are then used in CSS to style the form.
    $form['#attributes']['class'][] = 'foldershare-searchbox-form';
    $form['#attributes']['role'] = 'search';

    $uiClass       = 'foldershare-searchbox';
    $submitClass   = $uiClass . '-submit';
    $keywordsClass = $uiClass . '-keywords';

    //
    // Create UI
    // ---------
    // The UI is wrapped in a container annotated with parent attributes.
    // Children of the container include a search field and a submit button.
    $form[$uiClass] = [
      '#type'              => 'container',
      '#weight'            => -100,
      '#name'              => $uiClass,
      '#attributes'        => [
        'class'            => [
          $uiClass,
        ],
      ],

      // Add a search field.
      $keywordsClass       => [
        '#type'            => 'search',
        '#size'            => 15,
        '#name'            => $keywordsClass,
        '#default_value'   => '',
        '#disabled'        => $disabled,
        '#attributes'      => [
          'placeholder'    => $this->t('Search folder...'),
          // Adding 'results' triggers addition of the magnifying glass
          // icon on Webkit-based browsers (e.g. Safari).
          'results'        => '',
          'aria-label'     => $this->t('Search through files and folders'),
          'class'          => [
            $keywordsClass,
          ],
        ],
      ],

      // Add a submit button. The button is always hidden and is triggered
      // automatically by a carriage-return on the search field.
      $submitClass         => [
        '#type'            => 'submit',
        '#value'           => $this->t('Search'),
        '#name'            => $submitClass,
        '#disabled'        => $disabled,
        '#attributes'      => [
          'class'          => [
            'hidden',
            $submitClass,
          ],
        ],
      ],
    ];

    return $form;
  }

  /*--------------------------------------------------------------------
   *
   * Form validate
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState) {
    // Do nothing.
  }

  /*--------------------------------------------------------------------
   *
   * Form submit
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    //
    // Validate
    // --------
    // If the search module is not enabled or the module's search plugin
    // cannot be found, then abort.
    if ($this->moduleHandler->moduleExists('search') === FALSE) {
      // No search module. This form should not have been created if there
      // was no search module enabled. Silently do nothing.
      return;
    }

    if ($this->searchPluginManager === NULL ||
        $this->searchPluginManager->hasDefinition(Constants::SEARCH_PLUGIN) === FALSE) {
      // No search plugin. This form should not have been created if there
      // was no search plugin. Silently do nothing.
      return;
    }

    $user = $this->currentUser();
    if ($user->hasPermission('search content') === FALSE) {
      // When the user does not have search permission, do not return a
      // search form.
      return NULL;
    }

    // Get parent entity ID, if any.
    $args = $formState->getBuildInfo()['args'];
    if (empty($args) === TRUE || $args[0] === '') {
      // No parent entity. Default to showing a root folder list.
      $parentId = FolderShareInterface::USER_ROOT_LIST;
      $parent   = NULL;
      $disabled = FALSE;
    }
    else {
      // Parent entity ID should be the sole argument. Load it.
      // Loading could fail and return a NULL if the ID is bad.
      $parentId = (int) $args[0];
      $parent   = FolderShare::load($parentId);
      $disabled = $parent->isSystemDisabled() || $parent->isSystemHidden();
    }

    // Abort if disabled.
    if ($disabled === TRUE) {
      // Item disabled so cannot search it and its children.
      return;
    }

    //
    // Search
    // ------
    // Redirect to a search results page for the module's search plugin.
    // Pass keywords and the parent ID as URL query parameters.
    $uiClass       = 'foldershare-searchbox';
    $keywordsClass = $uiClass . '-keywords';

    $keywords = $formState->getValue($keywordsClass);
    if (empty($keywords) === TRUE) {
      // No search keywords. Do nothing.
      return;
    }

    $formState->setRedirect(
      'search.view_' . Constants::SEARCH_PLUGIN,
      [],
      [
        'query' => [
          'keys'     => $keywords,
          'parentId' => $parentId,
        ],
      ]
    );
  }

}
