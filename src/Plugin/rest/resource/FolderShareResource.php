<?php

namespace Drupal\foldershare\Plugin\rest\resource;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\Plugin\rest\resource\EntityResourceValidationTrait;

use Psr\Log\LoggerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

use Drupal\foldershare\Entity\FolderShare;

/**
 * Responds to FolderShare entity REST GET, POST, PATCH, and DELETE requests.
 *
 * This REST resource provides a web services response for operations
 * related to FolderShare entities. This includes responses to the following
 * standard HTTP requests:
 * - GET: Get a FolderShare entity, list of entities, state, or module settings.
 * - POST: Create a FolderShare entity.
 * - PATCH: Modify a FolderShare entity's fields or sharing settings.
 * - DELETE: Delete a FolderShare entity.
 *
 * FolderShare entities are hierarchical and create a tree of files and
 * folders nested within other folders to arbitrary depth (see the
 * FolderShare entity). Working with this hierarchical necessarily requires
 * that GET operations go beyond requesting a single entity's values based
 * upon that single entity's ID. Instead, this REST resource supports
 * multiple types of GET operations that can request lists of entities,
 * the descendants or ancestors of an entity, the path to an entity, and
 * the sharing settings for a folder tree. Additional GET operations can
 * trigger a search of a folder tree and get non-entity values, such as
 * module settings and usage. Finally, GET operations can download an
 * entity to the local host.
 *
 * POST operations can create a new folder as a top-level folder, or as a
 * child of a specified folder. POST operations can also upload a file or
 * folder, copy a folder tree, or create an archive from a file or folder tree.
 *
 * PATCH operations can modify the values of an entity, such as its name
 * or description, and also move the entity to a different part of the
 * folder tree.
 *
 * DELETE operations can delete a file or folder, including any child files
 * and folders.
 *
 * <B>Routes and URLs</B><BR>
 * GET, PATCH, and DELETE operations all use a canonical URL that must
 * include an entity ID, even for operations that do not require an entity ID.
 * This is an artifact of the REST module's requirement that they all share
 * the same route, and therefore have the same URL structure.
 *
 * POST operations all use a special creation URL that cannot include an
 * entity ID, even for operations that need one, such as creating a subfolder
 * within a selected parent folder. The lack of an entity ID in the URL is
 * an artifact of the REST module's assumption that entities are not related
 * to each other.
 *
 * Since most of FolderShare's GET, POST, and PATCH operations require
 * additional information beyond what is included (or includable) on the URL,
 * this response needs another mechanism for providing that additional
 * information. While POST can receive posted data, GET and PATCH cannot.
 * This leaves us with two possible design solutions:
 * - Add URL query strings.
 * - Add HTTP headers.
 *
 * Both of these designs are possible, but the use of URL query strings is
 * cumbersome for the range and complexity of the information needed. This
 * design therefore uses several custom HTTP headers to provide
 * this information. Most headers are optional and defaults are used if
 * the header is not available.
 *
 * When paths are used, a path has one of these forms:
 * - "DOMAIN:/PATH" = a path relative to a specific content domain.
 * - "/PATH" = a path relative to the "pesonal" content domain.
 *
 * The following domains are recognized:
 * - "pesonal" = (default) the user's own files and folders and those
 *   shared with them.
 * - "public" = the site's public files and folders.
 *
 * The following minimal paths have special meanings:
 * - "pesonal:/" = the user's own root list and those shared with them.
 * - "public:/" = the site's public root list.
 *
 * Some operations require a path to a specific file or folder. In these
 * cases, the operation will return an error with minimal paths that
 * refer to domains of content, rather than a specific entity.
 *
 * <B>Headers</B><BR>
 * Primary headers specify an operation to perform:
 *
 * - X-FolderShare-Get-Operation selects the GET variant, such as whether
 *   to get a single entity, a list of entities, or module settings.
 *
 * - X-FolderShare-Post-Operation selects the POST variant, such as whether
 *   to create a new folder, upload a file, copy a folder
 *   tree, or create an archive.
 *
 * - X-FolderShare-Patch-Operation selects the PATCH variant, such as whether
 *   to modify an entity's fields or move the entity.
 *
 * - X-FolderShare-Delete-Operation selects the DELETE variant.
 *
 * Many GET, PATCH, and DELETE operations require a source, or subject entity
 * to operate upon. By default, the entity is indicated by a non-negative
 * integer entity ID included on the URL. Alternately, the entity may be
 * specified via a folder path string included in the header:
 *
 * - X-FolderShare-Source-Path includes the URL encoded entity path.
 *   When present, this path overrides the entity ID in the URL.
 *
 * Some PATCH operations copy or move an entity to a new location. That
 * location is always specified via a folder path string in another header:
 *
 * - X-FolderShare-Destination-Path includes the URL encoded entity
 *   path to the destination.
 *
 * GET, POST, and PATCH can return information to the client. The structure
 * of that information may be controlled using:
 *
 * - X-FolderShare-Return-Format selects whether to return a serialized entity,
 *   a simplified key-value pair array, an entity ID, a path, or a "Linux"
 *   formatted result.
 *
 * @internal
 * <B>Collision with default entity response</B><BR>
 * The Drupal REST module automatically creates a resource for every entity
 * type, including FolderShare. However, its implementation presumes simple
 * entities that have no linkage into a hierarchy and no context-sensitive
 * constraints on names and other values. If the default response were used,
 * a malicious user could overwrite internal entity fields (like parent,
 * root, and file IDs) and corrupt the hierarchy.  This class therefore
 * provides a proper REST response for FolderShare entities and insures that
 * the hierarchy is not corrupted.
 *
 * However, we need to insure that the default resource created by the REST
 * module is disabled and <B>not available</B>. If it were available, a site
 * admin could enable it without understanding it's danger.
 *
 * There is no straight-forward way to block a default REST resource. It is
 * created at Drupal boot and added to the REST plugin manager automatically.
 * It remains in the plugin manager even if we define a custom resource for
 * the same entity type. However, we can <B>block the default resource</B>
 * by giving our resource the same plugin ID. This overwrites the default
 * resource's entry in the plugin manager's hash table, leaving only this
 * resource visible.
 *
 * <B>The plugin ID for this resource is therefore: "entity:foldershare"
 * to intentionally overwrite the default resource. This is mandatory!</B>
 *
 * @ingroup foldershare
 *
 * @RestResource(
 *   id    = "entity:foldershare",
 *   label = @Translation("FolderShare files and folders"),
 *   serialization_class = "Drupal\foldershare\Entity\FolderShare",
 *   uri_paths = {
 *     "canonical" = "/foldershare/{id}",
 *     "create" = "/entity/foldershare"
 *   }
 * )
 */
