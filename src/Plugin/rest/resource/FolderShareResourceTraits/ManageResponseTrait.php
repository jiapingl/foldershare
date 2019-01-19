<?php

namespace Drupal\foldershare\Plugin\rest\resource\FolderShareResourceTraits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;

use Drupal\user\Entity\User;

use Symfony\Component\HttpFoundation\Response;

use Drupal\foldershare\FolderShareInterface;
use Drupal\foldershare\Plugin\rest\resource\UncacheableResponse;

/**
 * Manage HTTP responses.
 *
 * This trait includes methods that help build a response to a request,
 * including responses with embedded entities and lists of entities.
 *
 * @section internal Internal trait
 * This trait is internal to the FolderShare module and used to define
 * features of the FolderShareResource entity class. It is a mechanism to group
 * functionality to improve code management.
 *
 * @ingroup foldershare
 */
trait ManageResponseTrait {

  /*--------------------------------------------------------------------
   *
   * Utilities.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns a generic access denied message.
   *
   * When performing access control checks, an access denied response
   * may or may not include a response message. If it does, that message
   * is used. But if not, this method fills in a generic message.
   *
   * @param string $operation
   *   The name of the operation that is not allowed (e.g. 'view').
   *
   * @return string
   *   Returns a generic default message for an AccessDeniedHttpException.
   */
  private function getDefaultAccessDeniedMessage($operation) {
    return "You are not authorized to {$operation} this item.";
  }

  /*--------------------------------------------------------------------
   *
   * Response header utilities.
   *
   *--------------------------------------------------------------------*/

  /**
   * Adds link headers to a response.
   *
   * If the entity has any entity reference fields, this method loops
   * through them and adds them to a 'Link' header field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The FolderShare entity.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response.
   *
   * @see https://tools.ietf.org/html/rfc5988#section-5
   */
  private function addLinkHeaders(
    EntityInterface $entity,
    Response $response) {

    // Loop through all URI relationships (entity references) in the entity.
    foreach ($entity->uriRelationships() as $relationName) {
      // If the relationship does not have a definition, skip it.
      // We don't have enough information to describe this relationship.
      $hasDef = $this->linkRelationTypeManager->hasDefinition($relationName);
      if ($hasDef === FALSE) {
        continue;
      }

      // Get the relationship information.
      $type = $this->linkRelationTypeManager->createInstance($relationName);

      if ($type->isRegistered() === TRUE) {
        $relationship = $type->getRegisteredName();
      }
      else {
        $relationship = $type->getExtensionUri();
      }

      // Generate a URL for the relationship.
      $urlObj = $entity->toUrl($relationName)
        ->setAbsolute(TRUE)
        ->toString(TRUE);

      $url = $urlObj->getGeneratedUrl();

      // Add a link header for this item.
      $response->headers->set(
        'Link',
        '<' . $url . '>; rel="' . $relationship . '"',
        FALSE);
    }
  }

  /*--------------------------------------------------------------------
   *
   * Key-value pair pseudo-serialization.
   *
   *--------------------------------------------------------------------*/

