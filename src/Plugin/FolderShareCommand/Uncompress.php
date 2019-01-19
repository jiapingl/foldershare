<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines a command plugin to unarchive (uncompress) files and folders.
 *
 * The command extracts all contents of a ZIP archive and adds them to
 * the current folder.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to Archive.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_unarchive",
 *  label           = @Translation("Uncompress"),
 *  menuNameDefault = @Translation("Uncompress"),
 *  menuName        = @Translation("Uncompress"),
 *  description     = @Translation("Uncompress ZIP files"),
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
 *    },
 *    "kinds"   = {
 *      "file",
 *    },
 *    "fileExtensions" = {
 *      "zip",
 *    },
 *    "access"  = "view",
 *  },
 * )
 */
class Uncompress extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Execute.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $selectionIds = $this->getSelectionIds();
    $item = FolderShare::load(reset($selectionIds));

    try {
      $item->unarchiveFromZip();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        t(
          "The @kind '@name' has been uncompressed.",
          [
            '@kind' => Utilities::translateKind($item->getKind()),
            '@name' => $item->getName(),
          ]),
        'status');
    }
  }

}