class FolderShareResource extends ResourceBase implements DependentPluginInterface {

  use EntityResourceValidationTrait;
  use FolderShareResourceTraits\ConfigureTrait;
  use FolderShareResourceTraits\ManageRequestTrait;
  use FolderShareResourceTraits\ManageResponseTrait;
  use FolderShareResourceTraits\GetResponseTrait;
  use FolderShareResourceTraits\DeleteResponseTrait;
  use FolderShareResourceTraits\PostResponseTrait;
  use FolderShareResourceTraits\PatchResponseTrait;

  /*--------------------------------------------------------------------
   *
   * Constants - Special entity IDs.
   *
   *--------------------------------------------------------------------*/

  /**
   * Indicates that no FolderShare item ID was provided.
   *
   * @var int
   */
  const EMPTY_ITEM_ID = (-1);

  /**
   * Indicates that no user ID was provided.
   *
   * @var int
   */
  const EMPTY_USER_ID = (-1);

  /*--------------------------------------------------------------------
   *
   * Constants - custom header fields.
   *
   * These header fields are recognized by this resource to selection
   * among different operations available or provide guidance on how
   * to perform an operation or return results.
   *
   *--------------------------------------------------------------------*/

  /**
   * A custom request header specifying a GET operation.
   *
   * Header values are any of those listed in GET_OPERATIONS.
   *
   * The default operation value is "entity".
   *
   * @see self::GET_OPERATIONS
   */
  const HEADER_GET_OPERATION = "X-FolderShare-Get-Operation";

  /**
   * A custom request header specifying a DELETE operation.
   *
   * Header values are any of those listed in DELETE_OPERATIONS.
   *
   * The default operation value is "entity".
   *
   * @see self::DELETE_OPERATIONS
   */
  const HEADER_DELETE_OPERATION = "X-FolderShare-Delete-Operation";

