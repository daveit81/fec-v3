<?php
/**
 * AuthorsControllerFxshieldadmin — FXShield admin cockpit endpoints.
 *
 * Task:
 *   exportmd — stream a Markdown fact-sheet (.md) for one casino, carrying the
 *              six fxshield_* fields the review generator expects plus context
 *              (baseline, events, payout test, licences, evidence). Read-only.
 *
 * Auth: gated on AuthorsModelDashboard::isAuthorized(). The link is a GET
 * download, so a GET form token is required (built into the href via JRoute
 * is not used here; we accept the authorized session and a token check).
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/verification.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

/** Fallback engine path used when the component option safety_engine_path is
 *  empty. com_authors is a site-only component (no admin views), so Joomla 2.5
 *  offers no screen that surfaces config.xml — the option stays available as
 *  an override, but this default is what runs in practice. Path confirmed by
 *  the site owner, 2026-08-21. */
if (!defined('FXA_SAFETY_ENGINE_DEFAULT')) {
    define('FXA_SAFETY_ENGINE_DEFAULT', '/home/freeextr/public_html/frank/safety-engine.php');
}

class AuthorsControllerFxshieldadmin extends JController
{
    /**
     * fxshieldadmin.recalc — run safety-engine.php in-process for ONE casino.
     *
     * No shell_exec by design: the engine file is include()d with
     * FXSAFETY_WEB_ENTRY defined, which the engine (web-entry build) uses to
     * accept a non-CLI SAPI, read its args from $GLOBALS['FXSAFETY_WEB_ARGS'],
     * throw instead of exit() on fatal, and return instead of exit(0) at the
     * end. Full runs stay cron-only: casino_id is mandatory here. The engine
     * path: component option safety_engine_path if set, else the confirmed
 * FXA_SAFETY_ENGINE_DEFAULT constant defined above the class.
     */
    /**
     * fxshieldadmin.recalcall — full engine run in-process (every casino in
     * the scoring universe), same gate as recalc(). Equivalent to the weekly
     * cron invocation with no arguments. The engine is idempotent (snapshot
     * upsert), so an interrupted run is simply re-run.
     */
    public function recalcall()
    {
        $back = 'index.php?option=com_authors&view=fxshieldadmin';
        if (!AuthorsModelDashboard::isAuthorized()) {
            $this->setRedirect($back, 'Forbidden', 'error');
            return;
        }
        if (!JRequest::checkToken('get')) {
            $this->setRedirect($back, 'Invalid or expired link token. Reload the page and retry.', 'error');
            return;
        }

        $params = JComponentHelper::getParams('com_authors');
        $engine = trim((string) $params->get('safety_engine_path', ''));
        if ($engine === '') {
            $engine = FXA_SAFETY_ENGINE_DEFAULT;
        }
        if (!is_readable($engine)) {
            $this->setRedirect($back, 'Safety engine not readable at: ' . $engine, 'error');
            return;
        }

        if (!defined('FXSAFETY_WEB_ENTRY')) {
            define('FXSAFETY_WEB_ENTRY', 1);
        }
        $GLOBALS['FXSAFETY_WEB_ARGS'] = array();   // no flags = full run, as the cron does

        @set_time_limit(0);
        @ignore_user_abort(true);
        $err = '';
        ob_start();
        try {
            include $engine;
        } catch (Exception $e) {
            $err = $e->getMessage();
        }
        $log = ob_get_clean();

        if ($err !== '') {
            $this->setRedirect($back, 'Full recalc failed: ' . $err, 'error');
            return;
        }

        $scored = '?'; $written = '?'; $excluded = '?';
        if (is_string($log)) {
            if (preg_match('/scoring\s+(\d+)\s+casino/i', $log, $m))            { $scored   = $m[1]; }
            if (preg_match('/snapshots written:\s*(\d+)\s*\((\d+) excluded\)/i', $log, $m)) { $written = $m[1]; $excluded = $m[2]; }
            $warns = preg_match_all('/WARN:/', $log, $mm);
        } else {
            $warns = 0;
        }
        $msg = 'Full recalc done - ' . $scored . ' casino(s) scored, ' . $written
             . ' snapshot(s) written (' . $excluded . ' excluded).';
        if ($warns > 0) {
            $msg .= ' ' . (int) $warns . ' engine warning(s) - check the next cron log.';
        }
        $this->setRedirect($back, $msg, 'message');
    }

