<?php
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.view');

/**
 * HTML View class for the HelloWorld Component
 */
class BonusesViewDetails extends JView
{
	// Overwriting JView display method
	function display($tpl = null)
	{
		// Assign data to the view
		parent::display($tpl);
	}
}

/**
 * NOTE
 * - CSS and inline scripts intentionally omitted (per request).
 * - This file is treated as a view template (tmpl/default.php style).
 * - Keep logic defensive: handle missing bid/result, init arrays, avoid notices.
 */

require_once JPATH_COMPONENT . DS . 'renders' . DS . 'common.php';


// ---------------------------------------------------------------------
// Helpers (guard against redeclare if this template gets included twice)
// ---------------------------------------------------------------------
if (!function_exists('fec_format_date')) {
  function fec_format_date($date) {
    $ts = strtotime((string)$date);
    if (!$ts) return '';
    return date("jS M Y - h:i:s A", $ts);
  }
}

if (!function_exists('fec_split_country_ok')) {
  function fec_split_country_ok($country) {
    include JPATH_COMPONENT . DS . 'helpers' . DS . 'countriesarray.php';
    $country = (string)$country;
    $countryprep = trim($country, '*');
    if ($countryprep === '') return '';
    $list = explode('*', $countryprep);
    $listup = array_map('strtoupper', $list);

    $out = array();
    foreach ($listup as $code) {
      if ($code === '') continue;
      $name = isset($countries_array[$code]) ? $countries_array[$code] : $code;
      $out[] = '<a href="/online-casinos/countries/' . strtolower($code) . '/">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    return implode(', ', $out);
  }
}

if (!function_exists('fec_split_country_no')) {
  function fec_split_country_no($country) {
    include JPATH_COMPONENT . DS . 'helpers' . DS . 'countriesarray.php';
    $country = (string)$country;
    $countryprep = trim($country, '*');
    if ($countryprep === '') return '';
    $list = explode('*', $countryprep);
    $listup = array_map('strtoupper', $list);

    $out = array();
    foreach ($listup as $code) {
      if ($code === '') continue;
      $out[] = isset($countries_array[$code]) ? $countries_array[$code] : $code;
    }
    return implode(', ', array_map(function($v){
      return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }, $out));
  }
}

if (!function_exists('fec_split_gamesok')) {
  function fec_split_gamesok($gametypes) {
    include JPATH_COMPONENT . DS . 'helpers' . DS . 'otherparams.php';
    $gametypes = (string)$gametypes;
    $gamesprep = trim($gametypes, '*');
    if ($gamesprep === '') return '';
    $list = explode('*', $gamesprep);
    $listup = array_map('strtolower', $list);

    $out = array();
    foreach ($listup as $key) {
      if ($key === '') continue;
      $out[] = isset($gametype[$key]) ? $gametype[$key] : $key;
    }
    return implode(', ', array_map(function($v){
      return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }, $out));
  }
}

if (!function_exists('fec_split_banking')) {
  function fec_split_banking($bank) {
    $db = JFactory::getDbo();

    $querybank = $db->getQuery(true)
      ->select(array('text','value'))
      ->from($db->quoteName('#__jreviews_fieldoptions'))
      ->where($db->quoteName('fieldid') . ' = ' . $db->quote('137'))
      ->order($db->quoteName('text') . ' ASC');

    $db->setQuery($querybank);
    $rowbank = $db->loadAssocList();

    $bankarr = array();
    if (is_array($rowbank)) {
      foreach ($rowbank as $bankopt) {
        if (!isset($bankopt['value'])) continue;
        $bankarr[(string)$bankopt['value']] = isset($bankopt['text']) ? (string)$bankopt['text'] : (string)$bankopt['value'];
      }
    }

    $bank = (string)$bank;
    $bankprep = trim($bank, '*');
    if ($bankprep === '') return '';
    $list = explode('*', $bankprep);
    $listup = array_map('strtolower', $list);

    $out = array();
    foreach ($listup as $name) {
      if ($name === '') continue;
      $label = isset($bankarr[$name]) ? $bankarr[$name] : $name;
      $out[] = '<a href="' . JRoute::_('index.php?option=com_jreviews&lang=en&url=tag%2Fbanking%2F'.$name) . '">' .
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
      '</a>';
    }

    return implode(', ', $out);
  }
}

// ---------------------------------------------------------------------
// SEO meta helper: join chunks naturally ("a, b and c")
// ---------------------------------------------------------------------
if (!function_exists('fec_join_chunks')) {
  function fec_join_chunks($chunks) {
    $clean = array_filter(array_map('trim', $chunks));
    if (empty($clean)) return '';
    if (count($clean) === 1) return reset($clean);
    $last = array_pop($clean);
    return implode(', ', $clean) . ' and ' . $last;
  }
}

// ---------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------
$jinput = JFactory::getApplication()->input;
$db     = JFactory::getDbo();

// country code (CF first, fallback empty)
$countrycode = '';
if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
  $cc = strtolower(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
  if (preg_match('/^[a-z]{2}$/', $cc) && $cc !== 'xx') $countrycode = $cc;
}

// ---------------------------------------------------------------------
// Load bonus row
// ---------------------------------------------------------------------
$bid = (int)$jinput->getInt('bid', 0);
if ($bid <= 0) { $bid = (int)$jinput->getInt('id', 0); }

if ($bid <= 0) {
  echo '<div class="alert alert-error">Missing bonus id.</div>';
  return;
}

$query = $db->getQuery(true);

$query->select(array(
  'a.*',
  'c.title AS jr_title',
  'b.jr_closed',
  'b.jr_suspended',
  'b.jr_blacklisted',
  'b.jr_restrictedcountries',
  'b.jr_logosmall',
  'b.jr_gourl',
  'b.jr_flag',
  'b.jr_flagreason',
  'b.jr_flagexpl',
  'COALESCE(agg.yes_count, 0) AS fec_yes',
  'COALESCE(agg.no_count, 0) AS fec_no'
  ,
  // related bonus (EXTRA row where r.related = main.bid)
  'r.bid AS rel_bid',
  'r.cid AS rel_cid',
  'r.type AS rel_type',
  'r.bonus AS rel_bonus',
  'r.max_bonus AS rel_max_bonus',
  'r.currency AS rel_currency',
  'r.game AS rel_game',
  'r.fp_time AS rel_fp_time',
  'r.fp_time_den AS rel_fp_time_den'
));

$query->from($db->quoteName('#__bonuses', 'a'));

// titolo Joomla
$query->join('LEFT', $db->quoteName('#__content', 'c') . ' ON (a.cid = c.id)');

// flag JReviews
$query->join('LEFT', $db->quoteName('#__jreviews_content', 'b') . ' ON (a.cid = b.contentid)');

// FXCheck aggregated counts
$query->join(
  'LEFT',
  $db->quoteName('#__bonus_feedback_agg', 'agg') . ' ON agg.bid = a.bid'
);

// Related bonus (EXTRA row where r.related = main.bid)
// DB rule: the extra bonus stores related = bid of the main bonus.
$query->join(
  'LEFT',
  $db->quoteName('#__bonuses', 'r') .
  ' ON (' . $db->quoteName('r.related') . ' = ' . $db->quoteName('a.bid') .
  " AND " . $db->quoteName('r.status') . " <> 'pending'" .
  ')'
);

// Safety: $bid already normalized above
$query->where('a.bid = ' . (int)$bid);

$db->setQuery($query);
$result = $db->loadAssoc();


if (!$result) {
  echo '<div class="alert alert-error">Bonus not found.</div>';
  return;
}

// Part of (2026-10-21, lib v90): when this row is an extra (related > 0),
// load its main bonus as main_* keys - the same keys the listing query
// carries - so the hero can say which offer these spins belong to.
if (!empty($result['related']) && (int)$result['related'] > 0) {
  try {
    $qMain = $db->getQuery(true)
      ->select(array(
        $db->quoteName('bid') . ' AS main_bid',
        $db->quoteName('type') . ' AS main_type',
        $db->quoteName('bonus') . ' AS main_bonus',
        $db->quoteName('max_bonus') . ' AS main_max_bonus',
        $db->quoteName('currency') . ' AS main_currency',
        $db->quoteName('game') . ' AS main_game',
        $db->quoteName('fp_time') . ' AS main_fp_time',
        $db->quoteName('fp_time_den') . ' AS main_fp_time_den',
      ))
      ->from($db->quoteName('#__bonuses'))
      ->where($db->quoteName('bid') . ' = ' . (int)$result['related'])
      ->where($db->quoteName('status') . ' = ' . $db->quote('active'));
    $db->setQuery($qMain);
    $mainRow = $db->loadAssoc();
    if (is_array($mainRow) && !empty($mainRow['main_bid'])) $result = array_merge($result, $mainRow);
  } catch (Exception $e) {}
}

$ctx = array(
  'countrycode' => $countrycode,
  'countryname' => $countryname,
  'currency'    => $currency,
  'bonustype'   => $bonustype,
  'bonusurl'    => $bonusurl,     // back to list
  'labelurl'    => $labelurl,
  'rowgames'    => $rowgames,
  'providerurl' => $providerurl   // pagina type (free-spins ecc.)
);


// ---------------------------------------------------------------------
// Games/provider (optional)
// ---------------------------------------------------------------------
$gid = isset($result['gid']) ? (string)$result['gid'] : '';
$rowgames = array();
$providerurl = '';
if ($gid !== '') {
  $querygames = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__games'))
    ->where($db->quoteName('status') . ' = ' . $db->quote('1'))
    ->where($db->quoteName('gid') . ' = ' . $db->quote($gid));

  $db->setQuery($querygames);
  $rowgames = (array)$db->loadAssoc();

  if (!empty($rowgames['provider_value'])) {
    $providerval = preg_replace('/[\*]+/', '', (string)$rowgames['provider_value']);
    $providerurl = JRoute::_('index.php?option=com_jreviews&Itemid=426&lang=en&url=tag%2Fsoft%2F'.$providerval);
  }
}

// ---------------------------------------------------------------------
// From the blog: posts linked to this game (fec_blog.php 1.0.11,
// jos_fec_blog_meta.slot_gid = gid). Computed here so the jump chip and
// the section agree; '' when the game has no post or the helper is absent.
// ---------------------------------------------------------------------
$fecBlogForGame = '';
if ($gid !== '') {
  $fecBlogHelper = JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_blog.php';
  if (is_readable($fecBlogHelper)) {
    require_once $fecBlogHelper;
    if (function_exists('fec_blog_render_posts_for_game')) {
      try {
        $fecBlogForGame = fec_blog_render_posts_for_game($db, (int)$gid, array(
          'limit'     => 3,
          'game_name' => !empty($rowgames['game_name']) ? (string)$rowgames['game_name'] : '',
        ));
      } catch (Exception $e) {
        $fecBlogForGame = '';
      }
    }
  }
}

// ---------------------------------------------------------------------
// Core fields
// ---------------------------------------------------------------------
$brand    = isset($result['brand']) ? (string)$result['brand'] : '';
$isbulk   = isset($result['related']) ? (string)$result['related'] : '';
$cid      = isset($result['cid']) ? (int)$result['cid'] : 0;

$bonus    = isset($result['bonus']) ? (string)$result['bonus'] : '';
$type     = isset($result['type']) ? (string)$result['type'] : '';
$label    = isset($result['label']) ? (string)$result['label'] : '';

$labelurl = JRoute::_('index.php?option=com_bonuses&type='.$type.'&view=show');

$curr = (empty($currency[$result['currency']]) ? '$' : $currency[$result['currency']]);

$code      = (empty($result['code']) ? 'Not Required' : (string)$result['code']);
$cashable  = ucfirst((string)(isset($result['cashable']) ? $result['cashable'] : ''));

$wr     = isset($result['wr']) ? (string)$result['wr'] : '';
$wrmeth = (isset($result['wr_meth']) && $result['wr_meth'] !== '' ? (string)$result['wr_meth'] : '');
$wrmeth = ($wrmeth === 'bd' ? 'b+d' : $wrmeth);

$game     = isset($result['game']) ? (string)$result['game'] : '';
$maxbonus = isset($result['max_bonus']) ? (string)$result['max_bonus'] : '';

$tc    = isset($result['tc']) ? (string)$result['tc'] : '';
$tcurl = JRoute::_('index.php?option=com_bonuses&view=tc&cid='.$cid.'&bid='.$bid.'&tmpl=component');

// GB copy tweak (existing rule)
if ($countrycode === 'gb' && $type === 'fs' && !empty($result['min_dep'])) {
  $label = 'Extra Spins';
}

// ---------------------------------------------------------------------
// V2 parity: state machine + expiry pill (render helpers from common.php)
// ---------------------------------------------------------------------
$set = array(
  'status' => (string)$result['status'],
  'end'    => (string)$result['end'],

  'jr_closed'      => (string)$result['jr_closed'],
  'jr_suspended'   => (string)$result['jr_suspended'],
  'jr_blacklisted' => (string)$result['jr_blacklisted'],

  'jr_restrictedcountries' => (string)$result['jr_restrictedcountries'],
  'country_ok' => (string)$result['country_ok'],
  'country_no' => (string)$result['country_no'],
);

$dec = fec_decide_state($set, $countrycode); // ACTIVE | EXPIRED | GEO_BLOCK | UNAVAILABLE | SKIP

$expiryLabel    = fec_expiry_label($set['end']); // null|string
$expiryPillHtml = $expiryLabel ? fec_render_expiry_pill($expiryLabel, $set['end']) : '';

$canPlay = ($dec['state'] === 'ACTIVE');


// ---------------------------------------------------------------------
// SEO / status label based on state machine
// ---------------------------------------------------------------------
$statustitle = ' - FreeExtraChips';
$statusdesc  = '';
$status      = 'Active';
$statusclass = 'act';

switch ($dec['state']) {
  case 'ACTIVE':
    $status = $expiryLabel ? 'Active (ending soon)' : 'Active';
    $statusclass = $expiryLabel ? 'expsoon' : 'act';
    break;

  case 'EXPIRED':
    $statustitle = ' - EXPIRED';
    $statusdesc  = ' This bonus has expired.';
    $status = 'Expired';
    $statusclass = 'exp';
    break;

  case 'GEO_BLOCK':
    $status = 'Restricted in ' . strtoupper($countrycode);
    $statusclass = 'geo';
    break;

  case 'UNAVAILABLE':
    if (!empty($set['jr_closed'])) $status = 'Casino closed';
    elseif (!empty($set['jr_suspended'])) $status = 'Casino suspended';
    else $status = 'Unavailable';
    $statusclass = 'unavail';
    break;

  case 'SKIP':
    $status = 'Blacklisted';
    $statusclass = 'black';
    break;
}

// ---------------------------------------------------------------------
// Bonus formatting (legacy rules kept, but made safe)
// ---------------------------------------------------------------------
$bonusDisplay = $bonus;
if ($type === 'nd' || $type === 'fp' || $type === 'fc') {
  $bonusDisplay = $curr . $bonus;
} elseif ($type === 'fs') {
  $bonusDisplay = $bonus;
} else {
  $bonusDisplay = $bonus . '%';
}

// WR display
$wrDisplay = $wr;
if ($wrDisplay !== '') {
  if (($type === 'nd' || $type === 'fp' || $type === 'fs' || $type === 'rb') && $wrmeth === '') {
    $wrDisplay = $wrDisplay . 'X(b)';
  } elseif (($type === 'nd' || $type === 'fp' || $type === 'fs' || $type === 'rb') && $wrmeth !== '') {
    $wrDisplay = $wrDisplay . 'X(' . $wrmeth . ')';
  } elseif ($wrmeth === '') {
    $wrDisplay = $wrDisplay . 'X(b+d)';
  } else {
    $wrDisplay = $wrDisplay . 'X(' . $wrmeth . ')';
  }
} else {
  $wrDisplay = 'Not stated';
}

// ---------------------------------------------------------------------
// Related / bulk (defensive)
// ---------------------------------------------------------------------
$related = null;
$bulkb   = null;

$queryrel = $db->getQuery(true)
  ->select($db->quoteName(array('bonus','label','min_dep','type','game','bid','related','currency','cid')))
  ->from($db->quoteName('#__bonuses'))
  ->where($db->quoteName('related') . ' = ' . $db->quote($bid))
  ->where($db->quoteName('status') . ' LIKE "active"');

$db->setQuery($queryrel);
$tmpRel = $db->loadAssoc();
if (is_array($tmpRel) && !empty($tmpRel)) $related = $tmpRel;

if (!empty($isbulk)) {
  $querybulk = $db->getQuery(true)
    ->select($db->quoteName(array('bonus','max_bonus','min_dep','label','type','game','bid','related','currency','cid')))
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('bid') . ' = ' . $db->quote($isbulk))
    ->where($db->quoteName('status') . ' LIKE "active"');

  $db->setQuery($querybulk);
  $tmpBulk = $db->loadAssoc();
  if (is_array($tmpBulk) && !empty($tmpBulk)) $bulkb = $tmpBulk;
}

