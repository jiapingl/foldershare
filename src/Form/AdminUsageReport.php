<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShareUsage;

/**
 * Creates an administrator usage report and form for updating the report.
 *
 * This class creates and administrator page that reports the current
 * per-user and total usage. This includes the number of folders
 * and files, and the storage space used by files.
 *
 * @section internal Internal class
 * This class is internal to the FolderShare module. The class's existance,
 * name, and content may change from release to release without any promise
 * of backwards compatability.
 *
 * @section access Access control
 * The route to this form must restrict access to those with the
 * FolderShare administration permission.
 *
 * @section parameters Route parameters
 * The route to this form has no parameters.
 *
 * @ingroup foldershare
 *
 * @see \Drupal\foldershare\Entity\FolderShareUsage
 */
class AdminUsageReport extends FormBase {

  /*--------------------------------------------------------------------
   *
   * Fields - dependency injection.
   *
   *--------------------------------------------------------------------*/

  /**
   * The entity type manager, set at construction time.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new page.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /*--------------------------------------------------------------------
   *
   * Form setup.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return str_replace('\\', '_', get_class($this));
  }

  /*--------------------------------------------------------------------
   *
   * Form build.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $formState = NULL) {

    //
    // Get usage information.
    // ----------------------
    // Get totals and per-user information (array keys are UIDs).
    $nFolders = FolderShareUsage::getNumberOfFolders();
    $nFiles   = FolderShareUsage::getNumberOfFiles();
    $nBytes   = FolderShareUsage::getNumberOfBytes();

    $userUsage = FolderShareUsage::getAllUsage();

    // The above returned usage array contains entries ONLY for users that
    // are using the module. Any user that has never created a file or
    // folder will have no usage entry.
    //
    // For the table on this page, we'd like to include ALL users. So,
    // for any user not in the above usage array, add an empty entry.
    //
    // Get a list of all users on the site.
    foreach ($this->entityTypeManager->getStorage('user')->getQuery()->execute() as $uid) {
      if (isset($userUsage[$uid]) === FALSE) {
        // This user has never used the module. Add an empty entry.
        $userUsage[$uid] = [
          'nFolders'     => 0,
          'nFiles'       => 0,
          'nBytes'       => 0,
        ];
      }
    }

    // Load all the users and sort by their display names.
    $users = [];
    foreach ($this->entityTypeManager->getStorage('user')->loadMultiple(array_keys($userUsage)) as $user) {
      $users[$user->getDisplayName()] = $user;
    }

    ksort($users, SORT_NATURAL);

    //
    // Setup form.
    // -----------
    // Create class names, attach libraries, and create the container body
    // that will hold the table.
    $baseClass          = Constants::MODULE . '-admin-usage-table';
    $tableClass         = $baseClass;
    $userColumnClass    = $baseClass . '-user';
    $foldersColumnClass = $baseClass . '-folders';
    $filesColumnClass   = $baseClass . '-files';
    $bytesColumnClass   = $baseClass . '-bytes';
    $totalsClass        = $baseClass . '-total';
    $rebuildBarClass    = $baseClass . '-rebuild-bar';
    $rebuildTimeClass   = $baseClass . '-rebuild-time';
    $rebuildButtonClass = $baseClass . '-rebuild-button';

    $tableName = Constants::MODULE . '-admin-usage-table';

    $form['#attached']['library'][] = Constants::LIBRARY_MODULE;
    $form['#attached']['library'][] = Constants::LIBRARY_ADMIN;

    $form['#tree'] = TRUE;

    $form['description'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'p',
      '#weight' => 0,
      '#value'  => $this->t('Folders, files, and storage used by each user.'),
    ];

    $rebuildTime = Settings::getUsageReportTime();
    switch ($rebuildTime) {
      case '':
      case 'never':
        $rebuildTime = $this->t('Never updated');
        break;

      case 'pending':
        $rebuildTime = $this->t('Update in progress');
        break;

      default:
        try {
          // The stored update time is a timestamp string.
          // Compute how long ago the time was.
          $now = new \DateTime();
          $diff = (array) $now->diff(new \DateTime($rebuildTime));

          // Introduce a weeks count.
          $diff['w'] = (int) floor(($diff['d'] / 7));
          $diff['d'] -= ($diff->w * 7);

          // Get rid of the microsecond value, increasing seconds.
          if ((float) $diff['f'] !== 0) {
            $diff['s']++;
            unset($diff['f']);
          }

          // Get rid of the seconds value, increasing minutes.
          if ((int) $diff['s'] > 0) {
            $diff['i']++;
            unset($diff['s']);
          }

          // Use the rest of the time denominations and build up a string.
          $string = [
            'y' => [
              '@count year',
              '@count years',
            ],
            'm' => [
              '@count month',
              '@count months',
            ],
            'w' => [
              '@count week',
              '@count weeks',
            ],
            'd' => [
              '@count day',
              '@count days',
            ],
            'h' => [
              '@count hour',
              '@count hours',
            ],
            'i' => [
              '@count minute',
              '@count minutes',
            ],
          ];

          foreach ($string as $k => &$v) {
            if ($diff[$k] === 0) {
              unset($string[$k]);
              continue;
            }

            $v = (string) $this->formatPlural(
              $diff[$k],
              $v[0],
              $v[1]);
          }

          $rebuildTime = $this->t(
            'Updated @time ago',
            [
              '@time' => ((count($string) === 1) ?
                reset($string) : implode(', ', $string)),
            ]);
        }
        catch (\Exception $e) {
          // The stored time is invalid. Revert to 'never'.
          $rebuildTime = $this->t('Never updated');
        }
        break;
    }

    $form['rebuild'] = [
      '#type'            => 'container',
      '#weight'          => 10,
      '#attributes'      => [
        'class'          => [$rebuildBarClass],
      ],
      'time'             => [
        '#type'          => 'html_tag',
        '#tag'           => 'span',
        '#value'         => $rebuildTime,
        '#weight'        => 0,
        '#attributes'    => [
          'class'        => [$rebuildTimeClass],
        ],
      ],
      'actions'          => [
        '#type'          => 'actions',
        '#weight'        => 1,
        'submit'         => [
          '#type'        => 'submit',
          '#button_type' => 'primary',
          '#value'       => '',
          '#attributes'  => [
            'title'      => $this->t('Rebuild the usage table'),
            'class'      => [$rebuildButtonClass],
          ],
        ],
      ],
    ];

    //
    // Create table and headers.
    // -------------------------
    // The table's headers are:
    // - User name.
    // - Number of folders.
    // - Number of files.
    // - Number of bytes.
    $form['usage_table'] = [
      '#type'         => 'table',
      '#name'         => $tableName,
      '#responsive'   => FALSE,
      '#sticky'       => TRUE,
      '#weight'       => 10,
      '#attributes'   => [
        'class'       => [$tableClass],
      ],
      '#header'       => [
        'user'        => [
          'field'     => 'user',
          'data'      => $this->t('Users'),
          'specifier' => 'user',
          'class'     => [$userColumnClass],
          'sort'      => 'ASC',
        ],
        'nfolders'    => [
          'field'     => 'nfolders',
          'data'      => $this->t('Folders'),
          'specifier' => 'nfolders',
          'class'     => [$foldersColumnClass],
        ],
        'nfiles'      => [
          'field'     => 'nfiles',
          'data'      => $this->t('Files'),
          'specifier' => 'nfiles',
          'class'     => [$filesColumnClass],
        ],
        'nbytes'        => [
          'field'     => 'nbytes',
          'data'      => $this->t('Bytes'),
          'specifier' => 'nbytes',
          'class'     => [$bytesColumnClass],
        ],
      ],
    ];

    //
    // Create table rows.
    // ------------------
    // One row for each user, followed by a totals row.
    $rows = [];
    foreach ($users as $user) {
      $usage = $userUsage[$user->id()];

      // The user column has the user's display name and link to the user.
      $userColumn = [
        'data'      => [
          '#type'   => 'item',
          '#markup' => $user->toLink()->toString(),
        ],
        'class'     => [$userColumnClass],
      ];

      // The folders column has the number of folders used by the user.
      $foldersColumn = [
        'data'      => [
          '#type'   => 'item',
          '#markup' => $usage['nFolders'],
        ],
        'class'     => [$foldersColumnClass],
      ];

      // The files column has the number of files used by the user.
      $filesColumn = [
        'data'      => [
          '#type'   => 'item',
          '#markup' => $usage['nFiles'],
        ],
        'class'     => [$filesColumnClass],
      ];

      // The bytes column has the number of bytes used by the user.
      $bytesColumn = [
        'data'      => [
          '#type'   => 'item',
          '#markup' => Utilities::formatBytes($usage['nBytes']),
        ],
        'class'     => [$bytesColumnClass],
      ];

      $rows[] = [
        'data' => [
          'user'     => $userColumn,
          'nfolders' => $foldersColumn,
          'nfiles'   => $filesColumn,
          'nbytes'   => $bytesColumn,
        ],
      ];
    }

    // Add row for totals.
    //
    // The user column says "total" for the totals row.
    $userColumn = [
      'data'      => [
        '#type'   => 'item',
        '#markup' => $this->t('Total'),
      ],
      'class'     => [$userColumnClass],
    ];

    // The folders column has the total number of folders.
    $foldersColumn = [
      'data'      => [
        '#type'   => 'item',
        '#markup' => $nFolders,
      ],
      'class'     => [$foldersColumnClass],
    ];

    // The files column has the total number of files.
    $filesColumn = [
      'data'      => [
        '#type'   => 'item',
        '#markup' => $nFiles,
      ],
      'class'     => [$filesColumnClass],
    ];

    // The bytes column has the total number of bytes.
    $bytesColumn = [
      'data'      => [
        '#type'   => 'item',
        '#markup' => Utilities::formatBytes($nBytes),
      ],
      'class'     => [$bytesColumnClass],
    ];

    $rows[] = [
      'data' => [
        'user'     => $userColumn,
        'nfolders' => $foldersColumn,
        'nfiles'   => $filesColumn,
        'nbytes'   => $bytesColumn,
      ],
      'class'      => [$totalsClass],
    ];

    // Add the rows to the table.
    $form['usage_table']['#rows'] = $rows;

    return $form;
  }

  /*--------------------------------------------------------------------
   *
   * Form validate.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState) {
  }

  /*--------------------------------------------------------------------
   *
   * Form submit.
   *
   *--------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    FolderShareUsage::rebuildAllUsage();
    $this->messenger()->addMessage(
      $this->t('Usage information has been updated.'),
      'status');
  }

}
