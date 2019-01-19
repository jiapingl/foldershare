<?php

namespace Drupal\foldershare\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessManager;
use Drupal\Core\Routing\RouteProvider;

use Drupal\views\Views;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Form\UIFolderTableMenu;
use Drupal\foldershare\Form\UISearchBox;

/**
 * Creates pages showing a user interface and a list of root items.
 *
 * This controller's methods create a page body with a user
 * interface form and an embedded view that shows a list of root items.
 *
 * The root items listed depend upon the page method called, and the
 * underlying embedded view:
 *
 * - listAllRootItems(): show all root items, regardless of ownership.
 *
 * - listPersonalAndSharedRootItems(): show all root items owned by the
 *   current user, or shared with the current user.
 *
 * - listPublicRootItems(): show all root items owned by or shared with
 *   anonymous.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\FolderShare
 * @see \Drupal\foldershare\Form\UIFolderTableMenu
 * @see \Drupal\foldershare\Form\UISearchBox
 */
class RootListController extends ControllerBase {

  /*--------------------------------------------------------------------
   *
   * Fields - dependency injection.
   *
   *--------------------------------------------------------------------*/

  /**
   * The current user account, set at construction time.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The route provider, set at construction time.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected $routeProvider;

  /**
   * The access manager, set at construction time.
   *
   * @var \Drupal\Core\AccessManager
   */
  protected $accessManager;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new page.
   */
  public function __construct(
    RouteProvider $routeProvider,
    AccountInterface $currentUser,
    AccessManager $accessManager) {

    $this->routeProvider = $routeProvider;
    $this->currentUser = $currentUser;
    $this->accessManager = $accessManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('current_user'),
      $container->get('access_manager'));
  }

  /*--------------------------------------------------------------------
   *
   * Root list pages.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns a page listing all root items.
   *
   * The view associated with this page lists all root items owned by
   * anybody, regardless of share settings.
   *
   * @return array
   *   A renderable array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an exception if the user is not an administrator.
   */
  public function listAllRootItems() {
    return $this->buildPage(
      Constants::VIEW_LISTS,
      Constants::VIEW_DISPLAY_LIST_ALL,
      FolderShareInterface::ALL_ROOT_LIST);
  }

  /**
   * Returns a page listing root items owned by or shared with the user.
   *
   * The view associated with this page lists all root items owned by
   * the current user.
   *
   * @return array
   *   A renderable array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an exception if the the view display does not allow access.
   */
  public function listPersonalAndSharedRootItems() {
    return $this->buildPage(
      Constants::VIEW_LISTS,
      Constants::VIEW_DISPLAY_LIST_PERSONAL,
      FolderShareInterface::USER_ROOT_LIST);
  }

  /**
   * Returns a page listing root items owned by/shared with anonymous.
   *
   * When the module's sharing settings are enabled AND sharing with anonymous
   * is enabled, the view associated with this page lists all root items
   * owned by or shared with anonymous.
   *
   * When the module's sharing settings are disabled for the site or for
   * sharing with anonymous, the view associated with this page lists all
   * root items owned by anonymous. Root folders marked as shared with
   * anonymous are not listed.
   *
   * @return array
   *   A renderable array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an exception if the the view display does not allow access.
   */
  public function listPublicRootItems() {
    return $this->buildPage(
      Constants::VIEW_LISTS,
      Constants::VIEW_DISPLAY_LIST_PUBLIC,
      FolderShareInterface::PUBLIC_ROOT_LIST);
  }

  /**
   * Builds and returns a renderable array describing a view page.
   *
   * Arguments name the view and display to use. If the view or display
   * do not exist, a 'misconfigured web site' error message is logged and
   * the user is given a generic error message. If the display does not
   * allow access, an access denied exception is thrown.
   *
   * Otherwise, a page is generated that includes a user interface above
   * an embed of the named view and display.
   *
   * @param string $viewName
   *   The name of the view to embed in the page.
   * @param string $displayName
   *   The name of the view display to embed in the page.
   * @param int $rootListId
   *   The root list ID.
   *
   * @return array
   *   A renderable array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an exception if the named display's access controls
   *   do not allow access.
   */
  private function buildPage(
    string $viewName,
    string $displayName,
    int $rootListId) {
    //
    // View setup
    // ----------
    // Find the embedded view and display, confirming that both exist and
    // that the user has access. Generate errors if something is wrong.
    $error = FALSE;
    $view = NULL;

    if (($view = Views::getView($viewName)) === NULL) {
      // Unknown view!
      $this->getLogger(Constants::MODULE)->emergency($this->t(
        "Misconfigured web site. The required '@viewName' view is missing.\nPlease check the views module configuration and, if needed, restore the view using the module's settings page.",
        [
          '@viewName'   => $viewName,
        ]));
      $error = TRUE;
    }
    elseif ($view->setDisplay($displayName) === FALSE) {
      // Unknown display!
      $this->getLogger(Constants::MODULE)->emergency($this->t(
        "Misconfigured web site. The required '@displayName' display for the '@viewName' view is missing.\nPlease check the views module configuration and, if needed, restore the view using the module's settings page.",
        [
          '@viewName'    => $viewName,
          '@displayName' => $displayName,
          '@moduleName'  => Constants::MODULE,
        ]));
      $error = TRUE;
    }
    elseif ($view->access($displayName) === FALSE) {
      // User does not have access. Access denied.
      throw new AccessDeniedHttpException();
    }

    //
    // Error page
    // ----------
    // If the view could not be found, there is nothing to embed and there
    // is no point in adding a UI. Return an error message in place of the
    // view's content.
    if ($error === TRUE) {
      return [
        '#attached' => [
          'library' => [
            Constants::LIBRARY_MODULE,
          ],
        ],
        '#attributes' => [
          'class'   => [
            Constants::MODULE . '-error',
          ],
        ],

        // Do not cache this page. If any of the above conditions change,
        // the page needs to be regenerated.
        '#cache' => [
          'max-age' => 0,
        ],

        // Return an error message.
        'error'     => [
          '#type'   => 'item',
          '#markup' => $this->t(
            "The web site has encountered a problem with this page.\nPlease report this to the site administrator."),
        ],
      ];
    }

    //
    // Search box UI
    // -------------
    // If the UI is enabled, create the search form to include below.
    // This will fail if the Search module is not enabled, the search plugin
    // cannot be found, or if the user does not have search permission.
    $searchBoxForm = NULL;
    if (Constants::ENABLE_UI_SEARCH_BOX === TRUE) {
      $searchBoxForm = $this->formBuilder()->getForm(UISearchBox::class);
    }

    //
    // Ancestor menu UI
    // ----------------
    // If the UI is enabled, create the form to include below.
    $ancestorMenu = NULL;
    if (Constants::ENABLE_UI_ANCESTOR_MENU === TRUE) {
      $ancestorMenu = Utilities::createAncestorMenu();
    }

    //
    // Folder table menu UI
    // --------------------
    // If the UI is enabled, create the UI form to include below.
    $folderTableMenuForm = NULL;
    if (Constants::ENABLE_UI_COMMAND_MENU === TRUE) {
      $folderTableMenuForm = $this->formBuilder()->getForm(
        UIFolderTableMenu::class,
        $rootListId);
    }

    //
    // Build view
    // ----------
    // Assemble parts of the page, including the UI forms and embedded view.
    //
    // When the main UI is disabled, revert to the base user interface
    // included with the embedded view. Just add the view to the page, and
    // no Javascript-based main UI.
    //
    // When the main UI is enabled, attach Javascript and the main UI
    // form before the view. The form adds hidden fields used by the
    // Javascript.
    $page = [
      '#theme'        => Constants::THEME_VIEW,
      '#attached'     => [
        'library'     => [
          Constants::LIBRARY_MODULE,
        ],
      ],
      '#attributes'   => [
        'class'       => [
          Constants::MODULE . '-view',
          Constants::MODULE . '-view-page',
        ],
      ],

      // Do not cache this page. If anybody adds or removes a folder or
      // changes sharing, the view will change and the page needs to
      // be regenerated.
      '#cache'        => [
        'max-age'     => 0,
      ],

      'toolbar-and-folder-table' => [
        '#type'       => 'container',
        '#attributes' => [
          'class'     => [
            'foldershare-toolbar-and-folder-table',
          ],
        ],

        'toolbar'       => [
          '#type'       => 'container',
          '#attributes' => [
            'class'     => [
              'foldershare-toolbar',
            ],
          ],

          // Add the folder table menu UI.
          'foldertablemenu' => $folderTableMenuForm,

          // Add the ancestor menu UI.
          'ancestormenu' => $ancestorMenu,

          // Add the search box UI.
          'searchbox'   => $searchBoxForm,
        ],

        // Add the view with the base UI overridden by the main UI.
        'view'          => [
          '#type'       => 'view',
          '#embed'      => TRUE,
          '#name'       => $viewName,
          '#display_id' => $displayName,
          '#arguments'  => [$rootListId],
          '#attributes' => [
            'class'     => [
              'foldershare-folder-table',
            ],
          ],
        ],
      ],
    ];

    if (Constants::ENABLE_UI_COMMAND_MENU === TRUE) {
      $page['#attached']['library'][] = Constants::LIBRARY_MODULE;
      $page['#attached']['drupalSettings'] = [
        Constants::MODULE . '-view-page' => [
          'viewName'        => $viewName,
          'displayName'     => $displayName,
          'viewAjaxEnabled' => $view->ajaxEnabled(),
        ],
      ];
    }
    else {
      unset($page['foldershare-toolbar-and-folder-table']['foldershare-toolbar']['foldertablemenu']);
    }

    if (Constants::ENABLE_UI_ANCESTOR_MENU === FALSE) {
      unset($page['foldershare-toolbar-and-folder-table']['foldershare-toolbar']['ancestormenu']);
    }

    if (Constants::ENABLE_UI_SEARCH_BOX === FALSE) {
      unset($page['foldershare-toolbar-and-folder-table']['foldershare-toolbar']['searchbox']);
    }

    return $page;
  }

}
