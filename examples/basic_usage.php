<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
	<meta charset="UTF-8" />
	<title>phpImage</title>
</head>
<body>
<?php
define('ROOT_DIR', dirname(__FILE__));

// Include
require_once dirname(ROOT_DIR) . '/src/PhpImage.php';

// Config (optional)
PhpImage::$cachePath = ROOT_DIR . '/cache';
// PhpImage::$cacheDepth = ...;
// PhpImage::$rootPath = ...;

$imageFromAbsolutePath = ROOT_DIR . '/images/waterfall.jpg';

PhpImage::$urlMode = 'absolute';
// if PhpImage::$cdnUrl is set, then PhpImage::$urlMode will be ignored
// PhpImage::$cdnUrl = '//local.net/workspace/phpImage';

$image = new PhpImage($imageFromAbsolutePath);

$image->adaptiveResize(200, 400);



?>
<pre style="background:#000; color:#fff; padding:5px; text-align:left; margin:10px 0; overflow:auto;">
	<?php echo htmlentities(print_r($image->toObject(), true)); ?>
</pre>
<?php

echo $image->toHtml();

?>
</body>
