<?php

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/*ini_set('log_errors', 1);
ini_set('display_errors', 0); // non mostrarli in pagina
ini_set('error_log', __DIR__ . '/bonus_feedback_error.log');
error_reporting(E_ALL);*/

//define constant
define( '_JEXEC', 1 );

define( 'DS', DIRECTORY_SEPARATOR );
 
//you need to adjust joomla path according to your joomla installation
define( 'JPATH_BASE', $_SERVER[ 'DOCUMENT_ROOT' ] . DS . '' );
 
//include joomla core files
require_once( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
require_once( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );
require_once( JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'factory.php' );

// Lib FEC con renderer canonico bd-* per i row reports (single source of truth)
require_once( JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'fec_common_lib.php' );


//create application
$app =& JFactory::getApplication('site');


header('Content-Type: application/json; charset=utf-8');


$db = JFactory::getDbo();

function json_out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

// ---------------- ACTION ROUTER (must run BEFORE vote validation) ----------------
$action = JFactory::getApplication()->input->getCmd('action', '');

if ($action === 'status') {
  $bid = (int) JFactory::getApplication()->input->getInt('bid', 0);
  if ($bid <= 0) json_out(array('ok'=>false,'error'=>'bad_request'), 400);

  // stesso voter_key che usi per insert
  if (!isset($_COOKIE['fec_voter'])) {
    // mai votato (non ha cookie)
    $agg = get_agg($db, $bid);
    json_out(array('ok'=>true,'voted'=>false,'agg'=>$agg));
  }

  $voter_key = hash('sha256', $_COOKIE['fec_voter']);

  $q = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName('#__bonus_feedback'))
    ->where($db->quoteName('bid').'='.(int)$bid)
    ->where($db->quoteName('voter_key').'='.$db->quote($voter_key));
  $db->setQuery($q);
  $has = ((int)$db->loadResult() > 0);

  $agg = get_agg($db, $bid);
  json_out(array('ok'=>true,'voted'=>$has,'agg'=>$agg));
}


if ($action === 'reports_more') {
  $bid    = (int) JFactory::getApplication()->input->getInt('bid', 0);
  $offset = (int) JFactory::getApplication()->input->getInt('offset', 0);
  $limit  = (int) JFactory::getApplication()->input->getInt('limit', 5);

  if ($bid <= 0) json_out(array('ok'=>false,'error'=>'bad_request'), 400);

  if ($offset < 0) $offset = 0;
  if ($limit <= 0) $limit = 5;
  if ($limit > 20) $limit = 20;

  // Filtro visibilita' coerente con fec_render_reports_html del lib:
  // mostra rows senza commento OPPURE con commento approved.
  $whereVisible =
    '(' .
      $db->quoteName('comment') . ' IS NULL ' .
      'OR TRIM(' . $db->quoteName('comment') . ') = "" ' .
      'OR ' . $db->quoteName('moderation_status') . ' = ' . $db->quote('approved') .
    ')';

  $query = $db->getQuery(true)
    ->select(array(
      $db->quoteName('id'),
      $db->quoteName('username'),
      $db->quoteName('vote'),
      $db->quoteName('comment'),
      $db->quoteName('created_at'),
      $db->quoteName('moderation_status'),
      $db->quoteName('country_code')
    ))
    ->from($db->quoteName('#__bonus_feedback'))
    ->where($db->quoteName('bid') . ' = ' . (int)$bid)
    ->where($whereVisible)
    ->order($db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

  $db->setQuery($query, $offset, $limit);
  $rows = (array) $db->loadAssocList();

  // total count: conta SOLO i report visibili (stesso filtro)
  $qCount = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName('#__bonus_feedback'))
    ->where($db->quoteName('bid') . ' = ' . (int)$bid)
    ->where($whereVisible);

  $db->setQuery($qCount);
  $total = (int) $db->loadResult();

  // renderer canonico dal lib (markup bd-*, identico ai primi 5 row del PHP)
  if (!function_exists('fec_reports_render_item')) {
    json_out(array('ok'=>false,'error'=>'missing_renderer'), 500);
  }

  $html = '';
  foreach ($rows as $r) {
    $html .= fec_reports_render_item($r);
  }

  $shown = is_array($rows) ? count($rows) : 0;
  $remaining = max(0, $total - ($offset + $shown));

  json_out(array(
    'ok' => true,
    'html' => $html,
    'shown' => $shown,
    'remaining' => $remaining,
    'total' => $total
  ));
}

$bid = (int) JFactory::getApplication()->input->getInt('bid', 0);
$vote_raw = JFactory::getApplication()->input->getString('vote', '');
$username = trim(JFactory::getApplication()->input->getString('username', ''));
$comment  = trim(JFactory::getApplication()->input->getString('comment', ''));

