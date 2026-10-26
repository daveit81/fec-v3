<?php

defined('_JEXEC') or die('Restricted access');

require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'countriesarray.php' );
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'currenciesarray.php' );
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonustypesarray.php' );


/*include_once("geoip/geoip.inc");
$ip = $_SERVER["REMOTE_ADDR"];
$gi = geoip_open("geoip/GeoIP.dat",GEOIP_STANDARD);
$countrycode = strtolower(preg_replace('/\s+/','',geoip_country_code_by_addr($gi, "$ip")));
$countryname = geoip_country_name_by_addr($gi, "$ip");*/


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
$query = $db->getQuery(true);
 
$query->select(array('SQL_CALC_FOUND_ROWS a.*', 'b.*'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' NOT LIKE "pending"');
$query->where($db->quoteName('a.bonus') . ' > 0');
$query->where($db->quoteName('a.cid') . ' = '.$cid);
$query->order('CASE WHEN '.$db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$countrycode.'*%" then 1 else 0 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_ok').' LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_no').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.status').' LIKE "expired" then 1 else 0 end');
$query->order($db->quoteName('a.added') . ' DESC');

// Reset the query using our newly populated query object.
$db->setQuery($query,0,3);

$rowb2 = $db->loadAssocList();
$db->setQuery('SELECT FOUND_ROWS();');
$totalbonus = $db->loadResult();
foreach($rowb2 as $set) {

$db = JFactory::getDbo();

$queryrel = $db->getQuery(true);

$queryrel
    ->select($db->quoteName(array('bonus', 'label','type', 'game','bid','related','currency')))
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('related') . ' = '. $db->quote($set['bid']))
    ->where($db->quoteName('status') . ' LIKE "active"');
    
$db->setQuery($queryrel);

$related = $db->loadAssoc();

if(!empty($set['tc']) && $countrycode == 'gb') {
$tctag = '<div class="subsect tc">T&Cs Apply</div>';
} elseif ($countrycode == 'se') {
$tctag = '<div class="subsect tc">18+ - Regler & Villkor gäller</div>';
} else {
$tctag = '';
}

if($related['type'] == 'fs') {
$relcurr = '';
} else {
$relcurr = $currency[$related['currency']];
}

if($set['bid'] == $related['related']) {

$relatedtext = '<span class="legend rel ellipsis">+ '.$relcurr.''.$related['bonus'].' '.$related['label'].' '.($related['game']==='' ? ' ' : ' on '. $related['game']).' </span>';

$relclass = 'exp';

} else {

$relatedtext = '';
$relclass = '';
}

$related = $db->loadAssoc();



$type = $set['type'];
$wr = $set['wr'];
$wrmeth = ($set['wr_meth'] == 'bd' ? '+d' : $set['wr_meth']);
$closed = $set['jr_closed'];
$cid = $set['cid'];
$bid = $set['bid'];

if($set['link'] === NULL || $set['link'] == '') {
$play = $set['jr_gourl'];
}else{
$play = JRoute::_('index.php?option=com_bonuses&view=ads&cid='.$cid.'&bid='.$bid.'&tmpl=component');
}

if(($type == 'nd' || $type == 'fp' || $type == 'fs') && $wrmeth == '') {

$wr = $wr.'x';

} elseif(($type == 'nd' || $type == 'fp' || $type == 'fs') && $wrmeth != '') {

$wr = $wr.'x';

} elseif(($type != 'nd' || $type != 'fp' || $type != 'fs') && $wrmeth == '') {

$wr = $wr.'x+d';

} else {

$wr = $wr.'x'.$wrmeth.'';
}


if($set['code'] == '') {

$code = 'No Code Required';
$codeclass = '';
} else {

$code = $set['code'];
$codeclass = 'req';
}

if($set['type'] == 'fs') {

$curr = '';
$perc = '';


} elseif($set['currency'] == '') {

$curr = '<sup class="curr">$</sup>';

} else {

$curr = '<sup class="curr">'.$currency[$set['currency']].'</sup>';

}


if($set['type'] == 'nd' || $set['type'] == 'fs' || $set['type'] == 'fp') {

$perc = '';

} else {

$perc = '<sup class="perc">%</sup>';
$curr = '';

}

if($set['type'] == 'fs' && $set['game'] != '') {

$typetxt = 'FS on '.$set['game'];

} elseif($set['type'] == 'fp') {

$typetxt = ($set['fp_time_den'] == 'h' ? ''.$set['fp_time'].' Hours' : $set['fp_time'].' Minutes').' '.$bonustype[$set['type']];

} else {

$typetxt = $bonustype[$set['type']];

}


if (!empty($closed)) 
			        
			        {
			    
			    
                 	   $countryclass = 'disabled';
			   $btnclaim = 'Closed';
			   $countrytxt = '<span class="country closed"><i class="fa fa-circle na"></i> '.$set['brand'].' is closed</span>';
			   $status = 'Expired';
			   }
			   
			  elseif (fnmatch('*susp*', $set['jr_suspended']) && empty($closed)) {
			
			$countryclass  = 'disabled';
			$btnclaim = 'Suspended';
			$countrytxt = '<span class="country susp"><i class="fa fa-warning"></i> '.$set['brand'].' is suspended</span>';
			$status = 'N.A.';
			
} elseif (fnmatch('*bl*', $set['jr_blacklisted']) && empty($closed)) {
			
			$countryclass  = 'disabled bl';
			$btnclaim = 'Blacklisted';
			$countrytxt = '<span class="country closed"><i class="fa fa-ban"></i> '.$set['brand'].' is blacklisted</span>';
			$status = 'N.A.';
}

