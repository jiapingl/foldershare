<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\Exception\RuntimeExceptionWithMarkup;

/**
 * Defines a command plugin to archive (compress) files and folders.
 *
 * The command creates a new ZIP archive containing all of the selected
 * files and folders and adds the archive to current folder.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to Archive.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_archive",
 *  label           = @Translation("Compress"),
 *  menuNameDefault = @Translation("Compress"),
 *  menuName        = @Translation("Compress"),
 *  description     = @Translation("Compress files and folders into a ZIP archive"),
 *  category        = "archive",
 *  weight          = 20,
 *  parentConstraints = {
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
class Compress extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Execute.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function execute() {

    // Consolidate the selection into a single entity list.
    $nItems    = 0;
    $firstItem = NULL;
    $children  = [];
    $selection = $this->getSelection();

    foreach ($selection as $items) {
      $n = count($items);
      if ($n > 0) {
        $children = array_merge($children, $items);
        $nItems += $n;
        if ($firstItem === NULL) {
          $firstItem = reset($items);
        }
      }
    }

    // Create an archive.
    $parent = $this->getParent();
    try {
      if ($parent === NULL) {
        $archive = FolderShare::archiveToRoot($children);
      }
      else {
        $archive = $parent->archiveToFolder($children);
      }
    }
    catch (RuntimeExceptionWithMarkup $e) {
      \Drupal::messenger()->addMessage($e->getMarkup(), 'error', TRUE);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      if ($nItems === 1) {
        // One item. Refer to it by name.
        \Drupal::messenger()->addMessage(
          t(
            "The @kind '@name' has been compressed and saved into the new '@archive' file.",
            [
              '@kind'    => Utilities::translateKind($firstItem->getKind()),
              '@name'    => $firstItem->getName(),
              '@archive' => $archive->getName(),
            ]),
          'status');
      }
      elseif (count($selection) === 1) {
        // One kind of items. Refer to them by kind.
        \Drupal::messenger()->addMessage(
          t(
            "@number @kinds have been compressed and saved into the new '@archive' file.",
            [
              '@number'  => $nItems,
              '@kinds'   => Utilities::translateKinds($firstItem->getKind()),
              '@archive' => $archive->getName(),
            ]),
          'status');
      }
      else {
        // Multiple kinds of items.
        \Drupal::messenger()->addMessage(
          t(
            "@number @kinds have been compressed and saved into the new '@archive' file.",
            [
              '@number'  => $nItems,
              '@kinds'   => Utilities::translateKinds('items'),
              '@archive' => $archive->getName(),
            ]),
          'status');
      }
    }
  }

}
