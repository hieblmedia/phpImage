<?php
/**
 * phpImage - A PHP image processor
 *
 * This is a wrapper for phpThumb from gxdlabs
 * (https://github.com/masterexploder/PHPThumb)
 *
 * All rights reserved.
 *
 * -----------------------------------------------------------------------------
 *
 * @version    Id: $Id$
 *
 * @package    PhpImage
 *
 * @author     Reinhard Hiebl <reinhard@hieblmedia.com>
 * @copyright  Copyright (C) 2011 - 2012, HieblMedia (Reinhard Hiebl)
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       http://www.hieblmedia.de
 *
 */

// Import library dependencies
require_once dirname(__FILE__) . '/phpThumb/ThumbLib.inc.php';

/**
 * phpImage class
 *
 * @package  PhpImage
 * @since    1.0.0
 */
class PhpImage
{
	/**
	 * The url mode.
	 *
	 * Determines how the url is generated.
	 * This is dependent on your application environment and base href meta tag
	 *
	 * For example: PhpImage::$urlMode = 'base'
	 *
	 * Valid values are:
	 *  'base':     Relative URL starting from document root
	 *  'root':     Relative URL starting from the main running PHP script
	 *  'absolute': Absolute URL
	 *
	 * @var string  The url mode
	 */
	public static $urlMode = 'base';

	/**
	 * Protocol-Less URL.
	 *
	 * Only affected by: PhpImage::$urlMode = 'absolute'
	 *
	 * Determines the URL output is protocol less.
	 * This means without the scheme like http/https (e.g. '//domain.tld/path/to/image.png')
	 * @see http://tools.ietf.org/html/rfc3986#section-4.2
	 *
	 * @var boolean  True or false
	 */
	public static $protocolLess = true;

	/**
	 * Static CDN URL (prefixed)
	 * You can set this if you have a special or alternative URL
	 * or special CDN domain (e.g. CDN with static long-term caching)
	 *
	 * If this is set the settings $urlMode and $protocolLess are ignored.
	 *
	 * @var string  The Content Delivery Network URL (prefixed)
	 */
	public static $cdnUrl = '';

	/**
	 * Absolute path to the cache folder.
	 *
	 * If nothing set './PhpImage/cache' will be used.
	 * For example: PhpImage::$cachePath = 'path/of/cache'
	 *
	 * @var   string  The cache path
	 */
	public static $cachePath = '';

	/**
	 * The depth of the cache directory (min=1, max=10)
	 *
	 * This is to prevent disk fragmentation for more performance
	 *
	 * Change this value only if you have performance issues on your disk
	 * For example: PhpImage::$cacheDepth = 2
	 *
	 * Simple basic math formula: ceil(count of images / 10000)
	 *
	 * @var   int  The cache dpeth
	 */
	public static $cacheDepth = 2;

	/**
	 * Absolute path from the main script root.
	 *
	 * Usually nothing must be placed here.
	 *
	 * By default the directory of $_SERVER['SCRIPT_FILENAME'] is used.
	 * For example: PhpImage::$rootPath = '/var/www/web/space/htdocs/cms'
	 *
	 * @var   string  The root path
	 */
	public static $rootPath = '';

	/**
	 * @var   string  Image source
	 */
	protected $imgSrc = '';

	/**
	 * @var   string  New image source
	 */
	protected $newSrc = '';

	/**
	 * @var   string  Image ALT attribute text
	 */
	protected $imgAlt = '';

	/**
	 * @var   string  Image Format (png,jpg,gif,bmp)
	 */
	protected $imgFormat = '';

	/**
	 * @var   string   Additional attributes for <img />
	 */
	protected $additionalAttributes = '';

	/**
	 * PhpThumbFactory object
	 *
	 * @var GdThumb
	 */
	protected $thumbnailer = null;

	/**
	 * @var   array  Call history for methods
	 */
	protected $callHistory = array();

	/**
	 * @var   array  Error messages
	 */
	protected $errors = array();

