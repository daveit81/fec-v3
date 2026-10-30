#!/usr/bin/env php
<?php
/**
 * /home/freeextr/cron/send_weekly_digest.php
 *
 * Country-segmented weekly digest sender.
 *
 * Per ISO week generates one campaign per distinct country among confirmed
 * subscribers. Slug pattern: weekly-{ISO_YEAR-WW}-{cc} (lowercase), or
 * weekly-{ISO_YEAR-WW}-xx for NULL country (legacy/unknown).
 *
 * Args:
 *   --dry-run                       Don't write to DB or send
 *   --limit=N                       Cap GLOBAL number of sends across all countries
 *   --to=email                      Force recipient (testing). Auto-generates throwaway slug.
 *   --days=N                        Lookback window for bonuses (default 7, max 60)
 *   --force-slug-base=SLUG          Override slug base (advanced)
 *   --subject="..."                 Override email subject
 *   --only-country=cc               Process only one country (use 'xx' for NULL group)
 *
 * Cron schedule (cPanel, as user freeextr):
 *   0 2 * * 2 /opt/cpanel/ea-php56/root/usr/bin/php /home/freeextr/cron/send_weekly_digest.php >> /home/freeextr/cron/digest.log 2>&1
 */

if (php_sapi_name() !== 'cli'
    && (!empty($_SERVER['REQUEST_METHOD']) || !empty($_SERVER['HTTP_USER_AGENT']))) {
    header('Status: 403 Forbidden');
    echo "CLI/CGI from shell only\n";
    exit(4);
}

function out($m) { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"; }
function fail($m, $c = 4) { out('ERROR: ' . $m); exit($c); }

$opts = array(
    'dry-run' => false, 'limit' => 0, 'to' => '', 'days' => 7,
    'force-slug-base' => '', 'subject' => '', 'only-country' => '',
);
foreach ($argv as $arg) {
    if ($arg === '--dry-run') { $opts['dry-run'] = true; continue; }
    if (preg_match('/^--limit=(\d+)$/', $arg, $m))             { $opts['limit'] = (int)$m[1]; continue; }
    if (preg_match('/^--to=(.+)$/', $arg, $m))                 { $opts['to'] = trim($m[1]); continue; }
    if (preg_match('/^--days=(\d+)$/', $arg, $m))              { $opts['days'] = max(1, min(60, (int)$m[1])); continue; }
    if (preg_match('/^--force-slug-base=(.+)$/', $arg, $m))    { $opts['force-slug-base'] = trim($m[1]); continue; }
    if (preg_match('/^--subject=(.+)$/', $arg, $m))            { $opts['subject'] = trim($m[1]); continue; }
    if (preg_match('/^--only-country=(.+)$/', $arg, $m))       { $opts['only-country'] = strtolower(trim($m[1])); continue; }
}

if ($opts['to'] !== '' && $opts['force-slug-base'] === '') {
    $opts['force-slug-base'] = 'test-' . date('Ymd-His');
}

$lockFile = '/home/freeextr/cron/.send_weekly_digest.lock';
$lockFp = @fopen($lockFile, 'c');
if (!$lockFp) {
    out('ERROR: cannot open lock file ' . $lockFile);
    out('Likely a permission/ownership issue (file owned by root?).');
    out('Fix: chown -R freeextr:freeextr /home/freeextr/cron/');
    exit(2);
}
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    out('Another instance is running. Abort.');
    exit(1);
}

out('FEC weekly digest cron starting');
if ($opts['dry-run']) out('!! DRY RUN — no email/DB writes !!');
if ($opts['to'])      out('!! FORCED RECIPIENT: ' . $opts['to'] . ' — throwaway slug !!');
if ($opts['only-country']) out('!! ONLY COUNTRY: ' . $opts['only-country'] . ' !!');

define('_JEXEC', 1);
define('JPATH_BASE', '/home/freeextr/public_html');
if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

$_SERVER['HTTP_HOST']      = 'www.freeextrachips.com';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['SCRIPT_NAME']    = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS']          = 'on';
$_SERVER['SERVER_PORT']    = '443';

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';
try { $app = JFactory::getApplication('site'); }
catch (Exception $e) { fail('Joomla bootstrap: ' . $e->getMessage(), 4); }

require_once JPATH_BASE . '/components/com_bonuses/helpers/fec_common_lib.php';
require_once JPATH_BASE . '/components/com_bonuses/helpers/bonustypesarray.php';
require_once JPATH_BASE . '/components/com_bonuses/helpers/currenciesarray.php';

