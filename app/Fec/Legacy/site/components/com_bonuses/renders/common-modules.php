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
$cid = JRequest::getVar('cid');
$bid = JRequest::getVar('bid');
// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query->select(array('SQL_CALC_FOUND_ROWS a.*', 'b.*'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');

// FreeExtraCheck agg (YES/NO counts)
$query->select(array(
  'COALESCE(agg.yes_count, 0) AS fec_yes',
  'COALESCE(agg.no_count, 0) AS fec_no'
));
$query->join('LEFT', $db->quoteName('#__bonus_feedback_agg', 'agg') . ' ON (' . $db->quoteName('agg.bid') . ' = ' . $db->quoteName('a.bid') . ')');




$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.bonus') . ' > 0');
$query->where($db->quoteName('a.cid') . ' = '.$cid);
$query->where($db->quoteName('a.bid') . ' != '.$bid);
$query->order('CASE WHEN '.$db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$countrycode.'*%" then 1 else 0 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_ok').' LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_no').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.status').' LIKE "expired" then 1 else 0 end');
$query->order($db->quoteName('a.added') . ' DESC');
// Reset the query using our newly populated query object.
$db->setQuery($query,0,3);

$rowb1 = $db->loadAssocList();
$db->setQuery('SELECT FOUND_ROWS();');
$totalbonus = $db->loadResult();

/**
 * FreeExtraCheck mini helpers (lists)
 */
function fec_badge_lite($yes, $no){
  $yes = (int)$yes;
  $no  = (int)$no;
  $total = $yes + $no;

  if ($total < 5) return array('key'=>'lowdata', 'text'=>'Low data');

  $yesPct = ($total > 0) ? round(($yes * 100) / $total) : 0;
  $noPct  = 100 - $yesPct;

  if ($noPct >= 40) return array('key'=>'issues', 'text'=>'Issues reported');
  if ($total >= 8 && $yesPct >= 75) return array('key'=>'verified', 'text'=>'Verified');

  return array('key'=>'mixed', 'text'=>'Mixed reports');
}

function fec_render_mini($yes, $no){
  $yes = (int)$yes;
  $no  = (int)$no;

  // se vuoi NASCONDERE quando non ci sono voti:
  // if (($yes + $no) === 0) return '';

  $badge = fec_badge_lite($yes, $no);

  return '
  <div class="subsect fec-mini">
    <span class="fec-mini__badge fec-mini__badge--'.$badge['key'].'">'.htmlspecialchars($badge['text'], ENT_QUOTES, 'UTF-8').'</span>
    <span class="fec-mini__counts">'.$yes.'✓ / '.$no.'✕</span>
  </div>';
}


foreach($rowb1 as $set) {

$db = JFactory::getDbo();

$queryrel = $db->getQuery(true);

$queryrel
    ->select($db->quoteName(array('bonus', 'label','type', 'min_dep','game','bid','related','currency')))
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('related') . ' = '. $db->quote($set['bid']))
    ->where($db->quoteName('status') . ' LIKE "active"');
    
$db->setQuery($queryrel);

$related = $db->loadAssoc();

if(!empty($set['tc']) && $countrycode == 'gb') {
$tctag = '<div class="subsect tc">T&Cs Apply</div>';
} elseif (!empty($set['tc']) && $countrycode == 'se') {
$tctag = '<div class="subsect tc">18+ - Regler & Villkor g&auml;ller2</div>';
} else {
$tctag = '';
}

