<?php

defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';


include_once("geoip/geoip.inc");



$countrycode = strtolower(getenv(GEOIP_COUNTRY_CODE));
$countrycodeup = getenv(GEOIP_COUNTRY_CODE);
$countryname = $countries_array[$countrycodeup];


$jinput = JFactory::getApplication()->input;

$fq = $_GET['fq'];
$rq = $_GET['rq'];
$meth = $_GET['meth'];
$refmeth = $_GET['refmeth'];
$curr = $_GET['curr'];
$filter = JRequest::getVar('filter');
$type = JRequest::getVar('type');
$refine = JRequest::getVar('refine');

// how many records should be displayed on a page?
$records_per_page = 24;

// instantiate the pagination object
$pagination = new Zebra_Pagination();


$getpage = ($pagination->get_page() - 1) * $records_per_page;

// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query->select(array('SQL_CALC_FOUND_ROWS a.*', 'b.*'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');

switch($filter) {
case 'software':
$query->where($db->quoteName('b.jr_soft') . ' LIKE "%*'.trim($fq).'*%"');
break;

case 'wr':
$query->where($db->quoteName('a.wr') . ' = '.$fq.' AND '.$db->quoteName('a.wr_meth').' LIKE "'.$meth.'"');
break;

case 'exclusive':
$query->where($db->quoteName('a.exclusive') . ' LIKE "'.$fq.'"');
break;

case 'cashable':
$query->where($db->quoteName('a.cashable') . ' LIKE "'.$fq.'"');
break;

case 'minimum-deposit':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.min_dep').' LIKE "'.$fq.'"');
} else {
$query->where($db->quoteName('a.min_dep') . ' LIKE "'.$fq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;

case 'minimum-cashout':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.min_cashout').' LIKE "'.$fq.'"');
} else {
$query->where($db->quoteName('a.min_cashout') . ' LIKE "'.$fq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;

case 'maximum-cashout':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.max_cashout').' LIKE "'.$fq.'"');
} else {
$query->where($db->quoteName('a.max_cashout') . ' LIKE "'.$fq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;

case 'allowed-countries':
$query->where('('.$db->quoteName('a.country_ok').' LIKE "%*'.$fq.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "") AND '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$fq.'*%"');
$query->where($db->quoteName('b.jr_restrictedcountries').' NOT LIKE "%*'.$fq.'*%"');
break;

case 'restricted-countries':
$query->where('('.$db->quoteName('a.country_ok').' LIKE "%*'.$fq.'*%" OR '.$db->quoteName('a.country_ok').' NOT LIKE "") OR '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$fq.'*%"');
//$query->where($db->quoteName('a.country_no').' LIKE "%*'.$fq.'*%" OR '.$db->quoteName('a.country_no').' LIKE ""');
$query->where($db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$fq.'*%"');
break;


case 'code':
if($fq == 'required') {
$query->where($db->quoteName('a.code').' NOT LIKE ""');
} else {
$query->where($db->quoteName('a.code').' LIKE ""');
}
break;

case 'max-bonus':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.max_bonus').' LIKE "'.$fq.'"');
} else {
$query->where($db->quoteName('a.max_bonus') . ' LIKE "'.$fq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}

break;
case 'currency':
if(isset($fq) && ($fq == 'btc')) {
    $query->where('('.$db->quoteName('a.currency').' LIKE "btc" OR '.$db->quoteName('a.currency').' LIKE "mbtc" OR '.$db->quoteName('a.bankok').' LIKE "%bitcoin%")');
} else {
$query->where('('.$db->quoteName('a.currency').' LIKE "'.$fq.'")');
}
break;
}

switch($refine) {
case 'software':
$query->where($db->quoteName('b.jr_soft') . ' LIKE "%*'.trim($rq).'*%"');
break;

case 'wr':
$query->where($db->quoteName('a.wr') . ' = '.$rq);
$query->where($db->quoteName('a.wr_meth') . ' LIKE "'.$meth.'"');
break;

case 'exclusive':
$query->where($db->quoteName('a.exclusive') . ' LIKE "'.$rq.'"');
break;

case 'cashable':
$query->where($db->quoteName('a.cashable') . ' LIKE "'.$rq.'"');
break;

case 'minimum-deposit':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.min_dep').' LIKE "'.$rq.'"');
} else {
$query->where($db->quoteName('a.min_dep') . ' LIKE "'.$rq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;

case 'minimum-cashout':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.min_cashout').' LIKE "'.$rq.'"');
} else {
$query->where($db->quoteName('a.min_cashout') . ' LIKE "'.$rq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;

case 'maximum-cashout':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.max_cashout').' LIKE "'.$fq.'"');
} else {
$query->where($db->quoteName('a.max_cashout') . ' LIKE "'.$rq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;

case 'allowed-countries':
$query->where('('.$db->quoteName('a.country_ok').' LIKE "%*'.$rq.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "") AND '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$rq.'*%"');
$query->where($db->quoteName('b.jr_restrictedcountries').' NOT LIKE "%*'.$rq.'*%"');
break;

case 'restricted-countries':
$query->where($db->quoteName('a.country_no').' LIKE "%*'.$rq.'*%" OR '.$db->quoteName('a.country_no').' LIKE ""');
$query->where($db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$rq.'*%"');
break;


case 'code':
if($rq == 'required') {
$query->where($db->quoteName('a.code').' NOT LIKE ""');
} else {
$query->where($db->quoteName('a.code').' LIKE ""');
}
break;

case 'max-bonus':
if($curr == '') {
$query->where('('.$db->quoteName('a.currency').' LIKE "usd" OR '.$db->quoteName('a.currency').' LIKE "") AND '.$db->quoteName('a.max_bonus').' LIKE "'.$rq.'"');
} else {
$query->where($db->quoteName('a.max_bonus') . ' LIKE "'.$rq.'" AND '.$db->quoteName('a.currency').' LIKE "'.$curr.'"');
}
break;
}

$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->order('CASE WHEN '.$db->quoteName('a.status').' LIKE "expired" then 1 else 0 end');
$query->order('CASE WHEN '.$db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$countrycode.'*%" then 1 else 0 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_ok').' LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_no').' LIKE "" then 0 else 1 end');
$query->order($db->quoteName('a.added') . ' DESC');
    
    
    
if(isset($_GET['type']) && $_GET['type'] != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($_GET['type']));

}

// Reset the query using our newly populated query object.
$db->setQuery($query,$getpage,$records_per_page);

$row = $db->loadAssocList();

$db->setQuery('SELECT FOUND_ROWS()');
$total = $db->loadResult();

$db->setQuery($query);
$all = $db->loadAssocList();

foreach($all as $key) {

$values[] = array("exclusive"=>$key['exclusive'], "code"=>$key['code'], "software"=>count($key['jr_soft']), "wager"=>$key['wr'], "cashable"=>$key['cashable'], "maxbonus"=>$key['max_bonus'], "mindep"=>$key['min_dep'], "mincash"=>$key['min_cashout'], "maxcash"=>$key['max_cashout']);

}

//echo '<pre>';
//print_r($exclusive);
//echo '</pre>';
//print_r(array_count_values(array_column($exclusive, 'val')));

$countmaxb = array_count_values(array_column($values, 'maxbonus'));
$countmind = array_count_values(array_column($values, 'mindep'));
$countmincash = array_count_values(array_column($values, 'mincash'));
$countmaxcash = array_count_values(array_column($values, 'maxcash'));



$countvalues = array("maxbonus"=>$countmaxb, "mindep"=>$countmind, "mincash"=>$countmincash, "maxcash"=>$countmaxcash);

// pass the total number of records to the pagination class
$pagination->records($total);

// records per page
$pagination->records_per_page($records_per_page);



foreach($row as $set) {

$db = JFactory::getDbo();

$queryrel = $db->getQuery(true);

$queryrel
    ->select($db->quoteName(array('bonus', 'label','type', 'game','bid','related','currency')))
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('related') . ' = '. $db->quote($set['bid']))
    ->where($db->quoteName('status') . ' LIKE "active"');
    
$db->setQuery($queryrel);

$related = $db->loadAssoc();

if ($countrycode === 'gb' && $related['type'] == 'fs' && $related['min_dep'] !== '') {
    
$related['label'] = 'Extra Spins';

} else{
    $related['label'] = $related['label'];
}

if ($countrycode === 'gb' && $related['type'] == 'fc') {
    
$related['label'] = 'Extra Cash';
} else{
    $related['label'] = $related['label'];
}

if(!empty($set['tc']) && $countrycode == 'gb') {
$tctag = '<div class="subsect tc">T&Cs Apply</div>';
} elseif (!empty($set['tc']) && $countrycode == 'se') {
$tctag = '<div class="subsect tc">18+ - Regler & Villkor g√§ller</div>';
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
$btnclaim = 'Play';
$countrytxt = '<span class="country"><i class="fa fa-circle ok"></i> Players from '.$countryname.' allowed</span>';
$brandcountrytxt = 'accepts players from '.$countryname;


} else {

$countryclass = '';
$btnclaim = 'Play';
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

 // FreeExtraCheck mini for this card (already loaded via JOIN)
  $fecMini = fec_render_mini($set['fec_yes'], $set['fec_no']);
  
  
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
      <span>FXCheck¢‚</span>
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

?>