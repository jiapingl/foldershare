<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

/**
 * Defines a command plugin to delete files or folders, from a root list.
 *
 * This is one of several versions of the "Delete" command. This version is
 * only available on rootlists, not on folders. On a rootlist, it can delete
 * any root file or folder as long as it is owned by the current user.
 *
 * The command deletes all selected entities, as long as the user owns them.
 * Deletion recurses and deletes all folder content as well.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entities to delete.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_delete_on_rootlist",
 *  label           = @Translation("Delete"),
 *  menuNameDefault = @Translation("Delete..."),
 *  menuName        = @Translation("Delete..."),
 *  description     = @Translation("Delete top-level files and folders"),
 *  category        = "delete",
 *  weight          = 10,
 *  userConstraints = {
 *    "noadminpermission",
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
 *      "ownedbyuser",
 *    },
 *    "access"  = "delete",
 *  },
 * )
 */
class DeleteOnRootList extends DeleteBase {

}
