<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Drupal\Core\Entity\EntityInterface;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\Exception\NotFoundException;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Plugin\rest\resource\UncacheableResponse;

/**
 * Respond to HTTP POST requests.
 *
 * This trait includes methods that implement generic and operation-specific
 * responses to HTTP POST requests.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 *
 * @todo Upgrade to support Drupal 8.6+ file posts.
 */
trait PostResponseTrait {

  /*--------------------------------------------------------------------
   *
   * Generic.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to HTTP POST requests to create a new FolderShare entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $dummy
   *   The dummy entity created from incoming unserialized data.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if arguments are bad.
   *
   * @todo Media entities are not supported yet. The "new-media" operation
   * throws a bad-request exception.
   */
  public function post(EntityInterface $dummy = NULL) {
    //
    // Get operation
    // -------------
    // Use the HTTP header to get the POST operation, if any.
    $operation = $this->getAndValidatePostOperation();

    //
    // Validate dummy entity
    // ---------------------
    // A dummy entity must have been provided, it must have the proper
    // entity type, it cannot have an entity ID, and it must have a name.
    if ($dummy === NULL ||
        count($dummy->_restSubmittedFields) === 0) {
      throw new BadRequestHttpException(t(
        'Required new item information is missing.'));
    }

    if ($dummy->getEntityTypeId() !== FolderShare::ENTITY_TYPE_ID) {
      throw new BadRequestHttpException(t(
        'Required new item information is malformed with a bad entity type.'));
    }

    if ($dummy->isNew() === FALSE) {
      throw new BadRequestHttpException(t(
        'Required new item information is malformed with a bad ID "@id".',
        [
          '@id' => $dummy->id(),
        ]));
    }

    if (empty($dummy->getName()) === TRUE) {
      throw new BadRequestHttpException(t(
        'Required new item information is malformed with an empty name.'));
    }

    //
    // Dispatch
    // --------
    // Handle each of the POST operations.
    switch ($operation) {
      case 'new-rootfolder':
        return $this->postNewRootFolder($dummy);

      case 'new-folder':
        return $this->postNewFolder($dummy);

      case 'new-file':
        return $this->postNewFile($dummy);

      case 'new-rootfile':
        return $this->postNewRootFile($dummy);

      case 'new-media':
        // TODO implement!
        throw new BadRequestHttpException(
          'Media item creation is not yet supported.');
    }
  }

  /*--------------------------------------------------------------------
   *
   * Create folder.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP POST request to create a new root folder.
   *
   * The HTTP request contains:
   * - X-FolderShare-Post-Operation = "new-rootfolder".
   * - A dummy entity containing initial field values, including the name.
   *
   * The HTTP response contains:
   * - The new entity's URL.
   *
   * @param \Drupal\foldershare\FolderShareInterface $dummy
   *   The dummy entity created from incoming unserialized data.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if arguments are bad.
   */
  private function postNewRootFolder(FolderShareInterface $dummy) {
    //
    // Check create access
    // -------------------
    // Confirm that the user can create root items.
    $summary = FolderShareAccessControlHandler::getRootAccessSummary(
      FoldershareInterface::USER_ROOT_LIST,
      NULL);
    if (isset($summary['create']) === FALSE ||
        $summary['create'] === FALSE) {
      // Access denied.
      throw new AccessDeniedHttpException(
        $this->getDefaultAccessDeniedMessage('create'));
    }

    //
    // Create root folder
    // ------------------
    // Use the dummy entity's name and create a new root folder for the user.
    // Do not auto-rename to avoid name collisions. If the name collides,
    // report it to the user as an error.
    try {
      $folder = FolderShare::createRootFolder($dummy->getName(), FALSE);
    }
    catch (LockException $e) {
      throw new HttpException(Response::HTTP_LOCKED, $e->getMessage());
    }
    catch (ValidationException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
    catch (\Exception $e) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
    }

    //
    // Set fields
    // ----------
    // Loop through the dummy entity and copy changable fields into the
    // new folder. Ignore fields that cannot be edited.
    foreach ($dummy as $fieldName => $field) {
      // Ignore the "name" field handled above when we created the entity.
      if ($fieldName === 'name') {
        continue;
      }

      // Ignore empty fields.
      if ($field->isEmpty() === TRUE) {
        continue;
      }

      // Ignore fields that cannot be edited.
      if ($field->access('edit', NULL, FALSE) === FALSE) {
        continue;
      }

      // Set the entity's field.
      if (empty($field) === FALSE) {
        $folder->set($fieldName, $field);
      }
    }

    $folder->save();

    //
    // URL
    // ---
    // Create the URL for the updated entity.
    $url = $folder->toUrl(
      'canonical',
      ['absolute' => TRUE])->toString(TRUE);

    $headers = [
      'Location' => $url->getGeneratedUrl(),
    ];

    return new UncacheableResponse(NULL, Response::HTTP_CREATED, $headers);
  }