  /**
   * Creates a key-value pair version of an entity.
   *
   * This method serializes an entity into an associative array of field
   * name keys and their field values. For complex fields, only the primary
   * value (used in renders) is included.
   *
   * This key-value array provides a simpler view of the entity, though it
   * lacks detail and is not compatible with further PATCH and POST requests.
   *
   * The entity should already have been cleaned to remove empty fields and
   * fields that cannot be viewed by the user.
   *
   * @param \Drupal\foldeshare\FolderShareInterface $entity
   *   The entity whose fields contribute to a key-value pair output.
   *
   * @return array
   *   Returns an associative array with keys with field names and values
   *   that contain the simplified representation of the field's value.
   */
  private function formatKeyValueEntity(FolderShareInterface &$entity) {
    // Map the entity's fields to key-value pairs. This provides the
    // primary data for the response.
    $content = [];
    foreach ($entity as $fieldName => &$field) {
      $value = $this->createKeyValueFieldItemList($field);
      if (empty($value) === FALSE) {
        $content[$fieldName] = $value;
      }
    }

    // Add pseudo-fields that are useful for the client side to interpret
    // and present the entity. All of these are safe to return to the client:
    //
    // - The sharing status is merely a simplified state that could be
    //   determined by the client by querying the entity's root item
    //   and looking at the grant field UIDs.
    //
    // - The entity path is a locally built string that could be built by
    //   the client by querying the entity's ancestors and concatenating
    //   their names.
    //
    // - The user ID and account names are a minor translation of the data
    //   the client already provided for authentication. For content owned
    //   by the user, the UID is already in the entity's 'uid' field. The
    //   account name matches the authentication user name. The display name
    //   could be retrieved by the client by sending a REST request to the
    //   User module (if it is enabled for REST).
    //
    // - The host name is from the HTTP request provided by the client.
    //
    // - The formatted dates merely use the numeric timestamp already
    //   returned in the 'created' and 'changed' fields, and formats it
    //   for easier use.
    $content['host'] = $this->currentRequest->getHttpHost();

    $uid = $entity->getOwnerId();
    $content['user-id'] = $uid;
    $owner = User::load($uid);
    if ($owner !== NULL) {
      $content['user-account-name'] = $owner->getAccountName();
      $content['user-display-name'] = $owner->getDisplayName();
      $content['sharing-status'] = $entity->getSharingStatus();
    }
    else {
      $content['user-account-name'] = "unknown";
      $content['user-display-name'] = "unknown";
      $content['sharing-status'] = "unknown";
    }

    $content['path'] = $entity->getPath();

    // Provide formatted dates. We don't need to support a format that
    // includes every part of the date, such as seconds and microseconds.
    // The client can create its own formatting if it wants to by using
    // the raw timestamp. These formatted dates are a courtesy only.
    //
    // These formatted dates use the user's selected time zone and a
    // basic date format like that found in Linux "ls -l" output, which
    // does not include seconds and microseconds.
    //
    // Note that a user may or may not have their own timezone setting.
    // Anonymous users don't. And sites that have not configured the
    // setting (it defaults to OFF) will not have user-specific timezones.
    // In that case, default to the system's timezone, if any, or a
    // default timezone.
    $date = new \DateTime();
    $usersTimeZone = drupal_get_user_timezone();
    $date->setTimezone(new \DateTimeZone($usersTimeZone));
    $content['user-time-zone'] = $usersTimeZone;

    $date->setTimestamp($entity->getCreatedTime());
    $content['created-date'] = $date->format("M d Y H:i");

    $date->setTimestamp($entity->getChangedTime());
    $content['changed-date'] = $date->format("M d Y H:i");

    return $content;
  }

  /**
   * Returns a simplified value for a field item list.
   *
   * If the field item list has one entry, the value for that entry is
   * returned.  Otherwise an array of entry values is returned.
   *
   * If the field item list is empty, a NULL is returned.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldList
   *   The field item list whose values contribute to a key-value pair output.
   *
   * @return mixed
   *   Returns a value for the field item list.
   */
  private function createKeyValueFieldItemList(
    FieldItemListInterface &$fieldList) {

    // If the list is empty, return nothing.
    if ($fieldList->isEmpty() === TRUE) {
      return NULL;
    }

    // If the list has only one entry, return the representation of
    // that one entry. This is the most common case.
    if ($fieldList->count() === 1) {
      return $this->createKeyValueFieldItem($fieldList->first());
    }

    // Otherwise when the list has multiple entries, return an array
    // of their values.
    $values = [];
    foreach ($fieldList as $field) {
      $v = $this->createKeyValueFieldItem($field);
      if (empty($v) === FALSE) {
        $values[] = $v;
      }
    }

    return $values;
  }

  /**
   * Returns a simplified value for a field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field
   *   The field item whose value contributes to a key-value pair output.
   *
   * @return string
   *   Returns a value for the field item.
   */
  private function createKeyValueFieldItem(FieldItemInterface $field) {
    if ($field->isEmpty() === TRUE) {
      return NULL;
    }

    return $field->getString();
  }

  /*--------------------------------------------------------------------
   *
   * Responses with an entity or entity list.
   *
   *--------------------------------------------------------------------*/

  /**
   * Modifies an entity to clean out empty or unviewable fields.
   *
   * This method loops through an entity's fields and:
   * - Removes fields with no values.
   * - Removes fields for which the user does not have 'view' access.
   *
   * In both cases, removed fields are set to NULL. It is *assumed* that
   * the caller will not save the entity, which could corrupt the
   * database with an invalid entity.
   *
   * @param \Drupal\foldeshare\FolderShareInterface $entity
   *   The entity whose fields should be cleared if they are not viewable.
   *
   * @return \Drupal\foldershare\FolderShareInterface
   *   Returns the same entity.
   */
  private function cleanEntity(FolderShareInterface &$entity) {
    foreach ($entity as $fieldName => &$field) {
      // Check access to the field.
      if ($field->access('view', NULL, FALSE) === FALSE) {
        $entity->set($fieldName, NULL, FALSE);
        continue;
      }

      // Check for empty fields.
      if ($field->isEmpty() === TRUE) {
        $entity->set($fieldName, NULL, FALSE);
        continue;
      }

      // Fields normally implement isEmpty() in a sane way to report
      // when a field is empty. But the comment module implements isEmpty()
      // to always return FALSE because it always has state for the entity,
      // including whether or not commenting is open or closed.
      //
      // For our purposes, though, a clean entity should omit a comment
      // field if the entity has no comments yet. This means we cannot
      // rely upon isEmpty().
      //
      // This is made trickier because the right way to check this is not
      // well-defined, and because the comment module may not be installed.
      $first = $field->get(0);
      if (is_a($first, 'Drupal\comment\Plugin\Field\FieldType\CommentItem') === TRUE) {
        // We have a comment field. It contains multiple values for
        // commenting open/closed, the most recent comment, and finally
        // the number of comments so far. We only care about the latter.
        if (intval($first->get('comment_count')->getValue()) === 0) {
          $entity->set($fieldName, NULL, FALSE);
          continue;
        }
      }
    }

    return $entity;
  }

