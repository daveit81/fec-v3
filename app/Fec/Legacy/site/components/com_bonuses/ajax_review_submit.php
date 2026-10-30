<?php
/**
 * FEC Casino Review v2.1 — write-a-review AJAX endpoint (sprint 2, step 4)
 *
 * Receives form POST from fec_render_review_form() output, orchestrates:
 *   validate -> anti-spam (7 layers) -> insert -> moderation email
 *
 * Responds:
 *   - JSON when X-Requested-With: XMLHttpRequest (AJAX path)
 *   - HTTP 302 redirect to Referer (or casino page fallback) for no-JS
 *     submits, with ?fec_review_status=queued|error appended.
 *
 * Anti-spam silent-fail policy:
 *   When fec_review_check_spam_signals returns reject=true (honeypot,
 *   timing, turnstile, rate-limit), we log to the throttle table with
 *   status='spam' and respond with the SAME success message legitimate
 *   submissions get. Bots cannot fingerprint which layer caught them.
 *
 * Errors anywhere in the pipeline are caught; no stack traces or DB
 * details leak to the client - generic "submit failed" message only.
 *
 * Deploy path: /components/com_bonuses/ajax_review_submit.php
 * Permissions: 644
 */

// ---- Joomla 2.5 bootstrap (standalone entry point) ----
// PORT: the script bootstrapped Joomla itself (_JEXEC, JPATH_BASE, includes/defines.php,
// includes/framework.php); here the runner boots the layer before the include
// (ComponentRunner::runPublicScript(), fec.legacy.public_scripts) and JPATH_BASE is the
// virtual docroot, where the lib is the generated slice.
defined('_JEXEC') or die('Restricted access');

// We need only DB + Mailer; skip $app->initialise() (heavy + unneeded
// for a pure data-submit endpoint with no Joomla request routing).
$app = JFactory::getApplication('site');

// Load FEC helpers (which also pulls in fec_review_config.php at preamble)
$libPath = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';
if (!file_exists($libPath)) {
    http_response_code(500);
    fec_header('Content-Type: text/plain; charset=utf-8');
    echo 'FEC review endpoint: lib missing at ' . $libPath;
    jexit();
}
require_once $libPath;

// Hard-fail if the public-API helpers aren't available (lib version mismatch)
$requiredFns = array(
    'fec_review_validate_submit',
    'fec_review_check_spam_signals',
    'fec_review_insert',
    'fec_review_log_spam',
    'fec_review_send_moderation_email',
    '_fec_review_client_ip',
);
foreach ($requiredFns as $fn) {
    if (!function_exists($fn)) {
        http_response_code(500);
        fec_header('Content-Type: text/plain; charset=utf-8');
        echo 'FEC review endpoint: required helper missing: ' . $fn;
        jexit();
    }
}


// ---- Response mode + helpers ----

$isAjax = (
       (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0)
    || !empty($_GET['ajax'])
    || !empty($_POST['ajax'])
);

$pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;

$sendJson = function ($success, $message, $errors = array()) {
    if (!headers_sent()) {
        fec_header('Content-Type: application/json; charset=utf-8');
        fec_header('Cache-Control: no-store, no-cache, must-revalidate');
        fec_header('Pragma: no-cache');
    }
    $payload = array(
        'success' => (bool) $success,
        'message' => (string) $message,
    );
    if (!empty($errors) && is_array($errors)) {
        $payload['errors'] = $errors;
    }
    echo json_encode($payload);
    jexit();
};

$sendRedirect = function ($success, $message, $pid) {
    // Prefer redirecting back to the page the form was submitted from,
    // so testing in fec_template_test.php sandbox stays in the sandbox.
    $ref = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : '';
    if ($ref !== '' && (strpos($ref, 'http://') === 0 || strpos($ref, 'https://') === 0)) {
        $url = $ref;
    } else {
        $url = '/index.php?option=com_content&view=article&Itemid=26&catid=8&id=' . (int) $pid;
    }

    // Strip any existing fec_review_* params from the URL before appending
    $url = preg_replace('/[?&]fec_review_(status|msg)=[^&#]*/', '', $url);
    $url = preg_replace('/[?&]+#/', '#', $url);

    // Drop any hash, we'll re-add #cr-review-write
    $hashPos = strpos($url, '#');
    if ($hashPos !== false) $url = substr($url, 0, $hashPos);

    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    $url .= $sep . 'fec_review_status=' . ($success ? 'queued' : 'error');
    if (!$success && $message !== '') {
        $url .= '&fec_review_msg=' . urlencode(substr($message, 0, 200));
    }
    $url .= '#cr-review-write';

    if (!headers_sent()) {
        fec_header('Location: ' . $url, true, 303); // 303 = POST -> GET safe
    }
    jexit();
};

$respond = function ($success, $message, $errors = array())
           use ($isAjax, $pid, $sendJson, $sendRedirect) {
    if ($isAjax) {
        $sendJson($success, $message, $errors);
    } else {
        $sendRedirect($success, $message, $pid);
    }
};


// ---- Method check ----

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 'Invalid request method.');
}


// ---- Pipeline (wrapped in try/catch for safety) ----

try {
    $raw = $_POST;

    // 1. Validate
    $validation = fec_review_validate_submit($raw);
    if (empty($validation['valid'])) {
        $respond(false, 'Please correct the highlighted fields.', $validation['errors']);
    }
    $clean = $validation['clean'];

    // 2. Anti-spam (7 layers): honeypot, timing, turnstile, rate-ip,
    //    rate-ip-casino, url-in-body, bad-word.
    $db   = JFactory::getDbo();
    $ip   = _fec_review_client_ip();
    $spam = fec_review_check_spam_signals($clean, $raw, $ip, $db);

    $successMessage = 'Thank you! Your review has been submitted and is awaiting moderation. '
                    . 'It typically takes 1 to 3 days to appear.';

    if (!empty($spam['reject'])) {
        // Silent fail: log to throttle table + respond with SAME success
        // message a legitimate submit gets. Bots can't fingerprint.
        fec_review_log_spam($db, $clean, $ip, $spam);
        $respond(true, $successMessage);
    }

    // 3. Insert into jos_jreviews_comments with published=0 (queue)
    $reviewId = fec_review_insert($db, $clean, $ip, $spam);
    if (!$reviewId) {
        // Real failure (DB error). insert() logs via error_log internally.
        $respond(false, 'There was a problem submitting your review. Please try again in a moment.');
    }

    // 4. Notification email (non-fatal on failure - review is still queued
    //    and visible via Joomla admin -> JReviews -> Comments)
    fec_review_send_moderation_email($db, $reviewId);

    // 5. Done
    $respond(true, $successMessage);

} catch (Exception $e) {
    error_log('ajax_review_submit pipeline error: ' . $e->getMessage());
    $respond(false, 'An unexpected error occurred. Please try again in a moment.');
}