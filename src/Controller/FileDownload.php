<?php

namespace Drupal\foldershare\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

use Drupal\file\Entity\File;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\foldershare\Settings;
use Drupal\foldershare\Constants;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Defines a class to handle downloading a file in a folder.
 *
 * This download controller is used by the FolderShareStream wrapper for
 * external URLs to access individual files managed by FolderShare.
 * The controller is used for both private and public file systems in
 * order to do access control checks before download.
 *
 * @ingroup foldershare
 */
class FileDownload extends ControllerBase {

  /*--------------------------------------------------------------------
   *
   * Fields.
   *
   *--------------------------------------------------------------------*/

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  private $streamWrapperManager;

  /*--------------------------------------------------------------------
   *
   * Construction.
   *
   *--------------------------------------------------------------------*/

  /**
   * Constructs a new form.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $streamWrapperManager
   *   The MIME type guesser.
   */
  public function __construct(StreamWrapperManager $streamWrapperManager) {
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager')
    );
  }

  /*--------------------------------------------------------------------
   *
   * Download.
   *
   *--------------------------------------------------------------------*/

  /**
   * Downloads the file after access control checks.
   *
   * The file is sent with a custom HTTP header that includes the full
   * human-readable name of the file and its MIME type.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains the entity ID of the
   *   file being requested. The entity ID is included in the URL
   *   for links to the file.
   * @param \Drupal\file\Entity\File $file
   *   (optional, default = NULL) The file object to download, parsed from
   *   the URL by using an embedded File entity ID. If the entity ID is not
   *   valid, the function receives a NULL argument.  NOTE:  Because this
   *   function is the target of a route with a file argument, the name of
   *   the function argument here *must be* named after the argument
   *   name: 'file'.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   A binary transfer response is returned to send the file to the
   *   user's browser.
   *
   * @throws \Symfony\Component\HttpKernel\Exxception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   *
   * @throws \Symfony\Component\HttpKernel\Exxception\NotFoundHttpException
   *   Thrown when the entity ID is invalid, for a file not managed
   *   by this module, or any other access problem occurs.
   */
  public function download(Request $request, File $file = NULL) {
    //
    // Validate arguments
    // ------------------
    // Make sure the file argument loaded.
    if ($file === NULL) {
      // Fail. The URL did not include a File entity ID, or the entity ID
      // was not valid.
      throw new NotFoundHttpException();
    }

    // Get the file's URI.
    $uri = $file->getFileUri();

    //
    // Validate permission
    // -------------------
    // Make sure the File entity is wrapped by a FolderShare entity, and
    // that the FolderShare entity allows 'view' operations for this user.
    //
    // There are two branches for this code:
    //
    // - When using a public file system, this download controller must do
    //   access control checks itself.
    //
    // - When using a private file system, this download controller is
    //   expected to invoke hook_file_download(). This module's hook has
    //   to handle URIs from this controller, and any other code (for instance
    //   the download controller for the Image module calls the hook).
    //   Access control checks are therefore in this module's hook, not here.
    $fileScheme = Settings::getFileScheme();
    $stream = $this->streamWrapperManager->getViaScheme($fileScheme);
    $streamDirectory = $stream->getDirectoryPath();

    $isPrivate = ($fileScheme === 'private');
    $headers = [];
    if ($isPrivate === TRUE) {
      // Private file system is in use.
      //
      // Drupal core supports hook_file_download(), which is implemented by
      // this module. The hook is responsible for checking if the URI is
      // for a File entity (we already know it is), and that that File entity
      // is wrapped by a FolderShare entity. The hook then checks if the
      // FolderShare entity grants the current user 'view' permission.
      //
      // If the URI, File entity, FolderShare entity, and 'view' permission
      // are all OK, the hook returns HTTP headers for the file.
      $headers = $this->moduleHandler()->invokeAll('file_download', [$uri]);

      // If the returned $headers array is NULL, then ALL of the hooks
      // responded that the URI is not recognized. Report a not found error.
      if ($headers === NULL) {
        // Fail. This module's hook, and all other hooks, did not recognize
        // the file.
        throw new NotFoundHttpException();
      }

      // If ALL of the hooks return (-1), then Access Denied. If only some
      // did, then remove the (-1) entries and keep the good responses.
      // However, this seems unlikely - can one hook say this is my file and
      // access denied, while another hook says this is my file and sure, go
      // ahead and get the file?
      $cleanedHeaders = [];
      foreach ($headers as $index => $response) {
        // Valid headers do not have a numeric index and do not have
        // a (-1) as the value.
        if (!(is_int($index) === TRUE &&
            is_int($response) === TRUE &&
            (int) $response === (-1))) {
          $cleanedHeaders[$index] = $response;
        }
      }

      if (count($cleanedHeaders) === 0) {
        // Fail. This module's hook, or some other hook, has denied access to
        // the file.
        throw new AccessDeniedHttpException();
      }

      $headers = $cleanedHeaders;
    }
    else {
      // Public file system (or some other stream wrapper) is in use.
      //
      // The Drupal core hook_file_download() is not supposed to be used.
      // We therefore do access control checking here.
      //
      // Look for the FolderShare entity that wraps this File entity.
      $wrapperId = FolderShare::findFileWrapperId($file);
      if ($wrapperId === FALSE) {
        // Fail. There is none. While the URI is for a File entity, the File
        // entity is not one referenced by a FolderShare entity and it is,
        // therefore, not a file under management by this module.
        throw new NotFoundHttpException();
      }

      // Make sure the folder is loadable.
      $wrapper = FolderShare::load($wrapperId);
      if ($wrapper === NULL) {
        // Fail. Something has become corrupted! The above query found the ID
        // of a FolderShare entity that wraps the file, but now when the
        // entity is loaded, the load fails. This can only happen if the
        // entity has been deleted between the previous call and this one.
        throw new NotFoundHttpException();
      }

      if ($wrapper->isSystemHidden() === TRUE) {
        // Hidden items do not exist.
        throw new NotFoundHttpException();
      }

      if ($wrapper->isSystemDisabled() === TRUE) {
        // Disabled items cannot be edited.
        throw new AccessDeniedHttpException();
      }

      // Check for view access to the FolderShare entity that manages this
      // file.
      if ($wrapper->access('view') === FALSE) {
        // Fail. The user does not have access.
        throw new AccessDeniedHttpException();
      }
    }

    //
    // Forward if not delivering file
    // ------------------------------
    // If there is a $prefix query parameter, then use this to build a new
    // path and hand processing back to Drupal to work with that path.
    //
    // Watch for the special case where the prefix is just the scheme's
    // directory, in which case it is redundant and we can do without
    // this forwarding.
    if ($request->query->has(Constants::ROUTE_DOWNLOADFILE_PREFIX) === TRUE) {
      // Get the prefix query parameter and remove it from the request.
      // We handle the prefix specially.
      $prefix = $request->query->get(Constants::ROUTE_DOWNLOADFILE_PREFIX);
      $prefix = trim($prefix, '/');
      if ($prefix !== $streamDirectory) {
        $request->query->remove(Constants::ROUTE_DOWNLOADFILE_PREFIX);

        // Build a URL query string with any remaining query parameters.
        if ($request->query->count() !== 0) {
          $query = '?' . UrlHelper::buildQuery($request->query->all());
        }
        else {
          $query = '';
        }

        // Get the file URI's path.
        $path = trim(file_uri_target($uri), '/');

        // Build the original URL path that our hook_file_url_alter() caught
        // earlier and morphed into a URL for this download controller. We
        // now need to reverse that and regain the original URL by prepending
        // the original URL prefix onto the file's path.
        $newPath = UrlHelper::encodePath('/' . $prefix . '/' . $path);

        $redirectUrl = $newPath . $query;

        // Forward back to Drupal.
        //
        // We'd rather send this URL back to Drupal immediately, but it isn't
        // clear how to do this. We therefore issue a redirect response.
        //
        // Arguments to the constructor are:
        // - The URL string for the redirect.
        // - The status code.
        // - Optional HTTP heades.
        //
        // The Drupal default status code is 302, which works for HTTP 1.0
        // but has an illdefined meaning for browsers. HTTP 1.1 clarified
        // the meaning by introducing new status codes 307 and 308:
        //
        // - 307 = Temporary redirect. Future requests for the same item should
        //   issue a request to the original URL.
        //
        // - 308 = Permanent redirect. Future requests for the same item should
        //   issue a request using the new URL (i.e. they should cache the
        //   revised URL and use it only).
        //
        // The old 302 status code could be interpreted with either meaning.
        //
        // For this code, it is *essential* that the redirect be treated as
        // temporary (status code 307) so that future requests for the same item
        // will go through this file download controller again and check access
        // again. Access may have changed if, for instance, the user's access
        // has been changed by the owner of the item.
        return new RedirectResponse($redirectUrl, 307);
      }
    }

    //
    // Update headers
    // --------------
    // Insure that the user-visible file name is used by the browser.
    // This will override any content disposition header value that might
    // have been provided by private file system download hooks.
    //
    // Including the user-visible file name in the header is essential.
    // The file name in the URI is an internal numeric name (see
    // FileUtilities::getFileUri()). If the user tries to save the delivered
    // file, they'll get that numeric name instead of the user-visible name
    // if we didn't include the correct name in the HTTP header.
    $filename = $file->getFilename();
    $disposition = 'filename="' . $filename . '"';

    $headers['Content-Disposition'] = $disposition;

    // Insure that other parts of the header are set, if hooks have not
    // set them already.
    if (isset($headers['Content-Type']) === FALSE) {
      $headers['Content-Type'] =
        Unicode::mimeHeaderEncode($file->getMimeType());
    }

    if (isset($headers['Content-Length']) === FALSE) {
      $headers['Content-Length'] = $file->getSize();
    }

    // Don't cache the file because permissions and content may change.
    // Override any header values that may have been set by hooks.
    $headers['Pragma']        = 'no-cache';
    $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
    $headers['Expires']       = '0';
    $headers['Accept-Ranges'] = 'bytes';

    //
    // Respond
    // -------
    // Arguments to the response indicate:
    // - The URI.
    // - A status code (200 = OK).
    // - The HTTP headers.
    // - Whether the file is public. Public files can be delivered directly
    //   by the web server, rather than Drupal.
    // - Whether to set the content disposition header value. No.
    // - Whether to set the ETag header value. No.
    // - Whether to set the Last-modified header value. No.
    return new BinaryFileResponse(
      $uri,
      200,
      $headers,
      $isPrivate === FALSE,
      NULL,
      FALSE,
      FALSE);
  }

}
