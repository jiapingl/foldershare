<?php

namespace Drupal\foldershare\Entity\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\views\Views;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Form\UIFolderTableMenu;
use Drupal\foldershare\Form\UISearchBox;

/**
 * Presents a view of a FolderShare entity.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * This class builds an entity view page that presents the fields,
 * pseudo-fields, list of child entities, and user interface for
 * FolderShare entities.
 *
 * The module's MODULE.module class defines pseudo-field hooks that forward
 * to this class to define pseudo-fields for:
 *
 * - A path of ancestor entities.
 * - A table of an entity's child content and its user interface.
 *
 * The child contents table is created using an embedded view.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\FolderShare
 * @see \Drupal\foldershare\Form\UIFolderTableMenu
 * @see \Drupal\foldershare\Form\UISearchBox
 * @see \Drupal\foldershare\Entity\Builder\FolderShareViewBuilder
 */
class FolderShareViewController extends EntityViewController {

  /*---------------------------------------------------------------------
   *
   * Pseudo-fields.
   *
   * Module hooks in MODULE.module define pseudo-fields by delegating to
   * these functions.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns an array of pseudo-fields.
   *
   * Pseudo-fields show information derived from an entity, rather
   * than information stored explicitly in an entity field. We define:
   *
   * - The path of folder ancestors.
   * - The table of an entity's child content.
   *
   * @return array
   *   A nested array of pseudo-fields with keys and values that specify
   *   the characteristics and treatment of pseudo-fields.
   */
  public static function getEntityExtraFieldInfo() {
    $display = [
      // A '/' separated path to the current item. Path components are
      // all links to ancestor folders.
      //
      // This defaults to not being visible because it is redundant if a
      // site has a breadcrumb on the page.
      'folder_path'    => [
        'label'        => t('Path'),
        'description'  => t('Names and links for ancestor folders.'),
        'weight'       => 100,
        'visible'      => FALSE,
      ],

      // A table of child files and folders. Items in the table include
      // links to child items.
      //
      // This defaults to being visible because there is little value in
      // showing a folder without showing its contents too. This exists
      // as a pseudo-field so that site administrators can use the Field UI
      // and its 'Manage display' form to adjust the order in which fields
      // and this table are presented on a page.
      'folder_table'   => [
        'label'        => t('Folder contents table'),
        'description'  => t('Table of child files and folders.'),
        'weight'       => 110,
        'visible'      => TRUE,
      ],

      // The sharing status.
      'sharing_status' => [
        'label'        => t('Status'),
        'description'  => t('Sharing status'),
        'weight'       => 120,
        'visible'      => FALSE,
      ],
    ];

    // The returned array is indexed by the entity type, bundle,
    // and display/form configuration.
    $entityType = FolderShare::ENTITY_TYPE_ID;
    $bundleType = $entityType;

    $extras = [];
    $extras[$entityType] = [];
    $extras[$entityType][$bundleType] = [];
    $extras[$entityType][$bundleType]['display'] = $display;

    return $extras;
  }

