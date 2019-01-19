<?php

namespace Drupal\foldershare;

/**
 * Defines constants use through the module.
 *
 * This class defines constants for often-used text, services, libraries,
 * themes, permissions, routes, and so forth. In many cases, these values
 * repeat values defined in YML files.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @internal
 * Routes, permissions, themes, libraries, and many other names are
 * defined in the module's many ".yml" files. These same names are needed
 * at run-time to refer to the defined routes, permissions, etc.
 *
 * While many YML-defined values are available at run-time via the
 * Drupal API, they are often referenced via name. This forces these
 * name strings to be embedded in the code.
 *
 * Frequent use of important hard-coded strings invites typos that can
 * cause routes, permissions, settings, and other values to not line up
 * with their intended targets. To reduce the effect of typos, and centralize
 * common names, these are all defined here in a module-wide list of
 * constants. Module code uses these constants by name, rather than
 * hard-coding typo-risky strings. This turns these strings into syntactic
 * objects in PHP, which the PHP parser can catch and report as errors if
 * typos creep in.
 * @endinternal
 *
 * @ingroup foldershare
 */
final class Constants {

  /*--------------------------------------------------------------------
   *
   * Administration.
   *
   *--------------------------------------------------------------------*/

  /**
   * The module machine name.
   *
   * This must match all uses of the module name:
   *
   * - The name of the module directory.
   * - The name of the "MODULE.module" file.
   * - The machine name in "MODULE.info.yml".
   * - The name of the \Drupal\MODULE namespace for the module.
   *
   * This module name should be used as the prefix for names that
   * have global scope, such as tables, settings, and routes.
   *
   * The module name should be used as a prefix for CSS classes
   * and IDs, and as the name of the module's function group in
   * Javascript.
   *
   * @var string
   */
  const MODULE = 'foldershare';

  /**
   * The module's settings (configuration) name.
   *
   * This must match the configuration name in "MODULE.info.yml'.
   *
   * This must match the file name and settings group in
   * 'config/schema/MODULE.settings.yml'. It also must match the
   * file name in 'install/MODULE.settings.yml'.
   *
   * @var string
   */
  const SETTINGS = 'foldershare.settings';

  /**
   * The module's search index name.
   *
   * This must match the name in the "FolderSearch" plugin's annotation.
   *
   * This must match the name of the search page configuration in
   * 'config/optional/search.page.SEARCH_INDEX.yml'.
   *
   * This search index only exists if the Drupal core search module
   * is enabled.
   *
   * @var string
   */
  const SEARCH_INDEX = 'foldershare_search';

  /*--------------------------------------------------------------------
   *
   * Libraries.
   *
   *--------------------------------------------------------------------*/

  /**
   * The module primary library.
   *
   * This must match the library in 'MODULE.libraries.yml'.
   *
   * @var string
   */
  const LIBRARY_MODULE = 'foldershare/foldershare.module';

  /**
   * The module administration pages library.
   *
   * This must match the library in 'MODULE.libraries.yml'.
   *
   * @var string
   */
  const LIBRARY_ADMIN = 'foldershare/foldershare.module.admin';

  /**
   * The module field formatter library.
   *
   * This must match the library in 'MODULE.libraries.yml'.
   *
   * @var string
   */
  const LIBRARY_FIELD_FORMATTER = 'foldershare/foldershare.fieldformatter';

  /*--------------------------------------------------------------------
   *
   * Routes and route parameters.
   *
   * These constants define well-known module routes. All values must
   * match those in 'MODULE.routing.yml'.
   *
   *--------------------------------------------------------------------*/

  /**
   * The route to a FolderShare entity view page.
   *
   * The route has a numeric FolderShare ID parameter.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   * @see self::ROUTE_FOLDERSHARE_ID
   */
  const ROUTE_FOLDERSHARE = 'entity.foldershare.canonical';

  /**
   * The route to the user's personal and shared-with-them root item list.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   */
  const ROUTE_ROOT_ITEMS_PERSONAL = 'entity.foldershare.rootitems';

  /**
   * The route to the site's public root item list.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   */
  const ROUTE_ROOT_ITEMS_PUBLIC = 'entity.foldershare.rootitems.public';

  /**
   * The route to the site admin's root item list of everything.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   */
  const ROUTE_ROOT_ITEMS_ALL = 'entity.foldershare.rootitems.all';

