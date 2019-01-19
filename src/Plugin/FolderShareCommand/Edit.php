<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Url;

use Drupal\foldershare\Constants;

/**
 * Defines a command plugin to edit an entity.
 *
 * The command is a dummy that does not use a configuration and does not
 * execute to change the entity. Instead, this command is used solely to
 * create an entry in command menus and support a redirect to a stand-alone
 * edit form from which the user can alter fields on the entity.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to edit.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_edit",
 *  label           = @Translation("Edit"),
 *  menuNameDefault = @Translation("Edit Description..."),
 *  menuName        = @Translation("Edit Description..."),
 *  description     = @Translation("Edit file and folder description"),
 *  category        = "edit",
 *  weight          = 10,
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
 *    },
 *    "kinds"   = {
 *      "any",
 *    },
 *    "access"  = "update",
 *  },
 * )
 */
class Edit extends FolderShareCommandBase {

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
      Constants::ROUTE_FOLDERSHARE_EDIT,
      [
        Constants::ROUTE_FOLDERSHARE_ID => $id,
      ]);
  }

}
