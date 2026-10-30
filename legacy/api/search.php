<?php
// /v2/api/search.php (PHP 5.6 safe)
// Global search: Bonuses + Casino Reviews (catid=8)
// Optional: ?debug=1

define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/..'));
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$app = JFactory::getApplication('site');
$app->initialise();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$db = JFactory::getDbo();
$prefix = method_exists($db, 'getPrefix') ? $db->getPrefix() : 'jos_';

$tblBonuses  = $prefix . 'bonuses';
$tblContent  = $prefix . 'content';
$tblJreviews = $prefix . 'jreviews_content';

/* -----------------------
   Input
----------------------- */
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$q = preg_replace('/\s+/', ' ', $q);
if (function_exists('mb_strlen') && mb_strlen($q) > 64) $q = mb_substr($q, 0, 64);

$debug = isset($_GET['debug']) ? (string)$_GET['debug'] : '';

$len = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
$minLenOk = ($q !== '' && $len >= 2);

// LIKE safe (collation *_general_ci)
$like   = $db->Quote('%' . $db->escape($q, false) . '%');
$qExact = $db->Quote($db->escape($q, false));

// Normalized query (for special tokens/pills)
$qNorm = strtolower(trim($q));
$qNorm = preg_replace('/\s+/', ' ', $qNorm);

/* -----------------------
   Token / pill detection
----------------------- */
// wr=0 token (from pill)
$isNoWagering = in_array($qNorm, array(
  'wr=0','wr 0','no wagering','no-wagering','wagering free','wagering-free'
), true);

// wr<=N token (from pill: "wr<=20")
$isLowWagering = false;
$wrMax = 0;
if (preg_match('/^wr\s*<=\s*(\d{1,3})$/', $qNorm, $m) === 1) {
  $isLowWagering = true;
  $wrMax = (int) $m[1];
}
// optional text fallback
if (!$isLowWagering && in_array($qNorm, array('low wagering','low-wagering','low wr','low wager'), true)) {
  $isLowWagering = true;
  $wrMax = 20;
}

$isCode = in_array($qNorm, array(
  'code','bonus code','with code','has code','promo code','coupon'
), true);

$isExclusive = in_array($qNorm, array(
  'exclusive','exclusives','exclusive bonus','exclusive bonuses'
), true);

$isCrypto = in_array($qNorm, array(
  'crypto','cryptocurrency','bitcoin','btc','eth','ethereum'
), true);

/* -----------------------
   Helpers
----------------------- */
/**
 * Normalize SEF URLs generated inside /api context.
 * Removes ONLY the technical "/api/" segment.
 */
function fec_normalize_api_url($url) {
  $url = (string) $url;
  if ($url === '') return $url;

  // force leading slash for relative paths
  if ($url[0] !== '/' && strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
    $url = '/' . $url;
  }

  // remove ONLY "/api/" segment (keeps /v2/ or other env prefixes)
  $url = str_replace('/api/', '/', $url);

  // safety: avoid leading //
  $url = preg_replace('#^//+#', '/', $url);

  return $url;
}

function fec_bonus_meta($row) {
  $meta = array();

  // WR tags (prefer numeric)
  $wrRaw = isset($row['wr']) ? $row['wr'] : null;
  $wrNum = null;
  if ($wrRaw !== null && $wrRaw !== '' && is_numeric(str_replace('x', '', (string)$wrRaw))) {
    $wrNum = (float) str_replace('x', '', (string)$wrRaw);
  }

  if ($wrNum !== null) {
    if ($wrNum == 0) $meta[] = 'No wagering';
    else if ($wrNum > 0 && $wrNum <= 20) $meta[] = 'Low wagering';
  } else {
    // legacy exact string 0
    if (isset($row['wr']) && (string)$row['wr'] === '0') $meta[] = 'No wagering';
  }

  // NOTE: meta "Crypto" qui è best-effort; il filtro crypto reale è su b.jr_currencies
  $currency = isset($row['currency']) ? $row['currency'] : '';
  $type     = isset($row['type']) ? $row['type'] : '';
  $hay = strtolower($currency . ' ' . $type);

  if (strpos($hay, 'btc') !== false || strpos($hay, 'eth') !== false || strpos($hay, 'crypto') !== false) $meta[] = 'Crypto';
  if (strpos($hay, 'fs') !== false || strpos($hay, 'spin') !== false) $meta[] = 'Free spins';

  if (!empty($row['code'])) $meta[] = 'Code';
  if (!empty($row['exclusive']) && (string)$row['exclusive'] === 'yes') $meta[] = 'Exclusive';
  if (!empty($row['max_cashout']) && is_numeric($row['max_cashout'])) $meta[] = 'Cashout cap';

  if (!empty($row['status']) && (string)$row['status'] === 'expired') $meta[] = 'Expired';

  $meta[] = 'FXCheck';

  $meta = array_values(array_unique($meta));
  return array_slice($meta, 0, 4);
}

