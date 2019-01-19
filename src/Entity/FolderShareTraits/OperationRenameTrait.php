<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Rename Foldershare entities.
 *
 * This trait includes methods to rename FolderShare entities and
 * wrapped File entities.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationRenameTrait {

  /*---------------------------------------------------------------------
   *
   * Rename FolderShare entity.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function rename(string $newName) {
    //
    // Validate
    // --------
    // The new name must be different and legal.
    $oldName = $this->getName();
    if ($oldName === $newName) {
      // No change.
      return;
    }

    // The checkName() function throws an exception if the name is too
    // long or uses illegal characters.
    $this->checkName($newName);

    //
    // Lock
    // ----
    // LOCK THIS ITEM.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be renamed at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    // LOCK PARENT FOLDER.
    // If this item has a parent, lock the parent too while we
    // check for a name collision.
    $parentFolder = $this->getParentFolder();
    if ($parentFolder !== NULL && $parentFolder->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The parent folder "@name" is in use and cannot be updated at this time.',
          [
            '@name' => $parentFolder->getName(),
          ])));
    }

    //
    // Check names
    // -----------
    // Check name uniqueness in the parent. This has to be done while the
    // parent is locked so that no other item can get in while we're checking.
    if ($parentFolder !== NULL) {
      if ($parentFolder->isNameUnique($newName, (int) $this->id()) === FALSE) {
        // UNLOCK PARENT FOLDER.
        // UNLOCK THIS ITEM.
        $parentFolder->releaseLock();
        $this->releaseLock();
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'The name "@name" is already in use.',
            [
              '@name' => $newName,
            ]),
          t('Please use a different name.')));
      }
    }
    elseif (self::isRootNameUnique($newName, (int) $this->id()) === FALSE) {
      // UNLOCK THIS ITEM.
      $this->releaseLock();
      throw new ValidationException(Utilities::createFormattedMessagE(
        t(
          'The name "@name" is already in use.',
          [
            '@name' => $newName,
          ]),
          t('Please use a different name.')));
    }

    //
    // Execute
    // -------
    // Change the name!
    $this->setName($newName);
    $this->save();

    // If the item is a file, image, or media object change the underlying
    // item's name too.
    $this->renameWrappedFile($newName);

    //
    // Unlock
    // ------
    // UNLOCK PARENT FOLDER, if needed.
    // UNLOCK THIS ITEM.
    if ($parentFolder !== NULL) {
      $parentFolder->releaseLock();
    }

    $this->releaseLock();

    self::postOperationHook(
      'rename',
      [
        $this,
        $oldName,
        $newName,
      ]);
    self::log(
      'notice',
      'Renamed entity @id ("%oldName") to "%newName".',
      [
        '@id'      => $this->id(),
        '%oldName' => $oldName,
        '%newName' => $newName,
        'link'     => $this->toLink(t('View'))->toString(),
      ]);
  }

  /*---------------------------------------------------------------------
   *
   * Rename wrapped File entity.
   *
   *---------------------------------------------------------------------*/

  /**
   * Renames an entity's underlying file, image, and media entity.
   *
   * After a FolderShare entity has been renamed, this method updates any
   * underlying entities to share the same name. This includes File objects
   * underneath 'file' and 'image' kinds, and Media objects underneath
   * 'media' kinds.
   *
   * This method has no effect if the current entity is not a file, image,
   * or media wrapper.
   *
   * @param string $newName
   *   The new name for the underlying entities.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   */
  private function renameWrappedFile(string $newName) {
    // If the item is a file, image, or media object change the underlying
    // item's name too.  This public name appears in the file and image URI
    // as well so that field formatters can see a name and extension,
    // if they need it.
    if ($this->isFile() === TRUE || $this->isImage() === TRUE) {
      // Get the file's MIME type based upon the new name, which may
      // have changed the file name extension.
      $mimeType = \Drupal::service('file.mime_type.guesser')->guess($newName);

      if ($this->isFile() === TRUE) {
        $file = $this->getFile();
      }
      else {
        $file = $this->getImage();
      }

      if ($file !== NULL) {
        // Set the name first. This is used by the FileUtilities call below
        // to compute a new URI based upon the name (really, just the
        // extension) and the file's entity ID.
        $file->setFilename($newName);
        $file->save();

        $oldUri = $file->getFileUri();
        $newUri = FileUtilities::getFileUri($file);

        // If the URIs differ, then something about the new name has caused
        // the underlying saved file to change its name. This is probably the
        // filename extension. Move the file.
        if ($oldUri !== $newUri) {
          // It should not be possible for the following move to fail. The
          // old and new URIs differ and both are based upon the file
          // entity's ID, which is unique. This prevents name collisions.
          // Only the filename extensions can differ.
          //
          // The only errors that can occur are problems with the underlying
          // server file system. And there's nothing we can do about them.
          // The above function will report those to the server log.
          file_unmanaged_move($oldUri, $newUri, FILE_EXISTS_ERROR);

          // Update the URI to point to the moved file.
          $file->setFileUri($newUri);

          // If the file name has changed, the extension may have changed,
          // and thus the MIME type may have changed. Save the new MIME type.
          $this->setMimeType($mimeType);
          $file->save();
        }
      }

      // If the newly renamed file now has a top-level MIME type that
      // indicates a change from a generic file to an image, or the
      // reverse, then we need to swap use of the 'file' and 'image'
      // fields in the FolderShare entity. Both fields still reference
      // a File entity.
      $isForImage = self::isMimeTypeImage($mimeType);
      if ($this->isFile() === TRUE && $isForImage === TRUE) {
        // The file was a generic file, and now it is an image.
        $this->clearFileId();
        $this->setImageId($file->id());
        $this->setKind(self::IMAGE_KIND);
      }
      elseif ($this->isImage() === TRUE && $isForImage === FALSE) {
        // The file was an image, and now it is a generic file.
        $this->setFileId($file->id());
        $this->clearImageId();
        $this->setKind(self::FILE_KIND);
      }

      $this->save();
    }
    elseif ($this->isMedia() === TRUE) {
      $media = $this->getMedia();
      if ($media !== NULL) {
        $media->setName($newName);
        $media->save();
      }
    }
  }

}
