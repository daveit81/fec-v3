<?php
/**
 * /home/freeextr/public_html/webhook/bounce.php
 *
 * Resend webhook receiver. Handles bounce + complaint events.
 *
 * Setup chain:
 *   1. Deploy this file
 *   2. Resend dashboard → Webhooks → Add Endpoint:
 *        URL: https://www.freeextrachips.com/webhook/bounce.php
 *        Events: email.bounced, email.complained
 *   3. Resend generates a signing secret (format whsec_xxx...)
 *   4. Add to /home/freeextr/conf/fec_resend.php:
 *        'webhook_secret' => '<REDACTED:literal>',
 *   5. Send test event from Resend dashboard, verify it processes
 *
 * Security:
 *   - Svix signature verification (HMAC-SHA256 of svix-id + timestamp + body)
 *   - Replay protection (5-min timestamp window)
 *   - Subscriber updates only confirmed/pending → bounced/unsubscribed
 *     (never overrides existing bounced/unsubscribed status)
 */

// Method gate — only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'method_not_allowed'));
    exit;
}

// Read raw body BEFORE bootstrapping anything (signature is over raw bytes)
$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(array('error' => 'empty_body'));
    exit;
}

// Extract Svix headers — try $_SERVER then getallheaders() fallback
function fec_webhook_header($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (!empty($_SERVER[$key])) return $_SERVER[$key];
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        foreach ($h as $k => $v) {
            if (strcasecmp($k, $name) === 0) return $v;
        }
    }
    return '';
}

$svixId        = fec_webhook_header('svix-id');
$svixTimestamp = fec_webhook_header('svix-timestamp');
$svixSignature = fec_webhook_header('svix-signature');

if (!$svixId || !$svixTimestamp || !$svixSignature) {
    http_response_code(401);
    error_log('Webhook: missing svix headers');
    echo json_encode(array('error' => 'missing_signature_headers'));
    exit;
}

// Replay protection
$now = time();
$ts  = (int)$svixTimestamp;
if (abs($now - $ts) > 300) {
    http_response_code(401);
    error_log('Webhook: timestamp drift too large: ' . abs($now - $ts) . 's');
    echo json_encode(array('error' => 'timestamp_out_of_window'));
    exit;
}

// Bootstrap Joomla — needed for DB + config
define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__));
if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';
JFactory::getApplication('site');
require_once JPATH_BASE . '/components/com_bonuses/helpers/fec_common_lib.php';

// Load webhook secret
try {
    $cfg = fec_resend_config();
} catch (Exception $e) {
    http_response_code(500);
    error_log('Webhook: config error: ' . $e->getMessage());
    echo json_encode(array('error' => 'server_config_error'));
    exit;
}

$secret = isset($cfg['webhook_secret']) ? trim((string)$cfg['webhook_secret']) : '';
if (!$secret) {
    http_response_code(500);
    error_log('Webhook: webhook_secret not configured in fec_resend.php');
    echo json_encode(array('error' => 'webhook_secret_not_configured'));
    exit;
}

// Decode the secret: format is "whsec_<base64>"; the part after the prefix
// is the actual HMAC key (base64-encoded)
$prefix = 'whsec_';
if (substr($secret, 0, strlen($prefix)) === $prefix) {
    $secretRaw = base64_decode(substr($secret, strlen($prefix)));
} else {
    $secretRaw = $secret;
}

if (!$secretRaw) {
    http_response_code(500);
    error_log('Webhook: failed to decode webhook secret');
    echo json_encode(array('error' => 'invalid_secret_format'));
    exit;
}

// Compute expected signature
$signedContent = $svixId . '.' . $svixTimestamp . '.' . $rawBody;
$expectedSig   = base64_encode(hash_hmac('sha256', $signedContent, $secretRaw, true));

// Resend may include multiple signatures: "v1,sig1 v1,sig2 ..." — accept any match
$valid = false;
foreach (explode(' ', $svixSignature) as $candidate) {
    $candidate = trim($candidate);
    if ($candidate === '' || strpos($candidate, ',') === false) continue;
    list($version, $sigValue) = explode(',', $candidate, 2);
    if ($version !== 'v1') continue;
    if (hash_equals($expectedSig, $sigValue)) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    http_response_code(401);
    error_log('Webhook: signature verification failed for svix-id=' . $svixId);
    echo json_encode(array('error' => 'invalid_signature'));
    exit;
}

