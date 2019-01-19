<?php

namespace Drupal\foldershare\Entity;

use Drupal\Core\Database\Database;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;

/**
 * Manages per-user usage information for the module.
 *
 * This class manages the FolderShare usage table, which has one record for
 * each user that has used the module's features. Each record indicates:
 * - nFolders: the number of folders owned by the user.
 * - nFiles: the number of files owned by the user.
 * - nBytes: the total storage of all files owned by the user.
 *
 * Methods on this class build this table and return its values.
 *
 * The database table is created in MODULE.install when the module is
 * installed.
 *
 * @section access Access control
 * This class's methods do not do access control. The caller should restrict
 * access. Typically access is restricted to administrators.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\FolderShare
 */
final class FolderShareUsage {

  /*--------------------------------------------------------------------
   *
   * Database tables.
   *
   *-------------------------------------------------------------------*/

  /**
   * The name of the per-user usage tracking database table.
   *
   * This name must match the table defined in MODULE.install.
   */
  const USAGE_TABLE = 'foldershare_usage';

  /*--------------------------------------------------------------------
   *
   * Get totals.
   *
   *-------------------------------------------------------------------*/

  /**
   * Returns the total number of bytes.
   *
   * The returned value only includes storage space used for files.
   * Any storage space required in the database for folder or file
   * metadata is not included.
   *
   * @return int
   *   The total number of bytes.
   *
   * @see FolderShare::countNumberOfBytes()
   */
  public static function getNumberOfBytes() {
    return FolderShare::countNumberOfBytes();
  }

  /**
   * Returns the total number of folders.
   *
   * @return int
   *   The total number of folders.
   *
   * @see FolderShare::countNumberOfFolders()
   */
  public static function getNumberOfFolders() {
    return FolderShare::countNumberOfFolders();
  }

  /**
   * Returns the total number of files.
   *
   * @return int
   *   The total number of folders.
   *
   * @see FolderShare::countNumberOfFiles()
   */
  public static function getNumberOfFiles() {
    return FolderShare::countNumberOfFiles();
  }

  /*--------------------------------------------------------------------
   *
   * Get/Set usage.
   *
   *-------------------------------------------------------------------*/

  /**
   * Clears usage statistics for all users.
   */
  public static function clearAllUsage() {
    $connection = Database::getConnection();
    $connection->delete(self::USAGE_TABLE)
      ->execute();
  }

  /**
   * Clears usage statistics for a user.
   *
   * @param int $uid
   *   The user ID of the user whose usage is cleared.
   */
  public static function clearUsage(int $uid) {
    if ($uid < 0) {
      // Invalid UID.
      return;
    }

    $connection = Database::getConnection();
    $connection->delete(self::USAGE_TABLE)
      ->condition('uid', $uid)
      ->execute();
  }

  /**
   * Returns usage statistics of all users.
   *
   * The returned array has one entry for each user in the
   * database. Array keys are user IDs, and array values are associative
   * arrays with keys for specific metrics and values for those
   * metrics. Supported array keys are:
   *
   * - nFolders: the number of folders.
   * - nFiles: the number of files.
   * - nBytes: the total storage of all files.
   *
   * All metrics are for the total number of items or bytes owned by
   * the user.
   *
   * The returned values for bytes used is the current storage space use
   * for each user. This value does not include any database storage space
   * required for file and folder metadata.
   *
   * The returned array only contains records for those users that
   * have current usage . Users who have no recorded metrics
   * will not be listed in the returned array.
   *
   * @return array
   *   An array with user ID array keys. Each array value is an
   *   associative array with keys for each of the above usage.
   */
  public static function getAllUsage() {
    // Query the usage table for all entries.
    $connection = Database::getConnection();
    $select = $connection->select(self::USAGE_TABLE, 'u');
    $select->addField('u', 'uid', 'uid');
    $select->addField('u', 'nFolders', 'nFolders');
    $select->addField('u', 'nFiles', 'nFiles');
    $select->addField('u', 'nBytes', 'nBytes');
    $records = $select->execute()->fetchAll();

    // Build and return an array from the records.  Array keys
    // are user IDs, while values are usage info.
    $usage = [];
    foreach ($records as $record) {
      $usage[$record->uid] = [
        'nFolders' => $record->nFolders,
        'nFiles'   => $record->nFiles,
        'nBytes'   => $record->nBytes,
      ];
    }

    return $usage;
  }

