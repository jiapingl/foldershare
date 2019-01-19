<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\File;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines a command plugin to upload files for a folder.
 *
 * The command uploads files and adds them to the parent folder.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_upload_files",
 *  label           = @Translation("Upload"),
 *  menuNameDefault = @Translation("Upload..."),
 *  menuName        = @Translation("Upload..."),
 *  description     = @Translation("Upload files"),
 *  category        = "import & export",
 *  weight          = 10,
 *  specialHandling = {
 *    "upload",
 *  },
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *      "folder",
 *    },
 *    "access"  = "create",
 *  },
 *  selectionConstraints = {
 *    "types"   = {
 *      "none",
 *    },
 *  },
 * )
 */
class UploadFiles extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasConfigurationForm() {
    // A configuration form is only needed if there are no uploaded
    // files already available.  Check.
    $configuration = $this->getConfiguration();
    if (empty($configuration) === TRUE) {
      // No command configuration? Need a form.
      return TRUE;
    }

    if (empty($configuration['uploadClass']) === TRUE) {
      // No command upload class specified? Need a form.
      return TRUE;
    }

    $uploadClass = $configuration['uploadClass'];
    $pendingFiles = \Drupal::request()->files->get('files', []);
    if (isset($pendingFiles[$uploadClass]) === FALSE) {
      // No pending files? Need a form.
      return TRUE;
    }

    foreach ($pendingFiles[$uploadClass] as $fileInfo) {
      if ($fileInfo !== NULL) {
        // At least one good pending file. No form.
        return FALSE;
      }
    }

    // No good pending files. Need a form.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {

    // Build a form to prompt for files to upload.
    $uploadClass = Constants::MODULE . '-command-upload';

    $configuration = $this->getConfiguration();
    $configuration['uploadClass'] = $uploadClass;
    $this->setConfiguration($configuration);

    // Provide a file field. Browsers automatically add a button to
    // invoke a platform-specific file dialog to select files.
    $form[$uploadClass] = [
      '#type'        => 'file',
      '#multiple'    => TRUE,
      '#description' => t(
        "Select one or more @kinds to upload.",
        [
          '@kinds'  => Utilities::translateKinds(FolderShare::FILE_KIND),
        ]),
      '#process'   => [
        [
          get_class($this),
          'processFileField',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Process the file field in the view UI form to add extension handling.
   *
   * The 'file' field directs the browser to prompt the user for one or
   * more files to upload. This prompt is done using the browser's own
   * file dialog. When this module's list of allowed file extensions has
   * been set, and this function is added as a processing function for
   * the 'file' field, it adds the extensions to the list of allowed
   * values used by the browser's file dialog.
   *
   * @param mixed $element
   *   The form element to process.
   * @param Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   * @param mixed $completeForm
   *   The full form.
   */
  public static function processFileField(
    &$element,
    FormStateInterface $formState,
    &$completeForm) {

    // Let the file field handle the '#multiple' flag, etc.
    File::processFile($element, $formState, $completeForm);

    // Get the list of allowed file extensions for FolderShare files.
    $extensions = FolderShare::getAllowedNameExtensions();

    // If there are extensions, add them to the form element.
    if (empty($extensions) === FALSE) {
      // The extensions list is space separated without leading dots. But
      // we need comma separated with dots. Map one to the other.
      $list = [];
      foreach (mb_split(' ', $extensions) as $ext) {
        $list[] = '.' . $ext;
      }

      $element['#attributes']['accept'] = implode(',', $list);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    $this->execute();
  }

  /*--------------------------------------------------------------------
   *
   * Execute.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function execute() {

    // Get the parent, if any.
    $parent = $this->getParent();

    // Attach the uploaded files to the parent. PHP has already uploaded
    // the files. It remains to convert the files to File objects, then
    // wrap them with FolderShare entities and add them to the parent folder.
    $configuration = $this->getConfiguration();
    $uploadClass = $configuration['uploadClass'];

    try {
      if ($parent === NULL) {
        $results = FolderShare::addUploadFilesToRoot($uploadClass);
      }
      else {
        $results = $parent->addUploadFiles($uploadClass);
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    // Report success or errors. The returned array mixes File objects
    // with string objects containing error messages. The objects are
    // in the same order as the original list of uploaded files.
    //
    // Below, we sweep through the returned array and post
    // error messages, if any.
    $nUploaded = 0;
    $firstItem = NULL;
    foreach ($results as $entry) {
      if (is_string($entry) === TRUE) {
        // This file had an upload error.
        \Drupal::messenger()->addMessage($entry, 'error');
      }
      else {
        if ($nUploaded === 0) {
          $firstItem = $entry;
        }

        $nUploaded++;
      }
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      // Report on results, if any.
      if ($nUploaded === 1) {
        // Single file. There may have been other files that had errors though.
        $name = $firstItem->getFilename();
        \Drupal::messenger()->addMessage(
          t(
            "The @kind '@name' has been uploaded.",
            [
              '@kind' => Utilities::translateKind(FolderShare::FILE_KIND),
              '@name' => $name,
            ]),
          'status');
      }
      elseif ($nUploaded > 1) {
        // Multiple files. There may have been other files that had errors tho.
        \Drupal::messenger()->addMessage(
          t(
            "@number @kinds have been uploaded.",
            [
              '@number' => $nUploaded,
              '@kinds'  => Utilities::translateKinds(FolderShare::FILE_KIND),
            ]),
          'status');
      }
    }
  }

}
