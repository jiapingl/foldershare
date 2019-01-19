<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Form\FormStateInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Provides the base class for command plugins that delete files or folders.
 *
 * The command deletes all selected entities. Deletion recurses and
 * deletes all folder content as well.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to delete.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_release_share",
 *  label           = @Translation("Release Share"),
 *  menuNameDefault = @Translation("Release Share..."),
 *  menuName        = @Translation("Release Share..."),
 *  description     = @Translation("Release shared files and subfolders"),
 *  category        = "settings",
 *  weight          = 20,
 *  userConstraints = {
 *    "any",
 *  },
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *    },
 *    "access"  = "update",
 *  },
 *  selectionConstraints = {
 *    "types"   = {
 *      "one",
 *      "many",
 *    },
 *    "kinds"   = {
 *      "any",
 *    },
 *    "ownership" = {
 *      "sharedwithusertoview",
 *      "sharedwithusertoauthor",
 *    },
 *    "access"  = "view",
 *  },
 * )
 */
class ReleaseShare extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasConfigurationForm() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(bool $forPage) {
    // The description varies for page vs. dialog:
    //
    // - Dialog: The description is longer and has the form "Release
    //   the shared OPERAND? Once released, these items will no longer be
    //   accessible." For a single item, OPERAND is the NAME of the file/folder.
    //
    // - Page: The description is as for a dialog, except that the single
    //   item form is not included because it is already in the title.
    $selectionIds = $this->getSelectionIds();

    if (count($selectionIds) === 1) {
      // There is only one item. Load it.
      $item = FolderShare::load(reset($selectionIds));

      if ($forPage === TRUE) {
        // Page description. The page title already gives the name of the
        // item to be deleted. Don't include the item's name again here.
        if ($item->isFolder() === FALSE) {
          return t(
            'Release this shared @operand? Once released, this @operand will no longer be accessible.',
            [
              '@operand' => Utilities::translateKind($item->getKind()),
            ]);
        }

        return t(
          'Release this shared folder? Once released, this folder and all of its contents will no longer be accessible.');
      }

      // Dialog description. Include the name of the item to be deleted.
      if ($item->isFolder() === FALSE) {
        return t(
          'Release the shared file "@name"? Once released, this file will no longer be accessible.',
          [
            '@name' => $item->getName(),
          ]);
      }

      return t(
        'Release the shared folder "@name"? Once released, this folder and all of its contents will no longer be accessible.',
        [
          '@name' => $item->getName(),
        ]);
    }

    // Find the kinds for each of the selection IDs. Then choose an
    // operand based on the selection's single kind, or "items".
    $selectionKinds = FolderShare::findKindsForIds($selectionIds);
    if (count($selectionIds) === 1) {
      $operand = Utilities::trnaslateKind(key($selectionKinds));
    }
    elseif (count($selectionKinds) === 1) {
      $operand = Utilities::translateKinds(key($selectionKinds));
    }
    else {
      $operand = Utilities::translateKinds('items');
    }

    // Dialog and page description.
    //
    // Use the count and kind and end in a question mark. For folders,
    // include a reminder that all their contents are deleted too.
    if (isset($selectionKinds[FolderShare::FOLDER_KIND]) === FALSE) {
      return t(
        'Release these shared @operand? Once released, these items will no longer be accessible.',
        [
          '@operand' => $operand,
        ]);
    }

    return t(
      'Release these shared @operand? Once released, these items and all folder contents will no longer be accessible.',
      [
        '@operand' => $operand,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(bool $forPage) {
    // The title varies for page vs. dialog:
    //
    // - Dialog: The title is short and has the form "Release shared OPERAND",
    //   where OPERAND is the kind of item (e.g. "file"). By not putting
    //   the item's name in the title, we keep the dialog title short and
    //   avoid cropping or wrapping.
    //
    // - Page: The title is longer and has the form "Release shared OPERAND?",
    //   where OPERAND can be the name of the item if one item is being deleted,
    //   or the count and kinds if multiple items are being deleted. This
    //   follows Drupal convention.
    $selectionIds = $this->getSelectionIds();

    if ($forPage === TRUE && count($selectionIds) === 1) {
      // Page title. There is only one item. Load it.
      $item = FolderShare::load($selectionIds[0]);
      return t(
        'Release shared "@name"?',
        [
          '@name' => $item->getName(),
        ]);
    }

    // Find the kinds for each of the selection IDs. Then choose an
    // operand based on the selection's single kind, or "items".
    $selectionKinds = FolderShare::findKindsForIds($selectionIds);
    if (count($selectionIds) === 1) {
      $kind = key($selectionKinds);
      $operand = Utilities::translateKind($kind);
    }
    elseif (count($selectionKinds) === 1) {
      $kind = key($selectionKinds);
      $operand = Utilities::translateKinds($kind);
    }
    else {
      $operand = Utilities::translateKinds('items');
    }

    if ($forPage === TRUE) {
      // Page title. Include the count and operand kind. Question mark.
      return t(
        "Release @count shared @operand?",
        [
          '@count' => count($selectionIds),
          '@operand' => $operand,
        ]);
    }

    return t(
      // Dialog title. Include the operand kind. No question mark.
      'Release shared @operand',
      [
        '@operand' => $operand,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitButtonName() {
    return t('Release');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {

    // The command wrapper provides form basics:
    // - Attached libraries.
    // - Page title (if not an AJAX dialog).
    // - Description (from ::getDescription()).
    // - Submit buttion (labeled with ::getSubmitButtonName()).
    // - Cancel button (if AJAX dialog).
    $form['#attributes']['class'][] = 'confirmation';
    $form['#theme'] = 'confirm_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $formState) {
    // Nothing to do.
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
    $ids = $this->getSelectionIds();

    try {
      FolderShare::unshareMultiple($ids, \Drupal::currentUser()->id(), '');
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        \Drupal::translation()->formatPlural(
          count($ids),
          "Sharing for the item has been released.",
          "Sharing for @count items has been released."),
        'status');
    }
  }

}
