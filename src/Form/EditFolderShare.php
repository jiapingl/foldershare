<?php

namespace Drupal\foldershare\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines a user form for editing folder share entity fields.
 *
 * The form presents the current values of all fields, except
 * for the name and other internal fields. The set of fields
 * shown always includes the description. Additional fields may
 * include the language choice, if the Drupal core Languages module
 * is enabled, plus any other fields a site has added to the folder type.
 *
 * The user is prompted to change any of the presented fields. A 'Save'
 * button triggers the form and saves the edits.
 *
 * <b>Access control:</b>
 * The route to this form must invoke the access control handler to
 * insure that the user is the admin or the owner of the folder subject.
 *
 * <b>Route:</b>
 * The route to this form must include a $foldershare argument.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 */
final class EditFolderShare extends ContentEntityForm {

  /*---------------------------------------------------------------------
   *
   * Form.
   *
   *---------------------------------------------------------------------*/

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return str_replace('\\', '_', get_class($this));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $formState) {
    //
    // Validate arguments
    // ------------------
    // Check that the $foldershare argument is valid.
    $item = $this->getEntity();
    if ($item === NULL) {
      $message = $this->t(
        "Communications problem.\nThe @form form was improperly invoked. The FolderShare entity parameter is required but missing.",
        [
          '@form' => get_class($this),
        ]);

      // Report the message to the user.
      $this->messenger()->addMessage($this->t(
        '@module: @msg',
        [
          '@module' => Constants::MODULE,
          '@msg'    => $message,
        ]));

      // Log the message for the admin.
      $this->logger(Constants::MODULE)->error($message);
      return [];
    }

    if ($item->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($item->isSystemDisabled() === TRUE) {
      // Disabled items cannot be edited.
      throw new AccessDeniedHttpException();
    }

    //
    // Setup
    // -----
    // Let the parent class build the default form for the entity.
    // The form may include any editable fields accessible by the
    // current user. This will not include the name field, or other
    // internal fields, which are blocked from being listed by
    // the base field definitions for the Folder entity.
    //
    // The order of fields on the form will honor the site's choices
    // via the field_ui.
    //
    // The EntityForm parent class automatically adds a 'Save' button
    // to submit the form.
    $form = parent::form($form, $formState);

    $form['#title'] = $this->t(
      'Edit "@name"',
      [
        '@name' => $item->getName(),
      ]);

    //
    // Nothing else at this time.  The default form is fine.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState) {
    $item = $this->getEntity();

    if ($item->isSystemHidden() === TRUE) {
      // Hidden items do not exist.
      throw new NotFoundHttpException();
    }

    if ($item->isSystemDisabled() === TRUE) {
      // Disabled items cannot be edited.
      throw new AccessDeniedHttpException();
    }

    // Save
    // -----
    // The parent class handles saving the entity.
    parent::save($form, $formState);

    FolderShare::postOperationHook(
      'edit',
      [
        $item,
      ]);
    FolderShare::log(
      'notice',
      'Edited entity @id ("%name").',
      [
        '@id'      => $item->id(),
        '%name'    => $item->getName(),
        'link'     => $item->toLink($this->t('View'))->toString(),
      ]);

    //
    // Redirect
    // --------
    // Return to the view page for the folder.
    $formState->setRedirect(
      Constants::ROUTE_FOLDERSHARE,
      [
        Constants::ROUTE_FOLDERSHARE_ID => $item->id(),
      ]);
  }

}
