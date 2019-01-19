<?php

namespace Drupal\foldershare\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\FolderShareInterface;

/**
 * Manages a virtual file system hierarchy of folders, subfolders, and files.
 *
 * FolderShare entities represent entries in a virtual file system that
 * includes folders, subfolders, and files in an arbitrarily deep hierarchy.
 *
 * Every folder or file entity has a set of common fields, including:
 * - Entity ID.
 * - UUID.
 * - Name.
 * - Size.
 * - Visible.
 * - Creation and changed dates.
 * - Owner.
 * - Description.
 * - Kind.
 * - MIME type.
 * - Language code.
 * - Parent item, if any.
 * - Root item, if any.
 *
 * The 'kind' field has one of these values:
 * - folder.
 * - file.
 * - image.
 * - media.
 *
 * The parent folder field is an entity reference to the parent folder
 * containing an item. If this field is NULL, the item has no parent and
 * it exists at the "root" or top-level of the folder hierarchy.
 *
 * The root field is an entity reference to the highest ancestor
 * folder of an item. If this field is NULL, the item has no ancestor root
 * and it exists at the "root" itself.
 *
 * Root-level items have access control fields:
 * - Access grants for viewing.
 * - Access grants for authoring.
 * - Access grants for users known, but currently without viewing or authoring.
 *
 * Non-root level items adopt the access controls of their ancestor root
 * folder.
 *
 * When 'kind' is 'file', the entity has an additional field containing the
 * entity ID of a File object:
 * - File entity ID.
 *
 * When 'kind' is 'image', the entity has an additional field containing the
 * entity ID of a File object containing an image, plus attributes of that
 * image filled in by the Image module:
 * - Image entity ID and attributes.
 *
 * When 'kind' is 'media', the entity has an additional field containing the
 * entity ID of a Media object describing an arbitrary media item, plus
 * attributes of that item:
 * - Media entity ID and attributes.
 *
 * File and Image fields refer to local files managed by Drupal core and
 * the File module. The FolderShare entity handles creating, deleting,
 * and altering File entities and the local files they refer to.
 *
 * Media fields refer to media entities that may point to local files or
 * remote files, such as images or videos on a social media site. The
 * FolderShare entity handles creating, deleting, and altering Media
 * entities, but it has no control over any external media these entities
 * refer to.
 *
 * Operations on FolderShare entities include creating, deleting, moving,
 * copying, duplicating, and renaming objects. Fields on these objects may
 * be adjusted individually and, in some cases, in recursive operations
 * (such as to change ownership of an entire folder tree).
 *
 * Root-level items manage access control for their descendants. Access
 * checks on anything in a descendant tree redirect up to the root. Being able
 * to do this quickly is why every item includes a root reference up
 * to the root high above it.
 *
 * @section access Access control
 * This class's methods do not do access control. The caller should check
 * access as needed by their situation.
 *
 * @ingroup foldershare
 *
 * @ContentEntityType(
 *   id             = "foldershare",
 *   label          = @Translation("FolderShare file or folder "),
 *   label_singular = @Translation("FolderShare file or folder"),
 *   label_plural   = @Translation("FolderShare files or folders"),
 *   label_count    = @PluralTranslation(
 *     singular     = "@count FolderShare file or folder",
 *     plural       = "@count FolderShare files or folders"
 *  ),
 *   handlers = {
 *     "access"       = "Drupal\foldershare\Entity\FolderShareAccessControlHandler",
 *     "view_builder" = "Drupal\foldershare\Entity\Builder\FolderShareViewBuilder",
 *     "views_data"   = "Drupal\foldershare\Entity\FolderShareViewsData",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\foldershare\Form\EditFolderShare",
 *       "edit"    = "Drupal\foldershare\Form\EditFolderShare",
 *     },
 *   },
 *   list_cache_contexts     = { "user" },
 *   admin_permission        = "administer foldershare",
 *   permission_granularity  = "entity_type",
 *   translatable            = FALSE,
 *   base_table              = "foldershare",
 *   fieldable               = TRUE,
 *   common_reference_target = TRUE,
 *   field_ui_base_route     = "entity.foldershare.settings",
 *   entity_keys = {
 *     "id"       = "id",
 *     "uuid"     = "uuid",
 *     "uid"      = "uid",
 *     "label"    = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/foldershare/{foldershare}",
 *   }
 * )
 */