/* -----------------------
   DEBUG
----------------------- */
if ($debug === '1') {
  $db->setQuery("SELECT COUNT(*) FROM `$tblBonuses`");
  $total = (int) $db->loadResult();

  $sql = "SELECT bid, cid, label, code, status, exclusive, wr FROM `$tblBonuses` ORDER BY bid DESC LIMIT 10";
  $db->setQuery($sql);
  $rows = $db->loadAssocList();
  if (!is_array($rows)) $rows = array();

  $out = array(
    'debug' => 1,
    'php' => defined('PHP_VERSION') ? PHP_VERSION : '',
    'prefix' => $prefix,
    'tables' => array(
      'bonuses' => $tblBonuses,
      'content' => $tblContent,
      'jreviews' => $tblJreviews,
    ),
    'q' => $q,
    'qNorm' => $qNorm,
    'flags' => array(
      'isNoWagering' => $isNoWagering,
      'isLowWagering' => $isLowWagering,
      'wrMax' => $wrMax,
      'isCode' => $isCode,
      'isExclusive' => $isExclusive,
      'isCrypto' => $isCrypto
    ),
    'count_total' => $total,
    'sql_sample' => $sql,
    'rows' => $rows,
    'err' => array(
      'num' => method_exists($db, 'getErrorNum') ? (int)$db->getErrorNum() : 0,
      'msg' => method_exists($db, 'getErrorMsg') ? (string)$db->getErrorMsg() : ''
    )
  );

  echo defined('JSON_UNESCAPED_SLASHES')
    ? json_encode($out, JSON_UNESCAPED_SLASHES)
    : json_encode($out);
  exit;
}

/* -----------------------
   If query too short
----------------------- */
if (!$minLenOk) {
  echo json_encode(array('q' => $q, 'bonuses' => array(), 'casinos' => array()));
  exit;
}

$limitBonus  = 5;
$limitCasino = 3;

/* -----------------------
   Build WHERE for BONUSES
----------------------- */
$whereBonus = "(a.status IS NULL OR a.status = '' OR a.status <> 'pending')";

if ($isNoWagering) {
  // WR 0
  $whereBonus .= " AND (a.wr = 0 OR a.wr = '0')";
}
else if ($isLowWagering) {
  // Low WR: numeric wr <= wrMax (excludes 0 to keep it separate from "No wagering")
  // Handles values like "20x" by stripping "x" before CAST.
  $whereBonus .= " AND (
    a.wr IS NOT NULL AND TRIM(a.wr) <> ''
    AND CAST(REPLACE(a.wr, 'x', '') AS DECIMAL(10,2)) > 0
    AND CAST(REPLACE(a.wr, 'x', '') AS DECIMAL(10,2)) <= " . (int)$wrMax . "
  )";
}
else if ($isCode) {
  // bonus has a code
  $whereBonus .= " AND (a.code IS NOT NULL AND TRIM(a.code) <> '')";
}
else if ($isExclusive) {
  $whereBonus .= " AND (a.exclusive = 'yes')";
}
else if ($isCrypto) {
  // mirror logic in fec_common_lib: b.jr_currencies contains *btc* etc
  $whereBonus .= " AND (
    b.jr_currencies LIKE '%*btc*%' OR
    b.jr_currencies LIKE '%*eth*%' OR
    b.jr_currencies LIKE '%*ltc*%' OR
    b.jr_currencies LIKE '%*usdc*%' OR
    b.jr_currencies LIKE '%*usdt*%'
  )";
}
else {
  $whereBonus .= " AND (
    a.code LIKE $like
    OR a.label LIKE $like
    OR a.brand LIKE $like
    OR a.type LIKE $like
    OR a.currency LIKE $like
    OR c.title LIKE $like
    OR c.alias LIKE $like
    OR a.code = $qExact
  )";
}

