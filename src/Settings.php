<?php

namespace Drupal\foldershare;

/**
 * Defines functions to get/set the module's configuration.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * The module's settings (configuration) schema is defined in
 * config/schema/MODULE.settings.yml, with install-time defaults set
 * in config/install/MODULE.settings.yml. The defaults provided
 * in this module match those in the YML files.
 *
 * Configuration settings include:
 *
 * - *_term: module terminology.
 * - file_scheme: public or private files.
 * - file_directory: where files go on server.
 * - file_allowed_extensions: allowed file extensions.
 * - file_restrict_extensions: enable of extension restrictions.
 *
 * @internal
 * Get/set of a module configuration setting requires two strings: (1) the
 * name of the module's configuration, and (2) the name of the setting.
 * When used frequently in module code, these strings invite typos that
 * can cause the wrong setting to be set or retrieved.
 *
 * This class centralizes get/set for settings and turns all settings
 * accesses into class method calls. The PHP parser can then catch typos
 * in method calls and report them as errors.
 *
 * This class also centralizes and makes accessible the default values
 * for all settings.
 * @endinternal
 *
 * @ingroup foldershare
 */
class Settings {

  /*---------------------------------------------------------------------
   *
   * File storage.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the default public/private file storage scheme.
   *
   * Legal values are:
   *
   * - 'public' = store files in the site's public file system.
   * - 'private' = store files in the site's private file system.
   *
   * @return string
   *   The default file storage scheme as either 'public' or 'private'.
   */
  public static function getFileSchemeDefault() {
    // Try to use the Core File module's default choice, if it is
    // one of 'public' or 'private'. If not recognized, revert to 'public'.
    switch (file_default_scheme()) {
      default:
      case 'public':
        return 'public';

      case 'private':
        return 'private';
    }
  }

