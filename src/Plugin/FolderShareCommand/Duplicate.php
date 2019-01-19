<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines a command plugin to duplicate a file or folder.
 *
 * The command copies all selected entities and adds them back into the
 * same parent folder or root folder list. Duplication recurses through
 * all folder content as well.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to duplicate.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_duplicate",
 *  label           = @Translation("Duplicate"),
 *  menuNameDefault = @Translation("Duplicate"),
 *  menuName        = @Translation("Duplicate"),
 *  description     = @Translation("Duplicate files and folders"),
 *  category        = "copy & move",
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
class Duplicate extends FolderShareCommandBase {

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
      FolderShare::duplicateMultiple($ids);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        \Drupal::translation()->formatPlural(
          count($ids),
          "The item has been duplicated.",
          "@count items have been duplicated."),
        'status');
    }
  }

}