	/**
	 * Class constructor
	 *
	 * @param   string  $src      Absolute or Relative Image source path
	 * @param   string  $alt      Text for the alt attribute <img alt="..." />
	 * @param   array   $options  PhpThumbFactory options
	 * @param   string  $attribs  Additional attributes for <img [attribute_name="attribute_value"] />
	 */
	public function __construct($src, $alt='', $options=array(), $attribs='')
	{
		// Initialise the SERVER URI
		PhpImageSimpleURI::getInstance();

		// Set defaults and validate settings
		$this->imgSrc = (string) $this->_getImagePath($src);
		$this->newSrc = (string) $this->imgSrc;
		$this->imgAlt = (string) $alt;
		$this->imgFormat = strtolower(substr($src, strrpos($src, '.') + 1));
		$this->additionalAttributes = (string) $attribs;

		$this->_thumbOptions = $options;

		$this->_cachePath = self::$cachePath ? self::$cachePath : dirname(__FILE__) . '/cache';
		$this->_cachePath = $this->cleanPath($this->_cachePath);

		$this->_cacheSubdirs = is_int(self::$cacheDepth) ? self::$cacheDepth : 2;
		if ($this->_cacheSubdirs < 1)
		{
			$this->_cacheSubdirs = 1;
		}
		elseif ($this->_cacheSubdirs > 10)
		{
			$this->_cacheSubdirs = 10;
		}

		$this->_rootPath = self::$rootPath ? self::$rootPath : dirname($_SERVER['SCRIPT_FILENAME']);
		$this->_rootPath = $this->cleanPath($this->_rootPath);

		if (self::$cdnUrl && substr(self::$cdnUrl, -1) != '/')
		{
			self::$cdnUrl .= '/';
		}
	}

	/**
	 * Instance/Reference of PhpThumbFactory::create
	 *
	 * @param   boolean  $dummy  If true a dummy image (blank.gif) will be loaded
	 *                           (e.g. to check available methods, error handling or fallback)
	 *
	 * @access protected
	 * @return GdThumb
	 */
	protected function &getThumbnailer($dummy=false)
	{
		static $dummyThumbnailer;
		static $blankImageStream;

		if ($dummy)
		{
			if (!$dummyThumbnailer)
			{
				try
				{
					// Create a 1x1 blank.gif image as dummy
					if (!$blankImageStream)
					{
						$im = imagecreate(1, 1);
						@ob_start();
						imagegif($im);
						$blankImageStream = ob_get_contents();
						@ob_end_clean();
						imagedestroy($im);
					}

					$dummyThumbnailer = $this->_getPhpThumbFactory($blankImageStream, $this->_thumbOptions, true);
				}
				catch (Exception $e)
				{
					$dummyThumbnailer = null;
					$this->addError($e->getMessage());
				}
			}

			return $dummyThumbnailer;
		}

		if (!$this->thumbnailer)
		{
			try
			{
				$this->thumbnailer = $this->_getPhpThumbFactory($this->imgSrc, $this->_thumbOptions);
			}
			catch (Exception $e)
			{
				$this->thumbnailer = null;
				$this->addError($e->getMessage());
			}
		}

		return $this->thumbnailer;
	}

	/**
	 * Create the Thumbnail object PhpThumbFactory::create
	 *
	 * @param   string   $src        Absolute image path
	 * @param   array    $options    PhpThumbFactory options
	 * @param   boolean  $srcIsData  Determine if $src is a file or contents(stream) of a file
	 *
	 * @access private
	 * @return PhpThumbFactory::create
	 */
	private function _getPhpThumbFactory($src, $options=array(), $srcIsData=false)
	{
		// Output buffer fix (do not add contents of current buffers)
		// Using @ob_start(); and @ob_end_clean();
		@ob_start();
		$factory = PhpThumbFactory::create($src, $options, $srcIsData);
		@ob_end_clean();

		return $factory;
	}

