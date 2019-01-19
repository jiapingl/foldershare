<?php

namespace Drupal\foldershare\Form\AdminSettingsTraits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StringTranslation\TranslatableMarkup;

use Drupal\foldershare\Constants;
use Drupal\foldershare\Settings;
use Drupal\foldershare\Utilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Manages the "Files" tab for the module's settings form.
 *
 * <B>Warning:</B> This is an internal trait that is strictly used by
 * the AdminSettings form class. It is a mechanism to group functionality
 * to improve code management.
 *
 * @ingroup foldershare
 */
trait AdminSettingsFilesTab {

  /*---------------------------------------------------------------------
   *
   * Build.
   *
   *---------------------------------------------------------------------*/

  /**
   * Builds the form tab.
   *
   * Settings:
   * - File system (public or private).
   * - Enable/disable file extension restrictions.
   * - Set a list of allowed file extensions.
   * - Reset the allowed file extensions list to the default.
   * - Set maximum upload file size.
   * - Set maxumum number of files per upload.
   * - Links to related module settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form. The form
   *   is modified to include additional render elements for the tab.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param string $tabGroup
   *   The name of the tab group.
   * @param string $tabMachineName
   *   The untranslated name for the tab, used in CSS class names.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $tabTitle
   *   The translated name for the tab.
   */
  private function buildFilesTab(
    array &$form,
    FormStateInterface $formState,
    string $tabGroup,
    string $tabMachineName,
    TranslatableMarkup $tabTitle) {

    //
    // Setup
    // -----
    // Set up some variables.
    $tabName = self::makeCssSafe(Constants::MODULE . '_' . $tabMachineName . '_tab');
    $cssBase = self::makeCssSafe(Constants::MODULE);

    $sectionTitleClass       = Constants::MODULE . '-settings-section-title';
    $sectionClass            = Constants::MODULE . '-settings-section';
    $sectionDescriptionClass = Constants::MODULE . '-settings-section-description';
    $sectionDefaultClass     = Constants::MODULE . '-settings-section-default';
    $warningClass            = Constants::MODULE . '-warning';

    $inputStorageSchemeName      = Constants::MODULE . '_files_storage_scheme';
    $inputRestrictExtensionsName = Constants::MODULE . '_files_restrict_extensions';

    //
    // Create the tab
    // --------------
    // Start the tab with a title, subtitle, and description.
    $form[$tabName] = [
      '#type'           => 'details',
      '#open'           => FALSE,
      '#group'          => $tabGroup,
      '#title'          => $tabTitle,
      '#description'    => [
        'subtitle'      => [
          '#type'       => 'html_tag',
          '#tag'        => 'h2',
          '#value'      => $this->t(
            'Select how files are uploaded and stored.'),
          '#attributes' => [
            'class'     => [
              $cssBase . '-settings-subtitle',
            ],
          ],
        ],
        'description'   => [
          '#type'       => 'html_tag',
          '#tag'        => 'p',
          '#value'      => $this->t(
            "Files are stored on the site's server and tracked in the site's database. Administrators may restrict uploaded files based upon their name extension."),
          '#attributes' => [
            'class'     => [
              $cssBase . '-settings-description',
            ],
          ],
        ],
      ],
      '#attributes'     => [
        'class'         => [
          $cssBase . '-settings-tab ',
          $cssBase . '-fields-tab',
        ],
      ],
    ];

    //
    // Create warnings
    // ---------------
    // If the site currently has stored files, then the storage scheme
    // and subdirectory choice cannot be changed.
    //
    // If the site doesn't have stored files, but the private file system
    // has not been configured, then the storage scheme cannot be changed.
    $inUseWarning          = '';
    $noPrivateWarning      = '';
    $storageSchemeDisabled = FALSE;

    if (FolderShare::hasFiles() === TRUE) {
      // Create a link to the Drupal core system page to delete all content
      // for the entity type.
      $lnk = Link::createFromRoute(
        'Delete all',
        Constants::ROUTE_DELETEALL,
        [
          'entity_type_id' => FolderShare::ENTITY_TYPE_ID,
        ],
        []);

      $inUseWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'This setting is disabled because there are already files under management by this module. To change this setting, you must @deleteall files first.',
          [
            '@deleteall' => $lnk->toString(),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];
      $storageSchemeDisabled = TRUE;
    }

    if (empty(PrivateStream::basePath()) === TRUE) {
      $noPrivateWarning = [
        '#type'       => 'html_tag',
        '#tag'        => 'p',
        '#value'      => $this->t(
          'This setting is disabled and restricted to the <em>Public</em> file system. To change this setting, you must configure the site to support a <em>Private</em> file system. See the @filedoc module\'s documentation and the "file_private_path" setting in the site\'s "settings.php" file.',
          [
            '@filedoc' => Utilities::createDocLink('file', 'File'),
          ]),
        '#attributes' => [
          'class'     => [$warningClass],
        ],
      ];
      $storageSchemeDisabled = TRUE;
    }

    //
    // Storage scheme
    // --------------
    // Select whether files are stored in the public or private file system.
    $options = [
      'public'  => $this->t('Public'),
      'private' => $this->t('Private'),
    ];

    // Add section title and description.
    $form[$tabName]['file-system'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('File system') . '</h3>',
      '#attributes'      => [
        'class'          => [$sectionTitleClass],
      ],
      'section'          => [
        '#type'          => 'container',
        '#attributes'    => [
          'class'        => [$sectionClass],
        ],
        'description'    => [
          '#type'        => 'html_tag',
          '#tag'         => 'p',
          '#value'       => $this->t(
            "Select whether the module should use the site's <em>Public</em> or <em>Private</em> file system to store files. A <em>Private</em> file system provides better security."),
          '#attributes'  => [
            'class'      => [$sectionDescriptionClass],
          ],
        ],
      ],
    ];

    // Add warning if there are things disabled.
    if (empty($noPrivateWarning) === FALSE) {
      $form[$tabName]['file-system']['section']['warning'] = $noPrivateWarning;
    }
    elseif (empty($inUseWarning) === FALSE) {
      $form[$tabName]['file-system']['section']['warning'] = $inUseWarning;
    }

    // Add label and widget.
    $form[$tabName]['file-system']['section'][$inputStorageSchemeName] = [
      '#type'          => 'select',
      '#options'       => $options,
      '#default_value' => Settings::getFileScheme(),
      '#required'      => TRUE,
      '#disabled'      => $storageSchemeDisabled,
      '#title'         => $this->t('File system:'),
      '#description'   => [
        '#type'        => 'html_tag',
        '#tag'         => 'p',
        '#value'       => $this->t(
          'Default: @default',
          [
            '@default'   => $options[Settings::getFileSchemeDefault()],
          ]),
        '#attributes'    => [
          'class'        => [$sectionDefaultClass],
        ],
      ],
    ];

    //
    // File name extensions
    // --------------------
    // Select whether file uploads should be restricted based on their
    // file name extensions, and what extensions are allowed.
    $inputAllowedExtensionsName =
      Constants::MODULE . '_files_allowed_extensions';
    $inputRestoreExtensionsName =
      Constants::MODULE . '_files_restore_extensions';

    $form[$tabName]['fileextensions'] = [
      '#type'            => 'item',
      '#markup'          => '<h3>' . $this->t('File name extensions') . '</h3>',
      '#attributes'      => [
        'class'          => [$sectionTitleClass],
      ],
      'section'          => [
        '#type'          => 'container',
        '#attributes'    => [
          'class'        => [$sectionClass],
        ],
        'description'      => [
          '#type'          => 'html_tag',
          '#tag'           => 'p',
          '#value'         => $this->t(
            'Select whether uploaded files should be restricted to a specific set of allowed file name extensions.'),
          '#attributes'    => [
            'class'        => [$sectionDescriptionClass],
          ],
        ],
        $inputRestrictExtensionsName => [
          '#type'          => 'checkbox',
          '#title'         => $this->t('Enable restrictions'),
          '#default_value' => Settings::getFileRestrictExtensions(),
          '#return_value'  => 'enabled',
          '#required'      => FALSE,
          '#name'          => $inputRestrictExtensionsName,
        ],
        $inputAllowedExtensionsName => [
          '#type'          => 'textarea',
          '#title'         => $this->t('Allowed file name extensions:'),
          '#default_value' => Settings::getAllowedNameExtensions(),
          '#required'      => FALSE,
          '#rows'          => 7,
          '#name'          => $inputAllowedExtensionsName,
          '#description'   => $this->t(
            'Separate extensions with spaces and do not include dots. Changing this list does not affect files that are already on the site.'),
          '#states'        => [
            'invisible'    => [
              'input[name="' . $inputRestrictExtensionsName . '"]' => [
                'checked' => FALSE,
              ],
            ],
          ],
        ],
        $inputRestoreExtensionsName => [
          '#type'  => 'button',
          '#value' => $this->t('Restore original settings'),
          '#name'  => $inputRestoreExtensionsName,
          '#states'        => [
            'invisible'    => [
              'input[name="' . $inputRestrictExtensionsName . '"]' => [
                'checked' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ];

    // Get the current extensions, if any, on the form.
    $value = $formState->getValue($inputAllowedExtensionsName);
    if ($value !== NULL) {
      $form[$tabName]['fileextensions']['section'][$inputAllowedExtensionsName]['#value'] = $value;
    }

    if (self::ENABLE_FILE_LIMIT_SETTINGS === TRUE) {
      //
      // Maximum file size
      // -----------------
      // Select the maximum size for an individual uploaded file.
      // This is limited by the smaller of two PHP settings.
      $inputMaximumUploadSizeName =
        Constants::MODULE . '_files_maximum_upload_size';

      $form[$tabName]['filemaximumuploadsize'] = [
        '#type'              => 'item',
        '#markup'            => '<h3>' . $this->t('Maximum file upload size') . '</h3>',
        '#attributes'        => [
          'class'            => [$sectionTitleClass],
        ],
        'section'            => [
          '#type'            => 'container',
          '#attributes'      => [
            'class'          => [$sectionClass],
          ],
          'description'      => [
            '#type'          => 'html_tag',
            '#tag'           => 'p',
            '#value'         => $this->t(
              'Set the maximum size allowed for each uploaded file. This size is also limited by PHP settings in your server\'s "php.ini" file and your site\'s top-level ".htaccess" file. These are currently limiting file sizes to @bytes bytes.',
              [
                '@bytes'     => Settings::getPhpUploadMaximumFileSize(),
              ]),
            '#attributes'    => [
              'class'        => [$sectionDescriptionClass],
            ],
          ],
          $inputMaximumUploadSizeName => [
            '#type'          => 'textfield',
            '#title'         => $this->t('Size:'),
            '#default_value' => Settings::getUploadMaximumFileSize(),
            '#required'      => TRUE,
            '#size'          => 20,
            '#maxlength'     => 20,
            '#description'   => [
              '#type'        => 'html_tag',
              '#tag'         => 'p',
              '#value'       => $this->t(
                'Default: @default',
                [
                  '@default' => Settings::getUploadMaximumFileSizeDefault() .
                  ' ' . $this->t('bytes'),
                ]),
              '#attributes'  => [
                'class'      => [$sectionDefaultClass],
              ],
            ],
          ],
        ],
      ];

      //
      // Maximum file number
      // -------------------
      // Select the maximum number of files uploaded in one post.
      // This is limited by PHP settings.
      $inputMaximumUploadNumberName =
        Constants::MODULE . '_files_maximum_upload_number';

      $form[$tabName]['filemaximumuploadnumber'] = [
        '#type'              => 'item',
        '#markup'            => '<h3>' . $this->t('Maximum number of files per upload') . '</h3>',
        '#attributes'        => [
          'class'            => [$sectionTitleClass],
        ],
        'section'            => [
          '#type'            => 'container',
          '#attributes'      => [
            'class'          => [$sectionClass],
          ],
          'description'      => [
            '#type'          => 'html_tag',
            '#tag'           => 'p',
            '#value'         => $this->t(
              'Set the maximum number of files that may be uploaded in a single pload. This number is also limited by PHP settings in your server\'s "php.ini" file and your site\'s top-level ".htaccess" file. These are currently limiting file uploads to @number files.',
              [
                '@number'    => Settings::getPhpUploadMaximumFileNumber(),
              ]),
            '#attributes'    => [
              'class'        => [$sectionDescriptionClass],
            ],
          ],
          $inputMaximumUploadNumberName => [
            '#type'          => 'textfield',
            '#title'         => $this->t('Number:'),
            '#default_value' => Settings::getUploadMaximumFileNumber(),
            '#required'      => TRUE,
            '#size'          => 20,
            '#maxlength'     => 20,
            '#description'   => [
              '#type'        => 'html_tag',
              '#tag'         => 'p',
              '#value'       => $this->t(
                'Default: @default',
                [
                  '@default' => Settings::getUploadMaximumFileNumberDefault() .
                  ' ' . $this->t('files'),
                ]),
              '#attributes'  => [
                'class'      => [$sectionDefaultClass],
              ],
            ],
          ],
        ],
      ];
    }

    //
    // Related settings
    // ----------------
    // Add links for related settings.
    $this->buildFilesRelatedSettings($form, $formState, $tabGroup, $tabName);
  }

  /**
   * Builds the related settings section of the tab.
   *
   * @param array $form
   *   An associative array containing the structure of the form. The form
   *   is modified to include additional render elements for the tab.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param string $tabGroup
   *   The name of the tab group.
   * @param string $tabName
   *   The CSS-read tab name.
   */
  private function buildFilesRelatedSettings(
    array &$form,
    FormStateInterface $formState,
    string $tabGroup,
    string $tabName) {

    $sectionTitleClass = Constants::MODULE . '-settings-section-title';
    $seeAlsoClass      = Constants::MODULE . '-settings-seealso-section';
    $relatedClass      = Constants::MODULE . '-related-links';
    $markup            = '';

    //
    // Create links.
    // -------------
    // For each of several modules, create a link to the module's settings
    // page if the module is installed, and no link if it is not.
    //
    // Cron.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'Cron',
      'system.cron_settings',
      [],
      "Configure the site's automatic administrative tasks.",
      TRUE,
      [
        'system'                => 'System',
        'automated-cron-module' => 'Automated Cron',
      ]);

    // File.
    $markup .= $this->buildRelatedSettings(
      // No specific module.
      '',
      'File',
      'system.file_system_settings',
      [],
      "Configure the site's <em>Public</em> and <em>Private</em> file systems.",
      TRUE,
      ['file' => 'File']);

    //
    // Add to form.
    // ------------
    // Add the links to the end of the form.
    //
    $form[$tabName]['related-settings'] = [
      '#type'            => 'details',
      '#title'           => $this->t('See also'),
      '#open'            => FALSE,
      '#summary_attributes' => [
        'class'          => [$sectionTitleClass],
      ],
      '#attributes'      => [
        'class'          => [$seeAlsoClass],
      ],
      'seealso'          => [
        '#type'          => 'item',
        '#markup'        => '<dl>' . $markup . '</dl>',
        '#attributes'    => [
          'class'        => [$relatedClass],
        ],
      ],
    ];
  }

  /*---------------------------------------------------------------------
   *
   * Validate.
   *
   *---------------------------------------------------------------------*/

  /**
   * Validates form values.
   *
   * @param array $form
   *   The form configuration.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The entered values for the form.
   */
  private function validateFilesTab(
    array &$form,
    FormStateInterface $formState) {

    // Setup
    // -----
    // Get user input.
    $userInput = $formState->getUserInput();

    // Determine if the private file system is available.
    $hasPrivate = empty(PrivateStream::basePath()) === FALSE;

    //
    // File scheme
    // -----------
    // It must be "public" or "private". If "private", the site's private
    // file system must have been configured.
    $inputStorageSchemeName = Constants::MODULE . '_files_storage_scheme';
    $scheme = $formState->getValue($inputStorageSchemeName);
    if ($scheme !== 'public' && $scheme !== 'private') {
      $formState->setErrorByName(
        $inputStorageSchemeName,
        $this->t('The file storage scheme must be either "Public" or "Private".'));
    }

    if ($scheme === 'private' && $hasPrivate === FALSE) {
      $formState->setErrorByName(
        $inputStorageSchemeName,
        $this->t('The "Private" file storage scheme is not available at this time because the web site has not been configured to support it.'));
    }

    //
    // File extension restore
    // ----------------------
    // Check if the reset button was pressed.
    $inputAllowedExtensionsName =
      Constants::MODULE . '_files_allowed_extensions';
    $inputRestoreExtensionsName =
      Constants::MODULE . '_files_restore_extensions';
    $value = $formState->getValue($inputRestoreExtensionsName);
    if (isset($userInput[$inputRestoreExtensionsName]) === TRUE &&
        $userInput[$inputRestoreExtensionsName] === (string) $value) {
      // Reset button pressed.
      //
      // Get the default extensions.
      $value = Settings::getAllowedNameExtensionsDefault();

      // Immediately change the configuration and file field.
      Settings::setAllowedNameExtensions($value);
      FolderShare::setAllowedNameExtensions($value);

      // Reset the form's extensions list.
      $formState->setValue($inputAllowedExtensionsName, $value);

      // Unset the button in form state since it has already been handled.
      $formState->setValue($inputRestoreExtensionsName, NULL);

      // Rebuild the page.
      $formState->setRebuild(TRUE);
      \Drupal::messenger()->addMessage(
        t('The file extensions list has been restored to its original values.'),
        'status');
    }

    if (self::ENABLE_FILE_LIMIT_SETTINGS === TRUE) {
      //
      // Maximum upload size and number
      // ------------------------------
      // The values cannot be empty, zero, or negative.
      $inputMaximumUploadSizeName =
        Constants::MODULE . '_files_maximum_upload_size';
      $value = $formState->getValue($inputMaximumUploadSizeName);
      if (empty($value) === TRUE || ((int) $value) <= 0) {
        $formState->setErrorByName(
          $inputMaximumUploadSizeName,
          $this->t('The maximum file upload size must be a positive integer.'));
      }

      $inputMaximumUploadNumberName =
        Constants::MODULE . '_files_maximum_upload_number';
      $value = $formState->getValue($inputMaximumUploadNumberName);
      if (empty($value) === TRUE || ((int) $value) <= 0) {
        $formState->setErrorByName(
          $inputMaximumUploadNumberName,
          $this->t('The maximum file upload number must be a positive integer.'));
      }
    }
  }

  /*---------------------------------------------------------------------
   *
   * Submit.
   *
   *---------------------------------------------------------------------*/

  /**
   * Stores submitted form values.
   *
   * @param array $form
   *   The form configuration.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The entered values for the form.
   */
  private function submitFilesTab(
    array &$form,
    FormStateInterface $formState) {

    //
    // File scheme
    // -----------
    // Update file storage scheme if there are no files under management.
    if (FolderShare::hasFiles() === FALSE) {
      $inputStorageSchemeName = Constants::MODULE . '_files_storage_scheme';
      Settings::setFileScheme(
        $formState->getValue($inputStorageSchemeName));
    }

    //
    // File extensions
    // ---------------
    // Updated whether extensions are checked, and which ones
    // are allowed.
    $inputAllowedExtensionsName =
      Constants::MODULE . '_files_allowed_extensions';
    $inputRestrictExtensionsName =
      Constants::MODULE . '_files_restrict_extensions';

    $value      = $formState->getValue($inputRestrictExtensionsName);
    $enabled    = ($value === 'enabled') ? TRUE : FALSE;
    $extensions = $formState->getValue($inputAllowedExtensionsName);

    Settings::setFileRestrictExtensions($enabled);
    Settings::setAllowedNameExtensions($extensions);

    // If extensions are enabled, forward them to the file field.
    // Otherwise, clear the extension list on the file field.
    if ($enabled === TRUE) {
      FolderShare::setAllowedNameExtensions($extensions);
    }
    else {
      FolderShare::setAllowedNameExtensions('');
    }

    if (self::ENABLE_FILE_LIMIT_SETTINGS === TRUE) {
      //
      // Maximum upload size and number
      // ------------------------------
      // Update the limits.
      $inputMaximumUploadSizeName =
        Constants::MODULE . '_files_maximum_upload_size';
      Settings::setUploadMaximumFileSize(
        $formState->getValue($inputMaximumUploadSizeName));

      $inputMaximumUploadNumberName =
        Constants::MODULE . '_files_maximum_upload_number';
      Settings::setUploadMaximumFileNumber(
        $formState->getValue($inputMaximumUploadNumberName));
    }
  }

}
