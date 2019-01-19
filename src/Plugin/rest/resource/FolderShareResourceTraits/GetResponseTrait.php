<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Drupal\Component\Utility\Unicode;

use Symfony\Component\HttpKernel\Kernel;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareUsage;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\Exception\NotFoundException;
use Drupal\foldershare\Plugin\rest\resource\UncacheableResponse;

/**
 * Respond to GET requests.
 *
 * This trait includes methods that implement generic and operation-specific
 * responses to HTTP GET requests.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait GetResponseTrait {

  /*--------------------------------------------------------------------
   *
   * Generic.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to HTTP GET requests.
   *
   * Drupal's REST handling requires that:
   * - GET requests use the 'canonical' entity route.
   * - An entity ID on that route.
   *
   * Some GET requests use the entity ID, while others do not.
   *
   * All GET requests here use an additional HTTP header to specify the
   * operation. Depending on the operation, additional operands may be in
   * other HTTP header values.
   *
   * @param int $id
   *   (optional, default = NULL) The entity ID of a FolderShare entity,
   *   or a placeholder ID if the GET operation does not require an ID or
   *   if the ID or path is specified by the X-FolderShare-Source-Path
   *   header.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if any header value is not
   *   recognized, and throws AccessDeniedHttpException if the user does
   *   not have "view" access to an entity when an operation requires access.
   */
  public function get(int $id = NULL) {
    //
    // Get the operation
    // -----------------
    // Get the specific GET operation to perform from the HTTP header,
    // if any. If a header is not provided, default to getting an entity.
    $operation = $this->getAndValidateGetOperation();

    //
    // Dispatch
    // --------
    // Handle each of the GET operations.
    switch ($operation) {
      case 'get-version':
        // Return module version numbers.
        return $this->getVersion();

      case 'get-configuration':
        // Return the module and site configuration.
        return $this->getConfiguration();

      case 'get-entity':
        // Load and return an entity.
        return $this->getEntity($id);

      case 'get-parent':
        // Load and return the entity's parent.
        return $this->getEntityParent($id);

      case 'get-root':
        // Load and return the entity's root.
        return $this->getEntityRoot($id);

      case 'get-ancestors':
        // Load and return a list of the entity's ancestors.
        return $this->getEntityAncestors($id);

      case 'get-descendants':
        // Load and return a list of the entity's descendants, or a list
        // of root items.
        return $this->getEntityDescendants($id);

      case 'get-sharing':
        // TODO implement!
        throw new BadRequestHttpException(
          'Getting sharing settings not yet supported.');

      case 'search':
        // TODO implement!
        throw new BadRequestHttpException(
          'Search not yet supported.');

      case 'get-usage':
        // Return usage stats for the user.
        return $this->getUsage();

      case 'download':
        // Download a single file.
        return $this->download($id);

      default:
        throw new BadRequestHttpException(t(
          "The requested '@operation' web service is not recognized.\nThe web services client may be out of date.",
          [
            '@operation' => $operation,
          ]));
    }
  }

  /*--------------------------------------------------------------------
   *
   * Get site configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP GET request for Drupal and module version numbers.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-version".
   *
   * The HTTP response contains:
   * - A list of key-value pairs describing software version numbers
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns a response containing key-value pairs for version numbers.
   */
  private function getVersion() {
    //
    // Add pseudo-fields
    // -----------------
    // Add pseudo-fields that are useful for the client side to interpret
    // and present the data.
    // - Host name.
    $content = [];
    $content['host'] = $this->currentRequest->getHttpHost();

    //
    // Add version numbers
    // -------------------
    // Add numbers for Drupal core, key support modules, and FolderShare.
    $content['server'] = [];

    // Get the Drupal core version number.
    $content['server']['drupal'] = [
      'name'    => 'Drupal content management system',
      'version' => \Drupal::VERSION,
    ];

    // Get the PHP version number.
    $content['server']['serverphp'] = [
      'name'    => 'PHP',
      'version' => phpversion(),
    ];

    // Get the Symfony version number.
    $content['server']['symfony'] = [
      'name'    => 'Symfony library',
      'version' => Kernel::VERSION,
    ];

    // Get the relevant module names and version numbers.
    $modules = system_get_info('module');

    if (isset($modules['rest']) === TRUE) {
      $content['server']['rest'] = [
        'name'    => $modules['rest']['name'] . ' module',
        'version' => $modules['rest']['version'],
      ];
    }

    if (isset($modules['serialization']) === TRUE) {
      $content['server']['serializer'] = [
        'name'    => $modules['serialization']['name'] . ' module',
        'version' => $modules['serialization']['version'],
      ];
    }

    if (isset($modules['basic_auth']) === TRUE) {
      $content['server']['basic_auth'] = [
        'name'    => $modules['basic_auth']['name'] . ' module',
        'version' => $modules['basic_auth']['version'],
      ];
    }

    if (isset($modules['foldershare']) === TRUE) {
      $content['server']['foldershare'] = [
        'name'    => $modules['foldershare']['name'] . ' module',
        'version' => $modules['foldershare']['version'],
      ];
    }

    return new UncacheableResponse($content);
  }

  /**
   * Responds to an HTTP GET request for the module and site configuration.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-configuration".
   *
   * The HTTP response contains:
   * - A list of key-value pairs describing the site configuration.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns a response containing key-value pairs for module and
   *   site settings.
   */
  private function getConfiguration() {
    //
    // Add pseudo-fields
    // -----------------
    // Add pseudo-fields that are useful for the client side to interpret
    // and present the data.
    // - Host name.
    $content = [];
    $content['host'] = $this->currentRequest->getHttpHost();

    //
    // Add resource settings
    // ---------------------
    // Add client-relevant settings for this resource:
    // - Serializer formats.
    // - Authentication providers.
    //
    // A REST resource may be configured with two granularities:
    //
    // - method granularity: every method has its own serializer and
    //   authentication settings.
    //
    // - resource granularity: all methods share the same serializer and
    //   authentication settings.
    //
    // There is no method on the configuration to get the granularity.
    // We must assume the most complex case, which is method granularity.
    foreach ($this->resourceConfiguration->getMethods() as $method) {
      // Get the serializer formats and authentication providers configured
      // for the method.
      $formats = $this->resourceConfiguration->getFormats($method);
      $auths = $this->resourceConfiguration->getAuthenticationProviders($method);

      $content[$method] = [
        'serializer-formats' => $formats,
        'authentication-providers' => $auths,
      ];
    }

    //
    // Add module settings
    // -------------------
    // Add client-relevant module settings.
    $content['file-restrict-extensions'] =
      ((Settings::getFileRestrictExtensions() === TRUE) ?
      'true' : 'false');
    $content['file-allowed-extensions'] =
      Settings::getAllowedNameExtensions();
    $content['file-maximum-upload-size'] =
      Settings::getUploadMaximumFileSize();
    $content['file-maximum-upload-number'] =
      Settings::getUploadMaximumFileNumber();

    return new UncacheableResponse($content);
  }

  /*--------------------------------------------------------------------
   *
   * Get user's usage.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP GET request for the user's usage of the module.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-usage".
   *
   * The HTTP response contains:
   * - A list of key-value pairs describing the user's usage.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns a response containing key-value pairs for the user's current
   *   usage of the FolderShare module.
   */
  private function getUsage() {
    //
    // Add pseudo-fields
    // -----------------
    // Add pseudo-fields useful for the client:
    // - Host name.
    // - User ID.
    // - User account name.
    // - User display name.
    $user = \Drupal::currentUser();
    $uid = (int) $user->id();

    $content = [];
    $content['host'] = $this->currentRequest->getHttpHost();

    $content['user-id'] = $uid;
    $content['user-account-name'] = $user->getAccountName();
    $content['user-display-name'] = $user->getDisplayName();

    //
    // Add usage
    // ---------
    // Add usage data.
    $content += FolderShareUsage::getUsage($uid);

    return new UncacheableResponse($content);
  }

  /*--------------------------------------------------------------------
   *
   * Get one entity.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP GET request for an entity.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-entity".
   * - X-FolderShare-Source-Path = entity path (overrides URL entity ID).
   * - X-FolderShare-Return-Format = "full" or "keyvalue".
   *
   * The HTTP response contains:
   * - A serialized entity.
   *
   * @param int $id
   *   The ID of the entity from the URL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the request is bad.
   */
  private function getEntity(int $id) {
    // Get the HTTP header's source path, if any.
    $source = $this->getSourcePath();

    // Parse the path to get the entity's ID, if any.
    if (empty($source) === FALSE) {
      try {
        $components = FolderShare::parsePath($source);
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }

      // If the path is just '/', there is no actual entity to get.
      // Instead, create a temporary entity and return it.
      if ($components['path'] === '/') {
        $uid = $components['uid'];
        if ($uid === NULL || $uid < 0) {
          $uid = \Drupal::currentUser()->id();
        }

        $falseFolder = FolderShare::create([
          'name' => '',
          'uid'  => $uid,
          'kind' => FolderShare::FOLDER_KIND,
          'mime' => FolderShare::FOLDER_MIME,
        ]);

        return $this->formatEntityResponse($falseFolder);
      }

      // Get the path's entity.
      try {
        $id = FolderShare::findPathItemId($source);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    return $this->formatEntityResponse(
      $this->loadAndValidateEntity($id, "view"));
  }

  /**
   * Responds to an HTTP GET request for an entity's parent.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-parent".
   * - X-FolderShare-Source-Path = entity path (overrides URL entity ID).
   * - X-FolderShare-Return-Format = "full" or "keyvalue".
   *
   * The HTTP response contains:
   * - A serialized entity.
   *
   * @param int $id
   *   The ID of the entity from the URL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the request is bad.
   */
  private function getEntityParent(int $id) {
    // Get the HTTP header's source path, if any.
    $source = $this->getSourcePath();

    if (empty($source) === FALSE) {
      // Get the path's entity.
      try {
        $id = FolderShare::findPathItemId($source);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    $entity = $this->loadAndValidateEntity($id, "view");
    if ($entity->isRootItem() === TRUE) {
      throw new NotFoundHttpException(t(
        "The request for an entity's parent folder is invalid because the entity is already at the top level and has no parent."));
    }

    return $this->formatEntityResponse($entity->getParentFolder());
  }

  /**
   * Responds to an HTTP GET request for an entity's root.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-root".
   * - X-FolderShare-Source-Path = entity path (overrides URL entity ID).
   * - X-FolderShare-Return-Format = "full" or "keyvalue".
   *
   * The HTTP response contains:
   * - A serialized entity.
   *
   * @param int $id
   *   The ID of the entity from the URL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the request is bad.
   */
  private function getEntityRoot(int $id) {
    // Get the HTTP header's source path, if any.
    $source = $this->getSourcePath();

    if (empty($source) === FALSE) {
      // Get the path's entity.
      try {
        $id = FolderShare::findPathItemId($source);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    $entity = $this->loadAndValidateEntity($id, "view");
    if ($entity->isRootItem() === TRUE) {
      throw new NotFoundHttpException(t(
        "The request for an entity's top-level ancestor folder is invalid because the entity is already at the top level and has no ancestors."));
    }

    return $this->formatEntityResponse($entity->getRootItem());
  }

  /*--------------------------------------------------------------------
   *
   * Get entity list.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP GET request for an entity's ancestors.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-ancestors".
   * - X-FolderShare-Source-Path = entity path (overrides URL entity ID).
   * - X-FolderShare-Return-Format = "full" or "keyvalue".
   *
   * The HTTP response contains:
   * - A list of serialized entities.
   *
   * @param int $id
   *   The ID of the entity from the URL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the request is bad.
   */
  private function getEntityAncestors(int $id) {
    // Get the HTTP header's source path, if any.
    $source = $this->getSourcePath();

    if (empty($source) === FALSE) {
      // Get the path's entity.
      try {
        $id = FolderShare::findPathItemId($source);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    $entity = $this->loadAndValidateEntity($id, "view");
    if ($entity->isRootItem() === TRUE) {
      return new UncacheableResponse(NULL, Response::HTTP_NO_CONTENT);
    }

    return $this->formatEntityListResponse($entity->findAncestorFolders());
  }

  /**
   * Responds to an HTTP GET request for an entity's descendants or root lists.
   *
   * The HTTP request contains:
   * - X-FolderShare-Get-Operation = "get-descendants".
   * - X-FolderShare-Source-Path = entity path (overrides URL entity ID).
   * - X-FolderShare-Return-Format = "full" or "keyvalue".
   *
   * The HTTP response contains:
   * - A list of serialized entities.
   *
   * @param int $id
   *   The ID of the entity from the URL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the request is bad.
   */
  private function getEntityDescendants(int $id) {
    // Get the HTTP header's source path, if any.
    $source = $this->getSourcePath();

    if (empty($source) === FALSE) {
      // Parse the source path.
      try {
        $components = FolderShare::parsePath($source);
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }

      // If the path is just '/', get the root list based on the scheme.
      if ($components['path'] === '/') {
        return $this->getRootList($components['scheme'], $components['uid']);
      }

      // Otherwise get the entity ID for the path.
      try {
        $id = FolderShare::findPathItemId($source);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    // Load and return the entity's children.
    $entity = $this->loadAndValidateEntity($id, "view");
    if ($entity->isFolder() === FALSE) {
      // The entity is not a folder. Just return the folder itself.
      return $this->formatEntityListResponse([$entity]);
    }

    return $this->formatEntityListResponse($entity->findChildren());
  }

  /**
   * Responds to a GET request for a list of root items.
   *
   * The returned root list includes all root items in the "personal" or
   * "public" categories. Personal root items are those owned by the
   * indicated user, or shared with them. Public root items are those
   * owned by the anonymous user or shared with them.
   *
   * If the user is not an admin, the root list is culled to remove entries
   * for system hidden or disabled items.
   *
   * @param string $scheme
   *   The scheme to select "personal" or "public" root items.
   * @param int $uid
   *   (optional, default = NULL) For the "personal" scheme only, the ID of
   *   the user for whome to return a personal root list (i.e. items owned
   *   by them and shared with them). If not given, defaults to the current
   *   user. The value is ignored for the "public" scheme, which always
   *   returns all public items, regardless of who owns them.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if arguments are bad.
   */
  private function getRootList(string $scheme, int $uid = NULL) {
    $currentUser = \Drupal::currentUser();

    switch ($scheme) {
      case FolderShare::PERSONAL_SCHEME:
        // All root items owned by the user or shared with them.
        // Default the UID to the current user.
        if ($uid === NULL) {
          $uid = $currentUser->id();
        }

        $roots = array_merge(
          FolderShare::findAllRootItems($uid),
          FolderShare::findAllSharedRootItems(
            FolderShareInterface::ANY_USER_ID,
            $uid));
        break;

      case FolderShare::PUBLIC_SCHEME:
        // All root items shared with the public. Ignore the given UID.
        $roots = FolderShare::findAllPublicRootItems(
          FolderShareInterface::ANY_USER_ID);
        break;

      default:
        throw new BadRequestHttpException(t(
          "The folder path scheme '@scheme' is not recognized.",
          [
            '@scheme' => $scheme,
          ]));
    }

    // The returned lists may include system hidden or disabled items.
    // If the user is an admin, return the list as-is.
    if ($currentUser->hasPermission(Constants::ADMINISTER_PERMISSION) === TRUE) {
      return $this->formatEntityListResponse($roots);
    }

    // Filter out system hidden and disabled items, if any.
    $safeRoots = [];
    foreach ($roots as $root) {
      if ($root->isSystemHidden() === FALSE &&
          $root->isSystemDisabled() === FALSE) {
        $safeRoots[] = $root;
      }
    }

    return $this->formatEntityListResponse($safeRoots);
  }

  /*--------------------------------------------------------------------
   *
   * Download file or folder.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to a GET request to download a file or folder.
   *
   * The given entity ID or source path may refer to a file or folder.
   * If it refers to a file, that file is downloaded as-is. If it refers
   * to a folder, the folder is ZIPed and the ZIP file is returned.
   *
   * @param int $id
   *   The ID of the entity from the URL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the request is bad.
   */
  private function download(int $id) {
    //
    // Validate
    // --------
    // Get the HTTP header's source path, if any.
    $source = $this->getSourcePath();

    if (empty($source) === FALSE) {
      // Get the path's entity.
      try {
        $id = FolderShare::findPathItemId($source);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (ValidationException $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    // Check if it is a file, image, or folder.
    $entity = FolderShare::load($id);

    if ($entity->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($entity->isSystemDisabled() === TRUE) {
      // Disabled items cannot be used.
      throw new AccessDeniedHttpException();
    }

    if ($entity->isFolder() === TRUE) {
      // The entity is a folder. Create a ZIP archive of the folder.
      // The new ZIP archive is in temporary storage, so it will be
      // automatically deleted later on by Drupal CRON.
      try {
        $uri = FolderShare::createZipArchive([$entity]);
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }

      $filename = $entity->getName() . '.zip';
      $mimeType = Unicode::mimeHeaderEncode(
        \Drupal::service('file.mime_type.guesser')->guess($filename));
    }
    elseif ($entity->isFile() === TRUE || $entity->isImage() === TRUE) {
      // The entity is a file or image. Get the underlying file's URI,
      // human-readable name, MIME type, and size.
      if ($entity->isFile() === TRUE) {
        $file = $entity->getFile();
      }
      else {
        $file = $entity->getImage();
      }

      $uri      = $file->getFileUri();
      $filename = $file->getFilename();
      $mimeType = Unicode::mimeHeaderEncode($file->getMimeType());
    }
    else {
      // The entity is not a file or image. It may be a media object.
      // In any case, it cannot be downloaded.
      throw new BadRequestHttpException(t(
        "The item '@name' does not support downloading.",
        [
          '@name' => $entity->getName(),
        ]));
    }

    //
    // Get file attributes
    // -------------------
    // Map the file's URI to the full local file path and check that
    // it exists. If it does, get its size.
    $realPath = FileUtilities::realpath($uri);

    if ($realPath === FALSE || file_exists($realPath) === FALSE) {
      throw new NotFoundHttpException(t(
        "System error. A file at '@path' could not be read.\nThere may be a problem with permissions. Please report this to the site administrator.",
        [
          '@path' => $realPath,
        ]));
    }

    $filesize = FileUtilities::filesize($realPath);

    //
    // Build header
    // ------------
    // Build an HTTP header for the file by getting the user-visible
    // file name and MIME type. Both of these are essential in the HTTP
    // header since they tell the browser what type of file it is getting,
    // and the name of the file if the user wants to save it their disk.
    $headers = [
      // Use the File object's MIME type.
      'Content-Type'        => $mimeType,

      // Use the human-visible file name.
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',

      // Use the saved file size, in bytes.
      'Content-Length'      => $filesize,

      // Don't cache the file because permissions and content may
      // change.
      'Pragma'              => 'no-cache',
      'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
      'Expires'             => '0',
      'Accept-Ranges'       => 'bytes',
    ];

    $scheme = Settings::getFileScheme();
    $isPrivate = ($scheme == 'pesonal');

    //
    // Respond
    // -------
    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
    // sets response as not cacheable if the Cache-Control header is not
    // already modified. We pass in FALSE for non-private schemes for the
    // $public parameter to make sure we don't change the headers.
    return new BinaryFileResponse($uri, 200, $headers, !$isPrivate);
  }

}