  /**
   * Returns usage statistics for a user.
   *
   * The returned associative array has keys for specific metrics,
   * and values for those metrics. Supported array keys are:
   *
   * - nFolders: the number of folders.
   * - nFiles: the number of files.
   * - nBytes: the total storage of all files.
   *
   * All metrics are for the total number of items or bytes owned by
   * the user.
   *
   * The returned value for bytes used is the current storage space use
   * for the user. This value does not include any database storage space
   * required for file and folder metadata.
   *
   * If there is no recorded usage information for the user, an
   * array is returned with all metric values zero.
   *
   * @param int $uid
   *   The user ID of the user whose usage is to be returned.
   *
   * @return array
   *   An associative array is returned that includes keys for each
   *   of the above usage.
   */
  public static function getUsage(int $uid) {
    if ($uid < 0) {
      // Invalid UID.
      return [
        'nFolders'     => 0,
        'nFiles'       => 0,
        'nBytes'       => 0,
      ];
    }

    // Query the usage table for an entry for this user.
    // There could be none, or one, but not multiple entries.
    $connection = Database::getConnection();
    $select = $connection->select(self::USAGE_TABLE, 'u');
    $select->addField('u', 'uid', 'uid');
    $select->addField('u', 'nFolders', 'nFolders');
    $select->addField('u', 'nFiles', 'nFiles');
    $select->addField('u', 'nBytes', 'nBytes');
    $select->condition('u.uid', $uid, '=');
    $records = $select->execute()->fetchAll();

    // If none, return an empty usage array.
    if (count($records) === 0) {
      return [
        'nFolders'     => 0,
        'nFiles'       => 0,
        'nBytes'       => 0,
      ];
    }

    // Otherwise return the usage.
    $record = array_shift($records);
    return [
      'nFolders'     => $record->nFolders,
      'nFiles'       => $record->nFiles,
      'nBytes'       => $record->nBytes,
    ];
  }

  /**
   * Returns the storage used for a user.
   *
   * The returned value is the current storage space use for the user.
   * This value does not include any database storage space required
   * for file and folder metadata.
   *
   * This is a convenience function that just returns the 'nBytes'
   * value from the user's usage.
   *
   * @param int $uid
   *   The user ID of the user whose usage is to be returned.
   *
   * @return int
   *   The total storage space used for files owned by the user.
   *
   * @see ::getUsage()
   */
  public static function getUsageBytes(int $uid) {
    $usage = self::getUsage($uid);
    return $usage['nBytes'];
  }

  /**
   * Rebuilds usage information for all users.
   *
   * All current usage information is deleted and a new set assembled
   * and saved for all users at the site. Users that have no files or
   * folders are not included.
   *
   * This can be a lengthy process as it requires getting lists of all files
   * and folders, then looping through them to create metrics for
   * their owners.
   */
  public static function rebuildAllUsage() {
    //
    // Clear all usage.
    // ----------------
    // Delete all records.
    $connection = Database::getConnection();
    $connection->delete(self::USAGE_TABLE)->execute();

    //
    // Queue usage rebuild per user.
    // -----------------------------
    // If a work queue is available, add tasks to rebuild usage information.
    // To keep tasks small enough that they should complete before a
    // PHP or web server timeout, queue a separate task for each user ID.
    // In each case, the queued task will not overwrite a previously created
    // entry. This insures that the task will end quickly if the entry has
    // been updated already by a prior run of the task or by the code below.
    $userIds = \Drupal::entityQuery('user')->execute();
    $queue = \Drupal::queue(Constants::WORK_QUEUE, TRUE);
    $n = count($userIds);
    $lastUid = $userIds[$n - 1];
    foreach ($userIds as $uid) {
      $queue->createItem(
        [
          'operation'  => 'rebuildusage',
          'uids'       => [$uid],
          'overwrite'  => FALSE,
          'updatedate' => ($uid === $lastUid),
        ]);
    }

    // Rebuild immediately.
    // --------------------
    // Though we've queued a task above, we'd prefer to provide immediate
    // feedback to the user. So execute the task directly here.
    Settings::setUsageReportTime('pending');
    self::processRebuildUsageTask(
      [
        'operation'  => 'rebuildusage',
        'uids'       => $userIds,
        'overwrite'  => TRUE,
        'updatedate' => TRUE,
      ]);
  }

