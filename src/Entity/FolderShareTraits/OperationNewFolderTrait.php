<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\Exception\LockException;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Create new Foldershare folders.
 *
 * This trait includes methods to create root and subfolders.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait OperationNewFolderTrait {

  /*---------------------------------------------------------------------
   *
   * Create root folder.
   *
   *---------------------------------------------------------------------*/

  /**
   * Creates a new root folder with the given name.
   *
   * If the name is empty, it is set to a default.
   *
   * The name is checked for uniqueness among all root items owned by
   * the current user. If needed, a sequence number is appended before
   * the extension(s) to make the name unique (e.g. 'My new root 12').
   *
   * @param string $name
   *   (optional, default = '') The name for the new folder. If the name is
   *   empty, it is set to a default name.
   * @param bool $allowRename
   *   (optional, default = TRUE) When TRUE, the entity will be automatically
   *   renamed, if needed, to insure that it is unique within the folder.
   *   When FALSE, non-unique names cause an exception to be thrown.
   *
   * @return \Drupal\foldershare\Entity\FolderShare
   *   Returns the new folder at the root.
   *
   * @throws \Drupal\foldershare\Entity\Exception\LockException
   *   Throws an exception if an access lock on the root list could
   *   not be acquired.
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   If the name is already in use or is not legal.
   *
   * @section locking Process locks
   * The root list is locked for exclusive editing access by this
   * function for the duration of the operation.
   *
   * @section hooks Post-operation hooks
   * This method calls the "hook_foldershare_post_operation_new_folder" hook.
   *
   * @section logging Operation log
   * If the site hs enabled logging of operations, this method posts a
   * log message.
   *
   * @see ::createFolder()
   */
  public static function createRootFolder(
    string $name = '',
    bool $allowRename = TRUE) {

    //
    // Validate
    // --------
    // If no name given, use a default. Otherwise insure the name is legal.
    if (empty($name) === TRUE) {
      $name = t('New folder');
    }
    elseif (self::isNameLegal($name) === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" cannot be used.',
          [
            '@name' => $name,
          ]),
        t('Try using a name with fewer characters and avoid punctuation marks like ":", "/", and "\\".')));
    }

    //
    // Lock
    // ----
    // LOCK ROOT LIST.
    if (self::acquireRootLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t('The system is busy updating and cannot be updated at this time.')));
    }

    //
    // Execute
    // -------
    // Create a name, if needed, then create the folder.
    $uid = \Drupal::currentUser()->id();
    $savedException = NULL;
    $folder = NULL;

    // Create the new folder.
    try {
      if ($allowRename === TRUE) {
        // Insure name doesn't collide with existing root items.
        //
        // Checking for name uniqueness can only be done safely while
        // the root list is locked so that no other process can add or
        // change a name.
        $name = self::createUniqueName(
          self::findAllRootItemNames($uid),
          $name,
          '');
      }
      elseif (self::isRootNameUnique($name) === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'The name "@name" is already in use.',
            [
              '@name' => $name,
            ]),
          t('Please select a different name.')));
      }

      // Give the new root item no parent or root.
      // - Empty parent ID.
      // - Empty root ID.
      // - Automatic id.
      // - Automatic uuid.
      // - Automatic creation date.
      // - Automatic changed date.
      // - Automatic langcode.
      // - Empty description.
      // - Empty size.
      // - Empty author grants.
      // - Empty view grants.
      // - Empty disabled grants.
      $folder = self::create([
        'name' => $name,
        'uid'  => $uid,
        'kind' => self::FOLDER_KIND,
        'mime' => self::FOLDER_MIME,
        'size' => 0,
      ]);

      // Add default grants to a root item.
      $folder->addDefaultAccessGrants();

      $folder->save();
    }
    catch (\Exception $e) {
      $savedException = $e;
    }

    //
    // Unlock
    // ------
    // UNLOCK ROOT LIST.
    self::releaseRootLock();

    if ($savedException !== NULL) {
      throw $savedException;
    }

    self::postOperationHook('new_folder', $folder);
    self::log(
      'notice',
      'Created new top-level folder entity @id with name "%name".',
      [
        '@id'   => $folder->id(),
        '%name' => $folder->getName(),
        'link'  => $folder->toLink(t('View'))->toString(),
      ]);

    return $folder;
  }

  /*---------------------------------------------------------------------
   *
   * Create subfolder.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function createFolder(
    string $name = '',
    bool $allowRename = TRUE) {

    //
    // Validate
    // --------
    // If no name given, use a default. Otherwise insure the name is legal.
    if (empty($name) === TRUE) {
      $name = t('New folder');
    }
    elseif (self::isNameLegal($name) === FALSE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          'The name "@name" cannot be used.',
          [
            '@name' => $name,
          ]),
        t('Try using a name with fewer characters and avoid punctuation marks like ":", "/", and "\\".')));
    }

    //
    // Lock
    // ----
    // LOCK PARENT FOLDER.
    if ($this->acquireLock() === FALSE) {
      throw new LockException(Utilities::createFormattedMessage(
        t(
          'The item "@name" is in use and cannot be updated at this time.',
          [
            '@name' => $this->getName(),
          ])));
    }

    //
    // Execute
    // -------
    // Create a name, if needed, then create the folder.
    $uid = \Drupal::currentUser()->id();
    $savedException = NULL;
    $folder = NULL;

    // Create the new folder.
    try {
      if ($allowRename === TRUE) {
        // Insure name doesn't collide with existing files or folders.
        //
        // Checking for name uniqueness can only be done safely while
        // the parent folder is locked so that no other process can add or
        // change a name.
        $name = self::createUniqueName($this->findChildrenNames(), $name, '');
      }
      elseif ($this->isNameUnique($name) === FALSE) {
        throw new ValidationException(Utilities::createFormattedMessage(
          t(
            'The name "@name" is already in use.',
            [
              '@name' => $name,
            ]),
          t('Please select a different name.')));
      }

      // Create and set the parent ID to this folder,
      // and the root ID to this folder's root.
      // - Automatic id.
      // - Automatic uuid.
      // - Automatic creation date.
      // - Automatic changed date.
      // - Automatic langcode.
      // - Empty description.
      // - Empty size.
      // - Empty author grants.
      // - Empty view grants.
      // - Empty disabled grants.
      $folder = self::create([
        'name'     => $name,
        'uid'      => $uid,
        'kind'     => self::FOLDER_KIND,
        'mime'     => self::FOLDER_MIME,
        'size'     => 0,
        'parentid' => $this->id(),
        'rootid'   => $this->getRootItemId(),
      ]);

      $folder->save();
    }
    catch (\Exception $e) {
      $savedException = $e;
    }

    //
    // Unlock
    // ------
    // UNLOCK PARENT FOLDER.
    $this->releaseLock();

    if ($savedException !== NULL) {
      throw $savedException;
    }

    self::postOperationHook('new_folder', $folder);
    self::log(
      'notice',
      'Created new folder entity @id ("%name"). <br>%path',
      [
        '@id'   => $folder->id(),
        '%name' => $folder->getName(),
        '%path' => $folder->getPath(),
        'link'  => $folder->toLink(t('View'))->toString(),
      ]);

    return $folder;
  }

}
