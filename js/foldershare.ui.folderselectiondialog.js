/**
 * @file
 * Implements the FolderShare folder selection dialog user interface.
 *
 * A folder selection dialog is used by commands like "move" and "copy" to
 * select a destination folder. The dialog is a light version of a normal
 * file/folder list where all files in the list are disabled and only folders
 * may be selected.
 *
 * This script supports selecting folder rows in a dialog's file/folder list.
 * Rows can only be selected individually. Double-clicking a row opens the
 * row's folder in the dialog. Selecting a choice from an ancestor menu
 * opens a parent folder in the dialog.
 *
 * The dialog communicates with the server in three ways:
 * - Selecting an ancestor menu choice, clicking on a row link, or
 *   double-clicking on a row sets the hidden "parentid" field, which
 *   triggers an AJAX round-trip to the server that returns a new
 *   folder list.
 *
 * - Selecting a folder sets the hidden "currentselection" field, but does
 *   not trigger a form submit.
 *
 * - Selecting the form's submit button triggers an AJAX round-trip to the
 *   server that may close the dialog or update it in some way.
 *
 * This script requires HTML elements added by a table view that uses a name
 * field formatter that attaches attributes to name field anchors. This script
 * uses those attributes to guide prevalidation of menu items as appropriate
 * for selected rows.
 *
 * This script also requires an HTML form that provides:
 * - A field to hold the current selection.
 * - A field to hold the new parent.
 * - A form submit button.
 *
 * @ingroup foldershare
 * @see \Drupal\foldershare\Plugin\Field\FieldFormatter\FolderShareFolderOnlyName
 * @see \Drupal\foldershare\Form\CommandFormWrapper
 */