  /*---------------------------------------------------------------------
   *
   * Queue task.
   *
   *---------------------------------------------------------------------*/

  /**
   * Processes a usage rebuild task from the work queue.
   *
   * <B>This method is internal and strictly for use by the FolderShare
   * module itself.</B> This method is public so that it can be called
   * from the module's work queue handler.
   *
   * A rebuild task for the usage table provides a list of user IDs for
   * users for whome usage information is rebuilt. This list may include
   * all users at the site, or just a subset.
   *
   * There is one condition under which usage rebuilding may not complete fully:
   * - The database is too large to query before hitting a timeout.
   *
   * Database queries issued here count the number of matching entries or
   * sum entry sizes. Most of the work, then, is done in the database and
   * this work does not count against the PHP runtime. It is very unlikely
   * that a PHP or web server timeout will occur and interrupt this task.
   *
   * To protect against timeouts, queued tasks should be as small as
   * practical. For rebuilding usage, each queued task should rebuild
   * just one user's entry.
   *
   * @param array $data
   *   The queued task's data. The data must be an associative array
   *   with the following fields:
   *   - 'operation': Must be 'rebuildusage'.
   *   - 'uids': An array of integer User entity IDs to update.
   *   - 'overwrite': TRUE to overwrite existing entries.
   *   - 'updatedate': TRUE to update the stored last-updated date.
   */
  public static function processRebuildUsageTask(array $data) {
    //
    // Validate data.
    // --------------
    // The queued data is an array of user IDs to update.
    if (isset($data['uids']) === FALSE ||
        is_array($data['uids']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName @taskName task.\nThe required 'uids' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
          '@taskName'   => 'Rebuild Usage',
        ]);
      return;
    }

    $overwrite = FALSE;
    if (empty($data['overwrite']) === FALSE &&
        $data['overwrite'] === 'TRUE') {
      $overwrite = TRUE;
    }

    $updateDate = FALSE;
    if (empty($data['updatedate']) === FALSE &&
        $data['updatedate'] === TRUE) {
      $updateDate = TRUE;
    }

    //
    // Rebuild usage.
    // --------------
    // For each user, count the number of files, folders, and bytes, then
    // update the usage table.
    //
    // If this is interrupted, the next run will try the same update again.
    // To avoid redundant effort, if $overwrite === FALSE, then skip updating
    // users that already have a table entry - which was presumably created
    // on a prior run.
    $connection = Database::getConnection();
    foreach ($data['uids'] as $uid) {
      $nEntries = $connection->select(self::USAGE_TABLE, 'u')
        ->condition('uid', $uid, '=')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($overwrite === FALSE && (int) $nEntries !== 0) {
        // Do not overwrite the existing entry.
        continue;
      }

      if ((int) $nEntries > 0) {
        // Delete the entry first.
        $connection->delete(self::USAGE_TABLE)
          ->condition('uid', $uid, '=')
          ->execute();
      }

      $nFolders = FolderShare::countNumberOfFolders($uid);
      $nFiles   = FolderShare::countNumberOfFiles($uid);
      $nBytes   = FolderShare::countNumberOfBytes($uid);

      $query = $connection->insert(self::USAGE_TABLE);
      $query->fields(
        [
          'uid'      => $uid,
          'nFolders' => $nFolders,
          'nFiles'   => $nFiles,
          'nBytes'   => $nBytes,
        ]);
      $query->execute();
    }

    if ($updateDate === TRUE) {
      Settings::setUsageReportTime('@' . (string) time());
    }
  }

}
