<?php

namespace Drupal\foldershare\Entity\FolderShareTraits;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;

/**
 * Manages log posts.
 *
 * This trait includes internal methods to post log messages, if the
 * site's settings enable logging.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShare entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManageLogTrait {

  /*---------------------------------------------------------------------
   *
   * Log.
   *
   *---------------------------------------------------------------------*/

  /**
   * Posts a log message if logging is enabled.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from classes throughout the module.
   *
   * The given log message and its context are posted at the given level
   * if the module's logging feature is enabled for the site. If it is
   * not enabled, no action is taken.
   *
   * @param string $level
   *   The log level. This is expected to be one of the standard logger
   *   levels, including:
   *   - "emergency".
   *   - "alert".
   *   - "critical".
   *   - "error".
   *   - "warning".
   *   - "notice".
   *   - "info".
   *   - "debug".
   * @param string $message
   *   The message to be logged. Messages may contain variables of
   *   the form @name or %name that will be replaced by their corresponding
   *   values for keys in the $context associative array.
   * @param array $context
   *   (optional, default = []) An associative array that provides mappings
   *   from keys to values where the keys include any @name or %name found
   *   in the message. Additional special keys include:
   *   - "channel": the message channel.
   *   - "exception": an Exception object for a stack trace.
   *   - "link": a URL link to an involved entity. This is shown in the
   *     Operations column of site log messages.
   *   - "timestamp": the timestamp of the logged activity.
   *   Additional fields will be automatically added by the Drupal logging
   *   infrastructure, including:
   *   - "ip": the request IP address.
   *   - "referer": the request referer.
   *   - 'request_uri': the request URI.
   *   - "uid": the current user ID.
   *   - "user": the current user.
   */
  public static function log(
    string $level,
    string $message,
    array $context = []) {

    if (Settings::getActivityLogEnable() === FALSE) {
      return;
    }

    \Drupal::logger(Constants::MODULE)->log($level, $message, $context);
  }

}