/* -----------------------
   BONUSES
----------------------- */
$bonusSql = "
  SELECT
    a.bid,
    a.cid,
    a.code,
    a.label,
    a.brand,
    a.type,
    a.currency,
    a.status,
    a.wr,
    a.max_cashout,
    a.exclusive,
    c.title AS casino_title,
    c.alias AS casino_alias,
    b.jr_currencies
  FROM `$tblBonuses` a
  LEFT JOIN `$tblContent`  c ON c.id = a.cid
  LEFT JOIN `$tblJreviews` b ON b.contentid = a.cid
  WHERE $whereBonus
  ORDER BY
    CASE WHEN a.status = 'expired' THEN 1 ELSE 0 END,
    a.bid DESC
  LIMIT " . (int)$limitBonus . "
";

$db->setQuery($bonusSql);
$bonusRows = $db->loadAssocList();
if (!is_array($bonusRows)) $bonusRows = array();

$bonuses = array();
foreach ($bonusRows as $r) {
  $casinoTitle = !empty($r['casino_title']) ? $r['casino_title'] : (!empty($r['brand']) ? $r['brand'] : 'Bonus');
  $label       = !empty($r['label']) ? $r['label'] : (!empty($r['type']) ? $r['type'] : 'Bonus');

  $bid  = (int) $r['bid'];
  $cid  = (int) $r['cid'];
  $type = isset($r['type']) ? (string) $r['type'] : '';

  // SEF URL from router; normalize /api/ segment only
  $url = JRoute::_(
    'index.php?option=com_bonuses&view=details&type=' . urlencode($type) . '&bid=' . $bid . '&cid=' . $cid,
    false
  );
  $url = fec_normalize_api_url($url);

  $bonuses[] = array(
    'type'  => 'bonus',
    'title' => trim($casinoTitle . ' — ' . $label),
    'url'   => $url,
    'meta'  => fec_bonus_meta($r),
  );
}

/* -----------------------
   CASINOS (Casino Reviews only: catid=8)
----------------------- */
$casinoSql = "
  SELECT c.id AS cid, c.title, c.alias
  FROM `$tblContent` c
  WHERE
    c.state = 1
    AND c.catid = 8
    AND (c.title LIKE $like OR c.alias LIKE $like)
  ORDER BY c.id DESC
  LIMIT " . (int)$limitCasino . "
";

$db->setQuery($casinoSql);
$casinoRows = $db->loadAssocList();
if (!is_array($casinoRows)) $casinoRows = array();

$casinos = array();
foreach ($casinoRows as $r) {
  $cid   = (int) $r['cid'];
  $title = !empty($r['title']) ? $r['title'] : 'Casino';

  // Keep simple: article view (router will SEF it)
  $url = JRoute::_('index.php?option=com_content&view=article&id=' . $cid, false);
  $url = fec_normalize_api_url($url);

  // fallback (should rarely be needed)
  if (!$url) {
    $slug = !empty($r['alias']) ? $r['alias'] : ('casino-' . $cid);
    $url = '/online-casinos/' . $slug;
  }

  $casinos[] = array(
    'type'  => 'casino',
    'title' => $title,
    'url'   => $url,
    'meta'  => array('Casino review'),
  );
}

/* -----------------------
   Output
----------------------- */
$out = array(
  'q' => $q,
  'bonuses' => $bonuses,
  'casinos' => $casinos,
);

echo defined('JSON_UNESCAPED_SLASHES')
  ? json_encode($out, JSON_UNESCAPED_SLASHES)
  : json_encode($out);

exit;
