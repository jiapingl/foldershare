<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\file\FileInterface;
use Drupal\file\Entity\File;

use Drupal\foldershare\Utilities;
use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\Constants;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Add File entity as Foldershare entity.
 *
 * This trait includes methods to wrap File entities as FolderShare
 * entities with kind 'file' or 'image'. The File object's ID is saved
 * to the 'file' or 'image' entity reference field.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationAddFileTrait {

  /*---------------------------------------------------------------------
   *
   * Add file(s) to root.
   *
   *---------------------------------------------------------------------*/

  /**
   * Adds a file to the root list.
   *
   * If $allowRename is FALSE, an exception is thrown if the file's
   * name is not unique within the root list. But if $allowRename is
   * TRUE and the name is not unique, the file's name is adjusted
   * to include a sequence number immediately before the first "."
   * in the name, or at the end of the name if there is no "."
   * (e.g. "myfile.png" becomes "myfile 1.png").
   *
   * An exception is thrown if the file is already in a folder.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to be added to the root list.
   * @param bool $allowRename
   *   (optional) When TRUE, the file's name should be automatically renamed to
   *   insure it is unique within the folder. When FALSE, non-unique
   *   file names cause an exception to be thrown.  Defaults to FALSE.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the new FolderShare entity that wraps the given File entity.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   If an access lock on the folder could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If the file could not be added because the file is already
   *   in a folder, or if the file doesn't pass validation because
   *   the name is invalid or already in use (if $allowRename FALSE).
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entity has been created, but
   *   before it has been saved.
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_insert" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section locking Process locks
   * The root list is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFiles()
   */
  public static function addFileToRoot(
    FileInterface $file,
    bool $allowRename = FALSE) {

    // Add the file, checking that the name is legal (possibly rename it).
    // Check if the file is already in the root list, and lock the root list
    // as needed.
    $ary = self::addFilesInternal(
      NULL,
      [$file],
      TRUE,
      $allowRename,
      TRUE,
      TRUE);

    if (empty($ary) === TRUE) {
      return NULL;
    }

    return $ary[0];
  }

  /**
   * Adds files to the root list.
   *
   * If $allowRename is FALSE, an exception is thrown if the file's
   * name is not unique within the root list. But if $allowRename is
   * TRUE and the name is not unique, the file's name is adjusted
   * to include a sequence number immediately before the first "."
   * in the name, or at the end of the name if there is no "."
   * (e.g. "myfile.png" becomes "myfile 1.png").
   *
   * An exception is thrown if any file is already in a folder.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   An array of files to be added to the root list.  NULL files
   *   are silently skipped.
   * @param bool $allowRename
   *   (optional) When TRUE, the file's name should be automatically renamed to
   *   insure it is unique within the folder. When FALSE, non-unique
   *   file names cause an exception to be thrown.  Defaults to FALSE.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the new FolderShare entity that wraps the given File entity.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   If an access lock on the folder could not be acquired.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If the file could not be added because the file is already
   *   in a folder, or if the file doesn't pass validation because
   *   the name is invalid or already in use (if $allowRename FALSE).
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entity has been created, but
   *   before it has been saved.
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_insert" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section locking Process locks
   * The root list is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFiles()
   */
  public static function addFilesToRoot(
    array $files,
    bool $allowRename = FALSE) {

    // Add the files, checking that their names are legal (possibly rename it).
    // Check if the files are already in the folder, and lock the folder
    // as needed.
    $ary = self::addFilesInternal(
      NULL,
      $files,
      TRUE,
      $allowRename,
      TRUE,
      TRUE);

    if (empty($ary) === TRUE) {
      return NULL;
    }

    return $ary[0];
  }

  /*---------------------------------------------------------------------
   *
   * Add file(s) to folder.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function addFile(
    FileInterface $file,
    bool $allowRename = FALSE) {

    // Add the file, checking that the name is legal (possibly rename it).
    // Check if the file is already in the folder, and lock the folder
    // as needed.
    $ary = self::addFilesInternal(
      $this,
      [$file],
      TRUE,
      $allowRename,
      TRUE,
      TRUE);

    if (empty($ary) === TRUE) {
      return NULL;
    }

    return $ary[0];
  }

  /**
   * {@inheritdoc}
   */
  public function addFiles(
    array $files,
    bool $allowRename = FALSE) {

    // Add the files, checking that their names are legal (possibly rename it).
    // Check if the files are already in the folder, and lock the folder
    // as needed.
    $ary = self::addFilesInternal(
      $this,
      $files,
      TRUE,
      $allowRename,
      TRUE,
      TRUE);

    if (empty($ary) === TRUE) {
      return NULL;
    }

    return $ary[0];
  }

  /*---------------------------------------------------------------------
   *
   * Add files implementation.
   *
   *---------------------------------------------------------------------*/

  /**
   * Implements addition of files to this folder.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * If $allowRename is FALSE, an exception is thrown if the file's
   * name is not unique within the folder. But if $allowRename is
   * TRUE and the name is not unique, the file's name is adjusted
   * to include a sequence number immediately before the first "."
   * in the name, or at the end of the name if there is no "."
   * (e.g. "myfile.png" becomes "myfile 1.png").
   *
   * All files are checked and renamed before any files are added
   * to the folder. If any file is invalid, an exception is thrown
   * and no files are added.
   *
   * An exception is thrown if any file is already in a folder, or if
   * this entity is not a folder.
   *
   * @param \Drupal\foldershare\FolderShareInterface $parent
   *   The parent entity, or NULL for the root list, to which to add
   *   the files.
   * @param \Drupal\foldershare\file\FileInterface[] $files
   *   An array of File enities to be added to this folder.  An empty
   *   array and NULL file objects are silently skipped.
   * @param bool $checkNames
   *   When TRUE, each file's name is checked to be sure that it is
   *   valid and not already in use. When FALSE, name checking is
   *   skipped (including renaming) and the caller must assure that
   *   names are good.
   * @param bool $allowRename
   *   When TRUE (and when $checkNames is TRUE), each file's name
   *   will be automatically renamed, if needed, to insure that it
   *   is unique within the folder. When FALSE (and when $checkNames
   *   is TRUE), non-unique file names cause an exception to be thrown.
   * @param bool $checkForInFolder
   *   When TRUE, an exception is thrown if the file is already in a
   *   folder. When FALSE, this expensive check is skipped.
   * @param bool $lock
   *   True to have this method lock around folder access, and
   *   FALSE to skip locking. When skipped, the caller MUST have locked
   *   the folder BEFORE calling this method.
   *
   * @return \Drupal\foldershare\FolderShareInterface[]
   *   Returns an array of the newly created FolderShare file entities
   *   added to this folder using the given $files File entities.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Thrown if an access lock on this folder could not be acquired.
   *   This exception is never thrown if $lock is FALSE.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Thrown if addition of the files does not validate (very unlikely).
   *   If $checkForInFolder, also thrown if any file is already in a
   *   folder.  If $checkNames, also thrown if any name is illegal.
   *   If $checkNames and $allowRename, also thrown if a unique name
   *   could not be found. If $checkNames and !$allowRename, also
   *   thrown if a name is already in use.
   *
   * @section locking Process locks
   * This folder is *optionally* locked by this method, based upon the
   * lock argument.  However, if locking is disabled by this method
   * the caller MUST have locked this folder for exclusive editing
   * access BEFORE calling this method.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFile()
   * @see ::addFiles()
   * @see ::addUploadFiles()
   */
  private static function addFilesInternal(
    FolderShareInterface $parent = NULL,
    array $files = [],
    bool $checkNames = TRUE,
    bool $allowRename = FALSE,
    bool $checkForInFolder = TRUE,
    bool $lock = TRUE) {

    //
    // Validate
    // --------
    // Make sure we're given files to add.
    // Then check that none of the files are already in a folder.
    if (empty($files) === TRUE) {
      // Nothing to add.
      return NULL;
    }

    if ($parent !== NULL && $parent->isFolder() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called with an item that is not a folder.',
          [
            '@method' => 'FolderShare::addFilesInternal',
          ])));
    }

    // If requested, insure that none of the files are already in a folder.
    $filesWithGoodParentage = [];
    if ($checkForInFolder === TRUE) {
      foreach ($files as $index => $file) {
        if ($file === NULL) {
          continue;
        }

        // Get the file's parent folder ID.  There are three cases:
        // - FALSE = file has no parent, which is what we want.
        // - $parent->id = file's parent is this folder (if any), so skip it.
        // - other = file already has parent, so error.
        $fileParentId = self::findFileWrapperId($file);

        if ($fileParentId !== FALSE) {
          // The file is apparently already wrapped by a FolderShare entity.
          if ($parent !== NULL && $fileParentId === (int) $parent->id()) {
            // Already in this folder. Skip it.
            continue;
          }

          // Complain.
          $v = new ValidationException(Utilities::createFormattedMessage(
            t(
              '@method was called to add a file to a folder, but it is already there.',
              [
                '@method' => 'FolderShare::addFilesInternal',
              ])));
          $v->setItemNumber($index);
          throw $v;
        }

        // The file is not already in a folder, so add it to the list
        // to process.
        $filesWithGoodParentage[] = $file;
      }
    }
    else {
      // Files are assumed to be not in a folder.
      foreach ($files as $file) {
        if ($file !== NULL) {
          $filesWithGoodParentage[] = $file;
        }
      }
    }

    if (empty($filesWithGoodParentage) === TRUE) {
      return NULL;
    }

    // Before the expense of a folder lock, check that all files
    // have legal names.
    if ($checkNames === TRUE) {
      foreach ($filesWithGoodParentage as $index => $file) {
        // Check that the new name is legal.
        $name = $file->getFilename();
        if (self::isNameLegal($name) === FALSE) {
          throw new ValidationException(Utilities::createFormattedMessage(
            t(
              'The name "@name" cannot be used.',
              [
                '@name' => $name,
              ]),
            t('Try using a name with fewer characters and avoid punctuation marks like ":", "/", and "\\".')));
        }

        // Verify that the name meets any file name extension restrictions.
        $extensionsString = self::getAllowedNameExtensions();
        if (empty($extensionsString) === FALSE) {
          $extensions = mb_split(' ', $extensionsString);
          if (self::isNameExtensionAllowed($name, $extensions) === FALSE) {
            throw new ValidationException(Utilities::createFormattedMessage(
              t(
                'The file type used by "@name" is not supported.',
                [
                  '@name' => $name,
                ]),
              t(
                'The file name uses a file type extension "@extension" that is not supported on this site.',
                [
                  '@extension' => self::getExtensionFromPath($name),
                ]),
              t('Supported file type extensions:'),
              implode(', ', $extensions)));
          }
        }
      }
    }

    // LOCK THIS FOLDER.
    if ($lock === TRUE) {
      if ($parent === NULL) {
        if (self::acquireRootLock() === FALSE) {
          throw new LockException(Utilities::createFormattedMessage(
            t('The system is busy updating and cannot add the file at this time.')));
        }
      }
      else {
        if ($parent->acquireLock() === FALSE) {
          throw new LockException(Utilities::createFormattedMessage(
            t(
              'The item "@name" is in use and the file cannot be added at this time.',
              [
                '@name' => $parent->getName(),
              ])));
        }
      }
    }

    // Check name uniqueness.
    $filesToAdd     = [];
    $originalNames  = [];
    $savedException = NULL;

    if ($checkNames === TRUE) {
      // Checking a list of child names is only safe to do and act
      // upon while the folder is locked and no other process can
      // make changes.
      if ($parent === NULL) {
        $uid = \Drupal::currentUser()->id();
        $childNames = self::findAllRootItemNames($uid);
      }
      else {
        $childNames = $parent->findChildrenNames();
      }

      foreach ($filesWithGoodParentage as $index => $file) {
        $name = $file->getFilename();

        if ($allowRename === TRUE) {
          // If not unique, rename.
          $uniqueName = self::createUniqueName($childNames, $name);

          if ($uniqueName === FALSE) {
            $savedException = new ValidationException(Utilities::createFormattedMessage(
              t(
                'The name "@name" cannot be used.',
                [
                  '@name' => $name,
                ]),
              t('Try using a name with fewer characters')));
            $savedException->setItemNumber($index);
            break;
          }

          // Change public file name. This name also appears in the URI.
          $originalNames[(int) $file->id()] = $name;
          $file->setFilename($uniqueName);
          $file->setFileUri(FileUtilities::getFileUri($file));
          $file->save();

          // Add to the child name list so that further checks
          // will catch collisions.
          $childNames[$uniqueName] = 1;
        }
        elseif (isset($childNames[$name]) === TRUE) {
          // Not unique and not allowed to rename. Fail.
          $savedException = new ValidationException(Utilities::createFormattedMessage(
            t(
              'The name "@name" is already taken.',
              [
                '@name' => $name,
              ]),
            ($parent === NULL) ?
              t('Names must be unique among your top-level folders. Please choose a different name.') :
              t('Names must be unique within a folder. Please choose a different name.')));
          $savedException->setItemNumber($index);
          break;
        }

        $filesToAdd[] = $file;
      }

      if ($savedException !== NULL) {
        // Something went wrong. Some files might already have
        // been renamed. Restore them to their prior names.
        if ($allowRename === TRUE) {
          foreach ($filesWithGoodParentage as $file) {
            if (isset($originalNames[(int) $file->id()]) === TRUE) {
              // Restore the prior name.
              $file->setFilename($originalNames[(int) $file->id()]);
              $file->setFileUri(FileUtilities::getFileUri($file));
              $file->save();
            }
          }
        }

        // UNLOCK THIS FOLDER.
        if ($lock === TRUE) {
          if ($parent === NULL) {
            self::releaseRootLock();
          }
          else {
            $parent->releaseLock();
          }
        }

        throw $savedException;
      }
    }
    else {
      // All of the files are assumed to have safe names.
      $filesToAdd = $filesWithGoodParentage;
    }

    if (empty($filesToAdd) === TRUE) {
      // UNLOCK THIS FOLDER.
      if ($lock === TRUE) {
        if ($parent === NULL) {
          self::releaseRootLock();
        }
        else {
          $parent->releaseLock();
        }
      }

      return NULL;
    }

    //
    // Add files
    // ---------
    // Create FolderShare entities for the files. For each one,
    // set the parent ID to be the parent folder, and the root ID to
    // be the parent folder's root.
    $uid = \Drupal::currentUser()->id();
    $parentid = ($parent === NULL) ? NULL : $parent->id();
    $rootid = ($parent === NULL) ? NULL : $parent->getRootItemId();
    $newEntities = [];

    foreach ($filesToAdd as $file) {
      // Get the MIME type for the file.
      $mimeType = $file->getMimeType();

      // If the file is an image, set the 'image' field to the file ID
      // and the kind to IMAGE_KIND. Otherwise set the 'file' field to
      // the file ID and the kind to FILE_KIND.
      if (self::isMimeTypeImage($mimeType) === TRUE) {
        $valueForFileField = NULL;
        $valueForImageField = $file->id();
        $valueForKindField = self::IMAGE_KIND;
      }
      else {
        $valueForFileField = $file->id();
        $valueForImageField = NULL;
        $valueForKindField = self::FILE_KIND;
      }

      // Create new FolderShare entity in the parent folder or the root list.
      // - Automatic id.
      // - Automatic uuid.
      // - Automatic creation date.
      // - Automatic changed date.
      // - Automatic langcode.
      // - Empty description.
      // - Empty author grants.
      // - Empty view grants.
      // - Empty disabled grants.
      $f = self::create([
        'name'     => $file->getFilename(),
        'uid'      => $uid,
        'kind'     => $valueForKindField,
        'mime'     => $mimeType,
        'file'     => $valueForFileField,
        'image'    => $valueForImageField,
        'size'     => $file->getSize(),
        'parentid' => $parentid,
        'rootid'   => $rootid,
      ]);

      // Add default grants to a root item.
      $f->addDefaultAccessGrants();

      $f->save();

      $newEntities[] = $f;
    }

    if ($parent !== NULL) {
      // Clear the parent folder's size, which has now increased.
      $parent->clearSize();
      $parent->save();
    }

    // UNLOCK THIS FOLDER.
    if ($lock === TRUE) {
      if ($parent === NULL) {
        self::releaseRootLock();
      }
      else {
        $parent->releaseLock();
      }
    }

    // Update folder size.
    if ($parent !== NULL) {
      self::updateSizes([(int) $parent->id()], FALSE);
    }

    $s = '';
    foreach ($newEntities as $e) {
      $s .= ' ' . $e->id() . '("' . $e->getName() . '")';
    }

    if ($parent === NULL) {
      self::postOperationHook(
        'add_files',
        [
          NULL,
          $newEntities,
        ]);
      self::log(
        'notice',
        'Added top-level files. <br>%entities',
        [
          '%entities' => $s,
        ]);
    }
    else {
      self::postOperationHook(
        'add_files',
        [
          $parent,
          $newEntities,
        ]);
      self::log(
        'notice',
        'Added files to entity @id ("%name"). <br>%entities',
        [
          '@id'       => $parent->id(),
          '%name'     => $parent->getName(),
          '%entities' => $s,
          'link'      => $parent->toLink(t('View'))->toString(),
        ]);
    }

    return $newEntities;
  }

  /*---------------------------------------------------------------------
   *
   * File utilities.
   *
   * These functions handle quirks of the File module.
   *
   *---------------------------------------------------------------------*/

  /**
   * Creates a File entity for an existing local file.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * A new File entity is created using the existing file, and named using
   * the given file name. In the process, the file is moved into the proper
   * FolderShare directory tree and the stored file renamed using the
   * module's numeric naming scheme.
   *
   * The filename, MIME type, and file size are set, the File entity is
   * marked as a permanent file, and the file's permissions are set for
   * access by the web server.
   *
   * @param string $uri
   *   The URI to a stored file on the local file system.
   * @param string $name
   *   The publically visible file name for the new File entity. This should
   *   be a legal file name, without a preceding path or scheme. The MIME
   *   type of the new File entity is based on this name.
   *
   * @return \Drupal\file\FileInterface
   *   Returns a new File entity with the given file name, and a properly
   *   set MIME type and size. The entity is owned by the current user.
   *   The File's URI points to a FolderShare directory tree file moved
   *   from the given local path. A NULL is returned if the local path is
   *   empty.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if an error occurs when trying to create any file or directory
   *   or move the local file to the proper directory.
   *
   * @section locking Process locks
   * This function does not lock access. The caller should lock around changes
   * to the entity.
   */
  private static function createFileEntityFromLocalFile(
    string $uri,
    string $name = NULL) {

    // If the path is empty, there is no file to create.
    if (empty($uri) === TRUE || empty($name) === TRUE) {
      return NULL;
    }

    //
    // Setup
    // -----
    // Get the new file's MIME type and size.
    $mimeType = \Drupal::service('file.mime_type.guesser')->guess($name);
    $fileSize = FileUtilities::filesize($uri);

    //
    // Create initial File entity
    // --------------------------
    // Create a File object that wraps the file in its current location.
    // This is not the final File object, which must be adjusted by moving
    // the file from its current location to FolderShare's directory tree.
    //
    // The status of 0 marks the file as temporary. If left this way, the
    // File module will automatically delete the file in the future.
    $file = File::create([
      'uid'      => \Drupal::currentUser()->id(),
      'uri'      => $uri,
      'filename' => $name,
      'filemime' => $mimeType,
      'filesize' => $fileSize,
      'status'   => 0,
    ]);

    $file->save();

    //
    // Move file and update File entity
    // --------------------------------
    // Creating the File object assigns it a unique ID. We need this ID
    // to create a new long-term local file name and location when the
    // file is in its proper location in the FolderShare directory tree.
    //
    // Create the new proper file URI with a numeric ID-based name and path.
    $storedUri = FileUtilities::getFileUri($file);

    // Move the stored file from its current location to the FolderShare
    // directory tree and rename it using the File entity's ID. This updates
    // the File entity.
    //
    // Moving the file also changes the file name and MIME type to values
    // based on the new URI. This is not what we want, so we'll have to
    // fix this below.
    $newFile = file_move($file, $storedUri, FILE_EXISTS_REPLACE);

    if ($newFile === FALSE) {
      // The file move has failed.
      $file->delete();
      \Drupal::logger(Constants::MODULE)->error(
        "File system error. A file at '@name' could not be moved to '@destination'.\nThere may be a problem with site directories or permissions.",
        [
          '@name'        => $file->getFilename(),
          '@destination' => 'temp directory',
        ]);
      throw new SystemException(t(
        "System error. A file at '@name' could not be moved to '@destination'.\nThere may be a problem with directories or permissions. Please report this to the site administrator.",
        [
          '@name'        => $name,
          '@destination' => $storedUri,
        ]));
    }

    // Mark the file permanent and fix the name and MIME type.
    $newFile->setPermanent();
    $newFile->setFilename($name);
    $newFile->setFileUri(FileUtilities::getFileUri($newFile));
    $newFile->setMimeType($mimeType);
    $newFile->save();

    return $newFile;
  }

  /**
   * Duplicates a File object.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B>
   *
   * The file is duplicated, creating a new copy on the local file system.
   *
   * Exceptions are very unlikely and should only occur when something
   * catastrophic happens to the underlying file system, such as if it
   * runs out of space, if someone deletes key directories, or if the
   * file system goes offline.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to copy.
   *
   * @return \Drupal\file\FileInterface
   *   The new file copy.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if a serious system error occurred, such as a
   *   file system becomes unreadable/unwritable, gets full, or goes offline.
   *
   * @section locking Process locks
   * This function does not lock access. The caller should lock around changes
   * to the entity.
   *
   * @see ::copyAndAddFilesInternal()
   */
  private static function duplicateFileEntityInternal(FileInterface $file) {
    //
    // Implementation note:
    //
    // The File module's file_copy() will copy a File object into a new
    // File object with a new URI for the file name.  However, our
    // file naming scheme for local storage file names uses the object's
    // entity ID, and we don't know that before calling file_copy().
    //
    // So, this function calls file_copy() to copy the given file into a
    // temp file, and then calls file_move() to move the temp file into
    // a file with the proper entity ID-based name.
    //
    // Complicating things, file_copy() and file_move() both invoke
    // hook functions that can rename the file, which is not appropriate
    // here. So, after moving the file, the file name is restored to
    // something reasonable.
    //
    // Furthermore, file names used by this module do not have file name
    // extensions (due to security and work-around quirks in the
    // File module's handling of extensions). To get the right MIME
    // type for the new File, this function explicitly copies it from
    // the previous File object.
    if ($file === NULL) {
      return NULL;
    }

    // Copy the file into a temp location.
    //
    // Allow the file to be renamed in order to avoid name collisions
    // with any other temp files in progress.
    $newFile = file_copy(
      $file,
      FileUtilities::createLocalTempFile(),
      FILE_EXISTS_RENAME);

    if ($newFile === FALSE) {
      // Unfortunately, file_copy returns FALSE on an error
      // and provides no further details to us on the problem.
      // Instead, it writes a message to the log file and/or
      // to the output page, which is not useful to us or
      // meaningful to the user.
      //
      // Looking at the source code, the following types of
      // errors could occur:
      // - The source file doesn't exist
      // - The destination directory doesn't exist
      // - The destination directory can't be written
      // - The destination filename is in use
      // - The source and destination are the same
      // - Some other error occurred (probably a system error)
      //
      // Since the directory and file names are under our control
      // and are valid, the only errors that can occur here are
      // catastrophic, such as:
      // - File deleted out from under us
      // - File system changed out from under Drupal
      // - File system full, offline, hard disk dead, etc.
      //
      // For any of these, it is unlikely we can continue with
      // copying anything. Time to abort.
      \Drupal::logger(Constants::MODULE)->error(
        "File system error. A file at '@name. could not be copied to .@destination..\nThere may be a problem with site directories or permissions.",
        [
          '@name'        => $file->getFilename(),
          '@destination' => 'temp directory',
        ]);
      throw new SystemException(t(
        "System error. A file at '@name' could not be copied to '@destination'.\nThere may be a problem with directories or permissions. Please report this to the site administrator.",
        [
          '@name'        => $file->getFilename(),
          '@destination' => 'temp directory',
        ]));
    }

    // The File copy's fields are mostly correct:
    // - 'filesize' matches the original file's size.
    // - 'uid' is for the current user.
    // - 'fid', 'uuid', 'created', and 'changed' are new and uptodate.
    // - 'langcode' is at a default.
    // - 'status' is permanent.
    //
    // The following fields need correcting:
    // - 'uri' and 'filename' are for a temp file.
    // - 'filemime' is empty since the file has no extension.
    //
    // Change the copied file's user-visible file name to match the
    // original file's name. This does not change the name on disk.
    // This must be set before we create the new URI because the filename's
    // extension is used in the new URI.
    //
    // Change the copied file's MIME type to match the original file's
    // MIME type.
    //
    // The URI is fixed now by moving the file.
    //
    // With an entity ID for the new file, create the correct URI
    // and move the file there.
    $newFile->setFilename($file->getFilename());
    $newUri = FileUtilities::getFileUri($newFile);
    $newFile = file_move(
      $newFile,
      $newUri,
      FILE_EXISTS_REPLACE);

    if ($newFile === FALSE) {
      // See the above comment about ways file_copy() can fail.
      // The same applies here.
      $newFile->delete();
      \Drupal::logger(Constants::MODULE)->error(
        "File system error. A file at '@name' could not be moved to '@destination'.\nThere may be a problem with site directories or permissions.",
        [
          '@name'        => $file->getFilename(),
          '@destination' => 'temp directory',
        ]);
      throw new SystemException(t(
        "System error. A file at '@name' could not be moved to '@destination'.\nThere may be a problem with directories or permissions. Please report this to the site administrator.",
        [
          '@name'        => $newFile->getFilename(),
          '@destination' => $newUri,
        ]));
    }

    $newFile->setMimeType($file->getMimeType());
    $newFile->save();

    return $newFile;
  }

  /*---------------------------------------------------------------------
   *
   * Upload files.
   *
   *---------------------------------------------------------------------*/

  /**
   * Adds uploaded files for the named form field into the root list.
   *
   * When a file is uploaded via an HTTP form post from a browser, PHP
   * automatically saves the data into "upload" files saved in a
   * PHP-managed temporary directory. This method sweeps those uploaded
   * files, pulls out the ones associated with the named form field,
   * and adds them to this folder with their original names.
   *
   * If there are no uploaded files, this method returns immediately.
   *
   * Files may be automatically renamed, if needed, to insure they have
   * unique names within the folder.
   *
   * @param string $formFieldName
   *   The name of the form field with associated uploaded files pending.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, if a file's name collides with the
   *   name of an existing entity, the name is modified by adding a number on
   *   the end so that it doesn't collide. When FALSE, a file name collision
   *   throws an exception.
   *
   * @return array
   *   The returned array contains one entry per uploaded file.
   *   An entry may be a File object uploaded into the current folder,
   *   or a string containing an error message about that file and
   *   indicating that it could not be uploaded and added to the folder.
   *   An empty array is returned if there were no files to upload
   *   and add to the folder.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entities have been created, but
   *   before they have been saved.
   * - "hook_foldershare_presave" before the entities are saved.
   * - "hook_foldershare_insert" after the entities have been saved.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @see ::addFile()
   * @see ::addFiles()
   *
   * @todo The error messages returned here should be removed in favor of
   * error codes and arguments so that the caller can know which file
   * had which error and do something about it or report their own
   * error message. Returning text messages here is not flexible.
   */
  public static function addUploadFilesToRoot(
    string $formFieldName,
    bool $allowRename = TRUE) {

    return self::addUploadFilesInternal(NULL, $formFieldName, $allowRename);
  }

  /**
   * {@inheritdoc}
   */
  public function addUploadFiles(
    string $formFieldName,
    bool $allowRename = TRUE) {

    return self::addUploadFilesInternal($this, $formFieldName, $allowRename);
  }

  /**
   * Adds uploaded files to the selected parent folder or root list.
   *
   * @param \Drupal\foldershare\FolderShareInterface $parent
   *   The parent folder into which to place the uploaded files. If NULL,
   *   uploaded files are added to the root list.
   * @param string $formFieldName
   *   The name of the form field with associated uploaded files pending.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, if a file's name collides with the
   *   name of an existing entity, the name is modified by adding a number on
   *   the end so that it doesn't collide. When FALSE, a file name collision
   *   throws an exception.
   *
   * @return array
   *   The returned array contains one entry per uploaded file.
   *   An entry may be a File object uploaded into the current folder,
   *   or a string containing an error message about that file and
   *   indicating that it could not be uploaded and added to the folder.
   *   An empty array is returned if there were no files to upload
   *   and add to the folder.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   */
  private static function addUploadFilesInternal(
    FolderShareInterface $parent = NULL,
    string $formFieldName = '',
    bool $allowRename = TRUE) {

    static $uploadCache;
    //
    // Drupal's file_save_upload() function is widely used for handling
    // uploaded files. It does several activities at once:
    //
    // - 1. Collect all uploaded files.
    // - 2. Validate that the uploads completed.
    // - 3. Run plug-in validators.
    // - 4. Optionally check for file name extensions.
    // - 5. Add ".txt" on executable files.
    // - 6. Rename inner extensions (e.g. ".tar.gz" -> "._tar.gz").
    // - 7. Validate file names are not too long.
    // - 8. Validate the destination exists.
    // - 9. Optionally rename files to avoid destination collisions.
    // - 10. chmod the file to be accessible.
    // - 11. Create a File object.
    // - 12. Set the File object's URI, filename, and MIME type.
    // - 13. Optionally replace a prior File object with a new file.
    // - 14. Save the File object.
    // - 15. Cache the File objects.
    //
    // There are several problems, though.
    //
    // - The plug-in validators in (3) are not needed by us.
    //
    // - The extension checking in (4) can only be turned off for
    //   the first file checked, due to a bug in the current code.
    //
    // - The extension changes in (5) and (6) are mandatory, but
    //   nonsense when we'll be storing files without extensions.
    //
    // - The file name length check in (7) uses PHP functions that
    //   are not multi-byte character safe, and it limits names to
    //   240 characters, independent of the actual field length.
    //
    // - The file name handling loses the original file name that
    //   we need to maintain and show to users.
    //
    // - The file movement can't leave the file in our desired
    //   destination directory because that directory's name is a
    //   function of the entity ID, which isn't known until after
    //   file_save_upload() has created the file and moved it to
    //   what it thinks is the final destination.
    //
    // - Any errors generated are logged and reported directly to
    //   to the user. No exceptions are thrown. The only error
    //   indicator returned to the caller is that the array of
    //   returned File objects can include a FALSE for a file that
    //   failed. But there is no indication about which file it was
    //   that failed, or why.
    //
    // THIS function repeats some of the steps in file_save_upload(),
    // but skips ones we don't need. It also keeps track of errors and
    // returns error messages instead of blurting them out to the user.
    //
    // Validate inputs
    // ---------------
    // Validate uploads exist and that they are for this field.
    if (empty($formFieldName) === TRUE) {
      // No field to get files for.
      return [];
    }

    // Get a list of all uploaded files, across all ongoing activity
    // for all uploads of any type.
    $allFiles = \Drupal::request()->files->get('files', []);

    // If there is nothing for the requested form field, return.
    if (isset($allFiles[$formFieldName]) === FALSE) {
      return [];
    }

    // If the file list for the requested form field is empty, return.
    $filesToProcess = $allFiles[$formFieldName];
    unset($allFiles);
    if (empty($filesToProcess) === TRUE) {
      return [];
    }

    // If there is just one item, turn it into an array of items
    // to simplify further code.
    if (is_array($filesToProcess) === FALSE) {
      $filesToProcess = [$filesToProcess];
    }

    //
    // Cache shortcut
    // --------------
    // It is conceivable that this function gets called multiple
    // times on the same form. To avoid redundant processing,
    // check a cache of recently uploaded files and return from
    // that cache quickly if possible.
    //
    // The cache will be cleared and set to a new list of files
    // at the end of this function.
    if (isset($uploadCache[$formFieldName]) === TRUE) {
      return $uploadCache[$formFieldName];
    }

    //
    // Validate upload success
    // -----------------------
    // Loop through the available uploaded files and separate out the
    // ones that failed, along with an error message about why it failed.
    $goodFiles = [];
    $failedMessages = [];

    foreach ($filesToProcess as $index => $fileInfo) {
      if ($fileInfo === NULL) {
        // Odd. A file is listed in the uploads, but it isn't really there.
        $failedMessages[$index] = (string) t(
          "System error. The @index-th uploaded file could not be found. Please try again.",
          [
            '@index' => $index,
          ]);
        continue;
      }

      $filename = $fileInfo->getClientOriginalName();

      // Check for errors. On any error, create an error message
      // and add it to the messages array. If the error is very
      // severe, also log it.
      switch ($fileInfo->getError()) {
        case UPLOAD_ERR_INI_SIZE:
          // Exceeds max PHP size.
        case UPLOAD_ERR_FORM_SIZE:
          // Exceeds max form size.
          $failedMessages[$index] = (string) t(
            "Maximum file size limit exceeded.\nThe file '@file' could not be added to the folder because it exceeds the web site's maximum allowed file size of @maxsize.",
            [
              '@file'    => $filename,
              '@maxsize' => Utilities::formatBytes(file_upload_max_size()),
            ]);
          break;

        case UPLOAD_ERR_PARTIAL:
          // Uploaded only partially uploaded.
          $failedMessages[$index] = (string) t(
            "Interrupted file upload.\nThe file '@file' could not be added to the folder because the upload was interrupted and only part of the file was received.",
            [
              '@file' => $filename,
            ]);
          break;

        case UPLOAD_ERR_NO_FILE:
          // Upload wasn't started.
          $failedMessages[$index] = (string) t(
            "Maximum upload number limit exceeded.\nThe file '@file' could not be added to the folder because its inclusion exceeds the web site's maximum allowed number of file uploads at one time.",
            [
              '@file' => $filename,
            ]);
          break;

        case UPLOAD_ERR_NO_TMP_DIR:
          // No temp directory configured.
          $failedMessages[$index] = (string) t(
            "Web site configuration problem.\nThe file '@file' could not be added to the folder because the web site encountered a site configuration error about a missing temporary directory. Please report this to the site administrator.",
            [
              '@file' => $filename,
            ]);
          \Drupal::logger(Constants::MODULE)->emergency(
            "File upload failed because the PHP temporary directory is missing!");
          break;

        case UPLOAD_ERR_CANT_WRITE:
          // Temp directory not writable.
          $failedMessages[$index] = (string) t(
            "Web site configuration problem.\nThe file '@file' could not be added to the folder because the web site encountered a site configuration error about a temporary directory without write permission. Please report this to the site administrator.",
            [
              '@file' => $filename,
            ]);
          \Drupal::logger(Constants::MODULE)->emergency(
            "File upload failed because the PHP temporary directory is not writable!");
          break;

        case UPLOAD_ERR_EXTENSION:
          // PHP extension failed for some reason.
          $failedMessages[$index] = (string) t(
            "Web site configuration problem.\nThe file '@file' could not be added to the folder because the web site encountered a site configuration error. Please report this to the site administrator.",
            [
              '@file' => $filename,
            ]);
          \Drupal::logger(Constants::MODULE)->error(
            "File upload failed because a PHP extension failed for an unknown reason.");
          break;

        case UPLOAD_ERR_OK:
          // Success!
          if (is_uploaded_file($fileInfo->getRealPath()) === FALSE) {
            // But the file doesn't actually exist!
            $failedMessages[$index] = (string) t(
              "Web site internal error.\nThe file '@file' could not be added to the folder because the data was lost during the upload.",
              [
                '@file' => $filename,
              ]);
            \Drupal::logger(Constants::MODULE)->error(
              "File upload failed because the uploaded file went missing after the upload completed.");
          }
          else {
            $goodFiles[$index] = $fileInfo;
          }
          break;

        default:
          // Unknown error.
          $failedMessages[$index] = (string) t(
            "Web site internal error.\nThe file '@file' could not be added to the folder because of an unknown problem.",
            [
              '@file' => $filename,
            ]);
          \Drupal::logger(Constants::MODULE)->warning(
            "File upload failed with an unrecognized error '@code'.",
            [
              '@code' => $fileInfo->getError(),
            ]);
          break;
      }
    }

    unset($filesToProcess);

    //
    // Validate names
    // --------------
    // Check that all of the original file names are legal for storage
    // in this module. This checks file name length and character content,
    // and allows for multi-byte characters.
    $passedFiles = [];

    foreach ($goodFiles as $index => $fileInfo) {
      $filename = $fileInfo->getClientOriginalName();

      if (self::isNameLegal($filename) === FALSE) {
        $failedMessages[$index] = (string) t(
          "The name '@name' cannot be used.\nThe file '@file' could not be added to the folder because it's name must be between 1 and 255 characters long and the name cannot use ':', '/', or '\\' characters.",
          [
            '@file' => $filename,
          ]);
      }
      else {
        $passedFiles[$index] = $fileInfo;
      }
    }

    // And reduce the good files list to the ones that passed.
    $goodFiles = $passedFiles;
    unset($passedFiles);

    // If there are no good files left, return the errors.
    if (empty($goodFiles) === TRUE) {
      $uploadCache[$formFieldName] = $failedMessages;
      return $failedMessages;
    }

    //
    // Validate extensions
    // -------------------
    // The folder's 'file' field contains the allowed filename extensions
    // for this site. If the list is empty, do not do extension checking.
    //
    // Note that we specifically DO NOT do some of the extension handling
    // found in file_save_upload():
    //
    // - We do not add ".txt" to the end of executable files
    //   (.php, .pl, .py, .cgi, .asp, and .js). This was intended
    //   to protect web servers from unintentionally executing
    //   uploaded files. However, for this module all uploaded files
    //   will stored without extensions, so this is not necessary.
    //
    // - We do not replace inner extensions (e.g. "archive.tar.gz")
    //   with a "._". Again, this was intended to protect web servers
    //   from falling back from the last extension to an inner
    //   extension and, again, unintentionally executing uploaded
    //   files. However, for this module all uploaded files will be
    //   stored without extensions, so this is not necessary.
    $extensionsString = self::getAllowedNameExtensions();
    if (empty($extensionsString) === FALSE) {
      // Break up the extensions.
      $extensions = mb_split(' ', $extensionsString);

      // Loop through the good files again and split off the
      // ones with good extensions.
      $passedFiles = [];

      foreach ($goodFiles as $index => $fileInfo) {
        $filename = $fileInfo->getClientOriginalName();

        if (self::isNameExtensionAllowed($filename, $extensions) === FALSE) {
          // Extension is not allowed.
          $failedMessages[$index] = (string) t(
            "Unsupported file type.\nThe file '@file' could not be added to the folder because it uses a file name extension that is not allowed by this web site.",
            [
              '@file' => $filename,
            ]);
        }
        else {
          $passedFiles[$index] = $fileInfo;
        }
      }

      // And reduce the good files list to the ones that passed.
      $goodFiles = $passedFiles;
      unset($passedFiles);

      // If there are no good files left, return the errors.
      if (empty($goodFiles) === TRUE) {
        $uploadCache[$formFieldName] = $failedMessages;
        return $failedMessages;
      }
    }

    //
    // Process files
    // -------------
    // At this point we have a list of uploaded files that all exist
    // on the server and have acceptable file name lengths and extensions.
    // We can now try to create File objects.
    //
    // Get the current user. They will be the owner of the new files.
    $user = \Drupal::currentUser();
    $uid = $user->id();

    // Get the file system service.
    $fileSystem = \Drupal::service('file_system');

    // Get the MIME type service.
    $mimeGuesser = \Drupal::service('file.mime_type.guesser');

    // Loop through the files and create initial File objects.  Move
    // each file into the Drupal temporary directory.
    $fileObjects = [];
    foreach ($goodFiles as $index => $fileInfo) {
      $filename   = $fileInfo->getClientOriginalName();
      $filemime   = $mimeGuesser->guess($filename);
      $filesize   = $fileInfo->getSize();
      $uploadPath = $fileInfo->getRealPath();
      $tempUri    = file_destination(
        'temporary://' . $filename,
        FILE_EXISTS_RENAME);

      // Move file to Drupal temp directory.
      //
      // The file needs to be moved out of PHP's temporary directory
      // into Drupal's temporary directory.
      //
      // PHP's move_uploaded_file() can do this, but it doesn't
      // handle Drupal streams. So use Drupal's file system for this.
      //
      // Let the URI get changed to avoid collisions. This does not
      // affect the user-visible file name.
      if ($fileSystem->moveUploadedFile($uploadPath, $tempUri) === FALSE) {
        // Failure likely means a site problem, such as a bad
        // file system, full disk, etc.  Try to keep going with
        // the rest of the files.
        $drupalTemp = file_directory_temp();
        $failedMessages[$index] = (string) t(
          "Web site configuration error.\nThe file '@file' could not be added to the folder because the web site encountered a site configuration error about a missing Drupal temporary directory. Please report this to the site administrator.",
          [
            '@file' => $filename,
          ]);
        \Drupal::logger(Constants::MODULE)->emergency(
          "File upload failed because Drupal temporary directory '@dir' is missing!",
          [
            '@dir' => $drupalTemp,
          ]);
        continue;
      }

      // Set permissions.  Make the file accessible to the web server, etc.
      FileUtilities::chmod($tempUri);

      // Create a File object. Make it owned by the current user. Give
      // it the temp URI, file name, MIME type, etc. A status of 0 means
      // the file is temporary still.
      $file = File::create([
        'uid'      => $uid,
        'uri'      => $tempUri,
        'filename' => $filename,
        'filemime' => $filemime,
        'filesize' => $filesize,
        'status'   => 0,
        'source'   => $formFieldName,
      ]);

      // Save!  Saving the File object assigns it a unique entity ID.
      $file->save();
      $fileObjects[$index] = $file;
    }

    unset($goodFiles);

    // If there are no good files left, return the errors.
    if (empty($fileObjects) === TRUE) {
      $uploadCache[$formFieldName] = $failedMessages;
      return $failedMessages;
    }

    //
    // Move into local directory
    // -------------------------
    // This module manages files within a directory tree built from
    // the entity ID.  This entity ID is not known until after the
    // File object is done.  So we now need another pass through the
    // File objects to use their entity IDs and move the files to their
    // final destinations.
    //
    // Along the way we also mark the file as permanent and attach it
    // to this folder.
    $movedObjects = [];

    foreach ($fileObjects as $index => $file) {
      // Create the final destination URI. This is the URI that uses
      // the entity ID.
      $finalUri = FileUtilities::getFileUri($file);

      // Move it there. The directory will be created automatically
      // if needed.
      $newFile = file_move($file, $finalUri, FILE_EXISTS_REPLACE);

      if ($newFile === FALSE) {
        // Unfortunately, file_move() just returns FALSE on an error
        // and provides no further details to us on the problem.
        // Instead, it writes a message to the log file and/or
        // to the output page, which is not useful to us or
        // meaningful to the user.
        //
        // Looking at the source code, the following types of
        // errors could occur:
        // - The source file doesn't exist
        // - The destination directory doesn't exist
        // - The destination directory can't be written
        // - The destination filename is in use
        // - The source and destination are the same
        // - Some other error occurred (probably a system error)
        //
        // Since the directory and file names are under our control
        // and are valid, the only errors that can occur here are
        // catastrophic, such as:
        // - File deleted out from under us
        // - File system changed out from under Drupal
        // - File system full, offline, hard disk dead, etc.
        //
        // For any of these, it is unlikely we can continue with
        // anything.
        $failedMessages[$index] = (string) t(
          'The file "@file" could not be added to the folder because the web site encountered a system failure.',
          ['@file' => $filename]);
        $file->delete();
      }
      else {
        // On success, $file has already been deleted and we now
        // need to use $newFile.
        $movedObjects[$index] = $newFile;

        // Mark it permanent.
        $newFile->setPermanent();
        $newFile->save();
      }
    }

    // And reduce the good files list to the ones that got moved.
    $fileObjects = $movedObjects;
    unset($movedObjects);

    // If there are no good files left, return the errors.
    if (empty($fileObjects) === TRUE) {
      $uploadCache[$formFieldName] = $failedMessages;
      return $failedMessages;
    }

    //
    // Add to folder
    // -------------
    // At this point, $fileObjects contains a list of fully-created
    // File objects for files that have already been moved into their
    // correct locations. Add them to the folder!
    try {
      // Add them. Watch for bad names or name collisions and rename
      // if needed. Don't bother checking if the files are already in
      // the folder since we know they aren't. Do lock.
      self::addFilesInternal(
        $parent,
        $fileObjects,
        TRUE,
        $allowRename,
        FALSE,
        TRUE);
    }
    catch (\Exception $e) {
      // The add can fail if:
      // - A file name is illegal (but we already checked).
      //
      // - A unique name could not be created because $allowRename was FALSE.
      //
      // - A folder lock could not be acquired.
      //
      // On any failure, none of the files have been added.
      foreach ($fileObjects as $index => $file) {
        $filename = $file->getFilename();
        $failedMessages[$index] = (string) t(
          'The file "@file" could not be added to the folder because the folder is locked for exclusive use by another user.',
          ['@file' => $filename]);
        $file->delete();
      }

      $uploadCache[$formFieldName] = $failedMessages;
      return $failedMessages;
    }

    //
    // Cache and return
    // ----------------
    // $fileObjects contains the new File objects, indexed by the original
    // upload indexes.
    //
    // $failedMessages contains text messages for all failures, indexed
    // by the original upload indexes.
    //
    // Merge these.  We cannot use PHP's array_merge() because it will
    // renumber the entries.
    $result = $fileObjects;
    foreach ($failedMessages as $index => $message) {
      $result[$index] = $message;
    }

    $uploadCache[$formFieldName] = $result;
    return $result;
  }

  /*---------------------------------------------------------------------
   *
   * Redirect input to a file.
   *
   *---------------------------------------------------------------------*/

  /**
   * Reads a PHP input stream into a new temporary file.
   *
   * PHP's input stream is opened and read to acquire incoming data
   * to route into a new Drupal temporary file. The URI of the new
   * file is returned.
   *
   * @return string
   *   The URI of the new temporary file.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Throws an exception if one of the following occurs:
   *   - The input stream cannot be read.
   *   - A temporary file cannot be created.
   *   - A temporary file cannot be written.
   */
  private static function inputDataToFile() {
    //
    // Open stream
    // -----------
    // Use the "php" stream to access incoming data appended to the
    // current HTTP request. The stream is opened for reading in
    // binary (the binary part is required for Windows).
    $stream = fopen('php://input', 'rb');
    if ($stream === FALSE) {
      throw new SystemException(t(
        "System error. An input stream cannot be opened or read."));
    }

    //
    // Create temp file
    // ----------------
    // Use the Drupal "temproary" stream to create a temporary file and
    // open it for writing in binary.
    $fileSystem = \Drupal::service('file_system');
    $tempUri = $fileSystem->tempnam('temporary://', 'file');
    $temp = fopen($tempUri, 'wb');
    if ($temp === FALSE) {
      fclose($stream);
      throw new SystemException(t(
        "System error. A temporary file at '@path' could not be created.\nThere may be a problem with directories or permissions. Please report this to the site administrator.",
        [
          '@path' => $tempUri,
        ]));
    }

    //
    // Copy stream to file
    // -------------------
    // Loop through the input stream until EOF, copying data into the
    // temporary file.
    while (feof($stream) === FALSE) {
      $data = fread($stream, 8192);

      if ($data === FALSE) {
        // We aren't at EOF, but the read failed. Something has gone wrong.
        fclose($stream);
        fclose($temp);
        throw new SystemException(t(
          "System error. An input stream cannot be opened or read."));
      }

      if (fwrite($temp, $data) === FALSE) {
        fclose($stream);
        fclose($temp);
        throw new SystemException(t(
          "System error. A file at '@path' could not be written.\nThere may be a problem with permissions. Please report this to the site administrator.",
          [
            '@path' => $temp,
          ]));
      }
    }

    //
    // Clean up
    // --------
    // Close the stream and file.
    fclose($stream);
    fclose($temp);

    return $tempUri;
  }

  /**
   * Adds a PHP input stream file into the root list.
   *
   * When a file is uploaded via an HTTP post handled by a web services
   * "REST" resource, the file's data is available via the PHP input
   * stream. This method reads that stream, creates a file, and adds
   * that file to this folder with the given name.
   *
   * @param string $filename
   *   The name for the new file.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, if $filename collides with the
   *   name of an existing entity, the name is modified by adding a number on
   *   the end so that it doesn't collide. When FALSE, a file name collision
   *   throws an exception.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the newly added FolderShare entity wrapping the file.
   *
   * @section hooks Hooks
   * Via the parent Entity class, this method calls several hooks:
   * - "hook_foldershare_field_values_init" hook to initialize fields.
   * - "hook_foldershare_create" after the entity has been created, but
   *   before it has been saved.
   * - "hook_foldershare_presave" before the entity is saved.
   * - "hook_foldershare_insert" after the entity has been saved.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_add_files" hook.
   *
   * @section locking Process locks
   * This folder is locked for exclusive editing access by this
   * function for the duration of the modification.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::addFile()
   * @see ::addFiles()
   * @see ::addUploadedFiles()
   */
  public static function addInputFileToRoot(
    string $filename,
    bool $allowRename = TRUE) {

    //
    // Validate
    // --------
    // There must be a file name.
    if (empty($filename) === TRUE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" cannot be used.',
          [
            '@name' => $filename,
          ]),
        t('The file could not be added created because it\'s name is too long or it uses one of the prohibited ":", "/", or "\\" punctuation marks.')));
    }

    //
    // Read data into file
    // -------------------
    // Read PHP input into a local temporary file. This throws an exception
    // if the input stream cannot be read or a temporary file created.
    $tempUri = self::inputDataToFile();

    //
    // Create a File entity
    // --------------------
    // Create a File entity that wraps the given file. This moves the file
    // into the module's directory tree. An exception is thrown the file
    // cannot be moved.
    $file = self::createFileEntityFromLocalFile($tempUri, $filename);

    //
    // Add file to root list
    // ---------------------
    // Add the File entity to the root list, adjusting the file name if
    // needed.
    return self::addFileToRoot($file, $allowRename);
  }

  /**
   * {@inheritdoc}
   */
  public function addInputFile(string $filename, bool $allowRename = TRUE) {
    //
    // Validate
    // --------
    // There must be a file name.
    if (empty($filename) === TRUE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" cannot be used.',
          [
            '@name' => $filename,
          ]),
        t('The file could not be added to the folder because it\'s name is too long or it uses one of the prohibited ":", "/", or "\\" punctuation marks.')));
    }

    //
    // Read data into file
    // -------------------
    // Read PHP input into a local temporary file. This throws an exception
    // if the input stream cannot be read or a temporary file created.
    $tempUri = self::inputDataToFile();

    //
    // Create a File entity
    // --------------------
    // Create a File entity that wraps the given file. This moves the file
    // into the module's directory tree. An exception is thrown the file
    // cannot be moved.
    $file = self::createFileEntityFromLocalFile($tempUri, $filename);

    //
    // Add file to folder
    // ------------------
    // Add the File entity to this folder, adjusting the file name if
    // needed.
    return $this->addFile($file, $allowRename);
  }

}