(function($, Drupal) {
  // Check pre-requisits.
  //
  // The utility library must have been loaded before this script.
  if ("foldershare" in Drupal === false ||
    "utility" in Drupal.foldershare === false) {
    console.log(
      "%cFolderShare: Javascript files included in wrong order%c\n%cfoldershare.ui.folderselectiondialog.js requires that foldershare.ui.utility.js be included first.",
      "font-weight: bold",
      "font-weight: normal",
      "padding-left: 2em",
      "padding-left: 0");
    window.stop();
  }

  Drupal.foldershare.FolderSelectionDialog = {
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
     * Attaches the module's folder selection dialog UI behaviors.
     *
     * The UI includes
     * - Table row selection.
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
      const thisScript = Drupal.foldershare.FolderSelectionDialog;

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
      // <div class="foldershare-toolbar-and-folder-selection-table">
      //   <div class="foldershare-toolbar">...</div>
      //   <div class="foldershare-folder-selection-table">...</div>
      // </div>
      //
      // The toolbar <div> contains zero or more other UI components.
      //
      // The table <div> contains a wrapper <div> from Views. It includes
      // two <div>s for the view's content and footer. The view content
      // contains a wrapper <div> around a <form>. And the form contains
      // exposed filters and other views-supplied form elements, and
      // finally it includes a <table> that contains rows of files and
      // folders.
      //
      // <div class="foldershare-toolbar-and-folder-selection-table"> Top of UI
      //   <div class="foldershare-toolbar">...</div>         Toolbar
      //   <div class="foldershare-folder-selection-table">   Table wrapper
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
      const featureClass = "foldershare-folder-selection";
      const featureSelector = `.${featureClass}`;
      let $featureRoots;

      // The page context can be any of:
      // - The full document.
      // - The feature root we want.
      // - An element above the feature root we want.
      // - An element below the feature root we want.
      if (typeof pageContext.tagName === "undefined") {
        // The full document. Search down.
        $featureRoots = $(featureSelector, pageContext);
      } else if ($(pageContext).hasClass(featureClass) === true) {
        // Feature root. No search needed.
        $featureRoots = $(pageContext);
      } else {
        // Above or below. Search up first, then down.
        $featureRoots = $(pageContext).parents(featureSelector);
        if ($featureRoots.length === 0) {
          $featureRoots = $(featureSelector, pageContext);
        }
      }

      if ($featureRoots.length === 0) {
        // Fail. The document does not contain the feature root.
        return true;
      }

      //
      // Process top elements
      // --------------------
      // Process each top element. There should be only one.
      const envList = [];
      $featureRoots.each((index, element) => {
        // Create a new environment object.
        const env = {
          settings: settings,
          $featureRoot: $(element)
        };

        //
        // Gather configuration
        // --------------------
        // Find form elements, and the table of files and folders.
        if (thisScript.gather(env) === false) {
          // Fail. UI elements could not be found.
          return true;
        }

        //
        // Build UI
        // --------
        // Build the UI and attach its behaviors.
        if (thisScript.build(env) === false) {
          // Fail. Something didn't work
          return true;
        }

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
     * - An input field for the parent ID to trigger an AJAX reload.
     * - An input field for the current folder selection.
     * - An <input> to submit the command form.
     *
     * This function searches for the UI's elements and saves them
     * into the environment:
     * - env.gather.$table = the <table> containing the file/folder list.
     * - env.gather.$destinationIdInput = the destination ID <input>.
     * - env.gather.$refreshButton = the destination view refresh button.
     * - env.gather.$selectionIdInput = the selection ID <input>.
     * - env.gather.nameColumn = the table column name for the name & attrib.
     * - env.gather.$ancestorMenu = the ancestor menu.
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
      const nameColumn = "views-field-name";
      const $featureRoot = env.$featureRoot;

      //
      // Find inputs
      // -----------
      // The form contains several <input> items used to hold information
      // from the UI operation:
      // - A selection ID <input>.
      // - A destination ID <input>.
      // - A destination refresh <button> or <input>.
      const $selectionIdInput = $(
        'input[name="selectionid"]',
        $featureRoot).eq(0);
      if ($selectionIdInput.length === 0) {
        utility.printMalformedError("The UI selection ID field is missing.");
        return false;
      }

      const $destinationIdInput = $(
        'input[name="destinationid"]',
        $featureRoot).eq(0);
      if ($destinationIdInput.length === 0) {
        utility.printMalformedError("The UI destination ID field is missing.");
        return false;
      }

      let $refreshButton = $(
        'input[name="refresh"]',
        $featureRoot).eq(0);
      if ($refreshButton.length === 0) {
        $refreshButton = $(
          'button[name="refresh"]',
          $featureRoot).eq(0);
        if ($refreshButton.length === 0) {
          utility.printMalformedError("The UI refresh button is missing.");
          return false;
        }
      }

      //
      // Find table
      // ----------
      // Search for a views <table>.
      cls = "views-table";
      const $table = $(`table.${cls}`, $featureRoot).eq(0);
      if ($table.length === 0) {
        utility.printMalformedError(
          `The required <table> with class '${cls}' could not be found.`);
        return false;
      }

      //
      // Find ancestor menu
      // ------------------
      // Search for the ancestor menu on the toolbar. It may not be present.
      let $ancestorMenu = $('.foldershare-ancestormenu-menu', $featureRoot).eq(0);
      if ($ancestorMenu.length === 0) {
console.log('ancestor menu not found');
        $ancestorMenu = null;
      }

      //
      // Update environment
      // ------------------
      // Save main UI objects.
      env.gather = {
        $table: $table,
        $tbody: $table.find("tbody"),
        $thead: $table.find("thead"),
        nameColumn: nameColumn,
        $selectionIdInput: $selectionIdInput,
        $destinationIdInput: $destinationIdInput,
        $refreshButton: $refreshButton,
        $ancestorMenu: $ancestorMenu
      };

      return true;
    },

    /*--------------------------------------------------------------------
     *
     * Build the UI.
     *
     *--------------------------------------------------------------------*/

    /**
     * Builds the UI.
     *
     * The main UI has several features:
     * - Selectable rows in the view table.
     *
     * @param {object} env
     *   The environment object.
     *
     * @return {boolean}
     *   Returns TRUE on success and FALSE otherwise.
     */
    build(env) {
      const thisScript = Drupal.foldershare.FolderSelectionDialog;
      const pageDisabled = env.settings.foldershare.page.disabled;

      //
      // Add table behaviors
      // -------------------
      // Add table and table row behaviors, such as for row selection,
      // drag-and-drop, and the context menu.
      if (pageDisabled !== true) {
        thisScript.tableAttachBehaviors(env);
      }

      //
      // Refresh view on ancestor menu anchor click.
      // -------------------------------------------
      // For each ancestor menu item, add a click behavior that overrides
      // the anchor default behavior and refreshes the view to show the
      // clicked item's folder content.
      if (env.gather.$ancestorMenu !== null) {
        const $menu = env.gather.$ancestorMenu.menu();
        $menu.off("menuselect.foldershare");
        $menu.on("menuselect.foldershare", (ev, ui) => {
          $menu.menu().hide();
          const id = $(ui.item).attr("data-foldershare-id");
          env.gather.$destinationIdInput.val(id);
          env.gather.$refreshButton.click();
          ev.preventDefault();
          return false;
        });
      }

      return true;
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
     *--------------------------------------------------------------------*/

    /**
     * Attaches behaviors to the table.
     *
     * This method attaches row behaviors to support row selection
     * using mouse and touch events.
     *
     * @param {object} env
     *   The environment object.
     */
    tableAttachBehaviors(env) {
      const $tbody = env.gather.$tbody;
      const thisScript = Drupal.foldershare.FolderSelectionDialog;

      //
      // Refresh view on row double-click.
      // ---------------------------------
      // For each body row, add a double-click behavior that refreshes the
      // view to show the double-clicked row's folder content.
      $("tr", $tbody).off("dblclick.foldershare");
      $("tr", $tbody).on("dblclick.foldershare", function(e) {
        thisScript.tableDoubleClick.call(this, e, env);
      });

      //
      // Refresh view on file/folder name anchor click.
      // ----------------------------------------------
      // For each body row, add a click behavior that overrides the anchor
      // default behavior and refreshes the view to show the clicked row's
      // folder content.
      const sel = `tr td.${env.gather.nameColumn} a`;
      $(sel, $tbody).off("click.foldershare");
      $(sel, $tbody).on("click.foldershare", function(e) {
        thisScript.anchorClick.call(this, e, env);
      });

      //
      // Select on row click or touch.
      // -----------------------------
      // For each body row, add behaviors that respond to mouse clicks and
      // touch screen touches.
      $("tr", $tbody).once("row-click").on("click.foldershare", function(e) {
        thisScript.tableSelect.call(this, e, env);
      });

      $("tr", $tbody).once("row-touch").on("touchend.foldershare", function(e) {
        thisScript.tableSelect.call(this, e, env);
      });
    },

    /*--------------------------------------------------------------------
     *
     * Table behaviors - select.
     *
     *--------------------------------------------------------------------*/

    /**
     * Handles a double-click on a table row.
     *
     * A double-click sets the dialog form's destination ID field and triggers
     * a refresh of the view.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     */
    tableDoubleClick(ev, env) {
      const $tr = $(this);

      // Get the anchor for the double-clicked row.
      const $tdLinkName = $(`td.${env.gather.nameColumn} a`, $tr);
      if ($tdLinkName.length === 0) {
        // Fail. No linked name column. Ignore the row.
        ev.preventDefault();
        return false;
      }

      // Ignore disabled rows.
      const disabled = $tdLinkName.attr("data-foldershare-disabled");
      if (typeof disabled !== "undefined" && disabled === true) {
        // Fail. Item is disabled. Ignore the row.
        ev.preventDefault();
        return false;
      }

      // Get the item's ID.
      const entityId = $tdLinkName.attr("data-foldershare-id");
      if (typeof entityId === "undefined") {
        // Fail. ID is missing. Ignore the row.
        ev.preventDefault();
        return false;
      }

      // Push the ID into the parent ID field. This triggers an AJAX
      // request to the server that replaces the current dialog body.
      env.gather.$destinationIdInput.val(entityId);
      env.gather.$refreshButton.click();
      ev.preventDefault();
      return false;
    },

    /**
     * Handles a click on a file/folder name anchor on a table row.
     *
     * A click sets the dialog form's destination ID field and triggers
     * a refresh of the view.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     */
    anchorClick(ev, env) {
      const $a = $(this);

      // Ignore disabled rows.
      const disabled = $a.attr("data-foldershare-disabled");
      if (typeof disabled !== "undefined" && disabled === true) {
        // Fail. Item is disabled. Ignore the row.
        ev.preventDefault();
        return false;
      }

      // Get the item's ID.
      const entityId = $a.attr("data-foldershare-id");
      if (typeof entityId === "undefined") {
        // Fail. ID is missing. Ignore the row.
        ev.preventDefault();
        return false;
      }

      // Push the ID into the parent ID field. This triggers an AJAX
      // request to the server that replaces the current dialog body.
      env.gather.$destinationIdInput.val(entityId);
      env.gather.$refreshButton.click();
      ev.preventDefault();
      return false;
    },

    /**
     * Handles a touch or mouse selection event on a table row.
     *
     * Selection toggles the selected item on/off. It ignores keyboard
     * modifiers and therefore does not support range selection.
     *
     * @param {object} ev
     *   The row event to handle.
     * @param {object} env
     *   The environment object.
     */
    tableSelect(ev, env) {
      const $tr = $(this);
      const $table = env.gather.$table;
      const $tbody = env.gather.$tbody;

      // Get the anchor for the double-clicked row.
      const $tdLinkName = $(`td.${env.gather.nameColumn} a`, $tr);
      if ($tdLinkName.length === 0) {
        // Fail. No linked name column. Ignore the row.
        return;
      }

      // Ignore disabled rows.
      const disabled = $tdLinkName.attr("data-foldershare-disabled");
      if (typeof disabled !== "undefined" && disabled === true) {
        // Fail. Item is disabled. Ignore the row.
        return;
      }

      // For out of range row indexes (<1), clear the selection.
      // Otherwise toggle the row selection and update the form field.
      if (this.rowIndex <= 0) {
        // Header/footer click. Clear the selection.
        $("tr", $tbody).each((index, value) => {
          $(value).toggleClass("selected", false);
        });

        env.gather.$selectionIdInput.val(-1);
      } else {
        // Row click. Set the selection.
        const newState = !$tr.hasClass("selected");
        $tr.toggleClass("selected", newState);

        if (newState === false) {
          env.gather.$selectionIdInput.val(-1);
        } else {
          // Get the item's ID.
          const entityId = $tdLinkName.attr("data-foldershare-id");
          if (typeof entityId === "undefined") {
            // Fail. ID is missing. Ignore the row.
            env.gather.$selectionIdInput.val(-1);
          } else {
            env.gather.$selectionIdInput.val(entityId);
          }
        }
      }

      // Some browsers will also send mouse events after a touch event.
      // Such a "ghost click" is not useful here, so disable it.
      ev.preventDefault();
    },
  };

  /*--------------------------------------------------------------------
   *
   * On Drupal ready behaviors.
   *
   * Set up behaviors to execute when the page is fully loaded, or whenever
   * AJAX sends a new page fragment.
   *
   *--------------------------------------------------------------------*/

  Drupal.behaviors.foldershare_FolderSelectionDialog = {
    attach(pageContext, settings) {
      Drupal.foldershare.FolderSelectionDialog.attach(pageContext, settings);
    }
  };
})(jQuery, Drupal);