	/**
	 * Create the current Image with all called modifications
	 *
	 * @access protected
	 * @return self, null on error, null when no changes like resize are made
	 */
	protected function _createImageFile()
	{
		if ($this->getError())
		{
			return null;
		}

		// Check the image requires modifications
		if (empty($this->callHistory))
		{
			$this->getThumbnailer();
			return null;
		}

		$srcHash = md5($this->imgSrc);
		$cachePath = $this->_cachePath;
		$directoryDeptPath = '';
		for ($i = 0; $i < self::$cacheDepth; $i++)
		{
			$directoryDeptPath .= '/' . substr($srcHash, 0, $i + 1);
		}
		$cachePath = $this->cleanPath($cachePath . '/' . $directoryDeptPath);

		// Check cache path exists
		if (!is_dir($cachePath))
		{
			$mask = @umask(0);
			if (!@mkdir($cachePath, 0755, true))
			{
				$this->addError('Could not create the cache directory. Please check you have write permissions: ' . $cachePath);
			}
			@umask($mask);
		}

		// Check the cache path is writable
		if (!$this->_isWritable($cachePath))
		{
			$this->addError('Image cache for ' . basename($this->imgSrc) . ' is not writable. Please check the permissions: ' . $cachePath);

			return null;
		}

		// Get cache file name
		$sourceFilename = basename($this->imgSrc);
		$filePrefix = preg_replace('#\.[^.]*$#', '', $sourceFilename); /* Strip the extension from filename */
		$cacheFilename = $filePrefix . '.' . md5($this->imgSrc . serialize($this->callHistory)) . '.' . strtolower($this->imgFormat);
		$cacheFile = $this->cleanPath($cachePath . '/' . $cacheFilename);

		// Cache file time check compared with source
		$sourceFileIsModified = false;
		if (is_file($cacheFile))
		{
			$lastModifiedSource = filemtime($this->imgSrc);
			$lastModifiedCache = filemtime($cacheFile);

			if (($lastModifiedSource && $lastModifiedCache) && $lastModifiedSource > $lastModifiedCache)
			{
				$sourceFileIsModified = true;
			}
		}

		// If cache file does not exists yet or source is modified,
		// execute thumb methods and (re-)save the image
		if ($sourceFileIsModified || !is_file($cacheFile))
		{
			$thumb = $this->getThumbnailer();
			if (!$thumb)
			{
				return $this;
			}

			foreach ($this->callHistory as $_call)
			{
				$_method = isset($_call[0]) ? $_call[0] : '';
				$_arguments = isset($_call[1]) ? $_call[1] : array();

				if ($_method && is_array($_arguments))
				{
					call_user_func_array(array($thumb, $_method), $_arguments);
				}
			}

			// Save the new image into the cache path
			$thumb->save($cacheFile);
		}
		else
		{
			// Create temporary thumbnail image from cache file if exists
			try
			{
				$this->thumbnailer = $this->_getPhpThumbFactory($cacheFile, $this->_thumbOptions);
			}
			catch (Exception $e)
			{
				$this->thumbnailer = null;
				$this->addError($e->getMessage());
			}
		}

		// Change cache src
		$this->newSrc = $cacheFile;

		return $this;
	}

	/**
	 * Convert absolute path to an relative URL
	 *
	 * @param   string  $path  Absolute path
	 *
	 * @access protected
	 * @return string Relative URL
	 */
	protected function _pathToUrl($path)
	{
		if (strpos($path, $this->_rootPath) !== false)
		{
			if (self::$cdnUrl)
			{
				$base = self::$cdnUrl;
			}
			else
			{
				switch (self::$urlMode)
				{
					case 'absolute':
						$base = PhpImageSimpleURI::root(false);

						if (self::$protocolLess)
						{
							$tmp = explode('://', $base);
							$base = isset($tmp[1]) ? '//' . $tmp[1] : $base;
						}
						break;

					case 'root':
						$base = PhpImageSimpleURI::root(true);
						break;

					default:
					case 'base':
						$base = PhpImageSimpleURI::base(true);
						break;
				}
			}

			$path = str_replace($this->cleanPath($this->_rootPath . '/'), '', $path);
			$path = $base . $this->cleanPath($path, '/');

		}

		return $path;
	}

	/**
	 * Get the image path from source
	 *
	 * @param   string  $src  Image source
	 *
	 * @access protected
	 * @return string Absolute filesystem path
	 */
	protected function _getImagePath($src='')
	{
		$src = $this->cleanPath($src);

		if (!$src)
		{
			return '';
		}

		if (PhpImageSimpleURI::isInternal($src) && !is_dir($src) && !is_file($src))
		{
			// For internal images use absolute path to prevent http authorisation
			$base = PhpImageSimpleURI::root(false);
			$baseRel = PhpImageSimpleURI::root(true) . '/';

			// Remove base path
			$search = array(
				'#^' . $base . '#',
				'#^' . $baseRel . '#'
			);
			$src = preg_replace($search, '', $src);

			// Set path and clean
			$path = $this->cleanPath($this->_rootPath . '/' . $src);

			if (is_file($path))
			{
				$src = $path;
			}
		}

		return $src;
	}