  /**
   * A custom request header specifying a POST operation.
   *
   * Header values are any of those listed in POST_OPERATIONS.
   *
   * The default operation value is "entity".
   *
   * @see self::POST_OPERATIONS
   */
  const HEADER_POST_OPERATION = "X-FolderShare-Post-Operation";

  /**
   * A custom request header specifying a PATCH operation.
   *
   * Header values are any of those listed in PATCH_OPERATIONS.
   *
   * The default operation value is "entity".
   *
   * @see self::PATCH_OPERATIONS
   */
  const HEADER_PATCH_OPERATION = "X-FolderShare-Patch-Operation";

  /**
   * A custom request header specifying a GET's search scope.
   *
   * Header values are one or more of those listed in SEARCH_SCOPES.
   * When multiple values are used, they should be listed and separated
   * by commas.
   *
   * The default search scope is "name, body".
   *
   * @see self::SEARCH_SCOPES
   */
  const HEADER_SEARCH_SCOPE = "X-FolderShare-Search-Scope";

  /**
   * A custom request header specifying a return type.
   *
   * Header values are any of those listed in RETURN_FORMATS.
   *
   * The default return type is "entity".
   *
   * @see self::RETURN_FORMATS
   */
  const HEADER_RETURN_FORMAT = "X-FolderShare-Return-Format";

  /**
   * A custom request header to specify a source entity path.
   *
   * Header values are strings for a root-to-leaf path to a file or
   * folder. Strings must be URL encoded in order for them to include
   * non-ASCII characters in an HTTP header.
   *
   * For operations that operate upon an entity, if the header is present
   * the path is used to find the entity instead of using an entity ID
   * on the route.
   */
  const HEADER_SOURCE_PATH = "X-FolderShare-Source-Path";

  /**
   * A custom request header to specify a destination entity path.
   *
   * Header values are strings for a root-to-leaf path to a file or
   * folder. Strings must be URL encoded in order for them to include
   * non-ASCII characters in an HTTP header.
   *
   * For operations that operate upon an entity, if the header is present
   * the path is used to find the entity instead of using an entity ID
   * on the route.
   */
  const HEADER_DESTINATION_PATH = "X-FolderShare-Destination-Path";

  /*--------------------------------------------------------------------
   *
   * Constants - header values.
   *
   * These are well-known values for the custom header fields above.
   *
   *--------------------------------------------------------------------*/

  /**
   * A list of well-known GET operation names.
   *
   * These names may be used as values for the self::HEADER_GET_OPERATION
   * HTTP header.
   *
   * @see self::HEADER_GET_OPERATION
   * @see self::DEFAULT_GET_OPERATION
   */
  const GET_OPERATIONS = [
    // GET operations that can use an entity ID.
    'get-entity',
    'get-parent',
    'get-root',
    'get-ancestors',
    'get-descendants',
    'get-sharing',
    'search',
    'download',

    // GET operations for configuration settings.
    'get-configuration',
    'get-usage',
    'get-version',
  ];

  /**
   * The default GET operation.
   *
   * @see self::GET_OPERATIONS
   */
  const DEFAULT_GET_OPERATION = 'get-entity';

  /**
   * A list of well-known DELETE operation names.
   *
   * These names may be used as values for the self::HEADER_DELETE_OPERATION
   * HTTP header.
   *
   * @see self::HEADER_DELETE_OPERATION
   * @see self::DEFAULT_DELETE_OPERATION
   */
  const DELETE_OPERATIONS = [
    // DELETE operations never take an entity ID, just a path.
    'delete-file',
    'delete-folder',
    'delete-folder-tree',
    'delete-file-or-folder',
    'delete-file-or-folder-tree',
  ];

  /**
   * The default DELETE operation.
   *
   * @see self::DELETE_OPERATIONS
   */
  const DEFAULT_DELETE_OPERATION = 'delete-file';

  /**
   * A list of well-known POST operation names.
   *
   * These names may be used as values for the self::HEADER_POST_OPERATION
   * HTTP header.
   *
   * @see self::HEADER_POST_OPERATION
   * @see self::DEFAULT_POST_OPERATION
   */
  const POST_OPERATIONS = [
    // POST operations that may take an entity ID.
    'new-rootfolder',
    'new-folder',
    'new-file',
    'new-rootfile',
    'new-media',
  ];

  /**
   * The default POST operation.
   *
   * @see self::POST_OPERATIONS
   */
  const DEFAULT_POST_OPERATION = 'new-rootfolder';