$countrycflare = fec_get_countrycflare();

if ($bid <= 0 || ($vote_raw !== 'yes' && $vote_raw !== 'no')) {
  json_out(array('ok'=>false,'error'=>'bad_request'), 400);
}
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
  json_out(array('ok'=>false,'error'=>'bad_username'), 400);
}
if ($comment !== '') {
  if (function_exists('mb_strlen')) {
    if (mb_strlen($comment, 'UTF-8') > 200) $comment = mb_substr($comment, 0, 200, 'UTF-8');
  } else {
    if (strlen($comment) > 200) $comment = substr($comment, 0, 200);
  }
}


$vote = ($vote_raw === 'yes') ? 1 : 0;

// Cookie voter
if (!isset($_COOKIE['fec_voter'])) {
  $bytes = openssl_random_pseudo_bytes(32);
  $cookie = bin2hex($bytes);
  setcookie('fec_voter', $cookie, time()+365*24*3600, '/', '', false, true);
  $_COOKIE['fec_voter'] = $cookie;
}
$voter_key = hash('sha256', $_COOKIE['fec_voter']);

// Hash IP/UA (privacy)
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
// metti SALT veri in config
$ip_hash = hash('sha256', $ip . '|FEC_SALT_IP');
$ua_hash = hash('sha256', $ua . '|FEC_SALT_UA');

$now = JFactory::getDate()->toSql();

// Soft rate limit: max 5 voti/10 minuti per IP
$ten_min_ago = JFactory::getDate(time() - 600)->toSql();
$query = $db->getQuery(true)
  ->select('COUNT(*)')
  ->from($db->quoteName('#__bonus_feedback'))
  ->where($db->quoteName('ip_hash').' = '.$db->quote($ip_hash))
  ->where($db->quoteName('created_at').' >= '.$db->quote($ten_min_ago));
$db->setQuery($query);
$cnt = (int)$db->loadResult();
if ($cnt >= 5) {
  json_out(array('ok'=>false,'error'=>'rate_limited'), 429);
}

// Moderazione automatica SOLO commento (username già validato)
$mod = auto_moderate($comment);
$comment_clean = $mod['text'];
$mod_status = $mod['status'];
$mod_reason = $mod['reason'];

// Insert voto (duplicate => già votato)
try {
  $obj = new stdClass();
  $obj->bid = $bid;
  $obj->vote = $vote;
  $obj->username = $username;
  $obj->comment = ($comment_clean === '') ? null : $comment_clean;
  $obj->moderation_status = $mod_status;
  $obj->moderation_reason = $mod_reason;
  $obj->voter_key = $voter_key;
  $obj->ip_hash = $ip_hash;
  $obj->ua_hash = $ua_hash;
  $obj->country_code = $countrycflare; // NEW
  $obj->created_at = $now;

  $db->insertObject('#__bonus_feedback', $obj, 'id');
} catch (Exception $e) {
  // Duplicate key su uniq_bid_voter -> già votato
  $msg = $e->getMessage();
  if (strpos($msg, 'Duplicate') !== false) {
    $agg = get_agg($db, $bid);
    json_out(array('ok'=>true,'duplicate'=>true,'agg'=>$agg,'country_code'=>$countrycflare));

  }
  json_out(array('ok'=>false,'error'=>'db_error'), 500);
}

// Agg update atomico
// assicura riga
$query = "INSERT INTO ".$db->quoteName('#__bonus_feedback_agg')." (bid, yes_count, no_count, updated_at)
          VALUES (".(int)$bid.",0,0,".$db->quote($now).")
          ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)";
$db->setQuery($query)->execute();

if ($vote === 1) {
  $db->setQuery("UPDATE ".$db->quoteName('#__bonus_feedback_agg')." SET yes_count = yes_count+1, updated_at=".$db->quote($now)." WHERE bid=".(int)$bid)->execute();
} else {
  $db->setQuery("UPDATE ".$db->quoteName('#__bonus_feedback_agg')." SET no_count = no_count+1, updated_at=".$db->quote($now)." WHERE bid=".(int)$bid)->execute();
}

$agg = get_agg($db, $bid);

// Build row data for renderer (id viene popolato da insertObject sopra)
$rowForRender = array(
  'id' => isset($obj->id) ? (int)$obj->id : 0,
  'username' => $username,
  'vote' => $vote,
  'comment' => $comment_clean,
  'created_at' => $now,
  'moderation_status' => $mod_status,
  'country_code' => $countrycflare,
);

// Render solo se la row sara' visibile col filtro (no comment, oppure approved)
$isVisible = ($comment_clean === '' || $mod_status === 'approved');
$reportHtml = '';
if ($isVisible && function_exists('fec_reports_render_item')) {
  $reportHtml = fec_reports_render_item($rowForRender);
}