	/**
	 * Set the Image Output Format
	 *
	 * @param   string  $format  (png, gif or jpg)
	 *
	 * @access public
	 * @return self
	 */
	public function setFormat($format)
	{
		$format = strtolower($format);
		$validFormats = array('gif', 'jpg', 'png');

		if (in_array($format, $validFormats))
		{
			$this->imgFormat = $format;
			$this->__call('setFormat', array(strtoupper($format)));
		}
		else
		{
			$this->addError('Changed Image Format not supported: ' . $format . ' (use: png, gif or jpg)');
		}

		return $this;
	}

	/**
	 * Generate an object PhpImageSimpleObject output of the current image
	 *
	 * @access public
	 * @return PhpImageSimpleObject
	 */
	public function toObject()
	{
		$obj = new PhpImageSimpleObject;

		$this->_createImageFile();
		$thumb = $this->thumbnailer;

		if ($this->getError() || !$thumb)
		{
			return $obj;
		}

		// Set src and alt attribute
		$src = $this->_pathToUrl($this->newSrc);
		$alt = trim($this->imgAlt);

		// Set width and height attribute
		$width = false;
		$height = false;

		// Try to get the current size
		if (($_size = $thumb->getCurrentDimensions()))
		{
			$width = (isset($_size['width']) ? $_size['width'] : false);
			$height = (isset($_size['height']) ? $_size['height'] : false);
		}

		// Prepare additional img attributes
		$_attributes = trim($this->additionalAttributes);

		$obj->set('src', $src);
		$obj->set('width', $width);
		$obj->set('height', $height);
		$obj->set('alt', $alt);
		$obj->set('attributes', $_attributes);

		return $obj;
	}

	/**
	 * Generate an array output of the current image
	 *
	 * @access public
	 * @return array required image properties
	 */
	public function toArray()
	{
		return $this->toObject()->toArray();
	}

	/**
	 * Generate html output of the current image
	 *
	 * @access public
	 * @return string <img /> HTML
	 */
	public function toHtml()
	{
		$obj = $this->toObject();

		if (($_error = $this->getError()))
		{
			return '<span style="background:#000;color:#f00;">' . $_error . '</span>';
		}

		$src = $obj->get('src', '');
		$alt = $obj->get('alt', '');

		// Prepare width and height
		$width = $obj->get('width', '');
		$height = $obj->get('height', '');

		if ($width != '')
		{
			$width = ' width="' . $width . '"';
		}

		if ($height != '')
		{
			$height = ' height="' . $height . '"';
		}

		// Prepare additional img attributes
		$attribs = $obj->get('attributes', '');

		if ($attribs)
		{
			$attribs = ' ' . $attribs;
		}

		$html = '<img src="' . $src . '"' . $width . $height . ' alt="' . $alt . '"' . $attribs . ' />';
		return $html;
	}

	/**
	 * Generate html output of the current image
	 * Wrapper for $this->toHtml
	 *
	 * @access public
	 * @return string <img /> HTML
	 */
	public function toString()
	{
		return $this->toHtml();
	}

	/**
	 * Strip additional / or \ in a path name
	 *
	 * @param   string  $path  The path to clean
	 * @param   string  $ds    Directory separator (optional)
	 *
	 * @return  string  The cleaned path
	 */
	protected static function cleanPath($path, $ds = DIRECTORY_SEPARATOR)
	{
		$path = trim($path);
		$path = preg_replace('#[/\\\\]+#', $ds, $path);

		return $path;
	}

	/**
	 * is_writable Wrapper
	 * Will work in despite of Windows ACLs bug
	 *
	 * NOTE: use a trailing slash for folders!!!
	 *
	 * @param   string  $path  Folder or File path
	 *
	 * @see http://bugs.php.net/bug.php?id=27609
	 * @see http://bugs.php.net/bug.php?id=30931
	 *
	 * @static
	 * @return boolean is writable
	 */
	protected function _isWritable($path)
	{
		if ($path{strlen($path) - 1} == '/')
		{
			// Recursively return a temporary file path
			return $this->_isWritable($path . uniqid(mt_rand()) . ' . tmp');
		}
		elseif (is_dir($path))
		{
			return $this->_isWritable($path . '/' . uniqid(mt_rand()) . ' . tmp');
		}

		// Check tmp file for read/write capabilities
		$rm = file_exists($path);
		$f = @fopen($path, 'a');

		if ($f === false)
		{
			return false;
		}

		fclose($f);

		if (!$rm)
		{
			unlink($path);
		}

		return true;
	}