  /**
   * The route to a FolderShare entity edit page.
   *
   * The route has a numeric FolderShare ID parameter.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   * @see self::ROUTE_FOLDERSHARE_ID
   */
  const ROUTE_FOLDERSHARE_EDIT = 'entity.foldershare.edit';

  /**
   * The FolderShare view and edit route parameter for an entity ID.
   *
   * @var string
   */
  const ROUTE_FOLDERSHARE_ID = 'foldershare';

  /**
   * The UI's command route to command-specific forms.
   *
   * @var string
   */
  const ROUTE_FOLDERSHARE_COMMAND_FORM = 'entity.foldersharecommand.plugin';

  /**
   * The route to the settings page.
   *
   * This must match the route in 'MODULE.routing.yml',
   * 'foldershare.links.menu.yml', and 'MODULE.links.task.yml'.
   *
   * @var string
   */
  const ROUTE_SETTINGS = 'entity.foldershare.settings';

  /**
   * The route to the file download handler.
   *
   * An argument 'file' must contain the entity ID of a File object wrapped
   * by a FolderShare entity.
   *
   * The route may include one or more query arguments. If present, the
   * 'foldershareprefix' query argument contains a string containing the
   * URL path prefix to prepend to the incoming file URI to generate a
   * redirect URL and return to whatever URL processing another
   * module (such as Image) may require for derived paths.
   *
   * Any other query and fragment arguments are passed along on the
   * redirect.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   */
  const ROUTE_DOWNLOADFILE = 'entity.foldershare.file';

  /**
   * The query name for a path prefix for the file download handler.
   *
   * @var string
   */
  const ROUTE_DOWNLOADFILE_PREFIX = 'foldershareprefix';

  /**
   * The route to the entity download handler.
   *
   * The argument 'encoded' must contain a JSON base64 array containing
   * FolderShare entity IDs to download. They must all be children of
   * the same parent.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   */
  const ROUTE_DOWNLOAD = 'entity.foldershare.download';

  /**
   * The route to the usage page.
   *
   * This must match the route in 'MODULE.routing.yml'.
   *
   * @var string
   */
  const ROUTE_USAGE = 'foldershare.reports.usage';

  /**
   * The route to the delete-all form.
   *
   * The argument 'eneity_type_id' must be the entity ID.
   *
   * This must match the route in 'system.routing.yml'.
   *
   * @var string
   */
  const ROUTE_DELETEALL = 'system.prepare_modules_entity_uninstall';

  /**
   * The route to the help page.
   *
   * The value must have the form "help.page.MODULE".  To get to the
   * help page for a module, the "name" parameter must be set to
   * the module's name.
   *
   * @var string
   */
  const ROUTE_HELP = 'help.page';

  /**
   * The route to the views UI page listing views.
   *
   * @var string
   */
  const ROUTE_VIEWS_UI = 'entity.view.collection';

  /**
   * The base route to the views UI page for a specific view.
   *
   * The name of the view to edit must be in the "view" parameter.
   *
   * @var string
   */
  const ROUTE_VIEWS_UI_VIEW = 'entity.view.edit_form';

  /*--------------------------------------------------------------------
   *
   * URLs.
   *
   *--------------------------------------------------------------------*/

  /**
   * The base URL to the Drupal module documentation pages.
   *
   * The documentation for module 'system', for instance, is at:
   * $url = Constants::URL_DRUPAL_CORE_MODULE_DOC . 'system';
   *
   * @var string
   */
  const URL_DRUPAL_CORE_MODULE_DOC = 'https://www.drupal.org/docs/8/core/modules/';

  /*--------------------------------------------------------------------
   *
   * Services.
   *
   *--------------------------------------------------------------------*/

  /**
   * The service that keeps track of folder command plugins.
   *
   * This must match the route in 'MODULE.services.yml'.
   *
   * @var string
   */
  const SERVICE_FOLDERSHARECOMMANDS = 'foldershare.plugin.manager.foldersharecommand';

  /*--------------------------------------------------------------------
   *
   * Plugins.
   *
   *--------------------------------------------------------------------*/

  /**
   * The name of the module's search plugin for Drupal core Search.
   *
   * This name must match the ID for the search plugin.
   *
   * @var string
   */
  const SEARCH_PLUGIN = 'foldershare_search';

  /*--------------------------------------------------------------------
   *
   * Views and their displays.
   *
   * These constants define the names of module-installed views.
   *
   *--------------------------------------------------------------------*/