  /**
   * Returns the module's setting for the public/private file storage scheme.
   *
   * Legal values are:
   *
   * - 'public' = store files in the site's public file system.
   * - 'private' = store files in the site's private file system.
   *
   * @return string
   *   The file storage scheme as either 'public' or 'private'.
   */
  public static function getFileScheme() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('file_scheme') === NULL) {
      return self::getFileSchemeDefault();
    }

    $scheme = $config->get('file_scheme');
    switch ($scheme) {
      case 'public':
      case 'private':
        return $scheme;
    }

    return self::getFileSchemeDefault();
  }

  /**
   * Sets the module's setting for the public/private file storage scheme.
   *
   * Legal values are:
   *
   * - 'public' = store files in the site's public file system.
   * - 'private '= store files in the site's private file system.
   *
   * Unrecognized values are silently ignored.
   *
   * @param string $scheme
   *   The file storage scheme as either 'public' or 'private'.
   */
  public static function setFileScheme(string $scheme) {
    switch ($scheme) {
      case 'public':
      case 'private':
        break;

      default:
        return;
    }

    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('file_scheme', $scheme);
    $config->save(TRUE);
  }

  /*---------------------------------------------------------------------
   *
   * File restrictions.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the default setting flagging file name extension restrictions.
   *
   * Legal values are TRUE or FALSE.
   *
   * @return bool
   *   True if file name extensions are restricted, and false otherwise.
   */
  public static function getFileRestrictExtensionsDefault() {
    return FALSE;
  }

  /**
   * Returns the module's setting flagging file name extension restrictions.
   *
   * Legal values are TRUE or FALSE.
   *
   * @return bool
   *   True if file name extensions are restricted, and false otherwise.
   */
  public static function getFileRestrictExtensions() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('file_restrict_extensions') === NULL) {
      return self::getFileRestrictExtensionsDefault();
    }

    $value = $config->get('file_restrict_extensions');
    if ($value === FALSE) {
      return FALSE;
    }

    if ($value === TRUE) {
      return TRUE;
    }

    return self::getFileRestrictExtensionsDefault();
  }

  /**
   * Sets the module's setting flagging file name extension restrictions.
   *
   * Legal values are TRUE or FALSE.
   *
   * Unrecognized values are silently ignored.
   *
   * @param bool $value
   *   True if file name extensions are restricted, and false otherwise.
   */
  public static function setFileRestrictExtensions(bool $value) {
    if ($value !== FALSE && $value !== TRUE) {
      return;
    }

    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('file_restrict_extensions', $value);
    $config->save(TRUE);
  }

  /**
   * Returns the default list of allowed file name extensions.
   *
   * This list is intentionally broad and includes a large set of
   * well-known extensions for text, web, image, video, audio,
   * graphics, data, archive, office, and programming documents.
   *
   * @return string
   *   A string containing a space or comma-separated list of file
   *   extensions (without the leading dot).
   */
  public static function getAllowedNameExtensionsDefault() {
    $merged = FilenameExtensions::getAll();

    // Return as a giant space-separated string.
    return implode(' ', $merged);
  }

  /**
   * Returns the module's setting listing allowed file name extensions.
   *
   * The return list is a single string with space-separated dot-free
   * file name extensions allowed for file uploads and renames.
   *
   * @return string
   *   A string containing a space or comma-separated list of file
   *   extensions (without the leading dot).
   */
  public static function getAllowedNameExtensions() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('file_allowed_extensions') === NULL) {
      return self::getAllowedNameExtensionsDefault();
    }

    return $config->get('file_allowed_extensions');
  }

  /**
   * Sets the module's setting listing allowed file name extensions.
   *
   * The given list must be a single string with space-separated dot-free
   * file name extensions allowed for file uploads and renames.
   *
   * Non-string values are silently ignored.
   *
   * @param string $ext
   *   A string containing a space or comma-separated list of file
   *   extensions (without the leading dot).
   */
  public static function setAllowedNameExtensions(string $ext) {
    if (is_string($ext) === FALSE) {
      return;
    }

    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('file_allowed_extensions', $ext);
    $config->save(TRUE);
  }

  /*---------------------------------------------------------------------
   *
   * Max file size.
   *
   *---------------------------------------------------------------------*/

  /**
   * Converts a PHP .ini file size shorthand to an integer.
   *
   * In a php.ini file, settings that take a size in bytes support a
   * few shorthands as single letters adjacent to the number:
   * - K = kilobytes.
   * - M = megabytes.
   * - G = gigabytes.
   *
   * This function looks for those letters and multiplies the integer
   * part to get a size in bytes.
   *
   * @param string $value
   *   The php.ini size file, optionally with K, M, or G shorthand letters
   *   at the end for kilobytes, megabytes, or gigabytes.
   *
   * @return int
   *   The integer size in bytes.
   */
  private static function phpIniToInteger(string $value) {
    // Extract the number and letter, if any.
    if (preg_match('/(\d+)(\w+)/', $value, $matches) === 1) {
      switch (strtolower($matches[2])) {
        case 'k':
          return ((int) $matches[1] * 1024);

        case 'm':
          return ((int) $matches[1] * 1024 * 1024);

        case 'g':
          return ((int) $matches[1] * 1024 * 1024 * 1024);
      }
    }

    return (int) $value;
  }

  /**
   * Returns the PHP configured max upload file size.
   *
   * @return int
   *   Returns the PHP maximum file upload size.
   */
  public static function getPhpUploadMaximumFileSize() {
    // Two PHP .ini values limit the maximum upload file size. If either
    // one is not defined, use the documented default.  Then return the
    // lower of these limits.
    $value = ini_get('upload_max_filesize');
    if ($value === FALSE) {
      $phpMaxFileSize = (2 * 1024 * 1024);
    }
    else {
      $phpMaxFileSize = self::phpIniToInteger($value);
    }

    $value = ini_get('post_max_size');
    if ($value === FALSE) {
      $phpMaxFileSize = (8 * 1024 * 1024);
    }
    else {
      $phpMaxPostSize = self::phpIniToInteger($value);
    }

    return ($phpMaxFileSize < $phpMaxPostSize) ?
      $phpMaxFileSize : $phpMaxPostSize;
  }

  /**
   * Returns the PHP configured max upload number.
   *
   * @return int
   *   Returns the PHP maximum number of files uploaded at once.
   */
  public static function getPhpUploadMaximumFileNumber() {
    // One PHP .ini value sets the limit. If it is not defined, use
    // the documented default.
    $value = ini_get('max_file_uploads');
    if ($value === FALSE) {
      return 20;
    }

    return (int) $value;
  }

  /**
   * Returns the default max upload file size.
   *
   * @return int
   *   Returns the default maximum file upload size.
   */
  public static function getUploadMaximumFileSizeDefault() {
    // Default to PHP's settings.
    return self::getPhpUploadMaximumFileSize();
  }

  /**
   * Returns the default max upload number.
   *
   * @return int
   *   Returns the default maximum number of files uploaded at once.
   */
  public static function getUploadMaximumFileNumberDefault() {
    // Default to PHP's settings.
    return self::getPhpUploadMaximumFileNumber();
  }

  /**
   * Returns the module's setting for the max upload file size.
   *
   * @return int
   *   Returns the maximum file upload size.
   */
  public static function getUploadMaximumFileSize() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('upload_max_file_size') === NULL) {
      return self::getUploadMaximumFileSizeDefault();
    }

    return (int) $config->get('upload_max_file_size');
  }

  /**
   * Returns the module's setting for the max upload number.
   *
   * @return int
   *   Returns the maximum number of files uploaded at once.
   */
  public static function getUploadMaximumFileNumber() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('upload_max_file_number') === NULL) {
      return self::getUploadMaximumFileNumberDefault();
    }

    return (int) $config->get('upload_max_file_number');
  }

  /**
   * Sets the module's setting for the max upload file size.
   *
   * @param int $size
   *   The maximum file size. Negative or zero values are ignored.
   */
  public static function setUploadMaximumFileSize(int $size) {
    if ($size > 0) {
      $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
      $config->set('upload_max_file_size', $size);
      $config->save(TRUE);
    }
  }

  /**
   * Sets the module's setting for the max upload number.
   *
   * @param int $number
   *   The maximum number of files to upload at once. Negative or
   *   zero values are ignored.
   */
  public static function setUploadMaximumFileNumber(int $number) {
    if ($number > 0) {
      $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
      $config->set('upload_max_file_number', $number);
      $config->save(TRUE);
    }
  }

  /*---------------------------------------------------------------------
   *
   * Menus and toolbars.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a list of all available plugin command definitions.
   *
   * The returned list includes all currently installed plugin commands,
   * regardless of the module source for the commands. Depending upon
   * module settings, some of these commands are allowed on the command
   * menu, while others are disabled from the user interface.
   *
   * @return array
   *   Returns an array of plugin command definitions.  Array keys are
   *   command keys, and array values are command definitions.
   *
   * @see ::getAllowedCommandDefinitions()
   */
  public static function getAllCommandDefinitions() {
    // Get the command plugin manager.
    $container = \Drupal::getContainer();
    $manager = $container->get('foldershare.plugin.manager.foldersharecommand');
    if ($manager === NULL) {
      // No manager? Return nothing.
      return [];
    }

    // Get all the command definitions. The returned array has array keys
    // as plugin IDs, and array values as definitions.
    return $manager->getDefinitions();
  }

  /**
   * Returns a list of allowed plugin command definitions.
   *
   * The returned list includes all currently installed plugin commands,
   * regardless of the module source for the commands. This list is then
   * filtered to only include those commands that are currently allowed,
   * based upon module settings for command menu restrictions and content.
   *
   * @return array
   *   Returns an array of plugin command definitions.  Array keys are
   *   command keys, and array values are command definitions.
   */
  public static function getAllowedCommandDefinitions() {
    // Get a list of all installed commands.
    $defs = self::getAllCommandDefinitions();

    // If the command menu is not restricted, return the entire list.
    if (self::getCommandMenuRestrict() === FALSE) {
      return $defs;
    }

    // Otherwise the menu is restricted. Get the current list of allowed
    // command IDs.
    $ids = self::getCommandMenuAllowed();

    // Cull the list of definitions to only those allowed on the command
    // menu.
    $allowed = [];
    foreach ($defs as $id => $def) {
      if (in_array($id, $ids) === TRUE) {
        $allowed[$id] = $def;
      }
    }

    return $allowed;
  }

  /**
   * Returns the default setting flagging command menu restrictions.
   *
   * @return bool
   *   TRUE if the command menu is restricted, and FALSE otherwise.
   */
  public static function getCommandMenuRestrictDefault() {
    return FALSE;
  }

  /**
   * Returns the module's setting flagging command menu restrictions.
   *
   * When FALSE, all available plugin commands are allowed on the user
   * interface's command menu. When TRUE, this list is restricted to only
   * the commands selected by the site administrator.
   *
   * @return bool
   *   TRUE if the command menu is restricted, and FALSE otherwise.
   *
   * @see ::getCommandMenuRestrictDefault()
   * @see ::setCommandMenuRestrict()
   */
  public static function getCommandMenuRestrict() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('command_menu_restrict') === NULL) {
      return self::getCommandMenuRestrictDefault();
    }

    $value = $config->get('command_menu_restrict');
    if ($value === FALSE) {
      return FALSE;
    }

    if ($value === TRUE) {
      return TRUE;
    }

    return self::getCommandMenuRestrictDefault();
  }

  /**
   * Sets the module's setting flagging command menu restrictions.
   *
   * When FALSE, all available plugin commands are allowed on the user
   * interface's command menu. When TRUE, this list is restricted to only
   * the commands selected by the site administrator.
   *
   * @param bool $value
   *   TRUE if the command menu is restricted, and FALSE otherwise.
   *
   * @see ::getCommandMenuRestrict()
   */
  public static function setCommandMenuRestrict(bool $value) {
    if ($value !== FALSE && $value !== TRUE) {
      return;
    }

    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('command_menu_restrict', $value);
    $config->save(TRUE);
  }

  /**
   * Returns the default list of allowed plugin commands for menus.
   *
   * The returned list is an array of command plugin IDs. By default,
   * this list includes all command plugins currently installed.
   *
   * @return array
   *   Returns an array of plugin command IDs.
   */
  public static function getCommandMenuAllowedDefault() {
    return array_keys(self::getAllCommandDefinitions());
  }

  /**
   * Returns the list of allowed plugin commands for menus.
   *
   * The returned list is an array of command plugin IDs. If modules with
   * commands have been uninstalled since this list was last set, then the
   * list may include IDs for commands that are no longer available.
   *
   * @return array
   *   Returns an array of plugin command IDs.
   */
  public static function getCommandMenuAllowed() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('command_menu_allowed') === NULL) {
      // Nothing set yet. Revert to default.
      return self::getCommandMenuAllowedDefault();
    }

    $ids = $config->get('command_menu_allowed');
    if (is_array($ids) === TRUE) {
      return $ids;
    }

    // The stored value is bogus. Reset it to the default.
    $ids = self::getCommandMenuAllowedDefault();
    self::setCommandMenuAllowed($ids);
    return $ids;
  }

  /**
   * Sets the allowed list of plugin commands for menus.
   *
   * The given list is an array of command plugin IDs.
   *
   * @param array $ids
   *   An array of plugin command IDs.
   */
  public static function setCommandMenuAllowed(array $ids) {
    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);

    if (empty($ids) === TRUE || is_array($ids) === FALSE) {
      // Set the menu to be an empty list.
      $config->set('command_menu_allowed', []);
      $config->save(TRUE);
    }
    else {
      $config->set('command_menu_allowed', $ids);
      $config->save(TRUE);
    }
  }

  /*---------------------------------------------------------------------
   *
   * Usage report table rebuild.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the default date for the last usage table update.
   *
   * This is only needed if there is no previous date, so this function
   * always returns 'never'.
   *
   * @return string
   *   The string 'never'.
   */
  public static function getUsageReportTimeDefault() {
    return 'never';
  }

  /**
   * Returns the date of the most recent usage table update.
   *
   * Legal values are:
   *
   * - 'never' = the table has never been updated.
   * - 'pending' = an update is in progress.
   * - a date string for the most recent update date and time.
   *
   * @return string
   *   The most recent update date.
   */
  public static function getUsageReportTime() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('usage_report_time') === NULL) {
      return self::getUsageReportTimeDefault();
    }

    return $config->get('usage_report_time');
  }

  /**
   * Sets the date of the most recent usage table update.
   *
   * Legal values are:
   *
   * - 'never' = the table has never been updated.
   * - 'pending' = an update is in progress.
   * - a date string for the most recent update date and time.
   *
   * @param string $date
   *   The most recent update date.
   */
  public static function setUsageReportTime(string $date) {
    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('usage_report_time', $date);
    $config->save(TRUE);
  }

  /*---------------------------------------------------------------------
   *
   * Logging.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the default activity logging enable.
   *
   * @return bool
   *   TRUE if activity logging is enabled, and FALSE otherwise.
   */
  public static function getActivityLogEnableDefault() {
    return FALSE;
  }

  /**
   * Returns the module's setting for activity logging.
   *
   * @return bool
   *   TRUE if activity logging is enabled, and FALSE otherwise.
   */
  public static function getActivityLogEnable() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('activity_log') === NULL) {
      return self::getFileSchemeDefault();
    }

    $value = $config->get('activity_log');
    if ($value === FALSE) {
      return FALSE;
    }

    if ($value === TRUE) {
      return TRUE;
    }

    return self::getActivityLogEnableDefault();
  }

  /**
   * Sets the module's setting for activity logging.
   *
   * @param bool $value
   *   TRUE to enable activity logging, and FALSE to disable.
   */
  public static function setActivityLogEnable(bool $value) {
    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('activity_log', $value);
    $config->save(TRUE);
  }

  /*---------------------------------------------------------------------
   *
   * Process locks.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the default process locks enable.
   *
   * @return bool
   *   TRUE if process locks are enabled, and FALSE otherwise.
   */
  public static function getProcessLocksEnableDefault() {
    return FALSE;
  }

  /**
   * Returns the module's setting for process locks.
   *
   * @return bool
   *   TRUE if process locks are enabled, and FALSE otherwise.
   */
  public static function getProcessLocksEnable() {
    $config = \Drupal::config(Constants::SETTINGS);
    if ($config->get('process_locks') === NULL) {
      return self::getFileSchemeDefault();
    }

    $value = $config->get('process_locks');
    if ($value === FALSE) {
      return FALSE;
    }

    if ($value === TRUE) {
      return TRUE;
    }

    return self::getProcessLocksEnableDefault();
  }

  /**
   * Sets the module's setting for process locks.
   *
   * @param bool $value
   *   TRUE to enable process locks, and FALSE to disable.
   */
  public static function setProcessLocksEnable(bool $value) {
    $config = \Drupal::configFactory()->getEditable(Constants::SETTINGS);
    $config->set('process_locks', $value);
    $config->save(TRUE);
  }

}
