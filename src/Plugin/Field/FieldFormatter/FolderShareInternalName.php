<?php

namespace Drupal\foldershare\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\Html;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountProxyInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\foldershare\Branding;
use Drupal\foldershare\Constants;
use Drupal\foldershare\Entity\FolderShare;
use Drupal\foldershare\Entity\FolderShareAccessControlHandler;

/**
 * Formats a FolderShare entity name with a link and icon.
 *
 * <B>Warning:</B> This field formatter is strictly internal to the FolderShare
 * module. The formatter's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * The entity name may be formatted to include:
 * - An anchor around the name that links to the entity's page.
 * - Classes that themes use to add a file/folder MIME type icon.
 *
 * Hidden or disabled items are returned without links or data attributes
 * and are marked as hidden or disabled. All other items are linked and
 * include data attributes needed by the user interface.
 *
 * @ingroup foldershare
 *
 * @FieldFormatter(
 *   id          = "foldershare_internal_name",
 *   label       = @Translation("FolderShare (Internal) - File/folder name, link, & icon"),
 *   weight      = 910,
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class FolderShareInternalName extends FormatterBase implements ContainerFactoryPluginInterface {

  /*--------------------------------------------------------------------
   *
   * Fields - construction.
   *
   *------------------------------------------------------------------*/

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

  /**
   * Constructs an instance of the plugin.
   *
   * @param string $pluginId
   *   The ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter's settings.
   * @param string $label
   *   The formatter's label display setting.
   * @param string $viewMode
   *   The view mode.
   * @param array $thirdPartySettings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    $pluginId,
    $pluginDefinition,
    FieldDefinitionInterface $fieldDefinition,
    array $settings,
    $label,
    $viewMode,
    array $thirdPartySettings,
    AccountProxyInterface $currentUser) {

    parent::__construct(
      $pluginId,
      $pluginDefinition,
      $fieldDefinition,
      $settings,
      $label,
      $viewMode,
      $thirdPartySettings);

    $this->currentUser = $currentUser;
  }

  /*---------------------------------------------------------------------
   *
   * Configuration.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $fieldDef) {
    // The entity containing the field to be formatted must be
    // a FolderShare entity, and the field must be the 'name' field.
    return $fieldDef->getTargetEntityTypeId() === FolderShare::ENTITY_TYPE_ID &&
      $fieldDef->getName() === 'name';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'showIcon' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $this->sanitizeSettings();

    // Get current settings.
    $showIcon = $this->getSetting('showIcon');

    // Add text.
    $summary = [];
    $summary[] = $this->t('File/folder name and link.');

    if ($showIcon === TRUE) {
      $summary[] = $this->t('Include MIME-type icon.');
    }

    $summary[] = $this->t('Data attributes for the UI.');

    return $summary;
  }

  /*---------------------------------------------------------------------
   *
   * Settings form.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns a brief description of the formatter.
   *
   * @return string
   *   Returns a brief translated description of the formatter.
   */
  protected function getDescription() {
    return $this->t('<span class="foldershare-field-formatter-internal-use-only">For FolderShare module internal use only.</span> Show the file/folder name, link to the item, and include data attributes needed by the user interface. Optionally include a MIME-type icon.');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    // Add branding.
    $elements = [];
    $elements = Branding::addFieldFormatterBranding($elements, TRUE);
    $elements['#attached']['library'][] = Constants::LIBRARY_FIELD_FORMATTER;

    // Add description.
    //
    // Use a large negative weight to insure it comes first.
    $elements['description'] = [
      '#type'          => 'html_tag',
      '#tag'           => 'div',
      '#value'         => $this->getDescription(),
      '#weight'        => -1000,
      '#attributes'    => [
        'class'        => [
          'foldershare-settings-description',
        ],
      ],
    ];

    // Add a checkbox to enable/disable including an icon before the name.
    $elements['showIcon'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show a MIME-type icon before the file/folder name'),
      '#default_value' => $this->getSetting('showIcon'),
      '#weight'        => 0,
      '#attributes'    => [
        'class'        => [
          'foldershare-settings-name-show-icon',
        ],
      ],
    ];

    return $elements;
  }

  /**
   * Sanitize settings to insure that they are safe and valid.
   *
   * @internal
   * Drupal's class hierarchy for plugins and their settings does not
   * include a 'validate' function, like that for other classes with forms.
   * Validation must therefore occur on use, rather than on form submission.
   * @endinternal
   */
  protected function sanitizeSettings() {
    $this->setSetting(
      'showIcon',
      boolval($this->getSetting('showIcon')));
  }

  /*---------------------------------------------------------------------
   *
   * View.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langCode) {
    $this->sanitizeSettings();

    if (empty($items) === TRUE) {
      return [];
    }

    // Get settings.
    $showIcon = $this->getSetting('showIcon');
    $linkToEntity = TRUE;

    // The $items array has a list of string items to format, but we
    // need entities.
    $entities = [];
    foreach ($items as $delta => $item) {
      $entities[$delta] = $item->getEntity();
    }

    // At this point, the $entities array has a list of items to format.
    // We need to return an array with identical indexing and corresponding
    // render elements for those items.
    $build = [];
    foreach ($entities as $delta => $entity) {
      // Create a link for the entity and add it to the returned array.
      $build[$delta] = $this->format(
        $entity,
        $langCode,
        $showIcon,
        $linkToEntity);
    }

    return $build;
  }

  /**
   * Returns a formatted field for icon-linked values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A entity to return a value for.
   * @param string $langCode
   *   The target language.
   * @param bool $showIcon
   *   When TRUE, the entity will be marked with classes so that themes can
   *   add a MIME-type icon.
   * @param bool $linkToEntity
   *   When TRUE, the entity name will be enclosed within an anchor to the
   *   entity's view page.
   *
   * @return array
   *   The render element to present the field.
   */
  protected function format(
    EntityInterface $entity,
    $langCode,
    $showIcon,
    $linkToEntity) {

    //
    // Setup
    // -----
    // Get the entity's attributes.
    $name     = $entity->getName();
    $kind     = $entity->getKind();
    $mime     = $entity->getMimeType();
    $url      = $entity->toUrl();
    $hidden   = $entity->isSystemHidden();
    $disabled = $entity->isSystemDisabled();

    $attr = [];
    if ($disabled === FALSE) {
      // Get the user's access to the entity.
      $access = [];
      $summary = FolderShareAccessControlHandler::getAccessSummary($entity);
      foreach ($summary as $op => $tf) {
        if ($tf === TRUE) {
          $access[] = $op;
        }
      }

      // Create an attributes array for use below.
      $user      = $this->currentUser;
      $userId    = (int) $user->id();
      $anonymous = User::getAnonymousUser();
      $anonId    = (int) $anonymous->id();
      $prefix    = 'data-foldershare-';
      $root      = $entity->getRootItem();

      // Since access grants are set on the root only, use it to determine
      // the sharing states below.
      $attr = [
        $prefix . 'id'        => $entity->id(),
        $prefix . 'kind'      => $kind,
        $prefix . 'access'    => implode(',', $access),
        $prefix . 'extension' => $entity->getExtension(),
        $prefix . 'ownerid'   => $entity->getOwnerId(),
        $prefix . 'ownedbyuser' => $entity->isOwnedBy($userId),
        $prefix . 'ownedbyanonymous' => $entity->isOwnedBy($anonId),
        $prefix . 'ownedbyanother' => ($entity->isOwnedBy($userId) === FALSE),
        $prefix . 'sharedbyuser' => $root->isSharedBy($userId),
        $prefix . 'sharedwithusertoview' => $root->isSharedWith($userId, 'view'),
        $prefix . 'sharedwithusertoauthor' => $root->isSharedWith($userId, 'author'),
        $prefix . 'sharedwithanonymoustoview' => $root->isSharedWith($anonId, 'view'),
        $prefix . 'sharedwithanonymoustoauthor' => $root->isSharedWith($anonId, 'author'),
      ];
    }

    //
    // Classes
    // -------
    // Set classes based on what is to be shown and the entity's attributes.
    $classes = [];
    $attached = [];

    if ($hidden === TRUE) {
      $classes[] = 'foldershare-hidden-entity';
    }

    if ($disabled === TRUE) {
      $classes[] = 'foldershare-disabled-entity';
      $linkToEntity = FALSE;
    }

    if ($showIcon === TRUE) {
      // When including an icon, get the classes needed so that themes can
      // mark the item with an icon.
      //
      // The Drupal Core File module defines conventional classes that mark
      // an item as a file of varying type. While Drupal Core does not provide
      // icons, some Core themes do. This module automatically includes those
      // icons and adds icons for folders.
      $classes[] = 'file';

      switch ($kind) {
        case FolderShare::FOLDER_KIND:
          $classes[] = 'file--mime-folder-directory';
          $classes[] = 'file--folder';
          break;

        default:
          $classes[] = 'file--mime-' . strtr(
            $mime,
            [
              '/'   => '-',
              '.'   => '-',
            ]);
          $classes[] = 'file--' . file_icon_class($mime);
          break;
      }

      $attached = [
        'library'   => ['file/drupal.file'],
      ];
    }

    //
    // Format
    // ------
    // Add link, or non-link text, along with above configured classes
    // and attributes.
    if ($linkToEntity === TRUE) {
      $render = [
        '#type'       => 'link',
        '#title'      => $name,
        '#url'        => $url,
        '#cache'      => [
          'contexts'  => ['url.site'],
        ],
        '#attached'   => $attached,
        '#attributes' => array_merge($attr, ['class' => $classes]),
      ];
    }
    else {
      $render = [
        '#type'       => 'html_tag',
        '#tag'        => 'span',
        '#value'      => Html::escape($name),
        '#attached'   => $attached,
        '#attributes' => array_merge($attr, ['class' => $classes]),
      ];
    }

    return $render;
  }

}
