<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
require JPATH_BASE.DS.'components'.DS.'com_jackpots'.DS.'helpers'.DS.'detailsconstructor.php';

?>
<div class="rt-grid-12 rt-alpha rt-omega">
               <div class="rt-block ">

<section class="revintro jackpots">
<div class="sect heading">
<h1 class="casname">
<span class="ellipsis">
<?php echo $jtitle;?>
</span></h1>
<span class="country "><?php echo $countrytext;?></span>
</div>

<div class="sect rating">
<div class="logo">
<img alt="logo" class="logo-revs" width="190" src="<?php echo $iconurl;?>" />
</div>

<div class="subsect circles">
<div class="circles-wrapper">
<div class="first" data-value="<?php echo $jindexcircle;?>">
<div class="value">
<span class="int"><?php echo $jindexunit;?></span>
<span class="dec"><?php echo $jindexdecimal;?></span>
<span class="legend type"><i class="fa fa-flash"></i>Jindex</span>
</div>
    </div>
    <div class="second" data-value="<?php echo $percwincircle;?>"></div>
    <div class="third" data-value="<?php echo $perctimecircle;?>"></div>
</div>   
</div>
<div class="subsect dtinfos">
<div class="dtlbox">
<span class="legend name">Current Amount</span>
<span class="legend score"><?php echo $amount;?></span>
</div>

<div class="dtlbox">
<span class="legend name ellipsis">Wins Recorded</span>
<span class="legend score"><?php echo $hits;?></span>
</div>

<div class="dtlbox last">
<span class="legend name ellipsis">AVG Win</span>
<span class="legend score"><?php echo $avgwin;?></span>
</div>

</div>

<div class="subsect summary">

<div class="dtlbox">
<span class="legend name">Software</span>
<span class="legend score ellipsis"><?php echo $provider;?></span>
</div>

<div class="dtlbox">
<span class="legend name">Casino</span>
<span class="legend score"><?php echo $casino;?></span>
</div>

<div class="revs-sum">

<div class="distribution">

<div class="bar jindex">
<span class="legend name">Virtual Temp.</span>
<div class="inner">
<div class="progress-in" style="width:<?php echo $hotness;?>%;"></div>
</div>
<span class="legend score"><?php echo round($hotness,2);?>°</span>
</div>

<div class="bar caawr">
<span class="legend name">Current Amount/AVG Win Ratio (%)</span>
<div class="inner">
<div class="progress-in" style="width:<?php echo $percwinbar;?>%;"></div>
</div>
<span class="legend score"><?php echo round($percwinbar,2);?>%</span>
</div>

<div class="bar teafr">
<span class="legend name">Time elapsed since last hit/AVG Freq. Ratio (%)</span>
<div class="inner">
<div class="progress-in" style="width:<?php echo $perctimebar;?>%;"></div>
</div>
<span class="legend score"><?php echo round($perctimebar,2);?>%</span>
</div>
</div>
</div>

</div>

</div>
<div class="sect btns">
<div class="btns-wrapper">
<a href="<?php echo $play;?>" class="btn-revs ellipsis <?php echo $disabled;?>" rel="nofollow" target="_blank" onclick="<?php echo $onclickplay;?>"><?php echo $btnclaim;?></a>
</div>
</div>
</section>		                	
                	
           </div>
	
</div>