	/**
	 * Add an error message
	 *
	 * @param   string  $msg  Error message.
	 *
	 * @return  void
	 */
	protected function addError($msg)
	{
		$this->errors[] = $msg;
	}

	/**
	 * Get the most recent error message
	 *
	 * @return  string   Error message
	 */
	public function getError()
	{
		return end($this->errors);
	}

	/**
	 * Get an array of all error messages
	 *
	 * @return  array   Array of error messages.
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	/**
	 * Wrapper to call PhpThumbFactory
	 *
	 * All sub calls saved in the _callHistory property and executed only if no cache file was
	 * found in self::_createImageFile
	 *
	 * @param   string  $method     Method
	 * @param   array   $arguments  Arguments
	 *
	 * @return self or trigger error
	 */
	public function __call($method, $arguments)
	{
		if ($this->getError())
		{
			return $this;
		}

		$dummy = $this->getThumbnailer(true);
		$canCall = false;

		// First check if the method exists (faster as is_callable)
		if (method_exists($dummy, $method))
		{
			$canCall = true;
		}

		// If method not exists now we check with is_callable (only required for thumb_plugins)
		if ($canCall === false && is_callable(array($dummy, $method), false, $callableName))
		{
			$canCall = true;
		}

		if ($canCall)
		{
			$this->callHistory[] = array($method, $arguments);
		}
		else
		{
			trigger_error("Call to undefined method " . get_class($this) . '(subcall ' . get_class($dummy) . ')' . '::' . $method, E_USER_ERROR);
		}

		return $this;
	}
}

/**
 * PhpImageSimpleURI class
 *
 * Inspired by Joomla! JURI Class
 *
 * @package     PhpImage
 * @subpackage  PhpImageSimpleURI
 * @since       1.0.0
 */
class PhpImageSimpleURI extends PhpImageSimpleObject
{
	/**
	 * @var    string  Protocol
	 */
	protected $scheme = null;

	/**
	 * @var    string  Host
	 */
	protected $host = null;

	/**
	 * @var    integer  Port
	 */
	protected $port = null;

	/**
	 * @var    string  Username
	 */
	protected $user = null;

	/**
	 * @var    string  Password
	 */
	protected $pass = null;

	/**
	 * @var    string  Path
	 */
	protected $path = null;

	/**
	 * @var    string  Query
	 */
	protected $query = null;

	/**
	 * @var    string  Anchor
	 */
	protected $fragment = null;

	/**
	 * @var    array  Query variable hash
	 */
	protected $vars = array();

	/**
	 * @var    array  The current calculated base url segments
	 */
	protected static $base = array();

	/**
	 * @var    array  The current calculated root url segments
	 */
	protected static $root = array();

	/**
	 * Constructor
	 * You can specify an URI to the constructor to initialise.
	 *
	 * @param   string  $uri  The optional URI string
	 */
	public function __construct($uri = null)
	{
		if (!is_null($uri))
		{
			$this->parse($uri);
		}
	}