  /**
   * Returns a renderable description of an entity and its pseudo-fields.
   *
   * Pseudo-fields show information derived from an entity, rather
   * than information stored explicitly in an entity field. We handle:
   *
   * - The ancestor path as a string.
   * - The table of an entity's child content.
   *
   * @param array $page
   *   The initial rendering array modified by this method and returned.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being presented.
   * @param \Drupal\Core\Entity\Entity\EntityViewDisplay $display
   *   The field_ui display to use.
   * @param string $viewMode
   *   (optional) The field_ui view mode to use.
   * @param string $langcode
   *   (optional) The language code to use.
   *
   * @return array
   *   The given page array modified to contain the renderable
   *   pseudo-fields for a folder presentation.
   */
  public static function getFolderShareView(
    array &$page,
    EntityInterface $entity,
    EntityViewDisplay $display,
    string $viewMode,
    string $langcode = '') {
    //
    // There are several view modes defined through common use in Drupal core:
    //
    // - 'full' = generate a full view.
    // - 'search_index' = generate a keywords-only view.
    // - 'search_result' = generate an abbreviated search result view.
    //
    // Each of the pseudo-fields handle this in their own way.
    if ($display->getComponent('folder_path') !== NULL) {
      self::addPathPseudoField(
        $page,
        $entity,
        $display,
        $viewMode,
        $langcode);
    }

    if ($display->getComponent('folder_table') !== NULL) {
      self::addViewPseudoField(
        $page,
        $entity,
        $display,
        $viewMode,
        $langcode);
    }

    if ($display->getComponent('sharing_status') !== NULL) {
      self::addSharingPseudoField(
        $page,
        $entity,
        $display,
        $viewMode,
        $langcode);
    }

    // Add a class to mark the outermost element for the view of the
    // entity. This element contains the pseudo-fields added above,
    // plus all other fields being displayed for the entity. This
    // element also includes, nested within, the toolbar, search box,
    // and embedded view, and all user interface elements.
    //
    // This class is used by the Javascript UI library to
    // find the top-level element containing content for the user interface.
    $page['#attributes']['class'][] = 'foldershare-view';

    return $page;
  }

  /**
   * Adds an ancestor path of folder links to the page array.
   *
   * Several well-defined view modes are recognized:
   *
   * - 'full' = generate a full view
   * - 'search_index' = generate a keywords-only view
   * - 'search_result' = generate an abbreviated search result view
   *
   * For the 'search_index' and 'search_result' view modes, this function
   * returns immediately without adding anything to the build. The folder
   * path has no place in a search index or in search results.
   *
   * For the 'full' view mode, and all other view modes, this function
   * returns the folder path.
   *
   * Folder names on a path are always separated by '/', per the convention
   * for web URLs, and for Linux and macOS directory paths.  There
   * is no configuration setting to change this path presentation
   * to use a '\' per Windows convention.
   *
   * If the folder parameter is NULL, a simplified path with a single
   * link to the root list is added to the build.
   *
   * @param array $page
   *   A render array into which we insert our element.
   * @param \Drupal\foldershare\FolderShareInterface $item
   *   (optional) The item for which we generate a path render element.
   * @param \Drupal\Core\Entity\Entity\EntityViewDisplay $display
   *   The field_ui display to use.
   * @param string $viewMode
   *   (optional) The field_ui view mode to use.
   * @param string $langcode
   *   (optional) The language code to use.
   *
   * @todo This function calls Utilities::createFolderPath() to get markup
   * for an ancestor path with links. But it should also add a cacheable
   * dependency on each of the ancestors so that if any of them change,
   * the pseudo field and page it is on can be rebuilt.
   */
  private static function addPathPseudoField(
    array &$page,
    FolderShareInterface $item = NULL,
    EntityViewDisplay $display = NULL,
    string $viewMode = 'full',
    string $langcode = '') {
    //
    // For search view modes, do nothing. The folder path has no place
    // in a search index or in search results.
    if ($viewMode === 'search_index' || $viewMode === 'search_result') {
      return;
    }

    // Get the weight for this component.
    $component = $display->getComponent('folder_path');
    if (isset($component['weight']) === TRUE) {
      $weight = $component['weight'];
    }
    else {
      $weight = 0;
    }

    $name = 'foldershare-folder-path';
    $page[$name] = [
      '#name'   => $name,
      '#type'   => 'item',
      '#prefix' => '<div class="' . $name . '">',
      '#markup' => Utilities::createFolderPath($item, ' / ', FALSE),
      '#suffix' => '</div>',
      '#weight' => $weight,
    ];
  }

