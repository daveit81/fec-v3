<?php
/**
 * FEC Casino of the Month — cron
 * ==============================
 * Modes (?mode=):
 *   propose : elect + record proposals for NEXT period (global + target countries)
 *   publish : publish proposed/approved rows for CURRENT period (auto-publish)
 *   auto    : last day of month -> propose; 1st of month -> publish; else noop
 *
 * Schedule (server cron), using ?mode=auto daily, or explicitly:
 *   - run with mode=propose on the 28th-31st (generates next month's picks)
 *   - run with mode=publish at 00:05 on the 1st (auto-publishes anything the
 *     admin didn't override)
 *
 * Security: requires ?key=<FEC_COFM_CRON_KEY>. CHANGE THE KEY BELOW.
 * Placement: web root (/home/freeextr/public_html/fec_cofm_cron.php)
 */

// ---- CONFIG ----
// PORT: no key: this copy runs only from the console (fec:legacy:cli cofm -- --mode=auto|propose|
// publish, scheduled at the crontab's times); it is never served on the web, where the key gated
// the wget of the crontab.
// Target countries, thresholds and rotation are centralized in the lib:
// fec_cofm_config() in fec_common_lib.php — edit there (shared with the
// dashboard API so both elect identically).

// ---- bootstrap ----
// PORT: the script bootstrapped Joomla itself from the web root; here fec:legacy:cli boots the
// layer and includes it (ComponentRunner::runCliScript()). The key gate is gone with the web.
defined('_JEXEC') or die('Restricted access');
foreach ((array) $argv as $fecArg) { // PORT: ?mode= came from the crontab's URL, now from --mode=
    if (strpos((string) $fecArg, '--mode=') === 0) { $_GET['mode'] = substr((string) $fecArg, 7); }
}

$app = JFactory::getApplication('site'); // no initialise() needed (no JRoute)
$db  = JFactory::getDbo();
require_once JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';

$mode = isset($_GET['mode']) ? strtolower((string) $_GET['mode']) : 'auto';

// resolve auto -> propose/publish/noop
if ($mode === 'auto') {
    $day      = (int) date('j');
    $lastDay  = (int) date('t');
    if ($day === $lastDay)      $mode = 'propose';
    elseif ($day === 1)         $mode = 'publish';
    else { echo "auto: nothing to do on day {$day}\n"; jexit(); }
}

echo "=== FEC COFM cron — mode={$mode} — " . date('Y-m-d H:i:s') . " ===\n";

if ($mode === 'propose') {
    $period = fec_cofm_period_next();
    $cfg    = fec_cofm_config();
    $targets = $cfg['target_countries'];
    echo "Proposing for period {$period}\n\n";

    // global pick
    $res = fec_cofm_elect($db, $period, '', $cfg);
    if ($res['pick']) {
        $ok = fec_cofm_record_proposal($db, $period, '', $res['pick']);
        echo sprintf("[global]  pick=%d %s (frank %.2f) %s\n",
            $res['pick']['id'], $res['pick']['title'], (float) $res['pick']['frank'],
            $ok ? 'recorded' : 'skipped (human decision exists)');
        $sl = array();
        foreach ($res['shortlist'] as $s) $sl[] = $s['id'] . ':' . $s['title'];
        echo "          shortlist: " . implode(' | ', $sl) . "\n";
    } else {
        echo "[global]  no eligible candidate\n";
    }

    // per-country picks
    foreach ($targets as $cc) {
        $res = fec_cofm_elect($db, $period, $cc, $cfg);
        if ($res['pick']) {
            $ok = fec_cofm_record_proposal($db, $period, $cc, $res['pick']);
            echo sprintf("[%-4s]    pick=%d %s (frank %.2f) %s\n",
                $cc, $res['pick']['id'], $res['pick']['title'], (float) $res['pick']['frank'],
                $ok ? 'recorded' : 'skipped (human decision exists)');
        } else {
            echo sprintf("[%-4s]    no eligible candidate\n", $cc);
        }
    }
    echo "\nDone. Review/approve in the dashboard before the 1st.\n";

} elseif ($mode === 'publish') {
    $period = fec_cofm_period_now();
    echo "Publishing period {$period}\n";
    $n = fec_cofm_publish_period($db, $period);
    echo "Published {$n} pick(s) (proposed/approved -> published; jr_cofmdate set on global pick).\n";

} else {
    echo "unknown mode\n";
}