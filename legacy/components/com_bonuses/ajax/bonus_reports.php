<?php
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', __DIR__ . '/bonus_reports_error.log');
error_reporting(E_ALL);

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', $_SERVER['DOCUMENT_ROOT'] . DS);

require_once(JPATH_BASE . 'includes' . DS . 'defines.php');
require_once(JPATH_BASE . 'includes' . DS . 'framework.php');
require_once(JPATH_BASE . 'libraries' . DS . 'joomla' . DS . 'factory.php');

$app = JFactory::getApplication('site');
header('Content-Type: application/json; charset=utf-8');

function json_out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function fec_time_ago_simple($sqlDate) {
  if (!$sqlDate) return '';
  $ts = strtotime($sqlDate);
  if (!$ts) return '';
  $diff = time() - $ts;
  if ($diff < 60) return 'just now';
  $mins = floor($diff / 60);
  if ($mins < 60) return $mins . ' min ago';
  $hrs = floor($mins / 60);
  if ($hrs < 24) return $hrs . ' hours ago';
  $days = floor($hrs / 24);
  if ($days < 30) return $days . ' days ago';
  $months = floor($days / 30);
  if ($months < 12) return $months . ' months ago';
  $years = floor($months / 12);
  return $years . ' years ago';
}

function fec_render_report_item($r) {
  $user = htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8');

  $voteVal = isset($r['vote']) ? (int)$r['vote'] : 0;
  $voteClass = ($voteVal === 1) ? 'yes' : 'no';
  $voteLabel = ($voteVal === 1) ? 'claimed' : 'reported issue';

  $timeAgo = '';
  if (!empty($r['created_at'])) {
    $timeAgo = htmlspecialchars(fec_time_ago_simple($r['created_at']), ENT_QUOTES, 'UTF-8');
  }

  $comment = '';
  if (array_key_exists('comment', $r) && $r['comment'] !== null) {
    $comment = trim((string)$r['comment']);
  }

  // Mostra commento solo se status approved (se c'è testo)
  $commentHtml = '';
  if ($comment !== '' && isset($r['moderation_status']) && $r['moderation_status'] === 'approved') {
    $commentHtml = '<div class="fec-reports__comment">' .
      nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')) .
    '</div>';
  }

  // Flag
  $ccHtml = '';
  if (!empty($r['country_code'])) {
    $tmp = strtoupper(trim($r['country_code']));
    if (preg_match('/^[A-Z]{2}$/', $tmp) && $tmp !== 'XX') {
      $cc_lc = strtolower($tmp);
      $flagUrl = '/images/flags/' . $cc_lc . '.svg';
$ccHtml = '<span class="fec-report__cc fec-report__cc--flag"
  style="background-image:url(' . htmlspecialchars($flagUrl, ENT_QUOTES, 'UTF-8') . ')"
  aria-label="' . htmlspecialchars($tmp, ENT_QUOTES, 'UTF-8') . '"></span>';
    }
  }
  
$id = isset($r['id']) ? (int)$r['id'] : 0;

return '
  <div class="fec-reports__item fec-reports__item--'.$voteClass.'" data-id="'.$id.'">
    <div class="fec-reports__meta">
      <span class="fec-reports__user">'.$user.'</span>
      '.$ccHtml.'
      <span class="fec-reports__dot">•</span>
      <span class="fec-reports__action">'.$voteLabel.'</span>' .
      ($timeAgo ? '<span class="fec-reports__dot">•</span><span class="fec-reports__time">'.$timeAgo.'</span>' : '') .
    '</div>
    '.$commentHtml.'
  </div>';
}

function fec_get_reports_ajax($bid, $limit, $offset) {
  $db = JFactory::getDbo();
  $query = $db->getQuery(true);

$query
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
  ->where('(
    ' . $db->quoteName('comment') . ' IS NULL
    OR TRIM(' . $db->quoteName('comment') . ') = ""
    OR ' . $db->quoteName('moderation_status') . ' = ' . $db->quote('approved') . '
  )')
->order($db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

  $db->setQuery($query, (int)$offset, (int)$limit);
  $rows = $db->loadAssocList();

  return is_array($rows) ? $rows : array();
}

function fec_count_reports($bid) {
  $db = JFactory::getDbo();
  $q = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName('#__bonus_feedback'))
    ->where('bid='.(int)$bid)
    ->where('(
      comment IS NULL
      OR TRIM(comment) = ""
      OR moderation_status = '.$db->quote('approved').'
    )')
    ->order($db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');
    
  $db->setQuery($q);
  return (int)$db->loadResult();
}

/* ---- input ---- */
$input  = JFactory::getApplication()->input;
$bid    = (int)$input->getInt('bid', 0);
$offset = (int)$input->getInt('offset', 0);
$limit  = (int)$input->getInt('limit', 10);

// clamp
if ($bid <= 0) json_out(array('ok'=>false,'error'=>'bad_request'), 400);
if ($offset < 0) $offset = 0;
if ($limit < 1) $limit = 10;
if ($limit > 20) $limit = 20;

try {
  // limit+1 per sapere se has_more
  $rows = fec_get_reports_ajax($bid, $limit + 1, $offset);

  $hasMore = (count($rows) > $limit);
  if ($hasMore) array_pop($rows);

  $html = '';
  foreach ($rows as $r) {
    $html .= fec_render_report_item($r);
  }

$total = fec_count_reports($bid);
$nextOffset = $offset + count($rows);
$remaining = max(0, $total - $nextOffset);

  json_out(array(
    'ok' => true,
    'html' => $html,
    'next_offset' => $offset + count($rows),
    'has_more' => $hasMore,
    'returned' => count($rows),
    'remaining' => $remaining,
    'limit' => $limit
  ));
} catch (Exception $e) {
  json_out(array('ok'=>false,'error'=>'server_error'), 500);
}
