<?php

namespace Drupal\foldershare\Plugin\Search;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessibleInterface;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\Core\Config\Config;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\Query\Condition;

use Drupal\Core\Extension\ModuleHandlerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;

use Drupal\search\Plugin\ConfigurableSearchPluginBase;
use Drupal\search\Plugin\SearchIndexingInterface;
use Drupal\search\SearchQuery;

use Drupal\Component\Utility\Unicode;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\FilenameExtensions;
use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Handles searching for files and folders using the Search module index.
 *
 * This class defines a Drupal core search plugin particular to searching
 * the fields and, optionally, file content of files and folders managed
 * by the FolderShare module. Like all search plugins, this plugin has
 * several tasks:
 *
 * - Collect site administrator configuration choices that guide how the
 *   search plugin works.
 *
 * - Create index entries for items that can be searched for.
 *
 * - Perform the search through the index.
 *
 * - Format search results for presentation to the user.
 *
 * @ingroup foldershare
 *
 * @SearchPlugin(
 *   id    = "foldershare_search",
 *   title = @Translation("FolderShare folders and files")
 * )
 */
class FolderShareSearch extends ConfigurableSearchPluginBase implements AccessibleInterface, SearchIndexingInterface, ContainerFactoryPluginInterface {

  /*--------------------------------------------------------------------
   *
   * Fields - dependency injection.
   *
   *------------------------------------------------------------------*/

