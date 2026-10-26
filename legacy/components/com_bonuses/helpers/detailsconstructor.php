<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

require JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
include JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';

$jinput = JFactory::getApplication()->input;
// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query
    ->select(array('a.*', 'b.*'))
    ->from($db->quoteName('#__bonuses', 'a'))
    ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')')
    ->where($db->quoteName('bid').' = '.$db->quote($jinput->get('bid','','int')));

 
// Reset the query using our newly populated query object.
$db->setQuery($query);

$result = $db->loadAssoc();

/*include_once("geoip/geoip.inc");
$ip = $_SERVER["REMOTE_ADDR"];
$gi = geoip_open("geoip/GeoIP.dat",GEOIP_MEMORY_CACHE);
$countrycode = strtolower(preg_replace('/\s+/', '',geoip_country_code_by_addr($gi, "$ip")));
$countryname = geoip_country_name_by_addr($gi, "$ip");
geoip_close($gi);*/

$countrycode = strtolower(getenv(GEOIP_COUNTRY_CODE));
$countrycodeup = getenv(GEOIP_COUNTRY_CODE);
$countryname = $countries_array[$countrycodeup];

//variables
$brand = $result['brand'];
$cid = $result['cid'];
$bid = $result['bid'];
$type = $result['type'];
$bonus = $result['bonus'];
$maxbonus = $result['max_bonus'];
$exclusive = (empty($result['exclusive']) ? 'No' : ucfirst($result['exclusive']));
$exclass = (empty($result['exclusive']) || $result['exclusive'] == 'no' ? 'no': '');
$wr = $result['wr'];
$wrmeth = ($result['wr_meth'] == 'bd' ? 'b+d' : $result['wr_meth']);
$fptimeden = ($result['fp_time_den'] == 'h' ? ''.$result['fp_time'].' Hour(s)' : ''.$result['fp_time'].' Minutes');
$label = $result['label'];
$game = $result['game'];
$status = ucfirst($result['status']);
$logo = $result['jr_logosmall'];
$play = $result['jr_gourl'];
$reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$cid.'&lang=en&view=article');
$closed = $result['jr_closed'];
$curr = (empty($currency[$result['currency']]) ? '$' : $currency[$result['currency']]);
$code = (empty($result['code']) ? 'Not Required' : $result['code']);
$codeclass = (empty($result['code']) ? 'no' : '');


if ($countrycode === 'gb' && $type == 'fs' && $result['min_dep'] != '') {
    
$label = 'Extra Spins';
} else {
    $label = $result['label'];
}


if($result['link'] === NULL || $result['link'] == '') {
$play = $result['jr_gourl'];
}else{
$play = JRoute::_('index.php?option=com_bonuses&view=ads&cid='.$cid.'&bid='.$bid.'&tmpl=component');
}

$labelstr = strtr($label, array(
    'st' => '<sup>st</sup>',
    'nd' => '<sup>nd</sup>',
    'rd' => '<sup>rd</sup>',
    'th' => '<sup>th</sup>'
));

if($result['status'] == 'expired') {

$statusclass = 'expiring';

} elseif($result['status'] == 'active') {

$statusclass = 'act';

}

if (!empty($closed)) 
			        
			        {
			    
			    
                 	   $countryclass = 'disabled';
			   $btnclaim = 'Closed';
			   $countrytxt = '<span class="country closed"><i class="fa fa-circle na"></i> '.$result['brand'].' is closed</span>';
			   $status = 'Expired';
			   }
			   
			  elseif (fnmatch('*susp*', $result['jr_suspended']) && empty($closed)) {
			
			$countryclass  = 'disabled';
			$btnclaim = 'Suspended';
			$countrytxt = '<span class="country susp"><i class="fa fa-warning"></i> '.$result['brand'].' is suspended</span>';
			$status = 'N.A.';
			
} elseif (fnmatch('*bl*', $result['jr_blacklisted']) && empty($closed)) {
			
			$countryclass  = 'disabled';
			$btnclaim = 'Blacklisted';
			$countrytxt = '<span class="country closed"><i class="fa fa-ban"></i> '.$result['brand'].' is blacklisted</span>';
			$status = 'N.A.';
}

