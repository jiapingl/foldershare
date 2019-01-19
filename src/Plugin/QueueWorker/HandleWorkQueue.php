<?php

namespace Drupal\foldershare\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareUsage;

/**
 * Defines a queue worker for the module's background tasks.
 *
 * Potentially long-running move operations may be enqueued to a work queue
 * serviced by CRON. Each time CRON processes the queue, this queue worker
 * is called with one task from the queue. The task is dispatched to the
 * appropriate code, such as code to copy, delete, and move items, or update
 * item sizes. Typically these tasks are long-running because they need to
 * traverse a folder tree that may be large.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\Foldershare
 * @see \Drupal\foldershare\Entity\Foldershare::processChangeOwnerTask()
 * @see \Drupal\foldershare\Entity\Foldershare::processCopyTask()
 * @see \Drupal\foldershare\Entity\Foldershare::processDeleteTask()
 * @see \Drupal\foldershare\Entity\Foldershare::processMoveTask()
 * @see \Drupal\foldershare\Entity\Foldershare::processRebuildUsageTask()
 * @see \Drupal\foldershare\Entity\Foldershare::processUpdateSizesTask()
 *
 * @QueueWorker(
 *  id       = "foldershare_handle_work_queue",
 *  label    = @Translation( "Handle FolderShare work queue" ),
 *  cron     = { "time" = 600 }
 * )
 */
class HandleWorkQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Validate queue task.
    // --------------------
    // The task must be a non-empty array with an 'operation' name so we
    // can dispatch the task to the proper code.
    if (empty($data) === TRUE || is_array($data) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName.\nThe parameters array is empty.",
        [
          '@moduleName' => Constants::MODULE,
        ]);
      return;
    }

    if (isset($data['operation']) === FALSE) {
      \Drupal::logger(Constants::MODULE)->error(
        "Work queue error for @moduleName.\nThe required 'operation' parameter is missing.",
        [
          '@moduleName' => Constants::MODULE,
        ]);
      return;
    }

    // Dispatch.
    // ---------
    // Send the task to the appropriate method. The task method may be
    // interrupted by a PHP or web server timeout if it takes too long.
    // In that case, the task remains in the queue and will be re-run.
    // The task is only removed from the queue on a normal completion of
    // this method.
    //
    // The task methods are all written to be able to resume after an
    // interrupt, and gradually make progress on the task without repeating
    // prior work over and over again. These tasks will eventually complete,
    // even if interrupted many times.
    switch ($data['operation']) {
      case 'changeowner':
        FolderShare::processChangeOwnerTask($data);
        return;

      case 'copy':
        FolderShare::processCopyTask($data);
        return;

      case 'delete':
        FolderShare::processDeleteTask($data);
        return;

      case 'move':
        FolderShare::processMoveTask($data);
        return;

      case 'updatesizes':
        FolderShare::processUpdateSizesTask($data);
        return;

      case 'rebuildusage':
        FolderShareUsage::processRebuildUsageTask($data);
        return;

      default:
        \Drupal::logger(Constants::MODULE)->error(
          "Work queue error for @moduleName.\nUnknown operation '@operation'.",
          [
            '@moduleName' => Constants::MODULE,
            '@operation'  => $data['operation'],
          ]);
        return;
    }
  }

}
