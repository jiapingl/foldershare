<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;
use Drupal\foldershare\Entity\Exception\ValidationException;
use Drupal\foldershare\Entity\Exception\NotFoundException;
use Drupal\foldershare\FolderShareInterface;

/**
 * Creates a form to upload and add a file to a folder.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * This form is invoked by web service clients in order to upload a file
 * into a folder. It exists solely because the Drupal 8.5 (and earlier)
 * REST module does not support base class features needed for file uploads.
 *
 * The route to this form requires authentication, so there is a current
 * user.
 *
 * @ingroup foldershare
 */
class FileUpload extends FormBase {

  /*--------------------------------------------------------------------
   *
   * Form setup.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return str_replace('\\', '_', get_class($this));
  }

  /*--------------------------------------------------------------------
   *
   * Form build.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $formState = NULL) {
    //
    // Define form
    // -----------
    // The form has two fields:
    // - A file field that triggers the file upload.
    // - A destination field that names where the uploaded file should go.
    $form['file'] = [
      '#type'  => 'file',
      '#title' => 'The local file to upload.',
      '#required' => FALSE,
    ];

    $form['path'] = [
      '#type'  => 'textfield',
      '#title' => 'The path destination for the file.',
      '#required' => FALSE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Upload'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /*--------------------------------------------------------------------
   *
   * Form submit.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState) {
    // Nothing to do.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    //
    // Get destination
    // ---------------
    // The form may contain a destination path. The path may be:
    //
    // - The "/" path to name the user's root list as the destination.
    //
    // - The path to an existing folder to contain the uploaded file.
    //
    // - The path to a non-existant file, but with an existing parent. The
    //   name of the non-existant file is the intended name of the
    //   uploaded file into that parent.
    //
    // If there is no destination path, uploads are to the root list.
    $destinationPath   = $formState->getValue('path');
    $destinationEntity = NULL;
    $destinationName   = '';

    if (empty($destinationPath) === TRUE) {
      // The path is empty. Upload to the root list.
      $destinationId = FolderShareInterface::USER_ROOT_LIST;
    }
    else {
      try {
        // Parse the path. This will fail if:
        // - The path is malformed.
        $parts = FolderShare::parsePath($destinationPath);
      }
      catch (ValidationException $e) {
        throw new BadRequestHttpException($e->getMessage());
      }

      if ($parts['path'] === '/') {
        // The path is just '/'. Upload to the root list.
        $destinationId = FolderShareInterface::USER_ROOT_LIST;
      }
      else {
        // Try to get the entity. This will throw an exception if:
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
            throw new BadRequestHttpException($this->t(
              "The name is empty.\nPlease use a name with at least one character."));
          }

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
        catch (ValidationException $e) {
          // Otherwise the path is bad. We've already checked for this earlier,
          // so this shouldn't happen again.
          throw new BadRequestHttpException($e->getMessage());
        }
      }
    }

    //
    // Confirm destination legality
    // ----------------------------
    // Above, we found one of:
    // - A destination entity with $destinationId set and $destinationName
    //   left empty.
    //
    // - A parent destination entity with $destinationId and
    //   $destinationName set as the name for the new file.
    //
    // Load the entity, confirm it is a folder, and verify access.
    if ($destinationId !== FolderShareInterface::USER_ROOT_LIST) {
      $destinationEntity = FolderShare::load($destinationId);
      if ($destinationEntity === NULL) {
        // This should not be possible since we already validated the ID.
        throw new NotFoundHttpException($this->t(
          "'@path' could not be found.\nPlease check that the path is correct.",
          [
            '@path' => $destinationPath,
          ]));
      }

      if ($destinationEntity->isFolder() === FALSE) {
        if (empty($destinationName) === TRUE) {
          // The destination path explicitly named this entity, but it is
          // a file. To upload to it we would have to overwrite it, which
          // is not allowed.
          throw new BadRequestHttpException($this->t(
            "The name '@name' is already taken.\nPlease choose a different name.",
            [
              '@name' => $destinationEntity->getName(),
            ]));
        }

        // Otherwise the destination path named a non-existant entity and
        // we parsed it back to a parent that did exist, yet that parent
        // is not a folder.
        throw new BadRequestHttpException($this->t(
          "'@path' could not be found.\nPlease check that the path is correct.",
          [
            '@path' => $destinationPath,
          ]));
      }

      // Verify the user has access. This will fail if:
      // - The user does not have update access to the destination entity.
      $access = $destinationEntity->access('create', NULL, TRUE);
      if ($access->isAllowed() === FALSE) {
        $message = $access->getReason();
        if (empty($message) === TRUE) {
          $message = $this->t(
            "You are not authorized to upload files into '@path'.",
            [
              '@path' => $destinationPath,
            ]);
        }

        throw new AccessDeniedHttpException($message);
      }
    }
    else {
      // Verify the user has access. This will fail if:
      // - The user does not have update access to the root list.
      $summary = FolderShareAccessControlHandler::getRootAccessSummary(
        FoldershareInterface::USER_ROOT_LIST,
        NULL);
      if (isset($summary['create']) === FALSE ||
          $summary['create'] === FALSE) {
        // Access denied.
        throw new AccessDeniedHttpException($this->t(
          "You are not authorized to upload files into '/'."));
      }
    }

    //
    // Add file.
    // ---------
    // Allow automatic file renaming.
    //
    // This will fail if:
    // - There were any of several problems during the file upload.
    // - The file name is illegal.
    // - The folder is locked.
    //
    // While the method returns text error messages, it does not indicate
    // what type of error occurred. This makes it impossible for us to send
    // different HTTP codes on different errors.
    if ($destinationEntity === NULL) {
      $results = FolderShare::addUploadFilesToRoot('file', TRUE);
    }
    else {
      $results = $destinationEntity->addUploadFiles('file', TRUE);
    }

    $entry = array_shift($results);
    if (is_string($entry) === TRUE) {
      throw new BadRequestHttpException($entry);
    }
  }

}