	/**
	 * Returns the PhpImageSimpleURI object. Each $uri is cached as instance
	 *
	 * @param   string  $uri  Optional URI to parse, if null script URI will be used
	 *
	 * @return  PhpImageSimpleURI  The URI object.
	 */
	public static function getInstance($uri = 'SERVER')
	{
		static $instances = array();

		if (empty($instances[$uri]))
		{
			// Are we obtaining the URI from the server?
			if ($uri == 'SERVER')
			{
				// Determine if the request was over SSL (HTTPS)
				$https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) ? 's' : '';

				/*
				 * Since we are assigning the URI from the server variables, we first need
				 * to determine if we are running on apache or IIS. If PHP_SELF and REQUEST_URI
				 * are present, we will assume we are running on apache.
				 */
				$_URI = 'http' . $https . '://' . $_SERVER['HTTP_HOST'];

				if (!empty($_SERVER['PHP_SELF']) && !empty($_SERVER['REQUEST_URI']))
				{
					// To build the entire URI we need to prepend the protocol, and the http host
					// to the URI string.
					$_URI .= $_SERVER['REQUEST_URI'];
				}
				else
				{
					/*
					 * Since we do not have REQUEST_URI to work with, we will assume we are
					 * running on IIS and will therefore need to work some magic with the SCRIPT_NAME and
					 * QUERY_STRING environment variables.
					 *
					 * IIS uses the SCRIPT_NAME variable instead of a REQUEST_URI variable... thanks, MS
					 */
					$_URI .= $_SERVER['SCRIPT_NAME'];

					// If the query string exists append it to the URI string
					if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']))
					{
						$_URI .= '?' . $_SERVER['QUERY_STRING'];
					}
				}
			}
			else
			{
				// We were given a URI
				$_URI = $uri;
			}

			// Create the new PhpImageSimpleURI instance
			$instances[$uri] = new PhpImageSimpleURI($_URI);
		}
		return $instances[$uri];
	}

	/**
	 * Returns the base URI for the request
	 *
	 * @param   boolean  $relative  On false the scheme, host and port are prepend
	 *
	 * @return  string  The base URI
	 */
	public static function base($relative = false)
	{
		// Get the base request path.
		if (empty(self::$base))
		{
			$uri = self::getInstance();
			self::$base['prefix'] = $uri->toString(array('scheme', 'host', 'port'));

			if (strpos(php_sapi_name(), 'cgi') !== false && !ini_get('cgi.fix_pathinfo') && !empty($_SERVER['REQUEST_URI']))
			{
				// PHP-CGI on Apache with "cgi.fix_pathinfo = 0"

				// We shouldn't have user-supplied PATH_INFO in PHP_SELF in this case
				// because PHP will not work with PATH_INFO at all.
				$script_name = $_SERVER['PHP_SELF'];
			}
			else
			{
				// Others
				$script_name = $_SERVER['SCRIPT_NAME'];
			}

			self::$base['path'] = rtrim(dirname($script_name), '/\\');
		}

		return $relative === false ? self::$base['prefix'] . self::$base['path'] . '/' : self::$base['path'];
	}

	/**
	 * Returns the root URI for the request
	 *
	 * @param   boolean  $relative  On false the scheme, host and port are prepend
	 * @param   string   $path      Additional path
	 *
	 * @return  string  The root URI
	 */
	public static function root($relative = false, $path = null)
	{
		// Get the scheme
		if (empty(self::$root))
		{
			$uri = self::getInstance(self::base());
			self::$root['prefix'] = $uri->toString(array('scheme', 'host', 'port'));
			self::$root['path'] = '';
		}

		// Set additional path if given
		if (isset($path))
		{
			self::$root['path'] = $path;
		}

		return $relative === false ? self::$root['prefix'] . self::$root['path'] . '/' : self::$root['path'];
	}

	/**
	 * Checks if a URL is internal
	 *
	 * @param   string  $url  The URL to check
	 *
	 * @return  boolean  True if internal, otherwise false
	 */
	public static function isInternal($url)
	{
		$uri = self::getInstance($url);
		$base = $uri->toString(array('scheme', 'host', 'port', 'path'));
		$host = $uri->toString(array('scheme', 'host', 'port'));

		if (stripos($base, self::base()) !== 0 && !empty($host))
		{
			return false;
		}

		return true;
	}

	/**
	 * Parse a given URI and populate the class fields.
	 *
	 * @param   string  $uri  The URI string to parse.
	 *
	 * @return  boolean  True on success.
	 */
	public function parse($uri)
	{
		// Set the original URI to fall back on
		$this->uri = $uri;

		// Does a UTF-8 safe version of PHP parse_url function
		// @see http://us3.php.net/manual/en/function.parse-url.php
		$parts = array();

		// Build arrays of values we need to decode before parsing
		$entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
		$replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "$", ",", "/", "?", "%", "#", "[", "]");

		// Create encoded URL with special URL characters decoded so it can be parsed
		// All other characters will be encoded
		$encodedURL = str_replace($entities, $replacements, urlencode($uri));

		// Parse the encoded URL
		$encodedParts = parse_url($encodedURL);

		// Now, decode each value of the resulting array
		if ($encodedParts)
		{
			foreach ($encodedParts as $key => $value)
			{
				$parts[$key] = urldecode($value);
			}
		}

		$retval = ($parts) ? true : false;

		// We need to replace &amp; with & for parse_str to work right...
		if (isset($parts['query']) && strpos($parts['query'], '&amp;'))
		{
			$parts['query'] = str_replace('&amp;', '&', $parts['query']);
		}

		$this->scheme = isset($parts['scheme']) ? $parts['scheme'] : null;
		$this->user = isset($parts['user']) ? $parts['user'] : null;
		$this->pass = isset($parts['pass']) ? $parts['pass'] : null;
		$this->host = isset($parts['host']) ? $parts['host'] : null;
		$this->port = isset($parts['port']) ? $parts['port'] : null;
		$this->path = isset($parts['path']) ? $parts['path'] : null;
		$this->query = isset($parts['query']) ? $parts['query'] : null;
		$this->fragment = isset($parts['fragment']) ? $parts['fragment'] : null;

		// Parse the query

		if (isset($parts['query']))
		{
			parse_str($parts['query'], $this->vars);
		}

		return $retval;
	}

	/**
	 * Returns the URL query parts as string
	 *
	 * @return  string   URL query as string
	 */
	public function getQuery()
	{
		// If the query is empty build it first
		if (is_null($this->query))
		{
			$this->query = self::buildQuery($this->vars);
		}

		return $this->query;
	}

	/**
	 * Build a query from a array (reverse of the PHP parse_str()).
	 *
	 * @param   array  $params  The array of key => value pairs to return as a query string.
	 *
	 * @return  string  The resulting query string.
	 *
	 * @see     parse_str()
	 */
	public static function buildQuery(array $params)
	{
		if (count($params) == 0)
		{
			return false;
		}

		return urldecode(http_build_query($params, '', '&'));
	}

	/**
	 * Returns full uri string
	 *
	 * @param   array  $parts  An array of the parts to include in the uri string
	 *
	 * @return  string  The URI as string
	 */
	public function toString(array $parts = array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'))
	{
		// Make sure the query is created
		$query = $this->getQuery();

		$uri = '';
		$uri .= in_array('scheme', $parts) ? (!empty($this->scheme) ? $this->scheme . '://' : '') : '';
		$uri .= in_array('user', $parts) ? $this->user : '';
		$uri .= in_array('pass', $parts) ? (!empty($this->pass) ? ':' : '') . $this->pass . (!empty($this->user) ? '@' : '') : '';
		$uri .= in_array('host', $parts) ? $this->host : '';
		$uri .= in_array('port', $parts) ? (!empty($this->port) ? ':' : '') . $this->port : '';
		$uri .= in_array('path', $parts) ? $this->path : '';
		$uri .= in_array('query', $parts) ? (!empty($query) ? '?' . $query : '') : '';
		$uri .= in_array('fragment', $parts) ? (!empty($this->fragment) ? '#' . $this->fragment : '') : '';

		return $uri;
	}

	/**
	 * Magic method to get the string representation of the URI object.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return $this->toString();
	}

}

/**
 * PhpImageBaseObject class
 *
 * @package     PhpImage
 * @subpackage  PhpImageBaseObject
 * @since       1.0.0
 */
class PhpImageSimpleObject
{
	/**
	 * @var   object  Object data
	 */
	protected $data;

	/**
	 * Constructor
	 *
	 * @param   array  $data  The data to bind to object
	 */
	public function __construct($data = null)
	{
		$this->data = new stdClass;

		// Bind data, if given as array
		if (is_array($data))
		{
			$this->data = $data;
		}
	}

	/**
	 * Get a value.
	 *
	 * @param   string  $key  The name of the data key
	 * @param   mixed   $def  Optional default value, returned if the key not exists
	 *
	 * @return  mixed  Value
	 */
	public function get($key, $def = null)
	{
		if (isset($this->$key))
		{
			return $this->$key;
		}
		return $def;
	}

	/**
	 * Set or create a property
	 *
	 * @param   string  $key  The name/key
	 * @param   mixed   $val  The value to set
	 *
	 * @return  mixed  Previous value or null if was not set before
	 */
	public function set($key, $val = null)
	{
		$prev = isset($this->$key) ? $this->$key : null;
		$this->$key = $val;
		return $prev;
	}

	/**
	 * Recursively convert an object to an array.
	 *
	 * @return  array  Array of data.
	 */
	public function toArray()
	{
		$arr = array();

		foreach (get_object_vars($this->data) as $k => $v)
		{
			if (is_object($v))
			{
				$arr[$k] = $this->toArray($v);
			}
			else
			{
				$arr[$k] = $v;
			}
		}

		return $arr;
	}
}
