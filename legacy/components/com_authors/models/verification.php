<?php
/**
 * AuthorsModelVerification — FXShield first-party verification workflow (Phase B).
 *
 * B1 layer: persistence for verification sessions in #__fec_verifications.
 * Pure draft storage — create, load, save-progress, list. No write-through to
 * the engine tables yet; that is B3 (commit). Keeping the storage layer
 * isolated and testable on its own is deliberate: the wizard (B2) talks only
 * to these methods, and B3 will read a completed draft and translate it.
 *
 * The field payload travels as a PHP array in/out of here; this class owns the
 * JSON (de)serialisation so callers never touch raw JSON. All queries use
 * prepared statements / quoted values (frupdater lesson).
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class AuthorsModelVerification extends JModel
{
    /** Canonical field keys the wizard collects. The commit step (B3) maps
     *  these to baseline rows / events / payout_tests. Kept here as the single
     *  source of truth so wizard and commit can't drift apart. */
    public static function fieldSchema()
    {
        return array(
            // Step 1 — footer & licence
            'footer_entity'        => '',      // exact operating entity in footer
            'entity_reg_number'    => '',      // company registration number, '' if none
            'licence_number'       => '',
            'regulator'            => '',
            'regulator_key'        => '',      // key into regulatorTierMap (drives tier auto-select)
            'licence_verified'     => '',      // 'valid' | 'mismatch' | 'not_found'
            'footer_consistent'    => null,    // bool: footer matches T&C
            'license_tier'         => '',      // T1..T5 (editor's verified determination)
            'ev_footer'            => '',      // evidence url/note
            // Additional licences (exhibition only — do NOT affect tier/score).
            // List of array('regulator_key'=>..., 'number'=>...). The principal
            // licence above drives the tier; these enrich the public panel for
            // multi-licence operators (e.g. Videoslots, BitStarz).
            'additional_licences'  => array(),
            // Step 2 — RG tools (self-service?)
            'rg_2fa'               => null,    // bool
            'rg_limits'            => null,    // bool
            'rg_selfexclusion'     => null,    // bool
            'ev_rg'                => '',
            // Step 3 — ADR
            'adr_named'            => null,    // bool: independent ADR named in T&C
            'adr_name'             => '',
            'adr_reachable'        => null,    // bool: tested reachable
            'ev_adr'               => '',
            // Step 4 — T&C predatory clauses (8-item checklist; true = present)
            'tc_dormant'           => false,
            'tc_cashout_cap'       => false,
            'tc_irregular'         => false,
            'tc_maxbet_trap'       => false,
            'tc_monthly_cap'       => false,
            'tc_unilateral'        => false,
            'tc_math_advantage'    => false,
            'tc_kyc_repeat'        => false,
            'ev_tc'                => '',
            // Step 5 — test withdrawal
            'deposit_amount'       => '',
            'deposit_method'       => '',
            'withdraw_amount'      => '',
            'withdraw_method'      => '',
            'declared_hours'       => '',      // worst end of operator's stated range
            'requested_at'         => '',      // datetime-local
            'credited_at'          => '',      // datetime-local
            'kyc_required'         => null,    // bool
            'kyc_cycles'           => '',
            'kyc_hours'            => '',
            'payout_outcome'       => '',      // 'paid_ok' | 'paid_late' | 'not_paid'
            'ev_payout'            => '',
            // free notes
            'notes'                => '',
            // Control key: list of field names pre-filled from a previous
            // verification and not yet reconfirmed this session. Drops a field
            // as the editor saves the step containing it. Not a data field.
            '_inherited'           => array(),
        );
    }

    /** Number of wizard steps (1-indexed in the UI; step 0 = not started). */
    const TOTAL_STEPS = 5;

    /**
     * Canonical regulator -> licence tier map (FXShield spec §3). The wizard
     * uses this to auto-select the tier when an editor picks a regulator, and
     * the preview/commit read the editor's final tier value. Order = display
     * order in the dropdown, grouped by tier.
     *
     * IMPORTANT: the tier a regulator implies is the CEILING for that licence
     * type. Verification can lower it — e.g. a Curacao GCB licence whose number
     * is NOT found in the registry drops from T3 toward T4/T5. The wizard warns
     * when 'licence_verified' = not_found contradicts the regulator's tier.
     */
    public static function regulatorTierMap()
    {
        return array(
            // value => array(label, tier)
            'ADM'    => array('ADM / AAMS (Italy)',          'T1'),
            'UKGC'   => array('UK Gambling Commission',      'T1'),
            'MGA'    => array('Malta (MGA)',                 'T1'),
            'SGA'    => array('Spelinspektionen (Sweden)',   'T1'),
            'DGOJ'   => array('DGOJ (Spain)',                'T1'),
            'ANJ'    => array('ANJ (France)',                'T1'),
            'KSA'    => array('Kansspelautoriteit (NL)',     'T1'),
            'AGCO'   => array('AGCO / iGaming Ontario',      'T1'),
            'DGA'    => array('Spillemyndigheden (Denmark)', 'T1'),
            'GIB'    => array('Gibraltar',                   'T2'),
            'IOM'    => array('Isle of Man (GSC)',           'T2'),
            'ALD'    => array('Alderney (AGCC)',             'T2'),
            'CUR_GCB'=> array('Curacao GCB (new regime)',    'T3'),
            'KAH'    => array('Kahnawake (KGC)',             'T3'),
            'CUR_LEG'=> array('Curacao legacy sub-licence',  'T4'),
            'ANJOUAN'=> array('Anjouan (Comoros)',           'T4'),
            'TOBIQUE'=> array('Tobique Gaming Commission (TGC)', 'T4'),
            'OTHER_UNVERIF' => array('Other / not verifiable in a registry', 'T4'),
            'NONE'   => array('No gambling licence (corporate reg only)', 'T5'),
        );
    }

    /**
     * Assemble the full FXShield fact-sheet data for one casino, for MD export.
     * Pulls score, baseline (incl. principal licence + tier), events, latest
     * payout test, additional licences, evidence count. Returns an assoc array
     * or null if the casino has no FXShield footprint. Read-only.
     */
    public static function factSheetData($casinoId)
    {
        $casinoId = (int)$casinoId;
        if ($casinoId <= 0) return null;
        $db = JFactory::getDbo();

        // Casino title (jReviews listing)
        $title = '';
        try {
            $db->setQuery("SELECT title FROM #__jreviews_content WHERE contentid = " . $casinoId, 0, 1);
            $title = (string)$db->loadResult();
        } catch (Exception $e) {}
        if ($title === '') {
            try {
                $db->setQuery("SELECT title FROM #__content WHERE id = " . $casinoId, 0, 1);
                $title = (string)$db->loadResult();
            } catch (Exception $e) {}
        }

        // Score snapshot
        $score = null;
        try {
            $db->setQuery(
                "SELECT si_score, si_tier, si_confidence, si_baseline, si_events,
                        si_caps_applied, prg_ratio, si_delta_30d, computed_at
                   FROM #__fec_safety_scores WHERE casino_id = " . $casinoId, 0, 1
            );
            $score = $db->loadAssoc();
        } catch (Exception $e) {}
        if (!$score) return null; // no FXShield footprint

        // Baseline fields (principal licence + tier live here)
        $baseline = array();
        try {
            $db->setQuery(
                "SELECT field_code, field_value, points, verified_first_party, verified_at, evidence_url
                   FROM #__fec_safety_baseline WHERE casino_id = " . $casinoId
            );
            $rows = $db->loadAssocList();
            if (is_array($rows)) {
                foreach ($rows as $r) { $baseline[$r['field_code']] = $r; }
            }
        } catch (Exception $e) {}

        // Active events (the ledger)
        $events = array();
        try {
            $db->setQuery(
                "SELECT event_code, event_date, base_weight, decay_class, source_type,
                        evidence_note, status
                   FROM #__fec_safety_events
                  WHERE casino_id = " . $casinoId . " AND status = 'active'
                  ORDER BY event_date DESC, id DESC"
            );
            $rows = $db->loadAssocList();
            if (is_array($rows)) $events = $rows;
        } catch (Exception $e) {}

        // Latest payout test
        $payout = null;
        try {
            $db->setQuery(
                "SELECT test_date, declared_hours, measured_hours, source
                   FROM #__fec_payout_tests WHERE casino_id = " . $casinoId . "
                  ORDER BY test_date DESC, id DESC", 0, 1
            );
            $payout = $db->loadAssoc();
        } catch (Exception $e) {}

        // Additional licences + public evidence count (reuse existing helper)
        $addlLic = self::additionalLicencesForCasino($casinoId);

        $evCount = 0;
        try {
            $db->setQuery(
                "SELECT COUNT(*) FROM #__fec_verification_evidence e
                   INNER JOIN #__fec_verifications v ON v.id = e.verification_id
                  WHERE e.casino_id = " . $casinoId . "
                    AND e.visibility = 'public' AND v.status = 'committed'"
            );
            $evCount = (int)$db->loadResult();
        } catch (Exception $e) {}

        // FRank score (the "how good" engine, 0-10) — separate engine, table
        // #__frank keyed by content id. frankvalue is the published 0-10 score;
        // rank is the directory position. Defensive: null if table absent.
        $frank = null; $frankRank = null;
        try {
            $db->setQuery(
                "SELECT frankvalue, rank FROM #__frank WHERE id = " . $casinoId, 0, 1
            );
            $fr = $db->loadAssoc();
            if ($fr) {
                $frank     = ($fr['frankvalue'] !== null) ? round((float)$fr['frankvalue'], 1) : null;
                $frankRank = ($fr['rank'] !== null) ? (int)$fr['rank'] : null;
            }
        } catch (Exception $e) {}

        // Qualitative detail from the committed verification JSON: the engine
        // tables store the licence TIER and a clause COUNT, but not which entity
        // was in the footer nor which specific clauses were flagged. Those live
        // in the verification payload. Pull them so the dossier shows the names,
        // not just the numbers. Defensive: empty if no committed verification.
        $footer = array(
            'entity' => '', 'entity_reg' => '', 'licence_number' => '', 'regulator' => '',
        );
        $clauses = array();
        try {
            $db->setQuery(
                "SELECT data FROM #__fec_verifications
                  WHERE casino_id = " . $casinoId . " AND status = 'committed'
                  ORDER BY committed_at DESC, id DESC", 0, 1
            );
            $vjson = $db->loadResult();
            if ($vjson) {
                $vd = json_decode($vjson, true);
                if (is_array($vd)) {
                    $footer['entity']         = isset($vd['footer_entity']) ? (string)$vd['footer_entity'] : '';
                    $footer['entity_reg']     = isset($vd['entity_reg_number']) ? (string)$vd['entity_reg_number'] : '';
                    $footer['licence_number'] = isset($vd['licence_number']) ? (string)$vd['licence_number'] : '';
                    $footer['regulator']      = isset($vd['regulator']) ? (string)$vd['regulator'] : '';
                    $clauseLabels = array(
                        'tc_dormant'       => 'Dormant-account balance confiscation',
                        'tc_cashout_cap'   => 'Multiplicative cashout cap on deposits',
                        'tc_irregular'     => 'Vague "irregular play" / bonus-abuse confiscation',
                        'tc_maxbet_trap'   => 'Max-bet trap (applied retroactively)',
                        'tc_monthly_cap'   => 'Monthly withdrawal cap below EUR 5,000',
                        'tc_unilateral'    => 'Unilateral term changes without notice',
                        'tc_math_advantage'=> 'Vague "mathematical advantage" / low-risk ban',
                        'tc_kyc_repeat'    => 'Repeatable KYC at discretion, no SLA',
                    );
                    foreach ($clauseLabels as $k => $label) {
                        if (!empty($vd[$k])) $clauses[] = $label;
                    }
                }
            }
        } catch (Exception $e) {}

        return array(
            'casino_id'   => $casinoId,
            'title'       => $title !== '' ? $title : ('#' . $casinoId),
            'score'       => $score,
            'baseline'    => $baseline,
            'events'      => $events,
            'payout'      => $payout,
            'addl_lic'    => $addlLic,
            'evidence_n'  => $evCount,
            'frank'       => $frank,
            'frank_rank'  => $frankRank,
            'footer'      => $footer,
            'clauses'     => $clauses,
        );
    }

    /**
     * Public-panel helper: additional licences for a casino, taken from its
     * most recent COMMITTED verification. Exhibition only — these never affect
     * the score. Returns a list of array('key','number','label','tier').
     * Empty array if none / not committed. Self-contained and defensive so the
     * public renderer can call it without extra plumbing.
     */
    public static function additionalLicencesForCasino($casinoId)
    {
        $casinoId = (int)$casinoId;
        if ($casinoId <= 0) return array();
        $out = array();
        try {
            $db = JFactory::getDbo();
            $db->setQuery(
                "SELECT data FROM #__fec_verifications
                  WHERE casino_id = " . $casinoId . " AND status = 'committed'
                  ORDER BY committed_at DESC, id DESC", 0, 1
            );
            $json = $db->loadResult();
            if (!$json) return array();
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data['additional_licences']) || !is_array($data['additional_licences'])) {
                return array();
            }
            $map = self::regulatorTierMap();
            foreach ($data['additional_licences'] as $row) {
                if (!is_array($row)) continue;
                $rk  = isset($row['regulator_key']) ? (string)$row['regulator_key'] : '';
                $num = isset($row['number']) ? (string)$row['number'] : '';
                $label = ($rk !== '' && isset($map[$rk])) ? $map[$rk][0] : ($rk !== '' ? $rk : 'Licence');
                $tier  = ($rk !== '' && isset($map[$rk])) ? $map[$rk][1] : '';
                $out[] = array('key' => $rk, 'number' => $num, 'label' => $label, 'tier' => $tier);
            }
        } catch (Exception $e) {
            return array();
        }
        return $out;
    }

    /**
     * Translate a completed draft into the concrete changes a commit would
     * make: baseline field rows, the payout event, the PRG datapoint, and the
     * resulting confidence factors. READ-ONLY — computes, writes nothing. B3.2
     * (the actual commit) consumes the very same structure, so preview and
     * write can never diverge.
     *
     * Returns an array:
     *   baseline   => list of array(field_code, value, points, verified=>1)
     *   baseline_B => summed baseline (0-100)
     *   event      => array(code, weight, decay, note) | null
     *   payout     => array(declared_hours, measured_hours, prg) | null
     *   confidence => array('F'=>, 'V'=>, 'P'=>, note) // P unknown here (FXCheck), shown as engine-derived
     *   warnings   => list of human-readable caveats (missing fields, etc.)
     *
     * The point weights mirror the FXShield spec / safety-engine baseline.
     */
    public function computeCommitPreview($verificationId)
    {
        $v = $this->getById($verificationId);
        if (!$v) return null;
        $f = $v['fields'];
        $warn = array();

        // ---- Baseline rows (spec §3) ----
        $tierPoints = array('T1'=>32,'T2'=>26,'T3'=>18,'T4'=>8,'T5'=>0);
        $tier = strtoupper(trim((string)$f['license_tier']));
        $baseline = array();

        if ($tier === '' || !isset($tierPoints[$tier])) {
            $warn[] = 'No licence tier set — baseline licence points will be 0.';
            $licPts = 0; $tier = '';
        } else {
            $licPts = $tierPoints[$tier];
        }
        $baseline[] = array('field_code'=>'license_tier', 'value'=>$tier !== '' ? $tier : '(none)', 'points'=>$licPts);

        // ADR: tested=8, named=4, none=0  — derive from adr_named/adr_reachable
        $adrPts = 0; $adrVal = 'none';
        if ($f['adr_named'] === true) {
            if ($f['adr_reachable'] === true) { $adrPts = 8; $adrVal = 'tested'; }
            else { $adrPts = 4; $adrVal = 'named'; }
        }
        $baseline[] = array('field_code'=>'adr', 'value'=>$adrVal, 'points'=>$adrPts);

        // Entity transparency (4+4+4)
        $entityPts = ($f['entity_reg_number'] !== '' ? 4 : 0);
        $baseline[] = array('field_code'=>'entity_registered',
            'value'=>($f['entity_reg_number'] !== '' ? $f['entity_reg_number'] : '0'), 'points'=>$entityPts);

        $footerPts = ($f['footer_consistent'] === true && $f['licence_verified'] === 'valid') ? 4 : 0;
        $baseline[] = array('field_code'=>'license_footer', 'value'=>$footerPts ? '1' : '0', 'points'=>$footerPts);
        if ($f['licence_verified'] === 'not_found') {
            $warn[] = 'Licence number not found in the registry — consider a lower tier than ' . ($tier ?: 'the one set') . '.';
        }

        // no_fragmentation isn't a wizard field; the editor sets it in baseline.
        // Preview leaves it untouched (not part of this verification's scope).

        // RG tools (2fa 4, limits 6, selfexcl 6)
        $baseline[] = array('field_code'=>'rg_2fa',
            'value'=>($f['rg_2fa']===true?'1':'0'), 'points'=>($f['rg_2fa']===true?4:0));
        $baseline[] = array('field_code'=>'rg_limits',
            'value'=>($f['rg_limits']===true?'1':'0'), 'points'=>($f['rg_limits']===true?6:0));
        $baseline[] = array('field_code'=>'rg_selfexclusion',
            'value'=>($f['rg_selfexclusion']===true?'1':'0'), 'points'=>($f['rg_selfexclusion']===true?6:0));

        // T&C fairness: 32 - 4*clauses
        $clauseKeys = array('tc_dormant','tc_cashout_cap','tc_irregular','tc_maxbet_trap',
                            'tc_monthly_cap','tc_unilateral','tc_math_advantage','tc_kyc_repeat');
        $clauses = 0;
        foreach ($clauseKeys as $k) if (!empty($f[$k])) $clauses++;
        $tcPts = max(0, 32 - 4 * $clauses);
        $baseline[] = array('field_code'=>'tc_fairness', 'value'=>(string)$clauses, 'points'=>$tcPts);

        $B = 0; foreach ($baseline as $row) $B += $row['points'];

        // ---- Payout event + PRG (spec §5/§8) ----
        $event = null; $payout = null;
        $declared = ($f['declared_hours'] !== '') ? (float)$f['declared_hours'] : null;
        $measured = null;
        if ($f['requested_at'] !== '' && $f['credited_at'] !== '') {
            $t0 = strtotime($f['requested_at']); $t1 = strtotime($f['credited_at']);
            if ($t0 && $t1 && $t1 >= $t0) $measured = ($t1 - $t0) / 3600.0;
        }
        $outcome = (string)$f['payout_outcome'];

        if ($outcome === 'not_paid') {
            $event = array('code'=>'ST_PAYOUT_FAIL', 'weight'=>-25, 'decay'=>'slow',
                'note'=>'Test withdrawal not paid / confiscated');
        } elseif ($outcome !== '' && $measured !== null && $declared !== null && $declared > 0) {
            $ratio = $measured / $declared;
            if ($measured <= 24) {
                $event = array('code'=>'ST_PAYOUT_FAST', 'weight'=>4, 'decay'=>'standard',
                    'note'=>'Test withdrawal paid within 24h');
            } elseif ($ratio <= 1.0) {
                $event = array('code'=>'ST_PAYOUT_OK', 'weight'=>3, 'decay'=>'standard',
                    'note'=>'Test withdrawal paid within declared time');
            } elseif ($ratio < 3.0) {
                $event = array('code'=>'ST_PAYOUT_LATE_MINOR', 'weight'=>-4, 'decay'=>'standard',
                    'note'=>'Test withdrawal 1.5-3x declared time');
            } else {
                $event = array('code'=>'ST_PAYOUT_LATE_MAJOR', 'weight'=>-8, 'decay'=>'standard',
                    'note'=>'Test withdrawal 3x+ declared time');
            }
            $payout = array('declared_hours'=>$declared, 'measured_hours'=>round($measured,1),
                'prg'=>round($measured / $declared, 1));
        } else {
            $warn[] = 'No complete test withdrawal (need outcome + declared + requested/credited times) — no payout event or PRG will be written. The first-party check still raises confidence.';
        }

        // KYC obstruction is a separate event if flagged severe
        $kycEvent = null;
        if ($f['kyc_required'] === true) {
            $cycles = (int)$f['kyc_cycles']; $hours = (float)$f['kyc_hours'];
            if ($cycles > 2 || $hours > 168) {
                $kycEvent = array('code'=>'ST_KYC_OBSTRUCTION', 'weight'=>-6, 'decay'=>'standard',
                    'note'=>'KYC obstruction during test ('.$cycles.' cycles)');
            }
        }

        // ---- Confidence factors (spec §11) ----
        // F = freshness: this verification is "today" -> 1.0
        // V = verified baseline fields / 9 (the wizard verifies 8 + tc read = up to 9)
        $verifiedCount = 0;
        // licence(+adr counted as licence&recourse area), entity, footer, 2fa, limits, selfexcl, tc
        // count fields that were actually answered (not left blank/null)
        if ($tier !== '') $verifiedCount++;
        $verifiedCount++; // adr always determined
        if ($f['entity_reg_number'] !== '' || $f['footer_entity'] !== '') $verifiedCount++;
        $verifiedCount++; // footer consistency answered
        if ($f['rg_2fa'] !== null) $verifiedCount++;
        if ($f['rg_limits'] !== null) $verifiedCount++;
        if ($f['rg_selfexclusion'] !== null) $verifiedCount++;
        $verifiedCount++; // tc read
        $V = min(1.0, $verifiedCount / 9.0);

        $confidence = array(
            'F' => 1.0, 'V' => round($V, 2),
            'note' => 'F=1.0 (verified today), V='.round($V,2).' ('.$verifiedCount.'/9 fields). '
                    . 'P (FXCheck sample) is set by the engine. Final grade computed on recalc; '
                    . 'a complete check today typically lands B or A.'
        );

        return array(
            'casino_id'  => (int)$v['casino_id'],
            'baseline'   => $baseline,
            'baseline_B' => $B,
            'tier'       => $tier,
            'event'      => $event,
            'kyc_event'  => $kycEvent,
            'payout'     => $payout,
            'confidence' => $confidence,
            'warnings'   => $warn,
        );
    }

    /**
     * Commit a verification (B3.2): write its preview through to the engine
     * tables, transactionally, then mark the draft committed.
     *
     * Writes:
     *   - baseline rows (upsert on PK casino_id,field_code) with
     *     verified_first_party = 1 and verified_at = today
     *   - the payout event into the ledger (if the test withdrawal is complete)
     *   - the payout_tests row, linked to that event
     *   - (kyc obstruction event, if flagged severe)
     *   - the verification row -> status committed, committed_at = now
     *
     * Deliberately does NOT recompute the score: no shell_exec from the web
     * tier. The weekly cron, or a manual `safety-engine.php --casino=ID`,
     * recalculates. Returns array('ok'=>bool, 'message'=>..., 'casino_id'=>..).
     *
     * Anti-double-commit: refuses if the row is already committed. Whole thing
     * runs inside a transaction; any failure rolls back and leaves the draft
     * untouched so it can be retried.
     *
     * NOTE: never writes no_fragmentation — not in this verification's scope;
     * the engine/editor owns that baseline field separately. Only the 8 fields
     * the wizard actually verifies are written, so a commit can't silently zero
     * a field the wizard doesn't cover.
     */
    public function commitVerification($verificationId, $userId)
    {
        $db = JFactory::getDbo();

        $v = $this->getById($verificationId);
        if (!$v) {
            return array('ok' => false, 'message' => 'Verification not found.');
        }
        if ($v['status'] === 'committed') {
            return array('ok' => false, 'message' => 'This verification is already committed.');
        }

        $preview = $this->computeCommitPreview($verificationId);
        if (!$preview) {
            return array('ok' => false, 'message' => 'Could not compute the commit.');
        }

        $casinoId = (int)$v['casino_id'];
        $f        = $v['fields'];
        $today    = JFactory::getDate()->toSql();           // datetime
        $todayDate = substr($today, 0, 10);                  // DATE
        $evFooter = $db->quote((string)$f['ev_footer']);

        // Map each baseline field to the evidence url most relevant to it.
        $evidenceFor = array(
            'license_tier'      => (string)$f['ev_footer'],
            'adr'               => (string)$f['ev_adr'],
            'entity_registered' => (string)$f['ev_footer'],
            'license_footer'    => (string)$f['ev_footer'],
            'rg_2fa'            => (string)$f['ev_rg'],
            'rg_limits'         => (string)$f['ev_rg'],
            'rg_selfexclusion'  => (string)$f['ev_rg'],
            'tc_fairness'       => (string)$f['ev_tc'],
        );

        $db->transactionStart();
        try {
            // Inherited-but-unconfirmed fields must NOT be stamped as verified
            // today. Map each baseline code to the wizard field that feeds it;
            // if that field is still in _inherited, the editor never reconfirmed
            // it this session, so we preserve the PREVIOUS verified_at instead
            // of today's date. This keeps freshness/confidence honest.
            $inheritedNow = (isset($f['_inherited']) && is_array($f['_inherited'])) ? $f['_inherited'] : array();
            $baselineSource = array(
                'license_tier'      => 'license_tier',
                'adr'               => 'adr_status',
                'entity_registered' => 'entity_reg_number',
                'license_footer'    => 'footer_consistent',
                'rg_2fa'            => 'rg_2fa',
                'rg_limits'         => 'rg_limits',
                'rg_selfexclusion'  => 'rg_selfexclusion',
                'tc_fairness'       => 'tc_clauses',
            );
            // Previous verified_at per field_code, to preserve where inherited.
            $prevVerifiedAt = array();
            try {
                $db->setQuery("SELECT field_code, verified_at FROM #__fec_safety_baseline WHERE casino_id = " . $casinoId);
                foreach (($db->loadAssocList() ?: array()) as $pr) {
                    $prevVerifiedAt[$pr['field_code']] = $pr['verified_at'];
                }
            } catch (Exception $e) {}

            // 1. Baseline rows — upsert. verified_at = today for fields confirmed
            //    this session; previous date preserved for inherited-unconfirmed.
            foreach ($preview['baseline'] as $row) {
                $code  = $row['field_code'];
                $value = $db->quote((string)$row['value']);
                $pts   = (float)$row['points'];
                $ev    = isset($evidenceFor[$code]) ? trim($evidenceFor[$code]) : '';
                $evSql = ($ev !== '') ? $db->quote($ev) : 'NULL';

                // Is this baseline field still inherited (not reconfirmed)?
                $srcField = isset($baselineSource[$code]) ? $baselineSource[$code] : null;
                $stillInh = ($srcField !== null && in_array($srcField, $inheritedNow, true));
                if ($stillInh && isset($prevVerifiedAt[$code]) && $prevVerifiedAt[$code]) {
                    $vAtSql = $db->quote($prevVerifiedAt[$code]); // keep old date
                } else {
                    $vAtSql = $db->quote($todayDate);             // confirmed today
                }

                $sql = "INSERT INTO #__fec_safety_baseline
                        (casino_id, field_code, field_value, points,
                         verified_first_party, verified_at, evidence_url)
                        VALUES (" . $casinoId . ", " . $db->quote($code) . ", "
                        . $value . ", " . $pts . ", 1, " . $vAtSql . ", " . $evSql . ")
                        ON DUPLICATE KEY UPDATE
                         field_value = VALUES(field_value),
                         points = VALUES(points),
                         verified_first_party = 1,
                         verified_at = VALUES(verified_at),
                         evidence_url = VALUES(evidence_url)";
                $db->setQuery($sql);
                $db->execute();
            }

            // 2. Payout event (+ optional KYC obstruction event), then payout_tests.
            $payoutEventId = null;
            $events = array();
            if (!empty($preview['event']))     $events[] = array($preview['event'], true);
            if (!empty($preview['kyc_event'])) $events[] = array($preview['kyc_event'], false);

            foreach ($events as $pair) {
                list($ev, $isPayout) = $pair;
                // event_date: for a payout test, the request date; else today.
                $evDate = $todayDate;
                if ($isPayout && $f['requested_at'] !== '') {
                    $ts = strtotime($f['requested_at']);
                    if ($ts) $evDate = date('Y-m-d', $ts);
                }
                $note = $db->quote((string)$ev['note']);
                $evUrl = trim((string)$f['ev_payout']);
                $evUrlSql = ($evUrl !== '') ? $db->quote($evUrl) : 'NULL';

                $sql = "INSERT INTO #__fec_safety_events
                        (casino_id, event_code, event_date, base_weight, decay_class,
                         source_type, evidence_url, evidence_note, visibility, status,
                         created_by, created_at)
                        VALUES (" . $casinoId . ", " . $db->quote($ev['code']) . ", "
                        . $db->quote($evDate) . ", " . (float)$ev['weight'] . ", "
                        . $db->quote($ev['decay']) . ", 'staff_test', " . $evUrlSql . ", "
                        . $note . ", 'public', 'active', " . (int)$userId . ", " . $db->quote($today) . ")";
                $db->setQuery($sql);
                $db->execute();
                if ($isPayout) $payoutEventId = (int)$db->insertid();
            }

            // 3. payout_tests row, linked to the payout event (if any).
            if (!empty($preview['payout'])) {
                $p = $preview['payout'];
                $eventIdSql = $payoutEventId ? (int)$payoutEventId : 'NULL';
                $evUrl = trim((string)$f['ev_payout']);
                $evUrlSql = ($evUrl !== '') ? $db->quote($evUrl) : 'NULL';
                $sql = "INSERT INTO #__fec_payout_tests
                        (casino_id, test_date, source, declared_hours, measured_hours,
                         event_id, evidence_url)
                        VALUES (" . $casinoId . ", " . $db->quote($todayDate) . ", 'staff', "
                        . (float)$p['declared_hours'] . ", " . (float)$p['measured_hours'] . ", "
                        . $eventIdSql . ", " . $evUrlSql . ")";
                $db->setQuery($sql);
                $db->execute();
            }

            // 4. Mark the verification committed.
            $db->setQuery(
                "UPDATE #__fec_verifications
                    SET status = 'committed', committed_at = " . $db->quote($today) . "
                  WHERE id = " . (int)$verificationId . " AND status = 'draft'"
            );
            $db->execute();

            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            return array('ok' => false,
                'message' => 'Commit failed and was rolled back: ' . $e->getMessage());
        }

        return array(
            'ok'        => true,
            'casino_id' => $casinoId,
            'message'   => 'Committed. Baseline and events written; the score updates on the '
                         . 'next recalculation (weekly cron), or run safety-engine.php --casino='
                         . $casinoId . ' to see it now.',
        );
    }

    /**
     * Create a fresh draft for a casino and return its id.
     */
    public function createDraft($casinoId, $userId)
    {
        $db  = JFactory::getDbo();
        $now = JFactory::getDate()->toSql();

        // Start from a clean schema.
        $fields = self::fieldSchema();
        $inherited = array();

        // Pre-fill from the most recent COMMITTED verification of this casino,
        // if any. Values are carried over so the editor confirms/edits instead
        // of re-typing — but every carried field is flagged in `_inherited`,
        // meaning "from a previous verification, NOT yet reconfirmed in this
        // session". The verified_first_party flag/date are NOT carried: only
        // fields the editor actually touches this session count as fresh.
        try {
            $db->setQuery(
                "SELECT data FROM #__fec_verifications
                  WHERE casino_id = " . (int)$casinoId . " AND status = 'committed'
                  ORDER BY committed_at DESC, id DESC", 0, 1
            );
            $prevJson = $db->loadResult();
            if ($prevJson) {
                $prev = json_decode($prevJson, true);
                if (is_array($prev)) {
                    foreach ($fields as $k => $default) {
                        // Skip evidence-meta and control keys; carry real data fields.
                        if ($k === '_inherited') continue;
                        if (array_key_exists($k, $prev) && $prev[$k] !== '' && $prev[$k] !== array()) {
                            $fields[$k] = $prev[$k];
                            $inherited[] = $k;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // No pre-fill on error — fall back to empty draft.
            $inherited = array();
        }

        // Record which fields are inherited-and-unconfirmed.
        $fields['_inherited'] = $inherited;

        $payload = $db->quote(json_encode($fields));
        $sql = "INSERT INTO #__fec_verifications
                (casino_id, status, step, data, created_by, created_at, updated_at)
                VALUES (" . (int)$casinoId . ", 'draft', 0, " . $payload . ", "
                . (int)$userId . ", " . $db->quote($now) . ", " . $db->quote($now) . ")";
        $db->setQuery($sql);
        $db->execute();
        return (int)$db->insertid();
    }

    /**
     * Load one verification by id. Returns assoc row with `fields` (decoded
     * array, merged over the schema so new keys always exist), or null.
     */
    public function getById($id)
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT * FROM #__fec_verifications WHERE id = " . (int)$id
        );
        $row = $db->loadAssoc();
        if (!$row) return null;
        $decoded = array();
        if (!empty($row['data'])) {
            $tmp = json_decode($row['data'], true);
            if (is_array($tmp)) $decoded = $tmp;
        }
        // Merge over schema so the wizard never hits an undefined key even if
        // the draft predates a schema addition.
        $row['fields'] = array_merge(self::fieldSchema(), $decoded);
        return $row;
    }

    /**
     * Save partial progress on a draft. $fields is the full field array (the
     * wizard posts the merged set each step). $step is the furthest step
     * reached. Committed rows are immutable — refuse to overwrite them.
     */
    public function saveDraft($id, array $fields, $step)
    {
        $db  = JFactory::getDbo();
        $cur = $this->getById($id);
        if (!$cur) return false;
        if ($cur['status'] === 'committed') return false;   // immutable once committed

        // Whitelist keys to the schema — drop anything unexpected from POST.
        $clean = array();
        foreach (self::fieldSchema() as $k => $default) {
            $clean[$k] = array_key_exists($k, $fields) ? $fields[$k] : $cur['fields'][$k];
        }

        // Reconfirmation tracking: any field the editor actually submitted this
        // save drops out of `_inherited` (it has now been seen and confirmed
        // this session). Fields not yet touched stay flagged.
        $stillInherited = array();
        $prevInherited = (isset($cur['fields']['_inherited']) && is_array($cur['fields']['_inherited']))
                       ? $cur['fields']['_inherited'] : array();
        foreach ($prevInherited as $fk) {
            // If this field was part of the submitted set, it's reconfirmed -> drop it.
            if (!array_key_exists($fk, $fields)) {
                $stillInherited[] = $fk; // not in this submission -> still pending
            }
        }
        $clean['_inherited'] = $stillInherited;

        // Sanitize additional_licences into a clean list of {regulator_key, number}.
        // Exhibition-only data; never trusted raw. Drops empty rows and unknown
        // regulators, caps length defensively.
        $cleanLic = array();
        if (isset($clean['additional_licences']) && is_array($clean['additional_licences'])) {
            $map = self::regulatorTierMap();
            foreach ($clean['additional_licences'] as $row) {
                if (!is_array($row)) continue;
                $rk  = isset($row['regulator_key']) ? trim((string)$row['regulator_key']) : '';
                $num = isset($row['number']) ? trim((string)$row['number']) : '';
                if ($rk === '' && $num === '') continue;       // skip blank rows
                if ($rk !== '' && !isset($map[$rk])) $rk = '';  // unknown regulator -> blank key
                $cleanLic[] = array(
                    'regulator_key' => substr($rk, 0, 40),
                    'number'        => substr($num, 0, 80),
                );
                if (count($cleanLic) >= 12) break;             // defensive cap
            }
        }
        $clean['additional_licences'] = $cleanLic;

        $now  = JFactory::getDate()->toSql();
        $step = max(0, min(self::TOTAL_STEPS, (int)$step));
        // keep the furthest step ever reached
        if ($step < (int)$cur['step']) $step = (int)$cur['step'];

        $db->setQuery(
            "UPDATE #__fec_verifications SET "
            . "data = " . $db->quote(json_encode($clean)) . ", "
            . "step = " . (int)$step . ", "
            . "updated_at = " . $db->quote($now) . " "
            . "WHERE id = " . (int)$id . " AND status = 'draft'"
        );
        $db->execute();
        return true;
    }

    /**
     * List verifications, newest first. Optional casino filter. Joins the
     * jReviews/content title for display.
     */
    public function listAll($casinoId = 0)
    {
        $db = JFactory::getDbo();
        $where = "1=1";
        if ((int)$casinoId > 0) $where = "v.casino_id = " . (int)$casinoId;
        $db->setQuery(
            "SELECT v.id, v.casino_id, v.status, v.step, v.created_by,
                    v.created_at, v.updated_at, v.committed_at,
                    c.title AS casino_title
               FROM #__fec_verifications v
               LEFT JOIN #__content c ON c.id = v.casino_id
              WHERE " . $where . "
              ORDER BY v.updated_at DESC"
        );
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Latest draft (in-progress) for a casino, or null. Used by the cockpit to
     * offer "resume verification" vs "start new".
     */
    public function getOpenDraftForCasino($casinoId)
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT id FROM #__fec_verifications
              WHERE casino_id = " . (int)$casinoId . " AND status = 'draft'
              ORDER BY updated_at DESC", 0, 1
        );
        $id = $db->loadResult();
        return $id ? $this->getById((int)$id) : null;
    }
}
