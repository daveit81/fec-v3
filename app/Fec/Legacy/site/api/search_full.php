<?php
// /v2/api/search_full.php (PHP 5.6 safe)
// View-all search API: paginated results + counts
// GET: q, tab=bonuses|casinos, offset, limit
// Tokens:
// - wr=0
// - wr<=20 (or any N)
// - code / exclusive / crypto
// Casino Reviews: jos_content.catid = 8

// PORT: the script bootstrapped Joomla itself (_JEXEC, JPATH_BASE, includes/defines.php,
// includes/framework.php); here the runner boots the layer before the include
// (ComponentRunner::runPublicScript(), fec.legacy.public_scripts).
defined('_JEXEC') or die('Restricted access');

$app = JFactory::getApplication('site');
$app->initialise();

fec_header('Content-Type: application/json; charset=utf-8');
fec_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
fec_header('Pragma: no-cache');
fec_header('Expires: 0');

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

$tab = isset($_GET['tab']) ? strtolower(trim((string)$_GET['tab'])) : 'bonuses';
if ($tab !== 'bonuses' && $tab !== 'casinos') $tab = 'bonuses';

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
if ($offset < 0) $offset = 0;
if ($limit < 1) $limit = 12;
if ($limit > 50) $limit = 50;

// Min len
$len = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
if ($q === '' || $len < 2) {
  echo json_encode(array(
    'q' => $q,
    'tab' => $tab,
    'offset' => $offset,
    'limit' => $limit,
    'counts' => array('bonuses' => 0, 'casinos' => 0),
    'bonuses' => array(),
    'casinos' => array(),
  ));
  jexit();
}

// LIKE safe (collation *_general_ci)
$like   = $db->Quote('%' . $db->escape($q, false) . '%');
$qExact = $db->Quote($db->escape($q, false));

// Normalize query (special pills)
$qNorm = strtolower(trim($q));
$qNorm = preg_replace('/\s+/', ' ', $qNorm);

/* -----------------------
   Token detection (mirror search.php)
----------------------- */
$isNoWagering = in_array($qNorm, array(
  'wr=0','wr 0','no wagering','no-wagering','wagering free','wagering-free'
), true);

$isLowWagering = false;
$wrMax = 0;
if (preg_match('/^wr\s*<=\s*(\d{1,3})$/', $qNorm, $m) === 1) {
  $isLowWagering = true;
  $wrMax = (int) $m[1];
}
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

// Optional sentinel (solo se lo usi dal JS)
$isNewCasinos = ($qNorm === '__new_casinos__');

/* -----------------------
   Helpers
----------------------- */
function fec_normalize_api_url($url) {
  $url = (string) $url;
  if ($url === '') return $url;

  if ($url[0] !== '/' && strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
    $url = '/' . $url;
  }

  $url = str_replace('/api/', '/', $url);
  $url = preg_replace('#^//+#', '/', $url);

  return $url;
}

