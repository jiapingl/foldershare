<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines a command plugin to create a new folder.
 *
 * The command creates a new folder in the current parent folder, if any.
 * If there is no parent folder, the command creates a new root folder.
 * The new folder is empty and has a default name.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_new_folder",
 *  label           = @Translation("New Folder"),
 *  menuNameDefault = @Translation("New Folder"),
 *  menuName        = @Translation("New Folder"),
 *  description     = @Translation("Create new folder"),
 *  category        = "open",
 *  weight          = 10,
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
class NewFolder extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Execute.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function execute() {

    $parent = $this->getParent();
    try {
      if ($parent === NULL) {
        $newFolder = FolderShare::createRootFolder('');
      }
      else {
        $newFolder = $parent->createFolder('');
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        t(
          "A @kind named '@name' has been created.",
          [
            '@kind' => Utilities::translateKind($newFolder->getKind()),
            '@name' => $newFolder->getName(),
          ]),
        'status');
    }
  }

}