  /**
   * The view creating all lists of files and folders.
   *
   * The name must match the machine name of the "FolderShare Lists" view.
   *
   * @var string
   */
  const VIEW_LISTS = 'foldershare_lists';

  /**
   * The view display listing all root items for any user.
   *
   * Used for the 'All files' list available to admins.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "List all" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_LIST_ALL = 'list_all';

  /**
   * The view display listing root items owned by or shared with a user.
   *
   * Used for the 'Personal files' list.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "List personal" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_LIST_PERSONAL = 'list_personal';

  /**
   * The view display listing root items owned by or shared with anonymous.
   *
   * Used for the 'Public files' list.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "List public" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_LIST_PUBLIC = 'list_public';

  /**
   * The view display listing folder contents available to the user.
   *
   * Used for the folder contents list on entity view pages.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "List folder" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_LIST_FOLDER = 'list_folder';

  /**
   * The view display dialog listing all root items for any user.
   *
   * Used for the 'All files' list in dialogs.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "Dialog all" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_DIALOG_ALL = 'dialog_all';

  /**
   * The view display dialog listing root items owned by or shared with a user.
   *
   * Used for the 'Personal files' list in dialogs.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "Dialog personal" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_DIALOG_PERSONAL = 'dialog_personal';

  /**
   * The view display dialog listing root items owned by/shared with anonymous.
   *
   * Used for the 'Public files' list in dialogs.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "Dialog public" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_DIALOG_PUBLIC = 'dialog_public';

  /**
   * The view display dialog listing folder contents available to the user.
   *
   * Used for the folder contents list in dialogs.
   *
   * The name must match the machine name of the "FolderShare Lists" view
   * and its "Dialog folder" display.
   *
   * @var string
   */
  const VIEW_DISPLAY_DIALOG_FOLDER = 'dialog_folder';

  /*--------------------------------------------------------------------
   *
   * Tokens.
   *
   *--------------------------------------------------------------------*/

  /**
   * The name of the token group for folder fields.
   *
   * The name should match FolderShare::ENTITY_TYPE_ID.
   *
   * @var string
   */
  const FOLDERSHARE_TOKENS = 'foldershare';

  /*--------------------------------------------------------------------
   *
   * Themes.
   *
   *--------------------------------------------------------------------*/

  /**
   * The folder page theme.
   *
   * This must match a theme name in the module's templates directory.
   * The theme file must be named:
   *  THEME_FOLDER . 'html.twig'.
   *
   * This name also must match the theme template function
   * template_preprocess_THEME_ROOT_LIST in MODULE.module.
   *
   * @var string
   */
  const THEME_FOLDER = 'foldershare';

  /**
   * The view (list) page theme.
   *
   * This must match a theme name in the module's templates directory.
   * The theme file must be named:
   *  THEME_VIEW . 'html.twig'.
   *
   * This name also must match the theme template function
   * template_preprocess_THEME_ROOT_LIST in MODULE.module.
   *
   * @var string
   */
  const THEME_VIEW = 'foldershare_view';

  /*--------------------------------------------------------------------
   *
   * Role-based permissions.
   *
   * These constants define well-known module permissions names. Their
   * values must match those in 'MODULE.permissions.yml'.
   *
   *--------------------------------------------------------------------*/

  /**
   * The module's administrative permission.
   *
   * Users may create, delete, and modify all content by any user, change
   * ownership, and adjust share settings. They cannot change module settings.
   *
   * This must much the administer permission in 'MODULE.permissions.yml'.
   *
   * @var string
   */
  const ADMINISTER_PERMISSION = 'administer foldershare';

  /**
   * The module's share with other users permission.
   *
   * Users may share root items with other specific users, granting them
   * view and/or author access.
   *
   * This must much the author permission in 'MODULE.permissions.yml'.
   *
   * @var string
   */
  const SHARE_PERMISSION = 'share foldershare';

  /**
   * The module's share with public permission.
   *
   * Users may share root items with the anonymous user, making the content
   * public and accessible by site visitors without accounts. Anonymous users
   * may only be granted view access.
   *
   * This must much the author permission in 'MODULE.permissions.yml'.
   *
   * @var string
   */
  const SHARE_PUBLIC_PERMISSION = 'share public foldershare';