  /**
   * A database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * An entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A module manager object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A config object for 'search.settings'.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $searchSettings;

  /**
   * The Drupal account to use for checking for access to advanced search.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The Renderer service to format the file or folder.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /*--------------------------------------------------------------------
   *
   * Construct.
   *
   *------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition) {

    // Construct a static plugin with the given parameters.
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('config.factory')->get('search.settings'),
      $container->get('renderer'),
      $container->get('current_user')
    );
  }

  /**
   * Constructs an instance of the plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   An entity type manager object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   A module manager object.
   * @param \Drupal\Core\Config\Config $searchSettings
   *   A config object for 'search.settings'.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The $account object to use for checking for access to advanced search.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    Config $searchSettings,
    RendererInterface $renderer,
    AccountProxyInterface $account) {

    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->searchSettings = $searchSettings;
    $this->renderer = $renderer;
    $this->account = $account;

    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /*--------------------------------------------------------------------
   *
   * Configuration form.
   *
   *------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    //
    // Return a default configuration that enables all search items.
    //
    $ext = implode(' ', FilenameExtensions::getText());
    return [
      'search_items' => [
        'search_file_content'         => TRUE,
        'search_file_extensions'      => $ext,
        'search_file_size'            => (int) (1 << 20),
        'show_advanced_keywords_form' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $formState) {
    //
    // Create a configuration form to enable site administrators to select
    // what can be searched.
    //
    // Create a form group for the searchable items.
    $form['search_items'] = [
      '#type'       => 'details',
      '#title'      => $this->t('Configure folder & file search'),
      '#open'       => TRUE,
      '#description' => $this->t(
        'Folder and file searches always support using the names, file extensions, dates, owner names, MIME types, descriptions, and comments. Additional search features may be configured here.'),
      '#attached'   => [
        'library'   => [
          Constants::LIBRARY_MODULE,
        ],
      ],
    ];

    // Get the current configuration.
    $c = &$this->configuration['search_items'];

    if (empty($c['search_file_content']) === TRUE) {
      $fileEnabled = TRUE;
    }
    else {
      $fileEnabled = $c['search_file_content'];
    }

    if (empty($c['search_file_extensions']) === TRUE) {
      $fileExtensions = '';
    }
    else {
      $fileExtensions = $c['search_file_extensions'];
    }

    if (empty($c['search_file_size']) === TRUE) {
      $fileSize = (int) (1 << 20);
    }
    else {
      $fileSize = (int) $c['search_file_size'];
    }

    if (empty($c['show_advanced_keywords_form']) === TRUE) {
      $showAdvKeywords = FALSE;
    }
    else {
      $showAdvKeywords = $c['show_advanced_keywords_form'];
    }

    // Map the numeric size to the text size name.
    //
    // This mapping is done specifically to insure that a hacked form
    // or YML configuration cannot be used to set an arbitrary file size.
    //
    // File sizes are also limited below to a "reasonable" size that is
    // sufficient for even very large text files. It is also sufficient
    // to get the header text (if any) out of binary files, such as those
    // for images and videos. Supporting larger sizes causes more file I/O
    // during indexing, more memory use for read in "text", more processing
    // during indexing, and more space in the database for index "text".
    switch ($fileSize) {
      default:
      case (int) (1 << 10):
        $fileSizeLabel = 0;
        break;

      case (int) ((1 << 10) * 10):
        $fileSizeLabel = 1;
        break;

      case (int) ((1 << 10) * 100):
        $fileSizeLabel = 2;
        break;

      case (int) (1 << 20):
        $fileSizeLabel = 3;
        break;

      case (int) ((1 << 20) * 10):
        $fileSizeLabel = 4;
        break;
    }

    $fileSizeOptions = [
      0 => $this->t('1 Kbyte'),
      1 => $this->t('10 Kbytes'),
      2 => $this->t('100 Kbytes'),
      3 => $this->t('1 Mbyte'),
      4 => $this->t('10 Mbytes'),
    ];

    // Include the advanced search form?
    $form['search_items']['show_advanced_keywords_form'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show the advanced keyword search form'),
      '#default_value' => $showAdvKeywords,
      '#return_value'  => 'enabled',
      '#required'      => FALSE,
      '#name'          => 'show_advanced_keywords_form',
      '#description'   => $this->t(
        'Enable the optional advanced search form to prompt for keywords to include and exclude, and a search phrase. Users must have the advanced search permission.'),
    ];

    // Search file content?
    $form['search_items']['search_file_content'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Add file text to the search index'),
      '#default_value' => $fileEnabled,
      '#return_value'  => 'enabled',
      '#required'      => FALSE,
      '#name'          => 'search_file_content',
      '#description'   => $this->t(
        'Enable users to search for text inside files. This is primarily effective with text-based file types (e.g. "txt", "csv", "htm", "pdf"), but it will also find text embedded in binary files (e.g. EXIF tags in image files).'),
    ];

    $form['search_items']['search_file_content_related'] = [
      '#type'         => 'container',
    ];

    $form['search_items']['search_file_content_related']['search_file_extensions'] = [
      '#type'          => 'textarea',
      '#rows'          => 3,
      '#default_value' => $fileExtensions,
      '#required'      => FALSE,
      '#name'          => 'search_file_extensions',
      '#states'        => [
        'invisible'    => [
          'input[name="search_file_content"]' => [
            'checked'  => FALSE,
          ],
        ],
      ],
      '#title'         => $this->t('File types to add to the search index:'),
      '#description'   => [
        '#type'        => 'html_tag',
        '#tag'         => 'p',
        '#value'       => $this->t(
          'Index the text content of specific file types only. Leave this blank to support all file types, or list file name extensions separated by spaces and without dots (e.g. "txt csv pdf").'),
      ],
    ];

    $form['search_items']['search_file_content_related']['search_file_size'] = [
      '#type'          => 'select',
      '#options'       => $fileSizeOptions,
      '#default_value' => $fileSizeLabel,
      '#required'      => FALSE,
      '#name'          => 'search_file_size',
      '#states'        => [
        'invisible'    => [
          'input[name="search_file_content"]' => [
            'checked'  => FALSE,
          ],
        ],
      ],
      '#title'         => $this->t('Maximum size of file content indexed'),
      '#description'   => [
        '#type'        => 'html_tag',
        '#tag'         => 'p',
        '#value'       => $this->t(
          'Limit file content so that it does not overwhelm the search index. A few Kbytes is usually sufficient for text files.'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $formState) {
    //
    // Update the configuration with the selected search features.
    //
    $reindex = FALSE;
    $c = &$this->configuration['search_items'];

    // Advanced search form.
    $c['show_advanced_keywords_form'] =
      ($formState->getValue('show_advanced_keywords_form') === 'enabled');

    // File content indexing enable/disable.
    $fileEnabled = ($formState->getValue('search_file_content') === 'enabled');
    if ($c['search_file_content'] !== $fileEnabled) {
      $c['search_file_content'] = $fileEnabled;

      // Trigger reindexing since whether or not to include file
      // content has changed.
      $reindex = TRUE;
    }

    // File extensions to index.
    $fileExtensions = $formState->getValue('search_file_extensions');
    if ($c['search_file_extensions'] !== $fileExtensions) {
      $c['search_file_extensions'] = $fileExtensions;
      if ($fileEnabled === TRUE) {
        // Trigger reindexing since the file extensions to support
        // has changed.
        $reindex = TRUE;
      }
    }

    // File size.
    $fileSizeLabel = $formState->getValue('search_file_size');
    if ($c['search_file_size'] !== $fileSizeLabel) {
      // Map the size choice to the numeric size. This mapping is needed to
      // insure that a hacked form cannot introduce a ridiculous file size.
      switch ($fileSizeLabel) {
        default:
        case 0:
          $fileSize = (int) (1 << 10);
          break;

        case 1:
          $fileSize = (int) ((1 << 10) * 10);
          break;

        case 2:
          $fileSize = (int) ((1 << 10) * 100);
          break;

        case 3:
          $fileSize = (int) (1 << 20);
          break;

        case 4:
          $fileSize = (int) ((1 << 20) * 10);
          break;
      }

      $c['search_file_size'] = $fileSize;
      if ($fileEnabled === TRUE) {
        // Trigger reindexing since the file size to index
        // has changed.
        $reindex = TRUE;
      }
    }

    if ($reindex === TRUE) {
      // Clear the search index and start over in future CRON jobs.
      $this->indexClear();
    }
  }

  /*--------------------------------------------------------------------
   *
   * Accessibility.
   *
   *------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function access(
    $operation = 'view',
    AccountInterface $account = NULL,
    $returnAsObject = FALSE) {
    //
    // Generically check if the user has enough permissions to issue
    // a search and view results. This DOES NOT check per-folder
    // access grants because this method is called only for the entire
    // search operation, not per folder.
    //
    $entityType = $this->entityTypeManager->getDefinition(
      FolderShare::ENTITY_TYPE_ID);

    // Allow administrators and users with view or author permissions.
    $perm = $entityType->getAdminPermission();
    if (empty($perm) === TRUE) {
      $perm = Constants::ADMINISTER_PERMISSION;
    }

    // Administrator?
    $ac = AccessResult::allowedIfHasPermission($account, $perm);
    if ($ac->isAllowed() === TRUE) {
      return ($returnAsObject === TRUE) ? $ac : $ac->isAllowed();
    }

    // Author?
    $ac = AccessResult::allowedIfHasPermission(
      $account,
      Constants::AUTHOR_PERMISSION);
    if ($ac->isAllowed() === TRUE) {
      return ($returnAsObject === TRUE) ? $ac : $ac->isAllowed();
    }

    // Viewer?
    $ac = AccessResult::allowedIfHasPermission(
      $account,
      Constants::VIEW_PERMISSION);
    if ($ac->isAllowed() === TRUE) {
      return ($returnAsObject === TRUE) ? $ac : $ac->isAllowed();
    }

    // Otherwise the user does not have permission to access
    // the content.
    return ($returnAsObject === TRUE) ? AccessResult::forbidden() : FALSE;
  }

  /*--------------------------------------------------------------------
   *
   * Search form.
   *
   * The basic search page supports a single keyword field for a list
   * of space-separated words to search for. These are added to the
   * search page URL.
   *
   * This plugin extends the search page to support "advanced" search
   * abilities similar to those for nodes, including keywords to
   * exclude, alternate keywords, and an exact phrase. These additional
   * items are also encoded into the search page URL using an expression-like
   * syntax: <keyword> <keyword> ... "<phrase>" ... OR <keyword> <keyword>.
   *
   *------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function buildSearchUrlQuery(FormStateInterface $formState) {
    //
    // Return a keyword string to include as a query parameter on
    // the search page URL.
    //
    // The returned keywords always include the pieces:
    // - Keywords to include.
    // - Keywords to exclude by preceding them with a '-'.
    // - "OR" conditions.
    // - A phrase in double quotes.
    //
    // There must be at least one keyword to include, but the rest of
    // the pieces can be empty.
    $c = &$this->configuration['search_items'];

    if (empty($c['show_advanced_keywords_form']) === TRUE ||
        $c['show_advanced_keywords_form'] === FALSE) {
      // The advanced keywords search form is disabled. Just return
      // the keyword string from the primary 'keys' field. This field's
      // value may still be a search expression.
      $keywords = trim($formState->getValue('keys'));
    }
    else {
      // The advanced keywords search form is enabled. Get values from
      // each form element and assemble a search expression.
      $keywords         = trim($formState->getValue('keys'));
      $orKeywords       = trim($formState->getValue('or'));
      $negativeKeywords = trim($formState->getValue('negative'));
      $phrase           = trim($formState->getValue('phrase'));

      // Build the URL parameter, starting with the basic form keywords
      // and appending the other values.
      if (empty($orKeywords) === FALSE) {
        // Add <keyword> OR <keyword> OR ...
        if (preg_match_all(
          '/ ("[^"]+"|[^" ]+)/i',
          ' ' . $orKeywords,
          $matches) === 1) {
          $keywords .= ' OR ' . implode(' OR ', $matches[1]);
        }
      }

      if (empty($negativeKeywords) === FALSE) {
        // Add -<keyword> -<keyword> ...
        if (preg_match_all(
          '/ ("[^"]+"|[^" ]+)/i',
          ' ' . $negativeKeywords,
          $matches) === 1) {
          $keywords .= ' -' . implode(' -', $matches[1]);
        }
      }

      if (empty($phrase) === FALSE) {
        // Add "<phrase>".
        $keywords .= ' "' . str_replace('"', ' ', $phrase) . '"';
      }

      $keywords = trim($keywords);
    }

    // Make the keywords a GET parameter.
    //
    // Even if the keywords are empty, add them as a parameter because the
    // search page controller uses the parameter's existence to decide if
    // it should check for search results.
    return ['keys' => $keywords];
  }

  /**
   * {@inheritdoc}
   */
  public function searchFormAlter(array &$form, FormStateInterface $formState) {
    //
    // Alter the basic search form.
    //
    $c = &$this->configuration['search_items'];

    if (empty($c['show_advanced_keywords_form']) === FALSE &&
        $c['show_advanced_keywords_form'] === TRUE &&
        $this->account !== NULL &&
        $this->account->hasPermission('use advanced search') === TRUE) {
      // The plugin enableds the advanced form AND the user has
      // permission to use it. Add the form.
      $this->addAdvancedKeywordsForm($form, $formState);
    }

    // Add a description.
    if (empty($c['search_file_content']) === TRUE ||
        $c['search_file_content'] === FALSE) {
      $description = $this->t('Search for text in folder and file names, filename extensions, descriptions, comments, and owner names.');
    }
    else {
      $description = $this->t('Search for text in folder and file names, filename extensions, descriptions, comments, owner names, and file content.');
    }

    $form['basic']['description'] = [
      '#type'  => 'html_tag',
      '#tag'   => 'p',
      '#value' => $description,
    ];

    // Set the search field's placeholder.
    $form['basic']['keys']['#attributes']['placeholder'] = $this->t('Search...');
  }

