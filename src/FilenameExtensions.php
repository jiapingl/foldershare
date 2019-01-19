<?php

namespace Drupal\foldershare;

/**
 * Defines groups of well-known filename extensions.
 *
 * This class defines constants containing groups of well-known filename
 * extensions, such as those for text files, image files, video files, etc.
 * These lists of extensions may be used to initialized module settings
 * that filter content based upon the file type. They provide a more
 * explicit indication of a file's broad file type than do MIME types.
 *
 * <B>Warning:</B> This class is strictly internal to the FolderShare
 * module. The class's existance, name, and content may change from
 * release to release without any promise of backwards compatability.
 *
 * @ingroup foldershare
 */
class FilenameExtensions {

  /*---------------------------------------------------------------------
   *
   * Archives.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for web archives and compression.
   */
  const ARCHIVE_WEB = [
    // General.
    'jar', 'zip',
  ];

  /**
   * File extensions used for other archive and compression types.
   */
  const ARCHIVE_OTHER = [
    // Windows.
    'cab',
    // MacOS.
    'dmg', 'mpkg', 'sit', 'sitx',
    // General Linux and MacOS.
    'a', 'cpio', 'bcpio', 'rar', 'tar', 'gtar', 'tgz', 'tbz2',
    'shar', 'gz', 'z', 'bz', 'bz2',
    // Android.
    'apk',
    // General, but not web.
    'iso', 'sit', 'sitx', 'phar',
    // Debian.
    'deb',
  ];

  /*---------------------------------------------------------------------
   *
   * Audio.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for web audio.
   */
  const AUDIO_WEB = [
    // MPEG.
    'mp3', 'm3u', 'm4a', 'm4p',
  ];

  /**
   * File extensions used for other audio types.
   */
  const AUDIO_OTHER = [
    // Windows.
    'wma', 'wav',
    // Everybody else.
    'aif', 'aiff', 'flac', 'au', 'aac', 'ogg', 'oga', 'mogg',
  ];

  /*---------------------------------------------------------------------
   *
   * Data.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for text-based web data.
   */
  const DATA_WEB_TEXT = [
    // Web page styles.
    'css', 'less', 'sass', 'scss', 'xsl', 'xsd',
    // Web data.
    'json', 'xml', 'rdf',
  ];

  /**
   * File extensions used for web binary legacy data.
   */
  const DATA_WEB_LEGACY = [
    // Adobe Flash.
    'swf', 'f4v', 'flv',
    // Adobe XML forms and data.
    'xdp', 'xfdf',
  ];

  /**
   * File extensions used for other text-based data types.
   */
  const DATA_TEXT = [
    // Drupal et al.
    'yaml', 'yml', 'twig', 'info',
    // Unspecified format.
    'asc', 'ascii', 'dat', 'data', 'text',
    // Comma- and tab-delimited.
    'csv', 'tsv',
    // Calendar.
    'ics',
    // Google.
    'kml',
    // Other.
    'texinfo',
  ];

  /**
   * File extensions used for other binary data types.
   */
  const DATA_BINARY = [
    // Science formats.
    'hdf', 'hdf5', 'h5', 'nc', 'fits', 'daq', 'fig',
    // Matlab.
    'mat', 'mn',
    // E-books.
    'azw',
    // Google.
    'kmz',
    // Generic.
    'bin', 'data',
  ];

  /*---------------------------------------------------------------------
   *
   * Developer.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for tools and scripts.
   */
  const DEVELOPER_TOOLS = [
    'make', 'cmake', 'proj', 'ini', 'config',
    'sh', 'csh', 'bash', 'bat',
  ];

  /**
   * File extensions used for programming languages.
   */
  const DEVELOPER_LANGUAGES = [
    // C, C++, C#.
    'c', 'c++', 'cp', 'cpp', 'cxx', 'cs', 'csx',
    'h', 'hpp', 'inc', 'include',
    // Drupal PHP.
    'module', 'install',
    // Everybody else.
    'f', 'p', 'pl', 'prl', 'perl', 'pm', 'py', 'python', 'pyc',
    'php', 'java', 'js', 'jsp', 'asp', 'aspx',
    'swift', 'r', 's', 'm', 'mlv',
    'cgi', 'tcl',
  ];

