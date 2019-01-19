<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;
use Drupal\foldershare\Entity\Exception\NotFoundException;

/**
 * Manage HTTP request headers, including getting and validating values.
 *
 * This trait includes internal methods used to manage custom HTTP header
 * values used to communicate operators and operands.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManageRequestTrait {

  /*--------------------------------------------------------------------
   *
   * Operations.
   *
   *--------------------------------------------------------------------*/

  /**
   * Gets, validates, and returns an operation from the request header.
   *
   * The self::HEADER_GET_OPERATION header is retrieved. If not found,
   * the default operation is returned. Otherwise the header's operation
   * is validated and returned.
   *
   * An exception is thrown if the operation is not recognized.
   *
   * @param string $header
   *   The name of the HTTP header to check.
   * @param array $allowed
   *   The array of allowed HTTP header values.
   * @param string $default
   *   The default value if the header is not found.
   *
   * @return string
   *   Returns the name of the operation, or the default if no operation
   *   was specified.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if an unknown operation is found.
   *
   * @see ::getAndValidateGetOperation()
   * @see ::getAndValidatePatchOperation()
   * @see ::getAndValidatePostOperation()
   */
  private function getAndValidateOperation(
    string $header,
    array $allowed,
    string $default) {

    // Get the request's headers.
    $requestHeaders = $this->currentRequest->headers;

    // Get the header. If not found, return the default.
    if ($requestHeaders->has($header) === FALSE) {
      return $default;
    }

    // Get and validate the operation.
    $operation = $requestHeaders->get($header);
    if (is_string($operation) === TRUE &&
        in_array($operation, $allowed, TRUE) === TRUE) {
      return $operation;
    }

    // Fail.
    throw new BadRequestHttpException(t(
      "The requested '@operation' web service is not recognized.\nThe web services client may be out of date.",
      [
        '@operation' => $operation,
      ]));
  }

  /**
   * Gets, validates, and returns the GET operation from the request header.
   *
   * The self::HEADER_GET_OPERATION header is retrieved. If not found,
   * the default operation is returned. Otherwise the header's operation
   * is validated and returned.
   *
   * An exception is thrown if the operation is not recognized.
   *
   * @return string
   *   Returns the name of the operation, or the default if no operation
   *   was specified.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if an unknown operation is found.
   *
   * @see ::HEADER_GET_OPERATION
   * @see ::GET_OPERATIONS
   * @see ::DEFAULT_GET_OPERATION
   */
  private function getAndValidateGetOperation() {
    return $this->getAndValidateOperation(
      self::HEADER_GET_OPERATION,
      self::GET_OPERATIONS,
      self::DEFAULT_GET_OPERATION);
  }

  /**
   * Gets, validates, and returns the DELETE operation from the request header.
   *
   * The self::HEADER_DELETE_OPERATION header is retrieved. If not found,
   * the default operation is returned. Otherwise the header's operation
   * is validated and returned.
   *
   * An exception is thrown if the operation is not recognized.
   *
   * @return string
   *   Returns the name of the operation, or the default if no operation
   *   was specified.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if an unknown operation is found.
   *
   * @see ::HEADER_DELETE_OPERATION
   * @see ::DELETE_OPERATIONS
   * @see ::DEFAULT_DELETE_OPERATION
   */
  private function getAndValidateDeleteOperation() {
    return $this->getAndValidateOperation(
      self::HEADER_DELETE_OPERATION,
      self::DELETE_OPERATIONS,
      self::DEFAULT_DELETE_OPERATION);
  }

  /**
   * Gets, validates, and returns the PATCH operation from the request header.
   *
   * The self::HEADER_PATCH_OPERATION header is retrieved. If not found,
   * the default operation is returned. Otherwise the header's operation
   * is validated and returned.
   *
   * An exception is thrown if the operation is not recognized.
   *
   * @return string
   *   Returns the name of the operation, or the default if no operation
   *   was specified.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if an unknown operation is found.
   *
   * @see ::HEADER_PATCH_OPERATION
   * @see ::PATCH_OPERATIONS
   * @see ::DEFAULT_PATCH_OPERATION
   */
  private function getAndValidatePatchOperation() {
    return $this->getAndValidateOperation(
      self::HEADER_PATCH_OPERATION,
      self::PATCH_OPERATIONS,
      self::DEFAULT_PATCH_OPERATION);
  }

  /**
   * Gets, validates, and returns the POST operation from the request header.
   *
   * The self::HEADER_POST_OPERATION header is retrieved. If not found,
   * the default operation is returned. Otherwise the header's operation
   * is validated and returned.
   *
   * An exception is thrown if the operation is not recognized.
   *
   * @return string
   *   Returns the name of the operation, or the default if no operation
   *   was specified.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if an unknown operation is found.
   *
   * @see ::HEADER_POST_OPERATION
   * @see ::POST_OPERATIONS
   * @see ::DEFAULT_POST_OPERATION
   */
  private function getAndValidatePostOperation() {
    return $this->getAndValidateOperation(
      self::HEADER_POST_OPERATION,
      self::POST_OPERATIONS,
      self::DEFAULT_POST_OPERATION);
  }

  /**
   * Gets, validates, and returns the return type from the request header.
   *
   * The current request's headers are checked for self::HEADER_RETURN_FORMAT.
   * If the header is found, it's value is validated against a list of
   * well-known return types and returned. If the header is not found,
   * a default return type is returned.
   *
   * @return string
   *   Returns the name of the return type, or the default if no return type
   *   was specified.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a BadRequestHttpException if an unknown return type is found.
   *
   * @see ::HEADER_RETURN_FORMAT
   * @see ::RETURN_FORMATS
   * @see ::DEFAULT_RETURN_FORMAT
   */
  private function getAndValidateReturnFormat() {
    // Get the request's headers.
    $requestHeaders = $this->currentRequest->headers;

    // If no return type is specified, return the default.
    if ($requestHeaders->has(self::HEADER_RETURN_FORMAT) === FALSE) {
      return self::DEFAULT_RETURN_FORMAT;
    }

    // Get and validate the return type.
    $returnFormat = $requestHeaders->get(self::HEADER_RETURN_FORMAT);
    if (is_string($returnFormat) === TRUE &&
        in_array($returnFormat, self::RETURN_FORMATS, TRUE) === TRUE) {
      return $returnFormat;
    }

    // Fail.
    throw new BadRequestHttpException(t(
      "The requested '@name' web service return format is not recognized.\nThe web services client may be out of date.",
      [
        '@name' => $returnFormat,
      ]));
  }

  /*--------------------------------------------------------------------
   *
   * Entity operands.
   *
   *--------------------------------------------------------------------*/

  /**
   * Loads and validates access to the indicated entity.
   *
   * @param int $id
   *   The entity ID for a FolderShare entity.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the loaded entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws a NotFoundHttpException if the entity ID is bad, and
   *   an AccessDeniedHttpException if the user does not have access.
   */
  private function loadAndValidateEntity(int $id) {

    // Load the entity using the ID.
    $entity = FolderShare::load($id);
    if ($entity === NULL) {
      throw new NotFoundHttpException(t(
        "The requested entity ID '@id' for web services could not be found.",
        [
          '@id' => $id,
        ]));
    }

    if ($entity->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($entity->isSystemDisabled() === TRUE) {
      // Disabled items cannot be used.
      throw new AccessDeniedHttpException();
    }

    // Check if the user has 'view' access to the entity.
    $access = $entity->access('view', NULL, TRUE);
    if ($access->isAllowed() === FALSE) {
      // No access. If a reason was not provided, use a default.
      $message = $access->getReason();
      if (empty($message) === TRUE) {
        $message = $this->getDefaultAccessDeniedMessage('view');
      }

      throw new AccessDeniedHttpException($message);
    }

    return $entity;
  }

  /*--------------------------------------------------------------------
   *
   * Path handling.
   *
   *--------------------------------------------------------------------*/

  /**
   * Gets the source path from the request header.
   *
   * @return string
   *   Returns the source path, or an empty string if the request header
   *   was not set or it had an empty value.
   *
   * @see ::HEADER_SOURCE_PATH
   */
  private function getSourcePath() {
    $requestHeaders = $this->currentRequest->headers;
    if ($requestHeaders->has(self::HEADER_SOURCE_PATH) === FALSE) {
      return '';
    }

    $value = $requestHeaders->get(self::HEADER_SOURCE_PATH);
    if (empty($value) === TRUE) {
      return '';
    }

    return rawurldecode($value);
  }

  /**
   * Gets the destination path from the request header.
   *
   * @return string
   *   Returns the source path, or an empty string if the request header
   *   was not set or it had an empty value.
   *
   * @see ::HEADER_DESTINATION_PATH
   */
  private function getDestinationPath() {
    $requestHeaders = $this->currentRequest->headers;
    if ($requestHeaders->has(self::HEADER_DESTINATION_PATH) === FALSE) {
      return '';
    }

    $value = $requestHeaders->get(self::HEADER_DESTINATION_PATH);
    if (empty($value) === TRUE) {
      return '';
    }

    return rawurldecode($value);
  }

  /*--------------------------------------------------------------------
   *
   * Source and destination entities.
   *
   *--------------------------------------------------------------------*/

  /**
   * Gets the source entity indicated by a URL entity ID or source path.
   *
   * If there is no source and $required is TRUE, an exception is thrown.
   * Otherwise a NULL is returned when there is no source.
   *
   * @param int $id
   *   (optional, default = EMPTY_ITEM_ID) The entity ID if there is no
   *   source path. A negative or EMPTY_ITEM_ID value indicates that no
   *   entity ID was provided.
   * @param bool $required
   *   (optional, default = TRUE) Indicate if the source entity must exist.
   *   If so and the entity cannot be found, an exception is thrown. Otherwise
   *   a NULL is returned.
   *
   * @return \Drupal\foldeshare\FolderShareInterface
   *   Returns the loaded source entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the source entity is required and there is
   *   no source found.
   */
  private function getSourceAndValidate(
    int $id = self::EMPTY_ITEM_ID,
    bool $required = TRUE) {

    // Get the source path, if any.
    $sourcePath = $this->getSourcePath();

    // If the source path exists, parse it to get the entity ID. Use that
    // ID to override the incoming entity ID.
    if (empty($sourcePath) === FALSE) {
      try {
        $id = FolderShare::findPathItemId($sourcePath);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    // If there is no entity ID, throw an exception if one is required.
    if ($id < 0) {
      if ($required === TRUE) {
        throw new BadRequestHttpException(t(
          "The path to the source item is empty.\nThe web service requires a source path, but the given path is empty. Valid use of the service should have been checked before issuing the request."));
      }

      return NULL;
    }

    // Load the entity.
    $entity = FolderShare::load($id);
    if ($entity === NULL) {
      if ($required === TRUE) {
        throw new NotFoundHttpException(t(
          "The requested entity ID '@id' for web services could not be found.",
          [
            '@id' => $id,
          ]));
      }

      return NULL;
    }

    if ($entity->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($entity->isSystemDisabled() === TRUE) {
      // Disabled items cannot be used.
      throw new AccessDeniedHttpException();
    }

    return $entity;
  }

  /**
   * Gets the destination entity indicated by a URL entity ID or source path.
   *
   * If the destination path is "/", a NULL is returned and the path parts
   * argument is set to the parsed scheme, user, and path.
   *
   * If there is no destination and $required is TRUE, an exception is thrown.
   * Otherwise a NULL is returned when there is no destination.
   *
   * @param int $id
   *   The entity ID if there is no destination path. A negative or
   *   EMPTY_ITEM_ID value indicates no ID was provided.
   * @param bool $required
   *   Indicate if the destination entity must exist. If so and the entity
   *   cannot be found, an exception is thrown. Otherwise a NULL is returned.
   * @param array $pathParts
   *   The returned parsed parts of the path, if a path was used.
   *
   * @return \Drupal\foldeshare\FolderShareInterface
   *   Returns the loaded destination entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if the destination entity is required and there is
   *   no destination found.
   *
   * @deprecated This function is no longer used and may be removed in a
   * future release.
   */
  private function getDestination(
    int $id,
    bool $required,
    array &$pathParts) {

    // Get the destination path, if any.
    $path = $this->getDestinationPath();

    // If the destination path exists, parse it to get the entity ID. Use that
    // ID to override the incoming entity ID.
    if (empty($path) === FALSE) {
      try {
        $parts = FolderShare::parsePath($path);
        if ($parts['path'] !== '/') {
          $pathParts['scheme'] = $parts['scheme'];
          $pathParts['uid']    = $parts['uid'];
          $pathParts['path']   = $parts['path'];
          return NULL;
        }

        $id = FolderShare::findPathItemId($path);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    // If there is no entity ID, throw an exception if one is required.
    if ($id < 0) {
      if ($required === TRUE) {
        throw new BadRequestHttpException(t(
          "The path to the destination item is empty.\nThe web service requires a destination path, but the given path is empty. Valid use of the service should have been checked before issuing the request."));
      }

      return NULL;
    }

    // Load the entity.
    $entity = FolderShare::load($id);
    if ($entity === NULL) {
      if ($required === TRUE) {
        throw new NotFoundHttpException(t(
          "The requested entity ID '@id' for web services could not be found.",
          [
            '@id' => $id,
          ]));
      }

      return NULL;
    }

    if ($entity->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($entity->isSystemDisabled() === TRUE) {
      // Disabled items cannot be used.
      throw new AccessDeniedHttpException();
    }

    return $entity;
  }

  /**
   * Checks for user access to the indicated entity.
   *
   * Access is checked for the requested operation. If access is denied,
   * an exception is thrown with an appropriate message.
   *
   * @param \Drupal\foldeshare\FolderShareInterface $entity
   *   The entity to check for access. If NULL, the operation must be 'create'
   *   and access is checked for creating a root item.
   * @param string $operation
   *   The access operation to check for.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if access is denied.
   */
  private function validateAccess(
    FolderShareInterface $entity = NULL,
    string $operation = '') {

    // If there is an entity, check the entity directly for access.
    if ($entity !== NULL) {
      $access = $entity->access($operation, NULL, TRUE);

      if ($access->isAllowed() === TRUE) {
        // Access allowed.
        return;
      }

      // No access. If a reason was not provided, use a default.
      $message = $access->getReason();
      if (empty($message) === TRUE) {
        $message = $this->getDefaultAccessDeniedMessage($operation);
      }

      // Access denied.
      throw new AccessDeniedHttpException($message);
    }

    // Otherwise there is no entity.
    $summary = FolderShareAccessControlHandler::getRootAccessSummary(
      FoldershareInterface::USER_ROOT_LIST,
      NULL);
    if (isset($summary[$operation]) === TRUE &&
        $summary[$operation] === TRUE) {
      // Access allowed.
      return;
    }

    // Access denied.
    throw new AccessDeniedHttpException(
      $this->getDefaultAccessDeniedMessage($operation));
  }

}