  /**
   * Adds advanced keyword search fields to the search form.
   *
   * By default, the search form only includes a single keywords text field.
   * Several standard search plugins (such as that for nodes) add fields to
   * prompt for:
   * - Additional alternate keywords to be OR-ed together.
   * - Keywords to exclude.
   * - A search phrase.
   *
   * The functionality offered by these added fields is entirely redundant.
   * The main keyword entry text field always support "OR", excluded keywords
   * (starting with "-"), and a search phrase (surrounded by double quotes).
   * But for compatability, this function adds these same search fields to
   * the search form.
   */
  private function addAdvancedKeywordsForm(
    array &$form,
    FormStateInterface $formState) {
    //
    // Get initial values
    // ------------------
    // Get the current keyword string and prase it into separate text for
    // each of the advanced search form fields.
    $rawKeywords = ' ' . $this->getKeywords() . ' ';
    $matches = [];

    $phraseDefault = '';
    $orDefault = '';
    $negativeDefault = '';

    // Look for a quoted phrase in the keywords. The advanced search
    // only supports a single phrase, so take the first one.
    if (preg_match('/ "([^"]+)" /', $rawKeywords, $matches) === 1) {
      // Phrase found. Save.
      $phraseDefault = $matches[1];

      // Remove it from the keywords.
      $rawKeywords = str_replace($matches[0], ' ', $rawKeywords);
    }

    // Look for words with a '-' prefix.
    if (preg_match_all('/ -([^ ]+)/', $rawKeywords, $matches) === 1) {
      // Negative words found. Save.
      $negativeDefault = implode(' ', $matches[1]);

      // Remove them from the keywords.
      $rawKeywords = str_replace($matches[0], ' ', $rawKeywords);
    }

    // Look for words separated by 'OR'. The advanced search only supports
    // one set of OR words, so take the first one.
    if (preg_match('/ [^ ]+( OR [^ ]+)+ /', $rawKeywords, $matches) === 1) {
      // OR words found. Split the list on 'OR' and save.
      $words = explode(' OR ', trim($matches[0]));
      $orDefault = implode(' ', $words);

      // Remove them from the keywords.
      $rawKeywords = str_replace($matches[0], ' ', $rawKeywords);
    }

    // Use whatever remains as the generic set of keywords for the
    // basic form.
    $keywords = trim($rawKeywords);

    //
    // Revised basic form
    // ------------------
    // Above we've reduced the list of primary keywords from the initial
    // value from getKeywords() to a subset that doesn't include "OR",
    // words starting with "-", or anything between double-quotes. Update
    // the primary keyword search field to this reduced list.
    $form['basic']['keys']['#default_value'] = $keywords;

    //
    // Build and initialized advanced settings
    // ---------------------------------------
    // See if any of the advanced keyword features were used.
    $hasAdvanced = (empty($phraseDefault) === FALSE) ||
      (empty($orDefault) === FALSE) ||
      (empty($negativeDefault) === FALSE);

    // Create a group for advanced search settings.
    $form['advanced'] = [
      '#type'          => 'details',
      '#title'         => $this->t('Advanced search'),
      '#attributes'    => [
        'class'        => ['search-advanced'],
      ],
      '#open'          => $hasAdvanced,
    ];

    // Containing any of the words?
    $form['advanced']['or'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Containing any of the words:'),
      '#size'          => 30,
      '#maxlength'     => 255,
      '#default_value' => $orDefault,
    ];

    // Containing the phrase?
    $form['advanced']['phrase'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Containing the phrase:'),
      '#size'          => 30,
      '#maxlength'     => 255,
      '#default_value' => $phraseDefault,
    ];

    // Containing none of the words?
    $form['advanced']['negative'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Containing none of the words:'),
      '#size'          => 30,
      '#maxlength'     => 255,
      '#default_value' => $negativeDefault,
    ];
  }

