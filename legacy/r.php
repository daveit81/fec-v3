<?php
/**
 * /r.php — Newsletter click tracking + redirect.
 *
 * URL format: /r.php?c=<campaign_id>&b=<bid>&l=<label>&sig=<hmac>&s=<send_id>
 * HMAC: sha256(c|b|l) — signs only the tamper-sensitive params.
 * send_id is metadata, unsigned.
 *
 * Flow: verify HMAC → resolve dest URL → log → optional GA ping → 302
 */

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$app = JFactory::getApplication('site');
$app->initialise();

require_once JPATH_BASE . '/components/com_bonuses/helpers/fec_common_lib.php';

function r_fail($code, $msg) {
    header('HTTP/1.1 ' . $code . ' ' . $msg);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $msg;
    exit;
}

$campaignId = isset($_GET['c']) ? (int)$_GET['c'] : 0;
$sendId     = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$bid        = isset($_GET['b']) ? (int)$_GET['b'] : 0;
$label      = isset($_GET['l']) ? preg_replace('/[^a-z0-9_]/i', '', (string)$_GET['l']) : 'bonus';
$sig        = isset($_GET['sig']) ? (string)$_GET['sig'] : '';

if (!$campaignId || !$bid || !$sig || $label === '') {
    r_fail('400', 'Bad Request');
}

// Verify HMAC (only c|b|l — s is metadata)
$expected = fec_nl_click_signature($campaignId, $bid, $label);
if (!hash_equals($expected, $sig)) {
    r_fail('400', 'Invalid signature');
}

$db = JFactory::getDbo();

// Resolve send → subscriber + country + campaign slug
$subscriberId = 0; $countryCode = null; $campaignSlug = '';
if ($sendId > 0) {
    $db->setQuery(
        'SELECT s.subscriber_id, sub.country_code, c.slug'
        . ' FROM ' . $db->qn('#__newsletter_sends') . ' s'
        . ' INNER JOIN ' . $db->qn('#__newsletter_subscribers') . ' sub ON sub.id = s.subscriber_id'
        . ' INNER JOIN ' . $db->qn('#__newsletter_campaigns') . ' c ON c.id = s.campaign_id'
        . ' WHERE s.id = ' . $sendId . ' AND s.campaign_id = ' . $campaignId
        . ' LIMIT 1'
    );
    $row = $db->loadAssoc();
    if ($row) {
        $subscriberId = (int)$row['subscriber_id'];
        $countryCode  = $row['country_code'];
        $campaignSlug = $row['slug'];
    }
}

// If no send context, still need campaign slug for UTM
if ($campaignSlug === '') {
    $db->setQuery('SELECT slug FROM ' . $db->qn('#__newsletter_campaigns') . ' WHERE id = ' . $campaignId);
    $campaignSlug = (string)$db->loadResult();
}

// Resolve destination
$destUrl = '';
if ($label === 'bonus' && $bid > 0) {
    $db->setQuery(
        'SELECT a.bid, a.cid, a.type FROM ' . $db->qn('#__bonuses') . ' a'
        . ' WHERE a.bid = ' . $bid . ' LIMIT 1'
    );
    $bonus = $db->loadAssoc();
    if ($bonus) {
        $siteUrl = rtrim(JUri::root(false), '/');
        $destUrl = $siteUrl . '/index.php?option=com_bonuses&view=details'
            . '&type=' . urlencode($bonus['type'])
            . '&bid=' . (int)$bonus['bid']
            . '&cid=' . (int)$bonus['cid']
            . '&utm_source=newsletter'
            . '&utm_medium=email'
            . '&utm_campaign=' . urlencode($campaignSlug)
            . '&utm_content=bid_' . (int)$bonus['bid'];
    }
}

if ($destUrl === '') {
    r_fail('404', 'Link target not found');
}

// Log click (never block redirect on logging error)
try {
    $secret = JFactory::getConfig()->get('secret');
    $ip = '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $p = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); $ip = trim($p[0]); }
    elseif (!empty($_SERVER['REMOTE_ADDR'])) $ip = $_SERVER['REMOTE_ADDR'];

    $obj = new stdClass();
    $obj->campaign_id   = $campaignId ?: null;
    $obj->subscriber_id = $subscriberId ?: null;
    $obj->send_id       = $sendId ?: null;
    $obj->bid           = $bid ?: null;
    $obj->url_label     = $label;
    $obj->dest_url      = substr($destUrl, 0, 2000);
    $obj->ip_hash       = $ip ? hash('sha256', $ip . '|' . $secret) : null;
    $obj->user_agent    = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    $obj->country_code  = $countryCode;
    $obj->referer       = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : null;
    $obj->clicked_at    = JFactory::getDate()->toSql();
    $db->insertObject('#__newsletter_clicks', $obj);
} catch (Exception $e) {
    error_log('newsletter_clicks insert failed: ' . $e->getMessage());
}

// GA4 Measurement Protocol (opzionale, no-op se config vuota)
fec_nl_ga_track_click(array(
    'campaign_id'   => $campaignId,
    'campaign_slug' => $campaignSlug,
    'subscriber_id' => $subscriberId,
    'bid'           => $bid,
    'country'       => $countryCode,
    'label'         => $label,
));

// Redirect
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . $destUrl, true, 302);
exit;