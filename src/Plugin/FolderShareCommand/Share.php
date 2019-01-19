<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;

/**
 * Defines a command plugin to change share settings on a root item.
 *
 * The command sets the access grants for the root item of the selected
 * entity. Access grants enable/disable view and author access for
 * individual users.
 *
 * Configuration parameters:
 * - 'parentId': the parent folder, if any.
 * - 'selectionIds': selected entity who's root item is shared.
 * - 'grants': the new access grants.
 *
 * @ingroup foldershare
 *
 * @FolderShareCommand(
 *  id              = "foldersharecommand_share",
 *  label           = @Translation("Share"),
 *  menuNameDefault = @Translation("Share..."),
 *  menuName        = @Translation("Share..."),
 *  description     = @Translation("Share files and folders"),
 *  category        = "settings",
 *  weight          = 10,
 *  parentConstraints = {
 *    "kinds"   = {
 *      "rootlist",
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
 *    "access"  = "share",
 *  },
 * )
 */
class Share extends FolderShareCommandBase {

  /*--------------------------------------------------------------------
   *
   * Configuration.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // Include room for the new grants in the configuration.
    $config = parent::defaultConfiguration();
    $config['grants'] = [];
    return $config;
  }

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function hasConfigurationForm() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(bool $forPage) {
    // The description varies for page vs. dialog:
    //
    // - Dialog: The description is longer and has the form "Adjust share
    //   settings for NAME, and all of its contents."
    //
    // - Page: The description is as for a dialog, except the name is not
    //   included because it is already in the title.
    $selectionIds = $this->getSelectionIds();
    $item = FolderShare::load(reset($selectionIds));

    if ($forPage === TRUE) {
      // Page description. The page title already gives the name of the
      // item. Don't include the item's name again here.
      if ($item->isFolder() === TRUE) {
        return t(
          'Adjust share settings for this folder, and all of its contents.',
          [
            '@name' => $item->getName(),
          ]);
      }

      return t(
        'Adjust share settings for this @operand.',
        [
          '@operand' => Utilities::translateKind($item->getKind()),
        ]);
    }

    // Dialog description. Include the name of the item to be changed.
    if ($item->isFolder() === TRUE) {
      return t(
        'Adjust share settings for "@name", and all of its contents.',
        [
          '@name' => $item->getName(),
        ]);
    }

    return t(
      'Adjust share settings for "@name".',
      [
        '@name' => $item->getName(),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(bool $forPage) {
    // The title varies for page vs. dialog:
    //
    // - Dialog: The title is short and has the form "Share OPERAND",
    //   where OPERAND is the kind of item (e.g. "file"). By not putting
    //   the item's name in the title, we keep the dialog title short and
    //   avoid cropping or wrapping.
    //
    // - Page: The title is longer and has the form "Share "NAME"?"
    //   This follows Drupal convention.
    $selectionIds = $this->getSelectionIds();
    $item = FolderShare::load(reset($selectionIds));

    if ($forPage === TRUE) {
      // Page title. Include the name of the item.
      return t(
        'Share "@name"',
        [
          '@name' => $item->getName(),
        ]);
    }

    // Dialog title. Include the operand kind.
    return t(
      'Share @operand',
      [
        '@operand' => Utilities::translateKind($item->getKind()),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitButtonName() {
    return t('Save');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {

    // Always a selection that is a root item.
    $selectionIds = $this->getSelectionIds();
    $item = FolderShare::load(reset($selectionIds));

    //
    // Get current user permissions
    // ----------------------------
    // Does the current user have permission to share with other users
    // and/or share with the public?
    $currentUser = \Drupal::currentUser();
    $hasShareWithUsersPermission = AccessResult::allowedIfHasPermission(
      $currentUser,
      Constants::SHARE_PERMISSION)->isAllowed();
    $hasShareWithPublicPermission = AccessResult::allowedIfHasPermission(
      $currentUser,
      Constants::SHARE_PUBLIC_PERMISSION)->isAllowed();

    //
    // Get user info
    // -------------
    // The returned list has UID keys and values that are arrays with one
    // or more of:
    //
    // '' = no grants.
    // 'view' = granted view access.
    // 'author' = granted author access.
    //
    // The returned array cannot be empty. It always contains at least
    // the owner of the folder, who always has 'view' and 'author' access.
    $grants = $this->getAccessGrants($item);

    // Load all referenced users. We need their display names for the
    // form's list of users. Add the users into an array keyed by
    // the display name, then sort on those keys so that we can create
    // a sorted list in the form.
    $loadedUsers = User::loadMultiple(array_keys($grants));
    $users = [];
    foreach ($loadedUsers as $user) {
      $users[$user->getDisplayName()] = $user;
    }

    ksort($users, SORT_NATURAL);

    // Get the anonymous user. This user always exists and is always UID = 0.
    $anonymousUser = User::load(0);

    // Save the root folder for use when the form is submitted later.
    $this->entity = $item;

    $ownerId = $item->getOwnerId();
    $owner   = User::load($ownerId);

    //
    // Start table of users and grants
    // -------------------------------
    // Create a table that has 'User' and 'Access' columns.
    $form[Constants::MODULE . '_share_table'] = [
      '#type'       => 'table',
      '#attributes' => [
        'class'     => [Constants::MODULE . '-share-table'],
      ],
      '#header'     => [
        t('User'),
        t('Access'),
      ],
    ];

    //
    // Define 2nd header
    // -----------------
    // The secondary header breaks the 'Access' column into
    // three child columns (visually) for "None", "View", and "Author".
    // CSS makes these child columns fixed width, and the same width
    // as the radio buttons in the rows below so that they line up.
    $rows    = [];
    $tnone   = t('None');
    $tview   = t('View');
    $tauthor = t('Author');
    $tmarkup = '<span>' . $tnone . '</span><span>' .
      $tview . '</span><span>' . $tauthor . '</span>';
    $rows[]  = [
      'User' => [
        '#type'       => 'item',
        // Do not include markup for user column since we need it to be
        // blank for this row.
        '#markup'     => '',
        '#value'      => (-1),
        '#attributes' => [
          'class'     => [Constants::MODULE . '-share-subheader-user'],
        ],
      ],
      'Access'        => [
        '#type'       => 'item',
        '#markup'     => $tmarkup,
        '#attributes' => [
          'class'     => [Constants::MODULE . '-share-subheader-grants'],
        ],
      ],
      '#attributes'   => [
        'class'       => [Constants::MODULE . '-share-subheader'],
      ],
    ];

    //
    // Add anonymous user row
    // ----------------------
    // The 1st row is always for generic anonymous access. Only include
    // the row if sharing with anonymous is allowed for the user.
    if ($hasShareWithPublicPermission === TRUE) {
      $rows[] = $this->buildRow(
        $anonymousUser,
        $owner,
        $grants[0]);
    }

    //
    // Add user rows
    // -------------
    // Go through each of the listed users and add a row. Only include
    // these if sharing with users is allowed for the user.
    if ($hasShareWithUsersPermission === TRUE) {
      foreach ($users as $user) {
        // Skip NULL users. This may occur when a folder included a grant to
        // a user account that has since been deleted.
        if ($user === NULL) {
          continue;
        }

        // Skip the anonymous user. This user has already been handled specially
        // as the first row of the table, if appropriate.
        if ($user->isAnonymous() === TRUE) {
          continue;
        }

        // Skip the owner.
        $uid = (int) $user->id();
        if ($uid === $ownerId) {
          continue;
        }

        // Skip blocked accounts.
        if ($user->isBlocked() === TRUE) {
          continue;
        }

        // Add the row.
        $rows[] = $this->buildRow(
          $user,
          $owner,
          $grants[$uid]);
      }
    }

    $form[Constants::MODULE . '_share_table'] = array_merge(
      $form[Constants::MODULE . '_share_table'],
      $rows);

    return $form;
  }

  /**
   * Builds and returns a form row for a user.
   *
   * The form row includes the user column with the user's ID and a link
   * to their profile page.  Beside the user column, the row includes
   * one, two, or three radio buttons for 'none', 'view', and 'author'
   * access grants based upon the grants provided and the user's current
   * module permissions.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user for whom to build the row.
   * @param \Drupal\user\Entity\User $owner
   *   The owner of the current root item.
   * @param array $grant
   *   The access grants for the user.
   *
   * @return array
   *   The form table row description for the user.
   */
  private function buildRow(
    User $user,
    User $owner,
    array $grant = NULL) {

    //
    // Get account attributes
    // ----------------------
    // Watch for anonymous and blocked users, and the owner of the content.
    $isAnonymous = $user->isAnonymous();
    $isBlocked   = $user->isBlocked();
    $isOwner     = ((int) $user->id() === (int) $owner->id());

    //
    // Check user permissions
    // ----------------------
    // Get the user's role's permissions. Admins always have full permission.
    $entityType = \Drupal::entityTypeManager()
      ->getDefinition(FolderShare::ENTITY_TYPE_ID);

    $perm = $entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    $access = AccessResult::allowedIfHasPermission($user, $perm);
    $isAdmin = ($access->isAllowed() === TRUE);

    if ($isAdmin === TRUE) {
      $permittedToView = TRUE;
      $permittedToAuthor = TRUE;
    }
    else {
      $permittedToView =
        FolderShareAccessControlHandler::mayAccess('view', $user);
      $permittedToAuthor =
        FolderShareAccessControlHandler::mayAccess('update', $user);
    }

    //
    // Get grants for this root
    // ------------------------
    // Look at the access grant for this user on this root.
    if (empty($grant) === TRUE) {
      // There is no entry for the user. They have not been granted anything.
      $currentlyGrantedView = FALSE;
      $currentlyGrantedAuthor = FALSE;
    }
    else {
      // There is any entry for the user. Note what it says.
      $currentlyGrantedView = in_array('view', $grant);
      $currentlyGrantedAuthor = in_array('author', $grant);

      // If the user is explicitly granted author access, then automatically
      // include view access.
      if ($currentlyGrantedAuthor === TRUE) {
        $currentlyGrantedView = TRUE;
      }
    }

    //
    // Override for special cases
    // --------------------------
    // Disable rows for blocked accounts, the admin, and the owner.
    $rowDisabled = FALSE;

    // Reduce access grants if they don't have permissions.
    if ($permittedToView === FALSE) {
      // The user doesn't have view permission, so block view and author
      // grants.
      $currentlyGrantedView   = FALSE;
      $currentlyGrantedAuthor = FALSE;
    }

    if ($permittedToAuthor === FALSE) {
      // The user doesn't have author permission, so block author grants.
      $currentlyGrantedAuthor = FALSE;
    }

    if ($isAdmin === TRUE || $isBlocked === TRUE || $isOwner === TRUE) {
      // Admin users cannot have their sharing set. They always have access.
      // Blocked users cannot ahve their sharing set. They never have access.
      // Owners cannot have their sharing set. They always have access.
      $rowDisabled = TRUE;
    }

    //
    // Build row
    // ---------
    // Start by creating the radio button options array based upon the grants.
    $radios = ['none' => ''];
    $default = 'none';

    if ($permittedToView === TRUE) {
      // User has view permissions, so allow a 'view' choice.
      $radios['view'] = '';
    }

    if ($permittedToAuthor === TRUE) {
      // User has author permissions, so allow a 'author' choice.
      $radios['author'] = '';
    }

    if ($currentlyGrantedView === TRUE) {
      // User has been granted view access.
      $default = 'view';
    }

    if ($currentlyGrantedAuthor === TRUE) {
      // User has been granted author access (which for our purposes
      // includes view access).
      $default = 'author';
    }

    if ($permittedToView === FALSE) {
      // Disable the entire row if the user has no view permission.
      $rowDisabled = TRUE;
    }

    // Create an annotated user name.
    $name = $user->getDisplayName();
    if ($isAnonymous === TRUE) {
      $nameMarkup = t(
        '@name (any web site visitor)',
        [
          '@name' => $name,
        ]);
    }
    elseif ($isAdmin === TRUE) {
      $nameMarkup = t(
        '@name (content administrator)',
        [
          '@name' => $name,
        ]);
    }
    elseif ($isOwner === TRUE) {
      $nameMarkup = t(
        '@name (content owner)',
        [
          '@name' => $name,
        ]);
    }
    elseif ($isBlocked === TRUE) {
      $nameMarkup = t(
        '@name (blocked)',
        [
          '@name' => $name,
        ]);
      $rowDisabled = TRUE;
    }
    else {
      $nameMarkup = t(
        '@name',
        [
          '@name' => $name,
        ]);
    }

    // Create the row. Provide the user's UID as the row value, which we'll
    // use later during validation and submit handling. Show a link to the
    // user's profile.
    return [
      'User' => [
        '#type'          => 'item',
        '#value'         => $user->id(),
        '#markup'        => $nameMarkup,
        '#attributes'    => [
          'class'        => [Constants::MODULE . '-share-user'],
        ],
      ],

      // Disable the row if the user has no permissions.
      'Access' => [
        '#type'          => 'radios',
        '#options'       => $radios,
        '#default_value' => $default,
        '#disabled'      => $rowDisabled,
        '#attributes'    => [
          'class'        => [Constants::MODULE . '-share-grants'],
        ],
      ],
      '#attributes'      => [
        'class'          => [Constants::MODULE . '-share-row'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    // Always a selection that is a root item.
    $selectionIds = $this->getSelectionIds();
    $item = FolderShare::load(reset($selectionIds));

    // Get the original grants. The returned array is indexed by UID.
    $ownerId = $item->getOwnerId();
    $grants = [];
    $originalGrants = $this->getAccessGrants($item);

    // Copy the owner's grants forward, regardless of what may have been
    // set in the form.
    if (isset($originalGrants[$ownerId]) === TRUE) {
      // The owner always has full access, but copy this anyway.
      $grants[$ownerId] = $originalGrants[$ownerId];
    }

    if (isset($originalGrants[0]) === TRUE) {
      // Anonymous (UID = 0) may have been omitted from the table because
      // the site has disabled sharing with anonymous. But copy the previous
      // values anyway.
      $grants[0] = $originalGrants[0];
    }

    // Validate
    // --------
    // Validate that the new grants make sense.
    //
    // Loop through the form's table of users and access grants. For each
    // user, see if 'none', 'view', or 'author' radio buttons are set
    // and create the associated grant in a user-grant array.
    $values = $formState->getValue(Constants::MODULE . '_share_table');
    foreach ($values as $item) {
      // Get the user ID for this row.
      $uid = $item['User'];

      // Ignore negative user IDs, which is only used for the 2nd header
      // row that labels the radio buttons in the access column.
      if ($uid < 0) {
        continue;
      }

      // Ignore any row for the root item owner.
      if ($uid === $ownerId) {
        continue;
      }

      // Get the access being granted.
      switch ($item['Access']) {
        case 'view':
          $grant = ['view'];
          break;

        case 'author':
          // Author grants ALWAYS include view grants.
          $grant = [
            'view',
            'author',
          ];
          break;

        default:
          $grant = [];
          break;
      }

      // Save it.
      $grants[$uid] = $grant;
    }

    $this->configuration['grants'] = $grants;

    try {
      $this->validateParameters();
    }
    catch (\Exception $e) {
      $formState->setErrorByName(
        Constants::MODULE . '_share_table',
        $e->getMessage());
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $formState) {

    $this->execute();
  }

  /*---------------------------------------------------------------------
   *
   * Utilities.
   *
   * These utility functions help in creation of the configuration form.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns an array of users and their access grants for a root item.
   *
   * The returned array has one entry per user. The array key is the
   * user's ID, and the array value is an array with one or more of:
   * - 'view'
   * - 'author'
   *
   * In normal use, the array for a user contains only one of these
   * possibilities:
   * - ['view'] = the user only has view access to the folder.
   * - ['view', 'author'] = the user has view and author access to the folder.
   *
   * While it is technically possible for a user to have 'author', but no
   * 'view' access, this is a strange and largely unusable configuration
   * and one this form does not support.
   *
   * @param \Drupal\foldershare\FolderShareInterface $root
   *   The root item object queried to get a list of users and their
   *   access grants.
   *
   * @return string[]
   *   The array of access grants for the folder.  Array keys are
   *   user IDs, while array values are arrays that contain values
   *   'view', and/or 'author'.
   *
   * @todo Reduce this function so that it only returns a list of users
   * with explicit view or author access to the folder. Do
   * not include all users at the site. However, this can only be done
   * when the form is updated to support add/delete users.
   */
  private function getAccessGrants(FolderShareInterface $root) {
    // Get the folder's access grants.
    $grants = $root->getAccessGrants();

    // For now, add all other site users and default them to nothing.
    foreach (\Drupal::entityQuery('user')->execute() as $uid) {
      if (isset($grants[$uid]) === FALSE) {
        $grants[$uid] = [];
      }
    }

    return $grants;
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
    // Always a selection that is a root item.
    $selectionIds = $this->getSelectionIds();
    $item = FolderShare::load(reset($selectionIds));

    try {
      $item->share($this->configuration['grants']);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    // Flush the render cache because sharing may have changed what
    // content is viewable throughout the folder tree under this root.
    Cache::invalidateTags(['rendered']);

    if (Constants::ENABLE_UI_COMMAND_REPORT_NORMAL_COMPLETION === TRUE) {
      \Drupal::messenger()->addMessage(
        t("Share settings have been updated."),
        'status');
    }
  }

}