// Currency for related (needs to exist before any related promo text)
$relcurr = '';
if (is_array($related) && isset($related['type'], $related['currency'])) {
  $relcurr = ($related['type'] === 'fs') ? '' : (isset($currency[$related['currency']]) ? $currency[$related['currency']] : '');
}

// ---------------------------------------------------------------------
// Default meta tags — DATA-DRIVEN VERSION (no code in title or description)
// Type coverage: nd, fc (free chip), fp (free play), fs (free spins),
// anything else = deposit match (legacy default ramp).
// ---------------------------------------------------------------------

// Helper: month + year for freshness signal
// 2026-10-21: the month follows the page's real freshness - the latest of
// listing added, listing modified and the last approved player report -
// and only within 90 days of it. date('F Y') stamped every page with the
// current month: "(October 2026)" over a byline saying "Published Jan 9,
// 2026". Older than 90 days: no month in the title. The byline (lib v88)
// prints the same report date, so title and signature agree.
$freshTs = 0;
foreach (array('added', 'modified') as $freshKey) {
  if (!empty($result[$freshKey]) && $result[$freshKey] !== '0000-00-00 00:00:00') {
    $freshT = strtotime((string)$result[$freshKey]);
    if ($freshT) $freshTs = max($freshTs, $freshT);
  }
}
try {
  $qFresh = $db->getQuery(true)
    ->select('MAX(' . $db->quoteName('created_at') . ')')
    ->from($db->quoteName('#__bonus_feedback'))
    ->where($db->quoteName('bid') . ' = ' . (int)$bid)
    ->where($db->quoteName('moderation_status') . ' = ' . $db->quote('approved'));
  $db->setQuery($qFresh);
  $freshReport = (string)$db->loadResult();
  if ($freshReport !== '' && $freshReport !== '0000-00-00 00:00:00') {
    $freshT = strtotime($freshReport);
    if ($freshT) $freshTs = max($freshTs, $freshT);
  }
} catch (Exception $e) {}
$monthYear = ($freshTs > 0 && (time() - $freshTs) <= 90 * 86400) ? date('F Y', $freshTs) : '';
$stamp = ($monthYear !== '') ? ' (' . $monthYear . ')' : '';

