<?php
/**
 * AuthorsControllerVerification — FXShield verification wizard endpoints (B2).
 *
 * Standard form POST (not AJAX), mirroring AuthorsControllerMethodologyadmin.
 * Every task hard-gates AuthorsModelDashboard::isAuthorized() AND a valid
 * Joomla form token before touching anything.
 *
 * Tasks:
 *   start     — create a fresh draft for a casino, redirect into step 1
 *   saveStep  — persist the posted fields for the current step; then either
 *               advance to the next step or exit to the list (per `after`)
 *
 * B2 writes ONLY to #__fec_verifications (the draft). It does NOT touch
 * baseline / events / payout_tests — that write-through is the commit task,
 * added in B3. So nothing here can affect a live FXShield score.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/verification.php';
require_once JPATH_COMPONENT . '/models/verificationevidence.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsControllerVerification extends JController
{
    /** Create a new draft and jump into step 1. */
    public function start()
    {
        if (!$this->gate()) return;

        $casinoId = (int) JRequest::getVar('casino_id', 0, 'post', 'int');
        if ($casinoId <= 0) {
            $this->fail('A casino must be selected to start a verification.');
            return;
        }

        $model  = new AuthorsModelVerification();
        $userId = (int) JFactory::getUser()->id;
        $id     = $model->createDraft($casinoId, $userId);

        if (!$id) {
            $this->fail('Could not create the verification draft.');
            return;
        }

        $this->setRedirect(
            'index.php?option=com_authors&view=verification&id=' . $id . '&step=1'
        );
    }

    /** Commit a verification: write its preview through to the engine tables. */
    public function commit()
    {
        if (!$this->gate()) return;

        $id = (int) JRequest::getVar('id', 0, 'post', 'int');
        $model = new AuthorsModelVerification();
        $userId = (int) JFactory::getUser()->id;

        $res = $model->commitVerification($id, $userId);

        if (empty($res['ok'])) {
            $msg = isset($res['message']) ? $res['message'] : 'Commit failed.';
            $this->setRedirect(
                'index.php?option=com_authors&view=verification&id=' . $id . '&step=6&error=' . urlencode($msg)
            );
            return;
        }

        $this->setRedirect(
            'index.php?option=com_authors&view=fxshieldadmin',
            $res['message']
        );
    }

    /** Upload one evidence file for a field, return to the same step. */
    public function uploadEvidence()
    {
        if (!$this->gate()) return;

        $id    = (int) JRequest::getVar('id', 0, 'post', 'int');
        $step  = (int) JRequest::getVar('step', 1, 'post', 'int');
        $field = (string) JRequest::getVar('field_code', '', 'post');

        $vModel = new AuthorsModelVerification();
        $cur = $vModel->getById($id);
        if (!$cur) { $this->fail('Verification not found.'); return; }
        if ($cur['status'] === 'committed') {
            $this->failStep($id, $step, 'Committed verifications cannot take new files.');
            return;
        }

        // Detect a post_max_size overflow first: when the whole request body
        // exceeds post_max_size, PHP discards $_POST AND $_FILES entirely, so
        // we'd otherwise see "no files" with no clue why. CONTENT_LENGTH being
        // large while $_FILES is empty is the tell.
        $postMax = $this->iniBytes(ini_get('post_max_size'));
        $contentLen = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        if (empty($_FILES) && $postMax > 0 && $contentLen > $postMax) {
            $this->failStep($id, $step, 'Upload too large for the server in one go (total exceeded '
                . ini_get('post_max_size') . '). Upload fewer files at once, or ask the host to raise post_max_size.');
            return;
        }

        // Read uploaded files. With name="evidence_file[]" PHP gives a
        // column-wise structure ($_FILES['evidence_file']['name'] is an array,
        // etc.); normalize it into one $file array per upload, then process
        // each through the same single-file model.store() (validation, MIME
        // sniff, safe naming, cataloging all live there — reused, not copied).
        $raw = isset($_FILES['evidence_file']) ? $_FILES['evidence_file'] : null;
        if (!is_array($raw) || !isset($raw['tmp_name'])) {
            $this->failStep($id, $step, 'No file received. (If the file looked fine, it may exceed the server upload limit, '
                . ini_get('upload_max_filesize') . ' per file.)');
            return;
        }

        // Normalize: handle both the multi ([] -> arrays) and the legacy single
        // (scalar) shapes, so an older cached form still works. Crucially we
        // keep entries that have a PHP-level upload error (e.g. INI_SIZE) so we
        // can report WHY, instead of silently dropping them.
        $files = array();
        $skipErrors = array();
        if (is_array($raw['tmp_name'])) {
            $n = count($raw['tmp_name']);
            for ($i = 0; $i < $n; $i++) {
                $err = isset($raw['error'][$i]) ? (int)$raw['error'][$i] : UPLOAD_ERR_NO_FILE;
                $nm  = isset($raw['name'][$i]) ? $raw['name'][$i] : 'file';
                // A genuinely empty slot (no file chosen) is NO_FILE — skip silently.
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                // Any other error with no tmp_name: record a human reason.
                if (!isset($raw['tmp_name'][$i]) || $raw['tmp_name'][$i] === '') {
                    $skipErrors[] = $nm . ': ' . $this->uploadErrText($err);
                    continue;
                }
                $files[] = array(
                    'name'     => $nm,
                    'type'     => isset($raw['type'][$i]) ? $raw['type'][$i] : '',
                    'tmp_name' => $raw['tmp_name'][$i],
                    'error'    => $err,
                    'size'     => isset($raw['size'][$i]) ? $raw['size'][$i] : 0,
                );
            }
        } elseif ($raw['tmp_name'] !== '') {
            $files[] = $raw;
        } elseif (isset($raw['error']) && (int)$raw['error'] !== UPLOAD_ERR_NO_FILE) {
            $skipErrors[] = ($raw['name'] !== '' ? $raw['name'] : 'file') . ': ' . $this->uploadErrText((int)$raw['error']);
        }

        if (empty($files) && empty($skipErrors)) {
            $this->failStep($id, $step, 'No file received.');
            return;
        }
        if (empty($files) && !empty($skipErrors)) {
            $this->failStep($id, $step, 'Upload failed — ' . implode('; ', $skipErrors));
            return;
        }

        $evModel = new AuthorsModelVerificationEvidence();
        $userId  = (int) JFactory::getUser()->id;

        $ok = 0;
        $errors = $skipErrors; // start with PHP-level rejections (size, etc.)
        foreach ($files as $file) {
            $res = $evModel->store($id, (int)$cur['casino_id'], $field, $file, $userId);
            if (!empty($res['ok'])) {
                $ok++;
            } else {
                $label = ($file['name'] !== '') ? $file['name'] : 'file';
                $errors[] = $label . ': ' . $res['message'];
            }
        }

        // Build a summary message. Partial success is reported honestly: the
        // files that saved stay saved, the ones that failed are named.
        if ($ok > 0 && empty($errors)) {
            $msg = $ok . ($ok === 1 ? ' file uploaded.' : ' files uploaded.');
            $this->setRedirect(
                'index.php?option=com_authors&view=verification&id=' . $id . '&step=' . $step, $msg
            );
        } elseif ($ok > 0 && !empty($errors)) {
            $msg = $ok . ' uploaded, ' . count($errors) . ' failed — ' . implode('; ', $errors);
            $this->failStep($id, $step, $msg);
        } else {
            $this->failStep($id, $step, 'Upload failed — ' . implode('; ', $errors));
        }
    }

    /** Human-readable text for a PHP $_FILES error code. */
    protected function uploadErrText($code)
    {
        switch ((int)$code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'larger than the server per-file limit (' . ini_get('upload_max_filesize') . ').';
            case UPLOAD_ERR_FORM_SIZE:
                return 'larger than the form-allowed size.';
            case UPLOAD_ERR_PARTIAL:
                return 'only partially uploaded (connection interrupted) — retry.';
            case UPLOAD_ERR_NO_FILE:
                return 'no file was provided.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'server has no temp folder for uploads (host config).';
            case UPLOAD_ERR_CANT_WRITE:
                return 'server could not write the file to disk (host config).';
            case UPLOAD_ERR_EXTENSION:
                return 'blocked by a server extension.';
            default:
                return 'upload error code ' . (int)$code . '.';
        }
    }

    /** Parse a php.ini shorthand size (e.g. "8M", "512K", "1G") into bytes. */
    protected function iniBytes($val)
    {
        $val = trim((string)$val);
        if ($val === '') return 0;
        $unit = strtolower($val[strlen($val) - 1]);
        $num  = (int)$val;
        switch ($unit) {
            case 'g': $num *= 1024; // fall through
            case 'm': $num *= 1024; // fall through
            case 'k': $num *= 1024;
        }
        return $num;
    }

    /** Delete one evidence file (pre-commit only), return to the step. */
    public function deleteEvidence()
    {
        if (!$this->gate()) return;

        $id     = (int) JRequest::getVar('id', 0, 'post', 'int');
        $step   = (int) JRequest::getVar('step', 1, 'post', 'int');
        $evId   = (int) JRequest::getVar('evidence_id', 0, 'post', 'int');

        $vModel = new AuthorsModelVerification();
        $cur = $vModel->getById($id);
        if (!$cur) { $this->fail('Verification not found.'); return; }
        if ($cur['status'] === 'committed') {
            $this->failStep($id, $step, 'Committed verifications are locked.');
            return;
        }

        $evModel = new AuthorsModelVerificationEvidence();
        $evModel->delete($evId);
        $this->setRedirect(
            'index.php?option=com_authors&view=verification&id=' . $id . '&step=' . $step,
            'File removed.'
        );
    }

    protected function failStep($id, $step, $msg)
    {
        $this->setRedirect(
            'index.php?option=com_authors&view=verification&id=' . $id . '&step=' . $step
            . '&error=' . urlencode($msg)
        );
    }

    /** Save the posted fields for one step, then advance or exit. */
    public function saveStep()
    {
        if (!$this->gate()) return;

        $id   = (int) JRequest::getVar('id',   0, 'post', 'int');
        $step = (int) JRequest::getVar('step', 1, 'post', 'int');
        $after = (string) JRequest::getVar('after', 'next', 'post'); // 'next' | 'prev' | 'exit'

        $model = new AuthorsModelVerification();
        $cur   = $model->getById($id);
        if (!$cur) {
            $this->fail('Verification not found.');
            return;
        }
        if ($cur['status'] === 'committed') {
            $this->fail('This verification is committed and can no longer be edited.');
            return;
        }

        // Merge posted values for THIS step over the stored field set. The
        // wizard posts only the current step's inputs; the model whitelists
        // to the schema, so unknown keys are dropped.
        $fields = $cur['fields'];
        foreach ($this->postedFieldsForStep($step) as $k => $v) {
            $fields[$k] = $v;
        }

        // The furthest step reached becomes max(current, this step). The model
        // also enforces monotonicity, this is just for the redirect target.
        $reached = max((int)$cur['step'], $step);
        $model->saveDraft($id, $fields, $reached);

        // Decide where to go next
        if ($after === 'exit') {
            $this->setRedirect(
                'index.php?option=com_authors&view=fxshieldadmin',
                'Progress saved. Resume any time from the verifications list.'
            );
            return;
        }

        // Steps 1..5 collect data; step 6 is the read-only preview. Allow
        // advancing one past the last data step into the preview.
        $previewStep = AuthorsModelVerification::TOTAL_STEPS + 1; // 6
        $target = ($after === 'prev') ? max(1, $step - 1) : min($previewStep, $step + 1);

        $this->setRedirect(
            'index.php?option=com_authors&view=verification&id=' . $id . '&step=' . $target
        );
    }

    /**
     * Pull and lightly type the inputs that belong to a given step. Text and
     * evidence fields go through the 'string' filter; the T&C clause checkboxes
     * and the boolean radios are normalised to real booleans here so the JSON
     * payload stays clean.
     */
    protected function postedFieldsForStep($step)
    {
        $g = function ($k, $type = 'string') {
            return JRequest::getVar($k, '', 'post', $type);
        };
        // tri-state radio: 'yes' -> true, 'no' -> false, '' -> null (unanswered)
        $tri = function ($k) {
            $v = JRequest::getVar($k, '', 'post');
            if ($v === 'yes') return true;
            if ($v === 'no')  return false;
            return null;
        };
        // checkbox present in POST -> true, absent -> false
        $chk = function ($k) {
            return (int) JRequest::getVar($k, 0, 'post', 'int') === 1;
        };

        switch ((int)$step) {
            case 1: return array(
                'footer_entity'     => $g('footer_entity'),
                'entity_reg_number' => $g('entity_reg_number'),
                'licence_number'    => $g('licence_number'),
                'regulator'         => $g('regulator'),
                'regulator_key'     => $g('regulator_key'),
                'licence_verified'  => $g('licence_verified'),
                'footer_consistent' => $tri('footer_consistent'),
                'license_tier'      => $g('license_tier'),
                'ev_footer'         => $g('ev_footer'),
                // Repeatable additional licences (exhibition only). Read as a
                // raw array; the model sanitizes each {regulator_key, number}.
                'additional_licences' => (array) JRequest::getVar('additional_licences', array(), 'post', 'array'),
            );
            case 2: return array(
                'rg_2fa'           => $tri('rg_2fa'),
                'rg_limits'        => $tri('rg_limits'),
                'rg_selfexclusion' => $tri('rg_selfexclusion'),
                'ev_rg'            => $g('ev_rg'),
            );
            case 3: return array(
                'adr_named'     => $tri('adr_named'),
                'adr_name'      => $g('adr_name'),
                'adr_reachable' => $tri('adr_reachable'),
                'ev_adr'        => $g('ev_adr'),
            );
            case 4: return array(
                'tc_dormant'        => $chk('tc_dormant'),
                'tc_cashout_cap'    => $chk('tc_cashout_cap'),
                'tc_irregular'      => $chk('tc_irregular'),
                'tc_maxbet_trap'    => $chk('tc_maxbet_trap'),
                'tc_monthly_cap'    => $chk('tc_monthly_cap'),
                'tc_unilateral'     => $chk('tc_unilateral'),
                'tc_math_advantage' => $chk('tc_math_advantage'),
                'tc_kyc_repeat'     => $chk('tc_kyc_repeat'),
                'ev_tc'             => $g('ev_tc'),
            );
            case 5: return array(
                'deposit_amount'  => $g('deposit_amount'),
                'deposit_method'  => $g('deposit_method'),
                'withdraw_amount' => $g('withdraw_amount'),
                'withdraw_method' => $g('withdraw_method'),
                'declared_hours'  => $g('declared_hours'),
                'requested_at'    => $g('requested_at'),
                'credited_at'     => $g('credited_at'),
                'kyc_required'    => $tri('kyc_required'),
                'kyc_cycles'      => $g('kyc_cycles'),
                'kyc_hours'       => $g('kyc_hours'),
                'payout_outcome'  => $g('payout_outcome'),
                'ev_payout'       => $g('ev_payout'),
                'notes'           => $g('notes'),
            );
        }
        return array();
    }

    /** Shared gate: auth + CSRF. Returns false (and sets a redirect) on failure. */
    protected function gate()
    {
        if (!AuthorsModelDashboard::isAuthorized()) {
            $this->setRedirect(
                'index.php?option=com_authors&view=fxshieldadmin',
                'Forbidden', 'error'
            );
            return false;
        }
        if (!JRequest::checkToken('post')) {
            $this->fail('Invalid form token. Please retry.');
            return false;
        }
        return true;
    }

    protected function fail($msg)
    {
        $this->setRedirect(
            'index.php?option=com_authors&view=fxshieldadmin',
            $msg, 'error'
        );
    }
}