$db = JFactory::getDbo();
$siteUrl = rtrim(JUri::root(false), '/');

$slugBase    = $opts['force-slug-base'] !== '' ? $opts['force-slug-base'] : 'weekly-' . date('o-\WW');
$periodLabel = 'Week of ' . date('F j, Y');
$days        = $opts['days'];

out('Slug base: ' . $slugBase . ', period: ' . $periodLabel . ', window: ' . $days . 'd');

$db->setQuery(
    'SELECT DISTINCT country_code FROM ' . $db->qn('#__newsletter_subscribers')
    . ' WHERE status = ' . $db->q('confirmed')
    . ' ORDER BY country_code IS NULL ASC, country_code ASC'
);
$countryRows = (array)$db->loadColumn();

if ($opts['only-country']) {
    $cc = strtolower($opts['only-country']);
    if ($cc === 'xx' || $cc === 'null') {
        $countryRows = array(null);
    } else {
        $countryRows = array(strtoupper($cc));
    }
}

if (empty($countryRows)) {
    out('No confirmed subscribers. Nothing to do.');
    flock($lockFp, LOCK_UN); fclose($lockFp);
    exit(0);
}

out('Countries to process: ' . count($countryRows) . ' (' . implode(', ', array_map(function($c){ return $c === null ? 'xx' : $c; }, $countryRows)) . ')');

$globalSent = 0;
$globalFailed = 0;
$campaignsProcessed = 0;
$campaignsSkipped = 0;
$globalLimitReached = false;  // flag for cross-country limit termination

