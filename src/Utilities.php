<?php

namespace Drupal\foldershare;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Utility\Html;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Render\Markup;
use Drupal\user\Entity\User;

use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines utility functions used throughout the module.
 *
 * This class defines a variety of utility functions in several groups:
 * - Create links from routes or for help or Drupal documentation pages.
 * - Create standardized content.
 * - Create standard terminology text.
 * - Revert configurations.
 * - Help search indexing.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 */
final class Utilities {

  /*--------------------------------------------------------------------
   *
   * Link functions.
   *
   * These functions assist in creating formatted links to other pages
   * at this site, or elsewhere on the web.
   *
   *-------------------------------------------------------------------*/

  /**
   * Returns an HTML anchor to a routed page.
   *
   * If no title is given, the route's title is used.
   *
   * If the route cannot be found, plain text is returned that contains
   * the title (if given) or the route name (if no title given).
   *
   * @param string $routeName
   *   The name of a route for an enabled module.
   * @param string $target
   *   (optional) The name of a page fragment target (i.e. a "#name",
   *   sans '#').
   * @param string $title
   *   (optional) The title for the link to the route. If empty, the route's
   *   title is used.
   *
   * @return string
   *   The HTML markup for a link to the module's page.
   */
  public static function createRouteLink(
    string $routeName,
    string $target = '',
    string $title = '') {

    // Set up the options array, if a target was provided.
    $options = [];
    if (empty($target) === FALSE) {
      $options['fragment'] = $target;
    }

    // Get the route's title, if no title was provided.
    if (empty($title) === TRUE) {
      $p = \Drupal::service('router.route_provider');
      if ($p === NULL) {
        $title = $routeName;
      }
      else {
        $title = $p->getRouteByName($routeName)->getDefault('_title');
      }
    }
    else {
      $title = $title;
    }

    // Create the link.
    $lnk = Link::createFromRoute($title, $routeName, [], $options);
    return ($lnk === NULL) ? $title : $lnk->toString();
  }

  /**
   * Returns an HTML anchor to a routed help page.
   *
   * If no title is given, the route's title is used.
   *
   * If the route cannot be found, plain text is returned that contains
   * the title (if given) or the route name (if no title given).
   *
   * @param string $moduleName
   *   (optional) The name of a module target.
   * @param string $title
   *   (optional) The title for the link to the route. If empty, the route's
   *   title is used.
   *
   * @return string
   *   The HTML markup for a link to the module's page.
   */
  public static function createHelpLink(
    string $moduleName,
    string $title = '') {

    // If the help module is not installed, just return the title.
    $mh = \Drupal::service('module_handler');
    if ($mh->moduleExists('help') === FALSE) {
      if (empty($title) === FALSE) {
        return Html::escape($title);
      }

      return Html::escape($moduleName);
    }

    // Set up the options array, if a moduleName was provided.
    $options = [];
    if (empty($moduleName) === FALSE) {
      $options['name'] = $moduleName;
    }

    // Get the route's title, if no title was provided.
    $routeName = Constants::ROUTE_HELP;
    if (empty($title) === TRUE) {
      $p = \Drupal::service('router.route_provider');
      if ($p === NULL) {
        $title = Html::escape($routeName);
      }
      else {
        $title = $p->getRouteByName($routeName)->getDefault('_title');
      }
    }

    // Create the link.
    $lnk = Link::createFromRoute($title, $routeName, $options);
    return ($lnk === NULL) ? $title : $lnk->toString();
  }

  /**
   * Returns an HTML anchor to a URL.
   *
   * @param string $path
   *   The text URL path.
   * @param string $title
   *   (optional) The title for the link to the page. If empty, the module
   *   name is used.
   *
   * @return string
   *   The HTML markup for a link to the module's documentation.
   */
  public static function createUrlLink(string $path, string $title = '') {
    if (empty($title) === TRUE) {
      $title = Html::escape($path);
    }
    else {
      $title = Html::escape($title);
    }

    $lnk = Link::fromTextAndUrl($title, Url::fromUri($path));

    return ($lnk === NULL) ? $title : $lnk->toString();
  }

  /**
   * Returns an HTML anchor to a Drupal.org module documentation page.
   *
   * If the link cannot be created, plain text is returned that contains
   * the given name only.
   *
   * @param string $moduleName
   *   The name of the module at Drupal.org.
   * @param string $title
   *   (optional) The title for the link to the page. If empty, the module
   *   name is used.
   *
   * @return string
   *   The HTML markup for a link to the module's documentation.
   */
  public static function createDocLink(string $moduleName, string $title = '') {
    if (empty($title) === TRUE) {
      $title = Html::escape($moduleName);
    }
    else {
      $title = Html::escape($title);
    }

    $lnk = Link::fromTextAndUrl(
      $title,
      Url::fromUri(Constants::URL_DRUPAL_CORE_MODULE_DOC . $moduleName));

    return ($lnk === NULL) ? $title : $lnk->toString();
  }

  /*--------------------------------------------------------------------
   *
   * Content.
   *
   * These functions create content pieces that may be included on
   * other pages.
   *
   *-------------------------------------------------------------------*/

