<?php

namespace Drupal\foldershare\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the annotation for command plugins.
 *
 * A command is similar to a Drupal action. It has a configuration containing
 * operands for the command, and an execute() function to apply the command
 * to the configuration. Some commands also have configuration forms to
 * prompt for operands.
 *
 * Commands differ from Drupal actions by including extensive annotation
 * that governs how that command appears in menus and under what circumstances
 * the command may be applied. For instance, annotation may indicate if
 * the command applies to files or folders or both, whether it requires a
 * selection and if that selection can include more than one item, and
 * what access permissions the user must have on the parent folder and on
 * the selection.
 *
 * Annotation for a command can be broken down into groups of information:
 *
 * - Identification:
 *   - the unique machine name for the plugin.
 *
 * - User interface:
 *   - the labels for the plugin.
 *   - the user interface category and weight for presenting the command.
 *
 * - Constraints and access controls:
 *   - the parent folder constraints and access controls required, if needed.
 *   - the selection constraints and access controls required, if needed.
 *   - the destination constraints and access controls required, if needed.
 *   - any special handling needed.
 *
 * There are several plugin labels that may be specified. Each is used in
 * a different context:
 *
 * - "label" is a generic name for the command, such as "Edit" or "Delete".
 *   This label may be used in error messages, such as "The Delete command
 *   requires a selection."
 *
 * - "menuNameDefault" is the command name used in menus when no better text
 *   is available in "menuName". The default text is primarily used when the
 *   menu item is disabled. If not given, this defaults to the value of "label".
 *
 * - "menuName" is the command name to use in menus. This is primarily used
 *   for selectable menu items and the text may include the "@operand" marker,
 *   which will be replaced with the name of an item or a kind, such as
 *   "Edit @operand...". If not given, this defaults to the value of
 *   "menuNameDefault".
 *
 * "label", "menuNameDefault", and "menuName" should all use
 * title-case except for small connecting words like "of", "in", and "and".
 *
 * An optional "@operand" is replaced with text that varies depending upon
 * use.  Possibilities include:
 *
 * - Replaced with a singular name of the kind of operand, such as
 *   "Delete file" or "Delete folder". If the context is the current item,
 *   the word "this" may be inserted, such as "Delete this file".
 *
 * - Replaced with a plural name of the kind for all operands, such as
 *   "Delete files" or "Delete folders".
 *
 * - Replaced with a plural "items" if the kinds of the operands is mixed,
 *   such as "Delete items".
 *
 * - Replaced with the quoted singular name of the operand when there is
 *   just one item (typically for form titles), such as "Delete "Bob's folder"".
 *
 * The plugin namespace for commands is "Plugin\FolderShareCommand".
 *
 * @ingroup foldershare
 *
 * @Annotation
 */
class FolderShareCommand extends Plugin {

  /*--------------------------------------------------------------------
   * Identification
   *--------------------------------------------------------------------*/

  /**
   * The unique machine-name plugin ID.
   *
   * The value is taken from the plugin's 'id' annotation.
   *
   * The ID must be a unique string used to identify the plugin. Often it
   * is closely related to a module namespace and class name.
   *
   * @var string
   */
  public $id = '';

  /*--------------------------------------------------------------------
   * User interface
   *--------------------------------------------------------------------*/

  /**
   * The generic command label for user interfaces.
   *
   * The label is used by user interface code for buttons and error messages.
   * The value is required.
   *
   * The text should be translated and use title-case where each word is
   * capitalized, except for small connecting words like "of" or "in".
   *
   * Examples:
   * - "Change Owner".
   * - "Delete".
   * - "Edit".
   * - "Rename".
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label = '';

  /**
   * The name for disabled user interface menus.
   *
   * The menu name is used for user interface code to create a menu item for
   * the command when the command is disabled and not available to the user.
   * If no menu name is given, the value defaults to the value of the
   * 'label' field.
   *
   * The text should be translated and use title-case where each word is
   * capitalized, except for small connecting words like "of" or "in".
   *
   * Examples:
   * - "Change Owner...".
   * - "Delete...".
   * - "Edit...".
   * - "Rename...".
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $menuNameDefault = '';

  /**
   * The name for enabled user interface menus.
   *
   * The menu name is used for user interface code to create a menu item for
   * the command when the command is enabled and available to the user. If
   * no menu name is given, the value defaults to the value of the
   * 'menuNameDefault' field.
   *
   * The text should be translated and use title-case where each word is
   * capitalized, except for small connecting words like "of" or "in".
   *
   * The text may include "@operand" to mark where the name of an item or
   * item kind should be inserted.
   *
   * Examples:
   * - "Change Owner of @operand...".
   * - "Delete @operand...".
   * - "Edit @operand...".
   * - "Rename @operand...".
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $menuName = '';

  /**
   * A brief description of the command.
   *
   * @var string
   */
  public $description = '';

  /**
   * The command's category within user interfaces.
   *
   * The category gives the name of a group into which the command should be
   * sorted when it is presented in a menu. Category names must be lower case.
   *
   * The module defines a set of well-known categories that may be used.
   * Any category name not on this list is appended to the end of menus
   * based upon these categories.
   *
   * Well-known categories are (in order):
   * - "open".
   * - "import & export".
   * - "close".
   * - "edit".
   * - "delete".
   * - "copy & move".
   * - "save".
   * - "archive".
   * - "message".
   * - "settings".
   * - "administer".
   *
   * @var string
   *
   * @see \Drupal\Core\Plugin\CategorizingPluginManagerTrait
   * @see \Drupal\Component\Plugin\CategorizingPluginManagerInterface
   */
  public $category = '';