  /**
   * File extensions used for programs or intermediate data.
   */
  const DEVELOPER_PROGRAMS = [
    // Windows.
    'exe',
    // Linux et al.
    'obj',
    // Java.
    'class', 'ser', 'jnlp',
  ];

  /*---------------------------------------------------------------------
   *
   * Graphics.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for web graphics.
   */
  const GRAPHICS_WEB = [
    'svg',
  ];

  /**
   * File extensions used for other graphics types.
   */
  const GRAPHICS_OTHER = [
    // Printing.
    'ps', 'eps', 'ppd',
    // Vendor neutral.
    'dae', 'stl',
    // Vendor specific.
    'dwf', 'dxf', 'blend', '3ds',
  ];

  /**
   * File extensions used by legacy graphics types.
   */
  const GRAPHICS_LEGACY = [
    'cgm', 'igs',
  ];

  /*---------------------------------------------------------------------
   *
   * Images.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for web images.
   */
  const IMAGES_WEB = [
    // JPEG and JPEG2000.
    'jpg', 'jpeg', 'jp2', 'j2k', 'jpf', 'jpx', 'jpm',
    // Other web.
    'png', 'gif', 'webp',
  ];

  /**
   * File extensions used for other image types.
   */
  const IMAGES_OTHER = [
    'bmp', 'png', 'gif', 'tif', 'tiff', 'fits', 'tga',
    'ras', 'ico', 'ppm', 'pgm', 'pbm', 'pnm', 'pcx', 'psd', 'pic',
    'xbm', 'xpm', 'xwd',
  ];

  /*---------------------------------------------------------------------
   *
   * Office.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used by newer versions of Microsoft's Office tools.
   */
  const OFFICE_MICROSOFT = [
    // Word.
    'docx', 'docm', 'dotx', 'dotm', 'docb',
    // Excel.
    'xlsx', 'xlsm', 'xltx', 'xltm',
    // Powerpoint.
    'pptx', 'pptm', 'potx', 'potm', 'ppam', 'ppsx', 'ppsm',
    'sldx', 'sldm',
    // Access.
    'adn', 'accdb', 'accdr', 'accdt', 'accda', 'mdw', 'accde',
    'mam', 'maq', 'mar', 'mat', 'maf', 'laccdb',
  ];

  /**
   * File extensions used by older versions of Microsoft's Office tools.
   */
  const OFFICE_MICROSOFT_LEGACY = [
    // Word.
    'doc', 'dot', 'wbk',
    // Excel.
    'xls', 'xlt', 'xlm',
    // Powerpoint.
    'ppt', 'pot', 'pps',
    // Access.
    'ade', 'adp', 'mdb', 'cdb', 'mda', 'mdn', 'mdt', 'mdf',
    'mde', 'ldb',
  ];

  /**
   * File extensions used by other office tools.
   */
  const OFFICE_OTHER = [
    // Wordperfect.
    'wpd',
    // KDE.
    'karbon', 'chrt', 'kfo', 'flw', 'kon', 'kpr', 'ksp', 'kwd',
    // OpenDocument.
    'odc', 'otc', 'odb', 'odf', 'odft', 'odg', 'otg', 'odi',
    'oti', 'odp', 'otp', 'ods', 'ots', 'odt', 'odm', 'ott',
    // Open office.
    'sxc', 'stc', 'sxd', 'std', 'sxi', 'sti', 'sxm', 'sxw',
    'sxg', 'stw', 'oxt',
    // Star office.
    'sdc', 'sda', 'sdd', 'smf', 'sdw', 'sgl',
  ];

  /*---------------------------------------------------------------------
   *
   * Text.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for plain text.
   */
  const TEXT_PLAIN = [
    'readme', 'txt', 'text', '1st',
  ];

  /**
   * File extensions used for formatted text.
   */
  const TEXT_FORMATTED = [
    'man', 'rtf', 'rtx', 'tex', 'ltx', 'latex', 'pdf', 'md',
  ];