// Parse payload
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    error_log('Webhook: invalid JSON body');
    echo json_encode(array('error' => 'invalid_json'));
    exit;
}

$eventType = isset($payload['type']) ? (string)$payload['type'] : '';
$data      = isset($payload['data']) ? (array)$payload['data'] : array();
$emailId   = isset($data['email_id']) ? (string)$data['email_id'] : '';
$toEmails  = isset($data['to']) ? (array)$data['to'] : array();

$db = JFactory::getDbo();

// Log raw event for every recipient — atomic, idempotent later via processed flag
$insertedIds = array();
foreach ($toEmails as $email) {
    $email = trim((string)$email);
    if ($email === '') continue;

    try {
        $db->setQuery(
            'INSERT INTO ' . $db->qn('#__newsletter_webhook_events')
            . ' (event_type, resend_message_id, email, payload, processed, created_at)'
            . ' VALUES ('
            . $db->q($eventType) . ', '
            . $db->q($emailId) . ', '
            . $db->q($email) . ', '
            . $db->q($rawBody) . ', '
            . '0, '
            . $db->q(date('Y-m-d H:i:s'))
            . ')'
        );
        $db->execute();
        $insertedIds[] = (int)$db->insertid();
    } catch (Exception $e) {
        error_log('Webhook: insert event failed: ' . $e->getMessage());
        // continue — try to act on the subscriber even if log insert failed
    }
}

// Determine action by event type
$newStatus    = null;
$timestampCol = null;

if ($eventType === 'email.bounced') {
    // Only act on PERMANENT bounces (hard).
    // Soft/transient bounces resolve themselves — ignore them.
    $bounce  = isset($data['bounce']) ? (array)$data['bounce'] : array();
    $subType = isset($bounce['subType']) ? strtolower((string)$bounce['subType']) : '';
    $type    = isset($bounce['type'])    ? strtolower((string)$bounce['type'])    : '';

    $hardBounceMarkers = array('permanent', 'onaccountsuppressionlist', 'suppressed', 'hardbounce');
    $isHard = in_array($subType, $hardBounceMarkers, true) || in_array($type, $hardBounceMarkers, true);

    if ($isHard) {
        $newStatus = 'bounced';
        // Schema doesn't have bounced_at — bounced status itself is the marker.
        // The webhook_events row preserves the exact timestamp.
    }
} elseif ($eventType === 'email.complained') {
    // Treat complaint as immediate opt-out
    $newStatus    = 'unsubscribed';
    $timestampCol = 'unsubscribed_at';
}

// Update subscribers (only if event warrants action)
$updated = 0;
if ($newStatus !== null) {
    foreach ($toEmails as $email) {
        $email = trim((string)$email);
        if ($email === '') continue;

        $sql = 'UPDATE ' . $db->qn('#__newsletter_subscribers')
             . ' SET status = ' . $db->q($newStatus);
        if ($timestampCol) {
            $sql .= ', ' . $db->qn($timestampCol) . ' = NOW()';
        }
        // Only flip from active states — never override already-bounced/unsubscribed
        $sql .= ' WHERE email = ' . $db->q($email)
              . ' AND status IN (' . $db->q('confirmed') . ', ' . $db->q('pending') . ')';

        try {
            $db->setQuery($sql);
            $db->execute();
            $affected = (int)$db->getAffectedRows();
            if ($affected > 0) {
                $updated++;
                error_log('Webhook: ' . $eventType . ' → ' . $email . ' marked ' . $newStatus);
            }
        } catch (Exception $e) {
            error_log('Webhook: update subscriber failed: ' . $e->getMessage());
        }
    }
}

// Mark all log entries from this delivery as processed
if (!empty($insertedIds)) {
    try {
        $db->setQuery(
            'UPDATE ' . $db->qn('#__newsletter_webhook_events')
            . ' SET processed = 1'
            . ' WHERE id IN (' . implode(',', $insertedIds) . ')'
        );
        $db->execute();
    } catch (Exception $e) {
        error_log('Webhook: mark processed failed: ' . $e->getMessage());
    }
}

// 200 OK — Resend retries on non-2xx, so always ack unless we already errored above
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(array(
    'ok'        => true,
    'type'      => $eventType,
    'recipients'=> count($toEmails),
    'updated'   => $updated,
));