  /**
   * Creates a folder path with links to ancestor folders.
   *
   * The path to the given item is assembled as an ordered list of HTML
   * links, starting with a link to the root folder list and ending with
   * the parent of the given folder. The folder itself is not included
   * in the path.
   *
   * Links on the path are separated by the given string, which defaults
   * to '/'.
   *
   * If the item is NULL or a root folder, the returned path only contains
   * the link to the root folder list.
   *
   * @param \Drupal\foldershare\FolderShareInterface $item
   *   (optional) The item to create the path for, or NULL to create a path
   *   for to the root folder list.
   * @param string $separator
   *   (optional, default '/') The text to place between links on the path.
   * @param bool $includeFolder
   *   (optional, default FALSE) When TRUE, include the folder itself at
   *   the end of the path.
   * @param bool $createLinks
   *   (optional, default TRUE) When TRUE, create a link to each folder on
   *   the path.
   *
   * @return string
   *   The HTML for a series of links to the item's ancestors.
   *
   * @deprecated This function is no longer needed by copy and move forms,
   * and is now only used by FolderShareViewController. Because cache
   * dependencies need to be created, that class should create the path
   * itself instead of using this function. This function will be removed
   * in a future release.
   *
   * @todo This function builds markup for an ancestor path with links.
   * However, any page that uses this should also add a cacheable dependency
   * for every ancestor, and yet those ancestors are not returned. This
   * function and places that use it need to be re-thought out.
   */
  public static function createFolderPath(
    FolderShareInterface $item = NULL,
    string $separator = ' / ',
    bool $includeFolder = TRUE,
    bool $createLinks = TRUE) {

    $routeProvider = \Drupal::service('router.route_provider');

    //
    // Link to root items page.
    // ------------------------
    // The page lists root items.
    $r = Constants::ROUTE_ROOT_ITEMS_PERSONAL;
    if ($createLinks === TRUE) {
      $groupLink = Utilities::createRouteLink($r);
    }
    else {
      $groupLink = t($routeProvider->getRouteByName($r)->getDefault('_title'));
    }

    //
    // Base cases
    // ----------
    // Two base cases exist:
    // - The route has no entity ID.
    // - The route is for a root folder group page.
    //
    // In both cases, return an abbreviated path that only contains links
    // to the groups page and specific group page.
    $path = $groupLink;
    if ($item === NULL) {
      return $path;
    }

    //
    // Root item only
    // --------------
    // When the entity is a root item, show an abbreviated path that
    // includes links to the groups page and specific group page. If
    // requested, include the root item at the end.
    if ($item->isRootItem() === TRUE && $includeFolder === FALSE) {
      return $path;
    }

    //
    // Subfolders
    // ----------
    // For other items, show a chain of links starting with
    // the root list, then root folder, then ancestors down to (but not
    // including) the item itself.
    $links = [];
    $folders = [];

    if ($item->isRootItem() === FALSE) {
      // Get ancestors of this item. The list does not include this item.
      // The first entry in the list is the root.
      $folders = $item->findAncestorFolders();

      // Add the item itself, if needed.
      if ($includeFolder === TRUE) {
        $folders[] = $item;
      }
    }
    else {
      $folders[] = $item;
    }

    // Get the current user.
    $currentUser = \Drupal::currentUser();

    // Loop through the folders, starting at the root. For each one,
    // create a link to the folder's page.
    foreach ($folders as $id => $f) {
      if ($createLinks === FALSE || $item->id() === $id ||
          $f->access('view', $currentUser) === FALSE) {
        // Either we aren't creating links, or the item in the list is the
        // current folder, or the user doesn't have view access.  In any
        // case, just show the item as plain text, not a link.
        //
        // The folder name needs to be escaped manually here.
        $links[] = Html::escape($item->getName());
      }
      else {
        // Create a link to the folder.
        //
        // No need to HTML escape the view title here. This is done
        // automatically by Link.
        $links[] = Link::createFromRoute(
          $f->getName(),
          Constants::ROUTE_FOLDERSHARE,
          [Constants::ROUTE_FOLDERSHARE_ID => $id])->toString();
      }
    }

    // Create the markup.
    return $path . $separator . implode($separator, $links);
  }