foreach ($countryRows as $country) {
    $ccTag = $country === null ? 'xx' : strtolower($country);
    $slug = $slugBase . '-' . $ccTag;
    out('');
    out('========== Country: ' . ($country === null ? 'xx (unknown)' : $country) . ' — slug: ' . $slug . ' ==========');

    // Idempotency check
    $db->setQuery(
        'SELECT id, status, content_html, content_plain, subject FROM '
        . $db->qn('#__newsletter_campaigns')
        . ' WHERE slug = ' . $db->q($slug)
    );
    $existing = $db->loadAssoc();

    $campaignId = 0;
    $htmlBody = ''; $plainBody = '';
    $subject = $opts['subject'] !== '' ? $opts['subject'] : "This week's verified bonuses — " . date('M j, Y');
    $isResume = false;

    if ($existing) {
        $st = $existing['status'];
        if ($st === 'sent') { out('Already sent. Skip.'); $campaignsSkipped++; continue; }
        if (in_array($st, array('failed','cancelled'), true)) { out('Status "'.$st.'" — manual intervention needed. Skip.'); $campaignsSkipped++; continue; }
        if (in_array($st, array('draft','scheduled','sending'), true)) {
            $campaignId = (int)$existing['id'];
            $htmlBody   = (string)$existing['content_html'];
            $plainBody  = (string)$existing['content_plain'];
            $subject    = (string)$existing['subject'];
            $isResume   = true;
            out('Resuming campaign id=' . $campaignId);
        }
    }

    if (!$isResume) {
        $countryArg = $country !== null ? $country : null;
        $verified = fec_digest_fetch_verified_this_week($db, 3, $days, $countryArg);
        $exclude  = array(); foreach ($verified as $r) $exclude[] = (int)$r['bid'];
        $new      = fec_digest_fetch_new_bonuses($db, 3, $days, $exclude, $countryArg);

        out('Verified: ' . count($verified) . ', New: ' . count($new));

        if (empty($verified) && empty($new)) {
            out('No content for this country. Skip.');
            $campaignsSkipped++;
            continue;
        }

        $renderOpts = array(
            'campaign_slug' => $slug,
            'period_label'  => $periodLabel,
            'preheader'     => "This week's verified bonuses + new offers.",
            'countrycode'   => $country !== null ? strtolower($country) : 'us',
        );
        $htmlBody  = fec_digest_render_html($verified, $new, $renderOpts);
        $plainBody = fec_digest_render_plain($verified, $new, $renderOpts);

        if ($opts['dry-run']) {
            out('DRY RUN — would create campaign with html=' . strlen($htmlBody) . ' bytes');
            $campaignsSkipped++;
            continue;
        }

        $now = date('Y-m-d H:i:s');
        $db->setQuery(
            'INSERT INTO ' . $db->qn('#__newsletter_campaigns')
            . ' (slug, subject, content_html, content_plain, status, started_at, created_at, updated_at)'
            . ' VALUES ('.$db->q($slug).', '.$db->q($subject).', '.$db->q($htmlBody).', '.$db->q($plainBody).', '
            . $db->q('sending').', '.$db->q($now).', '.$db->q($now).', '.$db->q($now).')'
        );
        $db->execute();
        $campaignId = (int)$db->insertid();
        out('Campaign created: id=' . $campaignId);

        $whereCC = $country === null ? 'country_code IS NULL' : 'country_code = ' . $db->q($country);
        $db->setQuery(
            'SELECT id, email, unsubscribe_token FROM ' . $db->qn('#__newsletter_subscribers')
            . ' WHERE status = ' . $db->q('confirmed') . ' AND ' . $whereCC
        );
        $subscribers = $db->loadAssocList();
        out('Subscribers for this country: ' . count($subscribers));

        if (empty($subscribers)) {
            $db->setQuery('UPDATE '.$db->qn('#__newsletter_campaigns').' SET status='.$db->q('failed').', completed_at='.$db->q($now).' WHERE id='.$campaignId);
            $db->execute();
            $campaignsSkipped++;
            continue;
        }

        $valueRows = array();
        foreach ($subscribers as $s) {
            $valueRows[] = '('.$campaignId.', '.(int)$s['id'].', '.$db->q($s['email']).', '.$db->q('queued').', 0, '.$db->q($now).')';
        }
        $db->setQuery(
            'INSERT IGNORE INTO ' . $db->qn('#__newsletter_sends')
            . ' (campaign_id, subscriber_id, email, status, attempt_count, created_at) VALUES '
            . implode(',', $valueRows)
        );
        $db->execute();

        $db->setQuery('UPDATE '.$db->qn('#__newsletter_campaigns').' SET recipient_count='.count($subscribers).' WHERE id='.$campaignId);
        $db->execute();
        out('Queue populated');
    }

    // Substitute click-tracking placeholders {{CID}} and {{SIG_<bid>}} now that
    // we have the real campaign_id. Idempotent: if the snapshot was already
    // substituted (clean resume), this no-ops. {{SID}} and {{UNSUB_URL}} remain
    // as placeholders and are substituted per-recipient in the send loop.
    // Defensive: handles the rare case of an interrupt between INSERT and this
    // block — on resume, placeholders are still present and we re-substitute.
    if (strpos($htmlBody, '{{CID}}') !== false || strpos($htmlBody, '{{SIG_') !== false) {
        out('Substituting click-tracking placeholders');
        preg_match_all('/\{\{SIG_(\d+)\}\}/', $htmlBody, $sigMatches);
        $bidList = array_unique($sigMatches[1]);

        $htmlBody  = str_replace('{{CID}}', (string)$campaignId, $htmlBody);
        $plainBody = str_replace('{{CID}}', (string)$campaignId, $plainBody);
        foreach ($bidList as $b) {
            $sig = fec_nl_click_signature($campaignId, (int)$b, 'bonus');
            $htmlBody  = str_replace('{{SIG_' . $b . '}}', $sig, $htmlBody);
            $plainBody = str_replace('{{SIG_' . $b . '}}', $sig, $plainBody);
        }

        // Persist substituted snapshot so a future resume picks it up clean.
        $db->setQuery(
            'UPDATE ' . $db->qn('#__newsletter_campaigns')
            . ' SET content_html=' . $db->q($htmlBody)
            . ', content_plain=' . $db->q($plainBody)
            . ' WHERE id=' . $campaignId
        );
        $db->execute();
    }

    // Send loop for this country's campaign
    $sentOk = 0; $sentFailed = 0; $processed = 0;
    while (true) {
        if ($opts['limit'] > 0 && ($globalSent + $globalFailed + $processed) >= $opts['limit']) {
            out('Global limit reached');
            $globalLimitReached = true;
            break;  // exit this while; finalize still runs below
        }

        $db->setQuery(
            'SELECT s.id AS send_id, s.subscriber_id, s.email, sub.unsubscribe_token'
            . ' FROM '.$db->qn('#__newsletter_sends').' s'
            . ' INNER JOIN '.$db->qn('#__newsletter_subscribers').' sub ON sub.id = s.subscriber_id'
            . ' WHERE s.campaign_id = '.$campaignId.' AND s.status = '.$db->q('queued')
            . ' AND sub.status = '.$db->q('confirmed')
            . ' ORDER BY s.id ASC LIMIT 100'
        );
        $batch = $db->loadAssocList();
        if (empty($batch)) break;

        foreach ($batch as $row) {
            if ($opts['limit'] > 0 && ($globalSent + $globalFailed + $processed) >= $opts['limit']) break;
            $processed++;
            $sendId     = (int)$row['send_id'];
            $email      = $row['email'];
            $unsubToken = $row['unsubscribe_token'];

            if ($opts['to'] !== '') {
                $actualTo = $opts['to'];
                $unsubUrl = $siteUrl . '/subscribe.php?action=unsubscribe&test=1';
            } else {
                $actualTo = $email;
                $unsubUrl = $siteUrl . '/subscribe.php?action=unsubscribe&token='<REDACTED:literal>'&email='.urlencode($email);
            }

            // Per-recipient substitution: unsub URL (different for each subscriber)
            // and send_id (unique tracking id for click attribution).
            $htmlOut  = str_replace(array('{{UNSUB_URL}}', '{{SID}}'), array($unsubUrl, (string)$sendId), $htmlBody);
            $plainOut = str_replace(array('{{UNSUB_URL}}', '{{SID}}'), array($unsubUrl, (string)$sendId), $plainBody);

            $db->setQuery(
                'UPDATE '.$db->qn('#__newsletter_sends')
                .' SET status='.$db->q('sending').', attempt_count=attempt_count+1, last_attempt_at='.$db->q(date('Y-m-d H:i:s'))
                .' WHERE id='.$sendId
            );
            $db->execute();

            $result = fec_resend_send($actualTo, $subject, $htmlOut, $plainOut, array(
                'headers' => array(
                    'List-Unsubscribe'      => '<'.$unsubUrl.'>',
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ),
                'tags' => array(
                    array('name' => 'category', 'value' => 'weekly_digest'),
                    array('name' => 'campaign', 'value' => $slug),
                    array('name' => 'country', 'value' => $ccTag),
                ),
            ));

            if ($result['ok']) {
                $sentOk++;
                $db->setQuery('UPDATE '.$db->qn('#__newsletter_sends').' SET status='.$db->q('sent').', resend_message_id='.$db->q($result['message_id']).', sent_at='.$db->q(date('Y-m-d H:i:s')).' WHERE id='.$sendId);
                $db->execute();
            } else {
                $sentFailed++;
                $errMsg = isset($result['error']) ? $result['error'] : 'unknown';
                out('  FAIL ' . $email . ': ' . $errMsg);
                $db->setQuery('UPDATE '.$db->qn('#__newsletter_sends').' SET status='.$db->q('failed').', error_message='.$db->q(substr($errMsg, 0, 500)).' WHERE id='.$sendId);
                $db->execute();
            }

            if ($processed % 50 === 0) out('  ... ' . $processed . ' (ok='.$sentOk.', failed='.$sentFailed.')');
            usleep(150000);
        }
    }

    // Finalize this country's campaign (runs even if global limit was hit)
    $finalStatus = $sentOk > 0 ? 'sent' : 'failed';
    $db->setQuery(
        'UPDATE '.$db->qn('#__newsletter_campaigns')
        .' SET status='.$db->q($finalStatus).', sent_count='.$sentOk.', failed_count='.$sentFailed
        .', completed_at='.$db->q(date('Y-m-d H:i:s'))
        .' WHERE id='.$campaignId
    );
    $db->execute();

    out('Country ' . $ccTag . ' done. Sent: ' . $sentOk . ', Failed: ' . $sentFailed);
    $globalSent += $sentOk;
    $globalFailed += $sentFailed;
    $campaignsProcessed++;

    if ($globalLimitReached) {
        out('Global limit hit — stopping after country ' . $ccTag);
        break;  // exit countries foreach cleanly
    }
}

out('');
out('======================================');
out('GRAND TOTAL — Campaigns processed: ' . $campaignsProcessed . ', skipped: ' . $campaignsSkipped);
out('GRAND TOTAL — Sent: ' . $globalSent . ', Failed: ' . $globalFailed);

flock($lockFp, LOCK_UN); fclose($lockFp);
exit($globalSent > 0 || $campaignsSkipped > 0 ? 0 : 5);