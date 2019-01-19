<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Url;

use Drupal\foldershare\Constants;

/**
 * Defines a command plugin to download files or folders.
 *
 * The command downloads a single selected entity.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to download.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_download",
 *  label           = @Translation("Download"),
 *  menuNameDefault = @Translation("Download"),
 *  menuName        = @Translation("Download"),
 *  description     = @Translation("Download files and folders"),
 *  category        = "import & export",
 *  weight          = 20,
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *      "any",
 *    },
 *    "access"  = "view",
 *  },
 *  selectionConstraints = {
 *    "types"   = {
 *      "parent",
 *      "one",
 *      "many",
 *    },
 *    "kinds"   = {
 *      "file",
 *      "image",
 *      "folder",
 *    },
 *    "access"  = "view",
 *  },
 * )
 */
class Download extends FolderShareCommandBase {

  /*---------------------------------------------------------------------
   *
   * Redirects.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasRedirect() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirect() {
    $ids = $this->getSelectionIds();
    if (empty($ids) === TRUE) {
      $ids[] = $this->getParentId();
    }

    // Redirect to download.
    return Url::fromRoute(
      Constants::ROUTE_DOWNLOAD,
      [
        'encoded' => implode(',', $ids),
      ]);
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
    // Do nothing.
  }

}