// Helper: wagering chunk
$wrChunk = '';
if (!empty($result['wr'])) {
  $wrChunk = $result['wr'] . 'x wagering';
  if ($wrmeth !== '') {
    $wrChunk .= ' on ' . ($wrmeth === 'b' ? 'bonus' : 'bonus + deposit');
  }
}

// Helper: max cashout chunk
$maxCashChunk = '';
if (!empty($result['max_cashout'])) {
  $maxCashChunk = $curr . $result['max_cashout'] . ' max cashout';
}

// Helper: bonus code presence (without revealing it)
$codeRequiredChunk = '';
if (!empty($result['code']) && strtolower(trim($result['code'])) !== 'not required') {
  $codeRequiredChunk = 'bonus code required';
}

// ===== Type-specific generation =====
if ($type === 'nd' || $type === 'fc') {
  // NO DEPOSIT / FREE CHIP (semantically equivalent for SERP)
  $titleWagerHint = !empty($result['wr']) ? ' | ' . $result['wr'] . 'x Wagering' : '';

  $titletag = trim(
    $brand . ' ' . $bonusDisplay . ' ' . $label .
    $stamp . $titleWagerHint . ' | FreeExtraChips'
  );

  $descChunks = array($wrChunk, $maxCashChunk, 'no deposit required', $codeRequiredChunk);
  $descriptiontag = 'Claim ' . $brand . "'s " . $bonusDisplay . ' ' . strtolower($label) .
    '. ' . ucfirst(fec_join_chunks($descChunks)) . '. New players only.' . $statusdesc;

} elseif ($type === 'fp') {
  // FREE PLAY (timed bankroll)
  $fpTime   = isset($result['fp_time']) ? (string)$result['fp_time'] : '';
  $fpDen    = (isset($result['fp_time_den']) && $result['fp_time_den'] === 'h') ? 'Hour' : 'Min';
  $fpWindow = $fpTime . ' ' . $fpDen . (((int)$fpTime) > 1 ? 's' : '');

  $titletag = trim(
    $brand . ' ' . $bonusDisplay . ' ' . $fpWindow . ' ' . $label .
    $stamp . ' | FreeExtraChips'
  );

  $descChunks = array($wrChunk, 'time-limited trial bonus', $codeRequiredChunk);
  $descriptiontag = 'Play ' . $brand . "'s " . $bonusDisplay . ' free play in ' . $fpWindow .
    '. ' . ucfirst(fec_join_chunks($descChunks)) . '.' . $statusdesc;

} elseif ($type === 'fs') {
  // FREE SPINS (deposit or no-deposit)
  $fsNoDep   = empty($result['min_dep']) ? ' No Deposit' : '';
  $gameChunk = !empty($game) ? ' on ' . $game : '';

  $titletag = trim(
    $brand . ' ' . $bonusDisplay . ' ' . $label . $fsNoDep . $gameChunk .
    $stamp . ' | FreeExtraChips'
  );

  $descChunks = array($wrChunk, $maxCashChunk);
  if (empty($result['min_dep'])) $descChunks[] = 'no deposit required';
  if ($codeRequiredChunk !== '') $descChunks[] = $codeRequiredChunk;

  $descriptiontag = 'Get ' . $bonusDisplay . ' ' . strtolower($label) . $gameChunk .
    ' at ' . $brand .
    '. ' . ucfirst(fec_join_chunks($descChunks)) . '.' . $statusdesc;

} elseif ($type === 'rb') {
  // RAKEBACK (rb, 2026-10-20): a share of every wager paid back, win or lose.
  // The boost window lives in the free-play timer columns (lib v78 helper);
  // the wagering, when any, is on the rakeback itself.
  $rbWin   = function_exists('fec_rb_window_label') ? fec_rb_window_label($result, 'plain') : '';
  $rbWrChunk = '';
  if (isset($result['wr']) && trim((string)$result['wr']) !== '' && is_numeric($result['wr'])) {
    $rbWrChunk = ((float)$result['wr'] == 0.0)
      ? 'no wagering on the rakeback'
      : $result['wr'] . 'x wagering on the rakeback';
  }
  $minDepChunk = !empty($result['min_dep']) ? $curr . $result['min_dep'] . ' min deposit' : '';
  $upToChunk   = !empty($maxbonus) ? ' up to ' . $curr . $maxbonus : '';

  $titletag = trim(
    $brand . ' ' . $bonusDisplay . ' ' . $label . $upToChunk . ($rbWin !== '' ? ' for ' . ucwords($rbWin) : '') .
    $stamp . ' | FreeExtraChips'
  );

  $descChunks = array('paid back on every wager, win or lose', $rbWrChunk, $minDepChunk, $codeRequiredChunk);
  $descriptiontag = 'Claim ' . $brand . "'s " . $bonusDisplay . ' ' . strtolower($label) . $upToChunk .
    ($rbWin !== '' ? ' for ' . $rbWin : '') .
    '. ' . ucfirst(fec_join_chunks($descChunks)) . '.' . $statusdesc;

} else {
  // DEPOSIT MATCH (default fall-through)
  $upToChunk   = !empty($maxbonus) ? ' up to ' . $curr . $maxbonus : '';
  $minDepChunk = !empty($result['min_dep']) ? $curr . $result['min_dep'] . ' min deposit' : '';

  $titletag = trim(
    $brand . ' ' . $bonusDisplay . ' ' . $label . $upToChunk .
    $stamp . ' | FreeExtraChips'
  );

  $descChunks = array($wrChunk, $minDepChunk, $codeRequiredChunk);
  $descriptiontag = 'Claim ' . $brand . "'s " . $bonusDisplay . ' ' . strtolower($label) . $upToChunk .
    '. ' . ucfirst(fec_join_chunks($descChunks)) . '.' . $statusdesc;
}

// Length safety: description max ~160, title max ~95
if (strlen($descriptiontag) > 160) {
  $descriptiontag = substr($descriptiontag, 0, 157) . '...';
}
if (strlen($titletag) > 95) {
  // fallback strategy: drop the monthYear chunk if too long
  if ($stamp !== '') $titletag = str_replace($stamp, '', $titletag);
}

$doc = JFactory::getDocument();
$doc->setTitle($titletag);
$doc->setMetaData('description', $descriptiontag);
fec_emit_social_meta($result, $titletag, $descriptiontag);

// ---------------------------------------------------------------------
// Meta overrides per-bid (SEO surgery)
// File esterno: helpers/meta_overrides_bid.php
// Ritorna array [bid => ['title' => ..., 'desc' => ...]]
// Se il bid non è presente o il file manca, si usano i default sopra.
// ---------------------------------------------------------------------
$overrideFile = JPATH_COMPONENT . DS . 'helpers' . DS . 'meta_overrides_bid.php';
if (is_file($overrideFile)) {
  $overridesBid = include $overrideFile;
  if (is_array($overridesBid) && isset($overridesBid[$bid])) {
    $ov = $overridesBid[$bid];

    if (!empty($ov['title'])) {
      $titletag = (string)$ov['title'];
      $doc->setTitle($titletag);
    }
    if (!empty($ov['desc'])) {
      $descriptiontag = (string)$ov['desc'];
      $doc->setMetaData('description', $descriptiontag);
    }
  }
}