  /**
   * Adds a sharing status field.
   *
   * @param array $page
   *   A render array into which we insert our element.
   * @param \Drupal\foldershare\FolderShareInterface $item
   *   (optional) The item for which we generate a path render element.
   * @param \Drupal\Core\Entity\Entity\EntityViewDisplay $display
   *   The field_ui display to use.
   * @param string $viewMode
   *   (optional) The field_ui view mode to use.
   * @param string $langcode
   *   (optional) The language code to use.
   */
  private static function addSharingPseudoField(
    array &$page,
    FolderShareInterface $item = NULL,
    EntityViewDisplay $display = NULL,
    string $viewMode = 'full',
    string $langcode = '') {

    //
    // For search view modes, do nothing. The sharing status is a fake
    // field that has no usefulness in a search index or in search results.
    if ($viewMode === 'search_index' || $viewMode === 'search_result') {
      return;
    }

    // Get the weight for this component.
    $component = $display->getComponent('sharing_status');
    if (isset($component['weight']) === TRUE) {
      $weight = $component['weight'];
    }
    else {
      $weight = 0;
    }

    // Get the sharing status and convert it to a message.
    $owner = $item->getOwner();
    $sharingStatus = $item->getSharingStatus();
    switch ($sharingStatus) {
      case 'personal':
        // Personal files. This is the most common case, and one that therefore
        // does not need a message.
        return [];

      case 'public':
        $sharingMessage = t(
          'Shared with everyone by @displayName',
          [
            '@displayName' => $owner->getDisplayName(),
          ]);
        break;

      case 'shared by you':
        $sharingMessage = t(
          'Shared by you');
        break;

      case 'private':
        $currentUserId = (int) \Drupal::currentUser()->id();
        if ($currentUserId == $owner->id()) {
          $sharingMessage = t('Owned by you');
        }
        else {
          $sharingMessage = t(
            'Owned by @displayName',
            [
              '@displayName' => $owner->getDisplayName(),
            ]);
        }
        break;

      case 'shared with you':
        $root = $item->getRootItem();
        $rootOwner = $root->getOwner();
        $sharingMessage = t(
          'Shared with you by @displayName',
          [
            '@displayName' => $rootOwner->getDisplayName(),
          ]);
        break;
    }

    $name = 'foldershare-sharing-status';
    $page[$name] = [
      '#name'   => $name,
      '#type'   => 'container',
      '#attributes' => [
        'class' => [
          $name,
          'field',
        ],
      ],
      'item'  => [
        '#type'   => 'html_tag',
        '#tag'    => 'div',
        '#attributes' => [
          'class' => ['field__item'],
        ],
        '#value' => $sharingMessage,
      ],
      '#weight' => $weight,
    ];
  }