  /**
   * Builds and returns a response containing a single entity.
   *
   * The entity is "cleaned" to remove non-viewable internal fields,
   * and then formatted properly using the selected return format.
   * A response containing the formatted entity is returned.
   *
   * @param \Drupal\foldershare\FolderShareInterface $entity
   *   A single entity to return, after cleaning and formatting.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   */
  private function formatEntityResponse(FolderShareInterface $entity) {
    //
    // Validate
    // --------
    // If there is no entity, return nothing.
    if ($entity === NULL) {
      return new UncacheableResponse(NULL, Response::HTTP_NO_CONTENT);
    }

    //
    // URL
    // ---
    // Create the URL for the updated entity.
    if (empty($entity->id()) === FALSE) {
      $url = $entity->toUrl(
        'canonical',
        ['absolute' => TRUE])->toString(TRUE);

      $headers = [
        'Location' => $url->getGeneratedUrl(),
      ];
    }
    else {
      $headers = [];
    }

    //
    // Entity access control again
    // ---------------------------
    // If the user has view permission for the entity (and they likely do
    // if they had update permission checked earlier), then we can return
    // the updated entity. Otherwise not.
    if ($entity->access('view', NULL, FALSE) === FALSE) {
      // This is odd that they can edit, but not view.
      return new UncacheableResponse(NULL, Response::HTTP_NO_CONTENT, $headers);
    }

    //
    // Clean entity
    // ------------
    // A loaded entity has all fields, but only some fields are user-visible.
    // "Clean" the entity of non-viewable fields by deleting them from the
    // entity.
    $partialEntity = $this->cleanEntity($entity);

    //
    // Return entity
    // -------------
    // Based upon the return format, return the entity.
    switch ($this->getAndValidateReturnFormat()) {
      default:
      case 'full':
        // Return as full entity.
        $response = new UncacheableResponse(
          $partialEntity,
          Response::HTTP_OK,
          $headers);
        break;

      case 'keyvalue':
        // Return as key-value pairs.
        $response = new UncacheableResponse(
          $this->formatKeyValueEntity($partialEntity),
          Response::HTTP_OK,
          $headers);
        break;
    }

    if ($partialEntity->isNew() === FALSE) {
      $this->addLinkHeaders($partialEntity, $response);
    }

    return $response;
  }

  /**
   * Builds and returns a response containing a list of entities.
   *
   * The entities are "cleaned" to remove non-viewable internal fields,
   * and then formatted properly using the selected return format.
   * A response containing the formatted entity is returned.
   *
   * @param \Drupal\foldershare\FolderShareInterface[] $entities
   *   A list of entities to return, after cleaning and formatting.
   *
   * @return \Drupal\foldershare\Plugin\rest\resource\UncacheableResponse
   *   Returns an uncacheable response.
   */
  private function formatEntityListResponse(array $entities) {
    //
    // Clean entities
    // --------------
    // Loaded entities have all fields, but only some fields are user-visible.
    // "Clean" the entities of non-viewable fields by deleting them from the
    // entity.
    if (empty($entities) === TRUE) {
      return new UncacheableResponse(NULL, Response::HTTP_NO_CONTENT);
    }

    $partialEntities = [];
    foreach ($entities as &$entity) {
      $partialEntities[] = $this->cleanEntity($entity);
    }

    if (empty($partialEntities) === TRUE) {
      return new UncacheableResponse(NULL, Response::HTTP_NO_CONTENT);
    }

    //
    // Return entities
    // ---------------
    // Based upon the return format, return the entities.
    switch ($this->getAndValidateReturnFormat()) {
      default:
      case 'full':
        // Return as full entities.
        return new UncacheableResponse($partialEntities);

      case 'keyvalue':
        // Return as key-value pairs.
        $kv = [];
        foreach ($partialEntities as &$entity) {
          $kv[] = $this->formatKeyValueEntity($entity);
        }
        return new UncacheableResponse($kv);
    }
  }

}
