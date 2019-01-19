<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\SystemException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Archive FolderShare entities to a FolderShare ZIP archive.
 *
 * This trait includes methods to archive one or more FolderShare
 * entities into a ZIP archive saved into a new FolderShare entity.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationArchiveTrait {

  /*---------------------------------------------------------------------
   *
   * Archive to FolderShare entity.
   *
   *---------------------------------------------------------------------*/

  /**
   * Archives the given root items to a new archive in the root list.
   *
   * A new ZIP archive is created in the root list and all of the given
   * items, and their children, recursively, are added to the archive.
   *
   * All items must be root items.
   *
   * @param \Drupal\foldershare\FoldershareInterface[] $items
   *   An array of root items that are to be included in a new archive
   *   added to the root list.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the FolderShare entity for the new archive.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Thrown if any item in the array are not root items.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock on any item could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if the archive file could not be created, such as if the
   *   temporary directory does not exist or is not writable, if a temporary
   *   file for the archive could not be created, or if any of the file
   *   children of this item could not be read and saved into the archive.
   *
   * @section locking Process locks
   * Each child file or folder and all of their subfolders and files are
   * locked for exclusive editing access by this function while they are
   * being added to a new archive. The root list is locked while the new
   * archive is added.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   */
  public static function archiveToRoot(array $items) {
    //
    // Validate.
    // ---------
    // ZIP extensions must be supported and all items must be root items.
    if (empty($items) === TRUE) {
      return NULL;
    }

    if (self::isZipExtensionAllowed() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called to create an archive type the site does not support.',
          [
            '@method' => 'FolderShare::archiveToRoot',
          ]),
        t('The ZIP file type required for new archives is not an allowed file type for the site. Archive creation is therefore not supported.')));
    }

    $itemsToArchive = [];
    foreach ($items as $item) {
      if ($item !== NULL) {
        if ($item->isRootItem() === FALSE) {
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              '@method was called with an item that is not a root item.',
              [
                '@method' => 'FolderShare::archiveToRoot',
              ])));
        }

        $itemsToArchive[] = $item;
      }
    }

    if (empty($itemsToArchive) === TRUE) {
      return NULL;
    }

    //
    // Create archive in local temp storage.
    // -------------------------------------
    // Create a ZIP archive containing the given items.
    //
    // On completion, a ZIP archive exists on the local file system in a
    // temporary file. This throws an exception and deletes the archive on
    // an error.
    $archiveUri = self::createZipArchive($itemsToArchive);

    //
    // Create File entity.
    // -------------------
    // Create a new File entity from the local file. This also moves the
    // file into FolderShare's directory tree and gives it a numeric name.
    // The MIME type is also set, and the File is marked as permanent.
    // This throws an exception if the File could not be created.
    try {
      if (count($itemsToArchive) === 1) {
        // Add '.zip' to the item name.
        $archiveName = $itemsToArchive[0]->getName() . '.zip';
      }
      else {
        // Use a generic name.
        $archiveName = self::NEW_ZIP_ARCHIVE;
      }

      $archiveFile = self::createFileEntityFromLocalFile(
        $archiveUri,
        $archiveName);
    }
    catch (\Exception $e) {
      FileUtilities::unlink($archiveUri);
      throw $e;
    }

    //
    // Add the archive to the root list.
    // ---------------------------------
    // Add the file, checking for and correcting name collisions. Lock
    // the root list during the add.
    try {
      $ary = self::addFilesInternal(
        NULL,
        [$archiveFile],
        TRUE,
        TRUE,
        FALSE,
        TRUE);
    }
    catch (\Exception $e) {
      // On any failure, the archive wasn't added and we need to delete it.
      $archiveFile->delete();
      throw $e;
    }

    if (empty($ary) === TRUE) {
      return NULL;
    }

    return $ary[0];
  }

  /**
   * {@inheritdoc}
   */
  public function archiveToFolder(array $items) {
    //
    // Validate.
    // ---------
    // ZIP extensions must be supported and all items must be children
    // of this folder.
    if (empty($items) === TRUE) {
      return NULL;
    }

    if (self::isZipExtensionAllowed() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called to create an archive type the site does not support.',
          [
            '@method' => 'FolderShare::archiveToFolder',
          ]),
        t('The ZIP file type required for new archives is not an allowed file type for the site. Archive creation is therefore not supported.')));
    }

    if ($this->isFolder() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called with an item that is not a folder.',
          [
            '@method' => 'FolderShare::archiveToFolder',
          ])));
    }

    $parentId = (int) $this->id();
    $itemsToArchive = [];
    foreach ($items as $item) {
      if ($item !== NULL) {
        if ($item->getParentFolderId() !== $parentId) {
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              '@method was called with an item that is not a folder.',
              [
                '@method' => 'FolderShare::archiveToFolder',
              ])));
        }
        $itemsToArchive[] = $item;
      }
    }

    if (empty($itemsToArchive) === TRUE) {
      return NULL;
    }

    //
    // Create archive in local temp storage.
    // -------------------------------------
    // Create a ZIP archive containing the given items.
    //
    // On completion, a ZIP archive exists on the local file system in a
    // temporary file. This throws an exception and deletes the archive on
    // an error.
    $archiveUri = self::createZipArchive($itemsToArchive);

    //
    // Create File entity.
    // -------------------
    // Create a new File entity from the local file. This also moves the
    // file into FolderShare's directory tree and gives it a numeric name.
    // The MIME type is also set, and the File is marked as permanent.
    // This throws an exception if the File could not be created.
    try {
      if (count($itemsToArchive) === 1) {
        // Add '.zip' to the item name.
        $archiveName = $itemsToArchive[0]->getName() . '.zip';
      }
      else {
        // Use a generic name.
        $archiveName = self::NEW_ZIP_ARCHIVE;
      }

      $archiveFile = self::createFileEntityFromLocalFile(
        $archiveUri,
        $archiveName);
    }
    catch (\Exception $e) {
      FileUtilities::unlink($archiveUri);
      throw $e;
    }

    //
    // Add the archive to this folder.
    // -------------------------------
    // Add the file, checking for and correcting name collisions. Lock
    // this folder during the add.
    try {
      $ary = self::addFilesInternal(
        $this,
        [$archiveFile],
        TRUE,
        TRUE,
        FALSE,
        TRUE);
    }
    catch (\Exception $e) {
      // On any failure, the archive wasn't added and we need to delete it.
      $archiveFile->delete();
      throw $e;
    }

    if (empty($ary) === TRUE) {
      return NULL;
    }

    return $ary[0];
  }

  /*---------------------------------------------------------------------
   *
   * Archive to file.
   *
   *---------------------------------------------------------------------*/

  /**
   * Creates and adds a list of children to a local ZIP archive.
   *
   * A new ZIP archive is created in the site's temporary directory
   * on the local file system. The given list of children are then
   * added to the archive and the file path of the archive is returned.
   *
   * If an error occurs, an exception is thrown and the archive file is
   * deleted.
   *
   * If a URI for the new archive is not provided, a temporary file is
   * created in the site's temporary directory, which is normally cleaned
   * out regularly. This limits the lifetime of the file, though
   * callers should delete the file when it is no longer needed, or move
   * it out of the temporary directory.
   *
   * @param \Drupal\foldershare\FoldershareInterface[] $items
   *   An array of FolderShare files and/or folders that are to be included
   *   in a new ZIP archive. They should all be children of the same parent
   *   folder.
   * @param string $archiveUri
   *   (optional, default = '' = create temp name) The URI for a local file
   *   to be overwritten with the new ZIP archive. If the URI is an empty
   *   string, a temporary file with a randomized name will be created in
   *   the site's temporary directory. The name will not have a filename
   *   extension.
   *
   * @return string
   *   Returns a URI to the new ZIP archive. The URI refers to a new file
   *   in the module's temporary files directory, which is cleaned out
   *   periodically. Callers should move the file to a new destination if
   *   they intend to keep the file.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock on any child could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if the archive file could not be created, such as if the
   *   temporary directory does not exist or is not writable, if a temporary
   *   file for the archive could not be created, or if any of the file
   *   children of this item could not be read and saved into the archive.
   *
   * @section locking Process locks
   * Each child file or folder and all of their subfolders and files are
   * locked for exclusive editing access by this function for the duration of
   * the archiving.
   *
   * @section usage Site usage
   * Usage statistics are not updated because no new FolderShare entities are
   * created.
   */
  public static function createZipArchive(
    array $items,
    string $archiveUri = '') {

    // Implementation note: How ZipArchive works
    //
    // Creating a ZipArchive selects a file name for the output.  Adding files
    // to the archive just adds those files to a list in memory of files
    // TO BE ADDED, but doesn't add them immediately or do any file I/O. The
    // actual file I/O occurs entirely when close() is called.
    //
    // This impacts when we lock files and folders. We cannot just lock
    // them before and after the archive addFile() call because that call
    // doesn't do the file I/O. Our locks would not guarantee that the file
    // continues to exist until the file I/O really happens on close().
    //
    // Instead, we need to lock everything FIRST, before adding anything to
    // the archive. Then we need to add the files and close the
    // archive to trigger the file I/O. And then when the file I/O is done
    // we can safely unlock everything LAST.
    //
    // Get a list of entity IDs including:
    // - Each of the given items.
    // - All descendants of each of the given items.
    $allIds = [];
    foreach ($items as $item) {
      $allIds[] = (int) $item->id();
      $allIds = array_merge($allIds, $item->findDescendantIds());
    }

    // LOCK ITEMS.
    if (self::acquireLockMultiple($allIds) === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t('One or more items are in use and cannot be compressed at this time.')));
    }

    if (empty($archiveUri) === TRUE) {
      // Create an empty temporary file. The file will have a randomized
      // name guaranteed not to collide with anything.
      $archiveUri = FileUtilities::tempnam(
        FileUtilities::getTempDirectoryUri(),
        'zip');

      if ($archiveUri === FALSE) {
        // This can fail if file system permissions are messed up, the
        // file system is full, or some other system error has occurred.
        //
        // UNLOCK ITEMS.
        self::releaseLockMultiple($allIds);
        throw new SystemException(t(
          "System error. A file at '@path' could not be created.\nThere may be a problem with directories or permissions. Please report this to the site administrator.",
          [
            '@path' => $archiveUri,
          ]));
      }
    }

    $archive = NULL;
    $failException = NULL;
    try {
      //
      // Create the ZipArchive object.
      // -----------------------------
      // Create the archiver and assign it the output file.
      $archive = new \ZipArchive();
      $archivePath = FileUtilities::realpath($archiveUri);
      if ($archive->open($archivePath, \ZipArchive::OVERWRITE) !== TRUE) {
        // All errors that could be returned are very unlikely. The
        // archive's path is known to be good since we just created a
        // temp file using it.  This means permissions are right, the
        // directory and file name are fine, and the file system is up
        // and working. Something catestrophic now has happened.
        $failException = new SystemException(t(
          "System error. A file at '@path' could not be created.\nThere may be a problem with directories or permissions. Please report this to the site administrator.",
          [
            '@path' => $archivePath,
          ]));
      }
      else {
        $archive->setArchiveComment(self::NEW_ARCHIVE_COMMENT);

        //
        // Recursively add to archive.
        // ---------------------------
        // For each of the given items, add them to the archive.  An exception
        // is thrown if any child, or its children, cannot be added. That
        // causes us to abort.
        foreach ($items as $item) {
          if ($item !== NULL) {
            $item->addToZipArchiveInternal($archive, '');
          }
        }

        // Close the file and trigger I/O to build the archive.
        if ($archive->close() === FALSE) {
          // Something went wrong with the file I/O. The ZIP
          // library delays all of its I/O until the close, so we actually
          // don't know which specific operation failed.
          $failException = new SystemException(t(
            "System error. A file at '@path' could not be written.\nThere may be a problem with permissions. Please report this to the site administrator.",
            [
              '@path' => $archivePath,
            ]));
        }
      }
    }
    catch (\Exception $e) {
      $failException = $e;
    }

    // UNLOCK ITEMS.
    self::releaseLockMultiple($allIds);

    if ($failException !== NULL) {
      // On failure, clean out the archive, delete the output file,
      // unlock everyting.
      if ($archive !== NULL) {
        $archive->unchangeAll();
        unset($archive);
      }

      // Delete the archive file as it exists so far.
      FileUtilities::unlink($archiveUri);

      throw $failException;
    }

    // Change the permissions to be suitable for web serving.
    FileUtilities::chmod($archiveUri);

    return $archiveUri;
  }

  /*---------------------------------------------------------------------
   *
   * Archive implementation.
   *
   *---------------------------------------------------------------------*/

  /**
   * Adds this item to the archive and recurses.
   *
   * This item is added to the archive, and then all its children are
   * added.
   *
   * If this item is a folder, all of the folder's children are added to
   * the archive. If the folder is empty, an empty directory is added to
   * the archive.
   *
   * If this item is a file, the file on the file system is copied into
   * the archive.
   *
   * To insure that the archive has the user-visible file and folder names,
   * an archive path is created during recursion. On the first call, a base
   * path is passed in as $baseZipPath. This item's name is then appended.
   * If this item is a file, the path with appended name is used as the
   * name for the file when in the archive. If this item is a folder, this
   * path with appended name is passed as the base path for adding the
   * folder's children, and so on.
   *
   * @param \ZipArchive $archive
   *   The archive to add to.
   * @param string $baseZipPath
   *   The folder path to be used within the ZIP archive to lead to the
   *   parent of this item.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock on this folder could not be acquired.
   *   This exception is never thrown if $lock is FALSE.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if a file could not be added to the archive.
   *
   * @section locking Process locks
   * This method does not lock access. The caller should lock around changes
   * to the entity.
   */
  private function addToZipArchiveInternal(
    \ZipArchive &$archive,
    string $baseZipPath) {
    //
    // Implementation note:
    //
    // The file path used within a ZIP file is recommended to always use
    // the '/' directory separator, regardless of the local OS conventions.
    // Since we are building a ZIP path, we therefore use '/'.
    //
    // Use the incoming folder path and append the user-visible item name
    // to create the name for the item as it should appear in the ZIP archive.
    if (empty($baseZipPath) === TRUE) {
      $currentZipPath = $this->getName();
    }
    else {
      $currentZipPath = $baseZipPath . '/' . $this->getName();
    }

    // Add the item to the archive.
    switch ($this->getKind()) {
      case self::FOLDER_KIND:
        // For folders, create an empty directory entry in the ZIP archive.
        // Then recurse to add all of the folder's children.
        if ($archive->addEmptyDir($currentZipPath) === FALSE) {
          throw new SystemException(t(
            "System error. Cannot add '@path' to archive '@archive'.\nThere may be a problem with the file system (such as out of storage space), with file permissions, or with the ZIP archive library. Please report this to the site administrator.",
            [
              '@path'    => 'empty directory',
              '@archive' => $currentZipPath,
            ]));
        }

        foreach ($this->findChildren() as $child) {
          $child->addToZipArchiveInternal($archive, $currentZipPath);
        }
        break;

      case self::FILE_KIND:
        // For files, get the path to the underlying stored file and add
        // the file to the archive.
        $file     = $this->getFile();
        $fileUri  = $file->getFileUri();
        $filePath = FileUtilities::realpath($fileUri);

        if ($archive->addFile($filePath, $currentZipPath) === FALSE) {
          throw new SystemException(t(
            "System error. Cannot add '@path' to archive '@archive'.\nThere may be a problem with the file system (such as out of storage space), with file permissions, or with the ZIP archive library. Please report this to the site administrator.",
            [
              '@path'    => $filePath,
              '@archive' => $currentZipPath,
            ]));
        }
        break;

      case self::IMAGE_KIND:
        // For images, get the path to the underlying stored file and add
        // the file to the archive.
        $file     = $this->getImage();
        $fileUri  = $file->getFileUri();
        $filePath = FileUtilities::realpath($fileUri);

        if ($archive->addFile($filePath, $currentZipPath) === FALSE) {
          throw new SystemException(t(
            "System error. Cannot add '@path' to archive '@archive'.\nThere may be a problem with the file system (such as out of storage space), with file permissions, or with the ZIP archive library. Please report this to the site administrator.",
            [
              '@path'    => $filePath,
              '@archive' => $currentZipPath,
            ]));
        }
        break;

      case self::MEDIA_KIND:
      default:
        // For any other kind, we don't know what it is or it does not have
        // a stored file, so we cannot add it to the archive. Silently
        // ignore it.
        break;
    }
  }

}