// Apply noindex,follow if bonus state requires it (centralized in fec_common_lib.php)
fec_apply_noindex_if_needed($result, $countrycode);

// ---------------------------------------------------------------------
// FreeExtraCheck / FXCheck helpers (kept, but cleaned up)
// ---------------------------------------------------------------------
if (!function_exists('fec_get_check_agg')) {
  function fec_get_check_agg($bid, $debug = false) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
      ->select(array(
        $db->quoteName('yes_count'),
        $db->quoteName('no_count'),
        $db->quoteName('updated_at')
      ))
      ->from($db->quoteName('#__bonus_feedback_agg'))
      ->where($db->quoteName('bid') . ' = ' . (int)$bid);

    if ($debug) {
      error_log('FEC CHECK SQL: ' . (string)$query);
    }

    $db->setQuery($query);
    $row = $db->loadAssoc();

    if ($db->getErrorNum()) {
      error_log('FEC DB ERROR: ' . $db->getErrorMsg());
    }

    if (!$row) {
      return array('yes'=>0,'no'=>0,'total'=>0,'yes_pct'=>0,'updated_at'=>null);
    }

    $yes = (int)$row['yes_count'];
    $no  = (int)$row['no_count'];
    $total = $yes + $no;
    $pct = ($total > 0) ? (int)round(($yes * 100) / $total) : 0;

    return array(
      'yes' => $yes,
      'no' => $no,
      'total' => $total,
      'yes_pct' => $pct,
      'updated_at' => $row['updated_at']
    );
  }
}

if (!function_exists('fec_time_ago')) {
  function fec_time_ago($sqlDate) {
    if (!$sqlDate) return '';
    $ts = strtotime((string)$sqlDate);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    $mins = (int)floor($diff / 60);
    if ($mins < 60) return $mins . ' min ago'; // "min" is an abbreviation, no plural
    $hrs = (int)floor($mins / 60);
    if ($hrs < 24)   return $hrs    . ' hour'  . ($hrs    === 1 ? '' : 's') . ' ago';
    $days = (int)floor($hrs / 24);
    if ($days < 30)  return $days   . ' day'   . ($days   === 1 ? '' : 's') . ' ago';
    $months = (int)floor($days / 30);
    if ($months < 12) return $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
    $years = (int)floor($months / 12);
    return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
  }
}

if (!function_exists('fec_get_last_vote_at')) {
  function fec_get_last_vote_at($bid, $voteValue) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
      ->select($db->quoteName('created_at'))
      ->from($db->quoteName('#__bonus_feedback'))
      ->where($db->quoteName('bid') . ' = ' . (int)$bid)
      ->where($db->quoteName('vote') . ' = ' . (int)$voteValue)
      ->order($db->quoteName('created_at') . ' DESC');

    $db->setQuery($query, 0, 1);
    $date = $db->loadResult();
    return $date ? $date : null;
  }
}

if (!function_exists('fec_has_voted')) {
  function fec_has_voted($bid) {
    if (empty($_COOKIE['fec_voter'])) return false;
    $voter_key = hash('sha256', $_COOKIE['fec_voter']);

    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
      ->select('1')
      ->from($db->quoteName('#__bonus_feedback'))
      ->where($db->quoteName('bid') . ' = ' . (int)$bid)
      ->where($db->quoteName('voter_key') . ' = ' . $db->quote($voter_key));

    $db->setQuery($query, 0, 1);
    return (bool)$db->loadResult();
  }
}

if (!function_exists('fec_badge_for_bonus')) {
  function fec_badge_for_bonus($check, $lastYesAt = null, $lastNoAt = null) {
    $total = isset($check['total']) ? (int)$check['total'] : 0;
    $yes   = isset($check['yes']) ? (int)$check['yes'] : 0;
    $no    = isset($check['no']) ? (int)$check['no'] : 0;

    if ($total < 5) return array('key' => 'lowdata', 'text' => 'Low data');

    $yesPct = ($total > 0) ? (int)round(($yes * 100) / $total) : 0;
    $noPct  = 100 - $yesPct;

    $now = time();
    $lastNoTs = $lastNoAt ? strtotime((string)$lastNoAt) : 0;
    $daysSinceNo = $lastNoTs ? (int)floor(($now - $lastNoTs) / 86400) : 9999;

    if ($noPct >= 40 || $daysSinceNo <= 7) return array('key' => 'issues', 'text' => 'Issues reported');
    if ($total >= 8 && $yesPct >= 75 && $daysSinceNo > 14) return array('key' => 'verified', 'text' => 'Verified');
    return array('key' => 'mixed', 'text' => 'Mixed reports');
  }
}

if (!function_exists('fec_render_badge_html')) {
  function fec_render_badge_html($badge) {
    if (!is_array($badge)) return '';

    $key  = isset($badge['key'])  ? preg_replace('/[^a-z0-9_-]/i', '', (string)$badge['key']) : '';
    $text = isset($badge['text']) ? (string)$badge['text'] : '';

    $textEsc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if ($key === '' || $textEsc === '') return '';

    switch ($key) {
      case 'verified': $tip = 'Based on consistent successful claims'; break;
      case 'issues':   $tip = 'Recent failed claims reported'; break;
      case 'mixed':    $tip = 'Mixed user outcomes reported'; break;
      case 'lowdata':  $tip = 'Not enough reports yet'; break;
      default:         $tip = 'Based on FreeExtraCheck™ user reports';
    }

    $tagMap = array('verified'=>'tag-ok', 'mixed'=>'tag-warn', 'lowdata'=>'tag-mute', 'issues'=>'tag-bad');
    $tagClass = isset($tagMap[$key]) ? $tagMap[$key] : 'tag-mute';
    $tipEsc = htmlspecialchars($tip, ENT_QUOTES, 'UTF-8');

    return '<span class="tag '.$tagClass.'" title="'.$tipEsc.'">' . $textEsc . '</span>';
  }
}

// Build FXCheck data once
$check     = fec_get_check_agg($bid);
$yes       = (int)$check['yes'];
$no        = (int)$check['no'];
$total     = (int)$check['total'];
$show_stats = ($total >= 5);

$lastYesAt = fec_get_last_vote_at($bid, 1);
$lastNoAt  = fec_get_last_vote_at($bid, 0);

$badge     = fec_badge_for_bonus($check, $lastYesAt, $lastNoAt);
$badgeHtml = fec_render_badge_html($badge);

$freshLine = '';
if ($lastYesAt) $freshLine = fec_time_ago($lastYesAt); // solo "8 hours ago"

$ctx['freshLine'] = $freshLine;

// Canonical casino list url (back target)
$ctx['casinobonusurl'] = JRoute::_('index.php?option=com_bonuses&cid='.$cid.'&view=casino');

$vm = fec_build_vm_from_row($result, $ctx);


// ---------------------------------------------------------------------
// Breadcrumb (DETAILS) — Home > Bonuses > {Casino} > {Bonus title}
// no type node, no trailing id
// ---------------------------------------------------------------------
$uri  = JUri::getInstance();
$siteBase = $uri->toString(array('scheme','host','port'));
if (!$siteBase) $siteBase = rtrim(JUri::root(false), '/');

// titolo bonus (fallback safe)
$bonusTitle = '';
if (!empty($vm['bonusTitle'])) {
  $bonusTitle = (string)$vm['bonusTitle'];
} elseif (!empty($vm['title'])) {
  $bonusTitle = (string)$vm['title'];
} else {
  $bonusTitle = !empty($result['jr_title']) ? (string)$result['jr_title'] : 'Bonus details';
}
// extra safety: rimuovi eventuale "-id1234"
$bonusTitle = preg_replace('/\s*-?\s*id\d+\s*$/i', '', $bonusTitle);

// casino name + url (usa quella "back target" che già hai in ctx)
$casinoName = !empty($vm['brand']) ? (string)$vm['brand'] : (!empty($result['brand']) ? (string)$result['brand'] : 'Casino');
$casinoUrl  = !empty($ctx['casinobonusurl']) ? (string)$ctx['casinobonusurl'] : (rtrim($siteBase,'/') . '/bonuses/');

