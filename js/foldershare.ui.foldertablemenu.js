/**
 * @file
 * Implements the FolderShare folder table menu user interface.
 *
 * The folder table menu UI presents a menu button and pull-down menu that
 * lists commands that operate upon folders and files of various types.
 * Commands are plugins to the FolderShare module and implement operations
 * such as creating a new folder, uploading files, deleting, copying, moving,
 * and downloading. The server attaches command descriptions used here to
 * build commands and pre-validate them before submitting them to the server.
 *
 * Most commands use a selection, so this script supports selecting rows in
 * a file/folder table. Rows can be selected individually or in groups.
 * Double-clicking a row opens the row's file or folder by advancing to its
 * page. Right-clicking on a row shows a context menu that shows a subset of
 * the main menu. Rows can be dragged and dropped onto subfolders to move
 * and copy, and files can be dragged from the host OS into the folder to
 * initiate a file upload.
 *
 * This script requires HTML elements added by a table view that uses a name
 * field formatter that attaches attributes to name field anchors. This script
 * uses those attributes to guide prevalidation of menu items as appropriate
 * for selected rows.
 *
 * This script also requires an HTML form that provides:
 * - A field to hold the current command choice.
 * - A set of fields holding command operands, including a parent ID,
 *   destination ID, and selection.
 * - A file field holding selected files for an upload.
 * - Drupal settings that list all known commands, and sundry other attributes.
 *
 * @ingroup foldershare
 * @see \Drupal\foldershare\Plugin\Field\FieldFormatter\FolderShareName
 * @see \Drupal\foldershare\Form\UIFolderTableMenu
 */
