<?php

/**
 * @file
 * Implements help hooks for the module.
 */

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\Entity\User;

use Drupal\foldershare\Branding;
use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;

/**
 * Implements hook_help().
 *
 * This function responds to the help route for the FolderShare module
 * and provides a single page with multiple sections to document the
 * purpose, uses, permissions, and access control mechanisms of the
 * module.
 */
function foldershare_help(string $routeName, RouteMatchInterface $routeMatch) {
  //
  // The function returns a render array containing multiple help sections,
  // partly mandated by Drupal best practices.
  //
  // Check the route.
  $helpRoute = Constants::ROUTE_HELP . '.' . Constants::MODULE;
  if ($routeName !== $helpRoute) {
    // The route is not for our help page. Do nothing.
    return;
  }

  //
  // Setup
  // -----
  // Create class names and get the module's human-readable title.
  $pageClass = Constants::MODULE . '-admin-help';
  $moduleTitle = \Drupal::service('module_handler')->getName(Constants::MODULE);

  // Get routed links to the module's settings and permissions.
  $moduleSettings = Utilities::createRouteLink(
    Constants::ROUTE_SETTINGS,
    '',
    'module settings');
  $permissionsSettings = Utilities::createRouteLink(
    'user.admin_permissions',
    'module-' . Constants::MODULE,
    'module permissions');

  $anonymousUser = User::load(0);
  $anonymousName = $anonymousUser->getDisplayName();

  //
  // About
  // -----
  // This section is recommended by Drupal best practices.
  $help = '';
  $help .= '<h4>' . t('About') . '</h4>';
  $help .= '<p>' . t(
    '<strong>@moduletitle</strong> manages shared folders that contain files and subfolders. Folders may be nested within other folders to create a folder tree similar to a file system on Windows, Mac, or Linux systems. Files are stored on the web server and tracked in the web site database. Access is controlled using web site user accounts, role-based permissions, and special folder tree access grants (a.k.a. access control lists) provided by this module.',
    [
      '@moduletitle' => $moduleTitle,
    ]) . '</p>';

  //
  // Uses
  // ----
  // This section is recommended by Drupal best practices.
  $help .= '<h4>' . t('Uses') . '</h4>';
  $help .= '<p>' . t(
    'Users of <strong>@moduletitle</strong> may create and edit a folder tree of shared files and folders managed by the web site. Users may upload files into folders, and move, copy, rename, search, compress, delete, and download files and folders.',
    [
      '@moduletitle' => $moduleTitle,
      '@anonymous'   => $anonymousName,
    ]) . '</p>';
  $help .= '<p>' . t(
    'By default, users have exclusive access to their files and folders, but users may elect to share folder trees with specific users and allow them to view and edit folder content. Users also may elect to publish their content for world access by sharing their content with the site\'s generic "@anonymous" user.',
    [
      '@anonymous'   => $anonymousName,
    ]) . '</p>';
  $help .= '<p>' . t(
    "These abilities may be used to organize files and folders that contain personal or shared documents, image libraries, videos, or current work by a work group. Access controls enable users to limit access to specific friends and collaborators and treat the web site's folders as a shared work space for a project.") . '</p>';

  //
  // Permissions
  // -----------
  // Explain role-based permissions.
  $help .= '<h4>' . t('Permissions') . '</h4>';
  $help .= '<p>' . t(
    "<strong>@moduletitle's</strong> @perm are assigned to user roles to give broad categories of users the ability to view, author, share, and administer file and folder content. These permissions work together with access controls that can grant access to specific users for specific folder trees. The module's permissions include:",
    [
      '@moduletitle' => $moduleTitle,
      '@perm'        => $permissionsSettings,
    ]) . '</p>';

  $help .= '<dl>';
  $help .= '<dt><em>' . t('View files and folders') . '</em></dt>';
  $help .= '<dd>' . t(
    "Users with this permission may view and download their own content. When a user has this permission and they are also granted view access to specific shared content, the user may view and download that shared content too.") . '</dd>';
  $help .= '<dd>' . t(
    "This permission is usually granted to all users, including the anonymous user.") . '</dd>';
  $help .= '<dd>' . t(
    "If the anonymous user does not have view permission, then site visitors that do not have accounts cannot view any files and folders.") . '</dd>';

  $help .= '<dt><em>' . t('Author files and folders') . '</em></dt>';
  $help .= '<dd>' . t(
    "Users with this permission may create, delete, and modify their own content. When a user has this permission and they are also granted author access to specific shared content, the user may create, delete, and modify that shared content too.") . '</dd>';
  $help .= '<dd>' . t(
    "This permission is usually only enabled for authenticated users.") . '</dd>';

  $help .= '<dt><em>' . t('Share files and folders with other users') . '</em></dt>';
  $help .= '<dd>' . t(
    "Users with this permission may share specific content with other users, granting selected users view and (optionally) author access. Users granted view access to shared content may view and download the content. Users granted author access may created, delete, and modify the shared content.") . '</dd>';
  $help .= '<dd>' . t(
    "This permission is usually enabled for all authenticated users.") . '</dd>';
  $help .= '<dd>' . t(
    "Sites that do not need content sharing can disable this permission for all user roles. Users will still be able to access their own content.") . '</dd>';

  $help .= '<dt><em>' . t('Share files and folders with the public') . '</em></dt>';
  $help .= '<dd>' . t(
    "Users with this permission may share specific content with anonymous 'public' users that do not have accounts on the site. Anonymous users can only be granted view access, not author access.") . '</dd>';
  $help .= '<dd>' . t(
    "This permission is usually only granted to trusted content authors who have responsability for general site content, such as documentation, released software, press kits, and so forth.") . '</dd>';
  $help .= '<dd>' . t(
    "Sites that do not want users to share content for anonymous access can disable this permission for all user roles. Anonymous users will still be able to access content owned by the anonymous user, if any.") . '</dd>';

  $help .= '<dt><em>' . t('Administer files and folders') . '</em></dt>';
  $help .= '<dd>' . t(
    "Users with this permission may create, delete, and modify their own content and content owned by any user. They may change ownership and sharing settings for specific content, but they cannot change module settings for the site.") . '</dd>';
  $help .= '<dd>' . t(
    "This permission is usually only granted to site administrators, content moderators, and those handling customer support. It enables a user to moderate content and fix problems. It should not be granted to all users or anonymous users.") . '</dd>';

  $help .= '</dl>';

  $help .= '<p>' . t(
    'These permissions are designed to be additive. Users that are granted <em>author</em> access should also be granted <em>view</em> access so that they can view the content they create, and similarly for the other permissions.') . '</p>';

  //
  // Shared access
  // -------------
  // Note that shared access goes beyond role-based permissions.
  $help .= '<h4>' . t('Shared access') . '</h4>';
  $help .= '<p>' . t(
    'In addition to role-based permissions, users with <em>share with others</em> access may grant specific users with view and author access to selected top-level files and folder, and all of the content within.') . '</p>';
  $help .= '<p>' . t(
    'When granted <em>view</em> access, users may view all content within the folder tree. They may browser through folders, view files, copy items to their own folder trees, and download any of the content.') . '</p>';
  $help .= '<p>' . t(
    'When granted <em>author</em> access, users may modify content within the folder tree. They may create new subfolders, upload files, edit file and folder descriptions, rename items, move items about, and delete them.') . '</p>';
  $help .= '<p>' . t(
    "Users that are granted view or author access to a folder tree cannot change the folder tree's access controls to grant other users with access. Only the owner of the folder tree can adjust its access controls.") . '</p>';

  //
  // Administration
  // --------------
  // Link to module's settings page.
  $help .= '<h4>' . t('Administration') . '</h4>';
  $help .= '<p>' . t(
    "<strong>@moduletitle's</strong> @settings may be adjusted to control where and how files are uploaded, where they are stored on the web server, whether file name extensions are restricted, how files and folders are displayed and searched, and more. All settings are explained on the module settings form.",
    [
      '@moduletitle' => $moduleTitle,
      '@settings'    => $moduleSettings,
    ]) . '</p>';

  //
  // Recommendations
  // ---------------
  // List recommended modules and settings.
  $help .= '<h4>' . t('Recommendations') . '</h4>';
  $help .= '<p>' . t(
    'Recommended configuration for this module:') . '</p>';
  $help .= '<ul class="' . Constants::MODULE . '-help-recommend-list">';
  $help .= '<li>' . t(
    "The module's file name extension restrictions may be disabled so that users can upload files of any type. Internally, this module stores all files on the server using numeric names without extensions so that the web server cannot be tricked into executing malicious uploads. Disabling file name extension restrictions for uploaded files is therefore safe for the server.") . '</li>';
  $help .= '<li>' . t(
    'Uploaded files may be stored in a <em>Private</em> file system. Since this module stores all files using numeric names without extensions, it must insert software to respond to user clicks on file links and properly return files to users and browsers. This makes the performance difference between <em>Public</em> and <em>Private</em> file systems negligable, but a <em>Private</em> file system also prevents malicious users from guessing file paths and uploading files they do not have access to.') . '</li>';
  $help .= '</ul>';

  $help .= '<p>' . t(
    'Recommended configurations for related modules:') . '</p>';
  $help .= '<ul class="' . Constants::MODULE . '-help-recommend-list">';
  $help .= '<li>' . t(
    'Remember to enable module permissions for specific roles. A typical configuration grants <em>view</em> access to anonymous users; <em>view</em>, <em>author</em>, and one or both <em>share</em> accesses to authenticated users, and everything to administrators.') . '</li>';
  $help .= '<li>' . t(
    "Using the site's block configuration, enable the breadcrumbs block and place it on the page. This module automatically builds breadcrumbs to show the folder hierarchy.") . '</li>';
  $help .= '<li>' . t(
    'Configure Cron (or equivalent) to run regularly. Hourly runs are recommended as a minimum. This module uses Cron to schedule background tasks that keep folder sizes uptodate and create search indexes of file and folder content. The shorter the period between Cron runs, the more uptodate these will be.') . '</li>';
  $help .= '<li>' . t(
    'Add a text filter that supports some or all of HTML, then assign it to the module\'s folder "description" field by using the folder "Manage display" tab. This will enable users to enter formatted text describing their folders. Also consider enabling the WYSIWYG editor for the text filter.') . '</li>';
  $help .= '<li>' . t(
    'Consider enabling the "Comments" module so that users can add comments to folders.') . '</li>';
  $help .= '<li>' . t(
    'Consider enabling the "Search" module so that users can search for files and folders.') . '</li>';
  $help .= '</ul>';

  //
  // Render element
  // --------------
  // Return a render element instead of the HTML string itself. This is
  // needed in order to include module libraries to style the help page.
  $page = [
    '#attached'  => [
      'library'  => [
        Constants::LIBRARY_MODULE,
        Constants::LIBRARY_ADMIN,
      ],
    ],
    'page'       => [
      '#type'    => 'container',
      '#tree'    => TRUE,
      '#attributes' => [
        'class'     => [$pageClass],
      ],
      'branding' => Branding::getBannerBranding(),
      'help'     => [
        '#type'  => 'html_tag',
        '#tag'   => 'div',
        '#value' => $help,
      ],
    ],
  ];

  return $page;
}