elseif($set['status'] == 'expired' && empty($closed)) {
			   
			$countryclass  = 'disabled';
			$btnclaim = 'Expired';
			$countrytxt = '<span class="country closed"><i class="fa fa-circle na"></i> This bonus has expired</span>';
			$status = 'Expired';
			   
			            			        }

elseif (fnmatch('*'.$countrycode.'*', $set['jr_restrictedcountries'])) {

$countryclass = 'disabled';
$btnclaim = 'No '.$countryname;
$countrytxt = '<span class="country"><i class="fa fa-circle na"></i> Players from '.$countryname.' not allowed</span>';
$brandcountrytxt = 'doesn&apos;t accept players from '.$countryname;

} elseif (($set['country_no'] != '' && fnmatch('*'.$countrycode.'*', $set['country_no'])) || ($set['country_ok'] != '' && !fnmatch('*'.$countrycode.'*', $set['country_ok']))) {

$btnclaim = 'N.A. in '.$countryname;
$countryclass = 'disabled notavailable';
$countrytxt = '<span class="country"><i class="fa fa-circle na"></i> Not available in '.$countryname.'</span>';

} elseif (fnmatch('*no-restrictions*', $set['jr_restrictedcountries'])) {


$countryclass = '';
$btnclaim = 'Get Bonus';
$countrytxt = '<span class="country"><i class="fa fa-circle ok"></i> Players from '.$countryname.' allowed</span>';
$brandcountrytxt = 'accepts players from '.$countryname;


} else {

$countryclass = '';
$btnclaim = 'Get Bonus';
$countrytxt = '<span class="country"><i class="fa fa-circle ok"></i> Players from '.$countryname.' allowed</span>';
$brandcountrytxt = 'accepts players from '.$countryname;
}


if (fnmatch('yes', $set['exclusive'])) {

$exclusivetag = '<div class="excl-shape"><span class="ex">Ex.</span></div>';

} else {

$exclusivetag = '';

}

$now  = new DateTime();
$ends = new DateTime($set['end']);
$left = $now->diff($ends);

if (($set['end'] == '0000-00-00 00:00:00' || $set['end'] == null) && !$related || $set['status'] == 'expired'){

$expires = '';
$expclass = '';



}elseif ($left->format('%a') > 3) {

$expires = $relatedtext;
$expclass = $relclass;

}elseif ($left->format('%a') == 0 && $related) {

$expires = $relatedtext;
$expclass = $relclass;

}elseif ($left->format('%a') <= 3) {

$expires = '<span class="legend expiring">Expires in '.$left->format('%a').' D, '.$left->format('%h').' H, '.$left->format('%i').' Min.</span>';
$expclass = 'exp';
}

if(isset($type)) {
$galabel = $set['label'];
} else {
$galabel = 'All Bonuses';
}

$gainturl = $set['brand'].' '.$set['bonus'].' '.$set['label'].' Details';
$gaexturl = $set['brand'].' '.$set['bonus'].' '.$set['label'].' Play Button';

$onclickcard = "ga('send', 'event', '$galabel', 'Box Bonus Details', '$gainturl')";
$onclickplay = "ga('send', 'event', '$galabel', 'Play Button', '$gaexturl')";
$url = JRoute::_('index.php?option=com_bonuses&view=details&type='.$set['type'].'&bid='.$set['bid'].'&cid='.$set['cid']);
$reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$set['cid'].'&lang=en&view=article');

//output

$output .= '
<div class="fitter">
<div class="bcard">
<a class="bcard-a" href="'.$url.'" onclick="">
<div class="sect head">
'.$exclusivetag.'
<div class="subsect logo">
<img alt="logo" class="logo-lists" width="50" height="50" src="/images/casino-logos/'.$set['jr_logosmall'].'">
</div>
<div class="ttl-lbl ellipsis">
'.$set['brand'].'
  </div>
</div>
<div class="subsect ttl">
<span class="legend code '.$codeclass.'">'.$code.'</span>
</div>
<div class="subsect bonus">
<span class="legend bonus">'.$curr.''.$set['bonus'].''.$perc.'</span>
<span class="legend nd '.$expclass.'">'.$typetxt.'</span>
'.$expires.'
</div>

<div class="subsect infos">
<div class="subr in rank">
<span class="legend value">'.$wr.'</span>
<span class="legend name">WR</span>
</div>

<div class="subr in views">
<span class="legend value">'.ucfirst($set['cashable']).'</span>
<span class="legend name">Cashable</span>
</div>

<div class="subr in infos">
<span class="legend value"><i class="fa fa-info"></i></span>
<span class="legend name">Details</span>
</div>

</div>
</a>
<div class="subsect btns">
<button class="btn-cards ellipsis '.$countryclass.'" onclick="window.open(\'' . $play . '\', \'_blank\')">'.$btnclaim.'</button>
</div>
</div>
</div>';


}