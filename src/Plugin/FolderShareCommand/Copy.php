<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\RuntimeExceptionWithMarkup;
use Drupal\foldershare\Entity\Exception\ValidationException;

/**
 * Defines a command plugin to copy files and folders.
 *
 * The command copies all selected files and folders to a chosen
 * destination folder or the root list.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to duplicate.
 * - 'destinationId': the destination folder, if any.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_copy",
 *  label           = @Translation("Copy"),
 *  menuNameDefault = @Translation("Copy..."),
 *  menuName        = @Translation("Copy..."),
 *  description     = @Translation("Copy files and folders"),
 *  category        = "copy & move",
 *  weight          = 10,
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *      "folder",
 *    },
 *    "access"  = "view",
 *  },
 *  destinationConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *      "folder",
 *    },
 *    "access"  = "create",
 *  },
 *  selectionConstraints = {
 *    "types"   = {
 *      "one",
 *      "many",
 *    },
 *    "kinds"   = {
 *      "any",
 *    },
 *    "access"  = "view",
 *  },
 * )
 */
class Copy extends CopyMoveBase {

  /*--------------------------------------------------------------------
   *
   * Configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function validateParameters() {
    if ($this->parametersValidated === TRUE) {
      // Already validated.
      return;
    }

    //
    // Validate destination.
    // ---------------------
    // There must be a destination ID. It must be a valid ID. It must
    // not be one of the selected items. And it must not be a descendant
    // of the selected items.
    //
    // A positive destination ID is for a folder to receive the selected
    // items.
    //
    // A negative destination ID is for the user's root list.
    $destinationId = $this->getDestinationId();

    if ($destinationId < 0) {
      // Destination is the user's root list. Nothing further to validate.
      $this->parametersValidated = TRUE;
      return;
    }

    // Destination is a specific folder.
    $destination = FolderShare::load($destinationId);

    if ($destination === NULL) {
      // Destination ID is not valid. This should have been caught
      // well before this validation stage.
      throw new ValidationException(Utilities::createFormattedMessage(
        t(
          '@method was called with an invalid entity ID "@id".',
          [
            '@method' => 'CopyItem::validateParameters',
            '@id'     => $destinationId,
          ])));
    }

    // Verify that the destination is not in the selection. That would
    // be a copy to self, which is not valid.
    $selectionIds = $this->getSelectionIds();
    if (in_array($destinationId, $selectionIds) === TRUE) {
      throw new ValidationException(Utilities::createFormattedMessage(
        t('Items cannot be copied into themselves.')));
    }

    // Verify that the destination is not a descendant of the selection.
    // That would be a recursive tree copy into itself.
    $selection = $this->getSelection();
    foreach ($selection as $items) {
      foreach ($items as $item) {
        if ($destination->isDescendantOfFolderId($item->id()) === TRUE) {
          throw new ValidationException(Utilities::createFormattedMessage(
            t('Items cannot be copied into their own subfolders.')));
        }
      }
    }

    $this->parametersValidated = TRUE;
  }

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getDescription(bool $forPage) {
    // The description varies for page vs. dialog:
    //
    // - Dialog: The description is longer and has the form "Copy OPERAND
    //   to a new location, including all of its contents?" For a single item,
    //   OPERAND is the NAME of the file/folder.
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
            'Copy this @operand to a new location.',
            [
              '@operand' => Utilities::translateKind($item->getKind()),
            ]);
        }

        return t(
          'Copy this folder to a new location, including all of its contents.');
      }

      // Dialog description. Include the name of the item to be deleted.
      if ($item->isFolder() === FALSE) {
        return t(
          'Copy "@name" to a new location.',
          [
            '@name' => $item->getName(),
          ]);
      }

      return t(
        'Copy "@name" to a new location, including all of its contents.',
        [
          '@name' => $item->getName(),
        ]);
    }

    // Find the kinds for each of the selection IDs. Then choose an
    // operand based on the selection's single kind, or "items".
    $selectionKinds = FolderShare::findKindsForIds($selectionIds);
    if (count($selectionIds) === 1) {
      $operand = Utilities::translateKind(key($selectionKinds));
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
        'Copy these @operand to a new location.',
        [
          '@operand' => $operand,
        ]);
    }

    return t(
      'Copy these @operand to a new location, including all of their contents?',
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
    // - Dialog: The title is short and has the form "Copy OPERAND",
    //   where OPERAND is the kind of item (e.g. "file"). By not putting
    //   the item's name in the title, we keep the dialog title short and
    //   avoid cropping or wrapping.
    //
    // - Page: The title is longer and has the form "Copy OPERAND", where
    //   OPERAND can be the name of the item if one item is being deleted,
    //   or the count and kinds if multiple items are being deleted. This
    //   follows Drupal convention.
    $selectionIds = $this->getSelectionIds();

    if ($forPage === TRUE && count($selectionIds) === 1) {
      // Page title. There is only one item. Load it.
      $item = FolderShare::load($selectionIds[0]);
      return t(
        'Copy "@name"',
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
      // Page title. Include the count and operand kind.
      return t(
        "Copy @count @operand?",
        [
          '@count' => count($selectionIds),
          '@operand' => $operand,
        ]);
    }

    return t(
      // Dialog title. Include the operand kind.
      'Copy @operand',
      [
        '@operand' => $operand,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitButtonName() {
    return t('Copy');
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
    $destination = $this->getDestination();

    try {
      if ($destination === NULL) {
        FolderShare::copyToRootMultiple($ids);
      }
      else {
        FolderShare::copyToFolderMultiple($ids, $destination);
      }
    }
    catch (RuntimeExceptionWithMarkup $e) {
      \Drupal::messenger()->addMessage($e->getMarkup(), 'error', TRUE);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        \Drupal::translation()->formatPlural(
          count($ids),
          "The item has been copied.",
          "@count items have been copied."),
        'status');
    }
  }

}