final class FolderShare extends ContentEntityBase implements FolderShareInterface {
  use EntityChangedTrait;
  use FolderShareTraits\ManageFileExtensionsTrait;
  use FolderShareTraits\ManageHookTrait;
  use FolderShareTraits\ManageLocksTrait;
  use FolderShareTraits\ManageLogTrait;
  use FolderShareTraits\ManagePathsTrait;
  use FolderShareTraits\GetSetAccessGrantsTrait;
  use FolderShareTraits\GetSetCreatedTimeTrait;
  use FolderShareTraits\GetSetDescriptionTrait;
  use FolderShareTraits\GetSetFileTrait;
  use FolderShareTraits\GetSetKindTrait;
  use FolderShareTraits\GetSetMimeTrait;
  use FolderShareTraits\GetSetNameTrait;
  use FolderShareTraits\GetSetOwnerTrait;
  use FolderShareTraits\GetSetParentTrait;
  use FolderShareTraits\GetSetRootTrait;
  use FolderShareTraits\GetSetSizeTrait;
  use FolderShareTraits\GetSetSystemDisabledTrait;
  use FolderShareTraits\GetSetSystemHiddenTrait;
  use FolderShareTraits\FindTrait;
  use FolderShareTraits\OperationAddFileTrait;
  use FolderShareTraits\OperationArchiveTrait;
  use FolderShareTraits\OperationChangeOwnerTrait;
  use FolderShareTraits\OperationNewFolderTrait;
  use FolderShareTraits\OperationCopyTrait;
  use FolderShareTraits\OperationDeleteTrait;
  use FolderShareTraits\OperationMoveTrait;
  use FolderShareTraits\OperationRenameTrait;
  use FolderShareTraits\OperationShareTrait;
  use FolderShareTraits\OperationUnarchiveTrait;

  /*---------------------------------------------------------------------
   *
   * Constants - Entity type id.
   *
   *---------------------------------------------------------------------*/

  /**
   * The entity type id for the FolderShare entity.
   *
   * This is 'foldershare' and it must match the entity type declaration
   * in this class's comment block.
   *
   * @var string
   */
  const ENTITY_TYPE_ID = 'foldershare';

  /*---------------------------------------------------------------------
   *
   * Constants - Database tables.
   *
   *---------------------------------------------------------------------*/

  /**
   * The base table for 'foldershare' entities.
   *
   * This is 'foldershare' and it must match the base table declaration in
   * this class's comment block.
   *
   * @var string
   */
  const BASE_TABLE = 'foldershare';

  /*---------------------------------------------------------------------
   *
   * Constants - Archives.
   *
   *---------------------------------------------------------------------*/

  /**
   * The comment added to ZIP archives created by this module.
   *
   * The comment is internal to the ZIP file and rarely seen by users.
   *
   * @var string
   */
  const NEW_ARCHIVE_COMMENT = 'Created by FolderShare.';

  /**
   * The default name for a new ZIP archive.
   *
   * @var string
   */
  const NEW_ZIP_ARCHIVE = 'Archive.zip';

  /**
   * Indicate how archives with multiple top-level items are unarchived.
   *
   * A ZIP archive may contain an arbitrary hierarchy of files and folders.
   * If there is a single top-level item, it is reasonable to extract and
   * add it to the current folder. But if there are multiple top-level items,
   * there are two common behaviors:
   * - Extract all top-level items into the current folder.
   * - Create a subfolder (named after the archive) in the current folder
   *   and extract everything there.
   *
   * When this flag is FALSE, the first case is done. When TRUE, the second
   * case. The second case is similar to that in macOS.
   *
   * @var bool
   */
  const UNARCHIVE_MULTIPLE_TO_SUBFOLDER = TRUE;

