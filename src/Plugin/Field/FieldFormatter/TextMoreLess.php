<?php

namespace Drupal\foldershare\Plugin\Field\FieldFormatter;

use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;

/**
 * Formats a text field.
 *
 * @deprecated This formatter no longer does anything.
 *
 * @FieldFormatter(
 *   id = "text_moreless",
 *   label = @Translation("Text (deprecated)"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class TextMoreLess extends TextDefaultFormatter {

}