if ($countrycode === 'gb' && $related['type'] == 'fs' && $related['min_dep'] != '') {
    
$related['label'] = 'Extra Spins';
} else{
    $related['label'] = $related['label'];
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


if($set['type'] == 'nd' || $set['type'] == 'fs' || $set['type'] == 'fp' || $set['type'] == 'fc') {

$perc = '';

} else {

$perc = '<sup class="perc">%</sup>';
$curr = '';

}

if($set['type'] == 'fs' && $set['game'] != '' && $countrycode === 'gb') {

$typetxt = 'ES on '.$set['game'];

}elseif($set['type'] == 'fs' && $set['game'] != '' && $countrycode != 'gb') {

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
$brandcountrytxt2 = 'Welcoming players from '.$countryname.', '.$set['brand'].' ensures a thrilling gaming experience.';


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


//$expiresbk = '<span class="legend expiring">Expires in '.$left->format('%a').' D, '.$left->format('%h').' H, '.$left->format('%i').' Min.</span>';

$now  = new DateTime();
$ends = new DateTime($set['end']);
$left = $now->diff($ends);



if (($set['end'] == '0000-00-00 00:00:00' || $set['end'] == null) && !$related){

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

  // FreeExtraCheck mini for this card (already loaded via JOIN)
  $fecMini = fec_render_mini($set['fec_yes'], $set['fec_no']);
  
 $logo = $set['jr_logosmall'];
  // --- Exclusive pill ---
$exclusivePill = '';
if (fnmatch('yes', $set['exclusive'])) {
  $exclusivePill = '<span class="fec-pill fec-pill--excl">Exclusive</span>';
}

// --- FreeExtraCheck numbers already in query ---
$fec_yes   = (int)$set['fec_yes'];
$fec_no    = (int)$set['fec_no'];
$fec_total = $fec_yes + $fec_no;
$fec_pct   = ($fec_total > 0) ? round(($fec_yes * 100) / $fec_total) : 0;

// --- Badge (list version) ---
$fec_badge_key  = 'lowdata';
$fec_badge_text = 'Low data';

if ($fec_total >= 5) {
  if ($fec_pct >= 75) {
    $fec_badge_key = 'verified';
    $fec_badge_text = 'Verified';
  } elseif ($fec_pct <= 40) {
    $fec_badge_key = 'issues';
    $fec_badge_text = 'Issues';
  } else {
    $fec_badge_key = 'mixed';
    $fec_badge_text = 'Mixed';
  }
}

// micro-copy opzionale (tooltip)
$fec_tip = ($fec_total > 0)
  ? ($fec_yes.' Yes / '.$fec_no.' No ('.$fec_total.' reports)')
  : 'No reports yet';

// --- Counter (render only if data exists) ---
$fec_counter = '';
if ($fec_total > 0) {
  $fec_counter = '
    <div class="fec-card__fact fec-card__fact--fec"
         title="'.htmlspecialchars($fec_tip, ENT_QUOTES, 'UTF-8').'">
        <strong>
    <span class="fec-fb-yes">'.(int)$fec_yes.'</span>
    /
    <span class="fec-fb-no">'.(int)$fec_no.'</span>
  </strong>
      <span>FXCheck™</span>
    </div>';
}

 $output .= '
<div class="fec-card">

  <div class="fec-card__head">

    <img class="fec-card__logo"
         src="/cdn-cgi/image/width=60,height=60,quality=90,format=webp/images/casino-logos/'.$logo.'"
         alt="'.htmlspecialchars($set['brand'], ENT_QUOTES, 'UTF-8').' logo">

    <div class="fec-card__brand">
      <div class="fec-card__brandname">'.htmlspecialchars($set['brand'], ENT_QUOTES, 'UTF-8').'</div>
      <div class="fec-card__type">'.htmlspecialchars($typetxt, ENT_QUOTES, 'UTF-8').'</div>
    </div>

  </div>

  <div class="fec-card__bonus">
    <div class="fec-card__amount">'.$curr.$set['bonus'].$perc.'</div>
    '.(!empty($code)
        ? '<div class="fec-card__game">CODE: <strong>'.$code.'</strong></div>'
        : '<div class="fec-card__game fec-card__game--empty">NO CODE REQUIRED</div>'
      ).'
  </div>

  <div class="fec-card__meta">
    '.(!empty($exclusivePill) ? $exclusivePill : '').'
    <span class="fec-card__check fec-check--'.$fec_badge_key.'"
          title="'.htmlspecialchars($fec_tip, ENT_QUOTES, 'UTF-8').'">
      <span class="fec-check__dot" aria-hidden="true"></span>
      <span class="fec-check__txt">'.htmlspecialchars($fec_badge_text, ENT_QUOTES, 'UTF-8').'</span>
    </span>
  </div>

  <div class="fec-card__facts">
    <div class="fec-card__fact">
      <strong>'.htmlspecialchars($wr, ENT_QUOTES, 'UTF-8').'</strong>
      <span>Wager</span>
    </div>

    <div class="fec-card__fact">
      <strong>'.htmlspecialchars(ucfirst($set['cashable']), ENT_QUOTES, 'UTF-8').'</strong>
      <span>Cashable</span>
    </div>

    '.$fec_counter.'
  </div>


  <div class="fec-card__cta">
    <a href="'.$url.'" class="fec-btn fec-btn--ghost">Details</a>

    <button class="fec-btn fec-btn--primary '.$countryclass.'"
            type="button"
            onclick="window.open(\'' . $play . '\', \'_blank\')">
      '.htmlspecialchars($btnclaim, ENT_QUOTES, 'UTF-8').'
    </button>
  </div>
</div>';

}