  /**
   * The module's author permission.
   *
   * Users may create, delete, and modify their own content, and
   * content owned by others if they have been granted author access.
   *
   * This must much the author permission in 'MODULE.permissions.yml'.
   *
   * @var string
   */
  const AUTHOR_PERMISSION = 'author foldershare';

  /**
   * The module's view permission.
   *
   * Users may view their own content, and content owned by others if
   * granted view access.
   *
   * This must much the view permission in 'MODULE.permissions.yml'.
   *
   * @var string
   */
  const VIEW_PERMISSION = 'view foldershare';

  /*--------------------------------------------------------------------
   *
   * File and directory name lengths.
   *
   *--------------------------------------------------------------------*/

  /**
   * The number of base-10 digits of file ID used for directory and file names.
   *
   * Directory and file names are generated automatically based upon the
   * file entity IDs of stored files.
   *
   * The number of digits is typically 4, which supports 10,000 files
   * or subdirectories in each subdirectory.  Keeping this number of
   * items small improves performance for operations that must open and
   * read directories. But keeping it large reduces the directory
   * depth, which can slightly improve file path handling.
   *
   * Operating system file systems may be limited in the number of separate
   * files and directories that they can support. For 32-bit file systems,
   * this limit is around 2 billion total.
   *
   * The following examples illustrate URIs when varying this number from
   * 1 to 10 for a File entity 123,456 with a module file directory DIR in
   * the public file system:
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 1:
   *   - public://DIR/0/0/0/0/0/0/0/0/0/0/0/0/0/0/1/2/3/4/5/6
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 2:
   *   - public://DIR/00/00/00/00/00/00/00/12/34/56
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 3:
   *   - public://DIR/000/000/000/000/001/234/56
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 4:
   *   - public://DIR/0000/0000/0000/0012/3456
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 5:
   *   - public://DIR/00000/00000/00001/23456
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 6:
   *   - public://DIR/000000/000000/001234/56
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 7:
   *   - public://DIR/0000000/0000000/123456
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 8:
   *   - public://DIR/00000000/00000012/3456
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 9:
   *   - public://DIR/000000000/000001234/56
   *
   * - DIGITS_PER_SERVER_DIRECTORY_NAME = 10:
   *   - public://DIR/0000000000/0000123456
   *
   * @var int
   */
  const DIGITS_PER_SERVER_DIRECTORY_NAME = 4;

  /**
   * The name of the top-level folder into which the module places files.
   *
   * The folder is within the public or private file system, depending
   * upon which file system is selected by the site administrator using
   * the module's settings.
   *
   * @var string
   */
  const FILE_DIRECTORY = 'foldersharefiles';

  /*---------------------------------------------------------------------
   *
   * Work queue.
   *
   *---------------------------------------------------------------------*/

  /**
   * The name of the module's work queue for background tasks.
   *
   * The work queue is used for tasks that may take too long to be completed
   * in the process responding to a user's page request. In most cases, the
   * queued task is intentionally redundant - the task is executed in the
   * request process to TRY to complete it before the PHP or web server
   * timeout, and thereby give the user immediate feedback. But if a timeout
   * occurs, the queued task picks up where the immediate execution left off
   * and finishes the task. The queued task is therefore protection to insure
   * the task gets completed.
   *
   * The name must have the form "MODULE_name".
   *
   * The name must match the ID of the HandleWorkQueue plugin.
   */
  const WORK_QUEUE = 'foldershare_handle_work_queue';

  /*--------------------------------------------------------------------
   *
   * Module feature flags.
   *
   *--------------------------------------------------------------------*/

  /**
   * Indicates whether to try to extend the execution time for long tasks.
   *
   * <B>In normal use, this flag should always be TRUE.</B> The flag exists
   * only as an aid in debugging. When FALSE, it disables changing the PHP
   * timeout.
   *
   * Recursive operations on folders can take a long time if the folder
   * tree is large. When defering work, a background queue can handle
   * long-running tasks, but it is certainly preferred to handle the task
   * immediately and completely. However, executing the task immediately
   * runs the risk of hitting the PHP timeout.
   *
   * When this flag is set, operations that do long-running tasks try to
   * set the PHP timeout to "unlimited". This is not guaranteed to work
   * since some sites are configured to prevent this timeout from being
   * set by PHP code. Further, web servers also have timeouts and setting
   * the PHP timeout has no effect on those.
   *
   * Setting the timeout to "unlimited" is dangerous. It can allow infinite
   * loops to run a long time and waste resources. Also, once set it is
   * not possible to unset the timeout. So anything that runs
   * after a FolderShare operation could also run a long time.
   *
   * @var bool
   */
  const ENABLE_SET_PHP_TIMEOUT_UNLIMITED = TRUE;