  /**
   * Creates a render description for an ancestor menu.
   *
   * The last item in the ancestor menu is a list of available root lists
   * for the user:
   * - Personal files.
   * - Public files.
   * - All files.
   *
   * The anonoymous user always sees only the "public" list, regardless of
   * the value of the $allowPublicRootList argument.
   *
   * The "all" list is only included if the user has module admin permissions.
   *
   * The "public" list is only included if $allowPublicRootList is TRUE and
   * anonymous users (and thus all users) have permission to view content.
   *
   * @param int $currentId
   *   (optional, default = FolderShareInterface::USER_ROOT_LIST) The integer
   *   entity ID of the current FolderShare item. If the value is negative or
   *   FolderShareInterface::USER_ROOT_LIST, if the current item is
   *   the user's root list.
   * @param bool $allowPublicRootList
   *   (optional, default = TRUE) When TRUE, the public root list is available.
   *
   * @return array
   *   Returns an array of renderable elements for an ancestor menu's
   *   entries.
   */
  public static function createAncestorMenu(
    int $currentId = FolderShareInterface::USER_ROOT_LIST,
    bool $allowPublicRootList = TRUE) {
    //
    // Load entity.
    // ------------
    // Load the current entity, if any.
    $currentItem = NULL;
    if ($currentId >= 0) {
      $currentItem = FolderShare::load($currentId);
      if ($currentItem === NULL) {
        $currentId = FolderShareInterface::USER_ROOT_LIST;
      }
    }

    $uiClass         = 'foldershare-ancestormenu';
    $menuButtonClass = $uiClass . '-menu-button';
    $menuClass       = $uiClass . '-menu';

    //
    // Add ancestor list markup.
    // -------------------------
    // Build a list of ancestor folders for the ancestor menu. For each one,
    // include the ancestor's URL as an attribute. Javascript will use this
    // to load the appropriate page. The URL is not included in an <a> for
    // the menu item so that menu items aren't styled as links.
    //
    // File classes are added so that themes can style the item with
    // folder icons.
    $menuMarkup = "<ul class=\"hidden $menuClass\">";

    if ($currentItem !== NULL) {
      // The page is for entity. Get its ancestors.
      $folders = $currentItem->findAncestorFolders();

      // Reverse ancestors from root first to root last.
      $folders = array_reverse($folders);

      // Push the current entity onto the ancestor list so that it gets
      // included in the menu.
      array_unshift($folders, $currentItem);

      // Add ancestors to menu.
      foreach ($folders as $item) {
        // Get the URL to the folder.
        $url = rawurlencode($item->toUrl(
          'canonical',
          [
            'absolute' => TRUE,
          ])->toString());

        // Get the name for the folder.
        $name = Html::escape($item->getName());

        // Add the HTML. Include file classes that mark this as a folder
        // or file.
        if ($item->isFolder() === TRUE) {
          $fileClasses = 'file file--folder file--mime-folder-directory';
        }
        else {
          $mimes = explode('/', $item->getMimeType());
          $fileClasses = 'file file--' . $mimes[0] .
            ' file--mime-' . $mimes[0] . '-' . $mimes[1];
        }

        if ($item->isSystemDisabled() === TRUE) {
          $attr = '';
        }
        else {
          $attr = 'data-foldershare-id="' . $item->id() . '"';
        }

        $menuMarkup .= "<li $attr data-foldershare-url=\"$url\"><div><span class=\"$fileClasses\"></span>$name</div></li>";
      }
    }

    //
    // Add root list.
    // --------------
    // Show a list of available root lists in a submenu. If there is only
    // one available root list, then don't use a submenu.
    $currentUser = \Drupal::currentUser();

    // Start by assembling a list of available root lists for this user.
    $rootLists = [];
    if ($currentUser->isAnonymous() === TRUE) {
      // The anonymous user can only view the public list.
      $rootLists[] = [
        "public",
        Constants::ROUTE_ROOT_ITEMS_PUBLIC,
      ];
    }
    elseif ($currentUser->hasPermission(Constants::ADMINISTER_PERMISSION) === TRUE) {
      // An admin user can view all of the lists.
      $rootLists[] = [
        "personal",
        Constants::ROUTE_ROOT_ITEMS_PERSONAL,
      ];

      if ($allowPublicRootList === TRUE) {
        $rootLists[] = [
          "public",
          Constants::ROUTE_ROOT_ITEMS_PUBLIC,
        ];
      }

      $rootLists[] = [
        "all",
        Constants::ROUTE_ROOT_ITEMS_ALL,
      ];
    }
    else {
      // Authenticated non-admin users can view their personal list.
      $rootLists[] = [
        "personal",
        Constants::ROUTE_ROOT_ITEMS_PERSONAL,
      ];

      if ($allowPublicRootList === TRUE) {
        // If the anonymous user has view permission, include the public list.
        $anonymousUser = User::getAnonymousUser();
        if ($anonymousUser->hasPermission(Constants::VIEW_PERMISSION) === TRUE) {
          $rootLists[] = [
            "public",
            Constants::ROUTE_ROOT_ITEMS_PUBLIC,
          ];
        }
      }
    }

    // Build markup for the available root lists.
    $routeProvider = \Drupal::service('router.route_provider');
    if (count($rootLists) === 1) {
      $rootListName = $rootLists[0][0];
      $rootListRoute = $rootLists[0][1];

      $route = $routeProvider->getRouteByName($rootListRoute);
      $url = Url::fromRoute(
        $rootListRoute,
        [],
        ['absolute' => TRUE])->toString();

      $title = t($route->getDefault('_title'))->render();
      $fileClasses = 'file file--folder file--mime-rootfolder-group-directory';
      $attr = "data-foldershare-id=\"$rootListName\" data-foldershare-url=\"$url\"";

      $menuMarkup .= "<li $attr><div><span class=\"$fileClasses\"></span>$title</div></li>";
    }
    else {
      $title = (string) t('Lists');
      $menuMarkup .= "<li><div>$title</div><ul>";

      foreach ($rootLists as $rootList) {
        $rootListName = $rootList[0];
        $rootListRoute = $rootList[1];

        $route = $routeProvider->getRouteByName($rootListRoute);
        $url = Url::fromRoute(
          $rootListRoute,
          [],
          ['absolute' => TRUE])->toString();

        $title = t($route->getDefault('_title'))->render();
        $fileClasses = 'file file--folder file--mime-rootfolder-group-directory';
        $attr = "data-foldershare-id=\"$rootListName\" data-foldershare-url=\"$url\"";
        $menuMarkup .= "<li $attr><div><span class=\"$fileClasses\"></span>$title</div></li>";
      }
      $menuMarkup .= "</ul></li>";
    }

    $menuMarkup .= '</ul>';

    //
    // Create menu button.
    // -------------------
    // Create HTML for a button. Include:
    //
    // - Class 'hidden' so that the button is initially hidden and only shown
    //   later by Javascript, if the browser supports scripting.
    $buttonText = (string) t('Ancestors');
    $buttonMarkup = "<button type=\"button\" class=\"hidden $menuButtonClass\"><span>$buttonText</span></button>";

    //
    // Create UI
    // ---------
    // Everything is hidden initially, and only exposed by Javascript, if
    // the browser supports Javascript.
    $renderable = [
      '#attributes' => [
        'class'     => [
          'foldershare-ancestormenu',
        ],
      ],
      $uiClass => [
        '#type'              => 'container',
        '#weight'            => -90,
        '#attributes'        => [
          'class'            => [
            $uiClass,
            'hidden',
          ],
        ],

        // Add a hierarchical menu of ancestors. Javascript uses jQuery.ui
        // to build a menu from this and presents it from a menu button.
        // The menu was built and marked as hidden.
        //
        // Implementation note: The field is built using an inline template
        // to avoid Drupal's HTML cleaning that can remove classes and
        // attributes on the menu items, which we need to retain to provide
        // the URLs of ancestor folders. Those URLs are used by Javascript
        // to load the appropriate page when a menu item is selected.
        $menuClass           => [
          '#type'            => 'inline_template',
          '#template'        => '{{ menu|raw }}',
          '#context'         => [
            'menu'           => $menuMarkup,
          ],
        ],

        // Add a button to go up a folder. Javascript binds a behavior
        // to the button to load the parent page. The button is hidden
        // initially and only shown if the browser supports Javascript.
        //
        // Implementation note: The field is built using an inline template
        // so that we get a <button>. If we used the '#type' 'button',
        // Drupal instead creates an <input>. Since we specifically want a
        // button so that jQuery.button() will button-ize it, we have to
        // bypass Drupal.
        $menuButtonClass       => [
          '#type'            => 'inline_template',
          '#template'        => '{{ button|raw }}',
          '#context'         => [
            'button'         => $buttonMarkup,
          ],
        ],
      ],
    ];

    return $renderable;
  }

