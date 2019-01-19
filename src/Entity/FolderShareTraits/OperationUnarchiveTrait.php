<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\SystemException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Unarchive FolderShare entities into multiple FolderShare entities.
 *
 * This trait includes methods to unarchive a FolderShare entity for
 * a ZIP archive, saving the contents as new separate FolderShare entities.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationUnarchiveTrait {

  /*---------------------------------------------------------------------
   *
   * Unarchive.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function unarchiveFromZip() {
    //
    // Implementation note:
    //
    // A ZIP archive includes a list of files and folders. Each entry in the
    // list has a path, modification date, size, and assorted internal
    // attributes. Entries are listed in an order so that parent directories
    // are listed before files in those directories.
    //
    // Each entry's name is a relative path. Path components are separated
    // by '/' characters, regardless of the source or current OS. An entry
    // that ends in a '/' is for a directory.
    //
    // The task here is to extract everything from a ZIP archive and create
    // new FolderShare files and folders for that content. While the ZIP
    // archive supports a single extractTo() method that can dump the whole
    // archive into a subdirectory, this can cause file and directory names
    // to be changed based upon the limitations of the local OS. Names could
    // be shortened, special characters removed, and extensions shortened.
    // We don't want any of that. We want to retain the original names in
    // the entities we create.
    //
    // The extraction task is therefore one with multiple steps:
    //
    // 1. Extract the archive into a temporary directory. Assign each
    //    extracted file a generic numeric name (e.g. 1, 2, 3, 4) instead of
    //    using the original name, which may not work for this OS. Record
    //    this temporary name, the original ZIP name, and the other item
    //    attributes for later use.
    //
    // 2. Loop through all of the extracted files and folders and create
    //    corresponding FolderShare entities. Give those entities the
    //    original ZIP names and modification dates. For FolderShare files,
    //    also create a File object that wraps the stored file. Move that
    //    stored file from the temporary directory into FolderShare's
    //    normal file directory tree and rename it to use FolderShare's
    //    entity ID-based name scheme.
    //
    // 3. Delete the temporary directory. Since all of the files will have
    //    been moved out of it, all that will be left is empty directories.
    //
    // On errors, we need to clean up. The amount of cleanup depends upon
    // where the error occurs:
    //
    // 1. If the current FolderShare entity is not a file, or it is not
    //    recognized as a ZIP file, or it is corrupted, then abort. Delete
    //    anything extracted so far.
    //
    // 2. If there is a problem creating FolderShare entities, abort but
    //    keep whatever has been created so far. Delete the temp directory
    //    and whatever it contains.
    //
    // Validate
    // --------
    // This item must be a FolderShare file. We'll leave validating the
    // ZIP file until we try to unarchive it below.
    if ($this->isFile() === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called with an entity that is not a file.',
          [
            '@method' => 'FolderShare::unarchiveFromZip',
          ])));
    }

    //
    // Extract to local directory
    // --------------------------
    // The FolderShare file entity wraps a File object which in turn wraps
    // a locally stored file. Get that file path then open and extract
    // everything from the file. Lock the File while we do this.
    //
    // LOCK THIS FILE.
    // TODO Unarchive to the root list?
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be uncompressed at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    // Create a temporary directory for the archive's contents.
    $tempDirUri = FileUtilities::createLocalTempDirectory();

    // Get the local file path to the archive.
    $archivePath = FileUtilities::realpath($this->getFile()->getFileUri());

    // Extract into a temp directory.
    try {
      $entries = self::extractLocalZipFileToLocalDirectory(
        $archivePath,
        $tempDirUri);
    }
    catch (\Exception $e) {
      // A problem occurred while trying to unarchive the file. This
      // could be because the ZIP file is corrupted, or because there
      // is insufficient disk space to store the unarchived contents.
      // There could also be assorted system errors, like a file system
      // going off line or a permissions problem.
      //
      // UNLOCK THIS FILE.
      $this->releaseLock();
      FileUtilities::rrmdir($tempDirUri);
      throw $e;
    }

    // UNLOCK THIS FILE.
    $this->releaseLock();

    //
    // Decide content should go into a subfolder
    // -----------------------------------------
    // A ZIP file may contain any number of files and folders in an
    // arbitrary hierarchy. There are four cases of interest regarding
    // the top-level items:
    // - A single top-level file.
    // - A single top-level folder and arbitrary content.
    // - Multiple top-level files.
    // - Multiple top-level folders and arbitrary content.
    //
    // There are two common behaviors for these:
    //
    // - Unarchive single and multiple cases the same and put them all
    //   into the current folder.
    //
    // - Unarchive single items into the current folder, but unarchive
    //   multiple top-level items into a subfolder named after the archive.
    //   This prevents an archive uncompress from dumping a large number of
    //   files and folders all over a folder, which is confusing. This is
    //   the behavior of macOS.
    $topLevelFolder = $this->getParentFolder();
    if (self::UNARCHIVE_MULTIPLE_TO_SUBFOLDER === TRUE) {
      // When there are multiple top-level items, unarchive to a subfolder.
      //
      // Start by seeing how many top-level items we have.
      $nTop = 0;
      foreach ($entries as $entry) {
        if ($entry['isTop'] === TRUE) {
          ++$nTop;
        }
      }

      if ($nTop > 1) {
        // There are multiple top-level items. We need a subfolder.
        $subFolderName = $this->getName();
        $lastDotIndex = mb_strrpos($subFolderName, '.');
        if ($lastDotIndex !== FALSE) {
          $subFolderName = mb_substr($subFolderName, 0, $lastDotIndex);
        }

        $subFolder = $topLevelFolder->createFolder($subFolderName);

        // Hereafter, treat the subfolder as the parent folder for the
        // unarchiving.
        $topLevelFolder = $subFolder;
      }
    }

    //
    // Create files and folders
    // ------------------------
    // Loop through the list of files and folders in the archive and
    // create corresponding FolderShare entities for folders, and
    // FolderShare and File entities for files.
    //
    $mapPathToEntity = [];

    try {
      // Loop through all of the entries.
      foreach ($entries as $entry) {
        // Get a few values from the entry.
        $isDirectory = $entry['isDirectory'];
        $zipPath     = $entry['zipPath'];
        $localUri    = $entry['localUri'];
        $zipTime     = $entry['time'];

        // Split the original ZIP path into the parent folder path and
        // the new child's name. For a directory, remember to skip the
        // ending '/'. Note, again, that '/' is the ZIP directory separator,
        // regardless of the separator used by the current OS.
        if ($isDirectory === TRUE) {
          $slashIndex = mb_strrpos($zipPath, '/', -1);
        }
        else {
          $slashIndex = mb_strrpos($zipPath, '/');
        }

        if ($slashIndex === FALSE) {
          // There is no slash. This entry has no parent directory, so
          // use the top-level folder. This entry may be a directory or file.
          $parentEntity = $topLevelFolder;
          $zipName      = $zipPath;
        }
        else {
          // There is a slash. Get the last name and the parent path.
          $parentZipPath = mb_substr($zipPath, 0, $slashIndex);
          $zipName       = mb_substr($zipPath, ($slashIndex + 1));

          // Find the parent entity by looking up the path in the map
          // of previously created entities. Because ZIP entries for
          // folder files always follow entries for their parent folders,
          // we are guaranteed that the parent entity has already been
          // encountered.
          $parentEntity = $mapPathToEntity[$parentZipPath];
        }

        // Create folder or file.
        if ($entry['isDirectory'] === TRUE) {
          // The ZIP entry is for a directory.
          //
          // Create a new folder in the appropriate parent.
          //
          // This function call locks the parent and updates usage tracking.
          if ($parentEntity === NULL) {
            $childFolder = self::createRootFolder($zipName);
          }
          else {
            $childFolder = $parentEntity->createFolder($zipName);
          }

          if ($zipTime !== 0) {
            $childFolder->setCreatedTime($zipTime);
            $childFolder->setChangedTime($zipTime);
            $childFolder->save();
          }

          // Save that new folder entity back into the map.
          $mapPathToEntity[$zipPath] = $childFolder;
        }
        else {
          // The ZIP entry is for a file.
          //
          // Move the temporary file to FolderShare's directory tree and
          // wrap it with a new File entity.
          $childFile = self::createFileEntityFromLocalFile(
            $localUri,
            $zipName);

          if ($zipTime !== 0) {
            $childFile->setChangedTime($zipTime);
            $childFile->save();
          }

          try {
            // Add the file to the parent folder, locking the parent folder
            // during the addition and updating usage tracking.
            self::addFilesInternal(
              $parentEntity,
              [$childFile],
              TRUE,
              TRUE,
              FALSE,
              TRUE);
          }
          catch (\Exception $e) {
            // On any error, we cannot continue. Delete the orphaned File.
            $childFile->delete();
            throw $e;
          }
        }
      }
    }
    catch (\Exception $e) {
      // On any error, we cannot continue. Delete the temporary directory
      // containing the extracted archive.
      FileUtilities::rrmdir($tempDirUri);
      throw $e;
    }

    // We're done. Delete the temporary directory that used to contain
    // the extracted archive.
    FileUtilities::rrmdir($tempDirUri);
  }

  /*---------------------------------------------------------------------
   *
   * Implementation.
   *
   *---------------------------------------------------------------------*/

  /**
   * Extracts a local ZIP archive into a local directory.
   *
   * The indicated ZIP archive is un-zipped to extract all of its files
   * into a flat temporary directory. The files are all given simple numeric
   * names, instead of their names in the archive, in order to avoid name
   * changes that result from the current OS not supporting the same name
   * length and character sets used within the ZIP archive.
   *
   * An array is returned that indicates the name of the temporary directory
   * and a mapping from ZIP entries to the numerically-named temporary files
   * in the temporary directory.
   *
   * @param string $archivePath
   *   The local file system path to the ZIP archive to extract.
   * @param string $directoryUri
   *   The URI for a local temp directory into which to extract the ZIP
   *   archive's files and directories.
   *
   * @return array
   *   Returns an array containing one entry for each ZIP archive file or
   *   folder. Entries are associative arrays with the following keys:
   *   - 'isDirectory' - TRUE if the entry is a directory.
   *   - 'zipPath' - the file or directory path in the ZIP file.
   *   - 'localUri' - the file or directory URI in local storage.
   *   - 'time' - the last-modified time in the ZIP file.
   *
   * @throws \Drupal\foldershare\Entity\Exception\SystemException
   *   Thrown if a file or directory cannot be created, or if the ZIP
   *   archive is corrupted.
   */
  private static function extractLocalZipFileToLocalDirectory(
    string $archivePath,
    string $directoryUri) {

    //
    // Implementation note:
    //
    // A ZIP archive includes files and directories with relative paths
    // that meet the name constraints on the OS and file system on which
    // the archive was created. So if the original OS only supports ASCII
    // names and 3-letter extensions, that's what will be in the ZIP archive.
    //
    // The ZipArchive class can open an archive, then extract all of it in one
    // operation:
    // @code
    // $archive->extractTo($dir);
    // @endcode
    //
    // This works and it creates a new directory tree under $dir that contains
    // all of the files and subdirectories in the ZIP archive.
    //
    // HOWEVER... the local OS and file system may have different name length
    // and character set limits from that used to create the archive. In a
    // worst case, imagine extracting an archive with long UTF-8 file and
    // directory names into an old DOS file system that requires 8.3 names
    // in ASCII. Rather than fail, ZipArchive will rename the files during
    // extraction.
    //
    // The problem is that we need to know those new file names. We want to
    // create new FolderShare entities that point to them. But extractTo()
    // does not return them.
    //
    // A variant of extractTo() takes two arguments. The first is the
    // directory path for the new files, and the second is the name of the
    // file to extract:
    // @code
    // for ($i = 0; $i < $archive->numFiles; ++$i)
    //   $archive->extractTo($dir, $archive->getNameIndex($i));
    // @endcode
    //
    // HOWEVER... each file extracted by this method is dropped into $dir
    // by APPENDING the internal ZIP file path. So if the internal ZIP path
    // is "mydir/myfile.png" and $dir is "/tmp/stuff", then the file will
    // be dropped into "/tmp/stuff/mydir/myfile.png", but with "mydir" and
    // "myfile.png" adjusted for the local file system and OS limitations.
    //
    // The problem again is that we still don't know the names of the newly
    // created local files. Even though we can specify $dir, we cannot
    // specify the name of the file that is created.
    //
    // THEREFORE... we cannot use extractTo(). This is unfortunate and
    // causes a lot more code here.
    //
    // We can bypass extractTo() by getting a stream from ZipArchive,
    // then reading from that stream directly to copy the archive's
    // contents into a new file we explicitly create and write to.
    //
    if (empty($archivePath) === TRUE || empty($directoryUri) === TRUE) {
      return NULL;
    }

    // Implementation note:
    //
    // ZIP paths always use '/', regardless of the local OS or file system
    // conventions. So, as we parse ZIP paths, we use '/', and not the
    // current OS's DIRECTORY_SEPARATOR.
    //
    //
    // Open archive
    // ------------
    // Create the ZipArchive object and open the archive. The CHECKCONS
    // flag asks that the open perform consistency checks.
    $archive = new \ZipArchive();

    if ($archive->open($archivePath, \ZipArchive::CHECKCONS) !== TRUE) {
      throw new SystemException(Utilities::createFormattedMessage(
        t('The file does not appear to be a valid ZIP archive.'),
        t('The file may be corrupted or it may not be a ZIP archive.')));
    }

    $numFiles = $archive->numFiles;

    //
    // Check file name extensions
    // --------------------------
    // If the site is restricting file name extensions, check everything
    // in the archive first to insure that all files are supported. If any
    // are not, stop and report an error.
    $extensionsString = self::getAllowedNameExtensions();
    if (empty($extensionsString) === FALSE) {
      // Extensions are limited.
      $extensions = mb_split(' ', $extensionsString);
      for ($i = 0; $i < $numFiles; $i++) {
        $path = $archive->getNameIndex($i);
        if (self::isNameExtensionAllowed($path, $extensions) === FALSE) {
          $archive->close();
          throw new SystemException(t(
            "The file type used by '@name' in the archive is not allowed.\nThe archive cannot be uncompressed. Please see the site's documentation for a list of approved file types.",
            [
              '@name' => $path,
            ]));
        }
      }
    }

    //
    // Create temp directories
    // -----------------------
    // Sweep through the archive and make a list of directories. For each
    // one, create a corresponding temp directory. To avoid local file
    // system naming problems, use simple numbers (e.g. 0, 1, 2, 3).
    //
    // Implementation note:
    //
    // Each ZIP entry can have its own relative path. That path may include
    // parent directories that do not have their own ZIP entries. So we need
    // to parse out the parent directory path for EVERY entry and insure we
    // create a temp directory for all of them.
    //
    // For each entry, we'll use statIndex() to get these values:
    // - 'name' = the stored path for the file.
    // - 'index' = the index for the entry (equal to $i for this loop).
    // - 'crc' = the CRC (cyclic redundancy check) for the file.
    // - 'size' = the uncompressed file size.
    // - 'mtime' = the modification time stamp.
    // - 'comp_size' = the compressed file size.
    // - 'comp_method' = the compression method.
    // - 'encryption_method' = the encryption method.
    //
    // We do not support encryption, and we rely upon ZipArchive to handle
    // CRC checking and decompression. So the only values we need are:
    // - 'name'
    // - 'mtime'
    //
    // Note that the name returned is in the original file's character
    // encoding, which we don't know and it may not match that of the
    // current OS. We therefore need to attempt to detect the encoding of
    // each name and convert it to our generic UTF-8.
    //
    // Note that the creation time and recent access times are not stored
    // in the OS-independent part of the ZIP archive. They are apparently
    // stored in some OS-specific parts of the archive, but those require
    // PHP 7+ to access, and we cannot count on that.
    $entries = [];
    $counter = 0;

    for ($i = 0; $i < $numFiles; $i++) {
      // Get the next entry's info.
      $stat    = $archive->statIndex($i, \ZipArchive::FL_UNCHANGED);
      $zipPath = $stat['name'];
      $zipTime = isset($stat['mtime']) === FALSE ? 0 : $stat['mtime'];

      // Insure the ZIP file path is in UTF-8.
      $zipPathEncoding = mb_detect_encoding($zipPath, NULL, TRUE);
      if ($zipPathEncoding !== 'UTF-8') {
        $zipPath = mb_convert_encoding($zipPath, 'UTF-8', $zipPathEncoding);
      }

      // Split on the ZiP directory separator, which is always '/'.
      $zipDirs = mb_split('/', $zipPath);

      // For a directory entry, the last character in the name is '/' and
      // the last name in $dirs is empty.
      //
      // For a file entry, the last character in the name is not '/' and
      // the last name in $dirs is the file name.
      //
      // In both cases, we don't need the last entry since we are only
      // interested in all of the parent directories.
      unset($zipDirs[(count($zipDirs) - 1)]);

      // Loop through the directories on the ZIP file's path and create
      // any we haven't encountered before.
      $zipPathSoFar = '';
      $dirUriSoFar = $directoryUri;

      foreach ($zipDirs as $dir) {
        // Append the next dir to our ZIP path so far.
        if ($zipPathSoFar === '') {
          $zipPathSoFar = $dir;
          $isTop = TRUE;
        }
        else {
          $zipPathSoFar .= '/' . $dir;
          $isTop = FALSE;
        }

        if (isset($entries[$zipPathSoFar]) === TRUE) {
          // We've encountered this path before. Update it's saved
          // modification time if it is newer.
          $dirUriSoFar = $entries[$zipPathSoFar]['localUri'];

          if ($zipTime > $entries[$zipPathSoFar]['time']) {
            $entries[$zipPathSoFar]['time'] = $zipTime;
          }
        }
        else {
          // Create the local URI.
          $localUri = $dirUriSoFar . '/' . $counter;
          ++$counter;

          $entries[$zipPathSoFar] = [
            'isDirectory' => TRUE,
            'isTop'       => $isTop,
            'zipPath'     => $zipPathSoFar,
            'localUri'    => $localUri,
            'time'        => $zipTime,
          ];

          FileUtilities::mkdir($localUri);
        }
      }
    }

    //
    // Extract files
    // -------------
    // Sweep through the archive again. Ignore directory entries since
    // we have already handled them above.
    //
    // For each file, DO NOT use extractTo(), since that will create
    // local names we cannot control (see implementation notes earlier).
    // Instead, open a stream for each file and copy from the stream
    // into a file we create here with a name we can control and save.
    for ($i = 0; $i < $numFiles; $i++) {
      // Get the next entry's info.
      $stat    = $archive->statIndex($i, \ZipArchive::FL_UNCHANGED);
      $zipPath = $stat['name'];
      $zipTime = isset($stat['mtime']) === FALSE ? 0 : $stat['mtime'];

      // Insure the ZIP file path is in UTF-8.
      $zipPathEncoding = mb_detect_encoding($zipPath, NULL, TRUE);
      if ($zipPathEncoding !== 'UTF-8') {
        $zipPath = mb_convert_encoding($zipPath, 'UTF-8', $zipPathEncoding);
      }

      if ($zipPath[(mb_strlen($zipPath) - 1)] === '/') {
        // Paths that end in '/' are directories. Already handled.
        continue;
      }

      // Get the parent ZIP directory path.
      $parentZipPath = FileUtilities::dirname($zipPath);

      // Get the local temp directory URI for this path, which we have
      // created earlier.
      if ($parentZipPath === '.') {
        // The $zipPath had no parent directories. Drop the file into
        // the target directory.
        $parentLocalUri = $directoryUri;
        $isTop = TRUE;
      }
      else {
        // Get the name of the temp directory we created earlier for
        // this parent path.
        $parentLocalUri = $entries[$parentZipPath]['localUri'];
        $isTop = FALSE;
      }

      // Create a name for a local file in the parent directory. We'll
      // be writing the ZIP archive's uncompressed file here.
      $localUri = $parentLocalUri . '/' . $counter;
      ++$counter;

      // Get an uncompressed byte stream for the file.
      $stream = $archive->getStream($zipPath);

      // Create a local file. We can use the local URI because fopen()
      // is stream-aware and will track the scheme down to Drupal and
      // its installed stream wrappers.
      $fp = fopen($localUri, 'w');
      if ($fp === FALSE) {
        $archive->close();
        throw new SystemException(t(
          "System error. A file at '@path' could not be written.\nThere may be a problem with permissions. Please report this to the site administrator.",
          [
            '@path' => $archivePath,
          ]));
      }

      while (feof($stream) !== TRUE) {
        fwrite($fp, fread($stream, 8192));
      }

      fclose($fp);
      fclose($stream);

      // Give the new file appropriate permissions.
      FileUtilities::chmod($localUri);

      // Set the new file's modification time.
      if ($zipTime !== 0) {
        FileUtilities::touch($localUri, $zipTime);
      }

      $entries[$zipPath] = [
        'isDirectory' => FALSE,
        'isTop'       => $isTop,
        'zipPath'     => $zipPath,
        'localUri'    => $localUri,
        'time'        => $zipTime,
      ];
    }

    if ($archive->close() === FALSE) {
      throw new SystemException(t(
        "System error. A file at '@path' could not be written.\nThere may be a problem with permissions. Please report this to the site administrator.",
        [
          '@path' => $archivePath,
        ]));
    }

    return $entries;
  }

}