  /*--------------------------------------------------------------------
   *
   * Search indexing.
   *
   * These functions control the creation of a search index that
   * records information about files and folders.
   *
   * The search module only allows a plugin to have a single search
   * index (the name is returned by getType()). This is awkward here
   * because we need to support searching for both folders and files
   * in the folders.
   *
   * Further, a search index has a single "search ID" which is intended
   * to hold the entity ID of the item in the index. For user search,
   * this is the UID. For node search, this is the node ID. But for
   * this search plugin, we need this to be EITHER a folder ID or a
   * file ID. But given a simple numeric ID, it is impossible to determine
   * if the ID is for a folder or file. We therefore need to indicate
   * folder vs. file with something else in the index.
   *
   * It'd be nice to say that a negative ID is a file, and a positive ID
   * is a folder. Except that the search index forces IDs to be unsigned
   * integers.
   *
   * The only other database field available to us is the 'langcode'
   * field, which is intended to indicate the language used by the entity.
   * For this search plugin, we introduce a new 'language' of 'file'
   * to mean a file entry. Any other value is a folder entry.
   *
   *------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getType() {
    //
    // Return the name of the search index used.
    //
    // While it is common for search plugins to name their search index
    // after the plugin's ID, we need to use a well-known name so that
    // other parts of the module can refer to the search index by name,
    // without knowing the name of the search plugin.
    //
    return Constants::SEARCH_INDEX;
  }

  /**
   * {@inheritdoc}
   */
  public function indexClear() {
    //
    // Clear all search index.
    //
    search_index_clear($this->getType());

    // Clear the render cache too because re-indexing may need to re-render
    // entities, and having them cached is a problem.
    Cache::invalidateTags(['rendered']);
  }

  /**
   * {@inheritdoc}
   */
  public function markForReindex() {
    //
    // Mark the search index as in need of re-indexing. This flags every
    // entry in the index as out of date. Later, during indexing, these
    // flags are gradually flipped.
    //
    search_mark_for_reindex($this->getType());
  }