  /**
   * The commands weight among other commands in the same category.
   *
   * The value is taken from the plugin's 'weight' annotation and should
   * be a positive or negative integer.
   *
   * Weights are used to sort commands within a category before they
   * are presented within a menu, toobar of buttons, etc. Higher weights
   * are listed later in the category.
   *
   * @var int
   */
  public $weight = 0;

  /*--------------------------------------------------------------------
   * Constraints and access controls
   *--------------------------------------------------------------------*/

  /**
   * The command's user constraints.
   *
   * Defined by the 'userConstraints' annotation, this value is an array
   * of user type requirements:
   * - 'any' (default).
   * - 'anonymous'.
   * - 'authenticated'.
   * - 'adminpermission'.
   * - 'noadminpermission'.
   * - 'authorpermission'.
   * - 'sharepermission'.
   * - 'sharepublicpermission'.
   * - 'viewpermission'.
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * @var array
   */
  public $userConstraints = NULL;

  /**
   * The command's parent constraints.
   *
   * Defined by the 'parentConstraints' annotation, this value is an
   * associative array with keys:
   * - 'kinds': an optional list of the kinds of parents supported.
   * - 'access': an optional access operation that the parent must support.
   * - 'ownership': an optional list of ownerships supported.
   *
   * <B>Parent kinds</B><BR>
   * The 'kinds' field lists the kinds of parent supported:
   * - 'any' (default).
   * - 'rootlist'.
   * - Any kind supported by FolderShare (e.g. 'file', 'folder').
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * <B>Parent access</B><BR>
   * The 'access' field names ONE access operation the parent must support:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view' (default).
   *
   * <B>Parent ownership</B><BR>
   * The 'ownership' field lists the ownership states the parent supports:
   * - 'any' (default).
   * - 'ownedbyanonymous'.
   * - 'ownedbyanother'.
   * - 'ownedbyuser'.
   * - 'sharedbyuser'.
   * - 'sharedwithanonymoustoview'.
   * - 'sharedwithanonymoustoauthor'.
   * - 'sharedwithusertoview'.
   * - 'sharedwithusertoauthor'.
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * @var array
   */
  public $parentConstraints = NULL;

  /**
   * The command's selection constraints, if any.
   *
   * Defined by the 'selectionConstraints' annotation, this value is an
   * associative array with keys:
   * - 'types': an optional indicator of the selection size.
   * - 'kinds': an optional list of the kinds of selected items supported.
   * - 'access': an optional access operation that the selection must support.
   * - 'ownership': an optional list of ownerships supported.
   *
   * <B>Selection types</B><BR>
   * The 'types' field lists types of selection supported:
   * - 'none' (default).
   * - 'parent'.
   * - 'one'.
   * - 'many'.
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * <B>Selection kinds</B><BR>
   * The 'kinds' field lists the kinds of selection supported:
   * - 'any' (default).
   * - Any kind supported by FolderShare (e.g. 'file', 'folder').
   *
   * The 'rootlist' kind is not available for selections.
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * <B>Selection access</B><BR>
   * The 'access' field names ONE access operation every selected item
   * must support:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update'.
   * - 'view' (default).
   *
   * <B>Selection ownership</B><BR>
   * The 'ownership' field lists the ownership states the selection supports:
   * - 'any' (default).
   * - 'ownedbyanonymous'.
   * - 'ownedbyanother'.
   * - 'ownedbyuser'.
   * - 'sharedbyuser'.
   * - 'sharedwithanonymoustoview'.
   * - 'sharedwithanonymoustoauthor'.
   * - 'sharedwithusertoview'.
   * - 'sharedwithusertoauthor'.
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * <B>Selection file extensions</B><BR>
   * The 'fileExtensions' field lists file name extensions the selection
   * supports. An empty list (the default) supports everything.
   *
   * @var array
   */
  public $selectionConstraints = NULL;

  /**
   * The command's destination constraints, if any.
   *
   * Defined by the 'destinationConstraints' annotation, this value is an
   * associative array with keys:
   * - 'kinds': an optional list of the kinds of selected items supported.
   * - 'access': an optional access operation that the selection must support.
   * - 'ownership': an optional list of ownerships supported.
   *
   * <B>Destination kinds</B><BR>
   * The 'kinds' field lists the kinds of destination supported:
   * - 'none' (default).
   * - 'any'.
   * - 'rootlist'.
   * - Any kind supported by FolderShare (e.g. 'file', 'folder').
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * <B>Destination access</B><BR>
   * The 'access' field names ONE access operation the destination must support:
   * - 'chown'.
   * - 'create'.
   * - 'delete'.
   * - 'share'.
   * - 'update' (default).
   * - 'view'.
   *
   * <B>Destination ownership</B><BR>
   * The 'ownership' field lists the ownership states the destination supports:
   * - 'any' (default).
   * - 'ownedbyanonymous'.
   * - 'ownedbyanother'.
   * - 'ownedbyuser'.
   * - 'sharedbyuser'.
   * - 'sharedwithanonymoustoview'.
   * - 'sharedwithanonymoustoauthor'.
   * - 'sharedwithusertoview'.
   * - 'sharedwithusertoauthor'.
   *
   * The constraint is met if any choice is met (i.e. they are ORed together).
   *
   * @var array
   */
  public $destinationConstraints = NULL;

  /**
   * The command's special needs, if any.
   *
   * Defined by the 'specialHandling' annotation, this value is an array
   * with known values:
   * - 'upload': the command uploads files.
   *
   * @var array
   */
  public $specialHandling = NULL;

}
