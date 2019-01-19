<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

/**
 * Defines a command plugin to delete files or folders, not on a rootlist.
 *
 * This is one of several versions of the "Delete" command. This version is
 * only available on rootlists, not on folders, and the user must be an
 * administrator. On a rootlist, it can delete any root file or folder
 * owned by anyone.
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
 *  id              = "foldersharecommand_delete_as_admin",
 *  label           = @Translation("Delete"),
 *  menuNameDefault = @Translation("Delete..."),
 *  menuName        = @Translation("Delete..."),
 *  description     = @Translation("Administrator delete files and folders"),
 *  category        = "delete",
 *  weight          = 10,
 *  userConstraints = {
 *    "adminpermission",
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
 *      "any",
 *    },
 *    "access"  = "delete",
 *  },
 * )
 */
class DeleteAsAdmin extends DeleteBase {

}