  /*--------------------------------------------------------------------
   *
   * Translations
   *
   *-------------------------------------------------------------------*/

  /**
   * Returns a translation of the FolderShare entity kind in singular form.
   *
   * Standard entity kinds are mapped to their corresponding terms,
   * as set on the module's settings page.
   *
   * @param string $kind
   *   The kind of a FolderShare entity.
   * @param int $caseMode
   *   (optional, default = MB_CASE_LOWER) The mix of case for the returned
   *   value. One of MB_CASE_UPPER, MB_CASE_LOWER, or MB_CASE_TITLE.
   *
   * @return string
   *   The user-visible term for the singular kind.
   */
  public static function translateKind(
    string $kind,
    int $caseMode = MB_CASE_LOWER) {

    $term = '';
    switch ($kind) {
      case FolderShare::FILE_KIND:
        $term = t('file');
        break;

      case FolderShare::IMAGE_KIND:
        $term = t('image');
        break;

      case FolderShare::MEDIA_KIND:
        $term = t('media');
        break;

      case FolderShare::FOLDER_KIND:
        $term = t('folder');
        break;

      case 'rootlist':
        $term = t('top-level items');
        break;

      default:
      case 'item':
      case 'items':
        $term = t('item');
        break;
    }

    return mb_convert_case($term, $caseMode);
  }

  /**
   * Returns a translation of the FolderShare entity kind in plural form.
   *
   * Standard entity kinds are mapped to their corresponding terms,
   * as set on the module's settings page.
   *
   * @param string $kind
   *   The kind of a FolderShare entity.
   * @param int $caseMode
   *   (optional, default = MB_CASE_LOWER) The mix of case for the returned
   *   value. One of MB_CASE_UPPER, MB_CASE_LOWER, or MB_CASE_TITLE.
   *
   * @return string
   *   The user-visible term for the plural kind.
   */
  public static function translateKinds(
    string $kind,
    int $caseMode = MB_CASE_LOWER) {

    $term = '';
    switch ($kind) {
      case FolderShare::FILE_KIND:
        $term = t('files');
        break;

      case FolderShare::IMAGE_KIND:
        $term = t('images');
        break;

      case FolderShare::MEDIA_KIND:
        $term = t('media');
        break;

      case FolderShare::FOLDER_KIND:
        $term = t('folders');
        break;

      case 'rootlist':
        $term = t('top-level items');
        break;

      default:
      case 'item':
      case 'items':
        $term = t('items');
        break;
    }

    return mb_convert_case($term, $caseMode);
  }

