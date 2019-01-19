<?php

namespace Drupal\foldershare\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Html;

use Drupal\foldershare\Branding;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Formats a FolderShare entity reference with a name, link, and icon.
 *
 * The referenced entity's name may be formatted to include:
 * - An anchor around the name that links to the entity's page.
 * - Classes that themes use to add a file/folder MIME type icon.
 *
 * @ingroup foldershare
 *
 * @FieldFormatter(
 *   id          = "foldershare_reference",
 *   label       = @Translation("FolderShare - File/folder name, link, & icon"),
 *   weight      = 900,
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class FolderShareReference extends EntityReferenceFormatterBase {

  /*---------------------------------------------------------------------
   *
   * Configuration.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $fieldDef) {
    // Get the plugin's settings and confirm that the plugin is configured
    // to work only with entity references to FolderShare entities.
    $settings = $fieldDef->getSettings();
    if (isset($settings['target_type']) === TRUE &&
        $settings['target_type'] === FolderShare::ENTITY_TYPE_ID) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'showIcon'     => TRUE,
      'linkToEntity' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $this->sanitizeSettings();

    // Get current settings.
    $showIcon = $this->getSetting('showIcon');
    $linkToEntity = $this->getSetting('linkToEntity');

    // Add text.
    $summary = [];
    if ($linkToEntity === TRUE) {
      $summary[] = $this->t('File/folder name and link.');
    }
    else {
      $summary[] = $this->t('File/folder name.');
    }

    if ($showIcon === TRUE) {
      $summary[] = $this->t('Include MIME-type icon.');
    }

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
    return $this->t('Show the file/folder name. Optionally include a MIME-type icon and a link to the item.');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    // Add branding.
    $elements = [];
    $elements = Branding::addFieldFormatterBranding($elements);
    $elements['#attached']['library'][] =
      'foldershare/foldershare.fieldformatter';

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

    // Add a checkbox to enable/disable linking the name to the entity.
    $elements['linkToEntity'] = [
      '#title'         => $this->t("Link the file/folder's name to its page"),
      '#type'          => 'checkbox',
      '#default_value' => $this->getSetting('linkToEntity'),
      '#weight'        => -1000,
      '#attributes'    => [
        'class'        => [
          'foldershare-settings-name-link-to-entity',
        ],
      ],
    ];

    // Add a checkbox to enable/disable including an icon before the name.
    $elements['showIcon'] = [
      '#title'         => $this->t('Show a MIME-type icon before the file/folder name'),
      '#type'          => 'checkbox',
      '#default_value' => $this->getSetting('showIcon'),
      '#weight'        => -1000,
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

    $this->setSetting(
      'linkToEntity',
      boolval($this->getSetting('linkToEntity')));
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
    $linkToEntity = $this->getSetting('linkToEntity');

    // While the $items array has a list of items to format, the parent
    // class offers a method to adjust the list to reference the proper
    // language versions and to check access. The returned list is of
    // entities, not FieldItems. Entities are in the same order as items.
    $entities = $this->getEntitiesToView($items, $langCode);
    if (empty($entities) === TRUE) {
      return [];
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

    //
    // Classes
    // -------
    // Set classes based on what is to be shown.
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
              '/' => '-',
              '.' => '-',
            ]);
          $classes[] = 'file--' . file_icon_class($mime);
          break;
      }

      $attached = [
        'library' => ['file/drupal.file'],
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
        '#attributes' => [
          'class'     => $classes,
        ],
      ];
    }
    else {
      $render = [
        '#type'       => 'html_tag',
        '#tag'        => 'span',
        '#value'      => Html::escape($name),
        '#attached'   => $attached,
        '#attributes' => [
          'class'     => $classes,
        ],
      ];
    }

    return $render;
  }

}