  /**
   * File extensions used for web text.
   */
  const TEXT_WEB = [
    'htm', 'html', 'xhtml', 'rss', 'dtd',
  ];

  /*---------------------------------------------------------------------
   *
   * Video.
   *
   *---------------------------------------------------------------------*/

  /**
   * File extensions used for web video.
   */
  const VIDEO_WEB = [
    // MPEG.
    'mp4', 'm4v', 'mpg', 'mpv', 'mpeg',
    // Google.
    'webm',
  ];

  /**
   * File extensions used for other video types.
   */
  const VIDEO_OTHER = [
    // Windows.
    'avi', 'wmv',
    // Mac.
    'mov', 'qt',
    // Everybody else.
    'mj2', 'mkv', 'ogv',
  ];

  /*---------------------------------------------------------------------
   *
   * Methods.
   *
   *---------------------------------------------------------------------*/

  /**
   * Returns an array of all filename extensions defined here.
   *
   * @return string[]
   *   Returns an array of filename extensions, without leading dots.
   *   Extensions are unique and sorted alphabetically.
   */
  public static function getAll() {
    $merged = array_unique(array_merge(
      self::ARCHIVE_WEB,
      self::ARCHIVE_OTHER,
      self::AUDIO_WEB,
      self::AUDIO_OTHER,
      self::DATA_WEB_TEXT,
      self::DATA_WEB_LEGACY,
      self::DATA_TEXT,
      self::DATA_BINARY,
      self::DEVELOPER_TOOLS,
      self::DEVELOPER_LANGUAGES,
      self::DEVELOPER_PROGRAMS,
      self::GRAPHICS_WEB,
      self::GRAPHICS_OTHER,
      self::GRAPHICS_LEGACY,
      self::IMAGES_WEB,
      self::IMAGES_OTHER,
      self::OFFICE_MICROSOFT,
      self::OFFICE_MICROSOFT_LEGACY,
      self::OFFICE_OTHER,
      self::TEXT_PLAIN,
      self::TEXT_FORMATTED,
      self::TEXT_WEB,
      self::VIDEO_WEB,
      self::VIDEO_OTHER));
    natsort($merged);
    return $merged;
  }

  /**
   * Returns an array of all audio filename extensions defined here.
   *
   * @return string[]
   *   Returns an array of filename extensions, without leading dots.
   *   Extensions are unique and sorted alphabetically.
   */
  public static function getAudio() {
    $merged = array_unique(array_merge(
      self::AUDIO_WEB,
      self::AUDIO_OTHER));
    natsort($merged);
    return $merged;
  }

  /**
   * Returns an array of all image filename extensions defined here.
   *
   * @return string[]
   *   Returns an array of filename extensions, without leading dots.
   *   Extensions are unique and sorted alphabetically.
   */
  public static function getImage() {
    $merged = array_unique(array_merge(
      self::IMAGES_WEB,
      self::IMAGES_OTHER));
    natsort($merged);
    return $merged;
  }

  /**
   * Returns an array of all text-based filename extensions defined here.
   *
   * @return string[]
   *   Returns an array of filename extensions, without leading dots.
   *   Extensions are unique and sorted alphabetically.
   */
  public static function getText() {
    $merged = array_unique(array_merge(
      self::DATA_WEB_TEXT,
      self::DATA_TEXT,
      self::DEVELOPER_TOOLS,
      self::DEVELOPER_LANGUAGES,
      self::TEXT_PLAIN,
      self::TEXT_FORMATTED,
      self::TEXT_WEB));
    natsort($merged);
    return $merged;
  }

  /**
   * Returns an array of all video filename extensions defined here.
   *
   * @return string[]
   *   Returns an array of filename extensions, without leading dots.
   *   Extensions are unique and sorted alphabetically.
   */
  public static function getVideo() {
    $merged = array_unique(array_merge(
      self::VIDEO_WEB,
      self::VIDEO_OTHER));
    natsort($merged);
    return $merged;
  }

}
