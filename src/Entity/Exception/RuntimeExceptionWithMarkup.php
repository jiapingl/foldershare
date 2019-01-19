<?php

namespace Drupal\foldershare\Entity\Exception;

use Drupal\Core\Render\Markup;

/**
 * Defines an exception indicating a content validation problem.
 *
 * In addition to standard exception parameters (such as the message),
 * a validation exception includes an optional item number that indicates
 * when the exception applies to a specific item in a list of items.
 *
 * @ingroup foldershare
 */
class RuntimeExceptionWithMarkup extends \RuntimeException {
  /*--------------------------------------------------------------------
   *
   * Fields.
   *
   *--------------------------------------------------------------------*/

  /**
   * The original markup.
   *
   * The string version of the markup is used to set the exception's
   * message so that the parent class's getMessage() works.
   *
   * @var \Drupal\Core\Render\MarkupInterface
   */
  private $markup = NULL;

  /*--------------------------------------------------------------------
   *
   * Constructors.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs an exception.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $message
   *   The message. It is commonly either a string or an instance of
   *   \Drupal\Component\Render\MarkupInterface.
   * @param int $code
   *   (optional, default = 0) An error code.
   * @param \Throwable $previous
   *   (optional, default = NULL) A previous exception that this extends.
   */
  public function __construct(
    $message,
    int $code = 0,
    Throwable $previous = NULL) {

    // Save or create markup for the message.
    $this->markup = Markup::create($message);

    // Invoke the parent with the string version of the message.
    parent::__construct(strip_tags((string) $message), $code, $previous);
  }

  /*--------------------------------------------------------------------
   *
   * Methods.
   *
   *--------------------------------------------------------------------*/

  /**
   * Returns the exception message's markup.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   Returns a markup object for the exception's message.
   */
  public function getMarkup() {
    return $this->markup;
  }

}
