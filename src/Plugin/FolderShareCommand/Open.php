<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Url;

use Drupal\foldershare\Constants;

/**
 * Defines a command plugin to open an entity.
 *
 * The command is a dummy that does not use a configuration and does not
 * execute to change the entity. Instead, this command is used solely to
 * create an entry in command menus and support a redirect to the entity's
 * view page.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to edit.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_open",
 *  label           = @Translation("Open"),
 *  menuNameDefault = @Translation("Open..."),
 *  menuName        = @Translation("Open..."),
 *  description     = @Translation("Open file or folder"),
 *  category        = "open",
 *  weight          = 20,
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
 *      "folder",
 *    },
 *    "access"  = "view",
 *  },
 *  selectionConstraints = {
 *    "types"   = {
 *      "one",
 *    },
 *    "kinds"   = {
 *      "any",
 *    },
 *    "access"  = "view",
 *  },
 * )
 */
class Open extends FolderShareCommandBase {

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
      $id = $this->getParentId();
    }
    else {
      $id = reset($ids);
    }

    return Url::fromRoute(
      Constants::ROUTE_FOLDERSHARE,
      [
        Constants::ROUTE_FOLDERSHARE_ID => $id,
      ]);
  }

}
