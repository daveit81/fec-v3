<?php
/**
 * /subscribe.php — Newsletter capture endpoint (light approach)
 *
 *  POST  /subscribe.php
 *    Body:  email, consent=1, source, fts (form-ts), fsg (HMAC sig), website (honeypot)
 *    Resp:  JSON  { ok: bool, status?: string, error?: string }
 *
 *  GET   /subscribe.php?action=confirm&token=...&email=...
 *  GET   /subscribe.php?action=unsubscribe&token=...&email=...
 *    Resp:  HTML standalone page (noindex)
 *
 *  GET   /subscribe.php?action=fts
 *    Resp:  JSON  { fts, fsg }  — refresh per form su pagine cachate
 *
 *  Bootstrap pattern: same as /freshtoken.php.
 */

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$app = JFactory::getApplication('site');
$app->initialise();

require_once JPATH_BASE . '/components/com_bonuses/helpers/fec_common_lib.php';

// ---- Config ---------------------------------------------------------------
define('FEC_NL_FORM_MIN_AGE',      0);     // sec; bots submit istantaneamente
define('FEC_NL_FORM_MAX_AGE',      3600);  // sec; form stantio = nuovo render
define('FEC_NL_RATE_LIMIT_WINDOW', 3600);  // 1h
define('FEC_NL_RATE_LIMIT_MAX',    5);     // tentativi per ip_hash per finestra
define('FEC_NL_RESEND_COOLDOWN',   300);   // 5 min tra resend conferma
define('FEC_NL_ALLOWED_SOURCES',
    'home_block,footer,bonus_detail,blog_post,home_popup');

// ---- Dispatch -------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action'])
    ? $_REQUEST['action']
    : (($method === 'POST') ? 'signup' : '');

switch ($action) {
    case 'signup':      fec_nl_handle_signup();      break;
    case 'confirm':     fec_nl_handle_confirm();     break;
    case 'unsubscribe': fec_nl_handle_unsubscribe(); break;
    case 'fts':         fec_nl_handle_fts();         break;
    default:
        http_response_code(400);
        echo 'Bad request';
}
exit;


// ===========================================================================
// HANDLERS
// ===========================================================================