  /*---------------------------------------------------------------------
   *
   * Formatting.
   *
   *---------------------------------------------------------------------*/

  /**
   * Formats a number with a bytes suffix.
   *
   * @param mixed $number
   *   The number to format.
   * @param int $kunit
   *   (optional, default = 1000) Either 1000 (ISO standard kilobyte) or
   *   1024 (legacy kibibyte).
   * @param bool $fullWord
   *   (optional, default = FALSE) When FALSE, use an abbreviation suffix,
   *   like "KB". When TRUE, use a full word suffix, like "Kilobytes".
   * @param int $decimalDigits
   *   (optional, default = 2) The number of decimal digits for the resulting
   *   number.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Returns a representation of the number, along with a suffix
   *   that summarizes the value using the selected 'k' units.
   *
   * @internal
   * Drupal's format_size() function is widely used to format values in
   * units of kilobytes, megabytes, etc. Unfortunately, it considers a
   * "kilobyte" equal to 1024 bytes, which is not compliant with the
   * 1998 International Electrotechnical Commission (IEC) and
   * International System of Quantities (ISO) standards. By those standards,
   * a "kilobyte" equals 1000 bytes. This definition retains the SI units
   * convention that "k" is for 1000 (e.g. kilometer, kilogram).
   *
   * The following code is loosely based upon format_size(). It corrects
   * the math for the selected "k" units (kilo or kibi) and uses the
   * corresponding correct suffixes. It also supports a requested number
   * of decimal digits.
   *
   * The following code also ignores the $langcode, which format_size()
   * incorrectly uses to make the output translatable - even though it is
   * NOT subject to translation since it is numeric and the suffixes are
   * defined by international standards.
   * @endinternal
   */
  public static function formatBytes(
    $number,
    int $kunit = 1000,
    bool $fullWord = FALSE,
    int $decimalDigits = 2) {

    // Validate.
    switch ($kunit) {
      case 1000:
      case 1024:
        $kunit = (float) $kunit;
        break;

      default:
        $kunit = 1000.0;
        break;
    }

    if ($decimalDigits < 0) {
      $decimalDigits = 0;
    }

    if ($number < $kunit) {
      // The quantity is smaller than the 'k' unit. Report it as-is.
      return \Drupal::translation()->formatPlural(
        $number,
        '1 byte',
        '@count bytes');
    }

    // Find the proper 'k'. Values larger than the largest 'k' unit
    // will just be large numbers ahead of the largest prefix (e.g.
    // "879 YB").
    $value = (((float) $number) / $kunit);
    foreach (['K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'] as $unit) {
      if ($value < $kunit) {
        // The value is now less than the next unit, stop reducing.
        break;
      }

      $value /= $kunit;
    }

    // Simplify the remainder with the proper number of decimal digits.
    $fmt = '%1.' . $decimalDigits . 'f';
    $formattedValue = sprintf($fmt, $value);

    // If using abbrevations, return the formatted value and suffix.
    if ($fullWord === FALSE) {
      // Use abbreviations for suffixes.
      switch ($kunit) {
        default:
        case 1000:
          $suffix = $unit . 'B';
          break;

        case 1024:
          $suffix = $unit . 'iB';
          break;
      }

      return new FormattableMarkup(
        '@value @suffix',
        [
          '@value'  => $formattedValue,
          '@suffix' => $suffix,
        ]);
    }

    // When using full words, return the formatted value and word,
    // using singular and plural forms as appropriate.
    $singular = [];
    $plural   = [];
    switch ($kunit) {
      default:
      case 1000:
        $singular = [
          'K' => 'Kilobyte',
          'M' => 'Megabyte',
          'G' => 'Gigabyte',
          'T' => 'Terabyte',
          'P' => 'Petabyte',
          'E' => 'Exabyte',
          'Z' => 'Zettabyte',
          'Y' => 'Yottabyte',
        ];
        $plural = [
          'K' => 'Kilobytes',
          'M' => 'Megabytes',
          'G' => 'Gigabytes',
          'T' => 'Terabytes',
          'P' => 'Petabytes',
          'E' => 'Exabytes',
          'Z' => 'Zettabytes',
          'Y' => 'Yottabytes',
        ];
        break;

      case 1024:
        $singular = [
          'K' => 'Kibibyte',
          'M' => 'Mibibyte',
          'G' => 'Gibibyte',
          'T' => 'Tebibyte',
          'P' => 'Pebibyte',
          'E' => 'Exbibyte',
          'Z' => 'Zebibyte',
          'Y' => 'Yobibyte',
        ];
        $plural = [
          'K' => 'Kibibytes',
          'M' => 'Mibibytes',
          'G' => 'Gibibytes',
          'T' => 'Tebibytes',
          'P' => 'Pebibytes',
          'E' => 'Exbibytes',
          'Z' => 'Zebibytes',
          'Y' => 'Yobibytes',
        ];
        break;
    }

    return new FormattableMarkup(
      '@value @suffix',
      [
        '@value'  => $formattedValue,
        '@suffix' => ($value < 1) ? $singular[$unit] : $plural[$unit],
      ]);
  }

