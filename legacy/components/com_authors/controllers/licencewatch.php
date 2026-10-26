<?php
/**
 * AuthorsControllerLicencewatch — quarterly freeze of the Licence Watch register.
 *
 * Task: licencewatch.snapshot (GET, key-gated, no session)
 *
 *   /authors?task=licencewatch.snapshot&key=<FEC_PI_CRON_KEY>[&edition=YYYY-MM][&force=1]
 *
 * Freezes the live register into a DRAFT edition (default: the current
 * month). The desk then writes the three sections in the dashboard and
 * publishes. Published editions are never overwritten. Uses the same key file
 * as the Payout Index (/home/freeextr/conf/fec_payout_index.php) and emails
 * FEC_PI_NOTIFY_TO when the draft is ready, through fec_resend_send().
 *
 * Cron (first day of the quarter, 06:20 UTC, origin IP, SEF form):
 *   20 6 1 1,4,7,10 * curl -sk --resolve www.freeextrachips.com:443:170.249.194.122 "https://www.freeextrachips.com/authors?task=licencewatch.snapshot&key=KEY&_=$(date +\%s)" >> /home/freeextr/scripts/licence_watch.log 2>&1
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/licencewatch.php';

class AuthorsControllerLicencewatch extends JController
{
    public function snapshot()
    {
        $key = (string)JRequest::getVar('key', '', 'get', 'string');
        if (!self::keyOk($key)) {
            $this->emit(array('ok' => false, 'error' => 'forbidden'), 403);
            return;
        }
        $edition = trim((string)JRequest::getVar('edition', '', 'get', 'string'));
        $force   = (int)JRequest::getVar('force', 0, 'get', 'int') === 1;

        @set_time_limit(120);
        $res = AuthorsModelLicencewatch::snapshot($edition !== '' ? $edition : null, $force);
        if (!empty($res['ok']) && !empty($res['created'])) {
            $res['notified'] = $this->notify($res);
        }
        $this->emit($res, !empty($res['ok']) ? 200 : 500);
    }

    protected function notify($res)
    {
        $to = '';
        $cfg = AuthorsModelLicencewatch::EDITIONS_CONFIG;
        if (is_readable($cfg)) { try { @include_once $cfg; } catch (Exception $e) {} }
        if (defined('FEC_PI_NOTIFY_TO')) $to = trim((string)FEC_PI_NOTIFY_TO);
        if ($to === '') return 'skipped: FEC_PI_NOTIFY_TO not set';
        $lib = JPATH_SITE . AuthorsModelLicencewatch::COMMON_LIB;
        if (!function_exists('fec_resend_send') && is_readable($lib)) { try { @include_once $lib; } catch (Exception $e) {} }
        if (!function_exists('fec_resend_send')) return 'skipped: fec_resend_send unavailable';

        $site  = 'https://www.freeextrachips.com';
        $label = AuthorsModelLicencewatch::editionNumberLabel($res['number']) . ' · ' . AuthorsModelLicencewatch::editionMonthLabel($res['edition']);
        $lines = array(
            'Licence Watch — ' . $label . ' is frozen as a draft.',
            '',
            'Live casinos: ' . (int)$res['live'] . '. Verified: ' . (int)$res['verified'] . '. Licence events in the window: ' . (int)$res['events'] . '.',
            'Preview: ' . $site . $res['page'] . '?preview=1',
            '',
            'Write the three sections (Overview, What the desk documented, Bottom line) in the dashboard, Licence Watch tab, then publish.',
        );
        $plain = implode("\n", $lines);
        $html  = '<pre style="font: 14px/1.5 -apple-system, Segoe UI, sans-serif; white-space: pre-wrap;">' . htmlspecialchars($plain, ENT_QUOTES, 'UTF-8') . '</pre>';
        try {
            $r = fec_resend_send($to, 'Licence Watch — ' . $label . ' draft ready', $html, $plain, array('tags' => array(array('name' => 'kind', 'value' => 'licence-watch-edition'))));
            return (is_array($r) && !empty($r['ok'])) ? 'sent to ' . $to : 'failed: ' . (is_array($r) && isset($r['error']) ? $r['error'] : 'unknown');
        } catch (Exception $e) {
            return 'failed: ' . $e->getMessage();
        }
    }

    protected static function keyOk($given)
    {
        $given = trim((string)$given);
        if ($given === '' || strlen($given) < 16) return false;
        $cfg = AuthorsModelLicencewatch::EDITIONS_CONFIG;
        if (!is_readable($cfg)) return false;
        try { @include_once $cfg; } catch (Exception $e) { return false; }
        if (!defined('FEC_PI_CRON_KEY')) return false;
        $expected = (string)FEC_PI_CRON_KEY;
        if ($expected === '' || strlen($expected) < 16) return false;
        if (function_exists('hash_equals')) return hash_equals($expected, $given);
        return $expected === $given;
    }

    protected function emit($data, $status)
    {
        if (function_exists('http_response_code')) http_response_code((int)$status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex');
        echo json_encode($data);
        JFactory::getApplication()->close();
    }
}