  /**
   * {@inheritdoc}
   */
  public function indexStatus() {
    //
    // Indicate the total number of items to index, and the number
    // remaining to index.
    //
    // Get total indexable
    // -------------------
    // Get the number of files and folders.
    $totalIndexable = FolderShare::countNumberOfItems();

    //
    // Get remaining
    // -------------
    // The number of items remaining to index equals the number of
    // items marked as in need of reindexing the search index.
    $totalRemaining = $this->database->query(
      'SELECT COUNT(DISTINCT fs.id) FROM {' . FolderShare::BASE_TABLE . '} fs LEFT JOIN {search_dataset} sd ON sd.sid = fs.id AND sd.type = :searchIndex WHERE sd.sid IS NULL OR sd.reindex <> 0',
      [
        ':searchIndex' => $this->getType(),
      ])->fetchField();

    return [
      'remaining' => $totalRemaining,
      'total'     => $totalIndexable,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex() {
    //
    // Update the search index by adding a few more entries. This function
    // may be invoked via CRON, so it needs to limit its work to only a
    // few items or risk a CRON job that runs out of time and fails.
    $storage = $this->entityTypeManager->getStorage(
      FolderShare::ENTITY_TYPE_ID);

    // The search module supports a setting for the "CRON limit" to
    // specify the number of items to index on each CRON run.  We use
    // this to limit the number of folders or files indexed.
    $cronLimit = (int) $this->searchSettings->get('index.cron_limit');

    //
    // Index pending items
    // -------------------
    // Get pending items to index. This searches the index table and joins
    // it with the FolderShare table. The result are entries that are items
    // that have not been indexed yet. This also pulls in new items
    // that have not yet had their IDs added to the index table.
    $query = $this->database->select(FolderShare::BASE_TABLE, 'fs');
    $query->addField('fs', 'id', 'id');
    $query->leftJoin(
      'search_dataset',
      'sd',
      'sd.sid = fs.id AND sd.type = :searchIndex',
      [
        ':searchIndex' => $this->getType(),
      ]);
    $query->addExpression(
      'CASE MAX(sd.reindex) WHEN NULL THEN 0 ELSE 1 END',
      'ex');
    $query->addExpression('MAX(sd.reindex)', 'ex2');
    $query->condition(
      $query->orConditionGroup()
        ->where('sd.sid IS NULL')
        ->condition('sd.reindex', 0, '<>'));
    $query->orderBy('ex', 'DESC');
    $query->orderBy('ex2');
    $query->orderBy('fs.id');
    $query->groupBy('fs.id');
    $query->range(0, $cronLimit);

    // Execute the query. The only value returned for each record is the ID.
    $ids = $query->execute()
      ->fetchCol();

    // Load each folder and index it. If this takes too long and we hit
    // PHP's execution limit, then some of the items will have been indexed.
    // These will have been marked as indexed in the database and the next
    // time this CRON task is called, those items won't show up in the above
    // query. We'll just start with the next item that hasn't been indexed yet.
    foreach ($ids as $id) {
      $this->indexItem($storage->load($id));
    }

    $cronLimit -= count($ids);
    if ($cronLimit <= 0) {
      // Hit limit.
      return;
    }
  }

  /**
   * Indexes a single file or folder item.
   *
   * The item's name and field data is added to the index. Depending upon
   * the plugin's configuraiton, file content may also be scanned for words
   * to add to the index.
   *
   * @param \Drupal\foldershare\FolderShareInterface $item
   *   The item to index.
   */
  private function indexItem(FolderShareInterface $item) {
    //
    // Update the index.
    //
    // Get file content
    // ----------------
    // If the plugin has file content indexing enabled, the item is a file,
    // and the file's name extension is allowed for indexing, then collect
    // text from the file's content.
    $fileText = '';

    $c = &$this->configuration['search_items'];

    // For file-based items, get the underlying File entity.
    switch ($item->getKind()) {
      case FolderShare::FILE_KIND:
        $file = $item->getFile();
        break;

      case FolderShare::IMAGE_KIND:
        $file = $item->getImage();
        break;

      default:
        $file = NULL;
        break;
    }

    $info = [];
    if ($file !== NULL &&
        $c['search_file_content'] === TRUE &&
        (int) $c['search_file_size'] > 0) {
      // Get the file's extension and check if it is acceptable.
      $uri = $file->getFileUri();
      $info = pathinfo($uri);
      $exts = $c['search_file_extensions'];

      if (empty($exts) === TRUE ||
          in_array($info['extension'], explode(' ', $exts)) === TRUE) {
        // Index the file's content.
        $fp = @fopen($uri, 'r');
        if ($fp !== FALSE) {
          // Read the text.
          $fileText = fread($fp, $c['search_file_size']);
          fclose($fp);

          // Guess the file's encoding. Text files are usually found to
          // be UTF-8, while binary files (e.g. images) are usually found
          // to be ASCII (though this isn't guaranteed).
          $encoding = mb_detect_encoding($fileText, 'UTF-8', TRUE);
          if ($encoding === FALSE) {
            $encoding = mb_detect_encoding($fileText, 'auto', TRUE);
            if ($encoding === FALSE) {
              $encoding = 'ASCII';
            }
          }

          // Get the Search module's configured minimum word size.
          $minimumSize = \Drupal::config('search.settings')->get('index.minimum_word_size');
          if ($minimumSize <= 3) {
            $minimumSize = 3;
          }

          // Filter the file's text before handing it off to the Search module.
          // The module does its own filtering, but we can save it some effort
          // by pre-filtering to reduce the text size.
          //
          // - Replace non-graphical characters with spaces. This gets rid of
          //   control characters and other special codes. For data from a
          //   binary file (e.g. images), this strips out characters
          //   misinterpreted from binary data bytes.
          //
          // - Remove short words. Particularly for binary data, we can get a
          //   lot of 1 or 2 character "words" that are meaningless.
          //
          // - Collapse multiple spaces into a single space. Replacing
          //   non-graphical characters earlier may have created a large
          //   runs of spaces that we can reduce.
          if ($encoding !== 'ASCII') {
            if ($encoding !== 'UTF-8') {
              $fileText = mb_convert_encoding($fileText, 'UTF-8', $encoding);
            }

            $removeNonGraphical = '/[^[:graph:]]+/u';
            $removeShort = '/ [[:graph:]]{1,' . ($minimumSize - 1) . '} /u';
            $collapseSpaces = '/[ ]{2,}/u';
          }
          else {
            $removeNonGraphical = '/[^[:graph:]]+/';
            $removeShort = '/ [[:graph:]]{1,' . ($minimumSize - 1) . '} /';
            $collapseSpaces = '/[ ]{2,}/';
          }

          $filteredText = preg_replace($removeNonGraphical, '  ', $fileText);
          if ($filteredText !== NULL) {
            $filteredText = preg_replace($removeShort, ' ', $filteredText);
          }

          if ($filteredText !== NULL) {
            $filteredText = preg_replace($collapseSpaces, ' ', $filteredText);
          }

          if ($filteredText !== NULL) {
            $fileText = $filteredText;
          }

          unset($filteredText);
        }
      }
    }

    //
    // Get names
    // ---------
    // The search index entry for this entity should include the entity's
    // name prominently since the name has the most likely words used for
    // a search.
    //
    // However, the renderable version of an entity usually does not include
    // the entity's name, which is left to the page title instead. So here
    // we collect the entity name, and variants without the filename
    // extension, for explicit addition to the indexed version of the entity.
    $nameWords = [];
    $nameWords[] = $item->getName();

    if ($file !== NULL) {
      $filename = $file->getFilename();
      $nameWords[] = $filename;

      if (isset($info['extension']) === TRUE) {
        $filenameBase = basename($filename, '.' . $info['extension']);
      }
      else {
        $filenameBase = basename($filename);
      }

      $nameWords[] = $filenameBase;

      $found = [];
      preg_match_all('/[[:alpha:]]+/u', $filename, $found, PREG_PATTERN_ORDER);
      $nameWords = array_unique(array_merge($nameWords, $found[0]));
    }

    //
    // Get owner
    // ---------
    // The search index entry for this entity should include the account
    // name and display name of the entity's owner, since these are useful
    // qualifiers on a search.
    //
    // However, the renderable version of an entity may or may not include
    // the owner's name, depending upon how the site has configured the
    // display of the entity. So here we collect the account and display
    // name of the owner for explicit addition to the indexed version of
    // the entity.
    $owner = $item->getOwner();
    $ownerWords = [
      $owner->getAccountName(),
      $owner->getDisplayName(),
    ];

    //
    // Get status, kind, and MIME type
    // -------------------------------
    // The search index entry for this entity should include some
    // administrative descriptions of the entity including:
    // - It's kind (e.g. 'file', 'folder').
    // - It's sharing status.
    // - It's MIME type.
    //
    // As above, the renderable version of an entity may or may not include
    // these values, so we collect them here to be added below.
    $mime = $item->getMimeType();
    $adminWords = [
      Utilities::translateKind($item->getKind()),
      $this->t($item->getSharingStatus()),
      $mime,
    ];
    $adminWords = array_merge($adminWords, explode('/', $mime));

    //
    // Render entity plain
    // -------------------
    // Get the search index view of the item. This is expected to be a
    // reduced form of the entity view that omits field labels, the user
    // interface, pseudo fields, and fluff. This only leaves the field
    // values we want to add to the index.
    //
    // Then render the item through that view to get plain text.
    $langcode = $item->langcode->value;
    $builder = $this->entityTypeManager->getViewBuilder(
      FolderShare::ENTITY_TYPE_ID);

    $build = $builder->view($item, 'search_index', $langcode);
    unset($build['#theme']);

    $renderedText = $this->renderer->renderPlain($build);
    unset($build);

    //
    // Call hooks
    // ----------
    // Invoke hooks (if any) to add their own search text to the item,
    // and append that text to the rendered entity.
    $hookText = $this->moduleHandler->invokeAll(
      FolderShare::ENTITY_TYPE_ID . '_update_index',
      [$item]);

    //
    // Build indexed text
    // ------------------
    // Create the text to be indexed. Indexing considers words earlier in
    // the text and/or within heading tags to be more important. So build
    // the indexed text with:
    // - Name words in <h1>.
    // - Owner words in <h2>.
    // - Administrative words in <h3>.
    // - Text from the rendered entity.
    // - Text from hooks.
    // - Text from file content.
    $text = '<h1>' . implode(' ', $nameWords) . '</h1> ';
    $text .= '<h2>' . implode(' ', $ownerWords) . '</h2> ';
    $text .= '<h3>' . implode(' ', $adminWords) . '</h3> ';
    $text .= $renderedText;
    $text .= '<p>' . implode(' ', $hookText) . '</p> ';
    $text .= '<p>' . $fileText . '</p>';

    unset($nameWords);
    unset($ownerWords);
    unset($renderedText);
    unset($hookText);
    unset($fileText);

    //
    // Update search index
    // -------------------
    // Add the text to the search index.
    search_index($this->getType(), $item->id(), $langcode, $text);
  }

  /*--------------------------------------------------------------------
   *
   * Search.
   *
   *------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function suggestedTitle() {
    //
    // Returns a page title.
    //
    // If the user has not yet entered search keywords, return a generic
    // page title.
    $keywords = $this->getKeywords();
    if (empty($keywords) === TRUE) {
      return $this->t('Search folders and files');
    }

    // Otherwise, return a title that appends the entered search keywords.
    // Truncate the keywords, if needed.
    return $this->t(
      'Search folders and files for @keywords',
      [
        '@keywords' => Unicode::truncate($keywords, 60, TRUE, TRUE),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    //
    // Executes a search, if possible, and returns a structured
    // list of search results.
    //
    // Validate
    // --------
    // The base class provides isSearchExecutable(), which is TRUE
    // if any keywords have been provided by the user. The search
    // view controller should already have confirmed this is TRUE
    // before executing the search, but it doesn't hurt to check again.
    if ($this->isSearchExecutable() === FALSE) {
      // The search is not executable. The user has not provided any
      // keywords to guide the search, so return nothing.
      return [];
    }

    //
    // Get keywords and parameters
    // ---------------------------
    // Keywords come from the search form, URL, or a direct call to
    // the plugin's setSearch(). The single keyword string has embedded
    // syntax that uses a '-' in front of keywords to exclude, 'OR'
    // between keyword alternatives, and a double-quoted phrase.
    //
    // Parameters come from the URL or a call to setSearch(). The
    // 'parentid' parameter, if present, gives the FolderShare entity ID
    // for a parent entity who's children are to be searched. If the ID
    // is FolderShareInterface::ANY_ITEM_ID, then the search is not
    // constrained to a parent's folder tree.
    $keywords = $this->getKeywords();

    // Get a parent entity ID for folder tree-based search.
    $parentId = FolderShareInterface::ANY_ITEM_ID;
    $parameters = $this->getParameters();
    if (empty($parameters) === FALSE) {
      if (isset($parameters['parentId']) === TRUE) {
        $parentId = (int) intval($parameters['parentId']);
      }
    }

    //
    // Search
    // ------
    // Execute the search and collect the results.
    $results = $this->search($keywords, $parentId);
    if (empty($results) === TRUE) {
      // The search produced nothing, so return nothing.
      return [];
    }

    //
    // Format
    // ------
    // Format the search results and return them.
    return $this->formatResults($results);
  }

  /**
   * Searches the search index and returns results.
   *
   * On success, an array of search results is returned. On failure,
   * the returned array may be truncated or empty and an error message
   * may have been presented to the user.
   *
   * @param string $keywords
   *   A keywords string that may include multiple words. A '-' before a
   *   word negates the word in the search, and an 'OR' between words
   *   selects alternatives. A group of words within double-quotes indicates
   *   a search phrase.
   * @param int $parentId
   *   (optional, default = FolderShareInterface::ANY_ITEM_ID) Constrain the
   *   search to the descendants of the folder with the indicated FolderShare
   *   entity ID. If the value is negative or FolderShareInterface::ANY_ITEM_ID,
   *   the search is not constrained to a specific folder tree.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   Returns results from a search query, or NULL if the search failed.
   *
   * @todo This function constrains the search to only include descendants
   * of the given parent. The current implementation gets a list of all
   * descendant IDs, then adds an SQL condition that the returned items must
   * be one of these IDs. For a large folder tree, however, the list of
   * descendant IDs could be very large. It takes to create the list, and
   * a large list may exceed SQL's condition limits. This function should
   * be rewritten to consider this.
   */
  private function search(
    string $keywords,
    int $parentId = FoldershareInterface::ANY_ITEM_ID) {
    //
    // Query
    // -----
    // Build and execute the search index query, including special
    // search handling and a default pager. Add the search keywords
    // and the name of the search index to use.
    //
    // The search API *requires* that the search index table be
    // aliased to 'i'.
    $query = $this->database
      ->select('search_index', 'i')
      ->extend('Drupal\search\SearchQuery')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->searchExpression($keywords, $this->getType());
    $query->addField('i', 'langcode');
    $query->groupBy('i.langcode');

    // Join with the FolderShare table, matching the search index's entity ID
    // against the FolderShare table entry's ID. Add fields to return so
    // that we can format them in the search results.
    $query->join('foldershare', 'fs', 'fs.id = i.sid');
    $query->addField('fs', 'name');
    $query->addField('fs', 'uid');
    $query->addField('fs', 'changed');
    $query->addField('fs', 'kind');
    $query->addField('fs', 'mime');
    $query->addField('fs', 'size');

    // Block hidden and disabled items from search.
    $query->condition('fs.systemhidden', TRUE, '!=');
    $query->condition('fs.systemdisabled', TRUE, '!=');

    // Modify the query (if needed) to return only those entities that
    // are viewable by the current user. Criteria are:
    //
    // - If the user is an administrator, they have access to all content
    //   and the search does not need to be constrained.
    //
    // - Otherwise if the site has sharing disabled, then users only have
    //   access to content owned by themselves. Constrain the search
    //   accordingly.
    //
    // - Otherwise if the site has sharing enabled, but sharing with
    //   anonymous disabled AND the current user is anonymous, then they
    //   only have access to content owned by anonymous. Constrain the
    //   search accordingly.
    //
    // - Otherwise the site has sharing enabled and the user is allowed to
    //   access any file or folder where its root folder explicitly grants
    //   the user access. Constraint the search accordingly.
    $hasAdmin = $this->account->hasPermission(Constants::ADMINISTER_PERMISSION);
    if ($hasAdmin === FALSE) {
      // The user is NOT an administrator, so they don't have automatic
      // access to everything. Add an appropriate query condition.
      //
      // Regardless of the whether the current user is anonymous or not,
      // they have access to any items where the root folder above them
      // grants view access to the user.
      //
      // Join with the view grants table to get the root folder's grants.
      // Note that the owner of an item is always listed in the item's
      // view grants table. So this query condition allows the user access
      // to all of their own items, even if they weren't shared with others,
      // PLUS anything owned by somebody else and shared with this user.
      //
      // Two cases exist, though:
      // - The row's entity has a root ID, so we want that root's view grants.
      // - The row's entity IS a root, so it has no root ID, and we want
      //   the entity's own view grants.
      $query->join(
        'foldershare__grantviewuids',
        'view',
        '(fs.rootid = view.entity_id) or (fs.rootid is null and view.entity_id = fs.id)');

      $query->condition(
        'view.grantviewuids_target_id',
        $this->account->id(),
        '=');
    }

    // If there was a parent folder, then constrain the search to a set of
    // descendants of that parent.
    if ($parentId >= 0) {
      $descendantIds = [];
      $parent = FolderShare::load($parentId);
      if ($parent !== NULL) {
        $descendantIds = $parent->findDescendantIds();
      }

      // TODO REWRITE: For a large folder tree, the list of descendant IDs
      // could be very large, and perhaps too large to add on as a condition
      // to the query. How else can we do this?
      if (empty($descendantIds) === FALSE) {
        $query->condition('i.sid', $descendantIds, 'IN');
      }
      else {
        // With no descendants, there can be nothing found in a search.
        return NULL;
      }
    }

    // Execute the query and get the results.
    $results = $query->execute();

    //
    // Check for problems
    // ------------------
    // Report problems to the user. Search queries report the following
    // types of errors:
    // - NO_POSITIVE_KEYWORDS = all of the keywords were preceded with '-'.
    // - EXPRESSIONS_IGNORED = there were too many keyword OR clauses.
    // - LOWER_CASE_OR = an OR clause was in lower case.
    // - NO_KEYWORD_MATCHES = nothing found.
    //
    // The NO_KEYWORD_MATCHES error also returns an empty results array,
    // which is simply returned as-is. The other errors require an error
    // message.
    $status = $query->getStatus();

    if (($status & SearchQuery::NO_POSITIVE_KEYWORDS) !== 0) {
      // The user didn't enter any keywords to find, just keywords
      // to ignore.
      \Drupal::messenger()->addMessage(
        $this->t(
          'Please include at least one search keyword. Keywords must be at least @count characters. Punctuation is ignored.',
          [
            '@count' => $this->searchSettings->get('index.minimum_word_size'),
          ]),
        'warning');
    }

    if (($status & SearchQuery::EXPRESSIONS_IGNORED) !== 0) {
      // The user's search keywords were too complex and were partly ignored.
      \Drupal::messenger()->addMessage(
        $this->t(
          'Your search used too many AND/OR expressions. Only the first @count terms were included in this search.',
          [
            '@count' => $this->searchSettings->get('and_or_limit'),
          ]),
        'warning');
    }

    if (($status & SearchQuery::LOWER_CASE_OR) !== 0) {
      // The user entered a lower-case 'or' when they should have
      // used an uppercase 'OR'.
      \Drupal::messenger()->addMessage(
        $this->t('Please use an uppercase <strong>OR</strong> to search for either of the two terms. For example, <strong>cats OR dogs</strong>.'),
        'warning');
    }

    return $results;
  }

  /**
   * Formats search results for presentation.
   *
   * Search plugins are expected to return an array of results with several
   * standard variables set in each entry. Required variables:
   * - 'title' = the name of the item.
   * - 'link'  = the URL of the item.
   *
   * Optional variables:
   * - 'date' = the date of the item.
   * - 'extra' = additional text from hooks.
   * - 'language' = the language code of the item.
   * - 'plugin_id' = the ID of this plugin.
   * - 'score' = the calculated search ranking.
   * - 'snippet' = a text snippet showing where search keywords were found.
   * - 'user' = the name of the user.
   *
   * This function supports all of the above, and adds several variables
   * specific to FolderShare entities:
   * - 'kind' = the kind name of the item (e.g. file, folder, etc.).
   * - 'mime' = the MIME type of the item.
   * - 'size' = the size of the item, in bytes.
   * - 'userid' = the ID of the user.
   * - 'userurl' = the URL of the user.
   * - 'status' = the sharing status.
   *
   * The standard 'date' variable is set to the last-modified (changed) date
   * for the entity.
   *
   * The standard 'user' variable is set to the owner's display name.
   *
   * @param \Drupal\Core\Database\StatementInterface $results
   *   Results found from a successful search.
   *
   * @return array
   *   Returns a renderable array containing presentable search results.
   */
  private function formatResults(StatementInterface $results) {
    //
    // Setup
    // -----
    // Get the storage manager.
    $storage = $this->entityTypeManager->getStorage(
      FolderShare::ENTITY_TYPE_ID);

    // Get the builder.
    $builder = $this->entityTypeManager->getViewBuilder(
      FolderShare::ENTITY_TYPE_ID);

    // Get the search keywords.
    $keywords = $this->getKeywords();

    //
    // Build the variable array
    // ------------------------
    // Loop through the search results and create the needed variables for
    // each item. We don't know which of these variables will be used by
    // the presentation template, so we need to create them all.
    $rows = [];
    $hasUserAccess = $this->account->hasPermission('access user profiles');
    foreach ($results as $result) {
      $id = $result->sid;

      // Load the item.
      $item = $storage->load($id);

      //
      // Build the snippet. This is formed in stages:
      // - Create a simply rendered version of the item.
      // - Invoke hooks to update the text with comment info.
      // - Invoke hooks to update the text with extra info.
      // - Use the search API to create a snippet using the keywords.
      //
      // The snippet is an abbreviated form of the fully rendered item,
      // keeping only those parts of the item that include the keywords.
      //
      // When this search entry has been found because keywords matched
      // a file's content, the rendered version may have no keyword matches
      // and the snippet generator won't show anything.
      $build = $builder->view($item, 'search_result', $result->langcode);
      unset($build['#theme']);

      $text = $this->renderer->renderPlain($build);
      $this->addCacheableDependency(
        CacheableMetadata::createFromRenderArray($build));

      // Invoke comment hooks.
      $text .= ' ' . $this->moduleHandler->invoke(
        'comment',
        FolderShare::ENTITY_TYPE_ID . '_update_index',
        [$item]);

      // Invoke search result hooks.
      $extra = $this->moduleHandler->invokeAll(
        FolderShare::ENTITY_TYPE_ID . '_search_result',
        [$item]);

      $snippet = search_excerpt($keywords, $text, $result->langcode);

      //
      // Build the user name and URL from the item's owner.
      $user = user_load($result->uid);
      if ($user === NULL) {
        // Odd.
        $userName = 'Unknown (' . $result->uid . ')';
        $userUrl = '';
      }
      elseif ($hasUserAccess === FALSE) {
        $userName = $user->getDisplayName();
        $userUrl = '';
      }
      else {
        $userName = $user->getDisplayName();
        $userUrl = $user->url('canonical', ['absolute' => TRUE]);
      }

      //
      // Add a row with variables set for later use in rendering this row.
      $row = [
        // Administrative variables.
        'type'     => FolderShare::ENTITY_TYPE_ID,
        FolderShare::ENTITY_TYPE_ID => $item,
        'score'    => $result->calculated_score,

        // Required variables.
        'title'    => $item->getName(),
        'link'     => $item->url('canonical', ['absolute' => TRUE]),

        // Standard optional variables.
        'extra'    => $extra,
        'snippet'  => $snippet,
        'language' => $result->langcode,
        'date'     => $result->changed,
        'user'     => $userName,

        // Additional variables.
        'userid'   => $result->uid,
        'userurl'  => $userUrl,
        'kind'     => $result->kind,
        'mime'     => $result->mime,
        'size'     => $result->size,
      ];

      $this->addCacheableDependency($item);

      $rows[] = $row;
    }

    return $rows;
  }

}