elseif($result['status'] == 'expired' && empty($closed)) {
			   
			$countryclass  = 'disabled';
			$btnclaim = 'Expired';
			$countrytxt = '<span class="country closed"><i class="fa fa-circle na"></i> This bonus has expired</span>';
			$status = 'Expired';
			   
			            			        }

elseif (fnmatch('*'.$countrycode.'*', $result['jr_restrictedcountries'])) {

$countryclass = 'disabled';
$btnclaim = 'No '.$countryname;
$countrytxt = '<span class="country"><i class="fa fa-circle na"></i> Players from '.$countryname.' not allowed</span>';

} elseif (($result['country_no'] != '' && fnmatch('*'.$countrycode.'*', $result['country_no'])) || ($result['country_ok'] != '' && !fnmatch('*'.$countrycode.'*', $result['country_ok']))) {

$btnclaim = 'N.A. in '.$countryname;
$countryclass = 'disabled';
$countrytxt = '<span class="country"><i class="fa fa-circle na"></i> Not available in '.$countryname.'</span>';

} elseif (fnmatch('*no-restrictions*', $result['jr_restrictedcountries'])) {


$countryclass = '';
$btnclaim = 'Get Bonus';
$countrytxt = '<span class="country"><i class="fa fa-circle ok"></i> Players from '.$countryname.' allowed</span>';


} else {

$countryclass = '';
$btnclaim = 'Get Bonus';
$countrytxt = '<span class="country"><i class="fa fa-circle ok"></i> Players from '.$countryname.' allowed</span>';
}


if($type == 'nd' || $type == 'fc') {
$title = $brand.' '.$curr.''.$bonus.' '.$label;
$bonus = '<sup>'.$curr.'</sup>'.$bonus;

} elseif ($type == 'fp') {
$title = $brand.' '.$curr.''.$bonus.' - '.$fptimeden.' '.$label;
$bonus = '<sup>'.$curr.'</sup>'.$bonus;


} elseif ($type == 'fs') {

$title = $brand.' '.$bonus.' '.$label.' '.(empty($game) ? '': 'on '.$game);
$bonus = $bonus;

} else {

$title = $brand.' '.$bonus.'% '.$label.' '.(empty($maxbonus) ? '': 'up to '.$curr.''.$maxbonus);
$bonus = $bonus.'<sup>%</sup>';
}

if(($type == 'nd' || $type == 'fp' || $type == 'fs') && $wrmeth == '') {

$wr = $wr.'<sup>X(b)</sup>';

} elseif(($type == 'nd' || $type == 'fp' || $type == 'fs') && $wrmeth != '') {

$wr = $wr.'<sup>X('.$wrmeth.')</sup>';

} elseif(($type != 'nd' || $type != 'fp' || $type != 'fs') && $wrmeth == '') {

$wr = $wr.'<sup>X(b+d)</sup>';

} else {

$wr = $wr.'<sup>X('.$wrmeth.')</sup>';
}
    
$tcs = '';
if(!empty($result['tc']) && $countrycode == 'gb') {
    $tcs = '<div class="sect sub-head">
    <p><span class="headwarn">18+</span> only. <a href="'.$tclink.'" rel="nofollow" target="_blank">T&amp;Cs apply</a></p>
</div>';
} elseif (!empty($result['tc']) && $countrycode == 'se') {
    
    $tcs = '<div class="sect sub-head">
    <p><span class="headwarn">18+</span>. Spela ansvarsfullt. <a href="'.$tclink.'" rel="nofollow" target="_blank">Regler & Villkor g&auml;ller</a>.</p>
</div>';
}
?>