// Total count post-insert col stesso filtro visibilita' usato altrove
$qCountAfter = $db->getQuery(true)
  ->select('COUNT(*)')
  ->from($db->quoteName('#__bonus_feedback'))
  ->where($db->quoteName('bid') . ' = ' . (int)$bid)
  ->where(
    '(' .
      $db->quoteName('comment') . ' IS NULL ' .
      'OR TRIM(' . $db->quoteName('comment') . ') = "" ' .
      'OR ' . $db->quoteName('moderation_status') . ' = ' . $db->quote('approved') .
    ')'
  );
$db->setQuery($qCountAfter);
$totalAfter = (int)$db->loadResult();

json_out(array(
  'ok' => true,
  'duplicate' => false,
  'agg' => $agg,
  'comment_status' => $mod_status,
  'country_code' => $countrycflare,
  'report_html' => $reportHtml,
  'total_after' => $totalAfter,
));

/* ---------------- Helpers ---------------- */

function fec_get_countrycflare() {
  if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $cc = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));

    // Cloudflare usa "XX" per country sconosciuta
    if ($cc === 'XX') return null;

    if (preg_match('/^[A-Z]{2}$/', $cc)) {
      return $cc;
    }
  }
  return null;
}


function get_agg($db, $bid) {
  $q = $db->getQuery(true)
    ->select('yes_count, no_count')
    ->from($db->quoteName('#__bonus_feedback_agg'))
    ->where('bid='.(int)$bid)
    ->setLimit(1);
  $db->setQuery($q);
  $row = $db->loadAssoc();
  $yes = $row ? (int)$row['yes_count'] : 0;
  $no  = $row ? (int)$row['no_count'] : 0;
  $total = $yes + $no;
  $pct = ($total > 0) ? round(($yes * 100) / $total) : 0;
  return array('yes'=>$yes,'no'=>$no,'total'=>$total,'yes_pct'=>$pct);
}

function normalize_text($s) {
  $s = trim($s);
  $s = strip_tags($s);
  $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

function auto_moderate($raw) {
  $txt = normalize_text($raw);
  if ($txt === '') return array('status'=>'approved','reason'=>null,'text'=>'');

  $lower = mb_strtolower($txt, 'UTF-8');
  $leet = strtr($lower, array('0'=>'o','1'=>'i','3'=>'e','4'=>'a','5'=>'s','7'=>'t','@'=>'a','$'=>'s'));
  $compact = preg_replace('/[^a-z0-9]/', '', $leet);

  // Link/contact => reject
  $link_patterns = array(
    '/https?:\/\//i', '/\bwww\./i',
    '/\b[a-z0-9\-]+\.(com|net|org|ru|xyz|top|info|site)\b/i',
    '/\b(t\.me|telegram|whatsapp|wa\.me)\b/i',
    '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i'
  );
  foreach ($link_patterns as $p) {
    if (preg_match($p, $txt)) return array('status'=>'rejected','reason'=>'link_or_contact','text'=>$txt);
  }

  // Hard offensive => reject (metti qui lista reale)
  $hard = array('fuck','shit','bitch','idiot','moron'); // TODO: vostra lista
  foreach ($hard as $w) {
    if (strpos($leet, $w) !== false || strpos($compact, $w) !== false) {
      return array('status'=>'rejected','reason'=>'offensive_language','text'=>$txt);
    }
  }

  // Soft accusations => pending (non mostrare)
  $soft = array('scam','fraud','stole','not paying','no payout');
  foreach ($soft as $w) {
    if (strpos($leet, $w) !== false || strpos($compact, preg_replace('/\s+/', '', $w)) !== false) {
      return array('status'=>'pending','reason'=>'accusation','text'=>$txt);
    }
  }

  // Heuristic spam => pending
  if (preg_match('/([!?.])\1{6,}/', $txt)) return array('status'=>'pending','reason'=>'excessive_punct','text'=>$txt);
  if (preg_match('/([a-zA-Z])\1{6,}/', $txt)) return array('status'=>'pending','reason'=>'excessive_repeat','text'=>$txt);

  $len = mb_strlen($txt, 'UTF-8');
  $letters = preg_replace('/[^a-zA-Z]/', '', $txt);
  $letter_count = strlen($letters);
  if ($len >= 20 && ($letter_count / max(1, $len)) < 0.35) {
    return array('status'=>'pending','reason'=>'low_letter_ratio','text'=>$txt);
  }

  return array('status'=>'approved','reason'=>null,'text'=>$txt);
}

// fec_reports_render_item_safe_inline rimossa: usiamo fec_reports_render_item dal lib (markup bd-*)