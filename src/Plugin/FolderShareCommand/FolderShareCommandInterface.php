<?php

namespace Drupal\foldershare\Plugin\FolderShareCommand;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Executable\ExecutableInterface;

/**
 * Defines an interface for plugins for commands on a shared folder.
 *
 * A command may be triggered by a user interaction with a web page via
 * AJAX, a Drupal form, a REST request, a drush command line, or some
 * other interface.  Commands report errors via a well-defined set of
 * exceptions, which the external trigering mechanism may turn into
 * AJAX responses, REST responses, or Drupal form responses.
 *
 * Subclasses implement specific commands that use a set of parameters
 * and operate upon referenced FolderShare objects. Commands
 * could create new folders or files, delete them, move them, copy them,
 * or update them in some way.
 *
 * A command is responsible for validating parameters, performing access
 * control checks, and issuing calls to the Folder and File data models
 * to cause an operation to take place.
 *
 * @ingroup foldershare
 */
interface FolderShareCommandInterface extends PluginFormInterface, ExecutableInterface, PluginInspectionInterface, ConfigurablePluginInterface {

  /*--------------------------------------------------------------------
   *
   * Constants - Special entity IDs.
   *
   *--------------------------------------------------------------------*/

  /**
   * Indicates that no FolderShare item ID was provided.
   *
   * Note that the Javascript UI also uses (-1) to mean an empty item ID.
   *
   * @var int
   */
  const EMPTY_ITEM_ID = (-1);

  /*---------------------------------------------------------------------
   *
   * Configuration forms.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns the translated description of the form, if any.
   *
   * @param bool $forPage
   *   Indicate whether the returned description is intended for a page
   *   (TRUE) or a dialog (FALSE).
   *
   * @return TranslatableMarkup
   *   Returns the description for the form. Return an empty string for
   *   no description.
   */
  public function getDescription(bool $forPage);

  /**
   * Returns the translated title of the form, if any.
   *
   * @param bool $forPage
   *   Indicate whether the returned title is intended for a page
   *   (TRUE) or a dialog (FALSE).
   *
   * @return TranslatableMarkup
   *   Returns the title for the form. Return an empty string for
   *   no title.
   */
  public function getTitle(bool $forPage);

  /**
   * Returns the translated name of the form's submit button, if any.
   *
   * @return TranslatableMarkup
   *   Returns the name for the submit button.
   */
  public function getSubmitButtonName();

  /**
   * Returns TRUE if the command has a configuration form.
   *
   * When TRUE, the class must implement the form methods of
   * PluginFormInterface, including:
   *
   * - buildConfigurationForm().
   * - validateConfigurationForm().
   * - submitConfigurationForm().
   *
   * @return bool
   *   Returns TRUE if there is a configuration form.
   *
   * @see PluginFormInterface::buildConfigurationForm()
   * @see PluginFormInterface::validateConfigurationForm()
   * @see PluginFormInterface::submitConfigurationForm()
   */
  public function hasConfigurationForm();

  /*---------------------------------------------------------------------
   *
   * Redirects.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the command redirects to another page.
   *
   * When TRUE, the class must implement getRedirect() to return a URL
   * for the redirect page.
   *
   * @return bool
   *   Returns TRUE if there is a redirect.
   *
   * @see ::getRedirect()
   */
  public function hasRedirect();

  /**
   * Returns a URL for the command's redirect, if any.
   *
   * The class must implement hasRedirect() to return TRUE and flag that
   * execution of the command must be handled after redirecting to another
   * page first.
   *
   * @return \Drupal\Core\Url
   *   Returns the URL for a new page to which the command needs to redirect
   *   in order to complete the command. This may be a page with a form to
   *   collect additional information.
   *
   * @see ::hasRedirect()
   */
  public function getRedirect();

  /*---------------------------------------------------------------------
   *
   * Validate.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns TRUE if the command's configuration has been fully validated.
   *
   * @return bool
   *   Returns TRUE if the command has been validated.
   *
   * @see ::validateConfiguration()
   * @see ::validateCommandUse();
   */
  public function isValidated();

  /**
   * Validates the current configuration.
   *
   * This validates, in order:
   * - The command's availability at this site.
   * - The current user.
   * - The parent folder.
   * - The selection.
   * - The destination folder.
   * - The command's custom parameters.
   *
   * If any of the above are not valid, this function throws an exception
   * with details about the problem.
   *
   * On success, this function returns and future calls to isValidated()
   * will return TRUE.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws a validation exception with a message describing the problem.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an access denied exception if an entity is disabled.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws a not found exception if an entity is hidden or not found.
   *
   * @see ::isValidated()
   * @see ::validateCommandAllowed()
   * @see ::validateParentConstraints()
   */
  public function validateConfiguration();

  /**
   * Validates that the command is allowed by this site.
   *
   * The command is checked against site constraints to insure that the
   * command is available.
   *
   * An exception is thrown if the constraints are not met.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an access denied exception if the command is not available
   *   on this site.
   *
   * @see ::validateConfiguration()
   * @see \Drupal\foldershare\Annotation\FolderShareCommand
   */
  public function validateCommandAllowed();

  /**
   * Validates that the current user meets command constraints.
   *
   * An exception is thrown if the constraints are not met.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws a validation exception with a message describing the problem.
   *
   * @see ::validateConfiguration()
   * @see \Drupal\foldershare\Annotation\FolderShareCommand
   */
  public function validateUserConstraints();

  /**
   * Validates that the parent meets command constraints.
   *
   * An exception is thrown if the constraints are not met.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws a validation exception with a message describing the problem.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an access denied exception if the parent entity is disabled.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws a not found exception if the parent entity is hidden.
   *
   * @see ::validateConfiguration()
   * @see \Drupal\foldershare\Annotation\FolderShareCommand
   */
  public function validateParentConstraints();

  /**
   * Validates that the selection (if any) meets command constraints.
   *
   * An exception is thrown if the constraints are not met.
   *
   * This function is automatically called by validateConfiguration(),
   * but it may be called directly to validate the selection configuration only.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws a validation exception with a message describing the problem.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an access denied exception if a selected entity is disabled.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws a not found exception if a selected entity is hidden.
   *
   * @see ::validateConfiguration()
   * @see \Drupal\foldershare\Annotation\FolderShareCommand
   */
  public function validateSelectionConstraints();

  /**
   * Validates that the destination (if any) meets command constraints.
   *
   * An exception is thrown if the constraints are not met.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws a validation exception with a message describing the problem.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws an access denied exception if the destination entity is disabled.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws a not found exception if the destination entity is hidden.
   *
   * @see ::validateConfiguration()
   * @see \Drupal\foldershare\Annotation\FolderShareCommand
   */
  public function validateDestinationConstraints();

  /**
   * Validates any additional command-specific parameters.
   *
   * An exception is thrown if the constraints are not met.
   *
   * @throws \Drupal\foldershare\Entity\Exception\ValidationException
   *   Throws a validation exception with a message describing the problem.
   *
   * @see ::validateConfiguration()
   */
  public function validateParameters();

}