function fec_nl_handle_signup() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return fec_nl_json(array('ok' => false, 'error' => 'method'), 405);
    }

    // 1. Honeypot — bot fills, human doesn't. Fake success per non confondere.
    if (!empty($_POST['website'])) {
        fec_nl_log_attempt('', 'honeypot');
        return fec_nl_json(array('ok' => true));
    }

    // 2. Form timestamp + HMAC (anti-replay, anti-instant-submit).
    $fts = isset($_POST['fts']) ? (int) $_POST['fts'] : 0;
    $fsg = isset($_POST['fsg']) ? (string) $_POST['fsg'] : '';
    $secret   = JFactory::getConfig()->get('secret');
    $expected = hash_hmac('sha256', (string) $fts, $secret);
    $age      = time() - $fts;
    if (!hash_equals($expected, $fsg)
        || $age < FEC_NL_FORM_MIN_AGE
        || $age > FEC_NL_FORM_MAX_AGE) {
        fec_nl_log_attempt('', 'invalid');
        return fec_nl_json(array('ok' => false, 'error' => 'form_expired'), 400);
    }

    // 3. Consent (GDPR — non implicito mai).
    if (empty($_POST['consent'])) {
        fec_nl_log_attempt('', 'consent_missing');
        return fec_nl_json(array('ok' => false, 'error' => 'consent_required'), 400);
    }

    // 4. Email.
    $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        fec_nl_log_attempt('', 'invalid');
        return fec_nl_json(array('ok' => false, 'error' => 'email_invalid'), 400);
    }

    // 5. Source (whitelist).
    $source = isset($_POST['source']) ? (string) $_POST['source'] : 'unknown';
    if (!in_array($source, explode(',', FEC_NL_ALLOWED_SOURCES), true)) {
        $source = 'unknown';
    }

    // 6. IP + country (CF header). ip_hash, mai IP raw → GDPR-safe.
    $ip      = fec_nl_client_ip();
    $ip_hash = hash('sha256', $ip . '|' . $secret);
    $country = null;
    if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $cc = strtoupper(substr($_SERVER['HTTP_CF_IPCOUNTRY'], 0, 2));
        if (preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'XX' && $cc !== 'T1') {
            $country = $cc;
        }
    }

    // 7. Rate limit (basato su log table, conta anche i tentativi falliti).
    if (fec_nl_is_rate_limited($ip_hash)) {
        fec_nl_log_attempt($ip_hash, 'rate_limited');
        return fec_nl_json(array('ok' => false, 'error' => 'rate_limited'), 429);
    }

    // 8. Lookup subscriber esistente.
    $db = JFactory::getDbo();
    $q  = $db->getQuery(true)
        ->select('id, status, confirmation_sent_at')
        ->from($db->quoteName('#__newsletter_subscribers'))
        ->where($db->quoteName('email') . ' = ' . $db->quote($email));
    $db->setQuery($q);
    $existing = $db->loadObject();

    $now         = JFactory::getDate()->toSql();
    $conf_token  = fec_nl_random_token();
    $unsub_token = fec_nl_random_token();

    if ($existing) {
        if ($existing->status === 'confirmed') {
            fec_nl_log_attempt($ip_hash, 'duplicate');
            return fec_nl_json(array('ok' => true, 'status' => 'already_subscribed'));
        }
        if ($existing->status === 'bounced') {
            // Email che ha bounced ripetutamente — non riproviamo da self-service.
            return fec_nl_json(array('ok' => false, 'error' => 'contact_support'), 400);
        }
        // pending o unsubscribed → re-prime con nuovi token + resend conferma.
        if ($existing->status === 'pending' && $existing->confirmation_sent_at) {
            $delta = time() - strtotime($existing->confirmation_sent_at);
            if ($delta < FEC_NL_RESEND_COOLDOWN) {
                // Troppo presto per un resend; mostra "check inbox" comunque.
                return fec_nl_json(array('ok' => true, 'status' => 'check_inbox'));
            }
        }
        $set = array(
            $db->quoteName('status')               . ' = ' . $db->quote('pending'),
            $db->quoteName('confirmation_token')   . ' = ' . $db->quote($conf_token),
            $db->quoteName('unsubscribe_token')    . ' = ' . $db->quote($unsub_token),
            $db->quoteName('source')               . ' = ' . $db->quote($source),
            $db->quoteName('country_code')         . ' = ' . ($country ? $db->quote($country) : 'NULL'),
            $db->quoteName('ip_hash')              . ' = ' . $db->quote($ip_hash),
            $db->quoteName('confirmation_sent_at') . ' = ' . $db->quote($now),
            $db->quoteName('unsubscribed_at')      . ' = NULL',
        );
        $qu = $db->getQuery(true)
            ->update($db->quoteName('#__newsletter_subscribers'))
            ->set($set)
            ->where($db->quoteName('id') . ' = ' . (int) $existing->id);
        $db->setQuery($qu);
        $db->execute();
    } else {
        // Insert nuovo. NULL handling via raw query (insertObject quota '' invece).
        $cols = array(
            $db->quoteName('email'),
            $db->quoteName('status'),
            $db->quoteName('confirmation_token'),
            $db->quoteName('unsubscribe_token'),
            $db->quoteName('source'),
            $db->quoteName('country_code'),
            $db->quoteName('ip_hash'),
            $db->quoteName('created_at'),
            $db->quoteName('confirmation_sent_at'),
        );
        $vals = array(
            $db->quote($email),
            $db->quote('pending'),
            $db->quote($conf_token),
            $db->quote($unsub_token),
            $db->quote($source),
            $country ? $db->quote($country) : 'NULL',
            $db->quote($ip_hash),
            $db->quote($now),
            $db->quote($now),
        );
        $qi = $db->getQuery(true)
            ->insert($db->quoteName('#__newsletter_subscribers'))
            ->columns($cols)
            ->values(implode(',', $vals));
        $db->setQuery($qi);
        $db->execute();
    }

    // 9. Manda l'email di conferma.
    $sent = fec_nl_send_confirmation_email($email, $conf_token, $unsub_token);
    fec_nl_log_attempt($ip_hash, $sent ? 'success' : 'mail_failed');
    // Non riveliamo errori SMTP all'utente — il row è in DB, resend possibile.

    return fec_nl_json(array('ok' => true, 'status' => 'check_inbox'));
}