  /**
   * Responds to an HTTP POST request to create a new folder.
   *
   * The HTTP request contains:
   * - X-FolderShare-Post-Operation = "new-folder".
   * - X-FolderShare-Destination-Path = the path to the destination folder.
   * - A dummy entity containing initial field values, including the name.
   *
   * The HTTP response contains:
   * - The new entity's URL.
   *
   * The destination path header should contain the path to a parent folder.
   * If the header is missing, the dummy entity must have a parent ID field
   * set.
   *
   * @param \Drupal\foldershare\FolderShareInterface $dummy
   *   The dummy entity created from incoming unserialized data.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if arguments are bad.
   */
  private function postNewFolder(FolderShareInterface $dummy) {
    //
    // Find the parent
    // ---------------
    // Check for a header destination path. If found, it must provide
    // the path to the parent folder, which must exist.
    $destinationPath = $this->getDestinationPath();

    if (empty($destinationPath) === FALSE) {
      try {
        $parentId = FolderShare::findPathItemId($destinationPath);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }
    else {
      $parentId = $dummy->getParentFolderId();
    }

    if ($parentId === FALSE) {
      throw new BadRequestHttpException(t(
        'Required new entity information is malformed with a bad parent entity ID "@id".',
        [
          '@id' => $parentId,
        ]));
    }

    $parent = FolderShare::load($parentId);
    if ($parent === NULL) {
      throw new BadRequestHttpException(t(
        'Required new entity information is malformed with a bad parent entity ID "@id".',
        [
          '@id' => $parentId,
        ]));
    }

    if ($parent->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($parent->isSystemDisabled() === TRUE) {
      // Disabled items cannot be used.
      throw new AccessDeniedHttpException();
    }

    //
    // Check create access
    // -------------------
    // Confirm that the user can create folders in the parent folder.
    $access = $parent->access('create', NULL, TRUE);
    if ($access->isAllowed() === FALSE) {
      // Access denied.
      $message = $access->getReason();
      if (empty($message) === TRUE) {
        $message = $this->getDefaultAccessDeniedMessage('create');
      }

      throw new AccessDeniedHttpException($message);
    }

    //
    // Create folder
    // -------------
    // Use the dummy entity's name and create a new folder in the parent.
    // Do not auto-rename to avoid name collisions. If the name collides,
    // report it to the user as an error.
    try {
      $folder = $parent->createFolder($dummy->getName(), FALSE);
    }
    catch (LockException $e) {
      throw new HttpException(Response::HTTP_LOCKED, $e->getMessage());
    }
    catch (ValidationException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
    catch (\Exception $e) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
    }

    //
    // Set fields
    // ----------
    // Loop through the dummy entity and copy changable fields into the
    // new folder. Ignore fields that cannot be edited.
    foreach ($dummy as $fieldName => $field) {
      // Ignore the "name" field handled above when we created the entity.
      if ($fieldName === 'name') {
        continue;
      }

      // Ignore empty fields.
      if ($field->isEmpty() === TRUE) {
        continue;
      }

      // Ignore fields that cannot be edited.
      if ($field->access('edit', NULL, FALSE) === FALSE) {
        continue;
      }

      // Set the entity's field.
      if (empty($field) === FALSE) {
        $folder->set($fieldName, $field);
      }
    }

    $folder->save();

    //
    // URL
    // ---
    // Create the URL for the updated entity.
    $url = $folder->toUrl(
      'canonical',
      ['absolute' => TRUE])->toString(TRUE);

    $headers = [
      'Location' => $url->getGeneratedUrl(),
    ];

    return new UncacheableResponse(NULL, Response::HTTP_CREATED, $headers);
  }

  /*--------------------------------------------------------------------
   *
   * Upload file.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP POST request to create a new root file.
   *
   * The dummy entity's 'name' field must have the name of the new file.
   *
   * The HTTP request contains:
   * - X-FolderShare-Post-Operation = "new-rootfile".
   * - X-FolderShare-Destination-Path = the path to the destination folder.
   * - A dummy entity containing initial field values, including the name.
   *
   * The HTTP response contains:
   * - The new entity's URL.
   *
   * @param \Drupal\foldershare\FolderShareInterface $dummy
   *   The dummy entity created from incoming unserialized data.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if arguments are bad.
   *
   * @todo Upgrade to support Drupal 8.6+ file posts.
   */
  private function postNewRootFile(FolderShareInterface $dummy) {
    //
    // Check create access
    // -------------------
    // Confirm that the user can create root items.
    $summary = FolderShareAccessControlHandler::getRootAccessSummary(
      FoldershareInterface::USER_ROOT_LIST,
      NULL);
    if (isset($summary['create']) === FALSE ||
        $summary['create'] === FALSE) {
      // Access denied.
      throw new AccessDeniedHttpException(
        $this->getDefaultAccessDeniedMessage('create'));
    }

    // TODO Cannot continue until Drupal 8.6 supports file posts.
    throw new BadRequestHttpException('File entity creation not yet supported.');

    /*
    //
    // Create file
    // -----------
    // Use the dummy entity's name and create a new folder in the parent.
    // Do not auto-rename to avoid name collisions. If the name collides,
    // report it to the user as an error.
    try {
      $filename = $dummy->getName();
      $file = FolderShare::addInputFileToRoot($filename, FALSE);
    }
    catch (LockException $e) {
      throw new HttpException(Response::HTTP_LOCKED, $e->getMessage());
    }
    catch (ValidationException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
    catch (\Exception $e) {
      throw new HttpException(
        Response::HTTP_INTERNAL_SERVER_ERROR,
        $e->getMessage());
    }

    //
    // URL
    // ---
    // Create the URL for the updated entity.
    $url = $file->toUrl(
      'canonical',
      ['absolute' => TRUE])->toString(TRUE);

    $headers = [
      'Location' => $url->getGeneratedUrl(),
    ];

    return new UncacheableResponse(NULL, Response::HTTP_CREATED, $headers);
    */
  }

  /**
   * Responds to an HTTP POST request to create a new file.
   *
   * The dummy entity's 'name' field must have the name of the new file.
   *
   * The HTTP request contains:
   * - X-FolderShare-Post-Operation = "new-file".
   * - A dummy entity containing initial field values, including the name.
   *
   * The HTTP response contains:
   * - The new entity's URL.
   *
   * The destination path header should contain the path to a parent folder.
   * If the header is missing, the dummy entity must have a parent ID field
   * set.
   *
   * @param \Drupal\foldershare\FolderShareInterface $dummy
   *   The dummy entity created from incoming unserialized data.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if arguments are bad.
   *
   * @todo Upgrade to support Drupal 8.6+ file posts.
   */
  private function postNewFile(FolderShareInterface $dummy) {
    //
    // Find the parent
    // ---------------
    // Check for a header destination path. If found, it must provide
    // the path to the parent folder, which must exist.
    $destinationPath = $this->getDestinationPath();

    if (empty($destinationPath) === FALSE) {
      try {
        $parentId = FolderShare::findPathItemId($destinationPath);
      }
      catch (NotFoundException $e) {
        throw new NotFoundHttpException($e->getMessage());
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }
    else {
      $parentId = $dummy->getParentFolderId();
    }

    if ($parentId === FALSE) {
      throw new BadRequestHttpException(t(
        'Required new entity information is malformed with a bad parent entity ID "@id".',
        [
          '@id' => $parentId,
        ]));
    }

    $parent = FolderShare::load($parentId);
    if ($parent === NULL) {
      throw new NotFoundHttpException(t(
        'Required new entity information is malformed with a bad parent entity ID "@id".',
        [
          '@id' => $parentId,
        ]));
    }

    if ($parent->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($parent->isSystemDisabled() === TRUE) {
      // Disabled items cannot be used.
      throw new AccessDeniedHttpException();
    }

    //
    // Check create access
    // -------------------
    // Confirm that the user can create files in the parent folder.
    $access = $parent->access('create', NULL, TRUE);
    if ($access->isAllowed() === FALSE) {
      // Access denied.
      $message = $access->getReason();
      if (empty($message) === TRUE) {
        $message = $this->getDefaultAccessDeniedMessage('create');
      }

      throw new AccessDeniedHttpException($message);
    }

    // TODO Cannot continue until Drupal 8.6 supports file posts.
    throw new BadRequestHttpException('File entity creation not yet supported.');

    /*
    //
    // Create file
    // -----------
    // Use the dummy entity's name and create a new folder in the parent.
    // Do not auto-rename to avoid name collisions. If the name collides,
    // report it to the user as an error.
    try {
      $filename = $dummy->getName();
      $file = $parent->addInputFile($filename, FALSE);
    }
    catch (LockException $e) {
      throw new HttpException(Response::HTTP_LOCKED, $e->getMessage());
    }
    catch (ValidationException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
    catch (\Exception $e) {
      throw new HttpException(
        Response::HTTP_INTERNAL_SERVER_ERROR,
        $e->getMessage());
    }

    //
    // URL
    // ---
    // Create the URL for the updated entity.
    $url = $file->toUrl(
      'canonical',
      ['absolute' => TRUE])->toString(TRUE);

    $headers = [
      'Location' => $url->getGeneratedUrl(),
    ];

    return new UncacheableResponse(NULL, Response::HTTP_CREATED, $headers);
    */
  }

}