  /**
   * A list of well-known PATCH operation names.
   *
   * These names may be used as values for the self::HEADER_PATCH_OPERATION
   * HTTP header.
   *
   * @see self::HEADER_PATCH_OPERATION
   * @see self::DEFAULT_PATCH_OPERATION
   */
  const PATCH_OPERATIONS = [
    // PATCH operations that can use an entity ID.
    'update-entity',
    'update-sharing',

    // PATCH operations that need a destination.
    'copy-overwrite',
    'copy-no-overwrite',
    'move-overwrite',
    'move-no-overwrite',
  ];

  /**
   * The default PATCH operation.
   *
   * @see self::PATCH_OPERATIONS
   */
  const DEFAULT_PATCH_OPERATION = 'update-entity';

  /**
   * A list of well-known return types.
   *
   * These types may be used as values for the self::HEADER_RETURN_FORMAT
   * HTTP header that is supported for most GET, POST, and PATCH operations.
   *
   * @see self::HEADER_RETURN_FORMAT
   * @see self::DEFAULT_RETURN_FORMAT
   */
  const RETURN_FORMATS = [
    'full',
    'keyvalue',
  ];

  /**
   * The default return type.
   *
   * @see self::RETURN_FORMATS
   */
  const DEFAULT_RETURN_FORMAT = 'full';

  /**
   * A list of well-known search scopes.
   *
   * These values may be used for the self::HEADER_SEARCH_SCOPE HTTP header
   * that is supported by the GET "search" operation.
   *
   * @see self::HEADER_GET_OPERATION
   * @see self::GET_OPERATIONS
   * @see self::DEFAULT_SEARCH_SCOPE
   */
  const SEARCH_SCOPES = [
    'name',
    'body',
    'file-content',
  ];

  /**
   * The default search scope.
   *
   * @see self::SEARCH_SCOPES
   */
  const DEFAULT_SEARCH_SCOPE = 'name,body';


  /*--------------------------------------------------------------------
   *
   * Fields.
   *
   *--------------------------------------------------------------------*/

  /**
   * The entity type targeted by this resource.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $definition;

  /**
   * The link relation type manager used to create HTTP header links.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $linkRelationTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $typeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The configuration for this REST resource.
   *
   * @var \Drupal\rest\RestResourceConfigInterface
   */
  protected $resourceConfiguration;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /*--------------------------------------------------------------------
   *
   * Construct.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a Drupal\rest\Plugin\rest\resource\EntityResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the REST
   *   plugin instance.
   * @param string $pluginId
   *   The ID for the REST plugin instance.
   * @param mixed $pluginDefinition
   *   The REST plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $typeManager
   *   The entity type manager.
   * @param array $serializerFormats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Symfony\Component\HttpFoundation\Request $currentRequest
   *   The current HTTP request.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $linkRelationTypeManager
   *   The link relation type manager.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    EntityTypeManagerInterface $typeManager,
    array $serializerFormats,
    LoggerInterface $logger,
    Request $currentRequest,
    PluginManagerInterface $linkRelationTypeManager,
    FileSystem $fileSystem) {

    parent::__construct(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $serializerFormats,
      $logger);

    //
    // Notes:
    // - $configuration is required as an argument to the parent class,
    //   but it is empty. This is *not* the resource configuration.
    //
    // - $serializerFormats is required as an argument to the parent class,
    //   but it lists *all* formats installed at the site, rather than just
    //   those configured as supported by this plugin.
    //
    // Get the FolderShare entity definition. This is needed later
    // to calculate dependencies. Specifically, this resource is dependent
    // upon the FolderShare entity being installed.
    $this->definition = $typeManager->getDefinition(
      FolderShare::ENTITY_TYPE_ID);

    // Get the resource's configuration. The configuration is named
    // after this resource plugin, replacing ':' with '.'.
    $restResourceStorage = $typeManager->getStorage('rest_resource_config');
    $this->resourceConfiguration = $restResourceStorage->load('entity.foldershare');

    // And save information for later.
    $this->typeManager = $typeManager;
    $this->currentRequest = $currentRequest;
    $this->linkRelationTypeManager = $linkRelationTypeManager;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition) {

    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager'),
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('plugin.manager.link_relation_type'),
      $container->get('file_system')
    );
  }

}