  /**
   * Returns standardized markup for a summary, body, & details message.
   *
   * Formatted messages are intended for use in exceptions or as
   * messages posted on a page via Drupal's messenger. They follow
   * a three-part convention:
   *
   * - Summary - a short statement of the essential issue.
   *
   * - Body - a longer multi-sentence description of the problem and how
   *   a user might solve it.
   *
   * - Details summary - a brief summary of the details section.
   *
   * - Details - a much longer detailed explanation.
   *
   * The summary is required, but the body and details are optional.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $summary
   *   The short summary of the issue.
   * @param string|\Drupal\Component\Render\MarkupInterface $body
   *   (optional, default = NULL = none) The longer description of the issue
   *   and how to resolve it.
   * @param string|\Drupal\Component\Render\MarkupInterface $detailsSummary
   *   (optional, default = NULL = none) A brief summary of the details
   *   section. This could be "Details:", for instance.
   * @param string|\Drupal\Component\Render\MarkupInterface $details
   *   (optional, default = NULL = none) The much longer detailed information.
   *
   * @return \Drupal\Core\Render\Markup
   *   Returns a Markup object suitable for use in messages. Casting the
   *   object to a (string) creates a conventional string message.
   */
  public static function createFormattedMessage(
    $summary,
    $body = NULL,
    $detailsSummary = NULL,
    $details = NULL) {

    $markup = '<span class="foldershare-message-summary">' .
      (string) $summary . '</span>';

    if ($body !== NULL) {
      $markup .= '<p class="foldershare-message-body">' .
        (string) $body . '</p>';
    }

    if ($details !== NULL) {
      $markup .= '<details class="foldershare-message-details">';
      if ($detailsSummary !== NULL) {
        $markup .= '<summary>' . (string) $detailsSummary . '</summary>';
      }

      $markup .= '<p>' . (string) $details . '</p></details>';
    }

    return Markup::create($markup);
  }

  /*--------------------------------------------------------------------
   *
   * Configuration.
   *
   * These functions assist in managing stored site configurations.
   *
   *-------------------------------------------------------------------*/

  /**
   * Loads a named configuration.
   *
   * The named configuration for the indicated entity type is found on
   * disk (in any module), loaded, and returned as a raw nested array
   * of values. Returning the array, rather than a created entity,
   * avoids unnecessarily creating new entities when the caller just
   * wants to see particular values within a configuration, or compare
   * it with another configuration.
   *
   * @param string $entityTypeId
   *   The ID (name) of the entity type.
   * @param string $configName
   *   The name of the desired configuration for the entity type.
   *
   * @return array
   *   Returns a nested associative array containing the keys and values
   *   of the configuration. The structure of the array varies with the
   *   type of configuration. A NULL is returned if the configuration
   *   could not be found. An empty array is returned if the configuration
   *   is found, but it is empty.
   */
  public static function loadConfiguration(
    string $entityTypeId,
    string $configName) {

    // Validate
    // --------
    // The entity type ID and configuration name both must be non-empty.
    if (empty($entityTypeId) === TRUE || empty($configName) === TRUE) {
      // Empty entity type ID or configuration name.
      return NULL;
    }

    // Setup
    // -----
    // Get some values we need.
    $entityTypeManager = \Drupal::entityTypeManager();
    try {
      $entityDefinition = $entityTypeManager->getDefinition($entityTypeId);
    }
    catch (\Exception $e) {
      // Unrecognized entity type.
      return NULL;
    }

    // Create the fully-qualified configuration name formed by adding a
    // configuration prefix for the entity type.
    $fullName = $entityDefinition->getConfigPrefix() . '.' . $configName;

    // Load file
    // ---------
    // Read the configuration from a file. Start by checking the install
    // configuration. If not found there, check the optional configuration.
    $configStorage = \Drupal::service('config.storage');

    $installStorage = new ExtensionInstallStorage(
      $configStorage,
      InstallStorage::CONFIG_INSTALL_DIRECTORY);

    $config = $installStorage->read($fullName);
    if (empty($config) === TRUE) {
      $optionalStorage = new ExtensionInstallStorage(
        $configStorage,
        InstallStorage::CONFIG_OPTIONAL_DIRECTORY);

      $config = $optionalStorage->read($fullName);
      if (empty($config) === TRUE) {
        // Cannot find the configuration file.
        return NULL;
      }
    }

    return $config;
  }