$items = array(
  array('name' => 'Home',      'url' => rtrim($siteBase,'/') . '/'),
  array('name' => 'Bonuses',   'url' => rtrim($siteBase,'/') . '/bonuses/'),
  array('name' => $casinoName, 'url' => $casinoUrl),
  array('name' => $bonusTitle, 'url' => ''), // ultimo non linkato
);

// Se il VM ha un titolo più ricco, sovrascrive l'ultimo nodo
if (!empty($vm['title'])) {
  $last = count($items) - 1;
  if ($last >= 0) {
    $items[$last]['name'] = $vm['title'];
    $items[$last]['url']  = '';
  }
}

$GLOBALS['fec_breadcrumb_html']   = fec_render_breadcrumb_html($items);
$GLOBALS['fec_breadcrumb_jsonld'] = fec_render_breadcrumb_jsonld($items);


$alreadyVoted = fec_has_voted($bid);

// ---------------------------------------------------------------------
// Watchouts (now AFTER badge is known)
// ---------------------------------------------------------------------
$watchouts = array();
if ($dec['state'] === 'GEO_BLOCK') $watchouts[] = 'Country restrictions apply for your location.';
if (empty($result['wr'])) $watchouts[] = 'Wagering is not clearly stated.';
else {
  $wrNum = (float)$result['wr'];
  if ($wrNum >= 40) $watchouts[] = 'High wagering requirement.';
}
if (!empty($result['code'])) $watchouts[] = 'Bonus code required.';
if (!empty($expiryLabel)) $watchouts[] = 'Short validity window.';
if (!empty($result['max_cashout']) && !empty($result['bonus'])) {
  $max = (float)$result['max_cashout'];
  $bon = (float)$result['bonus'];
  if ($max > 0 && $bon > 0 && $max < ($bon * 2)) $watchouts[] = 'Max cashout may cap the real value.';
}
if (is_array($badge) && isset($badge['key']) && $badge['key'] === 'issues') $watchouts[] = 'FXCheck shows recent failed claims.';

// ---------------------------------------------------------------------
// Content blocks
// ---------------------------------------------------------------------
$casinobonusurl = JRoute::_('index.php?option=com_bonuses&cid='.$cid.'&view=casino');

// Additional infos: sanitize to avoid inline JS / unsafe HTML
$infos = isset($result['additional_infos']) ? (string)$result['additional_infos'] : '';
if ($infos !== '') {
  try {
    $filter = JFilterInput::getInstance();
    $infos = $filter->clean($infos, 'HTML');
  } catch (Exception $e) {
    // fallback: minimal
    $infos = strip_tags($infos, '<p><br><ul><ol><li><strong><em><b><i><a>');
  }
}

// Author card (from helpers/authors.php)
$authorCard = '';
if (function_exists('fec_render_bonus_author_card')) {
  $authorCard = fec_render_bonus_author_card($bid);
}

// ---------------------------------------------------------------------
// FXRate: welcome package band (fec_fxrate_render.php 1.0.0). '' when this
// bonus is not a leg of an active package or the helper is absent; the
// helper adds its own stylesheet. Computed here so the jump chip and the
// band under the hero agree.
// ---------------------------------------------------------------------
$fecFxrateBand = '';
$fecFxrateHelper = JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_fxrate_render.php';
if (is_readable($fecFxrateHelper)) {
  require_once $fecFxrateHelper;
  if (function_exists('fec_fxrate_render_band')) {
    try {
      $fecFxrateBand = fec_fxrate_render_band($db, (int)$bid, array('brand' => $brand));
    } catch (Exception $e) {
      $fecFxrateBand = '';
    }
  }
}

// ---------------------------------------------------------------------
// Interactive blocks per type (2026-10-20). Decision Notes and the Wagering
// Calculator model a bonus BALANCE (a match or a no-deposit amount) against
// a deposit; a rakeback (rb) is a share of every wager, not a balance, so
// both would compute on a number that means something else. Skipped for rb
// the way they are for timed free play. The jump chips follow these flags,
// so no chip points at a block that is not on the page.
// ---------------------------------------------------------------------
$dnOn   = !in_array($type, array('fp', 'fs', 'rb'), true);
$wcalOn = !in_array($type, array('fp', 'rb'), true);

// ---------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------
?>
<?php
// ---------------------------------------------------------------------
// MARKUP ONLY — FEC V2 style skeleton
// No inline CSS, no inline JS
// ---------------------------------------------------------------------
?>
<?php
if (!empty($GLOBALS['fec_breadcrumb_html']))   echo $GLOBALS['fec_breadcrumb_html'];
if (!empty($GLOBALS['fec_breadcrumb_jsonld'])) echo $GLOBALS['fec_breadcrumb_jsonld'];

// Flag banner ("under review") — same component as the casino review page
// (.cr-flag-banner, amber). Shown when the parent casino is flagged: the
// bonus stays claimable but the visitor sees the caution before the hero.
$bonusCasinoFlagged = isset($result['jr_flag'])
  && strpos((string) $result['jr_flag'], '*flagged*') !== false;
if ($bonusCasinoFlagged) {
  $flagBody = isset($result['jr_flagexpl']) ? trim((string) $result['jr_flagexpl']) : '';
  if ($flagBody === '') {
    $flagBody = isset($result['jr_flagreason']) ? trim((string) $result['jr_flagreason']) : '';
  }
if ($flagBody !== '') {
    $fEsc = htmlspecialchars($flagBody, ENT_QUOTES, 'UTF-8');
    echo '<div class="wrap">'
       .   '<section class="cr-flag-banner" role="alert">'
       .     '<div class="cr-flag-banner__inner">'
       .       '<span class="cr-flag-banner__icon" aria-hidden="true">&#9888;</span>'
       .       '<div class="cr-flag-banner__text">'
       .         '<h2 class="cr-flag-banner__title">' . htmlspecialchars($casinoName, ENT_QUOTES, 'UTF-8') . ' is under review</h2>'
       .         '<div class="cr-flag-banner__body">' . $fEsc . '</div>'
       .       '</div>'
       .     '</div>'
       .   '</section>'
       . '</div>';
  }
}
?>
<!-- HERO -->
<?php echo fec_render_detail_hero_v2($vm); ?>

<?php if ($fecFxrateBand !== ''): ?>
<?php echo $fecFxrateBand; ?>
<?php endif; ?>

<div class="wrap">
  <nav class="bd-jumpto" aria-label="Jump to">
    <span class="lbl">Jump to</span>
    <div class="chips">
<?php if ($fecFxrateBand !== ''): ?>
      <a class="chip" href="#fxrate">Package</a>
<?php endif; ?>
      <a class="chip" href="#key-facts">Key facts</a>
      <a class="chip" href="#risks">Watch out</a>
      <a class="chip" href="#reports">Reports</a>
<?php if ($dnOn): ?>
      <a class="chip" href="#for-you">For you</a>
<?php endif; ?>
      <a class="chip" href="#decision-notes">Decision notes</a>
<?php if ($wcalOn): ?>
      <a class="chip" href="#calculator">Calculator</a>
<?php endif; ?>
<?php if ($fecBlogForGame !== ''): ?>
      <a class="chip" href="#from-the-blog">Blog</a>
<?php endif; ?>
    </div>
  </nav>
</div>

<?php echo fec_render_fxcheck_panel_html($vm, array('reports_limit' => 5)); ?>

<div class="wrap">
  <div class="bd-stats">
<?php echo fec_render_keyfacts_card($vm); ?>
<?php echo fec_render_watchouts_card($vm); ?>
<?php echo fec_render_specifics_card($vm); ?>
<?php echo fec_render_eligibility_limits_card($vm); ?>
  </div>
<?php // NEW — disclosure box (only renders for multi-tier or opaque slots) ?>
<?php if (!empty($gid)) fec_render_slot_disclosure_box($gid); ?>  
</div>

