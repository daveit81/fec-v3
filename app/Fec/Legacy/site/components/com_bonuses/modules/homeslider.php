<?php

defined('_JEXEC') or die('Restricted access');

require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'countriesarray.php' );
require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'currenciesarray.php' );
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonustypesarray.php' );
require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonuslabelsarray.php' );


/*include_once("geoip/geoip.inc");
$ip = $_SERVER["REMOTE_ADDR"];
$gi = geoip_open("geoip/GeoIP.dat",GEOIP_STANDARD);
$countrycode = strtolower(preg_replace('/\s+/','',geoip_country_code_by_addr($gi, "$ip")));
$countryname = geoip_country_name_by_addr($gi, "$ip");*/

require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'countriesarray.php' );

$countrycode = strtolower(getenv(GEOIP_COUNTRY_CODE));
$countrycodeup = getenv(GEOIP_COUNTRY_CODE);
$countryname = $countries_array[$countrycodeup];

$jinput = JFactory::getApplication()->input;
$cid = JRequest::getVar('id');
$bid = JRequest::getVar('bid');
$bonusurl = JRoute::_('index.php?option=com_bonuses&cid='.$cid.'&view=casino');
// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$queryone = $db->getQuery(true);
 
$queryone->select(array('a.*', 'b.*'));
$queryone->from($db->quoteName('#__content', 'a'));
$queryone->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')');
$queryone->where('MONTH(b.jr_cofmdate) = MONTH(CURDATE())');
// Reset the query using our newly populated query object.
$db->setQuery($queryone);
$rowone = $db->loadAssoc();


$querytwo = $db->getQuery(true);
 
$querytwo->select(array('a.*', 'b.*'));
$querytwo->from($db->quoteName('#__content', 'a'));
$querytwo->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')');
$querytwo->order($db->quoteName('a.created') . ' DESC');
$db->setQuery($querytwo,0,1);
// Reset the query using our newly populated query object.
$db->setQuery($querytwo);
$rowtwo = $db->loadAssoc();


$querythree = $db->getQuery(true);
 
$querythree->select(array('a.*', 'b.*'));
$querythree->from($db->quoteName('#__content', 'a'));
$querythree->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')');
$querythree->where($db->quoteName('b.featured').' = 1');
$querythree->order('RAND()');
// Reset the query using our newly populated query object.
$db->setQuery($querythree);
$rowthree = $db->loadAssoc();

$queryfour = $db->getQuery(true);
 
