<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\NotFoundException;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Plugin\rest\resource\UncacheableResponse;

/**
 * Respond to DELETE requests.
 *
 * This trait includes methods that implement a generic response to
 * HTTP DELETE requests.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait DeleteResponseTrait {

  /*--------------------------------------------------------------------
   *
   * Generic.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP DELETE requests.
   *
   * The HTTP request contains:
   * - X-FolderShare-Delete-Operation = "delete-file", "delete-folder",
   *   "delete-folder-tree", or "delete-file-and-folder-tree".
   *
   * The HTTP response contains:
   * - A no-content message.
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
   *   Throws an exception if arguments are bad.
   */
  public function delete(int $id = NULL) {
    //
    // Get the operation
    // -----------------
    // Get the specific DELETE operation to perform from the HTTP header,
    // if any. If a header is not provided, default to getting an entity.
    $operation = $this->getAndValidateDeleteOperation();

    //
    // Get source
    // ----------
    // Get the source entity using the HTTP header, if any.
    $source = $this->getSourcePath();

    if (empty($source) === FALSE) {
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

    // Load the entity. Check for 'delete' access.
    $entity = $this->loadAndValidateEntity($id, "delete");

    //
    // Delete
    // ------
    // Delete the entity. If the entity is a folder, this recurses to delete
    // all children as well.
    switch ($operation) {
      default:
      case 'delete-file':
        // Delete a file. Fail if the entity is not a file.
        if ($entity->isFolder() === TRUE) {
          throw new BadRequestHttpException(t(
            "The '@operation' web service only deletes files, but a folder was provided.",
            [
              '@operation' => $operation,
            ]));
        }
        break;

      case 'delete-folder':
        // Delete a folder, without recursion. Fail if the entity is not a
        // folder or the folder is not empty.
        if ($entity->isFolder() === FALSE) {
          throw new BadRequestHttpException(t(
            "The '@operation' web service only deletes folders, but a file was provided.",
            [
              '@operation' => $operation,
            ]));
        }

        if (empty($entity->findChildrenIds()) === FALSE) {
          throw new BadRequestHttpException(t(
            "The '@name' folder cannot be deleted. It is not empty.",
            [
              '@name' => $entity->getName(),
            ]));
        }
        break;

      case 'delete-folder-tree':
        // Delete a folder, with recursion. Fail if the entity is not a folder.
        if ($entity->isFolder() === FALSE) {
          // When deleting a folder, the entity must be a folder.
          throw new BadRequestHttpException(t(
            "The '@operation' web service only deletes files, but a folder was provided.",
            [
              '@operation' => $operation,
            ]));
        }
        break;

      case 'delete-file-or-folder':
        // Delete a file or folder, without recursion. Fail if the entity
        // is a folder and the folder is not empty.
        if (empty($entity->findChildrenIds()) === FALSE) {
          throw new BadRequestHttpException(t(
            "The '@name' folder cannot be deleted. It is not empty.",
            [
              '@name' => $entity->getName(),
            ]));
        }
        break;

      case 'delete-file-or-folder-tree':
        // Delete a file or folder, with recursion. This will delete anything.
        // Fall through.
        break;
    }

    try {
      // Delete!
      $entity->delete();

      return new UncacheableResponse(NULL, Response::HTTP_NO_CONTENT);
    }
    catch (LockException $e) {
      throw new HttpException(Response::HTTP_LOCKED, $e->getMessage());
    }
    catch (\Exception $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

}