  /**
   * Reverts an entity or core configuration by reloading it from a module.
   *
   * This method supports core and entity configurations. Core configurations
   * have a single site-wide instance, while entity configurations are
   * entity instances with an ID and values for entity fields. For instance,
   * core configurations are used to describe an entity type's forms and
   * and displays, while entity configurations are used to describe each
   * of the views configurations that might have been created by a site.
   *
   * This method looks at the $baseName. If it is 'core', the method loads
   * a core configuration. Otherwise it assumes the name is for an entity
   * type and the method loads a configuration and saves it as either a new
   * entity type instance, or to replace an existing instance.
   *
   * This method looks for either type of configuration in the 'install' or
   * 'optional' folders across all installed modules. If not found,
   * FALSE is returned. If found, the configuration is loaded as above.
   *
   * For entity configurations, this method looks for the entity type
   * definition. If not found, FALSE is returned.
   *
   * @param string $baseName
   *   The base name of the configuration. For entity type's, this is the
   *   entity type ID. For core configurations, this is 'core'.
   * @param string $configName
   *   The name of the desired configuration.
   *
   * @return bool
   *   Returns TRUE if the configuration was reverted. FALSE is returned
   *   if the configuration file could not be found. For entity configurations,
   *   FALSE is returned if the entity type could not be found.
   */
  public static function revertConfiguration(
    string $baseName,
    string $configName) {

    // The base name and configuration name both must be non-empty.
    if (empty($baseName) === TRUE || empty($configName) === TRUE) {
      return FALSE;
    }

    // Revert a core or entity configuration.
    if ($baseName === 'core') {
      // Core configurations SOMETIMES prefix with 'core'. Try that first.
      if (self::revertCoreConfiguration('core.' . $configName) === TRUE) {
        return TRUE;
      }

      // Other core configurations do not prefix with 'core'. Try that.
      return self::revertCoreConfiguration($configName);
    }

    return self::revertEntityConfiguration($baseName, $configName);
  }

  /**
   * Reverts a core configuration by reloading it from a module.
   *
   * Core configurations have a single site-wide instance. The form and
   * display configurations for an entity type's fields, for instance,
   * are core configurations.
   *
   * This method looks for a core configuration in the 'install' or
   * 'optional' folders across all installed modules. If not found,
   * FALSE is returned. If found, the configuration is loaded and used
   * to replace the current core configuration.
   *
   * @param string $configName
   *   The name of the desired core configuration.
   *
   * @return bool
   *   Returns TRUE if the configuration was reverted. FALSE is returned
   *   if the configuration file could not be found.
   */
  private static function revertCoreConfiguration(string $configName) {

    //
    // Load file
    // ---------
    // The configuration file exists in several locations:
    // - The current cached configuration used each time Drupal boots.
    // - The original configuration loaded when the module was installed.
    // - The original optional configuration that might load on module install.
    //
    // We specifically want to replace the current cached configuration,
    // so that isn't the one to load here.
    //
    // We'd prefer to get the installed configuration, but it will not exist
    // if the configuration we're asked to load is for an optional feature
    // that was only added if other non-required modules were installed.
    // In that case, we need to switch from the non-existant installed
    // configuration to the optional configuration and load that.
    //
    // Get the configuration storage service.
    $configStorage = \Drupal::service('config.storage');

    // Try to load the install configuration, if any.
    $installStorage = new ExtensionInstallStorage(
      $configStorage,
      InstallStorage::CONFIG_INSTALL_DIRECTORY);
    $config = $installStorage->read($configName);

    if (empty($config) === TRUE) {
      // The install configuration did not exist. Try to load the optional
      // configuration, if any.
      $optionalStorage = new ExtensionInstallStorage(
        $configStorage,
        InstallStorage::CONFIG_OPTIONAL_DIRECTORY);
      $config = $optionalStorage->read($configName);

      if (empty($config) === TRUE) {
        // Neither the install or optional configuration directories found
        // the configuration we're after. Nothing more we can do.
        return FALSE;
      }
    }

    //
    // Revert
    // ------
    // To revert to the newly loaded configuration, we need to get the
    // current cached ('sync') configuration and replace it.
    $configStorage->write($configName, $config);

    return TRUE;
  }