  /**
   * Adds to the page a table showing a list of the item's children.
   *
   * Several well-defined view modes are recognized:
   *
   * - 'full' = generate a full view
   * - 'search_index' = generate a keywords-only view
   * - 'search_result' = generate an abbreviated search result view
   *
   * For the 'search_index' and 'search_result' view modes, this function
   * returns immediately without adding anything to the build. The contents
   * view has no place in a search index or in search results.
   *
   * For the 'full' view mode, and all other view modes, this function
   * returns the contents view.
   *
   * @param array $page
   *   A render array into which we insert our element.
   * @param \Drupal\foldershare\FolderShareInterface $item
   *   (optional) The item for which we generate a contents render element.
   * @param \Drupal\Core\Entity\Entity\EntityViewDisplay $display
   *   The field_ui display to use.
   * @param string $viewMode
   *   (optional) The field_ui view mode to use.
   * @param string $langcode
   *   (optional) The language code to use.
   */
  private static function addViewPseudoField(
    array &$page,
    FolderShareInterface $item,
    EntityViewDisplay $display = NULL,
    string $viewMode = 'full',
    string $langcode = '') {

    //
    // Setup
    // -----
    // For search view modes, do nothing. The contents view has
    // no place in a search index or in search results.
    if ($viewMode === 'search_index' || $viewMode === 'search_result') {
      return;
    }

    // Get the weight for this component.
    $component = $display->getComponent('folder_table');
    $weight = 0;
    if (isset($component['weight']) === TRUE) {
      $weight = $component['weight'];
    }

    //
    // View setup
    // ----------
    // Find the embedded view and display, confirming that both exist and
    // that the user has access. Log errors if something is wrong.
    $error       = FALSE;
    $view        = NULL;
    $viewName    = Constants::VIEW_LISTS;
    $displayName = Constants::VIEW_DISPLAY_LIST_FOLDER;

    if (($view = Views::getView($viewName)) === NULL) {
      // Unknown view!
      \Drupal::logger(Constants::MODULE)->emergency(
        "Misconfigured web site. The required view \"@viewName\" is missing.\nPlease check the views module configuration and, if needed, restore the view from the @moduleName module's settings page.",
        [
          '@viewName'   => $viewName,
          '@moduleName' => Constants::MODULE,
        ]);
      $error = TRUE;
    }
    elseif ($view->setDisplay($displayName) === FALSE) {
      // Unknown display!
      \Drupal::logger(Constants::MODULE)->emergency(
        "Misconfigured web site. The required \"@displayName\" display for the \"@viewName\" view is missing.\nPlease check the views module configuration and, if needed, restore the view from the @moduleName module's settings page.",
        [
          '@viewName'    => $viewName,
          '@displayName' => $displayName,
          '@moduleName'  => Constants::MODULE,
        ]);
      $error = TRUE;
    }
    elseif ($view->access($displayName) === FALSE) {
      // Access denied to view display.
      $error = TRUE;
    }

    $pageName = 'foldershare-folder-table';

    //
    // Error page
    // ----------
    // If the view could not be found, there is nothing to embed and there
    // is no point in adding a UI. Return an error message in place of the
    // view's content.
    if ($error === TRUE) {
      $page[$pageName] = [
        '#attributes' => [
          'class'   => [
            'foldershare-error',
          ],
        ],

        // Do not cache this page. If any of the above conditions change,
        // the page needs to be regenerated.
        '#cache' => [
          'max-age' => 0,
        ],

        '#weight'   => $weight,

        'error'     => [
          '#type'   => 'item',
          '#markup' => t(
            "The web site has encountered a problem with this page.\nPlease report this to the site administrator."),
        ],
      ];
      return;
    }

    //
    // Prefix
    // ------
    // If the item is NOT a folder, create a prefix and suffix that marks
    // the view accordingly. CSS can then hide irrelevant parts of the UI.
    //
    // If the item is NOT a folder, then there's no point in having a
    // folder tree search box either.
    if ($item->isFolder() === FALSE) {
      $viewPrefix = '<div class="foldershare-nonfolder-table">';
      $viewSuffix = '</div';
      $showSearch = FALSE;
    }
    else {
      $viewPrefix = $viewSuffix = '';
      $showSearch = Constants::ENABLE_UI_SEARCH_BOX;
    }

    //
    // Search box UI
    // -------------
    // If the search UI is enabled, create the search form to include below.
    // This will fail if the Search module is not enabled, the search plugin
    // cannot be found, or if the user does not hava search permission.
    $searchBoxForm = NULL;
    if ($showSearch === TRUE) {
      $searchBoxForm = \Drupal::formBuilder()->getForm(
        UISearchBox::class,
        $item->id());
    }

    //
    // Ancestor menu UI
    // ----------------
    // If the ancestor menu UI is enabled, create the form to include below.
    $ancestorMenu = NULL;
    if (Constants::ENABLE_UI_ANCESTOR_MENU === TRUE) {
      $ancestorMenu = Utilities::createAncestorMenu($item->id());
    }

    //
    // Main UI
    // -------
    // If the main UI is enabled, create the UI form to include below.
    $folderTableMenuForm = NULL;
    if (Constants::ENABLE_UI_COMMAND_MENU === TRUE) {
      $folderTableMenuForm = \Drupal::formBuilder()->getForm(
        UIFolderTableMenu::class,
        $item->id());
    }

    //
    // Build view
    // ----------
    // Assemble parts of the view pseudo-field, including the prefix and
    // suffix, the UI forms, and the embeded view.
    $page[$pageName] = [
      '#weight'       => $weight,
      '#prefix'       => $viewPrefix,
      '#suffix'       => $viewSuffix,
      '#attributes' => [
        'class'     => [
          'foldershare-view-page',
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

        // Add the view with the base UI overridden by the folder table UI.
        'view'          => [
          '#type'       => 'view',
          '#embed'      => TRUE,
          '#name'       => $viewName,
          '#display_id' => $displayName,
          '#arguments'  => [$item->id()],
          '#attributes' => [
            'class'     => [
              'foldershare-folder-table',
            ],
          ],
        ],
      ],
    ];

    if (Constants::ENABLE_UI_COMMAND_MENU === TRUE) {
      $page[$pageName]['#attached'] = [
        'drupalSettings' => [
          'foldershare-view-page' => [
            'viewName'        => $viewName,
            'displayName'     => $displayName,
            'viewAjaxEnabled' => $view->ajaxEnabled(),
          ],
        ],
      ];
    }
    else {
      unset($page[$pageName]['foldershare-toolbar-and-folder-table']['foldershare-toolbar']['foldertablemenu']);
    }

    if (Constants::ENABLE_UI_ANCESTOR_MENU === FALSE) {
      unset($page[$pageName]['foldershare-toolbar-and-folder-table']['foldershare-toolbar']['ancestormenu']);
    }

    if ($showSearch === FALSE) {
      unset($page[$pageName]['foldershare-toolbar-and-folder-table']['foldershare-toolbar']['searchbox']);
    }
  }

  /*---------------------------------------------------------------------
   *
   * Page.
   *
   * These functions build the entity view.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the title of the indicated entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $foldershare
   *   The entity for which the title is needed.  NOTE: This function is
   *   the target of a route with an entity ID argument. The name of the
   *   function argument here *must be* named after the entity
   *   type: 'foldershare'.
   *
   * @return string
   *   The page title.
   */
  public function title(EntityInterface $foldershare) {
    // While folders are translatable, folder names are explicit text
    // that should not be translated. Return the folder name as plain text.
    //
    // The name does not need to be HTML escaped here because the caller
    // handles that.
    return $foldershare->getName();
  }

  /**
   * Builds and returns a renderable array describing an entity.
   *
   * Each of the fields shown by the selected view mode are added
   * to the renderable array in the proper order.  Pseudo-fields
   * for the contents table and folder path are added as appropriate.
   *
   * @param \Drupal\Core\Entity\EntityInterface $foldershare
   *   The entity being shown.  NOTE: This function is the target of
   *   a route with an entity ID argument. The name of the function
   *   argument here *must be* named after the entity type: 'foldershare'.
   * @param string $viewMode
   *   (optional) The name of the view mode. Defaults to 'full'.
   *
   * @return array
   *   A Drupal renderable array.
   */
  public function view(EntityInterface $foldershare, $viewMode = 'full') {
    //
    // The parent class sets a few well-known keys:
    // - #entity_type: the entity's type (e.g. "foldershare")
    // - #ENTITY_TYPE: the entity, where ENTITY_TYPE is "foldershare"
    //
    // The parent class invokes the view builder, and both of them
    // set up pre-render callbacks:
    // - #pre_render: callbacks executed at render time
    //
    // The EntityViewController adds a callback to buildTitle() to
    // set the page title.
    //
    // The EntityViewBuilder adds a callback to build() to build
    // the fields for the page.
    //
    // Otherwise, the returned page array has nothing in it.  All
    // of the real work of adding fields is deferred until render
    // time when the builder's build() callback is called.
    if ($foldershare->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($foldershare->isSystemDisabled() === TRUE) {
      // Disabled items cannot be viewed.
      throw new AccessDeniedHttpException();
    }

    $page = parent::view($foldershare, $viewMode);

    // Add the theme and attach libraries.
    $page['#theme'] = Constants::THEME_FOLDER;

    $page['#attached'] = [
      'library' => [
        Constants::LIBRARY_MODULE,
      ],
    ];

    if (Constants::ENABLE_UI_COMMAND_MENU === TRUE) {
      $page['#attached']['library'][] = Constants::LIBRARY_MODULE;
    }

    return $page;
  }

}