  /*--------------------------------------------------------------------
   *
   * UI feature flags.
   *
   *--------------------------------------------------------------------*/

  /**
   * Indicates whether to include the command menu in the UI.
   *
   * The command menu lists command plugins (e.g. "Delete", "Copy", "Move")
   * in a pull-down menu attached to the toolbar above a folder table listing,
   * or stand-alone on a file page. Along with the command menu, Javascript
   * provides:
   * - Table row selection.
   * - Drag-and-drop on table rows for copy and move.
   * - Drag-and-drop of files into a table for upload.
   * - A file dialog for selecting files to upload.
   * - Dynamic enable/disable of commands based upon the selection.
   * - Command pre-validation before execution.
   *
   * When this flag is TRUE, the command menu is included. When FALSE,
   * it is not. Without the menu, however, no commands may be executed
   * and it is not possible to create folders, upload files, delete, copy,
   * move, etc.
   *
   * This flag is normally TRUE, but it can be set to FALSE to help
   * debug the user interface.
   *
   * @var bool
   */
  const ENABLE_UI_COMMAND_MENU = TRUE;

  /**
   * Indicates whether to include the ancestor menu in the UI.
   *
   * The ancestor menu lists links to ancestor folders, starting with the
   * current item and continuing up to its root folder and list of root
   * folders. The ancestor list is in a pull-down menu attached to the
   * toolbar above a folder table listing, or stand-alone on a file page.
   *
   * When this flag is TRUE, the ancestor menu is included. When FALSE,
   * it is not. Without the menu, however, navigating upwards through the
   * folder tree must be done using breadcrumb links (if included on a page),
   * the path pseudo-field (if included on a page), or by clicking on a root
   * list page link and navigating downwards only.
   *
   * This flag is normally TRUE, but it can be set to FALSE to help
   * debug the user interface.
   *
   * @var bool
   */
  const ENABLE_UI_ANCESTOR_MENU = TRUE;

  /**
   * Indicates whether to include the search box in the UI.
   *
   * The search UI includes a search box and a search submit button.
   * If the browser supports Javascript, the submit button is hidden and
   * a behavior on the search box triggers a search on a carriage return.
   * These widgets are attached to the toolbar above a folder table listing.
   * They are not present on a file page.
   *
   * The Drupal core Search module must be enabled, FolderShare's search
   * plugin must be available, and the current user must have the "Use search"
   * permission. If any of these are not true, the search UI widgets are
   * not included even if this flag is TRUE.
   *
   * When this flag is TRUE, these UI widgets are included. When FALSE,
   * they are not. Without these widgets, however, folder search can be
   * initiated only site-wide using a site's search features (if available).
   * Folder tree-specific search is not available.
   *
   * This flag is normally TRUE, but it can be set to FALSE to help
   * debug the user interface.
   *
   * @var bool
   */
  const ENABLE_UI_SEARCH_BOX = TRUE;

  /**
   * Indicates whether to enable commands to report normal completion.
   *
   * Drupal convention has every operation (add, delete, edit) report
   * its normal completion with a status message shown on the page (e.g.
   * "The item has been deleted."). This is largely redundant when
   * working with files and folders because the page shows the change
   * already (e.g. the deleted item is no longer in the folder list).
   * This is also not the convention with Windows, macOS, or Linux file
   * operations done with their respective UIs, so having a status message
   * output on every operation will feel very verbose to users.
   *
   * When this flag is TRUE, status messages are output. When FALSE,
   * they are suppressed and normal operation complete is silent.
   *
   * This flag is normally FALSE, but it can be set to TRUE to be more
   * verbose.
   *
   * @var bool
   */
  const ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION = FALSE;

  /**
   * Indicates whether to enable AJAX command dialogs in the UI.
   *
   * When TRUE, the user interface uses AJAX to enable on-page command forms
   * within dialog boxes. When FALSE, command forms are on separate pages,
   * instead of on-page dialogs.
   *
   * This flag is normally TRUE, but it can be set to FALSE to help
   * debug the user interface.
   *
   * @var bool
   */
  const ENABLE_UI_COMMAND_DIALOGS = TRUE;

}