  /**
   * Reverts an entity configuration by reloading it from a module.
   *
   * Entity configurations are entity instances with an ID and values for
   * entity fields. For instance, since a "view" is an entity instance,
   * the configuration of a specific view is an entity configuration.
   * These are individually stored in cache and in a module's configuration
   * folder.
   *
   * This method looks for an entity configuration in the 'install' or
   * 'optional' folders across all installed modules. If not found,
   * FALSE is returned. If found, the configuration is loaded and used
   * to create a new entity or replace an existing entity with the same
   * ID as that found in the configuration file. If the entity type cannot
   * be found, FALSE is returned.
   *
   * @param string $entityTypeId
   *   The ID (name) of the entity type.
   * @param string $configName
   *   The name of the desired entity configuration.
   *
   * @return bool
   *   Returns TRUE if the configuration was reverted. FALSE is returned
   *   if the entity type cannot be found, or if the configuration file
   *   cannot be found.
   */
  private static function revertEntityConfiguration(
    string $entityTypeId,
    string $configName) {

    // Get entity info
    // ---------------
    // The incoming $entityTypeId names an entity type that has its
    // own storage manager and an entity type definition that we need
    // in order to build the full name of a configuration.
    try {
      // Get the entity's storage and definition.
      $entityTypeManager = \Drupal::entityTypeManager();
      $entityStorage     = $entityTypeManager->getStorage($entityTypeId);
      $entityDefinition  = $entityTypeManager->getDefinition($entityTypeId);
    }
    catch (\Exception $e) {
      // Unrecognized entity type!
      return FALSE;
    }

    //
    // Create name
    // -----------
    // The full name of the configuration uses the given $configName,
    // prefixed with an entity type-specific prefix obtained from
    // the entity type definition.
    $fullName = $entityDefinition->getConfigPrefix() . '.' . $configName;

    //
    // Load file
    // ---------
    // The configuration file exists in several locations:
    // - The current cached configuration used each time Drupal boots.
    // - The original configuration loaded when the module was installed.
    // - The original optional configuration that might load on module install.
    //
    // We specifically want to replace the current cached configuration,
    // so that isn't the one to load here.
    //
    // We'd prefer to get the installed configuration, but it will not exist
    // if the configuration we're asked to load is for an optional feature
    // that was only added if other non-required modules were installed.
    // In that case, we need to switch from the non-existant installed
    // configuration to the optional configuration and load that.
    //
    // Get the configuration storage service.
    $configStorage = \Drupal::service('config.storage');

    // Try to load the install configuration, if any.
    $installStorage = new ExtensionInstallStorage(
      $configStorage,
      InstallStorage::CONFIG_INSTALL_DIRECTORY);
    $config = $installStorage->read($fullName);

    if (empty($config) === TRUE) {
      // The install configuration did not exist. Try to load the optional
      // configuration, if any.
      $optionalStorage = new ExtensionInstallStorage(
        $configStorage,
        InstallStorage::CONFIG_OPTIONAL_DIRECTORY);
      $config = $optionalStorage->read($fullName);

      if (empty($config) === TRUE) {
        // Neither the install or optional configuration directories found
        // the configuration we're after. Nothing more we can do.
        return FALSE;
      }
    }

    //
    // Load entity
    // -----------
    // Entity configurations handled by this method are associated with
    // an entity, unsurprisingly. We need to load that entity as it is now
    // so that we can replace its fields with those loaded from the
    // configuration above.
    //
    // The entity might not exist if someone deleted it. This could happen
    // if a site administrator deleted a 'view' that this method is now
    // being called to restore.
    $idKey = $entityDefinition->getKey('id');
    $id = $config[$idKey];
    $currentEntity = $entityStorage->load($id);

    //
    // Revert
    // ------
    // If there is already an entity, update it with the loaded configuration.
    // And if there is not an entity, create a new one using the loaded
    // configuration.
    if ($currentEntity === NULL) {
      // Create a new entity.
      $newEntity = $entityStorage->createFromStorageRecord($config);
    }
    else {
      // Update the existing entity.
      $newEntity = $entityStorage->updateFromStorageRecord(
        $currentEntity,
        $config);
    }

    // Save the new or updated entity.
    $newEntity->trustData();
    $newEntity->save();

    return TRUE;
  }

  /*--------------------------------------------------------------------
   *
   * Search.
   *
   *-------------------------------------------------------------------*/

  /**
   * Request reindexing of core search.
   *
   * With no argument, this method sweeps through all available search
   * pages and marks each one for re-indexing. With an argument, the
   * indicated search page alone is marked for re-indexing.
   *
   * @param string $entityId
   *   (optional) The ID of a specific search page to reindex. If not
   *   given, or NULL, all search pages are reindexed.
   *
   * @return bool
   *   Returns TRUE if the reindexing request was success. FALSE is
   *   returned if the core Search module is not installed, or if the
   *   indicated entity ID is not found.
   *
   * @deprecated This function is no longer used and will be deleted
   * in a future release.
   */
  public static function searchReindex(string $entityId = NULL) {

    //
    // The core Search module does not have a direct API call to request
    // a reindex of the site. Such reindexing is needed after the search
    // configuration has changed, such as by reverting it to an original
    // configuration.
    //
    // This code roughly mimics code found in the Search module's
    // 'ReindexConfirm' form.
    //
    if (\Drupal::hasService('search.search_page_repository') === FALSE) {
      // Search page repository not found. Search module not installed?
      return FALSE;
    }

    $rep = \Drupal::service('search.search_page_repository');
    if (empty($entityId) === TRUE) {
      // Reindex all search pages.
      foreach ($rep->getIndexableSearchPages() as $entity) {
        $entity->getPlugin()->markForReindex();
      }

      return TRUE;
    }

    // Reindex a single entity.
    foreach ($rep->getIndexableSearchPages() as $entity) {
      if ($entity->id() === $entityId) {
        $entity->getPlugin()->markForReindex();
        return TRUE;
      }
    }

    return FALSE;
  }

}
