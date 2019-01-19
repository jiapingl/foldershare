<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Drupal\Core\Entity\EntityInterface;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\Exception\NotFoundException;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Plugin\rest\resource\UncacheableResponse;

/**
 * Respond to HTTP PATCH requests.
 *
 * This trait includes methods that implement generic and operation-specific
 * responses to HTTP PATCH requests.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait PatchResponseTrait {

  /*--------------------------------------------------------------------
   *
   * Generic.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP PATCH request.
   *
   * PATCH requests update or edit content, copy, move, archive, and
   * unarchive entities.
   *
   * @param int $id
   *   The FolderShare entity ID.
   * @param \Drupal\Core\Entity\EntityInterface $dummy
   *   The dummy entity created from incoming unserialized data.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *
   * @todo The "update-sharing" operation is not yet implemented.
   *
   * @todo The "archive" operation is not yet implemented.
   *
   * @todo The "unarchive" operation is not yet implemented.
   */
  public function patch(int $id = NULL, EntityInterface $dummy = NULL) {
    //
    // Get operation
    // -------------
    // Use the HTTP header to get the PATCH operation, if any.
    $operation = $this->getAndValidatePatchOperation();

    //
    // Dispatch
    // --------
    // Handle each of the POST operations.
    switch ($operation) {
      case 'update-entity':
        return $this->patchEntity($id, $dummy);

      case 'update-sharing':
        // TODO implement!
        throw new BadRequestHttpException(
          'Sharing update not yet supported.');

      case 'archive':
        // TODO implement!
        throw new BadRequestHttpException(
          'Archive update not yet supported.');

      case 'unarchive':
        // TODO implement!
        throw new BadRequestHttpException(
          'Unarchive update not yet supported.');

      case 'copy-overwrite':
        return $this->patchCopy($id, TRUE);

      case 'copy-no-overwrite':
        return $this->patchCopy($id, FALSE);

      case 'move-overwrite':
        return $this->patchMove($id, TRUE);

      case 'move-no-overwrite':
        return $this->patchMove($id, FALSE);
    }
  }

  /*--------------------------------------------------------------------
   *
   * Update entity.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP PATCH request to update a FolderShare entity.
   *
   * The HTTP request contains:
   * - X-FolderShare-Patch-Operation = "update-entity".
   * - A dummy entity containing the fields to update.
   *
   * The HTTP response contains:
   * - The serialized updated entity.
   *
   * @param int $id
   *   The FolderShare entity ID. If NULL, negative, or EMPTY_ITEM_ID,
   *   then no entity ID was provided.
   * @param \Drupal\Core\Entity\EntityInterface $dummy
   *   The dummy entity created from incoming unserialized data. The dummy
   *   cannot be NULL.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  private function patchEntity(int $id = NULL, EntityInterface $dummy = NULL) {
    //
    // Validate dummy entity
    // ---------------------
    // A dummy entity must have been provided and it must have the proper
    // entity type.
    if ($dummy === NULL ||
        count($dummy->_restSubmittedFields) === 0) {
      throw new BadRequestHttpException(t(
        'Required update information is missing.'));
    }

    if ($dummy->getEntityTypeId() !== FolderShare::ENTITY_TYPE_ID) {
      throw new BadRequestHttpException(t(
        'Required update information is malformed with a bad entity type.'));
    }

    if ($id === NULL) {
      $id = self::EMPTY_ITEM_ID;
    }

    //
    // Find the entity
    // ---------------
    // Check for a header source path. If found, it must provide
    // the path to an existing entity.
    $sourcePath = $this->getSourcePath();

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

    if ($id < 0) {
      throw new BadRequestHttpException(t(
        'Required update information is malformed with a bad ID "@id".',
        [
          '@id' => $id,
        ]));
    }

    $entity = FolderShare::load($id);
    if ($entity === NULL) {
      throw new NotFoundHttpException(t(
        'Required update information is malformed with a bad ID "@id".',
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

    //
    // Entity access control
    // ---------------------
    // Check if the current user has permisison to update the current entity.
    $access = $entity->access('update', NULL, TRUE);

    if ($access->isAllowed() === FALSE) {
      // No access. If a reason was not provided, use a default.
      $message = $access->getReason();
      if (empty($message) === TRUE) {
        $message = $this->getDefaultAccessDeniedMessage('update');
      }

      throw new AccessDeniedHttpException($message);
    }

    //
    // Update entity
    // -------------
    // Loop through the dummy entity's fields that were submitted in the
    // PATCH. For each one, check if the user is allowed to update the
    // field. If not, silently skip the field. Otherwise update the field's
    // value.
    //
    // Watch specifically for entity name changes since we need to handle
    // them specially.
    $oldName = $entity->getName();
    $newName = $entity->getName();
    foreach ($dummy->_restSubmittedFields as $fieldName) {
      if ($fieldName === 'name') {
        $newName = $dummy->getName();
      }
      elseif ($entity->get($fieldName)->access('edit') === FALSE) {
        throw new AccessDeniedHttpException(t(
          'Access denied for updating item field "@fieldname".',
          [
            '@fieldname' => $fieldName,
          ]));
      }
      else {
        // Copy new field values.
        $entity->set($fieldName, $dummy->get($fieldName)->getValue());
      }
    }

    $entity->save();

    // If a new name has been provided, rename the entity. This checks
    // for name legality and collisions with other names.
    if ($oldName !== $newName) {
      try {
        $entity->rename($newName);
      }
      catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    return $this->formatEntityResponse($entity, TRUE);
  }

  /*--------------------------------------------------------------------
   *
   * Copy entity.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to an HTTP POST request to copy a FolderShare entity.
   *
   * The HTTP request contains:
   * - X-FolderShare-Patch-Operation = "copy-overwrite" or "copy-no-overwrite".
   *
   * The HTTP response contains:
   * - The URL of the new copy.
   *
   * This method is modeled after the way the Linux/macOS/BSD "cp" command
   * operates. It supports copying an item and copying and renaming an item
   * at the same time.
   *
   * The source path must refer to an existing file or folder to be copied.
   *
   * The destination path may be one of:
   * - A "/" to refer to the user's rootlist.
   * - A path to an existing file or folder.
   * - A path to a non-existant item within an existing parent folder.
   *
   * The copied item will have the same name as in the source path. If there
   * is already an item with the same name in "/", the copy will fail unless
   * $overwrite is TRUE.
   *
   * If destination refers to a non-existant item, then the item referred to
   * by source will be copied into the destination's parent folder and
   * renamed to use the last name on destination.
   *
   * If destination refers to an existing folder, the file or folder referred
   * to by source will be copied into the destination folder and retain its
   * current name. If there is already an item with that name in the
   * destination folder, the copy will fail unless $overwrite is TRUE.
   *
   * If destination refers to an existing file, the copy will fail unless
   * $overwrite is TRUE. If overwrite is allowed, the item referred to by
   * source will be copied into the destination item's parent folder and
   * renamed to have th last name in destination. If destination's parent
   * folder is "/", then source must refer to a folder since files cannot be
   * copied into "/".
   *
   * In any case where an overwrite is required, if $overwrite is FALSE
   * the operation will fail. Otherwise the item to overwrite will be
   * deleted before the copy takes place.
   *
   * The user must have permission to view the item referred to by source.
   * They user must have permission to modify the folder or rootlist into
   * which the item will be placed, and permission to create the new item there.
   * When overwriting an existing item, the user must have permission to
   * delete that item.
   *
   * @param int $id
   *   The FolderShare entity ID.
   * @param bool $overwrite
   *   When TRUE, allow the copy to overwrite a same-name entity at the
   *   destination.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an empty uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if there is no source or destination path,
   *   if either path is malformed, if items are locked, if the user
   *   does not have permission, or if there will be a collision but
   *   $overwrite is FALSE.
   */
  private function patchCopy(
    int $id = NULL,
    bool $overwrite = TRUE) {

    //
    // Validation
    // ----------
    // Much of the following code is about validation, which we summarize here:
    //
    // 1. There must be a valid source entity specified by a source path
    //    in the header. The source entity must exist and be loaded.
    //
    // 2. The user must have update access on the source entity.
    //
    // 3. There must be a valid destination path. That path can refer to a
    //    destination in one of three ways:
    //    - The path is / and indicates a root list.
    //    - The path indicates an existing entity.
    //    - The path does not indicate an existing entity, but the parent
    //      path does.
    //
    // 4. If the destination is a parent path, then the name at the end
    //    of the original destination path is the proposed new name for the
    //    entity. The name cannot be empty. Later, during the copy, the name
    //    will be checked for legality.
    //
    // 5. If the destination is '/', the user must have root item create
    //    access. Otherwise the user must have update access on the
    //    destination.
    //
    // 6. If the source is a folder, the destination cannot be a file.
    //
    // 7. If the source collides with an item with the same name in the
    //    destination, $overwrite must be TRUE in order to delete it and
    //    then do the copy. The user also needs delete access to that entity.
    //
    // Find and validate source
    // ------------------------
    // Use the URL id or a header source path to get and load a source entity.
    // This will fail if:
    // - The source path is malformed.
    // - The source path is /.
    // - The source path refers to a non-existent entity.
    $sourceEntity = $this->getSourceAndValidate($id, TRUE);

    // Verify the user has access. This will fail if:
    // - The user does not have view access to the source entity.
    $this->validateAccess($sourceEntity, 'view');

    // Use the current entity name as the proposed new name.
    $destinationName = $sourceEntity->getName();

    //
    // Find the destination
    // --------------------
    // Check for a header destination path. It must exist and provide
    // the path to one of:
    // - /.
    // - An existing file or folder.
    // - An existing parent folder before the last name on the path.
    //
    // Get the destination path.
    $destinationPath = $this->getDestinationPath();
    if (empty($destinationPath) === TRUE) {
      throw new BadRequestHttpException(t(
        'The path to the destination item is empty.'));
    }

    try {
      // Parse the path. This will fail if:
      // - The destination path is malformed.
      $parts = FolderShare::parsePath($destinationPath);
    }
    catch (ValidationException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }

    $destinationId     = FALSE;
    $destinationEntity = NULL;
    $destinationName   = '';

    // When the destination path is NOT '/', it either refers to an existing
    // folder or to a non-existing name within an existing parent folder.
    if ($parts['path'] !== '/') {
      // Try to get the entity. This will throw an exception if:
      // - The destination path refers to a non-existent entity.
      try {
        $destinationId = FolderShare::findPathItemId($destinationPath);
      }
      catch (NotFoundException $e) {
        // The path was parsed but it doesn't point to a valid entity.
        // Back out out folder in the path and try again.
        //
        // Break the path into folders.
        $folders = mb_split('/', $parts['path']);

        // Skip the leading '/'.
        array_shift($folders);

        // Pull out the last name on the path as the proposed name of the
        // new entity. This will fail if:
        // - The destination name is empty.
        $destinationName = array_pop($folders);
        if (empty($destinationName) === TRUE) {
          throw new BadRequestHttpException(t(
            "The path to the destination must end with the new name for the item."));
        }

        // If the folder list is now empty, then we had a path like "/fred".
        // So we're moving into "/" and no further entity loading is required.
        // Otherwise, rebuild the path and try to load the parent folder.
        if (count($folders) !== 0) {
          // Rebuild the path, including the original scheme and UID.
          $parentPath = $parts['scheme'] . '://' . $parts['uid'];
          foreach ($folders as $f) {
            $parentPath .= '/' . $f;
          }

          // Try again. This will throw an exception if:
          // - The parent path refers to a non-existent entity.
          try {
            $destinationId = FolderShare::findPathItemId($parentPath);
          }
          catch (NotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage());
          }
        }
      }
      catch (ValidationException $e) {
        throw new BadRequestHttpException($e->getMessage());
      }

      // Above, we found one of:
      // - A destination entity with $destinationId set and $destinationName
      //   left empty.
      //
      // - A parent destination entity with $destinationId and
      //   $destinationName set for as the name for the renamed item.
      //
      // - A / parent for the root list with $destinationId left as FALSE and
      //   $destinationName set for as the name for the renamed item.
      //
      // If $destinationId is set, load the entity and verify access.
      if ($destinationId !== FALSE) {
        $destinationEntity = FolderShare::load($destinationId);
        if ($destinationEntity === NULL) {
          throw new NotFoundHttpException(t(
            'Required update information is malformed with a bad destination ID "@id".',
            [
              '@id' => $destinationId,
            ]));
        }

        if ($destinationEntity->isSystemHidden() === TRUE) {
          // Hidden items do not exist.
          throw new NotFoundHttpException();
        }

        if ($destinationEntity->isSystemDisabled() === TRUE) {
          // Disabled items cannot be used.
          throw new AccessDeniedHttpException();
        }

        // Verify the user has access. This will fail if:
        // - The user does not have update access to a destination entity.
        $this->validateAccess($destinationEntity, 'update');
      }
    }

    // When the path is '/', or if '/' is the parent of a path to a
    // non-existant entity, then verify that the user has access to the
    // root list and permission to create an item there.
    if ($destinationId === FALSE) {
      // The source is being moved into the root list.  This will fail if:
      // - The root list is not the user's own list.
      switch ($parts['scheme']) {
        case FolderShare::PERSONAL_SCHEME:
          $uid = (int) $parts['uid'];
          if ($uid !== (int) \Drupal::currentUser()->id()) {
            throw new AccessDeniedHttpException(t(
              "Access denied. The item '@name' cannot be copied into another user's top-level folder list.",
              [
                '@name' => $sourceEntity->getName(),
              ]));
          }
          break;

        case FolderShare::PUBLIC_SCHEME:
          throw new AccessDeniedHttpException(t(
            "Access denied. The item '@name' cannot be copied into the public top-level folder list.\nSet the item to be shared with the public instead.",
            [
              '@name' => $sourceEntity->getName(),
            ]));
      }

      // Verify the user has root item access. This will fail if:
      // - The user does not have create access to create root items.
      $this->validateAccess(NULL, 'create');
    }

    //
    // Confirm source & destination legality
    // -------------------------------------
    // At this point we have one of these destination cases:
    // - The destination for the copy is a folder in $destinationEntity.
    //   If the item is being renamed, $destinationName is non-empty.
    //
    // - The destination for the copy is the root list and
    //   $destinationEntity is NULL. If the item is being renamed,
    //   $destinationName is non-empty.
    //
    // Verify that the source can be added to the destination. This
    // will fail if:
    // - The source is a folder but the destination is a file.
    // - The source and destination are files but overwrite is not allowed.
    if ($destinationEntity !== NULL &&
        $destinationEntity->isFolder() === FALSE) {
      // The destination is a file. This is always invalid. Report
      // different error messages based on whether we're trying to
      // copy a folder or a file.
      if ($sourceEntity->isFolder() === TRUE) {
        throw new BadRequestHttpException(t(
          "The folder '@name' cannot be copied into a file.",
          [
            '@name' => $sourceEntity->getName(),
          ]));
      }
      elseif ($overwrite === FALSE) {
        throw new BadRequestHttpException(t(
          "The folder '@name' cannot be copied to overwrite an existing folder with the same name.",
          [
            '@name' => $sourceEntity->getName(),
          ]));
      }
    }

    //
    // Check for collision
    // -------------------
    // A collision occurs if an item in the destination already exists with
    // the same name as the source. This will fail if:
    // - There is a name collision and overwrite is not allowed.
    $collisionEntity = NULL;

    if (empty($destinationName) === TRUE) {
      $checkName = $sourceEntity->getName();
    }
    else {
      $checkName = $destinationName;
    }

    if ($destinationEntity === NULL) {
      // When moving to '/', check if there is a root item with the
      // proposed name already.
      $uid = \Drupal::currentUser()->id();
      $rootIds = FolderShare::findAllRootItemIds($uid, $checkName);
      if (empty($rootIds) === FALSE) {
        // A root item with the proposed name exists.
        // There can be at most one, so use the 1st item in the array.
        $collisionEntity = FolderShare::load($rootIds[0]);
      }
    }
    elseif ($destinationEntity->isFolder() === TRUE) {
      // When moving a file to a folder, check the list of the folder's names.
      $id = FolderShare::findNamedChildId($destinationId, $checkName);
      if ($id !== FALSE) {
        // A folder with the proposed name exists.
        $collisionEntity = FolderShare::load($id);
      }
    }
    else {
      // When moving a file or folder to a file, the destination file is
      // a collision.
      $collisionEntity = $destinationEntity;
    }

    //
    // Execute the collision delete
    // ----------------------------
    // If $collisionEntity is not NULL, then we have a collision we need to
    // deal with by deleting the entity before doing the copy. But if
    // $overwrite is FALSE, report an error instead.
    if ($collisionEntity !== NULL) {
      if ($collisionEntity->isSystemHidden() === TRUE) {
        // Hidden items do not exist.
        throw new NotFoundHttpException();
      }

      if ($collisionEntity->isSystemDisabled() === TRUE) {
        // Disabled items cannot be used.
        throw new AccessDeniedHttpException();
      }

      if ($overwrite === FALSE) {
        throw new BadRequestHttpException(t(
          "The item '@name' cannot be copied to overwrite an existing item with the same name.",
          [
            '@name' => $checkName,
          ]));
      }

      // Verify the user can delete the entity. This will fail if:
      // - The user does not have delete access to the collision entity.
      $this->validateAccess($collisionEntity, 'delete');

      // Delete the entity. This will fail if:
      // - Something is locked by another process.
      try {
        $collisionEntity->delete();
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
    }

    //
    // Execute the copy
    // ----------------
    // Copy the source to the destination folder, or the root item list.
    // Provide the new name for the entity, if one was provided.
    //
    // This can fail if:
    // - Something is locked by another process.
    // - There is a name collision (another process added something after we
    //   deleted the collision noted above). This is very unlikely.
    try {
      if ($destinationEntity !== NULL) {
        $copy = $sourceEntity->copyToFolder($destinationEntity, $destinationName);
      }
      else {
        $copy = $sourceEntity->copyToRoot($destinationName);
      }
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
    // URL
    // ---
    // Create the URL for the updated entity.
    $url = $copy->toUrl(
      'canonical',
      ['absolute' => TRUE])->toString(TRUE);

    $headers = [
      'Location' => $url->getGeneratedUrl(),
    ];

    return new UncacheableResponse(NULL, Response::HTTP_CREATED, $headers);
  }

  /*--------------------------------------------------------------------
   *
   * Move entity.
   *
   *--------------------------------------------------------------------*/

  /**
   * Responds to PATCH requests to move a FolderShare entity.
   *
   * The HTTP request contains:
   * - X-FolderShare-Patch-Operation = "move-overwrite" or "move-no-overwrite".
   *
   * The HTTP response contains:
   * - The URL of the moved entity.
   *
   * This method is modeled after the way the Linux/macOS/BSD "mv" command
   * operates. It supports moving an item, renaming an item in place, or
   * moving and renaming at the same time.
   *
   * The source path must refer to an existing file or folder to be moved
   * and/or renamed.
   *
   * The destination path may be one of:
   * - A "/" to refer to the user's root list.
   * - A path to an existing file or folder.
   * - A path to a non-existant item within an existing parent folder.
   *
   * The moved item will have the same name as in the source path. If there
   * is already an item with the same name in "/", the move will fail unless
   * $overwrite is TRUE.
   *
   * If destination refers to a non-existant item, then the item referred to
   * by source will be moved into the destination's parent folder and
   * renamed to use the last name on destination. If the destination's
   * parent folder is "/", then source must refer to a folder since files
   * cannot be moved into "/".
   *
   * If destination refers to an existing folder, the file or folder referred
   * to by source will be moved into the destination folder and retain its
   * current name. If there is already an item with that name in the
   * destination folder, the move will fail unless $overwrite is TRUE.
   *
   * If destination refers to an existing file, the move will fail unless
   * $overwrite is TRUE. If overwrite is allowed, the item referred to by
   * source will be moved into the destination item's parent folder and
   * renamed to have th last name in destination.
   *
   * In any case where an overwrite is required, if $overwrite is FALSE
   * the operation will fail. Otherwise the item to overwrite will be
   * deleted before the move and/or rename takes place.
   *
   * The user must have permission to modify the item referred to by source.
   * They user must have permission to modify the folder into which the
   * item will be placed. When moving a folder to "/", the user must have
   * permission to create a new top-level folder. And when overwriting an
   * existing item, the user must have permission to delete that item.
   *
   * @param int $id
   *   The FolderShare entity ID.
   * @param bool $overwrite
   *   When TRUE, allow the move to overwrite a same-name entity at the
   *   destination.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an empty uncacheable response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws an exception if there is no source or destination path,
   *   if either path is malformed, if items are locked, if the user
   *   does not have permission, or if there will be a collision but
   *   $overwrite is FALSE.
   *
   * @internal
   * At first glance, "move" and "rename" seem like separate operations.
   * Combining them into the same one seems to just complicate things.
   * However, it is in fact necessary.
   *
   * Consider the case where file A exists in folder AA among other files,
   * and it needs to be moved into folder BB and renamed as B. If move and
   * rename are separate steps, the rename must happen before or after the
   * move:
   * - If rename comes before the move, then A must be renamed to B before
   *   B is moved to folder BB. But this requires room in folder AA for a
   *   file named B, even though the file isn't going to stay there.
   * - If rename comes after the move, then A must be moved to folder BB
   *   while still named A, then renamed as B. But this requires room in
   *   folder BB for a file named A, even though the file is about to change
   *   names.
   *
   * With rename and move as separate operations, either the starting folder
   * or the ending folder must have room for both the original name AND the
   * new name. The name space footprint of the file is the union of both
   * names, instead of just one name or the other.
   *
   * Doubling the name space footprint of the file invites spurious name
   * space collisions. It becomes possible that file A cannot be renamed to
   * B in folder AA before the move, and it cannot be moved to BB as A
   * before being renamed to be B. Both cases could get a name space
   * collision. The user would be forced to pick a bogus third name C that
   * doesn't collide with names in AA or BB. That bogus name C would only
   * be used long enough to get the file moved. What a mess.
   *
   * So, "move" and "rename" operations must be combinable so that the
   * name space footprint stays at one name and no bogus intermediate name
   * is needed.
   */
  private function patchMove(
    int $id = NULL,
    bool $overwrite = TRUE) {

    //
    // Validation
    // ----------
    // Much of the following code is about validation, which we summarize here:
    //
    // 1. There must be a valid source entity. The source may be specified by
    //    an entity ID in the URL or via a path in a source header. The source
    //    entity must exist and be loaded.
    //
    // 2. The user must have update access on the source entity.
    //
    // 3. There must be a valid destination path. That path can refer to a
    //    destination in one of three ways:
    //    - The path is / and indicates a root list.
    //    - The path indicates an existing entity.
    //    - The path does not indicate an existing entity, but the parent
    //      path does.
    //
    // 4. If the destination is a parent path, then the name at the end
    //    of the original destination path is the proposed new name for the
    //    entity. The name cannot be empty. Later, during the move, the name
    //    will checked for legality.
    //
    // 5. If the destination is '/', the user must have root item create
    //    access. Otherwise the user must have update access on the
    //    destination.
    //
    // 6. If the source is a folder, the destination cannot be a file.
    //
    // 7. If the source collides with an item with the same name in the
    //    destination, $overwrite must be TRUE in order to delete it and
    //    then do the move. The user also needs delete access to that entity.
    //
    // Find and validate source
    // ------------------------
    // Use the URL id or a header source path to get and load a source entity.
    // This will fail if:
    // - The source path is malformed.
    // - The source path is /.
    // - The source path refers to a non-existent entity.
    $sourceEntity = $this->getSourceAndValidate($id, TRUE);

    // Verify the user has access. This will fail if:
    // - The user does not have update access to the source entity.
    $this->validateAccess($sourceEntity, 'update');

    // Use the current entity name as the proposed new name.
    $destinationName = $sourceEntity->getName();

    //
    // Find the destination
    // --------------------
    // Check for a header destination path. It must exist and provide
    // the path to one of:
    // - /.
    // - An existing file or folder.
    // - An existing parent folder before the last name on the path.
    //
    // Get the destination path.
    $destinationPath = $this->getDestinationPath();
    if (empty($destinationPath) === TRUE) {
      throw new BadRequestHttpException(t(
        'The path to the destination item is empty.'));
    }

    try {
      // Parse the path. This will fail if:
      // - The destination path is malformed.
      $parts = FolderShare::parsePath($destinationPath);
    }
    catch (ValidationException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }

    $destinationId     = FALSE;
    $destinationEntity = NULL;
    $destinationName   = '';

    // When the path is NOT '/', it either refers to an existing folder
    // or to a non-existing name within an existing parent folder.
    if ($parts['path'] !== '/') {
      // Try to get the entity. This will fail if:
      // - The destination path refers to a non-existent entity.
      try {
        $destinationId = FolderShare::findPathItemId($destinationPath);
      }
      catch (NotFoundException $e) {
        // The path was parsed but it doesn't point to a valid entity.
        // Back out one folder in the path and try again.
        //
        // Break the path into folders.
        $folders = mb_split('/', $parts['path']);

        // Skip the leading '/'.
        array_shift($folders);

        // Pull out the last name on the path as the proposed name of the
        // new entity. This will fail:
        // - The destination name is empty.
        $destinationName = array_pop($folders);
        if (empty($destinationName) === TRUE) {
          throw new BadRequestHttpException(t(
            "The path to the destination must end with the new name for the item."));
        }

        // If the folder list is now empty, then we had a path like "/fred".
        // So we're moving into "/" and no further entity loading is required.
        // Otherwise, rebuild the path and try to load the parent folder.
        if (count($folders) !== 0) {
          // Rebuild the path, including the original scheme and UID.
          $parentPath = $parts['scheme'] . '://' . $parts['uid'];
          foreach ($folders as $f) {
            $parentPath .= '/' . $f;
          }

          // Try again. This will fail if:
          // - The parent path refers to a non-existent entity.
          try {
            $destinationId = FolderShare::findPathItemId($parentPath);
          }
          catch (NotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage());
          }
        }
      }
      catch (ValidationException $e) {
        throw new BadRequestHttpException($e->getMessage());
      }

      // Above, we found one of:
      // - A destination entity with $destinationId set and $destinationName
      //   left empty.
      //
      // - A parent destination entity with $destinationId and
      //   $destinationName set as the name for the moved item.
      //
      // - A / parent for the root list with $destinationId left as FALSE and
      //   $destinationName set for as the name for the renamed item.
      //
      // If $destinationId is set, load the entity and verify access.
      if ($destinationId !== FALSE) {
        $destinationEntity = FolderShare::load($destinationId);
        if ($destinationEntity === NULL) {
          // This should not be possible since we already validated the ID.
          throw new NotFoundHttpException(t(
            'Required update information is malformed with a bad destination ID "@id".',
            [
              '@id' => $destinationId,
            ]));
        }

        if ($destinationEntity->isSystemHidden() === TRUE) {
          // Hidden items do not exist.
          throw new NotFoundHttpException();
        }

        if ($destinationEntity->isSystemDisabled() === TRUE) {
          // Disabled items cannot be used.
          throw new AccessDeniedHttpException();
        }

        // Verify the user has access. This will fail if:
        // - The user does not have update access to a destination entity.
        $this->validateAccess($destinationEntity, 'update');
      }
    }

    // When the path is '/', or if '/' is the parent of a path to a
    // non-existant entity, then verify that the user has access to the
    // root list and permission to create a folder there.
    if ($destinationId === FALSE) {
      // The source is being moved into the root list.  This will fail if:
      // - The root list is not the user'ss own list.
      switch ($parts['scheme']) {
        case FolderShare::PERSONAL_SCHEME:
          $uid = (int) $parts['uid'];
          if ($uid !== (int) \Drupal::currentUser()->id()) {
            throw new AccessDeniedHttpException(t(
              "Access denied. The item '@name' cannot be moved into another user's top-level folder list.",
              [
                '@name' => $sourceEntity->getName(),
              ]));
          }
          break;

        case FolderShare::PUBLIC_SCHEME:
          throw new AccessDeniedHttpException(t(
            "Access denied. The item '@name' cannot be moved into the public top-level folder list.\nSet the item to be shared with the public instead.",
            [
              '@name' => $sourceEntity->getName(),
            ]));
      }

      // Verify the user has root item access. This will fail if:
      // - The user does not have create access to create root items.
      $this->validateAccess(NULL, 'create');
    }

    //
    // Confirm source & destination legality
    // -------------------------------------
    // At this point we have one of these destination cases:
    // - The destination for the move is a folder in $destinationEntity.
    //   If the item is being renamed, $destinationName is non-empty.
    //
    // - The destination for the move is the root list and
    //   $destinationEntity is NULL. If the item is being renamed,
    //   $destinationName is non-empty.
    //
    // Verify that the source can be added to the destination. This
    // will fail if:
    // - The source is a folder but the destination is a file.
    // - The source and destination are files but overwrite is not allowed.
    if ($destinationEntity !== NULL &&
        $destinationEntity->isFolder() === FALSE) {
      // The destination is a file. This is always invalid. Return a
      // different error message if the source is a folder or a file.
      if ($sourceEntity->isFolder() === TRUE) {
        throw new BadRequestHttpException(t(
          "The folder '@name' cannot be moved into file.",
          [
            '@name' => $sourceEntity->getName(),
          ]));
      }
      elseif ($overwrite === FALSE) {
        throw new BadRequestHttpException(t(
          "The folder '@name' cannot be moved to overwrite an existing folder with the same name.",
          [
            '@name' => $sourceEntity->getName(),
          ]));
      }
    }

    //
    // Check for collision
    // -------------------
    // A collision occurs if an item in the destination already exists with
    // the same name as the source. This will fail if:
    // - There is a name collision and overwrite is not allowed.
    $collisionEntity = NULL;

    if (empty($destinationName) === TRUE) {
      $checkName = $sourceEntity->getName();
    }
    else {
      $checkName = $destinationName;
    }

    if ($destinationEntity === NULL) {
      // When moving to '/', check if there is a root item with the
      // proposed name already.
      $uid = \Drupal::currentUser()->id();
      $rootIds = FolderShare::findAllRootItemIds($uid, $checkName);
      if (empty($rootIds) === FALSE) {
        // A root item with the proposed name exists.
        // There can be at most one, so use the 1st item in the array.
        $collisionEntity = FolderShare::load($rootIds[0]);
      }
    }
    elseif ($destinationEntity->isFolder() === TRUE) {
      // When moving a file to a folder, check the list of the folder's names.
      $id = FolderShare::findNamedChildId($destinationId, $checkName);
      if ($id !== FALSE) {
        // A folder with the proposed name exists.
        $collisionEntity = FolderShare::load($id);
      }
    }
    else {
      // When moving a file or folder to a file, the destination file is
      // a collision.
      $collisionEntity = $destinationEntity;
    }

    //
    // Execute the collision delete
    // ----------------------------
    // If $collisionEntity is not NULL, then we have a collision we need to
    // deal with by deleting the entity before doing the move. But if
    // $overwrite is FALSE, report an error instead.
    if ($collisionEntity !== NULL) {
      if ($collisionEntity->isSystemHidden() === TRUE) {
        // Hidden items do not exist.
        throw new NotFoundHttpException();
      }

      if ($collisionEntity->isSystemDisabled() === TRUE) {
        // Disabled items cannot be used.
        throw new AccessDeniedHttpException();
      }

      if ($overwrite === FALSE) {
        throw new BadRequestHttpException(t(
          "The item '@name' cannot be moved to overwrite an existing item with the same name.",
          [
            '@name' => $checkName,
          ]));
      }

      // Verify the user can delete the entity. This will fail if:
      // - The user does not have delete access to the collision entity.
      $this->validateAccess($collisionEntity, 'delete');

      // Delete the entity. This will fail if:
      // - Something is locked by another process.
      try {
        $collisionEntity->delete();
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
    }

    //
    // Execute the move
    // ----------------
    // Move the source to the destination folder, or the root list.
    // Provide the new name for the entity, if one was provided.
    //
    // This can fail if:
    // - Something is locked by another process.
    // - There is a name collision (another process added something after we
    //   deleted the collision noted above). This is very unlikely.
    try {
      if ($destinationEntity !== NULL) {
        $sourceEntity->moveToFolder($destinationEntity, $destinationName);
      }
      else {
        $sourceEntity->moveToRoot($destinationName);
      }
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
    // URL
    // ---
    // Create the URL for the updated entity.
    $url = $sourceEntity->toUrl(
      'canonical',
      ['absolute' => TRUE])->toString(TRUE);

    $headers = [
      'Location' => $url->getGeneratedUrl(),
    ];

    return new UncacheableResponse(NULL, Response::HTTP_CREATED, $headers);
  }

}