function fec_nl_handle_confirm() {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    $email = isset($_GET['email']) ? strtolower(trim($_GET['email'])) : '';

    if (!preg_match('/^[a-f0-9]{64}$/', $token)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fec_nl_render_page('Invalid confirmation link',
            'This confirmation link is malformed. Please try subscribing again.');
        return;
    }

    $db = JFactory::getDbo();
    $q  = $db->getQuery(true)
        ->select('id, status')
        ->from($db->quoteName('#__newsletter_subscribers'))
        ->where($db->quoteName('email') . ' = ' . $db->quote($email))
        ->where($db->quoteName('confirmation_token') . ' = ' . $db->quote($token));
    $db->setQuery($q);
    $row = $db->loadObject();

    if (!$row) {
        fec_nl_render_page('Link expired',
            'This confirmation link is no longer valid. If you still want to subscribe, please sign up again.');
        return;
    }

    if ($row->status === 'confirmed') {
        fec_nl_render_page('Already confirmed', "You're already subscribed — thanks!");
        return;
    }

    $now = JFactory::getDate()->toSql();
    $qu  = $db->getQuery(true)
        ->update($db->quoteName('#__newsletter_subscribers'))
        ->set(array(
            $db->quoteName('status')             . ' = ' . $db->quote('confirmed'),
            $db->quoteName('confirmed_at')       . ' = ' . $db->quote($now),
            // Invalida il confirmation token → link one-shot.
            $db->quoteName('confirmation_token') . " = ''",
        ))
        ->where($db->quoteName('id') . ' = ' . (int) $row->id);
    $db->setQuery($qu);
    $db->execute();

    fec_nl_render_page('Subscription confirmed',
        "You're in. We'll send you this week's verified bonuses, no fluff.");
}


function fec_nl_handle_unsubscribe() {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    $email = isset($_GET['email']) ? strtolower(trim($_GET['email'])) : '';

    if (!preg_match('/^[a-f0-9]{64}$/', $token)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fec_nl_render_page('Invalid link', 'This unsubscribe link is malformed.');
        return;
    }

    $db = JFactory::getDbo();
    $q  = $db->getQuery(true)
        ->select('id, status')
        ->from($db->quoteName('#__newsletter_subscribers'))
        ->where($db->quoteName('email') . ' = ' . $db->quote($email))
        ->where($db->quoteName('unsubscribe_token') . ' = ' . $db->quote($token));
    $db->setQuery($q);
    $row = $db->loadObject();

    if (!$row) {
        fec_nl_render_page('Not found',
            'We could not find that subscription. It may already be removed.');
        return;
    }

    if ($row->status === 'unsubscribed') {
        fec_nl_render_page('Already unsubscribed',
            "You've already been removed from our list.");
        return;
    }

    $now = JFactory::getDate()->toSql();
    $qu  = $db->getQuery(true)
        ->update($db->quoteName('#__newsletter_subscribers'))
        ->set(array(
            $db->quoteName('status')          . ' = ' . $db->quote('unsubscribed'),
            $db->quoteName('unsubscribed_at') . ' = ' . $db->quote($now),
        ))
        ->where($db->quoteName('id') . ' = ' . (int) $row->id);
    $db->setQuery($qu);
    $db->execute();

    fec_nl_render_page('Unsubscribed', "You're off the list. No hard feelings.");
}


function fec_nl_handle_fts() {
    // Pubblico, non sensibile: serve solo a rifrescare il pair fts/fsg
    // così il form non scade su pagine cachate.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $fts = time();
    $fsg = hash_hmac('sha256', (string) $fts, JFactory::getConfig()->get('secret'));
    return fec_nl_json(array('fts' => $fts, 'fsg' => $fsg));
}


// ===========================================================================
// HELPERS  — sposterò in fec_common_lib.php (prefisso fec_nl_*) dopo lo shake-out
// ===========================================================================

function fec_nl_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function fec_nl_random_token() {
    // PHP 5.6: niente random_bytes(), usiamo openssl.
    return bin2hex(openssl_random_pseudo_bytes(32));
}

function fec_nl_client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function fec_nl_is_rate_limited($ip_hash) {
    $db    = JFactory::getDbo();
    $since = JFactory::getDate(time() - FEC_NL_RATE_LIMIT_WINDOW)->toSql();
    $q = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__newsletter_submit_log'))
        ->where($db->quoteName('ip_hash') . ' = ' . $db->quote($ip_hash))
        ->where($db->quoteName('created_at') . ' >= ' . $db->quote($since));
    $db->setQuery($q);
    return ((int) $db->loadResult()) >= FEC_NL_RATE_LIMIT_MAX;
}

function fec_nl_log_attempt($ip_hash, $outcome) {
    try {
        $db  = JFactory::getDbo();
        $obj = new stdClass();
        // Se l'ip_hash non era ancora calcolato (es. honeypot early-exit), calcolalo ora.
        if (!$ip_hash) {
            $ip_hash = hash('sha256',
                fec_nl_client_ip() . '|' . JFactory::getConfig()->get('secret'));
        }
        $obj->ip_hash    = $ip_hash;
        $obj->outcome    = $outcome;
        $obj->created_at = JFactory::getDate()->toSql();
        $db->insertObject('#__newsletter_submit_log', $obj);
    } catch (Exception $e) {
        // Mai bloccare il flusso per un fail di logging.
    }
}

