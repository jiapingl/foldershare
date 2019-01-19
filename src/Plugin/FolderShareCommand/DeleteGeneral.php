<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

/**
 * Defines a command plugin to delete files or folders, not on a rootlist.
 *
 * This is one of several versions of the "Delete" command. This version is
 * only available on folders, not on root lists. On a folder, it can delete any
 * file or subfolder as long as the user has "delete" access to the folder
 * tree.
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
 *  id              = "foldersharecommand_delete",
 *  label           = @Translation("Delete"),
 *  menuNameDefault = @Translation("Delete..."),
 *  menuName        = @Translation("Delete..."),
 *  description     = @Translation("Delete files and subfolders"),
 *  category        = "delete",
 *  weight          = 10,
 *  userConstraints = {
 *    "any",
 *  },
 *  parentConstraints = {
 *    "kinds"   = {
 *      "folder",
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
 *    "access"  = "delete",
 *  },
 * )
 */
class DeleteGeneral extends DeleteBase {

}