  /*---------------------------------------------------------------------
   *
   * Constants - Lock constants.
   *
   *---------------------------------------------------------------------*/

  /**
   * The base name of the process lock for editing folder content.
   *
   * Drupal locks may have arbitrary names. We use 'folder_edit_ID'.
   *
   * The folder content edit lock uses the folder entity ID.
   * This lock must be acquired before adding or removing any
   * folder content, including child files and folders.
   *
   * When deleting, copying, or moving a folder tree, this lock
   * is acquired for each folder in the tree as that folder is
   * being edited.  Parent folder locks are maintained while child
   * folder's are being deleted, copied, or moved.
   *
   * @var string
   */
  const EDIT_CONTENT_LOCK_NAME = 'foldershare_edit_';

  /**
   * The duration of locks (in seconds).
   *
   * All Drupal locks time out so that a crash of a Drupal process
   * that has a lock cannot leave content locked forever.  The lock
   * always times out.
   *
   * @var float
   */
  const EDIT_CONTENT_LOCK_DURATION = 30.0;

  /*---------------------------------------------------------------------
   *
   * Entity definition.
   *
   *---------------------------------------------------------------------*/

  /**
   * Defines the fields used by instances of this class.
   *
   * The following fields are defined, along with their intended
   * public or private access:
   *
   * | Field            | Allow for view | Allow for edit |
   * | ---------------- | -------------- | -------------- |
   * | id               | any user       | no             |
   * | uuid             | no             | no             |
   * | uid              | any user       | no             |
   * | langcode         | no             | no             |
   * | created          | any user       | no             |
   * | changed          | any user       | no             |
   * | size             | any user       | no             |
   * | kind             | any user       | no             |
   * | mime             | any user       | no             |
   * | name             | any user       | any user       |
   * | description      | any user       | any user       |
   * | parentid         | any user       | no             |
   * | rootid           | any user       | no             |
   * | file             | any user       | no             |
   * | image            | any user       | no             |
   * | media            | any user       | no             |
   * | grantauthoruids  | any user       | no             |
   * | grantviewuids    | any user       | no             |
   * | systemdisabled   | no             | no             |
   * | systemhidden     | no             | no             |
   *
   * Some fields are supported by parent class methods:
   *
   * | Field            | Get method                           |
   * | ---------------- | ------------------------------------ |
   * | id               | ContentEntityBase::id()              |
   * | uuid             | ContentEntityBase::uuid()            |
   * | name (label)     | ContentEntityBase::getName()         |
   * | changed          | EntityChangedTrait::getChangedTime() |
   * | langcode         | ContentEntityBase::language()        |
   *
   * Some fields are supported by methods in this class:
   *
   * | Field            | Get method                           |
   * | ---------------- | ------------------------------------ |
   * | uid              | getOwner()                           |
   * | description      | getDescription()                     |
   * | created          | getCreatedTime()                     |
   * | size             | getSize()                            |
   * | kind             | getKind()                            |
   * | mime             | getMimeType()                        |
   * | file             | getFileId()                          |
   * | image            | getImageId()                         |
   * | media            | getMediaId()                         |
   *
   * Some fields have no get/set methods or no set methods because handling
   * them requires special code and context:
   *
   * | Field            | Get method                           |
   * | ---------------- | ------------------------------------ |
   * | parentid         |                                      |
   * | rootid           |                                      |
   * | file             |                                      |
   * | image            |                                      |
   * | media            |                                      |
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type for which we are returning base field
   *   definitions.
   *
   * @return array
   *   An array of field definitions where keys are field names and
   *   values are BaseFieldDefinition objects.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    //
    // Base class fields
    // -----------------
    // The parent ContentEntityBase class supports several standard
    // entity fields:
    //
    // - id: the entity ID
    // - uuid: the entity unique ID
    // - langcode: the content language
    // - revision: the revision ID
    // - bundle: the entity bundle
    //
    // The parent class ONLY defines these fields if they exist in
    // THIS class's comment block declaring class fields.  Of the
    // above fields, we only define these for this class:
    //
    // - id
    // - uuid
    // - langcode
    //
    // By invoking the parent class, we don't have to define these
    // ourselves below.
    //
    // Node adds a few fields we don't need, including promote, sticky,
    // revision_timestamp, revision_uid, and revision_tralsnation_affected.
    $fields = parent::baseFieldDefinitions($entityType);

    // But we do need to adjust display options a bit (see below).
    $weight = 0;

    //
    // Entity id.
    // - Integer.
    // - Primary database index.
    // - Read-only (set at creation time)
    // - 'view' shows ID (though usually converted to entity name)
    // - 'form' unsupported since read only.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users.  The field is read only.
    //
    // Note:  This field was already defined by the parent class.
    // We just need to adjust it a little.
    // - Already has label ("ID").
    // - Already marked read only.
    // - Already marked unsigned.
    $fields[$entityType->getKey('id')]
      ->setDescription(t('The ID of the item.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'number_integer',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // Unique id (UUID).
    // - Integer.
    // - Read-only (set at creation time)
    // - 'view' unsupported.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users. The field is read only.
    //
    // Note:  This field was already defined by the parent class.
    // We just need to adjust it a little to have a better description
    // and a way to view it.
    // - Already has label ("UUID").
    // - Already marked read only.
    $fields[$entityType->getKey('uuid')]
      ->setDescription(t('The UUID of the item.'))
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // Language code.
    // - 'view' hidden.
    // - 'form' selects language.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users.
    //
    // Note: This field is required by the ContentEntityBase base class,
    // which may use it to guide trnaslations.  We do not need to
    // implement anything further.
    //
    // Note:  This field was already defined by the parent class.
    // We just need to adjust it a little.
    // - Already has label ("Language").
    // - Already has view (hidden).
    // - Already has form (language select).
    $f = $fields[$entityType->getKey('langcode')];
    $f->setDescription(t('The original language for the item.'));
    $fa = $f->getDisplayOptions('form');
    if ($fa === NULL) {
      $fa = ['type' => 'language_select'];
    }

    $fa['weight'] = $weight;
    $f->setDisplayOptions('form', $fa);

    //
    // Common fields
    // -------------
    // Like Drupal nodes, folders have an owner, creation date,
    // changed date, and name (node calls it a title).  We use
    // definitions that are very similar to those for node fields.
    //
    // Name.
    // - String.
    // - Required, no default.
    // - 'view' shows the name.
    // - 'form' unsupported.
    //
    // Note that the 'name' field is specifically excluded from the
    // folder's default edit form. Names are handled separately via a
    // 'Rename' command that does not use the default edit form.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the item.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value'   => 'Item',
        'max_length'      => self::MAX_NAME_LENGTH,
        'text_processing' => FALSE,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'string',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // User (owner) id.
    // - Entity reference to user object.
    // - 'view' shows the user's name.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // except for admins.
    //
    // Note that even for admins, the 'uid' field is specifically
    // excluded from the item's default edit form. User IDs are
    // handled separately via a 'Chown' (Change owner) command that
    // does not use the default edit form (and must recurse through
    // a folder tree).
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t("The user ID of the item's owner."))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback(
        'Drupal\foldershare\Entity\FolderShare::getCurrentUserId')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'author',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // Creation date.
    // - Time stamp.
    // - 'view' shows date/time.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // except for admins.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The date and time when the item was created.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'timestamp',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // Changed (modified) date.
    // - Time stamp.
    // - 'view' shows date/time.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler.
    //
    // Note: This field is required by the EntityChangedTrait, which
    // implements the EntityChangedInterface.  Because we use the trait,
    // we do not need to implement anything further.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Modified'))
      ->setDescription(t('The date and time when the item was most recently modified.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'timestamp',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // Custom fields
    // -------------
    // FolderShare entities have a variety of custom fields.
    //
    // Description.
    // - Long text.
    // - Optional, no default.
    // - 'view' shows the text.
    // - 'form' edits the text as a text area.
    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('The description of the item. The text may be empty, brief, or long.'))
      ->setRequired(FALSE)
      ->setSettings(['default_value' => ''])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'text_default',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions(
        'form',
        [
          'type'        => 'text_textfield',
          'description' => 'Enter a description of the item. The description may be empty, brief, or long.',
          'rows'        => 20,
          'weight'      => $weight,
        ]);
    $weight += 10;

    //
    // Kind.
    // - String.
    // - Required, no default.
    // - 'view' shows the text.
    // - 'form' unsupported.
    $fields['kind'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Kind'))
      ->setDescription(t('The kind of item, such as a file or folder.'))
      ->setRequired(TRUE)
      ->setSettings(['default_value' => ''])
      ->setSetting('is_ascii', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'text_default',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // MIME.
    // - String.
    // - Required, no default.
    // - 'view' shows the text.
    // - 'form' unsupported.
    $fields['mime'] = BaseFieldDefinition::create('string')
      ->setLabel(t('MIME type'))
      ->setDescription(t("The MIME type characterizing a file's contents, such as a JPEG image or a PDF document."))
      ->setRequired(TRUE)
      ->setSettings(['default_value' => ''])
      ->setSetting('is_ascii', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'text_default',
          'weight' => $weight,
        ])
      ->setDisplayConfigurable('form', FALSE);
    $weight += 10;

    //
    // Parent ID.
    // - Entity reference to parent Folder, if any.
    // - Optional, no default (empty means folder is in root list)
    // - 'view' shows the parent folder's name.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users.
    $fields['parentid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent folder'))
      ->setDescription(t("The item's direct ancestor folder. When an item is at the top-level itself, this field is empty (NULL)."))
      ->setRequired(FALSE)
      ->setSetting('target_type', self::ENTITY_TYPE_ID)
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'entity_reference_label',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // Root ID.
    // - Entity reference to a root item, if any.
    // - Optional, no default (empty means item is at the root).
    // - 'view' shows the root item's name.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users.
    $fields['rootid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Top-level folder'))
      ->setDescription(t("The item's highest folder ancestor. When an item is at the top-level itself, this field points back to the same entity."))
      ->setRequired(FALSE)
      ->setSetting('target_type', self::ENTITY_TYPE_ID)
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'hidden',
          'type'   => 'entity_reference_label',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // Size.
    // - Big integer.
    // - Optional, no default (filled in as needed by auto size calc)
    // - 'view' shows the size.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users.
    $fields['size'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Size'))
      ->setDescription(t('The storage space (in bytes) used by the file or folder, and all of its child files and folders.'))
      ->setRequired(FALSE)
      ->setSetting('size', 'big')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'foldershare_storage_size',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // System hidden field flag.
    // - Boolean.
    // - Default is FALSE.
    // - 'view' unsupported.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler for all users.
    $fields['systemhidden'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('System hidden'))
      ->setDescription(t('A TRUE/FALSE value indicating if the entity has been hidden by the system during special operations.'))
      ->setRequired(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);
    $weight += 10;

    //
    // System disabled field flag.
    // - Boolean.
    // - Default is FALSE.
    // - 'view' unsupported.
    // - 'form' unsupported.
    //
    // Field editing blocked by FolderShareAccessControlHandler for all users.
    $fields['systemdisabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('System disabled'))
      ->setDescription(t('A TRUE/FALSE value indicating if the entity has been disabled by the system during special operations.'))
      ->setRequired(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);
    $weight += 10;

    //
    // File.
    // - A single File entity for a file item in a folder (i.e. kind == file).
    // - 'view' shows item.
    // - 'form' unsupported (custom code later).
    //
    // Field editing is blocked by FolderShareAccessControlHandler
    // for all users.
    //
    // Because folders are intended to contain any type of file,
    // we would rather not restrict the set of file extensions.
    // Unfortunately, this is currently not possible:
    // @see https://www.drupal.org/node/997900
    //
    // Instead, elsewhere we provide our own handling of uploaded
    // files in order to take control over file extension testing.
    //
    // File names are constrained to not be empty, not include :, /, or \,
    // and not collide with an existing file. We check names explicitly
    // durring add/rename operations instead of using a field constraint,
    // which would be less efficient since it would require checking and
    // rechecking the name every time the entity was modified for any
    // reason (such as changing its description).
    //
    // A 'file' field type extends an entity reference field type and
    // adds these stored values with every instance:
    // - display     = true/false for whether the file should be shown.
    // - description = description text for the file.
    //
    // The 'file' field also adds these settings applied to this usage
    // of the field type:
    // - file_extensions   = the allowed extensions list.
    // - file_directory    = a subdirectory in which to store files.
    // - max_filesize      = the maximum uploaded file size allowed.
    // - description_field = whether to include a description per file.
    //
    // FolderShare does not use all of these features of 'file' fields:
    // - description_field is set to FALSE and the description text is
    //   never set or used. The containing FolderShare entity has its
    //   own description field.
    // - file_directory is ignored and FolderShare determines the directory
    //   path on its own (see FolderShareStream).
    // - max_filesize is ignored in favor of module settings that control
    //   upload sizes.
    $extensions = '';
    if (Settings::getFileRestrictExtensions() === TRUE) {
      $extensions = Settings::getAllowedNameExtensions();
    }

    $fields['file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('File'))
      ->setDescription(t('A non-image file within a folder.'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'default')
      ->setSetting('file_extensions', $extensions)
      ->setSetting('description_field', FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'file_default',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // Image.
    // - A single Image entity for a file item in a folder (i.e. kind == image).
    // - 'view' shows item.
    // - 'form' unsupported (custom code later).
    //
    // See comments above for the file field.
    //
    // An 'image' field type extends the 'file' field type and adds these
    // stored values for every instance:
    // - alt    = alternate text for the image's 'alt' attribute.
    // - title  = title text for the image's 'title' attribute.
    // - width  = the image width.
    // - height = the image height.
    //
    // The 'image' field also adds these settings applied to this usage
    // of the field type:
    // - alt_field            = whether to include an alt value per image.
    // - alt_field_required   = whether alt text is required.
    // - title_field          = whether to include a title value per image.
    // - title_field_required = whether title text is required.
    // - min_resolution       = the minimum accepted image resolution.
    // - max_resolution       = the maximum accepted image resolution.
    // - default_image        = a default image if none is provided.
    //
    // FolderShare does not use all of these features of 'image' fields:
    // - alt_field and title_field are not used and alt_field_required
    //   and title_field_required are set to FALSE.
    // - default_image is not used because the image field is only set if
    //   we have an image file.
    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('An image file within a folder.'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'default')
      ->setSetting('file_extensions', $extensions)
      ->setSetting('description_field', FALSE)
      ->setSetting('alt_field', FALSE)
      ->setSetting('alt_field_required', FALSE)
      ->setSetting('title_field', FALSE)
      ->setSetting('title_field_required', FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'image',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // Media.
    // - A single Media entity for a media item in a folder (i.e. kind ==media).
    // - 'view' shows item.
    // - 'form' unsupported (custom code later).
    //
    // See comments above for the file field.
    //
    // Media entities do not have a corresponding media field type. Instead
    // we refer to a media entity using an entity_reference.
    $fields['media'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media'))
      ->setDescription(t('A media item within a folder.'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'media_thumbnail',
          'weight' => $weight,
        ]);
    $weight += 10;

    //
    // Access control for author access.
    // Access control for view access.
    // - List of UIDs.
    // - 'view' shows author names (except disabled field).
    // - 'form' unsupported (custom code required).
    //
    // Field editing blocked by FolderShareAccessControlHandler
    // for all users.
    //
    $fields['grantauthoruids'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author grants'))
      ->setDescription(t("A list of IDs for users that have been granted author access to the item and all of its descendants. Only top-level items have values. The item's owner is always on this list."))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'author',
          'weight' => $weight,
        ]);
    $weight += 10;

    $fields['grantviewuids'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Viewer grants'))
      ->setDescription(t("A list of IDs for users that have been granted view access to the item and all of its descendants. Only top-level items have values. The item's owner is always on this list."))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions(
        'view',
        [
          'label'  => 'inline',
          'type'   => 'author',
          'weight' => $weight,
        ]);
    $weight += 10;

    return $fields;
  }

  /*---------------------------------------------------------------------
   *
   * Entity management.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   *
   * This method is called immediately after an entity has been saved.
   * If the Drupal core Search module is installed, the entity is marked
   * for search re-indexing.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Let the parent class do its work.
    parent::postSave($storage, $update);

    // If search module is enabled, mark entity as in need of re-indexing.
    if ($update === TRUE &&
        \Drupal::moduleHandler()->moduleExists('search') === TRUE) {
      search_mark_for_reindex(Constants::SEARCH_INDEX, (int) $this->id());
    }
  }

  /**
   * {@inheritdoc}
   *
   * This method is called immediately before an entity is deleted.
   * If the core 'search' module is installed, the search index entry
   * for the entity is cleared.
   */
  public static function preDelete(
    EntityStorageInterface $storage,
    array $items) {
    // Let the parent class do its work.
    parent::preDelete($storage, $items);

    // If search module is enabled, clear entity from the search index.
    if (\Drupal::moduleHandler()->moduleExists('search') === TRUE) {
      // Loop through each of the entities about to be deleted.
      foreach ($items as $item) {
        // Clear the entities's entry in the search index.
        search_index_clear(
          Constants::SEARCH_INDEX,
          (int) $item->id(),
          $item->langcode->value);
      }
    }
  }