function fec_nl_send_confirmation_email($email, $conf_token, $unsub_token) {
    $config    = JFactory::getConfig();
    $site_name = $config->get('sitename', 'FreeExtraChips');
    $site_url  = rtrim(JUri::root(), '/');
    $confirm_url = $site_url . '/subscribe.php?action=confirm'
                 . '&token=' . urlencode($conf_token)
                 . '&email=' . urlencode($email);
    $unsub_url   = $site_url . '/subscribe.php?action=unsubscribe'
                 . '&token=' . urlencode($unsub_token)
                 . '&email=' . urlencode($email);

    $subject = 'Confirm your ' . $site_name . ' subscription';

    $plain  = "Thanks for subscribing to {$site_name}.\n\n";
    $plain .= "Please confirm your email by opening this link:\n{$confirm_url}\n\n";
    $plain .= "If you didn't sign up, ignore this message — you won't hear from us again.\n\n";
    $plain .= "—\nUnsubscribe: {$unsub_url}\n";

    $esc = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>';
    $html .= '<body style="margin:0;padding:24px;background:#f6f7f9;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1c1f24;">';
    $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">';
    $html .= '<tr><td style="padding:24px 28px;border-bottom:1px solid #e5e7eb;"><strong style="font-size:16px;">' . $esc($site_name) . '</strong></td></tr>';
    $html .= '<tr><td style="padding:28px;">';
    $html .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.5;">Hi — thanks for signing up.</p>';
    $html .= '<p style="margin:0 0 24px;font-size:15px;line-height:1.5;">Confirm your email so we can start sending you this week\'s verified bonuses.</p>';
    $html .= '<p style="margin:0 0 24px;"><a href="' . $esc($confirm_url) . '" style="display:inline-block;padding:12px 20px;background:#1c1f24;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;">Confirm my email</a></p>';
    $html .= '<p style="margin:0 0 8px;font-size:12px;color:#6b7280;">Or paste this link into your browser:</p>';
    $html .= '<p style="margin:0;font-size:12px;color:#6b7280;word-break:break-all;">' . $esc($confirm_url) . '</p>';
    $html .= '</td></tr>';
    $html .= '<tr><td style="padding:16px 28px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;line-height:1.5;">';
    $html .= 'You received this because someone (hopefully you) entered this address at ' . $esc($site_url) . '. If not, ignore this email — no further messages will follow.<br>';
    $html .= '<a href="' . $esc($unsub_url) . '" style="color:#9ca3af;text-decoration:underline;">Unsubscribe</a>';
    $html .= '</td></tr></table></body></html>';

try {
        $result = fec_resend_send($email, $subject, $html, $plain, array(
            'headers' => array(
                'List-Unsubscribe'      => '<' . $unsub_url . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ),
            'tags' => array(
                array('name' => 'category', 'value' => 'confirmation'),
            ),
        ));

        if (!$result['ok']) {
            JLog::add('Resend confirm failed for ' . $email . ': '
                . $result['error'] . ' (HTTP ' . $result['code'] . ')',
                JLog::ERROR, 'newsletter');
            return false;
        }
        // Per ora non salviamo il message_id — le confirm sono one-shot transactional.
        // Quando faremo il digest weekly, lì sì.
        return true;
    } catch (Exception $e) {
        JLog::add('Resend confirm exception for ' . $email . ': ' . $e->getMessage(),
            JLog::ERROR, 'newsletter');
        return false;
    }
}

function fec_nl_render_page($title, $body) {
    header('Content-Type: text/html; charset=utf-8');
    $site = JFactory::getConfig()->get('sitename', 'FreeExtraChips');
    $home = rtrim(JUri::root(), '/');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $b = htmlspecialchars($body,  ENT_QUOTES, 'UTF-8');
    $s = htmlspecialchars($site,  ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$t} — {$s}</title>
<style>
  body{margin:0;padding:48px 16px;background:#f6f7f9;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1c1f24;}
  .card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:32px;}
  h1{margin:0 0 12px;font-size:20px;}
  p{margin:0 0 20px;font-size:15px;line-height:1.5;color:#374151;}
  a.btn{display:inline-block;padding:10px 16px;background:#1c1f24;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;}
</style>
</head>
<body>
  <div class="card">
    <h1>{$t}</h1>
    <p>{$b}</p>
    <a class="btn" href="{$home}/">Back to {$s}</a>
  </div>
</body>
</html>
HTML;
}