(function($, Drupal) {
  // Check pre-requisits.
  //
  // The utility library must have been loaded before this script.
  if ("foldershare" in Drupal === false ||
    "utility" in Drupal.foldershare === false) {
    console.log(
      "%cFolderShare: Javascript files included in wrong order%c\n%cfoldershare.ui.foldertablemenu.js requires that foldershare.ui.utility.js be included first.",
      "font-weight: bold",
      "font-weight: normal",
      "padding-left: 2em",
      "padding-left: 0");
    window.stop();
  }

  Drupal.foldershare.UIFolderTableMenu = {
    /*--------------------------------------------------------------------
     *
     * Constants - well-known commands.
     *
     *--------------------------------------------------------------------*/

    /**
     * The name of the module's standard file upload command.
     */
    uploadCommand: "foldersharecommand_upload_files",

    /**
     * The name of the module's standard entity copy command.
     */
    copyCommand: "foldersharecommand_copy",

    /**
     * The name of the module's standard entity move command.
     */
    moveCommand: "foldersharecommand_move",

    /*--------------------------------------------------------------------
     *
     * Constants - table attributes for drag state.
     *
     *--------------------------------------------------------------------*/

    /**
     * The table attribute created to track the current drag operand.
     *
     * Expected values are:
     * - "none" when no drag operation is in progress.
     * - "rows" when rows in the current table are being dragged.
     * - "files" when files are being dragged in from off browser.
     */
    tableDragOperand: "foldershare-drag-operand",

    /**
     * The table attribute created to track the current drag target.
     *
     * Expected values are:
     * - "none" when no drag operation is in progress.
     * - "rows" when rows in the current table are being dragged.
     * - "files" when files are being dragged in from off browser.
     */
    tableDropTarget: "foldershare-drop-target",

    /**
     * The table attribute created to track the current drag effects allowed.
     *
     * Expected values are any of those allowed for the "effectAllowed"
     * field of a drag event's data transfer. Values used here are:
     * - "none" when a drop is not allowed at this point.
     * - "copy" when only a copy is allowed at this point.
     * - "move" when only a copy is allowed at this point.
     * - "copyMove" when either a copy or a move is allowed at this point.
     */
    tableDragEffectAllowed: "foldershare-drag-effect-allowed",

    /**
     * The table attribute created to track the current drag row.
     *
     * Expected values are numeric row indexes (1 for the 1st row) or
     * "NaN" if there is no current row. The current row is the row most
     * recently under the cursor during a drag. When there is no drag in
     * progress, the value is "NaN".
     */
    tableDragRowIndex: "foldershare-drag-row-index",

    /*--------------------------------------------------------------------
     *
     * Constants - UI parameters.
     *
     *--------------------------------------------------------------------*/

    /**
     * The maximum number of menu items in a category before creating a
     * submenu.
     */
    maxCommandsBeforeSubmenu: 3,

    /*--------------------------------------------------------------------
     *
     * Initialize.
     *
     * The "environment" array created and updated by these functions
     * contains jQuery objects and assorted attributes gathered from the
     * page. Once gathered, these are passed among behavior functions so
     * that they can operate upon them without having to re-search for
     * them on the page.
     *
     *--------------------------------------------------------------------*/

    /**
     * Attaches the module's folder table menu UI behaviors.
     *
     * The folder table menu UI includes
     * - A command menu (e.g. open, new, upload, delete, etc.)
     * - A menu button used to present the command menu.
     * - Table row selection.
     * - Table row drag-and-drop for copy and move.
     * - File drag-and-drop for upload.
     * - A file dialog for upload.
     *
     * All UI elements and related elements are found, validated,
     * and behaviors attached.
     *
     * @param {Document} pageContext
     *   The page context for this call. Initially, this is the full document.
     *   Later, this is only portions of the document added via AJAX.
     * @param {objecT} settings
     *   The top-level Drupal settings object.
     *
     * @return {boolean}
     *   Always returns true.
     */
    attach(pageContext, settings) {
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      //
      // Test and exit
      // -------------
      // This method is called very frequently. It is called at least once
      // when the document is ready, and then again every time AJAX adds
      // anything to the page for any reason. This includes additions that
      // have nothing to do with this module.
      //
      // It is therefore important that this method decide quickly if the
      // context is not relevant for it, then return.
      //
      // Find top element
      // ----------------
      // The page structure contains a toolbar and table wrapper <div>,
      // then two child <div>s containing the toolbar and table:
      //
      // <div class="foldershare-toolbar-and-folder-table">
      //   <div class="foldershare-toolbar">...</div>
      //   <div class="foldershare-folder-table">...</div>
      // </div>
      //
      // The toolbar <div> contains zero or more other UI components. This
      // script adds a new menu button to the start of the toolbar.
      //
      // The table <div> contains a wrapper <div> from Views. It includes
      // two <div>s for the view's content and footer. The view content
      // contains a wrapper <div> around a <form>. And the form contains
      // exposed filters and other views-supplied form elements, and
      // finally it includes a <table> that contains rows of files and
      // folders.
      //
      // <div class="foldershare-toolbar-and-folder-table">   Top of UI
      //   <div class="foldershare-toolbar">...</div>         Toolbar
      //   <div class="foldershare-folder-table">             Table wrapper
      //     <div class="view">
      //       <div class="view-content">
      //         <table>...</table>                           Table
      //       </div>
      //       <nav class="pager">...</nav>                   View pager
      //       <div class="view-footer">...</div>             Table footer
      //     </div>
      //   </div>
      // </div>
      //
      // However, because the view can be themed, there may be more <div>s
      // added by some themes. Bootstrap-based themes, for instance, add
      // a <div> that nests the <table> within a view's <form>.
      //
      // If a view has AJAX and a pager enabled, then a page next/prev for
      // the view uses AJAX to replace the <div> with class "view" and all
      // of its content with a new page. This also replaces the inner <form>
      // and <table>. And both of those contain elements we need for behaviors.
      //
      const topSelector = ".foldershare-toolbar-and-folder-table";
      let $topElements;

      if (typeof pageContext.tagName === "undefined") {
        // The page context is for the full document. Search down
        // through the document to find the top element(s).
        $topElements = $(topSelector, pageContext);
        if ($topElements.length === 0) {
          // Fail. The document does not contain a top element.
          return true;
        }
      } else {
        // The page context is for a portion of the document. Search up
        // towards the document root fo find the top element.
        $topElements = $(pageContext).parents(topSelector).eq(0);
        if ($topElements.length === 0) {
          // Fail. The page context does not contain a top element.
          return true;
        }
      }

      //
      // Process top elements
      // --------------------
      // Process each top element. Usually there will be only one.
      const envList = [];
      $topElements.each((index, element) => {
        // Create a new environment object.
        const env = {
          settings: settings,
          $topElement: $(element)
        };

        //
        // Gather configuration
        // --------------------
        // Find forms, form elements, and the table of files and folders.
        if (thisScript.gather(env) === false) {
          // Fail. UI elements could not be found.
          return true;
        }

        //
        // Gather commands
        // ---------------
        // The UI requires a list of file/folder commands installed on the
        // server, and their attributes.
        //
        // Find the full set of commands, cull them down to those
        // available to this user on this page, then categorize them.
        // Build toolbar and context menu command lists.
        thisScript.gatherCommands(env);

        // Check if drag-and-drop is supported.
        thisScript.checkCommandDragAndDropSupport(env);

        //
        // Build UI
        // --------
        // Build the UI, including its menu, and attach its behaviors.
        if (thisScript.build(env) === false) {
          // Fail. Something didn't work
          return true;
        }

        // Show UI, now that it has been fully built and set up.
        env.gather.$subform.removeClass("hidden");

        envList.push(env);
      });

      return true;
    },

    /**
     * Gathers UI elements.
     *
     * Pages that add the UI place it within top element <div>
     * for the UI. Nested within is a <form> that contains the UI's
     * elements. The principal elements are:
     * - Multiple input fields for the command and its parameters, including
     *   the IDs of the current selection, parent, and destination, and
     *   a file upload field.
     * - An <input> to submit the command form.
     *
     * This function searches for the UI's elements and saves them
     * into the environment:
     * - env.gather.$form = the <form> containing the UI.
     * - env.gather.$subform = the <div> within <form> containing the UI.
     * - env.gather.$table = the <table> containing the file/folder list.
     * - env.gather.$uploadInput = the file upload <input>.
     * - env.gather.$commandInput = the command ID <input>.
     * - env.gather.$selectionIdInput = the selection <input>.
     * - env.gather.$parentIdInput = the parent ID <input>.
     * - env.gather.$destinationIdInput = the destination ID <input>.
     * - env.gather.$commandSubmitButton = the button for submitting the form.
     * - env.gather.nameColumn = the table column name for the name & attrib.
     *
     * @param {object} env
     *   The environment object containing saved object references for
     *   elements to operate upon. The object is updated on success.
     *
     * @return {boolean}
     *   Returns TRUE on success and FALSE otherwise.
     */
    gather(env) {
      const utility = Drupal.foldershare.utility;
      const base = "foldershare-folder-table";
      const nameColumn = "views-field-name";

      //
      // Find form
      // ---------
      // From the top element wrapping the forms and view, search downward
      // for the <form> wrapping the main UI's elements.
      //
      //   <div class="foldershare-toolbar-and-folder-table ...">
      //     ...
      //     ... <form class="foldershare-folder-table-menu-form">
      //          ...
      //     ... </form>
      //     ...
      //   </div>
      //
      // Nesting may add intermediate <div>s throughout.
      let cls = `${base}-menu-form`;
      const $commandForm = $(`.${cls}`, env.$topElement).eq(0);
      if ($commandForm.length === 0) {
        utility.printMalformedError(
          `The required <form> with class '${cls}' could not be found.`);
        return false;
      }

      //
      // Find main UI's subform
      // --------------------------
      // Within the <form>, the main UI wraps its form elements within
      // a <div>. The <div> is important because the same <div> has data
      // attributes attached that describe the parent entity, access
      // permissions, etc.
      cls = `${base}-menu`;
      const $subform = $(`.${cls}`, $commandForm).eq(0);
      if ($subform.length === 0) {
        utility.printMalformedError(
          `The required <div> with class '${cls}' could not be found.`);
        return false;
      }

      //
      // Find submit
      // -----------
      // Normally, Drupal creates an <input> for submit buttons,
      // but some themes (such as Bootstrap) convert these to <button>.
      // Functionally, these are similar but it means we have to look
      // for either type.
      let $commandSubmitButton = $('input[type="submit"]', $commandForm).eq(0);
      if ($commandSubmitButton.length === 0) {
        $commandSubmitButton = $('button[type="submit"]', $commandForm).eq(0);
        if ($commandSubmitButton.length === 0) {
          utility.printMalformedError(
            "A submit <input> or <button> could not be found.");
          return false;
        }
      }

      //
      // Find inputs
      // -----------
      // The form contains several <input> items used to hold information
      // from the UI operation:
      // - A file upload <input>.
      // - A command ID <input>.
      // - A selection IDs <input>.
      // - A destination ID <input>.
      // - A parent ID <input>.
      //
      // The upload field's name uses special [] array syntax
      // imposed by the Drupal file module.
      const $uploadInput = $(
        'input[name="files[foldershare-folder-table-menu-upload][]"]',
        $commandForm).eq(0);
      if ($uploadInput.length === 0) {
        utility.printMalformedError("The main UI file input field is missing.");
        return false;
      }

      const $commandInput = $(
        'input[name="foldershare-folder-table-menu-commandname"]',
        $commandForm).eq(0);
      if ($commandInput.length === 0) {
        utility.printMalformedError("The main UI command ID field is missing.");
        return false;
      }

      const $selectionIdInput = $(
        'input[name="foldershare-folder-table-menu-selection"]',
        $commandForm).eq(0);
      if ($selectionIdInput.length === 0) {
        utility.printMalformedError(
          "The main UI selection IDs field is missing.");
        return false;
      }

      const $destinationIdInput = $(
        'input[name="foldershare-folder-table-menu-destinationId"]',
        $commandForm).eq(0);
      if ($destinationIdInput.length === 0) {
        utility.printMalformedError(
          "The main UI destination ID field is missing.");
        return false;
      }

      const $parentIdInput = $(
        'input[name="foldershare-folder-table-menu-parentId"]',
        $commandForm).eq(0);
      if ($parentIdInput.length === 0) {
        utility.printMalformedError("The main UI parent ID field is missing.");
        return false;
      }

      //
      // Find table
      // ----------
      // Search for a views <table>. We cannot count on a class name for the table,
      // though it is often views-table, because themes have different templates.
      const $table = $(`table`, env.$topElement).eq(0);
      if ($table.length === 0) {
        utility.printMalformedError(
          `The required <table> with class '${cls}' could not be found.`);
        return false;
      }

      //
      // Update environment
      // ------------------
      // Save main UI objects.
      env.gather = {
        $commandForm: $commandForm,
        $subform: $subform,
        $table: $table,
        $tbody: $table.find("tbody"),
        $thead: $table.find("thead"),
        nameColumn: nameColumn,
        $uploadInput: $uploadInput,
        $commandInput: $commandInput,
        $selectionIdInput: $selectionIdInput,
        $destinationIdInput: $destinationIdInput,
        $parentIdInput: $parentIdInput,
        $commandSubmitButton: $commandSubmitButton
      };

      return true;
    },

    /**
     * Gathers a list of file/folder commands for use in main & context menus.
     *
     * A full list of file/folder commands installed for the Drupal module
     * is included in the DrupalSettings saved in the given environment.
     * This list is culled here into two lists:
     * - Commands for the toolbar menu.
     * - Commands for the row context menu (e.g. via a right-click).
     *
     * Commands are culled if:
     * - The page is disabled.
     * - The page's entity kind is not supported by the command.
     * - The command requires a selection but the page entity has no children.
     * - The user doesn't have necessary permissions on the parent.
     * - The user doesn't have necessary permissions on selections.
     * - The user doesn't have necessary permissions for special handling.
     *
     * The context menu command list also culls commands if:
     * - They never use a selection.
     * - They require upload or create special handling.
     *
     * Commands are then grouped by their named category. Category names may
     * be anything, but a set of well-known categories are defined by the
     * module and passed in as settings to this script. A typical
     * well-known category list includes:
     * - open
     * - import
     * - export
     * - close
     * - edit
     * - delete
     * - copymove
     * - save
     * - archive
     * - settings
     * - administer
     *
     * Unknown categories are added to the end. Any category may be empty.
     *
     * This function saves two command list objects to the environment.
     * Each object has one property for each supported command:
     * - env.mainCommands = the list of supported main menu commands.
     * - env.contextCommands = the list of supported context menu commands.
     *
     * For a command with ID 'abc', the definition is available at
     * env.mainCommands['abc'] or env.contextCommands['abc'] if it is
     * available for the main menu or context menu.
     *
     * Categorized main menu and context menu command lists are created and
     * added to the environment. Each list is an array, sorted by category
     * name. The value contains the name and an array of commands in the
     * category, also sorted by name. The commands are referred to by
     * command ID.
     * - env.mainCategories = the sorted list of categories and commands.
     * - env.contextCategories = the sorted list of categories and commands.
     *
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Always returns true.
     */
    gatherCommands(env) {
      //
      // Cull commands
      // -------------
      // Cull the full command list into command lists for the main menu
      // and context menu. Commands are culled if:
      // - The page's entity kind is not supported by the command.
      // - The command requires a selection but the page entity has no children.
      // - The user doesn't have necessary permissions on the parent.
      // - The user doesn't have necessary permissions on selections.
      // - The user doesn't have necessary permissions for special handling.
      //
      // The context menu command list also culls commands if:
      // - They never use a selection.
      // - They require upload or create special handling.
      //
      // The mainCommands and contextCommands objects have one field per
      // command so that command information may be quickly looked up by
      // command ID.
      const mainCommands = Object.create(null);
      const contextCommands = Object.create(null);

      const allCommands = env.settings.foldershare.commands;
      const pageEntityKind = env.settings.foldershare.page.kind;
      const pageAccess = env.settings.foldershare.user.pageAccess;
      const pageDisabled = env.settings.foldershare.page.disabled;

      // Loop through all commands and cull them into main and context
      // menu lists.
      Object.keys(allCommands).forEach(commandId => {
        const def = allCommands[commandId];

        //
        // Parent (page) disabled.
        // -----------------------
        // If a page's entity is disabled, none of the commands are valid.
        // Being disabled is normally a temporary state while a long-running
        // operation is updating the entity. It is not safe to do any new
        // operations until that one finishes and the entity is re-enabled.
        if (pageDisabled === true) {
          return;
        }

        //
        // Current user suitable for command.
        // ----------------------------------
        // Check that the command's user requirements are met.
        const allowedUsers = def.userConstraints;

        let userOk = false;
        if (allowedUsers.includes("any") === true) {
          userOk = true;
        } else {
          allowedUsers.forEach((value) => {
            if (env.settings.foldershare.user[value] === true) {
              userOk = true;
            }
          });
        }

        if (userOk === false) {
          // Fail. User is not suitable for this command.
          return;
        }

        //
        // Parent kind suitable.
        // ---------------------
        // Check that the command's kind requirements are met.
        const allowedParentKinds = def.parentConstraints.kinds;

        if (allowedParentKinds.includes("any") === false &&
          allowedParentKinds.includes(pageEntityKind) === false) {
          // Fail. Parent's kind is not suitable for this command.
          return;
        }

        //
        // Parent access suitable.
        // -----------------------
        // Check that the command's parent access requirements are met.
        const allowedParentAccess = def.parentConstraints.access;

        if (pageAccess.includes(allowedParentAccess) === false) {
          // Fail. The command requires special permission for accessing
          // the parent but the user does not have it.
          return;
        }

        //
        // Parent ownership suitable.
        // --------------------------
        // Check that the command's parent ownership requirements are met.
        const allowedParentOwnership = def.parentConstraints.ownership;

        let parentOwnershipOk = false;
        if (allowedParentOwnership.includes("any") === true) {
          parentOwnershipOk = true;
        } else {
          allowedParentOwnership.forEach((value) => {
            if (env.settings.foldershare.page[value] === true) {
              parentOwnershipOk = true;
            }
          });
        }

        if (parentOwnershipOk === false) {
          // Fail. Parent ownership is not suitable for this command.
          return;
        }

        //
        // Selection type (size) suitable.
        // -------------------------------
        // Check that the command's selection size requirements are met.
        //
        // The special 'parent' value means that when there is no selection,
        // the command can default to the page's parent entity.
        //
        // If a command requires a selection, then the page entity kind
        // must be a folder or rootlist because other kinds aren't shown
        // with a list of selectable items.
        const allowedSelectionTypes = def.selectionConstraints.types;

        let selectable = false;
        switch (pageEntityKind) {
          case "rootlist":
          case "folder":
            selectable = true;
            break;

          default:
            break;
        }

        if (selectable === false &&
          allowedSelectionTypes.includes("none") === false &&
          allowedSelectionTypes.includes("parent") === false) {
          // Fail. The page's kind is not a folder, yet the command does not
          // support having no selection or reverting to the parent. The
          // command therefore always requires a selection, and yet no
          // selection is possible on this page.
          return;
        }

        //
        // Selection access suitable.
        // --------------------------
        // Check that the command's selection access requirements are met
        // by the current page.
        const allowedSelectionAccess = def.selectionConstraints.access;

        if (allowedSelectionAccess !== "none" &&
          pageAccess.includes(allowedSelectionAccess) === false) {
          // Fail. The command requires special permission for accessing
          // the selection but the user does not have it.
          return;
        }

        //
        // Main menu command.
        // ------------------
        // For the main menu, the above culling is sufficient. Commands
        // will operate on either the page entity (parent) or a selection
        // of one or more children (if available).
        mainCommands[commandId] = def;

        //
        // Context menu command.
        // ---------------------
        // For the context menu, there is always a selection (the row(s) the
        // menu is shown for). Further culling is needed to remove:
        // - Commands that do not use a selection.
        // - Commands that upload.
        let cullContext = false;
        if (def.specialHandling.includes("upload") === true) {
          cullContext = true;
        }
        if (allowedSelectionTypes.includes("one") === false &&
            allowedSelectionTypes.includes("many") === false) {
          cullContext = true;
        }

        if (cullContext === false) {
          contextCommands[commandId] = def;
        }
      });

      // Save the command lists back to the environment. It is possible
      // for both command lists to be empty if none of the above commands
      // met page criteria.
      env.mainCommands = mainCommands;
      env.contextCommands = contextCommands;

      //
      // Get well-known categories.
      // --------------------------
      // Start with a list of well-known categories and add them
      // to the category list for the main and context menus.
      let mainCategories = new Map();
      let contextCategories = new Map();

      const categoryTerms = env.settings.foldershare.terminology.categories;

      Object.keys(env.settings.foldershare.categories).forEach(key => {
        const cat = env.settings.foldershare.categories[key];

        // Get the translated term, if any.
        let term = cat;
        if (categoryTerms.hasOwnProperty(cat) === true) {
          term = categoryTerms[cat];
        }

        // Add the category.
        mainCategories.set(cat, {
          name: term,
          commandIds: []
        });
        contextCategories.set(cat, {
          name: term,
          commandIds: []
        });
      });

      //
      // Categorize commands.
      // --------------------
      // Loop through all commands and add them to categories. If a command
      // uses an unrecognized category, add it to a separate category list.
      const extraMainCategories = new Map();
      const extraContextCategories = new Map();

      Object.keys(env.mainCommands).forEach(commandId => {
        const def = env.mainCommands[commandId];
        const cat = def.category;

        if (mainCategories.has(cat) === true) {
          mainCategories.get(cat).commandIds.push(commandId);
        } else if (extraMainCategories.has(cat) === true) {
          extraMainCategories.get(cat).commandIds.push(commandId);
        } else {
          // Get the translated term, if any.
          let term = cat;
          if (categoryTerms.hasOwnProperty(cat) === true) {
            term = categoryTerms[cat];
          }

          // Convert to title-case.
          term = term.charAt(0).toUpperCase() + term.substr(1).toLowerCase();

          extraMainCategories.set(cat, {
            name: term,
            commandIds: [commandId]
          });
        }
      });

      Object.keys(env.contextCommands).forEach(commandId => {
        const def = env.contextCommands[commandId];
        const cat = def.category;

        if (contextCategories.has(cat) === true) {
          contextCategories.get(cat).commandIds.push(commandId);
        } else if (extraContextCategories.has(cat) === true) {
          extraContextCategories.get(cat).commandIds.push(commandId);
        } else {
          // Get the translated term, if any.
          let term = cat;
          if (categoryTerms.hasOwnProperty(cat) === true) {
            term = categoryTerms[cat];
          }

          // Convert to title-case.
          term = term.charAt(0).toUpperCase() + term.substr(1).toLowerCase();

          extraContextCategories.set(cat, {
            name: term,
            commandIds: [commandId]
          });
        }
      });

      //
      // Sort commands by weight and name.
      // ---------------------------------
      // Loop through all of the categories for main and context menus and:
      // - Remove empty categories.
      // - Sort category commands by their weight and name.
      const mainSortFunction = (a, b) => {
        const adef = env.mainCommands[a];
        const bdef = env.mainCommands[b];

        const diff = Number(adef.weight) - Number(bdef.weight);
        if (diff !== 0) {
          return diff;
        }
        if (adef.menuNameDefault < bdef.menuNameDefault) {
          return -1;
        }
        if (adef.menuNameDefault > bdef.menuNameDefault) {
          return 1;
        }
        return 0;
      };

      const contextSortFunction = (a, b) => {
        const adef = env.contextCommands[a];
        const bdef = env.contextCommands[b];

        const diff = Number(adef.weight) - Number(bdef.weight);
        if (diff !== 0) {
          return diff;
        }
        if (adef.menuNameDefault < bdef.menuNameDefault) {
          return -1;
        }
        if (adef.menuNameDefault > bdef.menuNameDefault) {
          return 1;
        }
        return 0;
      };

      // Copy non-empty categories, sorting each one by command name.
      let tmp = new Map();
      mainCategories.forEach((value, cat) => {
        if (mainCategories.get(cat).commandIds.length !== 0) {
          tmp.set(cat, mainCategories.get(cat));
          tmp.get(cat).commandIds.sort(mainSortFunction);
        }
      });
      mainCategories = tmp;

      extraMainCategories.forEach((value, cat) => {
        extraMainCategories.get(cat).commandIds.sort(mainSortFunction);
      });

      // Copy non-empty categories, sorting each one by command name.
      tmp = new Map();
      contextCategories.forEach((value, cat) => {
        if (contextCategories.get(cat).commandIds.length !== 0) {
          tmp.set(cat, contextCategories.get(cat));
          tmp.get(cat).commandIds.sort(contextSortFunction);
        }
      });
      contextCategories = tmp;

      extraContextCategories.forEach((value, cat) => {
        extraContextCategories.get(cat).commandIds.sort(contextSortFunction);
      });

      //
      // Sort and add extra categories by name.
      // --------------------------------------
      if (extraMainCategories.size !== 0) {
        // Append extras.
        extraMainCategories.forEach((value, cat) => {
          mainCategories.set(cat, extraMainCategories.get(cat));
        });
      }

      if (extraContextCategories.size !== 0) {
        // Append extras.
        extraContextCategories.forEach((value, cat) => {
          contextCategories.set(cat, extraContextCategories.get(cat));
        });
      }

      // Save the categorized lists back to the environment.
      env.mainCategories = mainCategories;
      env.contextCategories = contextCategories;

      return true;
    },

    /*--------------------------------------------------------------------
     *
     * Build the folder table menu UI.
     *
     *--------------------------------------------------------------------*/

    /**
     * Builds the UI.
     *
     * The main UI has several features:
     * - A hierarchical main menu that pops up from a menu button.
     * - A context menu that pops up from a right-click on a row.
     * - Selectable rows in the view table.
     * - Drag-and-drop of selected rows to a subfolder.
     * - Drag-and-drop from the host into the table to do an upload.
     *
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns TRUE on success and FALSE otherwise.
     */
    build(env) {
      const thisScript = Drupal.foldershare.UIFolderTableMenu;
      const pageDisabled = env.settings.foldershare.page.disabled;

      //
      // Create main menu button
      // -----------------------
      // Create the main menu button and append it to the command subform.
      // If there is a button already there, remove it first.
      let buttonClasses = "foldershare-folder-table-mainmenu-button";
      if (pageDisabled === true) {
        buttonClasses += " foldershare-folder-table-mainmenu-button-disabled";
      }

      $(".foldershare-folder-table-mainmenu-button",
        env.gather.$subform).remove();
      const menuTerm = Drupal.foldershare.utility.getTerm(
        env.settings.foldershare.terminology, "menu");
      env.gather.$subform.prepend(
        `<button type="button" class="${buttonClasses}"><span>${menuTerm}</span></button>`);
      const $menuButton = $(".foldershare-folder-table-mainmenu-button",
        env.gather.$subform);
      $menuButton.button().show();
      if (pageDisabled === true) {
        $menuButton.button("disable");
      }

      //
      // Create main menu
      // ----------------
      // Create the main menu HTML and append it to the command subform.
      // If there is a menu already there, remove it first.
      $(".foldershare-folder-table-mainmenu", env.gather.$subform).remove();
      env.gather.$subform.append(thisScript.buildMainMenu(env));
      const $menu = $(".foldershare-folder-table-mainmenu",
        env.gather.$subform);
      $menu.menu().hide();
      $menu.removeClass("hidden");
      if (pageDisabled === true) {
        $menu.menu("disable");
      }

      // Disable the browser's context menu on the menu itself.
      $menu.on("contextmenu.foldershare", function(ev) {
        return false;
      });

      //
      // Create context menu
      // -------------------
      // Create the context menu HTML and append it to the command subform.
      // If there is a menu already there, remove it first.
      $(".foldershare-folder-table-contextmenu", env.gather.$subform).remove();
      env.gather.$subform.append(thisScript.buildContextMenu(env));
      const $contextMenu = $(".foldershare-folder-table-contextmenu",
        env.gather.$subform);
      $contextMenu.menu().hide();
      $contextMenu.removeClass("hidden");
      if (pageDisabled === true) {
        $contextMenu.menu("disable");
      }

      // Disable the browser's context menu on the context menu itself.
      $contextMenu.on("contextmenu.foldershare", function(ev) {
        return false;
      });

      //
      // Attach main menu button behavior
      // --------------------------------
      // When the main menu button is pressed, show the main menu.
      // When the menu is about to be shown, update all menu items to
      // enable/disable and adjust the text to reflect the selection.
      $menuButton.off("click.foldershare");
      if (pageDisabled !== true) {
        $menuButton.on("click.foldershare", ev => {
          if ($menu.menu().is(":visible")) {
            // When the menu is already visible, hide it.
            $menu.menu().hide();
          } else {
            // Update the menu's text based on the selection.
            thisScript.menuUpdate(env, $menu);

            // Position the menu and show it.
            $menu.show().position({
              my: "left top",
              at: "left bottom",
              of: ev.target,
              collision: "fit"
            });

            // Register a handler to catch an off-menu click to hide it.
            $(document).on("click.foldershare", () => {
              $menu.menu().hide();
            });
          }

          return false;
        });
      }

      //
      // Attach main menu item behavior
      // ------------------------------
      // When a menu item is selected, trigger the command.
      $menu.off("menuselect.foldershare");
      if (pageDisabled !== true) {
        $menu.on("menuselect.foldershare", (ev, ui) => {
          // Insure the menu is hidden.
          $menu.menu().hide();

          // Fill the server form.
          const command = $(ui.item).attr("data-foldershare-command");
          thisScript.serverCommandSetup(
            env,
            command,
            null,
            null,
            thisScript.tableGetSelectionIds(env),
            null);

          const specialHandling = env.mainCommands[command].specialHandling;
          if ($.inArray("upload", specialHandling) !== -1) {
            // Show file dialog.
            env.gather.$uploadInput.click();
          } else {
            // Submit form.
            thisScript.serverCommandSubmit(env);
          }

          return true;
        });
      }

      //
      // Attach context menu item behavior
      // ---------------------------------
      // When a menu item is selected, trigger the command.
      $contextMenu.off("menuselect.foldershare");
      if (pageDisabled !== true) {
        $contextMenu.on("menuselect.foldershare", (ev, ui) => {
          // Insure the menu is hidden.
          $contextMenu.menu().hide();

          // Fill the server form.
          const command = $(ui.item).attr("data-foldershare-command");
          thisScript.serverCommandSetup(
            env,
            command,
            null,
            null,
            thisScript.tableGetSelectionIds(env),
            null);

          const specialHandling = env.contextCommands[command].specialHandling;
          if ($.inArray("upload", specialHandling) !== -1) {
            // Show file dialog.
            env.gather.$uploadInput.click();
          } else {
            // Submit form.
            thisScript.serverCommandSubmit(env);
          }

          return true;
        });
      }

      //
      // Attach upload behavior
      // ----------------------
      // When a file dialog is closed, and there is a file selection,
      // trigger an upload command.
      env.gather.$uploadInput.off("change.foldershare");
      if (pageDisabled !== true) {
        env.gather.$uploadInput.on("change.foldershare", () => {
          // When called, the upload field's file list has already been
          // set via the browser's file dialog. The other fields of the
          // command form were set up when the menu command was selected.
          thisScript.serverCommandSubmit(env);
        });
      }

      //
      // Add table behaviors
      // -------------------
      // Add table and table row behaviors, such as for row selection,
      // drag-and-drop, and the context menu.
      if (pageDisabled !== true) {
        thisScript.tableAttachBehaviors(env);
      }

      return true;
    },

    /**
     * Builds the <ul> for the main menu.
     *
     * The list of commands suitable for the user and page is used to
     * create HTML containing a nested <ul> list. Each <li> in the list
     * is either an available command or the name of a submenu.
     *
     * @param {object} env
     *   The environment object.
     *
     * @return {string}
     *   Returns HTML for the main menu.
     */
    buildMainMenu(env) {
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      // Start the <ul>.
      let html = '<ul class="hidden foldershare-folder-table-mainmenu">';

      // Loop through all main menu categories.
      const maxBeforeSub = thisScript.maxCommandsBeforeSubmenu;
      let addSeparator = false;
      env.mainCategories.forEach((value, cat) => {
        // Add a separator before the next category of commands.
        if (addSeparator === true) {
          html += "<li>-</li>";
        }
        addSeparator = true;

        // Create a submenu if the category is large enough.
        let addSubmenu = false;
        if (env.mainCategories.get(cat).commandIds.length > maxBeforeSub) {
          html += `<li><div>${value.name}</div><ul>`;
          addSubmenu = true;
        }

        // Add the category's commands.
        Object.keys(env.mainCategories.get(cat).commandIds).forEach(key => {
          const commandId = env.mainCategories.get(cat).commandIds[key];
          const label = env.mainCommands[commandId].menuNameDefault;
          html += `<li data-foldershare-command="${commandId}"><div>${label}</div></li>`;
        });

        if (addSubmenu === true) {
          html += "</ul></li>";
        }
      });

      html += "</ul>";

      return html;
    },

    /**
     * Builds the <ul> for the context menu.
     *
     * The list of commands suitable for the user and page is used to
     * create HTML containing a nested <ul> list. Each <li> in the list
     * is either an available command or the name of a submenu.
     *
     * @param {object} env
     *   The environment object.
     *
     * @return {string}
     *   Returns HTML for the context menu.
     */
    buildContextMenu(env) {
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      // Start the <ul>.
      let html = '<ul class="hidden foldershare-folder-table-contextmenu">';

      // Loop through all context menu categories.
      const maxBeforeSub = thisScript.maxCommandsBeforeSubmenu;
      let addSeparator = false;
      env.contextCategories.forEach((value, cat) => {
        // Add a separator before the next category of commands.
        if (addSeparator === true) {
          html += "<li>-</li>";
        }
        addSeparator = true;

        // Create a submenu if the category is large enough.
        let addSubmenu = false;
        if (env.contextCategories.get(cat).commandIds.length > maxBeforeSub) {
          html += `<li><div>${value.name}</div><ul>`;
          addSubmenu = true;
        }

        // Add the category's commands.
        Object.keys(env.contextCategories.get(cat).commandIds).forEach(key => {
          const commandId = env.contextCategories.get(cat).commandIds[key];
          const label = env.contextCommands[commandId].menuNameDefault;
          html += `<li data-foldershare-command="${commandId}"><div>${label}</div></li>`;
        });

        if (addSubmenu === true) {
          html += "</ul></li>";
        }
      });

      html += "</ul>";

      return html;
    },

    /*--------------------------------------------------------------------
     *
     * Menu.
     *
     *--------------------------------------------------------------------*/

    /**
     * Updates a menu to enable/disable commands based on the selection.
     *
     * For each menu item, the selection constraints of the associated
     * command are checked against the given selection. If the constraints
     * are not met, the menu item is disabled and its text is set to generic
     * menu item text (e.g. "Delete"). If the constraints are met, the item
     * is enabled and its text is set appropriate for the selection (e.g.
     * "Delete Folder").
     *
     * @param {object} env
     *   The environment object.
     * @param {object} $menu
     *   The menu.
     */
    menuUpdate(env, $menu) {
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      // Count the number of selected items. There could be zero.
      const selection = thisScript.tableGetSelectionIdsByKind(env);
      let nSelected = 0;
      Object.keys(selection).forEach(kind => {
        nSelected += selection[kind].length;
      });

      // Get operand text describing the selection. This text may be
      // inserted into menu item labels.
      const operand = thisScript.menuGetOperandText(env, selection);

      // Loop through the menu and enable items that are suitable for the
      // current selection, and disable those that are not.
      $(".ui-menu-item", $menu).each((index, value) => {
        const $item = $(value);

        // Skip irrelevant menu items.
        if ($item.hasClass("ui-menu-divider") === true) {
          // Skip. Ignore separators.
          return true;
        }

        if ($item.hasClass("ui-state-broken") === true) {
          // Skip. Ignore broken menu items.
          return true;
        }

        // Get the menu item's command ID.
        const commandId = $item.attr("data-foldershare-command");
        if (typeof commandId === "undefined") {
          // Fail. Malformed menu item!
          return true;
        }

        if (commandId in env.settings.foldershare.commands === false) {
          // Fail. Unknown command. Mark it broken.
          $item.removeClass("ui-state-enabled");
          $item.addClass("ui-state-disabled");
          $item.addClass("ui-state-broken");
          return true;
        }

        // Validate the selection against the command's constraints.
        let text = "";
        if (thisScript.checkSelectionConstraints(
          env,
          nSelected,
          selection,
          commandId) === false) {
          // The command is not enabled in this context. Perhaps the
          // selection doesn't match what the command needs, or the
          // access permissions aren't right.
          //
          // In any case, we need to:
          // - Mark the menu item as disabled.
          // - Make its menu text generic.
          $item.removeClass("ui-state-enabled");
          $item.addClass("ui-state-disabled");

          // Generic text is encoded as an attribute on the menu item.
          // Get it and replace the user-visible text with that generic text.
          text = env.settings.foldershare.commands[commandId].menuNameDefault;
        } else {
          // The command is enabled in this context. The selection must
          // have satisfied the command's constraints, or perhaps it doesn't
          // need a selection.
          //
          // In any case, we need to:
          // - Mark the command as enabled.
          // - Make its menu text specific to the selection.
          $item.removeClass("ui-state-disabled");
          $item.addClass("ui-state-enabled");

          // Specific menu text, with a '@operand' placeholder, is encoded
          // as an attribute on the menu item. Get it, substitute '@operand'
          // with a suitable comment on the selection, then replace the
          // user-visible text of the menu item with the new text.
          text = env.settings.foldershare.commands[commandId].menuName;
          text = text.replace("@operand", operand);
        }

        $("div", $item).text(text);
      });
    },

    /**
     * Returns operand text based on the current selection.
     *
     * Operand text briefly describes the current selection. The text is
     * suitable for embedding within a menu item's name.
     *
     * @param {object} env
     *   The environment object.
     * @param {object} selection
     *   The current selection.
     *
     * @return {string}
     *   Returns text describing the current selection.
     */
    menuGetOperandText(env, selection) {
      //
      // Scan selection
      // --------------
      // Get the selection, which may be empty, then scan it to collect
      // information characterizing it:
      // - The total number of selected items.
      // - The total number of different kinds of selected items.
      // - The kind, if there is only one in use.
      let nSelected = 0;
      let nKinds = 0;
      let kind = "";

      Object.keys(selection).forEach(k => {
        const len = selection[k].length;
        nSelected += len;
        if (len > 0) {
          nKinds++;
          kind = k;
        }
      });

      const terminology = env.settings.foldershare.terminology;

      //
      // No selection case
      // -----------------
      // When there is no selection, use the kind of the page entity.
      if (nSelected === 0) {
        kind = env.settings.foldershare.page.kind;
        const term = Drupal.foldershare.utility.getTerm(terminology, "this", false);
        const singularKind = Drupal.foldershare.utility.getKindSingular(terminology, kind);
        return `${term} ${singularKind}`;
      }

      //
      // Single selection
      // ----------------
      // When there is just one item selected, use the kind of the selection.
      if (nSelected === 1) {
        return Drupal.foldershare.utility.getKindSingular(terminology, kind);
      }

      //
      // Multiple selection, one kind
      // ----------------------------
      // When there are multiple items selected, but all of the same kind,
      // use the kind of the selection.
      if (nKinds === 1) {
        return Drupal.foldershare.utility.getKindPlural(terminology, kind);
      }

      //
      // Multiple selection, multiple kinds
      // ----------------------------------
      // Otherwise there are multiple items selected and they are a mix of
      // multiple kinds. Returns return 'Items'.
      return Drupal.foldershare.utility.getKindPlural(terminology, "item");
    },

    /*--------------------------------------------------------------------
     *
     * Server form.
     *
     *--------------------------------------------------------------------*/

    /**
     * Sets up a server command.
     *
     * @param {object} env
     *   The environment object.
     * @param {string} command
     *   The id/name of the command.
     * @param {int} parentId
     *   (optional, default = null = current page) The parent entity ID.
     *   If not given, the current page's parent ID is used.
     * @param {int} destinationId
     *   (optional, default = null = none) The destination entity ID.
     *   If not given, the value is left empty.
     * @param {int[]} selectionIdList
     *   (optional, default = null = none) The list of selection IDs.
     *   If not given, the value is left empty.
     * @param {FileList} fileList
     *   (optional, default = null = none) The file list. If not given,
     *   the value is left empty.
     */
    serverCommandSetup(
      env,
      command,
      parentId = null,
      destinationId = null,
      selectionIdList = null,
      fileList = null) {

      env.gather.$commandForm[0].reset();

      env.gather.$commandInput.val(command);

      if (parentId === null) {
        env.gather.$parentIdInput.val(env.settings.foldershare.page.id);
      } else {
        env.gather.$parentIdInput.val(parentId);
      }

      if (destinationId !== null) {
        env.gather.$destinationIdInput.val(destinationId);
      }

      if (selectionIdList !== null) {
        env.gather.$selectionIdInput.val(JSON.stringify(selectionIdList));
      }

      if (fileList !== null) {
        // Setting the file list triggers a behavior which does
        // a form submit.
        env.gather.$uploadInput[0].files = fileList;
        this.serverCommandSubmit(env);
      }
    },

    /**
     * Submits a previously set up server command.
     *
     * @param {object} env
     *   The environment object.
     */
    serverCommandSubmit(env) {
      if (env.settings.foldershare.ajaxEnabled === true) {
        env.gather.$commandSubmitButton.submit();
      } else {
        env.gather.$commandForm.submit();
      }
    },

    /*--------------------------------------------------------------------
     *
     * Feature checks.
     *
     *--------------------------------------------------------------------*/

    /**
     * Checks command support of copy, move, and upload drag-and-drop.
     *
     * Copy, move, and upload drag-and-drop features are supported in the UI
     * dependant upon available commands and the type of page being shown.
     *
     * - If the page is for a file, not a folder or root list, then there is
     *   no drag-and-drop supported for the page.
     *
     * - If the copy command is available, then drag-and-drop row copy
     *   is supported.
     *
     * - If the move command is available, then drag-and-drop row move
     *   is supported.
     *
     * - If the upload command is available, then drag-and-drop of files
     *   from off browser is supported.
     *
     * Note that file drag-and-drop also requires checking if the browser
     * supports the feature. This check is done separately.
     *
     * Several environment flags are set:
     * - env.dndCopyEnabled = true if enabled.
     * - env.dndMoveEnabled = true if enabled.
     * - env.dndUploadEnabled = true if enabled.
     * - env.dndUploadChecked = false.
     *
     * @param {object} env
     *   The environment object.
     *
     * @see checkBrowserFileDragSupport()
     */
    checkCommandDragAndDropSupport(env) {
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      switch (env.settings.foldershare.page.kind) {
        case "rootlist":
        case "folder":
          env.dndCopyEnabled = thisScript.copyCommand in env.mainCommands;
          env.dndMoveEnabled = thisScript.moveCommand in env.mainCommands;
          env.dndUploadEnabled = thisScript.uploadCommand in env.mainCommands;
          env.dndUploadChecked = false;
          break;

        default:
          // For any other kind (e.g. file, image, or media), there are no
          // children so drag-and-drop doesn't make sense.
          env.dndCopyEnabled = false;
          env.dndMoveEnabled = false;
          env.dndUploadEnabled = false;
          env.dndUploadChecked = false;
          break;
      }
    },

    /**
     * Checks browser support of drag-and-drop of files for uploads.
     *
     * Modern browsers allow the "files" value of a file input field to
     * be set with a FileList object. The only way to get such an object
     * is from an event's dataTransfer, which is why we need to check
     * this browser ability from within an event behavior.
     *
     * If the browser throws an exception when attempting to set the
     * "files" value, then the browser is old and does not support the
     * feature. Without that feature, we cannot put the names of dragged
     * files into the file input field and therefore cannot do the upload.
     *
     * @param {object} ev
     *   The file drag event.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns false if file drag support is not available.
     */
    checkBrowserFileDragSupport(ev, env) {
      // If the file upload command is not available or if a prior call to
      // this method has already determined that file drag-and-drop is not
      // supported, then return false.
      if (env.dndUploadEnabled === false) {
        return false;
      }

      // If file upload support in the browser has already been checked,
      // then return true.
      if (env.dndUploadChecked === true) {
        return env.dndUploadEnabled;
      }

      // Check if the browser is not properly supporting data transfer
      // properties.
      if (typeof ev.originalEvent.dataTransfer === "undefined" ||
        typeof ev.originalEvent.dataTransfer.files === "undefined") {
        // Fail. The data transfer or files properties are missing.
        // The browser does not appear to be supporting drag-and-drop.
        env.dndUploadEnabled = false;
        env.dndUploadChecked = true;
        return env.dndUploadEnabled;
      }

      // Try to set the file upload field.
      try {
        env.gather.$uploadInput[0].files = ev.originalEvent.dataTransfer.files;
      } catch (er) {
        // Fail. Old browser does not support setting the "files" value.
        // File drag not supportable.
        env.dndUploadEnabled = false;
        env.dndUploadChecked = true;

        // Tell the user the drag is not supported.
        let text = "<div>";
        const translated = env.settings.foldershare.terminology.text.upload_dnd_not_supported;
        if (typeof translated === "undefined") {
          text += "<p><strong>Drag-and-drop file upload is not supported.</strong></p>";
          text += "<p>This feature is not supported by this web browser.</p>";
        } else {
          text += translated;
        }
        text += "</div>";
        Drupal.dialog(text, {}).showModal();
        return env.dndUploadEnabled;
      }

      env.dndUploadEnabled = true;
      env.dndUploadChecked = true;
      return env.dndUploadEnabled;
    },

    /*--------------------------------------------------------------------
     *
     * Operand checks.
     *
     *--------------------------------------------------------------------*/

    /**
     * Check if a current file drag-and-drop is valid.
     *
     * Users may select an arbitrary mix of files and folders in the
     * file browser of modern OSes, such as the Mac Finder or the Windows
     * Explorer. Those can be dragged to a browser window and into the
     * drag-and-drop area of this module in order to trigger an upload.
     *
     * HOWEVER, web browsers currently only support dragging and uploading
     * files. And yet it remains possible for a user to try to drag in a
     * folder.  This function checks the entries in the FileList being
     * dragged and verifies that they are all files, and no folders.
     * On success or failure the appropriate given function is called.
     *
     * Note that this function is ASYNCHRONOUS (because the underlying
     * file reading API is), so it will return immediately while file
     * checking continues in the background.
     *
     * @param {object} ev
     *   The file drag event.
     * @param {object} env
     *   The environment object.
     * @param {function} onValid
     *   The function to call if the file drag is valid. The function
     *   is called with the original event, environment, and file list.
     * @param {function} onInvalid
     *   The function to call if the file drag is not valid. The function
     *   is called with the original event, environment, and file list.
     */
    checkFileDragValid(ev, env, onValid, onInvalid) {
      // Check that the event has dataTransfer and files fields and
      // that there are actual files in the drag.
      if (typeof ev.originalEvent.dataTransfer === "undefined" ||
        typeof ev.originalEvent.dataTransfer.files === "undefined") {
        // Fail. Malformed event.
        onInvalid(ev, env, null);
        return;
      }

      const fileList = ev.originalEvent.dataTransfer.files;
      const nFiles = fileList.length;

      if (nFiles === 0) {
        // Fail. Empty file list.
        onInvalid(ev, env, fileList);
        return;
      }

      // Loop over the list and validate each entry.
      //
      // Each file entry has a name, size, type, and last modified date.
      // Unfortunately, NONE OF THESE can be used by themselves to reliably
      // detect a folder vs. a file.
      //
      // Files and folders both have non-empty names. Further, names are just
      // the file/folder name itself, without a leading path or trailing '/'.
      // So, names may not be used for validity checking.
      //
      // Files and folders both have sizes. While there are on-line claims
      // that folders will only have sizes that are a multiple of 4096 bytes,
      // this is entirely bogus. The size reported for a dragged folder
      // depends upon the OS and its configuration. So, sizes may not be used
      // for validity checking.
      //
      // Files and folders both have modified dates. There is no distinguishing
      // feature here between files and folders, so this is not useful for
      // validity checking.
      //
      // Finally, the type property contains the MIME type of the dragged item.
      // Folders do not have a MIME type, so at first this would seem to be an
      // indicator. BUT...
      // - A file with no extension also has no MIME type.
      // - A folder with an extension has a bogus MIME type.
      //
      // So the MIME type property can be empty for a file, or set for a
      // folder. This makes it not useful for validity checking.
      //
      // This means that NONE of the available properties are useful indicators
      // of files vs. folders. What we CAN DO is try to read bytes from the
      // item. Reading a file will succeed. Reading a folder will fail.
      //
      // FileReader() works asynchronously. If we try to start up too many
      // simultaneous reads, we'll get an error (varies by browser). So we
      // need to serialize this. We do this with:
      //   - load handler: called when the file starts loading. We immediately
      //     abort since we don't need to waste time reading the whole file.
      //
      //   - load end handler:  called when done reading a file, including
      //     when a file load has been aborted. We check if there is another
      //     file to read and start up that read. This continues until there
      //     are no more files to read.
      //
      // To catch errors, we use a:
      //   - error handler: called on an error, such as permission denied or
      //     trying to read a folder. Increment an error counter.
      //
      // If no errors occur, the process will go through the files in order,
      // trying to read each one, aborting, trying the next, etc. When they've
      // all been read, the validity handler is called.
      //
      // If an error occurs, the process will abort and call the invalidity
      // handler.
      let nChecked = 0;
      let nErrors = 0;
      let i = 0;
      const reader = new FileReader();

      reader.onerror = () => {
        ++nErrors;
      };
      reader.onload = e => {
        e.target.abort();
      };
      reader.onloadend = e => {
        // If any error has occurred, stop and report invalid.
        if (nErrors > 0) {
          onInvalid(e, env, fileList);
          return;
        }

        // If we're done checking files, report valid.
        ++nChecked;
        if (nChecked === nFiles) {
          onValid(e, env, fileList);
          return;
        }

        // Otherwise, when there is more to read, start reading the
        // next file.
        ++i;
        e.target.readAsArrayBuffer(fileList[i]);
      };

      // Start it up on the first file.
      reader.readAsArrayBuffer(fileList[i]);
    },

    /*--------------------------------------------------------------------
     *
     * Table behaviors - overview.
     *
     * These functions manage interaction behaviors on the file & folder
     * table.
     *
     * Selection.
     * ----------
     * Selection marks a row or rows as the nouns for the next verb chosen
     * from the command menu.
     *
     * Selection is indicated by giving a row the "selected" class. CSS
     * uses the "selected" class to highlight a selected row. When a command
     * is chosen, the set of selected items is found by looking for all rows
     * with the "selected" class.
     *
     * - On left mouse click or touch, the row is selected/unselected based
     *   upon modifier keys (e.g. SHIFT, CTRL, CMD, ALT).
     * - On right mouse click, if the row is not selected, then select it.
     *   Present a context menu.
     *
     * Open.
     * -----
     * Opening a row shows the view page for the row's entity.
     *
     * - On mouse double-click, open the row's item into a new page.
     *
     * Dragging - general.
     * -------------------
     * When copy and/or move are enabled, rows may be dragged and dropped
     * onto folders.
     *
     * When file uploads are enabled, files may be dragged from off browser
     * and dropped onto the table or onto folders in the table.
     *
     * Because browsers generate different event sequences for "rows" and
     * "files" drags, the event handler response is keyed by the type of drag.
     *
     * Dragging - rows drag.
     * ---------------------
     * A "rows" drag generates the following event sequence:
     * - "dragstart" starts the drag.
     * - "dragenter" is sent every time the drag enters an element.
     * - "dragleave" is sent every time the drag leaves an element.
     * - "dragover" is generated repeatedly as the mouse moves.
     * - "drop" is sent when the user releases the drag.
     * - "dragend" follows the "drop" event.
     *
     * Table styling affects the order of "dragenter" and "dragleave":
     * - If a table has no row spacing (which is typical), then "dragenter"
     *   for the entered row occurs BEFORE "dragleave" for the left row.
     *
     * - If there is row spacing (which default HTML styling does), then
     *   "dragenter" for the entered row occurs AFTER "dragleave" for the
     *   left row.
     *
     * - "dragenter" and "dragleave" are generated for every element, including
     *   nested elements within a table's rows and columns, such as <A> for
     *   anchors, <IMG> for image icons, <SPAN>, <STRONG>, etc. So the specific
     *   structure of a row's content will also affect enter/leave events.
     *
     * If the user cancels the drag (e.g. press the ESCAPE key), the "drop"
     * event does not occur, but "dragend" does.
     *
     * "rows" drag handling here uses events like this:
     * - "dragstart" starts a drag and sets up the data transfer.
     * - "dragenter" is ignored.
     * - "dragover" updates highlighting based on the current row's attributes.
     * - "dragleave" is ignored.
     * - "drop" drops the rows.
     * - "dragend" cleans up after a row "drop" or cancel.
     *
     * Dragging - files (upload) drag.
     * -------------------------------
     * A "files" drag generates the following event sequence:
     * - "dragenter" is sent every time the drag enters an element.
     * - "dragleave" is sent every time the drag leaves an element.
     * - "dragover" is generated repeatedly as the mouse moves.
     * - "drop" is sent when the user releases the drag.
     *
     * Because a files drag starts outside of the browser, the "dragstart"
     * and "dragend" events are never sent. The remaining events are the
     * same as for a "rows" drag.
     *
     * Because there is no "dragstart" event starting a files drag, code
     * must watch for the first "dragenter" event and treat it as the start.
     *
     * Because a final "dragend" event is not sent for files drags, any
     * cleanup must be done on the "drop" event.
     *
     * If the user cancels the drag (e.g. press the ESCAPE key), the "drop"
     * event does not occur. And since the drag did not start within the
     * table, a final "dragend" event does not occur either. Instead,
     * cleanup from a canceled drag must be done on "dragleave", without
     * knowing if there will be another "dragenter" to continue the drag.
     *
     * Summary of "files" drag event handling:
     * - "dragenter" is ignored.
     * - "dragleave" cleans up as if the file drag was canceled.
     * - "dragover" starts a file drag and highlights based on the current
     *   row's attributes.
     * - "drop" drops the files.
     *
     * Dragging - highlighting.
     * ------------------------
     * Highlighting needs to show a single drop target that can be either:
     * - A row.
     * - The table itself.
     *
     * In principal, "dragenter" can be used to highlight, and "dragleave"
     * to unhighlight a row. However:
     *
     * - The order of enter/leave events depends upon theme styling.
     *
     * - These events are generated on every element and nested element on
     *   a row. The number and structure of those nested elements will vary
     *   based upon the theme, field formatters, number of table columns, etc.
     *
     * - The "dragenter" event has a known bug in most browsers that leaves
     *   the event target NOT equal to the actual element under the cursor.
     *   This prevents proper checking for whether the cursor is over a blank
     *   area or something visible. And this prevents proper decision making
     *   about whether the drop target is the row or the table.
     *
     * This means we cannot reliably use "dragenter" to highlight, and
     * "dragleave" to unhighlight. Instead, we must track the current
     * drag-over row by watching for the parent row of events. On a
     * "dragover", if the parent row changes, the highlighting changes.
     *
     *--------------------------------------------------------------------*/

    /**
     * Attaches behaviors to the table.
     *
     * This method attaches row behaviors to support row selection
     * using mouse and touch events, row drag-and-drop, and file drag-and-drop.
     * Some or all of these features may be disabled based upon permissions
     * on the current folder (if any), subfolders (if any), and for copy,
     * move, and file uploads. Drag-and-drop features also may be disabled
     * if copy, move, and file upload commands are not available.
     *
     * @param {object} env
     *   The environment object.
     */
    tableAttachBehaviors(env) {
      const $table = env.gather.$table;
      const $tbody = env.gather.$tbody;
      const $thead = env.gather.$thead;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      //
      // Wrap all text nodes with spans.
      // -------------------------------
      // During drags, we need to know if the cursor is above a blank area
      // or non-blank content, such as text, an image, a video, etc. The
      // drag event target indicates the element under the cursor, BUT text
      // nodes are not elements. Instead, the drag event will indicate the
      // parent element of the text node, such as a <div> or <td>, which
      // does not provide sufficient granularity to know if the cursor is
      // over text or just over an area that could have text.
      //
      // We resolve this by sweeping through all table body rows and
      // replacing text nodes with a <span> that contains the same text.
      // The <span> is marked with an internal attribute. During a drag
      // over the text, the <span> becomes the target element and, since
      // a span is the same size as its content, the existance of this
      // target in an event is a clear indicator that the cursor is over text.
      $tbody.find("*").addBack().contents().filter((index, element) =>
        element.nodeType === Node.TEXT_NODE && /\S/.test(element.nodeValue)
      ).wrap("<span></span>");

      //
      // Show context menu on row right-click.
      // -------------------------------------
      // Attach a row behavior to present the context menu. Typically this
      // event is generated by a right-click, but it also may be presented
      // by a special context menu keyboard key.
      const $contextMenu = $(".foldershare-folder-table-contextmenu", env.gather.$subform);
      $("tr", $tbody).off("contextmenu.foldershare");
      $("tr", $tbody).on("contextmenu.foldershare", function(ev) {
        // If the context menu is visible, hide it.
        if ($contextMenu.menu().is(":visible")) {
          $contextMenu.menu().hide();
          return false;
        }

        // If the current row is NOT selected, select it (clearing any
        // prior selection). Otherwise get the current selection.
        const $thisTr = $(this);
        if ($thisTr.hasClass("selected") === false) {
          // Not selected. Select it now.
          thisScript.tableSelectRow($thisTr, env);
        }

        // Update the menu's text based on the selection.
        thisScript.menuUpdate(env, $contextMenu);

        // Position the menu and show it.
        $contextMenu.show().position({
          my: "left top",
          at: "left bottom",
          of: ev,
          collision: "fit"
        });

        // Register a handler to catch an off-menu click to hide it.
        $(document).on("click.foldershare", () => {
          $contextMenu.menu().hide();
        });

        return false;
      });

      //
      // Open new page on row double-click.
      // ----------------------------------
      // For each body row, add a double-click behavior that opens the
      // view page of the row's entity.
      $("tr", $tbody).off("dblclick.foldershare");
      $("tr", $tbody).on("dblclick.foldershare", function() {
        $(`td.${env.gather.nameColumn} a`, $(this))[0].click();
      });

      //
      // Select on row click or touch.
      // -----------------------------
      // For each body row, add behaviors that respond to mouse clicks and
      // touch screen touches.
      $("tr", $tbody).once("row-click").on("click.foldershare", function(e) {
        thisScript.tableClickSelect.call(this, e, env);
      });

      $("tr", $tbody).once("row-touch").on("touchend.foldershare", function(e) {
        thisScript.tableTouchSelect.call(this, e, env);
      });

      //
      // Prepare for drag behaviors.
      // ---------------------------
      // If copy, move, and/or file upload commands are enabled for this page,
      // then prepare for drag operations.
      if (env.dndCopyEnabled === true || env.dndMoveEnabled === true) {
        // Mark all rows as draggable for copy and/or move.
        $("tr", $tbody).attr("draggable", "true");
      }

      if (env.dndCopyEnabled === true ||
        env.dndMoveEnabled === true ||
        env.dndUploadEnabled === true) {
        // Initialize drag-related attributes.
        $table.attr(thisScript.tableDragOperand, "none");
        $table.attr(thisScript.tableDragEffectAllowed, "none");
        $table.attr(thisScript.tableDragRowIndex, "NaN");
      }

      //
      // Start/end row copy/move on row drags.
      // -------------------------------------
      // If copy or move are supported, respond to drag events for row drags.
      if (env.dndCopyEnabled === true || env.dndMoveEnabled === true) {
        $("tr", $tbody).off("dragstart.foldershare");
        $("tr", $tbody).on("dragstart.foldershare", function(ev) {
          thisScript.tableRowDragStart.call(this, ev, env);
        });

        $("tr", $tbody).off("dragend.foldershare");
        $("tr", $tbody).on("dragend.foldershare", function(ev) {
          thisScript.tableRowDragEnd.call(this, ev, env);
        });
      }

      //
      // Monitor rows during row and file drags.
      // ---------------------------------------
      // If copy, move, or upload are supported, respond to drag events for
      // row and file drags. A drop may occur on a row for row and file drags.
      //
      // Also respond to file drags over the table header. A drop on the
      // header drops files into the table"s entity.
      if (env.dndCopyEnabled === true || env.dndMoveEnabled === true ||
        env.dndUploadEnabled === true) {
        // Body rows.
        $("tr", $tbody).off("dragover.foldershare");
        $("tr", $tbody).on("dragover.foldershare", function(ev) {
          thisScript.tableRowDragOver.call(this, ev, env);
        });

        $("tr", $tbody).off("dragenter.foldershare");
        $("tr", $tbody).on("dragenter.foldershare", ev => {
          ev.preventDefault();
        });

        $("tr", $tbody).off("dragleave.foldershare");
        $("tr", $tbody).on("dragleave.foldershare", function(ev) {
          thisScript.tableRowOrHeaderDragLeave.call(this, ev, env);
        });

        $("tr", $tbody).off("drop.foldershare");
        $("tr", $tbody).on("drop.foldershare", function(ev) {
          thisScript.tableRowOrHeaderDrop.call(this, ev, env);
        });

        // Header.
        $("tr", $thead).off("dragover.foldershare");
        $("tr", $thead).on("dragover.foldershare", function(ev) {
          thisScript.tableHeaderDragOver.call(this, ev, env);
        });

        $("tr", $thead).off("dragenter.foldershare");
        $("tr", $thead).on("dragenter.foldershare", ev => {
          ev.preventDefault();
        });

        $("tr", $thead).off("dragleave.foldershare");
        $("tr", $thead).on("dragleave.foldershare", function(ev) {
          thisScript.tableRowOrHeaderDragLeave.call(this, ev, env);
        });

        $("tr", $thead).off("drop.foldershare");
        $("tr", $thead).on("drop.foldershare", function(ev) {
          thisScript.tableRowOrHeaderDrop.call(this, ev, env);
        });
      }
    },

    /*--------------------------------------------------------------------
     *
     * Table behaviors - select.
     *
     *--------------------------------------------------------------------*/

    /**
     * Handles a touch selection event on a table row.
     *
     * Touch selection toggles the selected item on/off. It ignores keyboard
     * modifiers and therefore does not support range selection.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     */
    tableTouchSelect(ev, env) {
      const $tr = $(this);
      const $table = env.gather.$table;
      const $tbody = env.gather.$tbody;

      // If the touched row does not have a linked name column,
      // then ignore. This can happen in two ways:
      // - The table is empty and the only thing in it is a generic empty
      //   message with no name column.
      // - The table row has a name column but no link because the row is
      //   disabled or the field formatter is misconfigured. Since the link
      //   contains the data attributes we need in order to track and use
      //   the selection, if there is no link the row is unusable and
      //   therefore unselectable.
      const $tdLinkName = $(`td.${env.gather.nameColumn} a`, $tr);
      if ($tdLinkName.length === 0) {
        // Fail. No linked name column. Ignore the row.
        return;
      }

      // If there is a linked name, but it is marked as disabled, then
      // ignore the row.
      const disabled = $tdLinkName.attr("data-foldershare-disabled");
      if (typeof disabled !== "undefined" && disabled === true) {
        // Fail. Item is disabled. Ignore the row.
        return;
      }

      // For out of range row indexes (<1), clear the selection.
      // Otherwise toggle the row selection.
      if (this.rowIndex <= 0) {
        // Header/footer click. Clear the selection.
        $("tr", $tbody).each((index, value) => {
          $(value).toggleClass("selected", false);
        });
        $table.attr("selectionFirstRowIndex", "");
        $table.attr("selectionLastRowIndex", "");
      } else {
        const newState = !$tr.hasClass("selected");
        $tr.toggleClass("selected", newState);
        if (newState === false) {
          $table.attr("selectionFirstRowIndex", "");
          $table.attr("selectionLastRowIndex", "");
        } else {
          $table.attr("selectionFirstRowIndex", this.rowIndex);
          $table.attr("selectionLastRowIndex", this.rowIndex);
        }
      }

      // Some browsers will also send mouse events after a touch event.
      // Such a "ghost click" is not useful here, so disable it.
      ev.preventDefault();
    },

    /**
     * Handles a mouse click selection event on a table row.
     *
     * Mouse selection supports range selection using keyboard modifiers
     * like shift-click, control-click (on Windows or Linux), or
     * command-click (on a Mac):
     *
     * - For all platforms, if there are no keyboard modifiers, then a mouse
     *   click clears any previous selection and starts a new one.
     *
     * - For all platforms, if the shift key is down during a click, a selection
     *   is extended from the most recent selection to the clicked on row.
     *
     * - For Windows and Linux platforms, if the control key is down during a
     *   click, a selected row is toggled.
     *
     * - For Mac platforms, if the command (meta) key is down during a
     *   click, a selected row is toggled.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     */
    tableClickSelect(ev, env) {
      const $tr = $(this);
      const $table = env.gather.$table;
      const $tbody = env.gather.$tbody;
      let first = $table.attr("selectionFirstRowIndex");
      let last = $table.attr("selectionLastRowIndex");

      // If the clicked-on row does not have a linked name column,
      // then ignore. This can happen in two ways:
      // - The table is empty and the only thing in it is a generic empty
      //   message with no name column.
      // - The table row has a name column but no link because the row is
      //   disabled or the field formatter is misconfigured. Since the link
      //   contains the data attributes we need in order to track and use
      //   the selection, if there is no link the row is unusable and
      //   therefore unselectable.
      const $tdLinkName = $(`td.${env.gather.nameColumn} a`, $tr);
      if ($tdLinkName.length === 0) {
        // Fail. No linked name column. Ignore the row.
        return;
      }

      // If there is a linked name, but it is marked as disabled, then
      // ignore the row.
      const disabled = $tdLinkName.attr("data-foldershare-disabled");
      if (typeof disabled !== "undefined" && disabled === true) {
        // Fail. Item is disabled. Ignore the row.
        return;
      }

      const isMac = navigator.appVersion.indexOf("Mac") !== -1;

      // Check for keyboard modifiers and mimic Windows/Linux/Mac behavior.
      // If more than one modifier is held down, the control/command
      // modifier has a higher priority than the shift modifier.
      if ((isMac === true && ev.metaKey === true) ||
        (isMac === false && ev.ctrlKey === true)) {
        // Control/Command-click
        // ---------------------
        // On a Mac, a command-click toggles the selection state of the
        // clicked-on row.
        //
        // On all other platforms (e.g. Windows and Linux), a control-click
        // toggles the selection state of the clicked-on row.
        const newState = !$tr.hasClass("selected");
        $tr.toggleClass("selected", newState);

        if (newState === true) {
          // A clicked-on row always resets the range to that row.
          $table.attr("selectionFirstRowIndex", this.rowIndex);
          $table.attr("selectionLastRowIndex", this.rowIndex);
        } else {
          first = Number(first);
          last = Number(last);

          if (this.rowIndex === first && this.rowIndex === last) {
            // The unselected row was the only row in the selection.
            // Empty the range.
            $table.attr("selectionFirstRowIndex", "");
            $table.attr("selectionLastRowIndex", "");
          } else if (this.rowIndex === first) {
            // The unselected row was the start of the range. Shorten the
            // range to start on the next row.
            $table.attr("selectionFirstRowIndex", first + 1);
          } else if (this.rowIndex === last) {
            // The unselected row was the end of the range. Shorten the
            // range to end on the previous row.
            $table.attr("selectionLastRowIndex", last - 1);
          } else {
            // The unselected row was within the range. Shorten the range
            // to include the lower half of the range.
            $table.attr("selectionFirstRowIndex", this.rowIndex + 1);
          }
        }
      } else if (ev.shiftKey === true) {
        // Shift-click
        // -----------
        // For all platforms, a shift-click adjusts a current selection.
        //
        // The following combinations could occur:
        //
        // - No current selection. Select the clicked-on row and save the
        //   range as (first = last = clicked-on row).
        //
        // - Current selection and clicked-on row is above it. Flip the
        //   range by clearing the entire selection first, then selecting
        //   rows from the clicked-on row to through the first item of the
        //   old selection. Save the range as (first = clicked-on row) and
        //   (last = old first).
        //
        // - Current selection and clicked-on row is below it. Extend the
        //   selection by selecting rows from (last+1) through the
        //   clicked-on row. Save the range as (first = old first) and
        //   (last = clicked-on row).
        //
        // - Current selection and clicked-on row within the range.
        //   Shorten the range by clearing everything from the row after
        //   the clicked-on row through the last row of the range. Save
        //   the range as (first = old first) and (last = clicked-on row).
        //
        // Note that row indexes are 1-based, but loop/array/element
        // indexes are 0-based.
        if (typeof last === "undefined" || last === "") {
          // No prior selection. Select from 1st row thru this row.
          $("tr", $tbody).slice(0, this.rowIndex).each((index, value) => {
            // Only select rows that have a linked name and are not disabled.
            const $n = $(`td.${env.gather.nameColumn} a`, $(value));
            if ($n.length !== 0) {
              const d = $n.attr("data-foldershare-disabled");
              if (typeof d === "undefined" || d === false) {
                $(value).toggleClass("selected", true);
              }
            }
          });

          $table.attr("selectionFirstRowIndex", 1);
          $table.attr("selectionLastRowIndex", this.rowIndex);
        } else {
          first = Number(first);
          last = Number(last);

          if (this.rowIndex > last) {
            // Extend selection downwards thru the clicked-on row.
            $("tr", $tbody).slice(last, this.rowIndex).each((index, value) => {
              // Only select rows that have a linked name and are not disabled.
              const $n = $(`td.${env.gather.nameColumn} a`, $(value));
              if ($n.length !== 0) {
                const d = $n.attr("data-foldershare-disabled");
                if (typeof d === "undefined" || d === false) {
                  $(value).toggleClass("selected", true);
                }
              }
            });

            $table.attr("selectionFirstRowIndex", first);
            $table.attr("selectionLastRowIndex", this.rowIndex);
          } else if (this.rowIndex < first) {
            // Flip selection upwards thru the clicked-on row. Clear the
            // current selection, except the first row, then add the new rows.
            $("tr", $tbody).slice(first, last + 1).each((index, value) => {
              $(value).toggleClass("selected", false);
            });
            $("tr", $tbody).slice(this.rowIndex - 1, first).each((index, value) => {
              // Only select rows that have a linked name and are not disabled.
              const $n = $(`td.${env.gather.nameColumn} a`, $(value));
              if ($n.length !== 0) {
                const d = $n.attr("data-foldershare-disabled");
                if (typeof d === "undefined" || d === false) {
                  $(value).toggleClass("selected", true);
                }
              }
            });

            $table.attr("selectionFirstRowIndex", this.rowIndex);
            $table.attr("selectionLastRowIndex", first);
          } else {
            // Shorten selection to end on the clicked-on row. Clear all rows
            // after the clicked-on row.
            $("tr", $tbody).slice(this.rowIndex, last).each((index, value) => {
              $(value).toggleClass("selected", false);
            });

            $table.attr("selectionFirstRowIndex", first);
            $table.attr("selectionLastRowIndex", this.rowIndex);
          }
        }
      } else {
        // Click
        // -----
        // When there are no keyboard modifiers, clicking on a row clears
        // the previous selection (if any) and selects the row.
        $("tr", $tbody).each((index, value) => {
          $(value).toggleClass("selected", false);
        });

        // For out of range row (<1), clear selection.
        // Otherwise select the clicked-on row and save its index.
        if (this.rowIndex <= 0) {
          $table.attr("selectionFirstRowIndex", "");
          $table.attr("selectionLastRowIndex", "");
        } else {
          $tr.toggleClass("selected", true);
          $table.attr("selectionFirstRowIndex", this.rowIndex);
          $table.attr("selectionLastRowIndex", this.rowIndex);
        }
      }

      // A click can sometimes cause a text selection if the mouse
      // moved a little between mouse down and up. Such a text
      // selection is meaningless here, so disable it.
      window.getSelection().removeAllRanges();
    },

    /*--------------------------------------------------------------------
     *
     * Table behaviors - drag.
     *
     *--------------------------------------------------------------------*/

    /**
     * Returns the current drop target.
     *
     * The drop target is one of:
     * - 'row' = the current row.
     * - 'table' = the current table, whether it represents a folder or
     *   a root list.
     * - 'none' = there is no drop target.
     *
     * This function is always called with a current row. But that row is
     * only a valid drop target if:
     * - The cursor is above a visible feature of the row (text, image, etc.).
     * - The row has a name column with necessary entity info.
     * - The entity is not disabled.
     * - The entity is a folder.
     *
     * If none of the above are TRUE, then the table itself is the drop
     * target if:
     * - The drag operation is for files, not rows.
     *
     * @param {object} $thisTr
     *   The table row under the cursor.
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     * @return {string}
     *   Returns the drag target as either 'row', 'table', or 'none'.
     */
    getTableDropTarget($thisTr, ev, env) {
      // To detect when the cursor is over text, earlier processing has
      // wrapped all text nodes with a <SPAN>. Unlike a text node, a <SPAN>
      // is an element and can be the target of an event.
      //
      // Our ASSUMPTION is that any element type that is not a block (e.g.
      // not <DIV> or <P>) will fairly tightly surround text or other visual
      // elements. If the cursor is over one of those, then that is enough
      // to say the drop target is a row.
      let dropTarget = "table";
      const targetStyle = window.getComputedStyle(ev.target, "");
      let $a = null;

      switch (targetStyle.display) {
        case "inline":
        case "inline-block":
        case "inline-flex":
        case "inline-table":
        case "marker":
          // If the row is valid, not disabled, and for a folder, then it
          // is a valid row drop target. Otherwise, revert to table.
          $a = $(`td.${env.gather.nameColumn} a`, $thisTr);
          if (typeof $a !== "undefined") {
            const disabled = $a.attr("data-foldershare-disabled");
            if (typeof disabled === "undefined" || disabled === false) {
              const rowKind = $a.attr("data-foldershare-kind");
              if (rowKind === "folder") {
                dropTarget = "row";
              }
            }
          }
          break;

        default:
          break;
      }

      return dropTarget;
    },

    /**
     * Handles the start of rows drags.
     *
     * This function is called on a 'dragstart' event, which only occurs
     * for row drags, not file drags.
     *
     * An entity row drag copies or moves one or more table rows, depicting
     * entities, and drops them into a subfolder. The drag list created
     * by the drag is a list of entity IDs for the dragged rows:
     *
     * - If the drag starts on a selected item, the entire selection is
     *   added to the drag list in a pending data transfer.
     *
     * - If the drag starts on an unselected item, that single row is
     *   added to the drag list in a pending data transfer.
     *
     * In both cases, a ghost image is created that shows the names
     * of the items being dragged. The data transfer state is initialized
     * and table attributes set to record that a row drag is in progress.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns true.
     */
    tableRowDragStart(ev, env) {
      const $thisTr = $(this);
      const $thisTable = env.gather.$table;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      //
      // Validate.
      // ---------
      // If the clicked-on row does not have a linked name column,
      // then ignore. This can happen in two ways:
      //
      // - The table is empty and the only thing in it is a generic empty
      //   message with no name column.
      //
      // - The table row has a name column but no link because the row is
      //   disabled or the field formatter is misconfigured. Since the link
      //   contains the data attributes we need in order to track and use
      //   the selection, if there is no link the row is unusable and
      //   therefore unselectable.
      const $tdLinkName = $(`td.${env.gather.nameColumn} a`, $thisTr);
      if ($tdLinkName.length === 0) {
        // Fail. No linked name column. Ignore the row.
        ev.preventDefault();
        ev.stopPropagation();
        return true;
      }

      // If there is a linked name, but it is marked as disabled, then
      // ignore the row.
      const disabled = $tdLinkName.attr("data-foldershare-disabled");
      if (typeof disabled !== "undefined" && disabled === true) {
        // Fail. Item is disabled. Ignore the row.
        ev.preventDefault();
        ev.stopPropagation();
        return true;
      }

      //
      // Mark the table.
      // ---------------
      // Mark the table as having a row drag in progress. This mark is
      // removed when the drag is done and it indicates that row dragging,
      // rather than off-browser file dragging is in progress.
      $thisTable.attr(thisScript.tableDragOperand, "rows");
      $thisTable.attr(thisScript.tableDragRowIndex, this.rowIndex);

      //
      // Create drag ghost image.
      // ------------------------
      // The drag image during the drag is from a ghost table that contains
      // a clone of the name column (or entire row) for dragged items.
      //
      // While looping over the items to add to the ghost, collect their
      // entity ID's to use as the data transfer data.
      //
      // Warning: older browsers may not support setting the drag image.
      // If they don't support it, skip creating the ghost image.
      let $dragTable = null;
      let $dragTbody = null;
      let rowHeight = 0;

      const dragImageSupported =
        typeof ev.originalEvent.dataTransfer.setDragImage === "function";
      if (dragImageSupported === true) {
        $dragTable = $('<table class="dragImage">');
        $dragTbody = $("<tbody>");
        $dragTable.append($dragTbody);
      }

      const nameColumn = env.gather.nameColumn;

      // Collect selected items.
      //
      // Add clones of the current row or all selected rows to the ghost table.
      //
      // Get the height of a dragged table row to use to position the ghost
      // table under the cursor.
      const draggedList = [];

      if ($thisTr.hasClass("selected") === true) {
        // The user has started a drag atop a selected row.
        //
        // Add all selected rows in the table into a list of dragged rows
        // and the ghost table.
        $("tr.selected", $thisTable).each((index, value) => {
          const $td = $(`td.${nameColumn}`, $(value));

          // Save the row's entity ID.
          const $a = $("a", $td);
          if ($a.length === 0) {
            // Fail. No anchor? Ignore row.
            return false;
          }

          draggedList.push($a.attr("data-foldershare-id"));

          if (dragImageSupported === true) {
            // Clone the column and add it to the ghost table.
            // Remove row and column classes so we don't get any residual
            // styling of the ghost.
            if (value.offsetHeight > rowHeight) {
              rowHeight = value.offsetHeight;
            }

            const $newTd = $td.clone(false).removeClass();
            $dragTbody.append($("<tr>").append($newTd));
          }
        });
      } else {
        // The user has started a drag atop an unselected row.
        //
        // Add the single row to the list of dragged rows and the ghost table.
        const $td = $(`td.${nameColumn}`, $thisTr);

        // Save the row"s entity ID.
        const $a = $("a", $td);
        if ($a.length === 0) {
          // Fail. No anchor? Ignore drag start.
          return true;
        }

        draggedList.push($a.attr("data-foldershare-id"));

        if (dragImageSupported === true) {
          // Clone the column or row and add it to the ghost table.
          // Remove row and column classes so we don't get any residual
          // styling of the ghost.
          rowHeight = $thisTr[0].offsetHeight;
          const $oldTd = $(`td.${nameColumn}`, $thisTr);
          const $newTd = $oldTd.clone(false).removeClass();
          $dragTbody.append($("<tr>").append($newTd));
        }
      }

      //
      // Set up the data transfer.
      // -------------------------
      // - Set the transferred data to be a list of entity IDs.
      // - Set the drag image to be the ghost table.
      // - Set the allowed 'effects' (e.g. copy or move).
      // - Set the initial 'effect' (e.g. copy or move).
      ev.originalEvent.dataTransfer.setData(
        "foldershare/local-entity-list",
        JSON.stringify(draggedList));

      let allowed = "none";
      let effect = "non";
      if (env.dndCopyEnabled === true && env.dndMoveEnabled === true) {
        allowed = "copyMove";
        effect = "move";
      } else if (env.dndCopyEnabled === true) {
        allowed = "copy";
        effect = "copy";
      } else {
        allowed = "move";
        effect = "move";
      }

      ev.originalEvent.dataTransfer.effectAllowed = allowed;
      ev.originalEvent.dataTransfer.dropEffect = effect;
      $thisTable.attr(thisScript.tableDragEffectAllowed, allowed);

      if (dragImageSupported === true) {
        // The ghost table must be on the page in order to be rendered
        // and used as the ghost table. So add it temporarily.
        $("body").append($dragTable);

        ev.originalEvent.dataTransfer.setDragImage(
          $dragTable[0],
          0,
          rowHeight / 2);

        // The ghost table must exist in the body long enough for it to be
        // rendered for use as the drag image. But after that it is clutter
        // that will show up at the end of the page. To remove it as soon
        // as possible, we set a timeout function.
        setTimeout(() => {
          $dragTable.remove();
        });
      }

      return true;
    },

    /**
     * Handles the end of rows drags.
     *
     * This function is called on a 'dragend' event, which only occurs
     * for row drags, not file drags.
     *
     * An entity row drag copies or moves one or more table rows, depicting
     * entities, and drops them into a subfolder. The start of the drag has
     * already initialized the event's data transfer object to contain a list
     * of dragged entity IDs.
     *
     * A drag can end in one of two ways:
     * - The user dropped the drag.
     * - The user canceled the drag (such as by the ESC key).
     *
     * If the user dropped the drag, the "drop" event behavior has already
     * handled collecting the entity ID list from the data transfer and
     * sending a copy or move command to the server.
     *
     * This method cleans up after either a drop or a cancel by resetting
     * table attributes and unhighlighting whatever row was most recently
     * under the cursor during the drag (if any).
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns false.
     */
    tableRowDragEnd(ev, env) {
      const $thisTable = env.gather.$table;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      // Get the old drop target and row index.
      const oldRowIndex = Number($thisTable.attr(thisScript.tableDragRowIndex));
      const oldDropTarget = $thisTable.attr(thisScript.tableDropTarget);

      // Unhighlight, if any.
      switch (oldDropTarget) {
        case "table":
          $thisTable.removeClass("foldershare-draghover");
          break;

        case "row":
          if (Number.isNaN(oldRowIndex) === false) {
            // The event"s row index is 1-based, while jQuery is 0-based.
            $("tbody tr", $thisTable).eq(oldRowIndex - 1)
              .removeClass("foldershare-draghover");
          }
          break;

        default:
        case "none":
          break;
      }

      // Clear the table attributes.
      $thisTable.attr(thisScript.tableDragRowIndex, "NaN");
      $thisTable.attr(thisScript.tableDropTarget, "none");
      $thisTable.attr(thisScript.tableDragOperand, "none");
      $thisTable.attr(thisScript.tableDragEffectAllowed, "none");
      return false;
    },

    /**
     * Handles continuation of rows/files drags atop table body rows.
     *
     * This function is called on a "dragover" event for both row and file
     * drags passing over table body rows.
     *
     * It is important to keep this behavior as fast as possible because
     * browsers generate a large number of these events, whether the user's
     * cursor is moving or not.
     *
     * For entity row drags, processing determines if the current row or the
     * table should be highlighted.
     *
     * For file drags, processing is done on a "dragleave". But on the
     * first "dragover" event during a file drag, intial setup is done.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns false and prevents further event processing.
     *
     * @see tableHeaderDragOver()
     */
    tableRowDragOver(ev, env) {
      const $thisTr = $(this);
      const $thisTable = env.gather.$table;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      // Get the old and new drop target and row index.
      const oldRowIndex = Number($thisTable.attr(thisScript.tableDragRowIndex));
      const oldDropTarget = $thisTable.attr(thisScript.tableDropTarget);

      const newRowIndex = this.rowIndex;
      const newDropTarget = thisScript.getTableDropTarget($thisTr, ev, env);

      let allowed = "none";
      let effect = "none";

      switch ($thisTable.attr(thisScript.tableDragOperand)) {
        default:
        case "rows":
          // Draging rows.
          //
          // "dragover" events occur in huge numbers. We only care about the
          // event if it changes the highlighting. And it only does that if:
          // - The row index changes, OR
          // - The drop target changes.
          if (oldRowIndex === newRowIndex && oldDropTarget === newDropTarget) {
            break;
          }

          // The row or the drop target have changed. Unhighlight whatever
          // was highlighted before (if anything).
          switch (oldDropTarget) {
            case "table":
              $thisTable.removeClass("foldershare-draghover");
              break;

            case "row":
              if (Number.isNaN(oldRowIndex) === false) {
                // The event's row index is 1-based, while jQuery is 0-based.
                $("tbody tr", $thisTable).eq(oldRowIndex - 1)
                  .removeClass("foldershare-draghover");
              }
              break;

            default:
            case "none":
              break;
          }

          // Highlight the new drop target (if anything). Determine the
          // drag allowed and effect settings.
          switch (newDropTarget) {
            case "row":
              // The event's row index is 1-based, while jQuery is 0-based.
              $("tbody tr", $thisTable).eq(newRowIndex - 1)
                .addClass("foldershare-draghover");
              if (env.dndCopyEnabled === true && env.dndMoveEnabled === true) {
                allowed = "copyMove";
                effect = "move";
              } else if (env.dndCopyEnabled === true) {
                allowed = "copy";
                effect = "copy";
              } else if (env.dndMoveEnabled === true) {
                allowed = "move";
                effect = "move";
              }
              break;

            default:
            case "table":
            case "none":
              break;
          }

          // Save the latest drop target and row index.
          $thisTable.attr(thisScript.tableDropTarget, newDropTarget);
          $thisTable.attr(thisScript.tableDragRowIndex, newRowIndex);

          // Save the latest drag effect.
          $thisTable.attr(thisScript.tableDragEffectAllowed, allowed);
          ev.originalEvent.dataTransfer.effectAllowed = allowed;
          ev.originalEvent.dataTransfer.dropEffect = effect;
          break;

        case "files":
          break;

        case "none":
          // Since the drag operand is still 'none', this must be the first
          // drag event for a file drag from off-browser.
          //
          // The event's dataTransfer property exists and can be configured
          // at this point, BUT the list of files being dragged is not yet
          // known. We therefore cannot confirm that the drag is valid yet.
          //
          // If the browser is old and does not support dragged files for
          // uploads, then do nothing.
          if (thisScript.checkBrowserFileDragSupport(ev, env) === false) {
            break;
          }

          // Highlight, if needed.
          switch (newDropTarget) {
            case "table":
              $thisTable.addClass("foldershare-draghover");
              break;

            case "row":
              $("tbody tr", $thisTable).eq(newRowIndex - 1)
                .addClass("foldershare-draghover");
              break;

            default:
            case "none":
              break;
          }

          // Mark the table as having a file drag in progress.
          $thisTable.attr(thisScript.tableDragOperand, "files");

          // File drags are always 'copy' operations.
          $thisTable.attr(thisScript.tableDragEffectAllowed, "copy");
          ev.originalEvent.dataTransfer.dropEffect = "copy";
          ev.originalEvent.dataTransfer.effectAllowed = "copy";

          // Save the latest drop target and row index.
          $thisTable.attr(thisScript.tableDropTarget, newDropTarget);
          $thisTable.attr(thisScript.tableDragRowIndex, newRowIndex);

          break;
      }

      ev.preventDefault();
      ev.stopPropagation();
      return false;
    },

    /**
     * Handles continuation of rows/files drags atop the table header.
     *
     * This function is called on a "dragover" event for both row and file
     * drags that are over the table header.
     *
     * It is important to keep this behavior as fast as possible because
     * browsers generate a large number of these events, whether the user's
     * cursor is moving or not.
     *
     * Dragging atop the table header is a simplified case of dragging atop
     * table body rows:
     *
     * - For entity row drags, the previous highlighted row (if any) is
     *   unhighlighted and the table is left unhighlighted. A row drag drop
     *   on the table header does nothing, so the pending drop target is
     *   set to none.
     *
     * - For file upload drags, if this is the first time a file upload drag
     *   has been encountered, the table is set as the drop target and
     *   highlighted.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns false and prevents further event processing.
     *
     * @see tableRowDragOver()
     */
    tableHeaderDragOver(ev, env) {
      const $thisTable = env.gather.$table;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      // Get the old drop target and row index.
      const oldRowIndex = Number($thisTable.attr(thisScript.tableDragRowIndex));
      const oldDropTarget = $thisTable.attr(thisScript.tableDropTarget);

      switch ($thisTable.attr(thisScript.tableDragOperand)) {
        default:
        case "rows":
          // Draging rows.
          //
          // "dragover" events occur in huge numbers. We only care about the
          // event if it changes the highlighting. And it only does that if:
          // - The row index changes, OR
          // - The drop target changes.
          if (Number.isNaN(oldRowIndex) === true && oldDropTarget === "none") {
            break;
          }

          // The row or the drop target have changed. Unhighlight whatever
          // was highlighted before (if anything).
          switch (oldDropTarget) {
            case "table":
              $thisTable.removeClass("foldershare-draghover");
              break;

            case "row":
              if (Number.isNaN(oldRowIndex) === false) {
                // The event's row index is 1-based, while jQuery is 0-based.
                $("tbody tr", $thisTable).eq(oldRowIndex - 1)
                  .removeClass("foldershare-draghover");
              }
              break;

            default:
            case "none":
              break;
          }

          // Dragging atop the header always means the drop target is 'none',
          // since a drop on the header drops into the current table entity.
          // And yet a row drag is dragging items that are already in the
          // entity, so dropping there makes no changes.
          //
          // Save the latest drop target and row index.
          $thisTable.attr(thisScript.tableDropTarget, "none");
          $thisTable.attr(thisScript.tableDragRowIndex, "NaN");

          // Save the latest drag effect.
          $thisTable.attr(thisScript.tableDragEffectAllowed, "none");
          ev.originalEvent.dataTransfer.effectAllowed = "none";
          ev.originalEvent.dataTransfer.dropEffect = "none";
          break;

        case "files":
          break;

        case "none":
          // Since the drag operand is still 'none', this must be the first
          // drag event for a file drag from off-browser.
          //
          // The event's dataTransfer property exists and can be configured
          // at this point, BUT the list of files being dragged is not yet
          // known. We therefore cannot confirm that the drag is valid yet.
          //
          // If the browser is old and does not support dragged files for
          // uploads, then do nothing.
          if (thisScript.checkBrowserFileDragSupport(ev, env) === false) {
            break;
          }

          // The new drop target is always the table. Highlight the table.
          $thisTable.addClass("foldershare-draghover");

          // Mark the table as having a file drag in progress.
          $thisTable.attr(thisScript.tableDragOperand, "files");

          // File drags are always "copy' operations.
          $thisTable.attr(thisScript.tableDragEffectAllowed, "copy");
          ev.originalEvent.dataTransfer.dropEffect = "copy";
          ev.originalEvent.dataTransfer.effectAllowed = "copy";

          // Save the latest drop target and row index.
          $thisTable.attr(thisScript.tableDropTarget, "table");
          $thisTable.attr(thisScript.tableDragRowIndex, "NaN");

          break;
      }

      ev.preventDefault();
      ev.stopPropagation();
      return false;
    },

    /**
     * Handles region leave on rows/files drags atop table body rows or header.
     *
     * This function is called on a "dragleave" event for both row and file
     * drags atop table body rows or the table's header.
     *
     * For entity row drags, processing is done on a "dragover". This
     * method does nothing.
     *
     * For file drags, processing is done here. With a file drag, there
     * is no unique ending event if the drag is canceled. All we get is a
     * final "dragleave" and we cannot determine if the event is from a
     * canceled drag or just a "dragleave" as the user's cursor is moving
     * across an element boundary during a drag. This method is forced to
     * assume the drag has been canceled and clean up for it since there will
     * be no other opportunity to do so. If this is not in fact the end of
     * the file drag, then there will be another "dragover" event during
     * which we'll restart the file drag. Ugly, but necessary.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns false.
     */
    tableRowOrHeaderDragLeave(ev, env) {
      const $thisTable = env.gather.$table;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;
      const oldRowIndex = Number($thisTable.attr(thisScript.tableDragRowIndex));

      switch ($thisTable.attr(thisScript.tableDragOperand)) {
        default:
        case "rows":
        case "none":
          break;

        case "files":
          // Assume this is the last event of a canceled file drag, since
          // we can't tell otherwise. End the file drag.
          // Unhighlight, if any.
          switch ($thisTable.attr(thisScript.tableDropTarget)) {
            case "table":
              $thisTable.removeClass("foldershare-draghover");
              break;

            case "row":
              if (Number.isNaN(oldRowIndex) === false) {
                // The event's row index is 1-based, while jQuery is 0-based.
                $("tbody tr", $thisTable).eq(oldRowIndex - 1)
                  .removeClass("foldershare-draghover");
              }
              break;

            default:
            case "none":
              break;
          }

          // Clear the table attributes.
          $thisTable.attr(thisScript.tableDragRowIndex, "NaN");
          $thisTable.attr(thisScript.tableDropTarget, "none");
          $thisTable.attr(thisScript.tableDragOperand, "none");
          $thisTable.attr(thisScript.tableDragEffectAllowed, "none");
          break;
      }

      return false;
    },

    /**
     * Handles a drop of rows/files drags atop table body rows or header.
     *
     * This function is called on a "drop" event for both row and file
     * drags atop rows in the table body or the table header.
     *
     * For entity row drags, the drop triggers a move or copy of the
     * dragged entities into a subfolder.
     *
     * For file drags, the drop triggers an upload of the dragged files
     * into the current table (if allowed) or a subfolder.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns false and prevents further event processing.
     */
    tableRowOrHeaderDrop(ev, env) {
      const $thisTr = $(this);
      const $thisTable = env.gather.$table;
      const thisScript = Drupal.foldershare.UIFolderTableMenu;

      //
      // Setup.
      // ------
      // Get the drop target. If it is empty, ignore the drop. If it is
      // a row, get row information for use below.
      //
      // Get the old drop target and row index.
      const oldRowIndex = Number($thisTable.attr(thisScript.tableDragRowIndex));
      const oldDropTarget = $thisTable.attr(thisScript.tableDropTarget);

      const newDropTarget = $thisTable.attr(thisScript.tableDropTarget);
      let dropEntityId = "";

      switch (newDropTarget) {
        case "table":
          // Table target. No setup required.
          dropEntityId = env.settings.foldershare.page.id;
          break;

        case "row":
          // Row target. Get the entity ID for the drop row.
          // Since we already got a new drop target of 'row', we know
          // that the row is valid, not disabled, and a folder.
          dropEntityId = $(`td.${env.gather.nameColumn} a`, $thisTr)
            .attr("data-foldershare-id");
          break;

        default:
        case "none":
          // No drop target. Ignore the drop.
          ev.preventDefault();
          ev.stopPropagation();
          return false;
      }

      // Unhighlight, if any.
      switch (oldDropTarget) {
        case "table":
          $thisTable.removeClass("foldershare-draghover");
          break;

        case "row":
          if (Number.isNaN(oldRowIndex) === false) {
            // The event's row index is 1-based, while jQuery is 0-based.
            $("tbody tr", $thisTable).eq(oldRowIndex - 1)
              .removeClass("foldershare-draghover");
          }
          break;

        default:
        case "none":
          break;
      }

      //
      // Execute the drop.
      // -----------------
      // The data transfer's "dropEffect" is handled differently by
      // different browsers:
      //
      // - Microsoft Edge and Mozilla Firefox set "dropEffect" to
      //   "copy" or "move" when earlier "dragover" behaviors have constained
      //   the allowed effect to "copyMove".
      //
      // - Apple Safari sets "dropEffect" to "none" and "effectAllowed" to
      //   "all", "copy", or "move" when we constrain the allowed effect to
      //   "copyMove".
      let effect = null;
      let command = null;
      let entityIdList = null;
      switch ($thisTable.attr(thisScript.tableDragOperand)) {
        default:
        case "none":
          // No drag in progress. This should not be possible.
          ev.preventDefault();
          ev.stopPropagation();
          return false;

        case "rows":
          // Drop entity rows.
          //
          // The drop target must be a row. If not, ignore the drop.
          if (newDropTarget !== "row") {
            break;
          }

          // Get the drag's list of entity IDs and make sure the drop row's
          // entity ID is not in the list.
          entityIdList = JSON.parse(ev.originalEvent.dataTransfer.getData(
              "foldershare/local-entity-list"));
          if ($.inArray(dropEntityId, entityIdList) !== -1) {
            // User error. Cannot drop onto self.
            break;
          }

          // Determine if the operation is a copy or move.
          effect = ev.originalEvent.dataTransfer.dropEffect;
          if (effect === "none") {
            switch (ev.originalEvent.dataTransfer.effectAllowed) {
              default:
              case "copyMove":
              case "linkMove":
              case "move":
              case "all":
                effect = "move";
                break;

              case "copyLink":
              case "copy":
                effect = "copy";
                break;
            }

            if (effect === "none") {
              // Still none. Cannot figure out effect.
              break;
            }
          }

          switch (effect) {
            default:
            case "move":
              command = thisScript.moveCommand;
              break;

            case "copy":
              command = thisScript.copyCommand;
              break;
          }

          // Issue the copy or move command.
          thisScript.serverCommandSetup(
            env,
            command,
            null,
            dropEntityId,
            entityIdList,
            null
          );
          thisScript.serverCommandSubmit(env);
          break;

        case "files":
          // Drop files.
          //
          // The drop target can be a row or the table.
          //
          // Clean up at the end of a file drag.
          $thisTable.attr(thisScript.tableDragOperand, "none");
          $thisTable.attr(thisScript.tableDragEffectAllowed, "none");
          $thisTable.attr(thisScript.tableDragRowIndex, "NaN");
          $thisTable.attr(thisScript.tableDropTarget, "none");

          thisScript.checkFileDragValid(
            ev,
            env,
            (eev, eenv, fileList) => {
              // Issue the upload command.
              thisScript.serverCommandSetup(
                eenv,
                thisScript.uploadCommand,
                dropEntityId,
                null,
                null,
                fileList);
            },
            (eev, eenv, fileList) => {
              // Tell the user the drag was not valid.
              let text = "<div>";
              if (fileList.length <= 1) {
                const translated = eenv.settings.foldershare.terminology.text
                  .upload_dnd_invalid_singular;
                if (typeof translated === "undefined") {
                  text += "<p><strong>Drag-and-drop item cannot be uploaded.</strong></p>";
                  text += "<p>You may not have access to the item, or it may be a folder. Folder upload is not supported.</p>";
                } else {
                  text += translated;
                }
              } else {
                const translated = eenv.settings.foldershare.terminology.text
                  .upload_dnd_invalid_plural;
                if (typeof translated === "undefined") {
                  text += "<p><strong>Drag-and-drop items cannot be uploaded.</strong></p>";
                  text += "<p>You may not have access to these items, or one of them may be a folder. Folder upload is not supported.</p>";
                } else {
                  text += translated;
                }
              }
              text += "</div>";

              Drupal.dialog(text, {}).showModal();
            });
          break;
      }

      ev.preventDefault();
      ev.stopPropagation();
      return false;
    },

    /*--------------------------------------------------------------------
     *
     * Table.
     *
     * These functions manage the table of files and folders.
     *
     *--------------------------------------------------------------------*/

    /**
     * Handles a single table row selection as if by a mouse click.
     *
     * Used to select a row based upon some non-mouse event, this method
     * marks a single row as selected, clearing any previous selection.
     *
     * @param {object} $tr
     *   The row to select.
     * @param {object} env
     *   The environment object.
     */
    tableSelectRow($tr, env) {
      const $table = env.gather.$table;
      const $tbody = env.gather.$tbody;

      // Selecting a row clears the previous selection (if any) and
      // selects the row.
      $("tr", $tbody).each((index, value) => {
        $(value).toggleClass("selected", false);
      });

      // For out of range row (<1), clear selection.
      // Otherwise select the clicked-on row and save its index.
      if (this.rowIndex <= 0) {
        $table.attr("selectionFirstRowIndex", "");
        $table.attr("selectionLastRowIndex", "");
      } else {
        // Only select rows that have a linked name and are not disabled.
        const $tdLinkName = $(`td.${env.gather.nameColumn} a`, $tr);
        if ($tdLinkName.length !== 0) {
          const disabled = $tdLinkName.attr("data-foldershare-disabled");
          if (typeof disabled === "undefined" || disabled === false) {
            $tr.toggleClass("selected", true);
            $table.attr("selectionFirstRowIndex", this.rowIndex);
            $table.attr("selectionLastRowIndex", this.rowIndex);
          }
        }
      }

      // A click can sometimes cause a text selection if the mouse
      // moved a little between mouse down and up. Such a text
      // selection is meaningless here, so disable it.
      window.getSelection().removeAllRanges();
    },

    /**
     * Returns the current table row selection, grouped by entity kind.
     *
     * The view table is scanned for selected rows. The entity ID, kind, and
     * access information for each selected row are extracted and used to
     * bin entities into an object with one property for each kind found.
     * The value of the property is an array containing one object for each
     * entity found of that property's kind. Each of those objects has 'id'
     * and 'access' properties containing the corresponding values for the
     * entity.
     *
     * @param {object} env
     *   The environment object.
     *
     * @return {object}
     *   The returned object contains one property for each entity kind
     *   found. The value for each property is an array of objects that each
     *   contain an entity ID and access grants for that entity.
     */
    tableGetSelectionIdsByKind(env) {
      const $tbody = env.gather.$tbody;
      const result = {};

      $(`tr.selected td.${env.gather.nameColumn} a`, $tbody).each((index, value) => {
        // Get the entity ID, kind, and access for the entity on the row.
        // If any of these is missing, the row is malformed and ignored.
        const entityId = $(value).attr("data-foldershare-id");
        const kind = $(value).attr("data-foldershare-kind");
        const disabled = $(value).attr("data-foldershare-disabled");
        let access = $(value).attr("data-foldershare-access");
        const ownerid = $(value).attr("data-foldershare-ownerid");
        const extension = $(value).attr("data-foldershare-extension");

        if (typeof entityId === "undefined" ||
          typeof kind === "undefined" ||
          typeof access === "undefined" ||
          typeof ownerid === "undefined") {
          // Fail. Something is missing. Ignore the row.
          return true;
        }

        if (typeof disabled !== "undefined" && disabled === true) {
          // Fail. Item is disabled. Ignore the row.
          return true;
        }

        // Parse the access list into an array.
        try {
          access = access.split(",");
        } catch (err) {
          // Fail. Parse error. Ignore the row.
          return true;
        }

        // Add this row into the selection. Use the row kind to group
        // rows, and save the entity ID and access array.
        if (typeof result[kind] === "undefined") {
          result[kind] = [];
        }

        const ownedbyuser =
          typeof $(value).attr("data-foldershare-ownedbyuser") !== "undefined";
        const ownedbyanonymous =
          typeof $(value).attr("data-foldershare-ownedbyanonymous") !== "undefined";
        const ownedbyanother =
          typeof $(value).attr("data-foldershare-ownedbyanother") !== "undefined";
        const sharedbyuser =
          typeof $(value).attr("data-foldershare-sharedbyuser") !== "undefined";
        const sharedwithusertoview =
          typeof $(value).attr("data-foldershare-sharedwithusertoview") !== "undefined";
        const sharedwithusertoauthor =
          typeof $(value).attr("data-foldershare-sharedwithusertoauthor") !== "undefined";
        const sharedwithanonymoustoview =
          typeof $(value).attr("data-foldershare-sharedwithanonymoustoview") !== "undefined";
        const sharedwithanonymoustoauthor =
          typeof $(value).attr("data-foldershare-sharedwithanonymoustoauthor") !== "undefined";

        result[kind].push({
          id: entityId,
          access: access,
          extension: extension,
          ownerid: ownerid,
          ownedbyuser: ownedbyuser,
          ownedbyanonymous: ownedbyanonymous,
          ownedbyanother: ownedbyanother,
          sharedbyuser: sharedbyuser,
          sharedwithusertoview: sharedwithusertoview,
          sharedwithusertoauthor: sharedwithusertoauthor,
          sharedwithanonymoustoview: sharedwithanonymoustoview,
          sharedwithanonymoustoauthor: sharedwithanonymoustoauthor
        });

        return true;
      });

      return result;
    },

    /**
     * Returns the current table row selection as an array of entity IDs.
     *
     * The view table is scanned for selected rows. The entity ID for each
     * selected row is added to an array and the array returned.
     *
     * @param {object} env
     *   the environment object.
     *
     * @return {int[]}
     *   Returns an array of entity IDs for selected rows.
     */
    tableGetSelectionIds(env) {
      const $tbody = env.gather.$tbody;
      const result = [];

      $(`tr.selected td.${env.gather.nameColumn} a`, $tbody).each((index, value) => {
        const entityId = $(value).attr("data-foldershare-id");
        if (typeof entityId === "undefined") {
          // Fail. ID is missing. Ignore the row.
          return true;
        }
        result.push(entityId);
        return true;
      });

      return result;
    },

    /*--------------------------------------------------------------------
     *
     * Validate.
     *
     *--------------------------------------------------------------------*/

    /**
     * Validates that the selection meets a command's constraints.
     *
     * Each command has selection constraints that limit the command to
     * apply only to files, folders, or root folders, or some combination
     * of these.
     *
     * This mimics similar checking on the server and is used to disable
     * command menu items that cannot be chosen in the current context.
     *
     * @param {object} env
     *   The environment object.
     * @param {int} nSelected
     *   The number of items selected.
     * @param {object} selection
     *   An array of selected entity kinds, each with an array of entity Ids.
     * @param {string} commandId
     *   A ID of the command to check for use with the selection.
     *
     * @return {boolean}
     *   Returns true if the command's selection constraints are met in this
     *   context, and false otherwise.
     */
    checkSelectionConstraints(env, nSelected, selection, commandId) {
      //
      // Setup
      // -----
      // Get the command's selection constraints.
      const constraints =
        env.settings.foldershare.commands[commandId].selectionConstraints;

      //
      // Selection type (size) suitable.
      // -------------------------------
      // Insure the selection size is compatible with the command.
      //
      // If the command does not use a selection, then we can skip all of this.
      const types = constraints.types;

      if ($.inArray("none", types) !== -1) {
        // Command expects NO selection.
        //
        // If there isn't one, return TRUE. Otherwise FALSE.
        //
        // We can return immediately without further selection constraint
        // checking because the command doesn't use a selection.
        if (nSelected === 0) {
          return true;
        }
        return false;
      }

      let selectionIsPageEntity = false;

      // The command uses a selection, so check it.
      if (nSelected === 0) {
        // There is no selection.
        //
        // Does the command support defaulting to operating on the page entity,
        // if there is one?
        const pageEntityId = env.settings.foldershare.page.id;
        if (pageEntityId >= 0 && $.inArray("parent", types) !== -1) {
          // There is a page entity, and this command accepts defaulting the
          // selection to the parent. So create a fake selection for the
          // remainder of this function"s checking.
          const pageKind = env.settings.foldershare.page.kind;
          const pageAccess = env.settings.foldershare.user.pageAccess;
          selection[pageKind] = {
            id: pageEntityId,
            access: pageAccess
          };
          nSelected = 1;
          selectionIsPageEntity = true;
        } else {
          // The command does not default to the page entity when there is no
          // selection, and we already checked that the command does not
          // work when there is no selection.
          return false;
        }
      } else if (nSelected === 1) {
        // There is a single item selected
        if ($.inArray("one", types) === -1) {
          // But the command does not support having just one item.
          if ($.inArray("many", types) === -1) {
            // But the command does not support having many items either.
            return false;
          }
          return false;
        }
      } else if ($.inArray("many", types) === -1) {
        // There are multiple items selected.
        // But the command does not support having multiple items.
        return false;
      }

      //
      // Selection kinds suitable.
      // -------------------------
      // Insure the kinds of items in the selection are compatible with
      // the command.
      const allowedKinds = constraints.kinds;

      if ($.inArray("any", allowedKinds) === -1) {
        // Command has specific kind requirements.
        //
        // Loop through the selection and make sure each item's
        // kind is allowed.
        let result = true;
        Object.keys(selection).forEach(kind => {
          if ($.inArray(kind, allowedKinds) === -1) {
            // Kind not supported by this command.
            if (selectionIsPageEntity === true) {
              selection = {};
            }
            result = false;
          }
        });

        if (result === false) {
          return false;
        }
      }

      //
      // Selection ownership suitable.
      // -----------------------------
      // Insure the ownership of the items in the selection is compatible
      // with the command.
      const allowedOwnership = constraints.ownership;

      if ($.inArray("any", allowedOwnership) === -1) {
        // Command has specific ownership requirements.
        //
        // Loop through the selection and make sure each item's
        // ownership is allowed.
        let result = false;
        Object.keys(selection).forEach(kind => {
          const items = selection[kind];
          for (let i = 0, len = items.length; i < len; ++i) {
            allowedOwnership.forEach((value) => {
              if (items[i][value] === true) {
                result = true;
              }
            });
          }
        });

        if (result === false) {
          return false;
        }
      }

      //
      // Selection file name extension suitable.
      // ---------------------------------------
      // Insure the file name extensions used by the items in the selection
      // are all compatible with the command.
      const allowedExtensions = constraints.fileExtensions;

      if (allowedExtensions.length !== 0) {
        // Command has specific file name extension requirements.
        //
        // Loop through the selection and make sure each item's
        // extension is allowed.
        let result = true;
        Object.keys(selection).forEach(kind => {
          const items = selection[kind];
          for (let i = 0, len = items.length; i < len; ++i) {
            if ($.inArray(items[i].extension, allowedExtensions) === -1) {
              result = false;
            }
          }
        });

        if (result === false) {
          return false;
        }
      }

      //
      // Selection access suitable.
      // --------------------------
      // Insure each selected item allows the command's single access.
      const allowedAccess = constraints.access;

      if (allowedAccess !== "none") {
        // The command has specific access requirements. Loop through
        // the selection and make sure each selected item grants the
        // required access.
        let result = true;
        Object.keys(selection).forEach(kind => {
          const items = selection[kind];
          for (let i = 0, len = items.length; i < len; ++i) {
            if ($.inArray(allowedAccess, items[i].access) === -1) {
              if (selectionIsPageEntity === true) {
                selection = {};
              }
              result = false;
            }
          }
        });

        if (result === false) {
          return false;
        }
      }

      if (selectionIsPageEntity === true) {
        selection = {};
      }

      return true;
    }
  };

  /*--------------------------------------------------------------------
   *
   * On Drupal ready behaviors.
   *
   * Set up behaviors to execute when the page is fully loaded, or whenever
   * AJAX sends a new page fragment.
   *
   *--------------------------------------------------------------------*/

  Drupal.behaviors.foldershare_UIFolderTableMenu = {
    attach(pageContext, settings) {
      Drupal.foldershare.UIFolderTableMenu.attach(pageContext, settings);
    }
  };
})(jQuery, Drupal);
