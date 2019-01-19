<?php

namespace Drupal\foldershare\Entity\Builder;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Drupal\foldershare\Constants;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Builds a page breadcrumb showing the ancestors of the given entity.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * This class builds a breadcrumb that includes a chain of links to
 * each of the ancestors of the given entity. The chain starts with a link
 * to the site's home page. This is followed by a link to the canonical
 * root folder list page. The remaining links lead to ancestors of the
 * entity. Per Drupal convention, the entity itself is not included on
 * the end of the breadcrumb.
 *
 * <b>Service:</b>
 * A service for this breadcrumb builder should be registered in
 * MODULE.services.yml.
 *
 * <b>Parameters:</b>
 * The service for this breadcrumb builder must pass a FolderShare entity.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\FolderShare
 */
class FolderShareBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /*---------------------------------------------------------------------
   *
   * Fields.
   *
   * These fields cache values from construction and dependency injection.
   *
   *---------------------------------------------------------------------*/

  /**
   * The entity storage manager, set at construction time.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The route provider, set at construction time.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The current user account, set at construction time.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /*---------------------------------------------------------------------
   *
   * Construct.
   *
   *---------------------------------------------------------------------*/

  /**
   * Constructs the bread crumb builder.
   *
   * The arguments here must match those in the service declaration in
   * MODULE.services.yml.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service for the FolderShare entity type.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider services.
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user account.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RouteProviderInterface $routeProvider,
    AccessManagerInterface $accessManager,
    AccountInterface $currentUser) {

    $this->entityStorage = $entityTypeManager->getStorage(FolderShare::ENTITY_TYPE_ID);
    $this->currentUser   = $currentUser;
    $this->routeProvider = $routeProvider;
  }

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $routeMatch) {
    //
    // There are several cases where this breadcrumb builder applies:
    //
    // - The route names the root folder groups page, or one of the individual
    //   group pages.
    //
    // - The route names a single FolderShare entity, such as for a view
    //   or edit page.
    //
    // - The route is for a "command" form that works with a selection
    //   of FolderShare entities, such as for delete, rename, copy, move,
    //   or change owner.
    //
    $routeName = $routeMatch->getRouteName();

    switch ($routeName) {
      case Constants::ROUTE_FOLDERSHARE_COMMAND_FORM:
        // The route is for a command form. Get the encoded parameter,
        // which should be there for all command form routes.
        $encoded = $routeMatch->getRawParameter('encoded');
        return ($encoded !== NULL);

      case Constants::ROUTE_FOLDERSHARE:
        // The route is for an entity page. There should be a FolderShare
        // entity ID.
        $entity = $routeMatch->getParameter(FolderShare::ENTITY_TYPE_ID);
        return ($entity instanceof FolderShareInterface);

      case Constants::ROUTE_ROOT_ITEMS_PERSONAL:
      case Constants::ROUTE_ROOT_ITEMS_PUBLIC:
      case Constants::ROUTE_ROOT_ITEMS_ALL:
        // The route is for page listing root items.
        return TRUE;

      default:
        // The route is not recognized.
        return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $routeMatch) {
    //
    // There are three cases this breadcrumb builder needs to handle:
    //
    // - The route names a single FolderShare entity, such as for a view
    //   or edit page.
    //
    // - The route is for a "command" form that works with a selection
    //   of FolderShare entities, such as for delete, rename, copy, move,
    //   or change owner.
    //
    // - The route is for FolderShare, but there is no entity ID. This can
    //   be for the main root folder groups page, or for one of the group
    //   pages.
    //
    $routeName = $routeMatch->getRouteName();

    switch ($routeName) {
      case Constants::ROUTE_FOLDERSHARE_COMMAND_FORM:
        // The route is for a command form.
        return $this->buildForCommandForm($routeMatch);

      case Constants::ROUTE_FOLDERSHARE:
        // The route is for an entity page. There should be a FolderShare
        // entity ID.
        $entity = $routeMatch->getParameter(FolderShare::ENTITY_TYPE_ID);
        if ($entity !== NULL) {
          return $this->buildForEntityPage($routeMatch, $entity);
        }

        // The route is for an entity, but the entity isn't ours. This
        // should not occur because the applies() method earlier should
        // already have rejected such a page.
        return $this->buildDefault($routeMatch);

      case Constants::ROUTE_ROOT_ITEMS_PERSONAL:
      case Constants::ROUTE_ROOT_ITEMS_PUBLIC:
      case Constants::ROUTE_ROOT_ITEMS_ALL:
        // The route is for page listing root items.
        return $this->buildForRootListPage($routeMatch);

      default:
        // The route is not recognized. This should not occur because
        // the applies() method earlier should already have rejected
        // such a page.
        return $this->buildDefault($routeMatch);
    }
  }

  /**
   * Builds an array of breadcrumb links when there is no entity or form.
   *
   * When a page is for FolderShare, but it doesn't have an entity ID or
   * command form parameters, we cannot build a breadcrumb of ancestor
   * links. Instead, this function returns an abbreviated breadcrumb that
   * only includes a home page link and a root folder list link.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route description, including the name of the route and its
   *   parameters.
   *
   * @return \Drupal\Core\Link[]
   *   Returns an array of links for the breadcrumb, from the site's home
   *   page to the current FolderShare entity.
   */
  private function buildDefault(RouteMatchInterface $routeMatch) {
    //
    // Cache control
    // -------------
    // Breadcrumbs vary per user because viewing permissions
    // vary per user role and per folder.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheableDependency($this->currentUser);
    $breadcrumb->addCacheableDependency($routeMatch);

    //
    // Link to home page
    // -----------------
    // The first link goes to the site's home page.
    $links   = [];
    $links[] = Link::createFromRoute($this->t('Home'), '<front>');

    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

  /**
   * Builds an array of breadcrumb links for a root list page.
   *
   * When a page is for a root list, we cannot build a breadcrumb of ancestor
   * links. Instead, this function returns an abbreviated breadcrumb that
   * only includes a home page link.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route description, including the name of the route and its
   *   parameters.
   *
   * @return \Drupal\Core\Link[]
   *   Returns an array of links for the breadcrumb, from the site's home
   *   page to the current FolderShare entity.
   */
  private function buildForRootListPage(
    RouteMatchInterface $routeMatch) {
    //
    // Cache control
    // -------------
    // Breadcrumbs vary per user because viewing permissions
    // vary per user role and per folder.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheableDependency($this->currentUser);
    $breadcrumb->addCacheableDependency($routeMatch);

    //
    // Link to home page
    // -----------------
    // The first link goes to the site's home page.
    $links   = [];
    $links[] = Link::createFromRoute($this->t('Home'), '<front>');

    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

  /**
   * Builds an array of breadcrumb links for a FolderShare entity page.
   *
   * FolderShare entity pages are those that view or edit a single entity
   * and where the ID of the entity is a well-known argument on the URL.
   *
   * For these pages, this function gets the ancestors of the entity and
   * returns an array of links to those ancestors. The first link in the
   * returned array is, by convention, for the site's home page. The next
   * link is for the canonical root folder list. The next links are for
   * ancestors, from the root folder to the parent of the page's item.
   * The last link in the returned array is for the page's item.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route description, including the name of the route and its
   *   parameters.
   * @param \Drupal\foldershare\FolderShareInterface $item
   *   The FolderShare entity for the breadcrumbs.
   * @param bool $includeItem
   *   When TRUE, the current FolderShare entity (whether a file or a folder)
   *   is included at the end of the breadcrumb. When FALSE, it is not.
   *
   * @return \Drupal\Core\Link[]
   *   Returns an array of links for the breadcrumb, from the site's home
   *   page to the current FolderShare entity.
   */
  private function buildForEntityPage(
    RouteMatchInterface $routeMatch,
    FolderShareInterface $item,
    bool $includeItem = FALSE) {

    //
    // Cache control
    // -------------
    // Breadcrumbs vary per user because viewing permissions
    // vary per user role and per folder.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheableDependency($this->currentUser);
    $breadcrumb->addCacheableDependency($routeMatch);

    //
    // Link to home page.
    // ------------------
    // Link to the site's home page.
    $links   = [];
    $links[] = Link::createFromRoute($this->t('Home'), '<front>');

    //
    // Link to root list page.
    // -----------------------
    // Link to one of the root list pages.
    //
    // Decide on the the appropriate group by looking at the root of the
    // current item.
    $rootItem = $item->getRootItem();
    switch ($rootItem->getSharingStatus()) {
      default:
      case 'private':
        // Root is owned by someone else and it has not been shared with
        // the current user.  Since the entity is being viewed anyway,
        // presumably the current user has admin permission.
        if ($this->currentUser->hasPermission(Constants::ADMINISTER_PERMISSION) === TRUE) {
          $r = Constants::ROUTE_ROOT_ITEMS_ALL;
        }
        else {
          $r = Constants::ROUTE_ROOT_ITEMS_PERSONAL;
        }
        break;

      case 'shared by you':
      case 'shared with you':
      case 'personal':
        // Root is owned by this user. Use the personal group.
        $r = Constants::ROUTE_ROOT_ITEMS_PERSONAL;
        break;

      case 'public':
        // Root folder is owned by anonymous or shared with anonymous.
        // Use the public group.
        $r = Constants::ROUTE_ROOT_ITEMS_PUBLIC;
        break;
    }

    $links[] = Link::createFromRoute(
      $this->routeProvider->getRouteByName($r)->getDefault('_title'),
      $r);

    //
    // Link to ancestors
    // -----------------
    // When VIEWing a file or folder, the breadcrumb ends with the
    // parent folder.
    //
    // When EDITing a file or folder, the breadcrumb ends with the action
    // after the item name.
    //
    // Distinguishing between these is a little awkward. VIEW routes
    // do not have a fixed title because the title comes
    // via a callback to get the item's title.  For EDIT routes,
    // there is a fixed title.
    //
    // Get ancestors of this item. The list does not include this item.
    // The first entry in the list is the root.
    $ancestors = $item->findAncestorFolders();

    // Loop through the ancestors, starting at the root. For each one,
    // add a link to the item's page.
    $routeName = Constants::ROUTE_FOLDERSHARE;
    $routeParam = Constants::ROUTE_FOLDERSHARE_ID;

    foreach ($ancestors as $ancestor) {
      // Breadcrumb cacheing also depends on this ancestor.
      $breadcrumb->addCacheableDependency($ancestor);

      // The BreadcrumbBuilderInterface that this class is implementing,
      // and the build() method in particular, is required to return a
      // Breadcrumb object. And that object is strictly a list of Link
      // objects.
      //
      // This is a problem here because an ancestor might not provide
      // 'view' access to this account. If it does not, we'd like to
      // return straight text instead of a link, since clicking on
      // the link would get an error anyway.
      //
      // Unfortunately, there is no way to do this. We must return
      // a Link, regardless of viewing permissions.
      //
      // No need to HTML escape the folder name here. This is done
      // automatically by Link.
      $links[] = Link::createFromRoute(
        $ancestor->getName(),
        $routeName,
        [$routeParam => (int) $ancestor->id()]);
    }

    // The last item in the link array is either:
    // - The entity's page if the current route is for editing it.
    // - A parent entity when a command is operating on its children.
    $route = $routeMatch->getRouteObject();
    $addedLast = FALSE;

    if ($route !== NULL) {
      $breadcrumb->addCacheableDependency($route);
      $title = $route->getDefault('_title');
      if (empty($title) === FALSE) {
        // Yes, the route has a title. This is for an edit page.
        // Add a link to the view page for the entity.
        $links[] = Link::createFromRoute(
          $item->getName(),
          $routeName,
          [$routeParam => $item->id()]);

        $addedLast = TRUE;
      }
    }

    if ($includeItem === TRUE && $addedLast === FALSE) {
      // This is a command and we're showing its edit/confirmation form.
      // Add a link to the view page for the entity.
      $links[] = Link::createFromRoute(
        $item->getName(),
        $routeName,
        [$routeParam => $item->id()]);
    }

    // Breadcrumbs vary per item.
    $breadcrumb->addCacheableDependency($item);

    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

  /**
   * Builds an array of breadcrumb links for a FolderShare command form.
   *
   * FolderShare command forms are a response from a command plugin.
   * Such plugins are invoked from the GUI menu and they may have multiple
   * values, including:
   * - an optional parent folder.
   * - an optional destination folder (such as for move and copy).
   * - an optional selection.
   *
   * The selection may have any number of FolderShare entity IDs, with any
   * mix of kinds. There may be no selection. And there may be no parent
   * or destination.
   *
   * All of these parameters are encoded as a single cryptic argument on
   * the command form URL - because they are too much and too complex to
   * encode with ? arguments, and because it would encourage users to fiddle
   * with them. This means they all come through as a single "encoded"
   * argument on the route.
   *
   * This function returns breadcrumbs based upon the decoded argument.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route description, including the name of the route and its
   *   parameters.
   *
   * @return \Drupal\Core\Link[]
   *   Returns an array of links for the breadcrumb, from the site's home
   *   page to the current FolderShare entity.
   */
  private function buildForCommandForm(RouteMatchInterface $routeMatch) {
    //
    // Command parameters
    // ------------------
    // Get the encoded parameters and decode them.
    $encoded = $routeMatch->getRawParameter('encoded');
    $parameters = (array) json_decode(base64_decode($encoded), TRUE);

    // If there are no parameters (which should not be possible), then
    // we can only build a generic abbreviated breadcrumb.
    if (empty($parameters) === TRUE ||
        isset($parameters['configuration']) === FALSE) {
      return $this->buildDefault($routeMatch);
    }

    $configuration = (array) $parameters['configuration'];

    // If there is a selection, and the selection has just one entity ID,
    // then we can build a normal breadcrumb using that entity ID.
    if (isset($configuration['selectionIds']) === TRUE) {
      $selectionIds = $configuration['selectionIds'];

      if (count($selectionIds) === 1) {
        $itemId = $selectionIds[0];
        $item = FolderShare::load($itemId);
        if ($item === NULL) {
          // The selection ID is bad. Revert to the default breadcrumb.
          return $this->buildDefault($routeMatch);
        }

        // Use the item to build an entity page breadcrumb.
        return $this->buildForEntityPage($routeMatch, $item, TRUE);
      }

      // Otherwise the selection is empty or it has more than one items.
    }

    // If there is a parent, use it.
    if (isset($configuration['parentId']) === TRUE) {
      $parentId = $configuration['parentId'];
      $item = FolderShare::load($parentId);
      if ($item === NULL) {
        // The parent ID is bad. Revert to the default breadcrumb.
        return $this->buildDefault($routeMatch);
      }

      // Use the item to build an entity page breadcrumb.
      return $this->buildForEntityPage($routeMatch, $item, TRUE);
    }

    // There is no selection and no parent. It doesn't make sense to use
    // the destination ID, so fall back to the default breadcrumb.
    return $this->buildDefault($routeMatch);
  }

}
