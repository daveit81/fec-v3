<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function outputSampleImage($imageFormat) {
$availableFmts = \Imagick::queryformats();
if ( ! in_array(strtoupper($imageFormat), $availableFmts) ){
return;
}
/* Create some objects */

$image = new Imagick();
$draw = new ImagickDraw();
$pixel = new ImagickPixel( '#D7EDF9' );




/* New image */
$image->newImage(150, 75, $pixel);

/* Black text */
$draw->setFillColor('#333333');

/* Font properties */
// setFont needs the path to a valid font file. You may use any of your
$draw->setFont('/usr/local/cpanel/3rdparty/share/ui-fonts/open_sans/optimized/OpenSans-Regular-webfont.ttf');
$draw->setFontSize( 15 );

/* Create text */
$image->annotateImage($draw, 10, 45, 0, 'cPanel Rocks!');

/* Give image a format */
$image->setImageFormat($imageFormat);

$imgBuff = $image->getimageblob();
$image->clear();

return base64_encode($imgBuff);
}
?>
<!doctype html>

<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Testing ImageMagick on cPanel</title>
</head>
<body>
<h1>Testing ImageMagick on cPanel</h1>
<h2>ImageMagick Version:</h2>
<pre>
<?php
print_r(imagick::getVersion());
?>
</pre>
<p><b>If you do not see the image format that you need to use listed below, you should double check to be sure that there are not multiple installations of ImageMagick on the server, and that the installed version has been compiled with support for your desired image type. This is a task that must be performed by a systems administrator with the skills, training, and expeience required to do so properly for you.</b></p>

<p>Here are a few examples of common image formats. Each image is dynamically generated from text.</p>
<p>Please keep in mind this code is crude and is not optimized for image quality.</p>
<table border=1>
<tr>
<th>Example Image Type</th>
<th>Embedded Example Of Image</th>
</tr>
<?php
$exampleFormats = "png jpg gif webp bmp";
$delimiter = ' ';
$fmts = explode($delimiter, $exampleFormats);

foreach ($fmts as $fmt) {
$sampleImage = outputSampleImage($fmt);
if ($sampleImage) {
echo "<tr>";
echo "<td>$fmt</td>";
echo "<td><img src='data:image/$fmt;base64,$sampleImage' alt='Example $fmt image'/></td>";
echo "</tr>";
}
}
?>
</table>
<p>The ImageMagick installation on this server supports the following image types.</p>
<ul>
<?php
$formats = \Imagick::queryformats();
foreach ($formats as $key => $format){
echo "<li>$format</li>";
}
?>
</ul>
</body>
</html>