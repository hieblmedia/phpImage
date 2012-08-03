<?php
/**
 * JYAML - Template Framework includes YAML for Joomla!
 *
 * All rights reserved. The JYAML project is a template to manage and
 * configure Joomla!-Templates with the YAML XHTML/CSS Framework
 * - http://www.yaml.de
 *
 * -----------------------------------------------------------------------------
 *
 * @version     Id: $Id$
 *
 * @package     JYAML
 * @subpackage  PhpThumb.Plugins
 *
 * @author      Reinhard Hiebl <reinhard@hieblmedia.com>
 * @copyright   Copyright (C) 2006 - 2012, HieblMedia (Reinhard Hiebl)
 * @license     http://www.jyaml.de/en/license-conditions.html Creative Commons Attribution 3.0
 * @link        http://www.jyaml.de
 *
 */

/*
 * Example:
 * $thumb = PhpThumbFactory::create($src);
 * $thumb->adaptiveResize($width, $height);
 * $thumb->createWatermark($mark_image, $position, $padding);
 * $thumb->show();
 *
 * Docs:
 * $mark_image = 'path/to/mark.png'
 * $position =
 * 		cc = center center
 * 		lt = left top
 * 		rt = right top
 * 		lb = left bottom
 * 		rb = right bottom
 * 		cb = center bottom
 * $padding = number like 10 = padding 10px
 */

/**
 * GD Watermark Lib Plugin
 *
 * This plugin allows you to overlay watermark images
 *
 * @package     JYAML
 * @subpackage  PhpThumb.Plugins
 * @since       4.5.0
 */
class GdWatermarkLib
{
	/**
	 * Instance of GdThumb passed to this class
	 *
	 * @var GdThumb
	 */
	protected $parentInstance;
	protected $currentDimensions;
	protected $workingImage;
	protected $newImage;
	protected $options;

	public function createWatermark (&$that, $mask_file, $mask_position='cc', $mask_padding=0)
	{
		// bring stuff from the parent class into this class...
		$this->parentInstance = $that;
		$this->currentDimensions = $this->parentInstance->getCurrentDimensions();
		$this->workingImage = $this->parentInstance->getWorkingImage();
		$this->newImage = $this->parentInstance->getOldImage();
		$this->options = $this->parentInstance->getOptions();

		if(!is_resource($this->workingImage))
		{
			$this->workingImage = $this->newImage;
		}

		$this->mask_file = $mask_file;
		$this->mask_position = $mask_position;
		$this->mask_padding = $mask_padding;

		$canvas_width	 = $this->currentDimensions['width'];
		$canvas_height	 = $this->currentDimensions['height'];

		if ($canvas_width <= 10 || $canvas_height <= 10) {
			return $that;
		}

		list($stamp_width, $stamp_height, $stamp_type, $stamp_attr) = getimagesize($mask_file);

		switch ($stamp_type) {
			case 1:
				$stamp_image = imagecreatefromgif($mask_file);
				break;
			case 2:
				@ini_set('gd.jpeg_ignore_warning', 1);
				$stamp_image = imagecreatefromjpeg($mask_file);
				break;
			case 3:
				$stamp_image = imagecreatefrompng($mask_file);
				break;
		}

		imagealphablending($this->workingImage, true);

		if ($stamp_width > $canvas_width || $stamp_height > $canvas_height) {
			// some simple resize math
			//$water_resize_factor = round($canvas_width / $stamp_width);
			$water_resize_factor = 0.5;
			$new_mask_width = $stamp_width * $water_resize_factor;
			$new_mask_height = $stamp_height * $water_resize_factor;
			$mask_padding = $mask_padding * $water_resize_factor;
			// the new watermark creation takes place starting from here
			$new_mask_image = imagecreatetruecolor($new_mask_width , $new_mask_height);
			// imagealphablending is important in order to keep, our png image (the watewrmark) transparent
			imagealphablending($new_mask_image , false);
			imagecopyresampled(
			$new_mask_image , $stamp_image, 0, 0, 0, 0,
			$new_mask_width, $new_mask_height,
			$stamp_width, $stamp_height
			);
			// assign the new values to the old variables
			$stamp_width = $new_mask_width;
			$stamp_height = $new_mask_height;
			$stamp_image = $new_mask_image;
		}

		switch($mask_position) {
			default:
			case 'cc':
				// Center Center
				$start_width = round(($canvas_width - $stamp_width) / 2);
				$start_height = round(($canvas_height - $stamp_height) / 2);
				break;
			case 'ct':
				// Center Top
				$start_width = round(($canvas_width - $stamp_width) / 2);
				$start_height = $mask_padding;
				break;
			case 'lt':
				// Left Top
				$start_width = $mask_padding;
				$start_height = $mask_padding;
				break;
			case 'rt':
				// Right Top
				$start_width = $canvas_width - $mask_padding - $stamp_width;
				$start_height = $mask_padding;
				break;
			case 'lb':
				// Left Bottom
				$start_width = $mask_padding;
				$start_height = $canvas_height - $mask_padding - $stamp_height;
				break;
			case 'rb':
				// Right Bottom
				$start_width = $canvas_width - $mask_padding - $stamp_width;
				$start_height = $canvas_height - $mask_padding - $stamp_height;
				break;
			case 'cb':
				// Center Bottom
				$start_width = round(($canvas_width - $stamp_width) / 2);
				$start_height = $canvas_height - $mask_padding - $stamp_height;
				break;
		}

		imagecopy( $this->workingImage, $stamp_image, $start_width, $start_height, 0, 0, $stamp_width, $stamp_height );
		imagedestroy( $stamp_image );

		return $that;
	}
}

$pt = PhpThumb::getInstance();
$pt->registerPlugin('GdWatermarkLib', 'gd');