$queryfour->select(array('a.*', 'b.*'));
$queryfour->from($db->quoteName('#__bonuses', 'a'));
$queryfour->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$queryfour->where($db->quoteName('a.bonus') . ' > 0');
$queryfour->where($db->quoteName('a.status') . ' LIKE "active"');
$queryfour->order('CASE WHEN '.$db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$countrycode.'*%" then 1 else 0 end');
$queryfour->order('CASE WHEN '.$db->quoteName('a.country_ok').' LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "" then 0 else 1 end');
$queryfour->order('CASE WHEN '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_no').' LIKE "" then 0 else 1 end');
$queryfour->order($db->quoteName('a.added') . ' DESC');
// Reset the query using our newly populated query object.
$db->setQuery($queryfour);
$rowfour = $db->loadAssoc();
$type = $rowfour['type'];
$bonus = $rowfour['bonus'];
$maxbonus = $rowfour['max_bonus'];
$brand = $rowfour['brand'];
$game = $rowfour['game'];
$label = $rowfour['label'];
$cur = $rowfour['currency'];
$curr = (empty($cur) ? '$' : $currency[$cur]);
$mindep = (empty($rowfour['min_dep']) ? 'No Deposit Required' : 'Minimum deposit '.$curr.''.$rowfour['min_dep'].'.');
$code = (empty($rowfour['code']) ? 'No code required' : 'Code required, redeem '.$rowfour['code']);
$wr = ($rowfour['wr_meth'] == 'b' ? $rowfour['wr'].'X(Bonus)' : $rowfour['wr'].'X(Bonus + Deposit)');
$cid = $rowfour['cid'];
$bid = $rowfour['bid'];

if($rowfour['link'] === NULL || $rowfour['link'] == '') {
$rowfourplay = $rowfour['jr_gourl'];
}else{
$rowfourplay = JRoute::_('index.php?option=com_bonuses&view=ads&cid='.$cid.'&bid='.$bid.'&tmpl=component');
}

if ($countrycode === 'gb' && $type == 'fs' && $rowfour['min_dep'] !== '') {
    
$label = 'Extra Spins';
$shlabel[$type] = 'Extra Spins';

} else{
    $label = $rowfour['label'];
    $shlabel[$type] = $shlabel[$type];
}


function get_thumb($thumb) {
$picextr = preg_replace('/[\[{\(].*[\]}\)]/U' , '',strstr($thumb, '|', true));
$pic = preg_replace('/\s/', '', $picextr);
return $pic;
}


function get_text($content) {
$pos=strpos($content, ' ', 300);
$shorten = substr($content,0,$pos );
$contentstr = preg_replace("/<h([1-6]{1})>.*?<\/h\\1>/si", '', $shorten);
$stripped = strip_tags($contentstr);
return $stripped;
}

function render_join_btn($code,$country,$href) {
if (fnmatch('*'.$code.'*', $country)) {
$button = '';
} elseif (fnmatch('*no-restrictions*', $country)) {
$buttons = '<a href="'.$href.'" class="cd-btn" rel="nofollow" target="_blank">Join</a>';
$button = '<button class="cd-btn" onclick="window.open(\'' . $href . '\', \'_blank\')">Join</button>';
} else {
$buttons = '<a href="'.$href.'" class="cd-btn" rel="nofollow" target="_blank">Join</a>';
$button = '<button class="cd-btn" onclick="window.open(\'' . $href . '\', \'_blank\')">Join</button>';
}
return $button;
}

function render_rev_btn($cid) {
$button = '<a href="'.JRoute::_('index.php?option=com_content&catid=8&id='.$cid.'&lang=en&view=article').'" class="cd-btn secondary">Read More</a>';
return $button;
}

function render_getbonus_btn($href) {
$buttons = '<a href="'.$href.'" class="cd-btn" rel="nofollow" target="_blank">Get Bonus</a>';
$button = '<button class="cd-btn" onclick="window.open(\'' . $href . '\', \'_blank\')">Get Bonus</button>';
return $button;
}

function render_bonusdet_btn($bid,$cid,$type) {
$button = '<a href="'.JRoute::_('index.php?option=com_bonuses&bid='.$bid.'&cid='.$cid.'&lang=en&type='.$type.'&view=details').'" class="cd-btn secondary">Details</a>';
return $button;
}

if($type == 'nd' || $type == 'fp' || $type == 'fc') {

$bonus = $curr.''.$bonus;

} elseif ($type == 'fs') {

$bonus = $bonus;

} else {

$bonus = $bonus.'%';
}

if($type == 'nd') {

$titletag = $bonus.' '.$label;
$desc = 'Get '.$bonus.' '.strtolower($label).' at '.$brand.'. '.$code.'. Wagering requirements '.$wr.'.';

} elseif ($type == 'fp') {
$titletag = $brand.' '.$bonus.' - '.$result['fp_time'].' '.$fptimeden.' '.$label.''.$statustitle;
} elseif ($type == 'fs') {

$titletag = $bonus.' '.$label.' '.(empty($game) ? '': 'on '.$game);
$desc = 'Get '.$bonus.' '.strtolower($label).' '.(empty($game) ? '': 'on '.$game).' at '.$brand.'. '.$mindep.' '.$code.'. Wagering requirements '.$wr.'.';

} else {

$titletag = $bonus.' '.$label.' '.(empty($maxbonus) ? '': 'up to '.$curr.''.$maxbonus);
$desc = 'Get '.$bonus.' '.strtolower($label).' '.(empty($maxbonus) ? '': 'up to '.$curr.''.$maxbonus).' at '.$brand.'. '.$mindep.' '.$code.'. Wagering requirements '.$wr.'.';

}


;?>

<section class="cd-hero">
		<ul class="cd-hero-slider autoplay">
			<li class="selected" style="background:url(/images/<?php echo get_thumb($rowone['images']);?>)">
			<div class="overlay"></div>
			<div class="cd-full-width">
				<h2>Casino of the Month - <?php echo date('F Y');?></h2>
					<h3><?php echo $rowone['title'];?></h3>
					<p class="multiline-ellipsis"><?php echo get_text($rowone['introtext']);?>...</p>
					<?php echo render_join_btn($countrycode,$rowone['jr_restrictedcountries'],$rowone['jr_gourl']);?>
					<?php echo render_rev_btn($rowone['contentid']);?>
				</div>
			</li>
			<li class="" style="background:url(/images/<?php echo get_thumb($rowtwo['images']);?>)">
			<div class="overlay"></div>
			<div class="cd-full-width">
				<h2>Latest Casino</h2>
					<h3><?php echo $rowtwo['title'];?></h3>
					<p class="multiline-ellipsis"><?php echo get_text($rowtwo['introtext']);?>...</p>
					<?php echo render_join_btn($countrycode,$rowtwo['jr_restrictedcountries'],$rowtwo['jr_gourl']);?>
					<?php echo render_rev_btn($rowtwo['contentid']);?>
				</div>
			</li>

			<li class="" style="background:url(/images/<?php echo get_thumb($rowthree['images']);?>)">
			<div class="overlay"></div>
			<div class="cd-full-width">
				<h2>Featured Casino</h2>
					<h3><?php echo $rowthree['title'];?></h3>
					<p class="multiline-ellipsis"><?php echo get_text($rowthree['introtext']);?>...</p>
					<?php echo render_join_btn($countrycode,$rowthree['jr_restrictedcountries'],$rowthree['jr_gourl']);?>
					<?php echo render_rev_btn($rowthree['contentid']);?>
				</div>
			</li>

			<li class="">
			<div class="cd-half-width">
		<h2><?php echo $titletag;?></h2>
		<h3><?php echo $rowfour['brand'];?></h3>
					<p><?php echo $desc;?></p>
					<?php echo render_getbonus_btn($rowfourplay);?>
					<?php echo render_bonusdet_btn($rowfour['bid'],$rowfour['cid'],$rowfour['type']);?>
				</div>
				<div class="cd-half-width cd-img-container">
					<img src="images/casino-logos/<?php echo $rowfour['jr_logosmall'];?>" alt="Logo">
				</div>
				
			</li>

		</ul>

		<div class="cd-slider-nav">
			<nav>
				<span class="cd-marker item-1"></span>
				
				<ul> 
				
				        <li class="selected"><a href="#0">COFM</a></li>
					<li class=""><a href="#0">Latest</a></li>
					<li class=""><a href="#0">Featured</a></li>
					<li class=""><a href="#0" class="ellipsis"><?php echo $shlabel[$type];?></a></li>
				</ul>
			</nav> 
		</div>
	</section>
<?php $user = &JFactory::getUser();
	if($user->id == '42'):?>
<section class="tops">
		<ul class="tops-list">
			<li>One</li>
		   <li>Two</li>
	</section>
<?php endif;?>