<?php
// ---------------------------------------------------------------------
// Interactive Decision Notes band (rule table v1.0)
// Renders ABOVE the signed editorial note: the engine scopes the note
// to the reader profile, it never replaces the editorial judgment.
// Operator status (R0 override) is read from jReviews by cid with the
// verified fieldoption values (*bl* / *susp* / *flagged*). Skipped for
// timed free play. The band self-hides client side when core fields
// are missing; isset() guards keep it safe pre-migration.
// Skipped for timed free play AND free spins: fs winnings do not exist
// before the spins pay out, and spin count is never money - a verdict
// computed on it would be invented data. fs support lands in v1.1 with
// winnings-aware messaging.
// ---------------------------------------------------------------------
if ($dnOn) {
  require_once JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_dn_widget.php';
  fec_dn_add_assets();

  $dnOpStatus = 'ok';
  $dnCid = isset($result['cid']) ? (int)$result['cid'] : 0;
  if ($dnCid > 0) {
    $dnQ = $db->getQuery(true)
      ->select('jr_flag, jr_suspended, jr_blacklisted')
      ->from($db->quoteName('#__jreviews_content'))
      ->where('contentid = ' . $dnCid);
    $db->setQuery($dnQ, 0, 1);
    $dnRow = $db->loadAssoc();
    if (is_array($dnRow)) {
      $dnBl   = strtolower(trim(trim((string)$dnRow['jr_blacklisted']), '*'));
      $dnSusp = strtolower(trim(trim((string)$dnRow['jr_suspended']), '*'));
      $dnFlag = strtolower(trim(trim((string)$dnRow['jr_flag']), '*'));
      if (in_array($dnBl, array('bl', 'blacklisted', 'yes'), true))        $dnOpStatus = 'blacklisted';
      elseif (in_array($dnSusp, array('susp', 'suspended', 'yes'), true))  $dnOpStatus = 'suspended';
      elseif ($dnFlag === 'flagged')                                       $dnOpStatus = 'flagged';
    }
  }

  $dnType = in_array($type, array('nd', 'fc'), true) ? 'nd' : 'match';
  $dnAttrs = array(
    'type'     => $dnType,
    'bid'      => (string)$bid,
    'bonus'    => ($bonus !== '' && $bonus !== null) ? (string)$bonus : '',
    'wr'       => (isset($result['wr']) && $result['wr'] !== null && $result['wr'] !== '') ? (string)(int)$result['wr'] : '',
    'wr-meth'  => (isset($result['wr_meth']) && $result['wr_meth'] === 'bd') ? 'bd' : 'b',
    'cashable' => (isset($result['cashable']) && strtolower((string)$result['cashable']) === 'no') ? 'no' : 'yes',
    'op-status' => $dnOpStatus
  );
  if ($maxbonus !== '' && $maxbonus !== null) $dnAttrs['max-bonus'] = (string)$maxbonus;
  if (!empty($result['min_dep'])) $dnAttrs['min-dep'] = (string)$result['min_dep'];
  if (!empty($result['max_bet'])) $dnAttrs['max-bet'] = (string)$result['max_bet'];
  $dnCapKind = isset($result['max_cashout_kind']) ? trim((string)$result['max_cashout_kind']) : '';
  if (!empty($result['max_cashout'])) {
    $dnAttrs['max-cashout'] = (string)$result['max_cashout'];
    if ($dnCapKind !== '') $dnAttrs['max-cashout-kind'] = $dnCapKind;
  }

  echo '<div class="wrap" id="for-you">';
  fec_dn_render_widget($dnAttrs);
  echo '</div>';
}
?>
<?php echo fec_render_additional_details($result); ?>

<?php
// ---------------------------------------------------------------------
// FEC Wagering Calculator embed (F3)
// Prefills the widget from the bonus record via data-attributes.
// Skipped for timed free play (fp): different math, not modeled yet.
// max_cashout is prefiled ONLY when max_cashout_kind is classified -
// unclassified legacy caps must never be treated as absolute amounts.
// Both new columns arrive via a.* once fec_wcal_migration.sql has run;
// isset() guards keep this block safe if it has not run yet.
// ---------------------------------------------------------------------
if ($wcalOn) {
  require_once JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_wcal_widget.php';
  fec_wcal_add_assets();

  $wcalType = ($type === 'fs')
    ? 'fs'
    : (in_array($type, array('nd', 'fc'), true) ? 'nd' : 'match');

  $wcalAttrs = array(
    'brand'    => $brand,
    'label'    => $label,
    'type'     => $wcalType,
    'bid'      => (string)$bid,
    'wr'       => (isset($result['wr']) && $result['wr'] !== null && $result['wr'] !== '') ? (string)(int)$result['wr'] : '',
    'wr-meth'  => (isset($result['wr_meth']) && $result['wr_meth'] === 'bd') ? 'bd' : 'b',
    'cashable' => (isset($result['cashable']) && strtolower((string)$result['cashable']) === 'no') ? 'no' : 'yes'
  );

  // bonus: match = percentage, nd = amount, fs = spin count.
  // For fs the widget uses it as CONTEXT only (placeholder and hint) and
  // never as the winnings value - spins are not money until they pay out.
  if ($bonus !== '') $wcalAttrs['bonus'] = (string)$bonus;
  if ($maxbonus !== '' && $maxbonus !== null) $wcalAttrs['max-bonus'] = (string)$maxbonus;
  if (!empty($result['min_dep'])) $wcalAttrs['min-dep'] = (string)$result['min_dep'];
  if (!empty($result['max_bet'])) $wcalAttrs['max-bet'] = (string)$result['max_bet'];

  $wcalCapKind = isset($result['max_cashout_kind']) ? trim((string)$result['max_cashout_kind']) : '';
  if ($wcalCapKind !== '' && !empty($result['max_cashout'])) {
    $wcalAttrs['max-cashout']      = (string)$result['max_cashout'];
    $wcalAttrs['max-cashout-kind'] = $wcalCapKind;
  }
  if (!empty($result['wager_validity_days'])) {
    $wcalAttrs['validity-days'] = (string)(int)$result['wager_validity_days'];
  }
  // wcal 1.6.0 (2026-10-21): the allowed and restricted game lists, as the
  // Eligibility card prints them, so the contribution select can disable
  // what the terms exclude (table, live, video poker) instead of offering
  // the generic 10% / 5% / 20% for games this bonus cannot be played on.
  if (function_exists('fec_vm_split_games')) {
    $wcalOk = !empty($result['gamesok']) ? fec_vm_split_games($result['gamesok']) : array();
    $wcalNo = !empty($result['gamesno']) ? fec_vm_split_games($result['gamesno']) : array();
    if (!empty($wcalOk)) $wcalAttrs['games-ok'] = implode('|', $wcalOk);
    if (!empty($wcalNo)) $wcalAttrs['games-no'] = implode('|', $wcalNo);
  }

  echo '<div class="wrap" id="calculator">';
  echo '<h2 class="fec-wcal-h2">What this bonus really takes</h2>';
  fec_wcal_render_widget($wcalAttrs);
  echo '</div>';
}
?>

<?php
$publishedAt = isset($result['added'])    ? (string)$result['added']    : '';
$modifiedAt  = isset($result['modified']) ? (string)$result['modified'] : '';
echo fec_render_bonus_author_card((int)$bid, $publishedAt, $modifiedAt);
?>

<?php if ($fecBlogForGame !== '') echo $fecBlogForGame; ?>

<?php echo fec_render_email_capture_block('bonus_detail', array(
  'variant'  => 'inline',
  'heading'  => 'Get bonuses like this every week',
  'subhead'  => "We curate the week's best, verified by hand.",
)); ?>

<?php
// The Active-bonuses section carries the Telegram CTA inside itself as its
// footer row (lib v67). Standalone fallback ONLY when the brand has no
// other active bonuses to show — which is exactly when an alert is worth
// the most to the visitor.
$fecMoreHtml = fec_render_more_bonuses_from_casino($db, $cid, $bid, $countrycode, $countryname, $bonustype, $currency, 3);
echo $fecMoreHtml;
if ($fecMoreHtml === '' && function_exists('fec_render_tg_alert_cta')) {
  $fecTgCta = fec_render_tg_alert_cta((int)$cid, isset($vm['brand']) ? $vm['brand'] : '');
  if ($fecTgCta !== '') {
    echo '<div class="wrap"><div class="fec-tgcta-row">' . $fecTgCta . '</div></div>';
  }
}
?>

<?php echo fec_render_similar_offers($db, $result, $countrycode, $countryname, $bonustype, $currency, 3); ?>

