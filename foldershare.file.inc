<?php

/**
 * @file
 * Implements file hooks for the module.
 */

use Drupal\Core\Url;

use Drupal\Component\Utility\Unicode;

use Drupal\foldershare\Constants;
use Drupal\foldershare\FileUtilities;
use Drupal\foldershare\Entity\FolderShare;

/**
 * Implements hook_file_url_alter().
 *
 * This hook is called from Drupal core's file_create_url() helper
 * function to optionally modify an incoming URI.
 *
 * This method's goals are:
 *
 * - Obscure the directory structure this module uses when storing files.
 *
 * - Create URLs that, when used, lead to this module's file download
 *   controller, which handles access control.
 *
 * - Insure that access control checking occurs for private *and* public
 *   storage of this module's files, *and* derived files (such as those
 *   for styled images from the Drupal core Image module).
 *
 * Drupal calls this method for all file URIs (assuming a module is written
 * properly and calls file_create_url()), including those for images,
 * Javascript, CSS, etc. It is therefore called very frequently and it is
 * important that this method be written to very quickly return if the
 * incoming URI is not relevant to this method.
 *
 * This method looks for URIs that include the module's FILE_DIRECTORY
 * into which all files managed by the module are stored. If this directory
 * name is not found, this method ignores the URI.
 *
 * When a URI does include this module's file directory name, the URI is
 * parsed to see if the subpath sequence that names a module file. This
 * sequence has the form "FILE_DIRECTORY/DIGITS/.../DIGITS.EXTENSION", where:
 *
 * - FILE_DIRECTORY is this module's file directory.
 * - DIGITS for the directory and file names form the 20-digit base-10
 *   zero-padded File entity ID of a file to access.
 * - EXTENSION is the filename extension for the file.
 *
 * URIs like this are created by FileUtilities::getFileUri().
 *
 * If a properly formed subpath is not found, this method ignores the URI.
 *
 * When the subpath is present, the entire URI is changed to the form
 * ROUTE_DOWNLOADFILE/ENTITY_ID?prefix=PREFIX, where:
 *
 * - ROUTE_DOWNLOADFILE is the route path for the module's file download
 *   controller.
 *
 * - ENTITY_ID is the File object entity ID parsed from the DIGITS above.
 *
 * - PREFIX is the portion of the original path preceding the subpath above.
 *
 * @see \Drupal\foldershare\FileUtilities::getFileUri()
 * @see \Drupal\foldershare\FileUtilities::getFileEntityId()
 * @see \Drupal\foldershare\Controller\FileDownload::download()
 * @see foldershare_file_download()
 * @see file_create_url()
 */
