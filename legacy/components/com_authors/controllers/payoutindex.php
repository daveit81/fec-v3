<?php
/**
 * AuthorsControllerPayoutindex — monthly snapshot of the FEC Payout Index.
 *
 * Task: payoutindex.snapshot (GET, key-gated, no session)
 *
 *   index.php?option=com_authors&task=payoutindex.snapshot&key=<FEC_PI_CRON_KEY>
 *       [&edition=YYYY-MM]  default: last month (the cron runs on the 1st)
 *       [&force=1]          rebuild an edition that already exists
 *       [&dry=1]            compute + render a preview card, insert nothing
 *
 * Why a web task rather than a CLI script: the snapshot needs exactly the
 * data the live page renders (JRoute URLs, jReviews exclusions, FXClock
 * threshold), so it runs inside Joomla and the cron is one curl line. The
 * key lives outside the webroot in /home/freeextr/conf/fec_payout_index.php
 * (define FEC_PI_CRON_KEY and FEC_PI_NOTIFY_TO). Without that file the task
 * refuses everything.
 *
 * Output: JSON. Exit codes are HTTP: 403 bad key, 500 failure, 200 otherwise.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/payoutindex.php';

class AuthorsControllerPayoutindex extends JController
{
    public function snapshot()
    {
        $app = JFactory::getApplication();

        $key = (string)JRequest::getVar('key', '', 'get', 'string');
        if (!self::keyOk($key)) {
            $this->emit(array('ok' => false, 'error' => 'forbidden'), 403);
            return;
        }

        $edition = trim((string)JRequest::getVar('edition', '', 'get', 'string'));
        $force   = (int)JRequest::getVar('force', 0, 'get', 'int') === 1;
        $dry     = (int)JRequest::getVar('dry', 0, 'get', 'int') === 1;

        @set_time_limit(120);
        $res = AuthorsModelPayoutindex::snapshot($edition !== '' ? $edition : null, $force, $dry);
        $this->emit($res, !empty($res['ok']) ? 200 : 500);
    }

    protected static function keyOk($given)
    {
        $given = trim((string)$given);
        if ($given === '' || strlen($given) < 16) return false;
        $cfg = AuthorsModelPayoutindex::EDITIONS_CONFIG;
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