<?php echo fec_render_detail_footer_nav($result, $cid); ?>
<script>
(function(){
  // endpoints
  var AJAX_FEEDBACK = "<?php echo JUri::root(); ?>components/com_bonuses/ajax/bonus_feedback.php";

  // ---------- utils ----------
  function validName(s){
    return /^[a-zA-Z0-9_]{3,20}$/.test((s||'').trim());
  }

  function smoothToId(id){
    var el = document.getElementById(id);
    if (!el) { location.hash = id; return; }
    try { el.scrollIntoView({behavior:'smooth', block:'start'}); }
    catch(e){ location.hash = id; }
  }

  function setReportsStatus(root, text){
    var st = root ? root.querySelector('.bd-reports-status') : null;
    if (st) st.textContent = text || '';
  }

  function encodeForm(obj){
    var s = [];
    for (var k in obj){
      if (!Object.prototype.hasOwnProperty.call(obj, k)) continue;
      s.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]));
    }
    return s.join('&');
  }

  function safeRemove(el){
    if (!el) return;
    if (typeof el.remove === 'function') el.remove();
    else if (el.parentNode) el.parentNode.removeChild(el);
  }

  // Disabilita anche <a> (che non ha "disabled")
  function setDisabled(el, disabled){
    if (!el) return;

    if ('disabled' in el) {
      el.disabled = !!disabled;
    } else {
      if (disabled) {
        el.setAttribute('aria-disabled', 'true');
        if (el.classList) el.classList.add('is-disabled');
      } else {
        el.removeAttribute('aria-disabled');
        if (el.classList) el.classList.remove('is-disabled');
      }
    }
  }

  // Aggiorna label correttamente sia per <input> che per <button>/<a>
  function setMoreLabel(btn, text){
    if (!btn) return;
    if (btn.tagName === 'INPUT') btn.value = text;
    else btn.textContent = text;
  }

  // OFFSET: legge data-offset dalla LISTA; se manca lo calcola contando gli item già stampati
  function getInitialOffset(list){
    var off = parseInt(list.getAttribute('data-offset'), 10);
    if (!isFinite(off) || off < 0) {
      off = list.querySelectorAll('.bd-report[data-id]').length;
      list.setAttribute('data-offset', String(off));
    }
    return off;
  }

  // ---------- stats toggle (delegation, works for any FXCheck) ----------
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.bd-whystats-toggle');
    if (!btn) return;

    var wrap = btn.closest('.bd-whystats');
    if (!wrap) return;

    var panel = wrap.querySelector('.bd-whystats-panel');
    if (!panel) return;

    var open = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    panel.hidden = open;
  });


// ---------- Reports "Load more" (delegation) ----------
(function(){

  // bind una sola volta anche se lo script viene caricato 2 volte
  if (window.__FEC_REPORTS_MORE_BOUND) return;
  window.__FEC_REPORTS_MORE_BOUND = true;

  function safeRemove(el){
    if (!el) return;
    if (typeof el.remove === 'function') el.remove();
    else if (el.parentNode) el.parentNode.removeChild(el);
  }

  function getOffset(list, btn){
    var off = parseInt(list.getAttribute('data-offset') || '', 10);

    if (!isFinite(off) || off < 0) {
      off = parseInt(btn.getAttribute('data-offset') || '', 10);
    }

    if (!isFinite(off) || off < 0) {
      // fallback: conta quanti item ci sono già
      off = list.querySelectorAll('.bd-report').length;
    }

    if (!isFinite(off) || off < 0) off = 0;

    // sincronizza SEMPRE
    list.setAttribute('data-offset', String(off));
    btn.setAttribute('data-offset', String(off));

    return off;
  }

  // capture = true così giri prima di eventuali listener vecchi,
  // e li blocchi con stopImmediatePropagation
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.bd-reports-more');
    if (!btn) return;

    e.preventDefault();
    e.stopImmediatePropagation();
    e.stopPropagation();

    var root = btn.closest('.bd-reports');
    if (!root) return;

    var list = root.querySelector('.bd-reports-list');
    if (!list) return;

    var bid = list.getAttribute('data-bid') || btn.getAttribute('data-bid');
    if (!bid) return;

    // anti-doppio click / anti-doppio handler
    if (btn.__fecLoading) return;
    btn.__fecLoading = true;

    var offset = getOffset(list, btn);

    var limit  = parseInt(btn.getAttribute('data-limit') || '5', 10);
    if (!isFinite(limit) || limit <= 0) limit = 5;

    btn.disabled = true;
    setReportsStatus(root, 'Loading…');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AJAX_FEEDBACK, true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');

    xhr.onreadystatechange = function(){
      if (xhr.readyState !== 4) return;

      btn.disabled = false;
      btn.__fecLoading = false;

      var r;
      try { r = JSON.parse(xhr.responseText); }
      catch(err){
        setReportsStatus(root, 'Could not load more reports.');
        return;
      }

      console.log('reports_more', {req:{bid:bid, offset:offset, limit:limit}, res:r});

      if (!r || !r.ok) {
        setReportsStatus(root, 'Could not load more reports.');
        return;
      }

      var html = (r.html || '').trim();
      if (!html) {
        setReportsStatus(root, '');
        safeRemove(btn);
        return;
      }

      // dedupe per data-id
      var existing = {};
      var existingEls = list.querySelectorAll('.bd-report[data-id]');
      for (var i=0; i<existingEls.length; i++){
        var id = existingEls[i].getAttribute('data-id');
        if (id) existing[id] = true;
      }

      var tmp = document.createElement('div');
      tmp.innerHTML = html;

      var appendedItems = 0;

      while (tmp.firstChild) {
        var node = tmp.firstChild;

        // scarta text nodes
        if (node.nodeType !== 1) {
          tmp.removeChild(node);
          continue;
        }

        var isItem = (node.classList && node.classList.contains('bd-report'));
        if (isItem) {
          var nid = node.getAttribute('data-id');
          if (nid && existing[nid]) {
            tmp.removeChild(node);
            continue;
          }
          if (nid) existing[nid] = true;
        }

        list.appendChild(node);
        if (isItem) appendedItems++;
      }

      // aggiorna offset in modo coerente col backend
      var shown = parseInt(r.shown, 10);
      if (!isFinite(shown) || shown < 0) shown = appendedItems;

      var newOffset = offset + shown;
      list.setAttribute('data-offset', String(newOffset));
      btn.setAttribute('data-offset', String(newOffset));

      // aggiorna label o rimuovi bottone
      var remaining = parseInt(r.remaining, 10);
      if (!isFinite(remaining)) remaining = 0;
      remaining = Math.max(0, remaining);

      // aggiorna counter "Showing X of Y" in footer
      var total = parseInt(r.total, 10);
      if (!isFinite(total) || total < 0) total = newOffset + remaining;
      var metaEl = root.querySelector('.bd-reports-foot .meta');
      if (metaEl) metaEl.textContent = 'Showing ' + newOffset + ' of ' + total;

      if (remaining <= 0) {
        setReportsStatus(root, '');
        safeRemove(btn);
        return;
      }

      var nextN = Math.min(limit, remaining);
      btn.textContent = 'Show ' + nextN + ' more';

      setReportsStatus(root, '');
    };

    xhr.send(encodeForm({
      action: 'reports_more',
      bid: bid,
      offset: offset,
      limit: limit,
      t: Date.now() // anti-cache proxy/plugin
    }));
  }, true);

})();

  // ---------- FXCheck init ----------
  function initFXCheck(box){
    if (box.__fecInit) return;
    box.__fecInit = true;

    var bid = box.getAttribute('data-bid');
    if (!bid) return;

    var reportsId = 'reports';
    var panel = box.closest('.bd-hero-card, section') || document;

    var vote = null;

    var btns   = box.querySelectorAll('.bd-vote-actions [data-vote]');
    var nameEl = box.querySelector('.bd-vote-name');
    var commEl = box.querySelector('.bd-vote-comment');
    var submit = box.querySelector('.bd-vote-submit');

    var msg = box.querySelector('.bd-vote-msg');

    var yesEl = panel.querySelector('.fec-yes');
    var noEl  = panel.querySelector('.fec-no');
    var pctEl = panel.querySelector('.fec-pct');
    var updEl = panel.querySelector('.fec-updated');

    var verifiedEl = box.querySelector('.bd-vote-verified');

    var actionsWrap = box.querySelector('.bd-vote-actions');
    var formWrap    = box.querySelector('.bd-vote-form');

    var statsToggle = panel.querySelector('.bd-whystats-toggle');
    var statsPanel  = panel.querySelector('.bd-whystats-panel');

    function setMsg(html, type){
      if (!msg) return;
      msg.innerHTML = html || '';
      msg.classList.remove('is-bubble','is-success','is-error','is-locked','is-info');
      if (!html) return;

      msg.classList.add('is-bubble');
      if (type === 'success') msg.classList.add('is-success');
      else if (type === 'error') msg.classList.add('is-error');
      else if (type === 'locked') msg.classList.add('is-locked');
      else msg.classList.add('is-info');
    }

    function canSubmit(){
      if (!submit || !nameEl) return;
      submit.disabled = !(vote && validName(nameEl.value));
    }

    function hideClaimUI(){
      if (actionsWrap) actionsWrap.style.display = 'none';
      if (formWrap) formWrap.style.display = 'none';

      for (var i=0; i<btns.length; i++){
        btns[i].disabled = true;
      }
      if (submit) submit.disabled = true;
      if (nameEl) nameEl.disabled = true;
      if (commEl) commEl.disabled = true;
    }

    function openStats(){
      if (!statsToggle || !statsPanel) return;
      statsToggle.setAttribute('aria-expanded', 'true');
      statsPanel.hidden = false;
    }

    function setVerifiedLine(){
      if (!verifiedEl) return;
      verifiedEl.hidden = false;
      verifiedEl.style.display = 'inline';
      verifiedEl.innerHTML = 'Verified by <strong>FXCheck™</strong>';
    }

    function linkToReports(text, type){
      setMsg(text + ' <a class="bd-see-reports" href="#' + reportsId + '">See reports</a>', type);
    }

    // vote buttons
    for (var i=0; i<btns.length; i++){
      (function(b){
        b.addEventListener('click', function(){
          vote = b.getAttribute('data-vote');
          for (var j=0; j<btns.length; j++){
            btns[j].classList.remove('is-selected');
          }
          b.classList.add('is-selected');
          setMsg('Selection saved. Add your name and submit.', 'info');
          canSubmit();
        });
      })(btns[i]);
    }

    if (nameEl) nameEl.addEventListener('input', canSubmit);
    if (submit) submit.addEventListener('click', post);

    box.addEventListener('click', function(e){
      var a = e.target.closest('.bd-see-reports');
      if (!a) return;
      e.preventDefault();
      smoothToId(reportsId);
    });

    function post(){
      if (!vote) { setMsg('Select YES or NO first.', 'error'); return; }
      if (!nameEl || !validName(nameEl.value)) {
        setMsg('Name: 3–20 chars (letters, numbers, underscore).', 'error');
        return;
      }

      var xhr = new XMLHttpRequest();
      xhr.open('POST', AJAX_FEEDBACK, true);
      xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');

      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;

        try {
          var r = JSON.parse(xhr.responseText);

          if (r.ok && r.agg) {
            if (yesEl) yesEl.textContent = r.agg.yes;
            if (noEl)  noEl.textContent  = r.agg.no;

            var total = (r.agg.total || 0);
            if (pctEl) pctEl.textContent = (total >= 5) ? r.agg.yes_pct : '';

            if (updEl && r.agg.last_yes_ago) updEl.textContent = r.agg.last_yes_ago;

            setVerifiedLine();

            // Prepend del nuovo row alla reports list (solo se visibile lato filtro server)
            if (r.report_html && !r.duplicate) {
              var reportsSec = document.getElementById(reportsId);
              var rList = reportsSec ? reportsSec.querySelector('.bd-reports-list') : null;
              if (rList) {
                var emp = rList.querySelector('.bd-reports-empty');
                if (emp && emp.parentNode) emp.parentNode.removeChild(emp);

                var tmpC = document.createElement('div');
                tmpC.innerHTML = r.report_html;
                var newRow = tmpC.firstElementChild;
                if (newRow) rList.insertBefore(newRow, rList.firstChild);

                // increment data-offset (next "Show more" salta anche questo row)
                var lOff = parseInt(rList.getAttribute('data-offset') || '0', 10) || 0;
                rList.setAttribute('data-offset', String(lOff + 1));
                var moreBtn = reportsSec.querySelector('.bd-reports-more');
                if (moreBtn) {
                  var bOff = parseInt(moreBtn.getAttribute('data-offset') || '0', 10) || 0;
                  moreBtn.setAttribute('data-offset', String(bOff + 1));
                }

                // Update "Showing X of Y" footer
                var metaEl = reportsSec.querySelector('.bd-reports-foot .meta');
                if (metaEl && typeof r.total_after === 'number') {
                  var nShown = rList.querySelectorAll('.bd-report').length;
                  metaEl.textContent = 'Showing ' + nShown + ' of ' + r.total_after;
                }
              }
            }

            if (r.duplicate) linkToReports('Submission locked. You\u2019ve already submitted for this bonus.', 'locked');
            else if (r.comment_status === 'pending') linkToReports('Thanks! Submission saved. Your comment is under review.', 'success');
            else if (r.comment_status === 'rejected') linkToReports('Thanks! Submission saved. Your comment won\u2019t be published.', 'success');
            else linkToReports('Thanks! Submission saved.', 'success');

            if (vote === 'no') {
              openStats();
              setTimeout(function(){ smoothToId(reportsId); }, 150);
            }

            hideClaimUI();
            return;
          }

          if (r.error === 'bad_username') setMsg('Name: 3\u201320 chars (letters, numbers, underscore).', 'error');
          else if (r.error === 'rate_limited') setMsg('Too many submissions from your network. Try again later.', 'error');
          else setMsg('Error saving feedback. Try again.', 'error');

        } catch(e) {
          setMsg('Error saving feedback. Try again.', 'error');
        }
      };

      var body =
        'bid=' + encodeURIComponent(bid) +
        '&vote=' + encodeURIComponent(vote) +
        '&username=' + encodeURIComponent(nameEl.value.trim()) +
        '&comment=' + encodeURIComponent((commEl && commEl.value ? commEl.value.trim() : ''));

      xhr.send(body);
    }
  }

  // ---------- card -> details vote handoff ----------
  // The hub bonus card (fec_render_hub_bonus_card) deep-links here with the
  // pre-chosen outcome in the URL hash (#fxvote=yes|no). Reuse the widget's own
  // click handler so selection + canSubmit() fire exactly as a manual tap would,
  // then drop the user on the widget with the name field focused. Pure progressive
  // enhancement: no hash, no widget, or already-voted -> it does nothing.
  function fxHandoff(){
    var m = (location.hash || '').match(/fxvote=(yes|no)/i);
    if (!m) return;
    var box = document.querySelector('.bd-vote[data-bid]');
    if (!box || box.getAttribute('data-voted') === '1') return;
    var btn = box.querySelector('.bd-vote-actions [data-vote="' + m[1].toLowerCase() + '"]');
    if (btn) btn.click();
    try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    catch (e) { box.scrollIntoView(); }
    var name = box.querySelector('.bd-vote-name');
    if (name) { try { name.focus({ preventScroll: true }); } catch (e) { name.focus(); } }
  }

  function boot(){
    var boxes = document.querySelectorAll('.bd-vote[data-bid]');
    for (var i=0; i<boxes.length; i++){
      initFXCheck(boxes[i]);
    }
    fxHandoff();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>

<script>
(function(){
  var bar  = document.querySelector('.fec-cta-sticky');
  var hero = document.querySelector('.bd-hero');
  if (!bar || !hero) return;

  // helper: keep page from being covered when bar is visible
  function setOn(on){
    bar.classList.toggle('is-on', !!on);
    document.body.classList.toggle('has-sticky-cta', !!on);
  }

  // IntersectionObserver: bar ON when hero is NOT intersecting
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries){
      var e = entries[0];
      setOn(!e.isIntersecting);
    }, {
      root: null,
      threshold: 0.01
    });

    io.observe(hero);
  } else {
    // fallback old browsers
    function onScroll(){
      var r = hero.getBoundingClientRect();
      setOn(r.bottom <= 0);
    }
    onScroll();
    window.addEventListener('scroll', onScroll, {passive:true});
  }

  // Bonus: hide bar when keyboard opens (focus in forms)
  document.addEventListener('focusin', function(e){
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) {
      bar.classList.remove('is-on');
    }
  });
  document.addEventListener('focusout', function(){
    // it will re-evaluate on next IO callback / scroll
  });

})();
</script>