function fec_bonus_meta($row) {
  $meta = array();

  $wrRaw = isset($row['wr']) ? $row['wr'] : null;
  $wrNum = null;
  if ($wrRaw !== null && $wrRaw !== '' && is_numeric(str_replace('x', '', (string)$wrRaw))) {
    $wrNum = (float) str_replace('x', '', (string)$wrRaw);
  }

  if ($wrNum !== null) {
    if ($wrNum == 0) $meta[] = 'No wagering';
    else if ($wrNum > 0 && $wrNum <= 20) $meta[] = 'Low wagering';
  } else {
    if (isset($row['wr']) && (string)$row['wr'] === '0') $meta[] = 'No wagering';
  }

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
   Build WHERE for BONUSES (same as search.php)
----------------------- */
$whereBonus = "(a.status IS NULL OR a.status = '' OR a.status <> 'pending')";

if ($isNoWagering) {
  $whereBonus .= " AND (a.wr = 0 OR a.wr = '0')";
}
else if ($isLowWagering) {
  $whereBonus .= " AND (
    a.wr IS NOT NULL AND TRIM(a.wr) <> ''
    AND CAST(REPLACE(a.wr, 'x', '') AS DECIMAL(10,2)) > 0
    AND CAST(REPLACE(a.wr, 'x', '') AS DECIMAL(10,2)) <= " . (int)$wrMax . "
  )";
}
else if ($isCode) {
  $whereBonus .= " AND (a.code IS NOT NULL AND TRIM(a.code) <> '')";
}
else if ($isExclusive) {
  $whereBonus .= " AND (a.exclusive = 'yes')";
}
else if ($isCrypto) {
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
   Counts (for tabs)
----------------------- */
$countBonusesSql = "
  SELECT COUNT(*)
  FROM `$tblBonuses` a
  LEFT JOIN `$tblContent`  c ON c.id = a.cid
  LEFT JOIN `$tblJreviews` b ON b.contentid = a.cid
  WHERE $whereBonus
";
$db->setQuery($countBonusesSql);
$countBonuses = (int) $db->loadResult();

$countCasinosSql = "
  SELECT COUNT(*)
  FROM `$tblContent` c
  WHERE c.state = 1
    AND c.catid = 8
    AND (c.title LIKE $like OR c.alias LIKE $like)
";
if ($isNewCasinos) {
  // se lo vuoi DAVVERO “ignore like”, allora va riscritto anche il count.
  // per ora resta coerente col like (non lo stiamo usando per le pill principali).
}
$db->setQuery($countCasinosSql);
$countCasinos = (int) $db->loadResult();

/* -----------------------
   BONUSES paginated
----------------------- */
$bonuses = array();
if ($tab === 'bonuses') {

  $bonusSql = "
    SELECT
      a.bid, a.cid, a.code, a.label, a.brand, a.type, a.currency,
      a.status, a.wr, a.max_cashout, a.exclusive,
      c.title AS casino_title, c.alias AS casino_alias,
      b.jr_currencies
    FROM `$tblBonuses` a
    LEFT JOIN `$tblContent`  c ON c.id = a.cid
    LEFT JOIN `$tblJreviews` b ON b.contentid = a.cid
    WHERE $whereBonus
    ORDER BY
      CASE WHEN a.status = 'expired' THEN 1 ELSE 0 END,
      a.bid DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
  ";

  $db->setQuery($bonusSql);
  $rows = $db->loadAssocList();
  if (!is_array($rows)) $rows = array();

  foreach ($rows as $r) {
    $casinoTitle = !empty($r['casino_title']) ? $r['casino_title'] : (!empty($r['brand']) ? $r['brand'] : 'Bonus');
    $label       = !empty($r['label']) ? $r['label'] : (!empty($r['type']) ? $r['type'] : 'Bonus');

    $bid  = (int) $r['bid'];
    $cid  = (int) $r['cid'];
    $type = isset($r['type']) ? (string) $r['type'] : '';

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
}

/* -----------------------
   CASINOS paginated (catid 8)
----------------------- */
$casinos = array();
if ($tab === 'casinos') {

  if ($isNewCasinos) {
    $casinoSql = "
      SELECT c.id AS cid, c.title, c.alias
      FROM `$tblContent` c
      WHERE c.state = 1 AND c.catid = 8
      ORDER BY c.id DESC
      LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
    ";
  } else {
    $casinoSql = "
      SELECT c.id AS cid, c.title, c.alias
      FROM `$tblContent` c
      WHERE c.state = 1
        AND c.catid = 8
        AND (c.title LIKE $like OR c.alias LIKE $like)
      ORDER BY c.id DESC
      LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
    ";
  }

  $db->setQuery($casinoSql);
  $rows = $db->loadAssocList();
  if (!is_array($rows)) $rows = array();

  foreach ($rows as $r) {
    $cid   = (int) $r['cid'];
    $title = !empty($r['title']) ? $r['title'] : 'Casino';

    $url = JRoute::_('index.php?option=com_content&view=article&id=' . $cid, false);
    $url = fec_normalize_api_url($url);

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
}

/* -----------------------
   Output
----------------------- */
$out = array(
  'q' => $q,
  'tab' => $tab,
  'offset' => $offset,
  'limit' => $limit,
  'counts' => array('bonuses' => $countBonuses, 'casinos' => $countCasinos),
  'bonuses' => $bonuses,
  'casinos' => $casinos,
);

echo defined('JSON_UNESCAPED_SLASHES')
  ? json_encode($out, JSON_UNESCAPED_SLASHES)
  : json_encode($out);

jexit();
