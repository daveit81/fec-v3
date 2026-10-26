<?php
/**
 * AuthorsControllerDeskadd — quick desk-baseline entry for the FXShield cockpit.
 *
 * Lets an editor add a casino to FXShield as a DESK estimate (confidence D)
 * without hand-written SQL: writes the 9 baseline fields (verified_first_party
 * = 0) and, optionally, one ledger event. Does NOT recompute the score from the
 * web tier (by design — no shell_exec); the weekly cron or a manual
 * `safety-engine.php --casino=ID` picks it up.
 *
 * Fact-events (WD_*, LIC_*) go through the same dupe_guard the DB enforces, so a
 * repeated sanction is rejected by the unique index, not silently doubled.
 *
 * Task: deskadd.save  (POST, token-gated, admin-gated)
 *
 * 2.7.43: after save the editor is redirected back to the same casino in edit
 * mode (?casino_id=N), so multiple ledger events are added one save at a time
 * while the baseline upsert stays idempotent.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/verification.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsControllerDeskadd extends JController
{
    /** Baseline field codes and their max points, mirroring the engine/spec. */
    protected static function baselineMax()
    {
        return array(
            'license_tier'      => 32,  // value carries tier points (0/8/18/26/32)
            'adr'               => 8,   // 0 / 4 / 8
            'entity_registered' => 4,
            'license_footer'    => 4,
            'no_fragmentation'    => 4,
            'rg_2fa'            => 4,
            'rg_limits'         => 6,
            'rg_selfexclusion'  => 6,
            'tc_fairness'       => 32,  // 32 - 4*clauses
        );
    }

    /** Event taxonomy available to the quick form (code => [weight, decay]). */
    protected static function eventMap()
    {
        return array(
            'WD_BLACKLIST'         => array(-20.0, 'slow'),
            'LIC_LAPSED'           => array(-15.0, 'slow'),
            'LIC_CHANGED'          => array(-5.0,  'standard'),
            'WD_WARNING'           => array(-10.0, 'slow'),
            'WD_UNFAIR_TC'         => array(-8.0,  'slow'),
            'COMPLAINT_PATTERN'    => array(-8.0,  'standard'),
            'LIC_UPGRADED'         => array(5.0,   'standard'),
            'WD_DELISTED'          => array(2.0,   'standard'),
        );
    }

    public function save()
    {
        // Auth + CSRF
        if (!AuthorsModelDashboard::isAuthorized()) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin', 'Forbidden', 'error');
            return;
        }
        if (!JRequest::checkToken('post')) {
            $this->setRedirect('index.php?option=com_authors&view=deskadd', 'Invalid form token. Please retry.', 'error');
            return;
        }

        $casinoId = (int) JRequest::getVar('casino_id', 0, 'post', 'int');
        if ($casinoId <= 0) {
            $this->setRedirect('index.php?option=com_authors&view=deskadd', 'Select a casino first.', 'error');
            return;
        }

        $db    = JFactory::getDbo();
        $user  = (int) JFactory::getUser()->id;
        $today = JFactory::getDate()->toSql();
        $todayDate = JFactory::getDate()->format('Y-m-d');

        // --- Gather + validate baseline values ---
        $tier    = (int) JRequest::getVar('license_tier', 0, 'post', 'int');   // points: 0/8/18/26/32
        $adr     = (int) JRequest::getVar('adr', 0, 'post', 'int');            // 0/4/8
        $clauses = (int) JRequest::getVar('tc_clauses', 0, 'post', 'int');     // 0..8
        if ($clauses < 0) $clauses = 0;
        if ($clauses > 8) $clauses = 8;
        $tcPoints = max(0, 32 - 4 * $clauses);

        if (!in_array($tier, array(0, 8, 18, 26, 32), true)) $tier = 0;
        if (!in_array($adr,  array(0, 4, 8), true)) $adr = 0;

        // Tier code (for the license_tier field_value, used by report/cap logic)
        $tierCodeByPts = array(32 => 'T1', 26 => 'T2', 18 => 'T3', 8 => 'T4', 0 => 'T5');
        $tierCode = $tierCodeByPts[$tier];

        $cb = function ($name) { return JRequest::getVar($name, 0, 'post', 'int') ? 1 : 0; };
        $vals = array(
            'license_tier'      => array('value' => $tierCode, 'points' => $tier),
            'adr'               => array('value' => ($adr === 8 ? 'reachable' : ($adr === 4 ? 'named' : 'none')), 'points' => $adr),
            'entity_registered' => array('value' => $cb('entity_registered') ? 'yes' : 'no', 'points' => $cb('entity_registered') ? 4 : 0),
            'license_footer'    => array('value' => $cb('license_footer') ? 'yes' : 'no', 'points' => $cb('license_footer') ? 4 : 0),
            'no_fragmentation'    => array('value' => $cb('no_fragmentation') ? 'yes' : 'no', 'points' => $cb('no_fragmentation') ? 4 : 0),
            'rg_2fa'            => array('value' => $cb('rg_2fa') ? 'yes' : 'no', 'points' => $cb('rg_2fa') ? 4 : 0),
            'rg_limits'         => array('value' => $cb('rg_limits') ? 'yes' : 'no', 'points' => $cb('rg_limits') ? 6 : 0),
            'rg_selfexclusion'  => array('value' => $cb('rg_selfexclusion') ? 'yes' : 'no', 'points' => $cb('rg_selfexclusion') ? 6 : 0),
            'tc_fairness'       => array('value' => (string)$clauses, 'points' => $tcPoints),
        );

        // --- Optional event ---
        $evCode = JRequest::getVar('event_code', '', 'post', 'cmd');
        $evDate = JRequest::getVar('event_date', '', 'post', 'string');
        $evNote = trim((string) JRequest::getVar('event_note', '', 'post', 'string'));
        $evMap  = self::eventMap();
        $addEvent = ($evCode !== '' && isset($evMap[$evCode]));

        $db->transactionStart();
        try {
            // 1. Baseline upsert — desk: verified_first_party = 0 (NOT verified).
            foreach ($vals as $code => $row) {
                $sql = "INSERT INTO #__fec_safety_baseline
                        (casino_id, field_code, field_value, points,
                         verified_first_party, verified_at, evidence_url)
                        VALUES (" . $casinoId . ", " . $db->quote($code) . ", "
                        . $db->quote($row['value']) . ", " . (float)$row['points'] . ", 0, NULL, NULL)
                        ON DUPLICATE KEY UPDATE
                         field_value = VALUES(field_value),
                         points = VALUES(points)";
                // NOTE: on update we deliberately do NOT touch verified_first_party
                // or verified_at — a desk edit must never downgrade a field that
                // was previously first-party verified.
                $db->setQuery($sql);
                $db->execute();
            }

            // 2. Optional event. Fact-events hit the dupe_guard unique index;
            //    a duplicate throws and we surface a friendly message.
            if ($addEvent) {
                list($w, $decay) = $evMap[$evCode];
                $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $evDate) ? $evDate : $todayDate;
                $noteSql = ($evNote !== '') ? $db->quote($evNote) : 'NULL';
                $sql = "INSERT INTO #__fec_safety_events
                        (casino_id, event_code, event_date, base_weight, decay_class,
                         source_type, evidence_url, evidence_note, visibility, status,
                         created_by, created_at)
                        VALUES (" . $casinoId . ", " . $db->quote($evCode) . ", "
                        . $db->quote($d) . ", " . (float)$w . ", " . $db->quote($decay) . ", "
                        . "'watchdog', NULL, " . $noteSql . ", 'public', 'active', "
                        . $user . ", " . $db->quote($today) . ")";
                $db->setQuery($sql);
                $db->execute();
            }

            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            $msg = $e->getMessage();
            if (strpos($msg, 'uniq_dupe_guard') !== false || strpos($msg, 'Duplicate entry') !== false) {
                $msg = 'That event already exists for this casino on that date (duplicate blocked).';
            }
            $this->setRedirect('index.php?option=com_authors&view=deskadd&casino_id=' . $casinoId, 'Save failed: ' . $msg, 'error');
            return;
        }

        $note = 'Desk baseline saved (confidence D). Score will appear at the next recalc '
              . '(weekly cron, or run safety-engine.php --casino=' . $casinoId . ').';
        if ($addEvent) $note .= ' Event recorded.';
        $note .= ' Back on this casino in edit mode: add the next event or adjust and re-save.';
        $this->setRedirect('index.php?option=com_authors&view=deskadd&casino_id=' . $casinoId, $note, 'message');
    }
}
