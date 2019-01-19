<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Drupal\rest\RequestHandler;

use Symfony\Component\Routing\Route;

use Drupal\foldershare\Entity\FolderShare;

/**
 * Respond to queries about the REST resource configuration.
 *
 * This trait includes methods required by a REST resource implementation
 * to report on dependencies, permissions, and the base route for
 * communications.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 *
 * @todo Support a base route for POST requests when Drupal 8.6+ supports the
 * handleRaw() method on a rest resource request handler.
 */
trait ConfigureTrait {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if (isset($this->definition) === TRUE) {
      return ['module' => [$this->definition->getProvider()]];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function permissions() {
    // Older versions of Drupal allowed REST implementations to define
    // additional permissions for REST access. Versions of Drupal >= 8.2
    // drop REST-specific entity permissions and instead use the entity's
    // regular permissions.
    //
    // Since the FolderShare module requires Drupal 8.4 or greater, the
    // older REST permissions model is not needed and we return nothing.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonicalPath, $method) {
    switch ($method) {
      default:
      case 'GET':
      case 'PATCH':
      case 'DELETE':
        // These methods use the canonical path, which includes an
        // entity ID in the URL. POST fields should be automatically
        // converted into a dummy entity.
        //
        // So, use the default route handler.
        $route = parent::getBaseRoute($canonicalPath, $method);

        if ($route->getOption('parameters') === FALSE) {
          $parameters = [];
          $parameters[FolderShare::ENTITY_TYPE_ID]['type'] =
            'entity:' . FolderShare::ENTITY_TYPE_ID;
          $route->setOption('parameters', $parameters);
        }
        break;

      /* TODO  This won't work yet because 'handleRaw' is a patch to Drupal core
       * TODO  that has not been added to Drupal yet. Without it, we cannot do
       * TODO  file uploads.
      case 'POST':
        // POST uses the create path, which does not include an entity ID
        // in the URL. Some POSTs create new FolderShare entities using
        // values in POST fields (e.g. new folder). But some POSTs need
        // to use the POST fields specially, such as when uploading a
        // file. There is no FolderShare field to automatically set to
        // an uploaded file's URI. That URI actually belongs in a File
        // entity referenced by a FolderShare field. This makes it hard
        // to send an uploaded file and have this resource automatically
        // create that File entity as needed.
        //
        // So, the fix is to use a "raw" handler for the incoming request
        // on POSTs. The raw handler does not try to automatically create
        // a valid dummy entity.
        $route = new Route(
          $canonicalPath,
          [
            '_controller' => RequestHandler::class . '::handleRaw',
          ],
          $this->getBaseRouteRequirements($method),
          [],
          '',
          [],
          // The HTTP method is a requirement for this route.
          [$method]
        );
        break;
      */
    }

    return $route;
  }

}