function foldershare_file_url_alter(&$uri) {
  //
  // Check URI for the file directory
  // --------------------------------
  // If the URI does not include this module's file directory name, then it
  // is not a URI we care about. The file directory name may occur anywhere
  // within the URI.
  $pos = strpos($uri, Constants::FILE_DIRECTORY);
  if ($pos === FALSE) {
    // Fail. The module's file directory name is not in the path. Ignore it.
    return;
  }

  //
  // Convert URI to URL
  // ------------------
  // To be clear:
  //
  // - The URI passed to this function may use an internal scheme, such as
  //   "public" or "private". This URI is only usable within Drupal and is
  //   never shown to a user.
  //
  // - A URL for the same file maps the URI to an external form that shows up
  //   on web pages and is sent by a browser back to Drupal to ask for the
  //   file. This external URL may have a different path, and it certainly
  //   has a different scheme.
  //
  // This method's goal is to alter the URI into a form that leads to a URL
  // that, when returned to Drupal later, routes to the file download
  // controller. We need to pass to that future download controller something
  // about the original URI so that it knows what file was requested. Since
  // it will be working with URLs, this method here needs to shift to
  // working with URLs too.
  //
  // We therefore need to map the URI to a URL. This is done by the stream
  // wrapper manager, which handles streams for public and private files.
  $mgr = \Drupal::service('stream_wrapper_manager');
  $scheme = \Drupal::service('file_system')->uriScheme($uri);
  if ($scheme === FALSE) {
    $scheme = 'public';
  }

  // Get the scheme's stream and map the URI to a URL. The returned URL
  // is complete, including http://hostname/path.
  //
  // This works for almost any scheme, including Drupal core's "public" and
  // "private" schemes, as well as any extension schemes that might reference
  // files in an external file system (e.g. Amazon).
  $stream = $mgr->getViaScheme($scheme);
  if ($stream === FALSE) {
    // Fail. Scheme is not recognized. The URI is malformed and there is
    // nothing more we can do.
    return;
  }

  $streamDirectory = $stream->getDirectoryPath();

  $stream->setUri($uri);
  try {
    $url = $stream->getExternalUrl();
  }
  catch (\Exception $e) {
    // Fail. The stream does not support conversion of URIs to external URLs.
    // The Drupal core TranslationsStream class is an example. Such a stream
    // is unlikely to ever be used with this method and contain this module's
    // FILE_DIRECTORY name, but if it does there's nothing more we can do.
    return;
  }

  // Parse the URL and get the path out of it. The path now includes any
  // path prefixes or changes added by the scheme's stream wrapper.
  //
  // For instance, the Drupal core "private" stream (class PrivateStream)
  // adds a prefix from the 'system.private_file_download' route, which
  // is always "/system/files".
  //
  // The Drupal core "public" stream (class PublicStream) does not add a
  // prefix.  The URI's file path is presumed to be the path to a file directly
  // deliverable by the web server.
  $parsedUrl = parse_url($url);
  if ($parsedUrl === FALSE) {
    // Fail. The URL created by the stream is malformed and cannot be parsed.
    // Something is wrong with the stream wrapper. Nothing more we can do.
    return;
  }

  if (isset($parsedUrl['path']) === FALSE) {
    // Fail. The URL created by the stream does not have a path (i.e. it
    // is something like "http://host/"). Since it is the path that we need
    // to indicate a specific file, this doesn't make sense. The URL is
    // therefore malformed and there is nothing more we can do.
    return;
  }

  if (isset($parsedUrl['user']) === TRUE ||
      isset($parsedUrl['pass']) === TRUE) {
    // Fail. The stream wrapper has added a user name and password to the URL.
    // This module has no mechanism to forward this to its file download
    // controller. This is a very unlikely situation, and one not supported
    // by Drupal core's "public" and "private" streams, but if it happens
    // there is nothing we can do.
    return;
  }

  $path = urldecode($parsedUrl['path']);

  // Check again that the returned path still references this module's
  // file directory. It certainly should.
  $pos = strpos($path, Constants::FILE_DIRECTORY);
  if ($pos === FALSE) {
    // Fail. The module's file directory name is not in the path. Ignore it.
    return;
  }

  //
  // Get prefix
  // ----------
  // The prefix is the part of the URL path that precedes the module's
  // file directory name. There may be nothing.
  if ($pos === 0) {
    // No prefix. The path starts with FILE_DIRECTORY
    //
    // This only occurs for the "public" file system where external URLs
    // indicate site-relative paths to files that can be delivered directly
    // by the web server.
    $prefix = FALSE;
  }
  else {
    // There is a prefix.
    //
    // This occurs in two well-known cases (and there may be others):
    //
    // - The URL is for a file in the "private" file system, so the path has
    //   a prefix of "/system/files". The web server sends such paths to
    //   Drupal, which replaces "/system/files" with the full path to the
    //   local private files directory, then returns that file.
    //
    // - The URL is for a derived file in the "public" or "private" file
    //   system. This is the case for the Drupal core Image module, which
    //   adds the "/styles/STYLENAME/SCHEME" prefix to refer to a styled
    //   image derived from an image file referred to by the rest of the
    //   file path. If the styled image doesn't exist yet then the Image
    //   module's file download controller generates the image. For the
    //   "public" file system, once the styled image is created it is
    //   thereafter delivered directly by the web server. For the "private"
    //   file system, this prefixed URL is always used and needs to go to
    //   the Image module's file download controller to get the image and
    //   return it.
    //
    // The prefix is important, then, because it indicates how the file
    // should be retrieved. For this module, all file downloads are routed
    // to a custom file download controller in order to do access controls.
    // The prefix, then, tells that download controller what to do after
    // checking for access - is the file delivered directly or is another
    // download controller invoked (e.g. Image's)?
    $prefix = substr($path, 0, $pos);
    $prefix = trim($prefix, '/');
    if ($scheme === 'public' && $prefix === $streamDirectory) {
      // Prefix is redundant.
      $prefix = FALSE;
    }
    elseif ($scheme !== 'public' && $prefix === 'system/files') {
      // Prefix is redundant.
      $prefix = FALSE;
    }
    else {
      $prefix = '/' . $prefix;
    }
  }

  //
  // Get entity ID
  // -------------
  // The part of the path after the FILE_DIRECTORY contains a sequence of
  // subdirectory names, and a final file name, that form a zero-padded
  // 20-digit number for the File object entity ID.
  $filePath = substr($path, $pos);
  $entityId = FileUtilities::getFileEntityId($filePath);
  if ($entityId === FALSE) {
    // Fail. The rest of the file path *did not* contain an entity ID.
    // This should not happen and indicates that the original URI was
    // malformed. There's nothing more we can do.
    return;
  }

  //
  // Return URI for file download controller
  // ---------------------------------------
  // Return a path that leads to the module's file download controller,
  // which handles access control. The path has the form
  // "ROUTE_DOWNLOADFILE/ENTITY_ID?prefix=PREFIX", where:
  //
  // - ROUTE_DOWNLOADFILE is the path for the route to the file download
  //   controller.
  //
  // - ENTITY_ID is the File entity ID parsed out of the path above.
  //
  // - PREFIX (if present) is the portion of the URL preceding this module's
  //   FILE_DIRECTORY.
  //
  // It is possible that URL created earlier from the URI also has query
  // and fragment components.
  //
  // Build query components.
  $query = '';
  if (isset($parsedUrl['query']) === TRUE) {
    $query = '?' . urldecode($parsedUrl['query']);
  }

  if ($prefix !== FALSE) {
    // The path prefix is defined by whatever module added the prefix.
    // The Drupal core Image module, for instance, adds one of these
    // two prefixes:
    // - /styles/STYLENAME/SCHEME for public files.
    // - /system/files/styles/STYLENAME/SCHEME for private files.
    //
    // Other modules may have their own prefixes of arbitrary length
    // and structure. We need to pass the prefix on to the file download
    // controller.
    if (empty($query) === TRUE) {
      $query = '?';
    }
    else {
      $query .= '&';
    }

    $query .= Constants::ROUTE_DOWNLOADFILE_PREFIX . '=' . $prefix;
  }

  // Build fragment component, if any.
  $fragment = '';
  if (isset($parsedUrl['fragment']) === TRUE) {
    $fragment = '#' . urldecode($parsedUrl['fragment']);
  }

  // Build the URL, without query and fragment.
  $url = Url::fromRoute(
    Constants::ROUTE_DOWNLOADFILE,
    [
      'file' => $entityId,
    ],
    [
      'absolute' => TRUE,
    ]);

  // The URL created above can be converted to something to return in two
  // ways:
  // - getInternalPath() returns the path part only.
  // - toUriString() returns a URI with "route:ROUTENAME" in front.
  //
  // The latter URI, however, causes general confusion in later Drupal
  // processing and the route never gets turned into a full URL for a page,
  // and therefore never causes the browser to invoke the URL. Fail.
  //
  // We therefore need to use getInternalPath(). Except the path does not
  // include any query or fragment parts. This is added here.
  $uri = $url->getInternalPath() . $query . $fragment;
}

