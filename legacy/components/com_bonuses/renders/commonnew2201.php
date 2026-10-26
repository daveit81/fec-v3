<?php

defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';

/**
 * FreeExtraCheck mini helpers (lists)
 * - lite badge logic: no recency, no extra queries
 * - outputs a compact row for cards
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

  // se vuoi NASCONDERE quando non ci sono voti, attiva:
  // if (($yes + $no) === 0) return '';

  $badge = fec_badge_lite($yes, $no);

  return '
  <div class="subsect fec-mini">
    <span class="fec-mini__badge fec-mini__badge--'.$badge['key'].'">'.htmlspecialchars($badge['text'], ENT_QUOTES, 'UTF-8').'</span>
    <span class="fec-mini__counts">'.$yes.'✓ / '.$no.'✕</span>
  </div>';
}

// Country detection
$cc_env = getenv('GEOIP_COUNTRY_CODE');
$countrycodeup = $cc_env ? strtoupper(trim($cc_env)) : 'US';
$countrycode   = strtolower($countrycodeup);
$countryname   = isset($countries_array[$countrycodeup]) ? $countries_array[$countrycodeup] : 'your country';

$jinput = JFactory::getApplication()->input;
$type    = $jinput->get('type');
$subtype = $jinput->get('subtype');
$cid     = $jinput->get('cid');
$view    = $jinput->get('view');
$user    = &JFactory::getUser();

// how many records should be displayed on a page?
$records_per_page = 24;

// instantiate the pagination object
$pagination = new Zebra_Pagination();

$getpage = ($pagination->get_page() - 1) * $records_per_page;

// Get a db connection.
$db = JFactory::getDbo();

// Create a new query object.
$query = $db->getQuery(true);

// Base select + join
$query->select(array(
  'SQL_CALC_FOUND_ROWS a.*',
  'b.*',
  // FreeExtraCheck agg (YES/NO counts)
  'COALESCE(agg.yes_count, 0) AS fec_yes',
  'COALESCE(agg.no_count, 0) AS fec_no'
));

$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');

// FreeExtraCheck aggregate table
$query->join('LEFT', $db->quoteName('#__bonus_feedback_agg', 'agg') . ' ON (' . $db->quoteName('agg.bid') . ' = ' . $db->quoteName('a.bid') . ')');

if(isset($_GET['view']) && $_GET['view'] == 'casino') {
  $query->where($db->quoteName('a.cid') . ' = '.$cid);
  $query->where($db->quoteName('a.status') . ' NOT LIKE "pending"');
  $query->order('CASE WHEN '.$db->quoteName('a.status').' LIKE "expired" then 1 else 0 end');
} else {
  $query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
  $query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
  $query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
  $query->where($db->quoteName('a.status') . ' LIKE "active"');
}

$query->where($db->quoteName('a.bonus') . ' > 0');

// Country sorting logic
$query->order('CASE WHEN '.$db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$countrycode.'*%" then 1 else 0 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_ok').' LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_no').' LIKE "" then 0 else 1 end');

$query->order($db->quoteName('a.added') . ' DESC');

if(isset($type)) {
  $query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));
}

if(isset($type) && $type=='fs' && isset($subtype) && $subtype=='nd') {
  $query->where($db->quoteName('a.type').' LIKE '.$db->quote($type).' AND ('.$db->quoteName('a.min_dep').' = "" OR '.$db->quoteName('a.min_dep').' IS NULL)');
}

if(isset($type) && $type=='fs' && isset($subtype) && $subtype=='dr') {
  $query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));
  $query->where($db->quoteName('a.min_dep').' > 0');
}

// Execute
$db->setQuery($query, $getpage, $records_per_page);
$row = $db->loadAssocList();

if(isset($view) && ($view == 'casino')) {
  $counter = $db->setQuery($query);
  $rowcount = $counter->loadAssocList();
}

$db->setQuery('SELECT FOUND_ROWS();');
$total = $db->loadResult();

// pass the total number of records to the pagination class
$pagination->records($total);
$pagination->records_per_page($records_per_page);

$i=1;
foreach($row as $set) {
  $i++;

  $db = JFactory::getDbo();

  // Related (N+1) - unchanged to avoid regressions
  $queryrel = $db->getQuery(true);
  $queryrel
    ->select($db->quoteName(array('bonus', 'label','type', 'game','bid','related','currency')))
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('related') . ' = '. $db->quote($set['bid']))
    ->where($db->quoteName('status') . ' LIKE "active"');

  $db->setQuery($queryrel);
  $related = $db->loadAssoc();

  if ($countrycode === 'gb' && isset($related['type']) && $related['type'] == 'fs' && !empty($related['min_dep'])) {
    $related['label'] = 'Extra Spins';
  }

  if ($countrycode === 'gb' && isset($related['type']) && $related['type'] == 'fc') {
    $related['label'] = 'Extra Cash';
  }

  if(!empty($set['tc']) && $countrycode == 'gb') {
    $tctag = '<div class="subsect tc">T&Cs Apply</div>';
  } elseif (!empty($set['tc']) && $countrycode == 'se') {
    $tctag = '<div class="subsect tc">18+ - Regler & Villkor g&auml;ller</div>';
  } else {
    $tctag = '';
  }

  if(isset($related['type']) && $related['type'] == 'fs') {
    $relcurr = '';
  } else {
    $relcurr = isset($related['currency']) && isset($currency[$related['currency']]) ? $currency[$related['currency']] : '';
  }

  if(isset($related['related']) && $set['bid'] == $related['related']) {
    $relatedtext = '<span class="legend rel ellipsis">+ '.$relcurr.''.$related['bonus'].' '.$related['label'].' '.($related['game']==='' ? ' ' : ' on '. $related['game']).' </span>';
    $relatedrawtext = '+ '.$relcurr.''.$related['bonus'].' '.$related['label'].' '.($related['game']==='' ? ' ' : ' on '. $related['game']).'';
    $relclass = 'exp';
  } else {
    $relatedtext = '';
    $relclass = '';
  }

  $type  = $set['type'];
  $wr    = $set['wr'];
  $wrmeth = ($set['wr_meth'] == 'bd' ? '+d' : $set['wr_meth']);
  $closed = $set['jr_closed'];
  $cid    = $set['cid'];
  $bid    = $set['bid'];

  if($set['link'] === NULL || $set['link'] == '') {
    $play = $set['jr_gourl'];
  } else {
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

  if($set['max_bonus'] > 0) {
    $upto = '<sub class="upto"> up to '.$currency[$set['currency']].$set['max_bonus'].'</sub>';
  }

  if($set['type'] == 'nd' || $set['type'] == 'fs' || $set['type'] == 'fp' || $set['type'] == 'fc') {
    $perc = '';
    $upto = '';
  } else {
    $perc = '<sup class="perc">%</sup>';
    $curr = '';
    $upto = $upto;
  }

  if($set['type'] == 'fs' && $set['game'] != '' && $countrycode === 'gb') {
    $typetxt = 'ES on '.$set['game'];
  } elseif($set['type'] == 'fs' && $set['game'] == '' && $countrycode === 'gb') {
    $typetxt = 'Extra Spins';
  } elseif($set['type'] == 'fs' && $set['game'] != '' && $countrycode != 'gb') {
    $typetxt = 'FS on '.$set['game'];
  } elseif($set['type'] == 'fc' && $countrycode === 'gb') {
    $typetxt = 'Extra Cash';
  } elseif($set['type'] == 'fp') {
    $typetxt = ($set['fp_time_den'] == 'h' ? ''.$set['fp_time'].' Hours' : $set['fp_time'].' Minutes').' '.$bonustype[$set['type']];
  } else {
    $typetxt = $bonustype[$set['type']];
  }

  if (!empty($closed)) {
    $countryclass = 'disabled';
    $btnclaim = 'Closed';
    $countrytxt = '<span class="country closed"><i class="fa fa-circle na"></i> '.$set['brand'].' is closed</span>';
    $status = 'Expired';
  } elseif (fnmatch('*susp*', $set['jr_suspended']) && empty($closed)) {
    $countryclass  = 'disabled';
    $btnclaim = 'Suspended';
    $countrytxt = '<span class="country susp"><i class="fa fa-warning"></i> '.$set['brand'].' is suspended</span>';
    $status = 'N.A.';
  } elseif (fnmatch('*bl*', $set['jr_blacklisted']) && empty($closed)) {
    $countryclass  = 'disabled bl';
    $btnclaim = 'Blacklisted';
    $countrytxt = '<span class="country closed"><i class="fa fa-ban"></i> '.$set['brand'].' is blacklisted</span>';
    $status = 'N.A.';
  } elseif($set['status'] == 'expired' && empty($closed)) {
    $countryclass  = 'disabled';
    $btnclaim = 'Expired';
    $countrytxt = '<span class="country closed"><i class="fa fa-circle na"></i> This bonus has expired</span>';
    $status = 'Expired';
  } elseif (fnmatch('*'.$countrycode.'*', $set['jr_restrictedcountries'])) {
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
    $brandcountrytxt2 = 'Welcoming players from '.$countryname.', '.$set['brand'].' ensures a thrilling gaming experience.';
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

  $now  = new DateTime();
  $ends = new DateTime($set['end']);
  $left = $now->diff($ends);

  if (($set['end'] == '0000-00-00 00:00:00' || $set['end'] == null) && !$related || $set['status'] == 'expired') {
    $expires = '';
    $expclass = '';
  } elseif ($left->format('%a') > 3) {
    $expires = $relatedtext;
    $expclass = $relclass;
  } elseif ($left->format('%a') == 0 && $related) {
    $expires = $relatedtext;
    $expclass = $relclass;
  } elseif ($left->format('%a') <= 3) {
    $expires = '<span class="legend expiring">Expires in '.$left->format('%a').' D, '.$left->format('%h').' H, '.$left->format('%i').' Min.</span>';
    $expclass = 'exp';
  }

  if(isset($_GET['view']) && $_GET['view'] == 'casino' && $set['jr_closed'] != '') {
    $doc = JFactory::getDocument();
    $doc->setMetaData('robots', 'noindex,follow');
  }

  $url = JRoute::_('index.php?option=com_bonuses&view=details&type='.$set['type'].'&bid='.$set['bid'].'&cid='.$set['cid']);
  $reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$set['cid'].'&lang=en&view=article');

  $logo = $set['jr_logosmall'];

  // FreeExtraCheck mini for this card (already loaded via JOIN)
  $fecMini = fec_render_mini($set['fec_yes'], $set['fec_no']);

  // OUTPUT (old design only)
  $output .= '
<div class="fitter">
  <div class="bcard">
    <a class="bcard-a" href="'.$url.'">
      <div class="sect head">
        '.$exclusivetag.'
        <div class="subsect logo">
          <img alt="'.$set['brand'].' Small Logo" class="logo-lists" width="60" height="60" loading="lazy" src="/cdn-cgi/image/width=60,height=60,quality=100,format=webp/images/casino-logos/'.$logo.'">
        </div>
        <div class="ttl-lbl ellipsis">'.$set['brand'].'</div>
        '.$tctag.'
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

    '.$fecMini.'

    <div class="subsect btns">
      <button class="btn-cards ellipsis '.$countryclass.'" onclick="window.open(\'' . $play . '\', \'_blank\')">'.$btnclaim.'</button>
    </div>
  </div>
</div>';
}

?>