<script>
/* TOC scrollspy: highlight the .hub-toc link matching the current section as the
   user scrolls. Without this, the .active class set by markup (always on the
   first link) does not update during scroll. */
(function(){
  var toc = document.querySelector('.bd-notes .hub-toc');
  if (!toc) return;
  var linkNodes = toc.querySelectorAll('a[href^="#"]');
  if (!linkNodes.length) return;

  var entries = [];
  for (var i = 0; i < linkNodes.length; i++) {
    var href = linkNodes[i].getAttribute('href');
    var id   = href ? href.substring(1) : '';
    var sec  = id ? document.getElementById(id) : null;
    if (sec) entries.push({ link: linkNodes[i], sec: sec });
  }
  if (!entries.length) return;

  function setActive(el) {
    for (var i = 0; i < linkNodes.length; i++) linkNodes[i].classList.remove('active');
    el.classList.add('active');
  }

  var ticking = false;
  function update() {
    /* threshold must be > scroll-margin-top (110px) to avoid jitter at section boundaries */
    var threshold = 130;
    var current = entries[0].link;
    for (var i = 0; i < entries.length; i++) {
      var rect = entries[i].sec.getBoundingClientRect();
      if (rect.top <= threshold) current = entries[i].link;
      else break; /* sections are in DOM order; stop once we're past the viewport */
    }
    setActive(current);
    ticking = false;
  }

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(update);
      ticking = true;
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    update();
  } else {
    document.addEventListener('DOMContentLoaded', update);
  }
})();
</script>

<?php echo fec_render_faq_enhancer_assets(); ?>
<?php echo fec_render_code_copy_assets(); ?>
<?php fec_render_bonus_offer_jsonld($result, $vm); ?>