/**
 * Implements hook_file_download().
 *
 * This hook is supposed to be called by Drupal core or custom download
 * controllers when a file is requested from the "private" file system.
 * This is known to be true for the following cases:
 *
 * - Drupal core's FileDownloadController class in the "system" module.
 * - Drupal core's ImageStyleDownloadController class in the "image" module.
 * - This module's FileDownload class.
 *
 * Drupal core's FileDownloadController invokes this hook for any valid URI
 * scheme (i.e. "private", "public", others). ImageStyleDownloadController
 * and this module's FileDownload only invoke this hook for the "private"
 * file system.
 *
 * This hook is expected to respond in one of these ways:
 *
 * - Return NULL to indicate the file URI is not relevant to this function.
 *
 * - Return -1 to indicate that the file URI is relevant and that access
 *   is denied.
 *
 * - Return an associative array of HTTP header key-value pairs for use if
 *   the file is returned to a browser.
 *
 * If none of the installed hooks recognize a file and respond with HTTP
 * headers, then the Drupal core FileDownloadController responds with an
 * access denied.
 *
 * @see \Drupal\foldershare\Controller\FileDownload::download()
 * @see foldershare_file_url_alter()
 */
function foldershare_file_download($uri) {
  //
  // Map URI to File
  // ---------------
  // Find the File object associated with the URI, if there is one. Since
  // this hook is called for every file to download (including CSS and JS
  // files when using a private file system), it is important that it
  // run quickly. We therefore use two steps:
  //
  // - Step 1 checks if the file URI contains this module's FILE_DIRECTORY.
  //   If it does not, then it cannot be a file we need to handle.
  //
  // - Step 2 queries the database for the File entity with this URI. This
  //   takes longer but yields the File entity we need to handle.
  $pos = strpos($uri, Constants::FILE_DIRECTORY);
  if ($pos === FALSE) {
    // Fail. The module's file directory name is not in the path. Ignore it.
    return NULL;
  }

  $files = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->loadByProperties(['uri' => $uri]);

  if (count($files) === 0) {
    // Fail. No file found. This is common and occurs, for instance, when the
    // Drupal core Image module invokes this hook on a serived image
    // (e.g. one with an image style applied, such as for thumbnails). In
    // such cases, the URI is for a stored file, but not for a File entity.
    //
    // If there is no File entity, then this URI cannot be for a file under
    // management by this module. Do nothing.
    return NULL;
  }

  // Some database servers may use a case-insensitive comparison, so the
  // query may have found multiple matches. Find the correct one, if any.
  foreach ($files as $item) {
    if ($item->getFileUri() === $uri) {
      $file = $item;
      break;
    }
  }

  if (isset($file) === FALSE) {
    // Fail. No file found. While the database query found a match, it did so
    // on the wrong case. There isn't an exact match for the given URI and
    // therefore there is no associated File entity and this URI is not for
    // a file under management by this module. Do nothing.
    return NULL;
  }

  //
  // Check permission
  // ----------------
  // Look for the FolderShare entity that wraps this File entity. This issues
  // a database query.
  $wrapperId = FolderShare::findFileWrapperId($file);
  if ($wrapperId === FALSE) {
    // Fail. There is none. While the URI is for a File entity, the File
    // entity is not one referenced by a FolderShare entity and it is,
    // therefore, not a file under management by this module. Do nothing.
    return NULL;
  }

  // Load the wrapper entity. This issues a database query.
  $wrapper = FolderShare::load($wrapperId);
  if ($wrapper === NULL) {
    // Fail. Something has become corrupted! The above query found the ID of a
    // FolderShare entity that wraps the file, but now when the entity is
    // loaded, the load fails. This can only happen if the entity has been
    // deleted between the previous call and this one. Access denied.
    return (-1);
  }

  // Check for view access to the FolderShare entity that manages this file.
  if ($wrapper->access('view') === FALSE) {
    // Fail. Access denied.
    return (-1);
  }

  //
  // Return headers
  // --------------
  // The URI references a File entity under management by this module,
  // and the user has 'view' access. Return headers that include the
  // user-visible name for the file, it's MIME type, etc.
  //
  // Including the user-visible file name in the header is essential.
  // The file name in the URI is an internal numeric name (see
  // FileUtilities::getFileUri()). If the user tries to save the delivered
  // file, they'll get that numeric name instead of the user-visible name
  // if we didn't include the correct name in the HTTP header.
  return [
    // Use the File object's MIME type.
    'Content-Type'        => Unicode::mimeHeaderEncode($file->getMimeType()),

    // Use the human-visible file name.
    'Content-Disposition' => 'filename="' . $file->getFilename() . '"',

    // Use the saved file size, in bytes.
    'Content-Length'      => $file->getSize(),

    // Don't cache the file because permissions and content may change.
    'Pragma'              => 'no-cache',
    'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
    'Expires'             => '0',
    'Accept-Ranges'       => 'bytes',
  ];
}
