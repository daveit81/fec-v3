<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
require JPATH_BASE.DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'detailsconstructor.php';

$gainturl = $brand.' Review';
$gaexturl = $brand.' Details Play Button';

$onclickcard = "ga('send', 'event', 'Bonus Details Page', 'Go Review', '$gainturl')";
$onclickplay = "ga('send', 'event', 'Bonus Details Page', 'Play Button', '$gaexturl')";

?>
<div class="rt-grid-12 rt-alpha rt-omega">
               <div class="rt-block ">

<section class="revintro bonuses">
<div class="sect heading">
<h1 class="casname ellipsis"><?php echo $title;?></h1>
<?php echo $countrytxt;?>
</div>
<?php echo $tcs;?>
<div class="sect rating">
<div class="logo">
<img alt="logo" class="logo-revs" width="190" height="190" src="/cdn-cgi/image/width=190,height=190,quality=100/images/casino-logos/<?php echo $logo;?>">
</div>


<div class="subsect dtinfos bonus-no-sm">
<div class="dtlbox">
<span class="legend name">Type</span>
<span class="legend score"><?php echo $labelstr;?></span>
</div>

<div class="dtlbox no-sm">
<span class="legend name ellipsis">Exclusive</span>
<span class="legend score <?php echo $exclass;?>"><?php echo $exclusive;?></span>
</div>

<div class="dtlbox last">
<span class="legend name ellipsis">Status</span>
<span class="legend score <?php echo $statusclass;?>"><?php echo $status;?></span>
</div>

</div>

<div class="subsect summary">

<div class="dtlbox">
<span class="legend name">Bonus</span>
<span class="legend score"><?php echo $bonus;?></span>
</div>

<div class="dtlbox">
<span class="legend name">Code</span>
<span class="legend score <?php echo $codeclass;?> ellipsis"><?php echo $code;?></span>
</div>

<div class="dtlbox last">
<span class="legend name">Wager</span>
<span class="legend score"><?php echo $wr;?></span>
</div>

</div>

<div class="subsect sect btns">
<div class="btns-wrapper">
<button class="btn-cards ellipsis btn-revs ellipsis <?php echo $countryclass; ?>" onclick="window.open('<?php echo $play; ?>', '_blank')"><?php echo $btnclaim; ?></button>
<a href="<?php echo $reviewurl;?>" class="btn-revs gorev ellipsis" onclick="<?php echo $onclickcard;?>"><?php echo $brand;?> Review</a>
</div>
</div>

</div>

</section>			                	
                	
           </div>
	
</div>