    public function recalc()
    {
        if (!AuthorsModelDashboard::isAuthorized()) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin', 'Forbidden', 'error');
            return;
        }
        if (!JRequest::checkToken('get')) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin',
                'Invalid or expired link token. Reload the page and retry.', 'error');
            return;
        }

        $casinoId = (int) JRequest::getVar('casino_id', 0, 'get', 'int');
        $ret      = JRequest::getVar('return', '', 'get', 'cmd');
        $back     = ($ret === 'deskadd' && $casinoId > 0)
                  ? 'index.php?option=com_authors&view=deskadd&casino_id=' . $casinoId
                  : 'index.php?option=com_authors&view=fxshieldadmin';

        if ($casinoId <= 0) {
            $this->setRedirect($back, 'No casino specified.', 'error');
            return;
        }

        $params = JComponentHelper::getParams('com_authors');
        $engine = trim((string) $params->get('safety_engine_path', ''));
        if ($engine === '') {
            $engine = FXA_SAFETY_ENGINE_DEFAULT;
        }
        if (!is_readable($engine)) {
            $this->setRedirect($back, 'Safety engine not readable at: ' . $engine, 'error');
            return;
        }

        if (!defined('FXSAFETY_WEB_ENTRY')) {
            define('FXSAFETY_WEB_ENTRY', 1);
        }
        $GLOBALS['FXSAFETY_WEB_ARGS'] = array('casino' => (string) $casinoId);

        @set_time_limit(300);
        $err = '';
        ob_start();
        try {
            include $engine;
        } catch (Exception $e) {
            $err = $e->getMessage();
        }
        $log = ob_get_clean();

        if ($err !== '') {
            $this->setRedirect($back, 'Recalc failed: ' . $err, 'error');
            return;
        }

        // Read back the fresh snapshot for a meaningful confirmation.
        $msg = 'Engine run completed.';
        try {
            $db = JFactory::getDbo();
            $db->setQuery('SELECT si_score, si_tier, si_confidence FROM #__fec_safety_scores'
                . ' WHERE casino_id = ' . $casinoId, 0, 1);
            $row = $db->loadAssoc();
            if (is_array($row) && $row['si_score'] !== null && $row['si_score'] !== '') {
                $msg = 'Recalc done - #' . $casinoId . ' score ' . (int) $row['si_score']
                     . ' (' . $row['si_tier'] . ', conf. ' . $row['si_confidence'] . ').';
            } elseif (is_array($row)) {
                // Snapshot exists with a NULL score: the engine wrote an Excluded result.
                $msg = 'Engine ran - #' . $casinoId . ' is EXCLUDED by the engine: one of the'
                     . ' jr_closed / jr_blacklisted / jr_suspended flags on its jReviews listing'
                     . ' is set. Clear the flag on the listing, then Recalc again.';
            } else {
                // No row at all: the id is outside the scoring universe.
                $msg = 'Engine ran, but #' . $casinoId . ' is outside the scoring universe:'
                     . ' no desk baseline, event or prior score is stored for this id.'
                     . ' Save a desk baseline first, then Recalc.';
            }
        } catch (Exception $e) {
            // keep the generic message
        }
        if (is_string($log) && strpos($log, 'WARN:') !== false) {
            $msg .= ' Engine reported warnings - check the next cron log.';
        }
        $this->setRedirect($back, $msg, 'message');
    }

    public function exportmd()
    {
        if (!AuthorsModelDashboard::isAuthorized()) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin', 'Forbidden', 'error');
            return;
        }

        $casinoId = (int) JRequest::getVar('casino_id', 0, 'get', 'int');
        if ($casinoId <= 0) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin', 'No casino specified.', 'error');
            return;
        }

        $data = AuthorsModelVerification::factSheetData($casinoId);
        if ($data === null) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin',
                'That casino has no FXShield score to export.', 'error');
            return;
        }

        $md   = $this->buildMarkdown($data);
        $slug = $this->slugify($data['title']);
        $fname = 'fxshield-' . $slug . '-' . $casinoId . '.md';

        // Stream as a download, bypassing the Joomla template. Use native
        // header() so this works regardless of the Joomla version's app API.
        while (ob_get_level() > (defined('FEC_OB_BASE') ? FEC_OB_BASE : 0)) { ob_end_clean(); } // PORT: keep the runner's buffer
        fec_header('Content-Type: text/markdown; charset=utf-8');
        fec_header('Content-Disposition: attachment; filename="' . $fname . '"');
        fec_header('Content-Length: ' . strlen($md));
        fec_header('Cache-Control: no-store, no-cache, must-revalidate');
        fec_header('Pragma: no-cache');
        echo $md;
        jexit();
    }

    /**
     * exportdossier — a richer, INTERNAL dossier (not the synthetic fact-sheet).
     * Baseline field-by-field, events with full context (source_type, note,
     * status), and interpretive notes for writing an accurate review.
     */
    public function exportdossier()
    {
        if (!AuthorsModelDashboard::isAuthorized()) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin', 'Forbidden', 'error');
            return;
        }
        $casinoId = (int) JRequest::getVar('casino_id', 0, 'get', 'int');
        if ($casinoId <= 0) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin', 'No casino specified.', 'error');
            return;
        }
        $data = AuthorsModelVerification::factSheetData($casinoId);
        if ($data === null) {
            $this->setRedirect('index.php?option=com_authors&view=fxshieldadmin',
                'That casino has no FXShield record to export.', 'error');
            return;
        }

        $md   = $this->buildDossier($data);
        $slug = $this->slugify($data['title']);
        $fname = 'fxshield-dossier-' . $slug . '-' . $casinoId . '.md';

        while (ob_get_level() > (defined('FEC_OB_BASE') ? FEC_OB_BASE : 0)) { ob_end_clean(); } // PORT: keep the runner's buffer
        fec_header('Content-Type: text/markdown; charset=utf-8');
        fec_header('Content-Disposition: attachment; filename="' . $fname . '"');
        fec_header('Content-Length: ' . strlen($md));
        fec_header('Cache-Control: no-store, no-cache, must-revalidate');
        fec_header('Pragma: no-cache');
        echo $md;
        jexit();
    }

    /** Build the synthetic Markdown fact-sheet from assembled data. */
    protected function buildMarkdown(array $d)
    {
        $score    = $d['score'];
        $baseline = $d['baseline'];
        $events   = $d['events'];
        $payout   = $d['payout'];
        $addl     = $d['addl_lic'];

        $si    = ($score['si_score'] !== null) ? (int)$score['si_score'] : null;
        $tier  = (string)$score['si_tier'];
        $conf  = strtoupper((string)$score['si_confidence']);
        $caps  = (string)$score['si_caps_applied'];
        $prg   = ($score['prg_ratio'] !== null && $score['prg_ratio'] !== '')
               ? (float)$score['prg_ratio'] : null;

        // Principal licence / tier from baseline
        $licField = isset($baseline['license_tier']) ? $baseline['license_tier'] : null;
        $tierCode = $licField ? (string)$licField['field_value'] : '';
        $verifiedCount = 0; $totalFields = count($baseline);
        foreach ($baseline as $bf) { if ((int)$bf['verified_first_party'] === 1) $verifiedCount++; }
        $isFirstParty = ($verifiedCount > 0);

        // PRG line
        $prgLine = 'not tested';
        if ($prg !== null) {
            $prgTxt = rtrim(rtrim(number_format($prg, 1), '0'), '.');
            if ($payout && $payout['declared_hours'] !== null && $payout['measured_hours'] !== null) {
                $decl = rtrim(rtrim(number_format((float)$payout['declared_hours'], 1), '0'), '.');
                $meas = rtrim(rtrim(number_format((float)$payout['measured_hours'], 1), '0'), '.');
                $prgLine = $prgTxt . '× — declares ' . $decl . 'h, measured ' . $meas . 'h';
            } else {
                $prgLine = $prgTxt . '×';
            }
        }

        $hardcap = ($caps !== '') ? $caps : 'none';

        // Auto editorial note
        $noteBits = array();
        if ($caps !== '') $noteBits[] = 'licence hard cap in play (' . $caps . ')';
        if ($prg !== null && $prg >= 2.0) $noteBits[] = 'pays materially slower than declared';
        if ($prg !== null && $prg < 1.5)  $noteBits[] = 'pays as promised';
        $negEvents = 0; foreach ($events as $e) { if ((float)$e['base_weight'] < 0) $negEvents++; }
        if ($negEvents > 0) $noteBits[] = $negEvents . ' negative ledger event' . ($negEvents > 1 ? 's' : '');
        if (count($addl) > 0) $noteBits[] = (count($addl) + 1) . ' licences total';
        $autoNote = $noteBits ? implode('; ', $noteBits) : '';

        $nl = "\n";
        $m  = '# FXShield fact-sheet — ' . $d['title'] . ' (#' . $d['casino_id'] . ')' . $nl . $nl;
        $m .= '> Generated from the live FXShield database. Paste the `fxshield_*` block into the review generator. ';
        $m .= $isFirstParty
            ? 'This casino is FIRST-PARTY VERIFIED (' . $verifiedCount . '/' . $totalFields . ' baseline fields), confidence ' . $conf . '.'
            : 'WARNING: NOT first-party verified (desk only) — treat the score as provisional.';
        $m .= $nl . $nl;

        // The six fields block
        $m .= '## Fields for the review generator' . $nl . $nl;
        $m .= '```' . $nl;
        $m .= 'fxshield_score: ' . ($si !== null ? $si : 'n/a') . $nl;
        $m .= 'fxshield_tier: ' . ($tier !== '' ? $tier : 'n/a') . $nl;
        $m .= 'fxshield_confidence: ' . ($conf !== '' ? $conf : 'n/a') . $nl;
        $m .= 'fxshield_hardcap: ' . $hardcap . $nl;
        $m .= 'fxshield_prg: ' . $prgLine . $nl;
        $m .= 'fxshield_note: ' . $autoNote . $nl;
        $m .= '```' . $nl . $nl;

        // Context
        $m .= '## Context (for Banking / Editor Notes)' . $nl . $nl;
        $m .= '- Score breakdown: baseline ' . $this->num($score['si_baseline'])
            . ', events ' . $this->num($score['si_events'])
            . ($caps !== '' ? ', cap ' . $caps : '')
            . ' = **' . ($si !== null ? $si : 'n/a') . ' ' . $tier . '** (confidence ' . $conf . ')' . $nl;
        $m .= '- Principal licence tier: ' . ($tierCode !== '' ? $tierCode : 'not recorded') . $nl;
        $m .= '- Baseline verified first-party: ' . $verifiedCount . ' / ' . $totalFields . ' fields' . $nl;
        if (isset($d['frank']) && $d['frank'] !== null) {
            $m .= '- FRank score (product quality, 0-10): ' . rtrim(rtrim(number_format((float)$d['frank'], 1), '0'), '.')
                . (isset($d['frank_rank']) && $d['frank_rank'] ? ' (rank #' . (int)$d['frank_rank'] . ')' : '') . $nl;
        }
        if ($score['si_delta_30d'] !== null && $score['si_delta_30d'] !== '') {
            $m .= '- 30-day change: ' . $this->num($score['si_delta_30d']) . $nl;
        }
        $m .= '- Public evidence files: ' . (int)$d['evidence_n'] . $nl;
        if ($score['computed_at']) $m .= '- Last computed: ' . $score['computed_at'] . $nl;
        $m .= $nl;

        // Additional licences
        if (!empty($addl)) {
            $m .= '### Additional licences' . $nl;
            foreach ($addl as $L) {
                $m .= '- ' . $L['label'] . ($L['tier'] !== '' ? ' (' . $L['tier'] . ')' : '')
                    . ($L['number'] !== '' ? ' — ' . $L['number'] : '') . $nl;
            }
            $m .= $nl;
        }

        // Ledger events (exclude system/technical annotations like SYSTEM_RECALC)
        $systemCodes = array('SYSTEM_RECALC');
        $ledgerEvents = array();
        foreach ($events as $e) {
            if (in_array($e['event_code'], $systemCodes, true)) continue;
            $ledgerEvents[] = $e;
        }
        if (!empty($ledgerEvents)) {
            $m .= '### Ledger events (active)' . $nl;
            foreach ($ledgerEvents as $e) {
                $w = $this->num($e['base_weight']);
                $m .= '- `' . $e['event_code'] . '` ' . $e['event_date'] . ' (' . $w . ', ' . $e['decay_class'] . ')';
                if ($e['evidence_note'] !== null && $e['evidence_note'] !== '') {
                    $m .= ' — ' . $e['evidence_note'];
                }
                $m .= $nl;
            }
            $m .= $nl;
        }

        // Payout test
        if ($payout) {
            $m .= '### Latest payout test' . $nl;
            $m .= '- Date: ' . $payout['test_date'] . ' (' . $payout['source'] . ')' . $nl;
            if ($payout['declared_hours'] !== null) $m .= '- Declared: ' . $this->num($payout['declared_hours']) . 'h' . $nl;
            if ($payout['measured_hours'] !== null) $m .= '- Measured: ' . $this->num($payout['measured_hours']) . 'h' . $nl;
            $m .= $nl;
        }

        $m .= '---' . $nl;
        $m .= '_FXShield SI v1.1. Score, tier, confidence and PRG are computed by the engine; do not edit by hand._' . $nl;

        return $m;
    }

    /**
     * Build the detailed INTERNAL dossier. Everything buildMarkdown has, plus:
     * baseline field-by-field with points, events with full context, and
     * interpretive prompts for writing an accurate review. For staff use.
     */
    protected function buildDossier(array $d)
    {
        $score    = $d['score'];
        $baseline = $d['baseline'];
        $events   = $d['events'];
        $payout   = $d['payout'];
        $addl     = $d['addl_lic'];
        $nl = "\n";

        $si    = ($score['si_score'] !== null) ? (int)$score['si_score'] : null;
        $tier  = (string)$score['si_tier'];
        $conf  = strtoupper((string)$score['si_confidence']);
        $caps  = (string)$score['si_caps_applied'];
        $prg   = ($score['prg_ratio'] !== null && $score['prg_ratio'] !== '') ? (float)$score['prg_ratio'] : null;

        // First-party status
        $fp = 0; $tot = 0;
        foreach ($baseline as $b) { $tot++; if ((int)$b['verified_first_party'] === 1) $fp++; }
        $isFP = ($fp > 0);

        // Readable baseline field labels + max points
        $fieldMeta = array(
            'license_tier'      => array('Licence tier', 32),
            'adr'               => array('ADR (alternative dispute resolution)', 8),
            'entity_registered' => array('Operator entity registered', 4),
            'license_footer'    => array('Licence number in footer, consistent', 4),
            'no_fragmentation'  => array('No brand fragmentation', 4),
            'rg_2fa'            => array('2FA available', 4),
            'rg_limits'         => array('Self-service deposit/loss limits', 6),
            'rg_selfexclusion'  => array('Self-service self-exclusion', 6),
            'tc_fairness'       => array('T&C fairness (32 minus 4/clause)', 32),
        );
        $evLabels = array(
            'ST_PAYOUT_FAST' => 'Fast staff payout test', 'ST_PAYOUT_OK' => 'Staff payout test (on time)',
            'ST_PAYOUT_SLOW' => 'Slow staff payout test', 'ST_KYC_OBSTRUCTION' => 'KYC obstruction',
            'WD_WARNING' => 'Regulator warning / sanction', 'WD_BLACKLIST' => 'Watchdog blacklisting',
            'WD_UNFAIR_TC' => 'Unfair T&C ruling', 'WD_DELISTED' => 'Regulator delisting',
            'LIC_LAPSED' => 'Licence lapsed', 'LIC_UPGRADED' => 'Licence upgraded',
            'GRP_CONTAGION' => 'Group contagion (sister brand)', 'COMPLAINT_PATTERN' => 'Complaint pattern',
        );
        $sourceLabels = array(
            'staff_test' => 'first-party staff test', 'watchdog' => 'external watchdog / complaints',
            'regulator' => 'regulator action', 'editorial' => 'editorial desk', 'system' => 'engine-generated',
        );

        $m  = '# FXShield INTERNAL DOSSIER — ' . $d['title'] . ' (#' . $d['casino_id'] . ')' . $nl . $nl;
        $m .= '> Internal working document for editorial use. NOT for publication. Use it to write an accurate, '
            . 'fair review that reflects exactly what FXShield found and why. ';
        $m .= $isFP
            ? 'Status: FIRST-PARTY VERIFIED (' . $fp . '/' . $tot . ' fields), confidence ' . $conf . '.'
            : 'Status: DESK-ONLY (not first-party verified) — treat as provisional.';
        $m .= $nl . $nl;

        // 1. Headline
        $m .= '## 1. Headline' . $nl . $nl;
        $m .= '| | |' . $nl . '|---|---|' . $nl;
        $m .= '| Safety Index | **' . ($si !== null ? $si : 'n/a') . ' / 100** |' . $nl;
        $m .= '| Tier | ' . ($tier !== '' ? $tier : 'n/a') . ' |' . $nl;
        $m .= '| Confidence | ' . $conf . ' |' . $nl;
        $m .= '| Licence cap | ' . ($caps !== '' ? $caps : 'none') . ' |' . $nl;
        $m .= '| PRG (payout reality gap) | ' . ($prg !== null ? (rtrim(rtrim(number_format($prg,1),'0'),'.') . '×') : 'not tested') . ' |' . $nl;
        if ($payout && $payout['declared_hours'] !== null && $payout['measured_hours'] !== null) {
            $m .= '| Declared / measured | ' . $this->num($payout['declared_hours']) . 'h declared, '
                . $this->num($payout['measured_hours']) . 'h measured |' . $nl;
        }
        $m .= '| Score breakdown | baseline ' . $this->num($score['si_baseline'])
            . ' + events ' . $this->num($score['si_events'])
            . ($caps !== '' ? ' (cap ' . $caps . ')' : '') . ' |' . $nl;
        if ($score['computed_at']) $m .= '| Last computed | ' . $score['computed_at'] . ' |' . $nl;
        $m .= $nl;

        // 2. Baseline field by field
        $m .= '## 2. Baseline — field by field' . $nl . $nl;
        $m .= 'Where the structural score comes from. A field at 0 was either absent or not assessed '
            . '(never assumed). `verified` = checked first-party this/last session; `desk` = estimate.' . $nl . $nl;
        $m .= '| Field | Value | Points | Source | Verified date |' . $nl;
        $m .= '|---|---|---|---|---|' . $nl;
        foreach ($fieldMeta as $code => $meta) {
            if (!isset($baseline[$code])) {
                $m .= '| ' . $meta[0] . ' | _missing_ | 0 / ' . $meta[1] . ' | — | — |' . $nl;
                continue;
            }
            $b = $baseline[$code];
            $val = ($b['field_value'] !== null && $b['field_value'] !== '') ? $b['field_value'] : '—';
            $pts = isset($b['points']) ? $this->num($b['points']) : '?';
            $src = ((int)$b['verified_first_party'] === 1) ? 'verified' : 'desk';
            $vat = (!empty($b['verified_at']) && strpos((string)$b['verified_at'],'0000-00-00') !== 0)
                 ? substr((string)$b['verified_at'], 0, 10) : '—';
            $m .= '| ' . $meta[0] . ' | ' . $val . ' | ' . $pts . ' / ' . $meta[1] . ' | ' . $src . ' | ' . $vat . ' |' . $nl;
        }
        $m .= $nl;

        // Additional licences
        if (!empty($addl)) {
            $m .= '### Additional licences (exhibition only — principal licence drives tier)' . $nl;
            foreach ($addl as $L) {
                $m .= '- ' . $L['label'] . ($L['tier'] !== '' ? ' (' . $L['tier'] . ')' : '')
                    . ($L['number'] !== '' ? ' — ' . $L['number'] : '') . $nl;
            }
            $m .= $nl;
        }

        // 3. Events with full context
        $systemCodes = array('SYSTEM_RECALC');
        $realEvents = array();
        foreach ($events as $e) { if (!in_array($e['event_code'], $systemCodes, true)) $realEvents[] = $e; }
        $m .= '## 3. Ledger events — full context' . $nl . $nl;
        if (empty($realEvents)) {
            $m .= '_No substantive events recorded._' . $nl . $nl;
        } else {
            $m .= 'Each event, with where it came from (`source`) and the editorial note. '
                . 'CRITICAL for writing: a staff-test event is your own observation; a watchdog event is a '
                . 'documented external pattern you may not have personally hit.' . $nl . $nl;
            foreach ($realEvents as $e) {
                $lbl = isset($evLabels[$e['event_code']]) ? $evLabels[$e['event_code']] : $e['event_code'];
                $w = (float)$e['base_weight'];
                $wTxt = ($w > 0 ? '+' : '') . $this->num($w);
                $src = isset($sourceLabels[$e['source_type']]) ? $sourceLabels[$e['source_type']] : $e['source_type'];
                $m .= '### ' . $lbl . ' (`' . $e['event_code'] . '`) — ' . $wTxt . $nl;
                $m .= '- Date: ' . $e['event_date'] . $nl;
                $m .= '- Source: **' . $src . '**' . $nl;
                $m .= '- Decay: ' . $e['decay_class'] . ' · Status: ' . $e['status'] . $nl;
                if ($e['evidence_note'] !== null && $e['evidence_note'] !== '') {
                    $m .= '- Note: ' . $e['evidence_note'] . $nl;
                }
                $m .= $nl;
            }
        }

        // 4. Payout detail
        if ($payout) {
            $m .= '## 4. Payout test' . $nl . $nl;
            $m .= '- Date: ' . $payout['test_date'] . ' (source: ' . $payout['source'] . ')' . $nl;
            if ($payout['declared_hours'] !== null) $m .= '- Declared: ' . $this->num($payout['declared_hours']) . 'h' . $nl;
            if ($payout['measured_hours'] !== null) $m .= '- Measured: ' . $this->num($payout['measured_hours']) . 'h' . $nl;
            if ($prg !== null) {
                $verdict = ($prg <= 1.05) ? 'pays as fast or faster than declared (good)' : 'pays slower than declared';
                $m .= '- PRG ' . rtrim(rtrim(number_format($prg,1),'0'),'.') . 'x — ' . $verdict . $nl;
                $m .= '  - Note: PRG measures time from withdrawal *approval* to funds arriving. It does NOT '
                    . 'capture pre-approval friction (KYC delays) — check the events for that.' . $nl;
            }
            $m .= $nl;
        }

        // 5. Interpretive notes for the review
        $m .= '## 5. Interpretive notes (for writing the review)' . $nl . $nl;
        $m .= '### FRank vs FXShield' . $nl;
        $frank = isset($d['frank']) ? $d['frank'] : null;
        $frankRank = isset($d['frank_rank']) ? $d['frank_rank'] : null;
        if ($frank !== null) {
            $frankTxt = rtrim(rtrim(number_format((float)$frank, 1), '0'), '.');
            $m .= '- FRank score (how GOOD the product is): **' . $frankTxt . ' / 10**'
                . ($frankRank ? ' (directory rank #' . $frankRank . ')' : '') . $nl;
        } else {
            $m .= '- FRank score (how GOOD the product is): `[DA CONFERMARE: non trovato in tabella frank]`' . $nl;
        }
        $m .= '- FXShield (how SAFE): ' . ($si !== null ? $si : 'n/a') . '/100 ' . $tier . $nl;
        // Automatic divergence flag: compare on a common 0-10 scale.
        if ($frank !== null && $si !== null) {
            $fxOn10 = $si / 10.0;
            $gap = $frank - $fxOn10;
            $absGap = abs($gap);
            if ($absGap >= 2.0) {
                $dir = ($gap > 0)
                    ? 'Good product, weaker safety — FRank rates it well above its FXShield safety standing.'
                    : 'Safe but unremarkable product — FXShield safety sits well above the FRank product score.';
                $m .= '- **DIVERGENCE FLAG (' . rtrim(rtrim(number_format($absGap, 1), '0'), '.')
                    . ' pts on a 0-10 scale): ' . $dir . '** This contrast is the headline angle — lead with it.' . $nl;
            } else {
                $m .= '- FRank and FXShield broadly agree (gap ' . rtrim(rtrim(number_format($absGap, 1), '0'), '.')
                    . ' pts on 0-10) — no sharp divergence to flag.' . $nl;
            }
        }
        $m .= '- If the two diverge sharply (great product, mediocre safety, or vice versa), that contrast is '
            . 'the most interesting thing to write about. State both, explain why they differ.' . $nl . $nl;

        $m .= '### Why this tier / cap' . $nl;
        $footer = isset($d['footer']) ? $d['footer'] : array();
        $fEntity = !empty($footer['entity']) ? $footer['entity'] : '';
        $fReg    = !empty($footer['entity_reg']) ? $footer['entity_reg'] : '';
        $fLic    = !empty($footer['licence_number']) ? $footer['licence_number'] : '';
        $fRegName= !empty($footer['regulator']) ? $footer['regulator'] : '';
        if ($fEntity !== '' || $fLic !== '' || $fRegName !== '') {
            $m .= '- Footer / licence as verified: '
                . ($fEntity !== '' ? 'entity **' . $fEntity . '**' : 'entity not recorded')
                . ($fReg !== '' ? ' (reg ' . $fReg . ')' : '')
                . ($fRegName !== '' ? ', regulator ' . $fRegName : '')
                . ($fLic !== '' ? ', licence no. ' . $fLic : '')
                . '.' . $nl;
        }
        if ($caps !== '') {
            $m .= '- A hard cap is in play (' . $caps . '). The score cannot exceed it regardless of everything '
                . 'else. This is the single most decision-relevant safety fact — explain it derives from the '
                . 'licence/ruling, not the casino\'s day-to-day conduct.' . $nl;
        } else {
            $m .= '- No hard cap. The licence tier is good enough not to cap the score';
            $m .= ($fEntity !== '' || $fLic !== '') ? ' (see footer/licence above).' : '. `[DA CONFERMARE: quale licenza/numero hai visto nel footer?]`';
            $m .= $nl;
        }
        $m .= $nl;

        $m .= '### Payout honesty' . $nl;
        if ($prg !== null) {
            if ($prg > 2.0) {
                $m .= '- Pays MUCH slower than it claims (PRG ' . rtrim(rtrim(number_format($prg,1),'0'),'.')
                    . 'x). This is a headline negative — name it plainly in Banking and Editor Notes.' . $nl;
            } elseif ($prg <= 1.05) {
                $m .= '- Pays as fast or faster than declared (PRG ' . rtrim(rtrim(number_format($prg,1),'0'),'.')
                    . 'x) — a genuine positive. But check events: fast normal payouts can coexist with KYC '
                    . 'friction on big wins.' . $nl;
            } else {
                $m .= '- Pays somewhat slower than declared (PRG ' . rtrim(rtrim(number_format($prg,1),'0'),'.')
                    . 'x) — mention as mild friction.' . $nl;
            }
        } else {
            $m .= '- No payout test on record. `[DA CONFERMARE: è stato fatto un test di prelievo?]`' . $nl;
        }
        $m .= $nl;

        $m .= '### Clauses & friction to mention' . $nl;
        $clauses = isset($d['clauses']) && is_array($d['clauses']) ? $d['clauses'] : array();
        if (!empty($clauses)) {
            $m .= '- Predatory T&C clauses flagged in the verification (' . count($clauses) . '):' . $nl;
            foreach ($clauses as $cl) {
                $m .= '  - ' . $cl . $nl;
            }
            $m .= '  - Use these by name in Editor Notes — they are the concrete basis of the tc_fairness deduction.' . $nl;
        } else {
            $m .= '- No predatory T&C clauses were flagged in the verification (tc_fairness at or near full). '
                . 'If the T&C looked clean, say so — a clean contract is a genuine positive worth stating.' . $nl;
        }
        $m .= '- `[DA CONFERMARE: attriti osservati nel test non già catturati come eventi, se presenti]`' . $nl;
        $m .= $nl;

        $m .= '### Aggregator divergence' . $nl;
        $m .= '- `[DA CONFERMARE: cosa dicono gli aggregatori (es. Casino.Guru)? Se danno un voto molto più '
            . 'alto del FXShield, spiega PERCHÉ divergono — è il valore del tuo sistema]`' . $nl;
        $m .= $nl;

        $m .= '---' . $nl;
        $m .= '_Internal dossier. FXShield SI v1.1. Fill the [DA CONFERMARE] fields from your verification notes._' . $nl;

        return $m;
    }

    /** Trim trailing zeros from a numeric string. */
    protected function num($v)
    {
        if ($v === null || $v === '') return 'n/a';
        return rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
    }

    /** URL/file-safe slug from a title. */
    protected function slugify($s)
    {
        $s = strtolower((string)$s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        return $s !== '' ? $s : 'casino';
    }
}