  /*---------------------------------------------------------------------
   *
   * General utilities.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the current user ID.
   *
   * This function provides the deault value callback for the 'uid'
   * base field definition.
   *
   * @return array
   *   An array of default values. In this case, the array only
   *   contains the current user ID.
   *
   * @see ::baseFieldDefinitions()
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /*---------------------------------------------------------------------
   *
   * Access control.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function access(
    $operation,
    AccountInterface $account = NULL,
    $returnAsObject = FALSE) {

    // Drupal's Entity class implements this method and splits 'create'
    // operations off from all other operations:
    // - 'create' is sent to the access controller's createAccess() method.
    // - Everything else is sent to the access controller's access() method.
    //
    // This is fine for everything except 'create'. By sending 'create'
    // to the createAccess() method, the context of the current entity is
    // lost. That method does not include an entity argument.
    //
    // Without the context of the current entity, the access controller
    // cannot check access grants based on the folder tree. All it can do
    // is check permissions.
    //
    // Without checking access grants, createAccess() is forced to return a
    // generic and incomplete answer. Drupal automatically caches that answer
    // and uses instead of making further access checks. This will cause a
    // mess when that answer needs to vary based on entity context and access
    // grants, and yet it can't. Inappropriate cached answers will be used
    // over and over again in every context.
    //
    // The fix is to route 'create' operations to the access controller's
    // access() method, along with all other operations. This enables
    // the access controller to check access grants on the entity's folder
    // tree and include a result cache dependency on the entity at hand.
    return $this->entityTypeManager()
      ->getAccessControlHandler($this->entityTypeId)
      ->access($this, $operation, $account, $returnAsObject);
  }

}
