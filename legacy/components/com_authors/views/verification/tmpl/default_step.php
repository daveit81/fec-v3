<?php
defined('_JEXEC') or die;

/**
 * Verification wizard — step.php. One page per step, progress bar on top.
 * Data: $this->vid, $this->fields, $this->step, $this->total, $this->casino,
 *       $this->committed, $this->error.
 */
$f     = is_array($this->fields) ? $this->fields : array();
$step  = (int)$this->step;
$total = (int)$this->total;
$vid   = (int)$this->vid;
$ro    = !empty($this->committed);   // read-only if committed
$h     = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$token = JHtml::_('form.token');

$stepNames = array(1 => 'Footer & licence', 2 => 'RG tools', 3 => 'ADR', 4 => 'T&C clauses', 5 => 'Test withdrawal');

// helpers to render stored values back into inputs
$val = function ($k) use ($f, $h) { return $h(isset($f[$k]) ? $f[$k] : ''); };
$triChecked = function ($k, $opt) use ($f) {
    $v = isset($f[$k]) ? $f[$k] : null;
    if ($opt === 'yes') return $v === true ? 'checked' : '';
    if ($opt === 'no')  return $v === false ? 'checked' : '';
    return '';
};
$chkChecked = function ($k) use ($f) { return !empty($f[$k]) ? 'checked' : ''; };
$disabled = $ro ? 'disabled' : '';

// Pre-fill awareness: fields carried over from a previous committed
// verification and not yet reconfirmed this session. Loaded into _inherited
// by createDraft; a field drops out once the editor saves the step holding it.
$inherited = (isset($f['_inherited']) && is_array($f['_inherited'])) ? $f['_inherited'] : array();
$isInherited = function ($k) use ($inherited) { return in_array($k, $inherited, true); };
// Fields belonging to the current step (for the banner check).
$stepFieldMap = array(
    1 => array('footer_entity','entity_reg_number','licence_number','regulator','regulator_key','licence_verified','footer_consistent','license_tier','additional_licences'),
    2 => array('rg_2fa','rg_limits','rg_selfexclusion'),
    3 => array('adr_status','adr_provider'),
    4 => array('tc_clauses','tc_predatory_count'),
    5 => array('declared_hours','measured_hours','payout_outcome','kyc_cycles','kyc_hours'),
);
$stepInheritedCount = 0;
if (!$ro && isset($stepFieldMap[$step])) {
    foreach ($stepFieldMap[$step] as $fk) { if ($isInherited($fk)) $stepInheritedCount++; }
}

// Which evidence field belongs to each step, and its public/private nature.
$stepEvidence = array(1=>'ev_footer', 2=>'ev_rg', 3=>'ev_adr', 4=>'ev_tc', 5=>'ev_payout');
$evField = isset($stepEvidence[$step]) ? $stepEvidence[$step] : '';
$evidence = isset($this->evidence) && is_array($this->evidence) ? $this->evidence : array();
$evFiles = ($evField && isset($evidence[$evField])) ? $evidence[$evField] : array();
$evIsPrivate = in_array($evField, array('ev_payout','ev_kyc'), true);

// Renders the upload control + attached-files list for the current step's field.
$renderEvidenceBox = function () use ($evField, $evFiles, $evIsPrivate, $vid, $step, $ro, $h, $token) {
    if ($evField === '') return;
    ?>
    <div class="fxv-evidence">
      <div class="fxv-ev-head">
        Screenshots / files for this step
        <span class="fxv-ev-tag <?php echo $evIsPrivate ? 'fxv-ev-private' : 'fxv-ev-public'; ?>">
          <?php echo $evIsPrivate ? 'private — never published' : 'publishable as proof'; ?>
        </span>
      </div>
      <?php if (!empty($evFiles)): ?>
        <ul class="fxv-ev-list">
          <?php foreach ($evFiles as $file): ?>
            <li>
              <span class="fxv-ev-name"><?php echo $h($file['original_name']); ?></span>
              <span class="fxv-ev-meta"><?php echo $h(strtoupper(substr($file['mime_type'], strpos($file['mime_type'],'/')+1))); ?> · <?php echo round($file['byte_size']/1024); ?> KB</span>
              <?php if (!$ro): ?>
                <form method="post" action="index.php?option=com_authors" class="fxv-ev-del" onsubmit="return confirm('Remove this file?');">
                  <input type="hidden" name="option" value="com_authors">
                  <input type="hidden" name="task" value="verification.deleteEvidence">
                  <input type="hidden" name="id" value="<?php echo $vid; ?>">
                  <input type="hidden" name="step" value="<?php echo $step; ?>">
                  <input type="hidden" name="evidence_id" value="<?php echo (int)$file['id']; ?>">
                  <?php echo $token; ?>
                  <button type="submit" class="fxv-ev-x" title="Remove">&times;</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="fxv-ev-empty">No files attached yet.</p>
      <?php endif; ?>
      <?php if (!$ro): ?>
        <form method="post" action="index.php?option=com_authors" enctype="multipart/form-data" class="fxv-ev-upload">
          <input type="hidden" name="option" value="com_authors">
          <input type="hidden" name="task" value="verification.uploadEvidence">
          <input type="hidden" name="id" value="<?php echo $vid; ?>">
          <input type="hidden" name="step" value="<?php echo $step; ?>">
          <input type="hidden" name="field_code" value="<?php echo $h($evField); ?>">
          <?php echo $token; ?>
          <input type="file" name="evidence_file[]" accept="image/*,application/pdf" multiple required>
          <button type="submit" class="btn btn-ghost">Upload files</button>
        </form>
        <p class="fxv-ev-hint">JPG, PNG, WEBP, GIF or PDF · max 8&nbsp;MB each, up to 20 files per upload. Select several at once — hold Ctrl/Cmd or Shift to multi-select. Uploading saves immediately and returns here.</p>
      <?php endif; ?>
    </div>
    <?php
};
?>

<div class="fecx wrap fxa-page fxv-wizard">

  <div class="fxa-head">
    <div class="eyebrow">FXShield verification · <?php echo $h($this->casino); ?><?php echo $ro ? ' · committed (read-only)' : ''; ?></div>
    <h1>Step <?php echo $step; ?> of <?php echo $total; ?> — <?php echo $h($stepNames[$step]); ?></h1>
  </div>

  <!-- Progress bar -->
  <ol class="fxv-progress">
    <?php for ($i = 1; $i <= $total; $i++):
      $cls = $i < $step ? 'done' : ($i === $step ? 'current' : 'todo');
      $url = 'index.php?option=com_authors&amp;view=verification&amp;id=' . $vid . '&amp;step=' . $i;
    ?>
      <li class="fxv-prog-<?php echo $cls; ?>">
        <?php if ($i < $step): ?><a href="<?php echo $url; ?>"><?php echo $i; ?></a>
        <?php else: ?><span><?php echo $i; ?></span><?php endif; ?>
        <em><?php echo $h($stepNames[$i]); ?></em>
      </li>
    <?php endfor; ?>
  </ol>

  <?php if ($this->error !== ''): ?>
    <div class="fxa-flash fxa-flash-error"><?php echo $h($this->error); ?></div>
  <?php endif; ?>

  <form method="post" action="<?php echo 'index.php?option=com_authors'; ?>" class="fxv-form">
    <input type="hidden" name="option" value="com_authors">
    <input type="hidden" name="task" value="verification.saveStep">
    <input type="hidden" name="id" value="<?php echo $vid; ?>">
    <input type="hidden" name="step" value="<?php echo $step; ?>">
    <input type="hidden" name="after" id="fxv-after" value="next">
    <?php echo $token; ?>

    <?php if ($stepInheritedCount > 0): ?>
      <div class="fxv-reconfirm-banner">
        <strong>Pre-filled from the last verification.</strong>
        <?php echo (int)$stepInheritedCount; ?> field<?php echo $stepInheritedCount > 1 ? 's on this step are' : ' on this step is'; ?>
        carried over and <em>not yet reconfirmed</em>. Check each against the live site, then save the step &mdash;
        saving marks the fields you reviewed as confirmed today. Only fields you confirm this session count as freshly verified.
      </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <p class="fxv-help">Read the live footer, then verify the licence number in the regulator&rsquo;s public registry. Assign the tier you can <em>verify</em>, not the one claimed.</p>
      <label class="fxv-field"><span class="fxv-label">Operating entity (exact footer text)</span>
        <input type="text" name="footer_entity" value="<?php echo $val('footer_entity'); ?>" <?php echo $disabled; ?>></label>
      <label class="fxv-field"><span class="fxv-label">Company registration number (blank if none shown)</span>
        <input type="text" name="entity_reg_number" value="<?php echo $val('entity_reg_number'); ?>" <?php echo $disabled; ?>></label>
      <label class="fxv-field"><span class="fxv-label">Licence number</span>
        <input type="text" name="licence_number" value="<?php echo $val('licence_number'); ?>" <?php echo $disabled; ?>></label>
      <label class="fxv-field"><span class="fxv-label">Regulator</span>
        <select name="regulator_key" id="fxv-regulator" <?php echo $disabled; ?>>
          <option value="">— select regulator —</option>
          <?php foreach (AuthorsModelVerification::regulatorTierMap() as $rk => $info): ?>
            <option value="<?php echo $h($rk); ?>" data-tier="<?php echo $h($info[1]); ?>"
              <?php echo $f['regulator_key'] === $rk ? 'selected' : ''; ?>>
              <?php echo $h($info[0]); ?> (<?php echo $h($info[1]); ?>)
            </option>
          <?php endforeach; ?>
        </select></label>
      <input type="hidden" name="regulator" id="fxv-regulator-label" value="<?php echo $val('regulator'); ?>">
      <label class="fxv-field"><span class="fxv-label">Licence verified in public registry?</span>
        <select name="licence_verified" id="fxv-licverif" <?php echo $disabled; ?>>
          <option value="">—</option>
          <option value="valid"     <?php echo $f['licence_verified']==='valid'?'selected':''; ?>>Present and valid</option>
          <option value="mismatch"  <?php echo $f['licence_verified']==='mismatch'?'selected':''; ?>>Present but different status</option>
          <option value="not_found" <?php echo $f['licence_verified']==='not_found'?'selected':''; ?>>Not found</option>
        </select></label>
      <fieldset class="fxv-tri"><legend>Footer entity &amp; licence consistent with the T&amp;C?</legend>
        <label><input type="radio" name="footer_consistent" value="yes" <?php echo $triChecked('footer_consistent','yes'); ?> <?php echo $disabled; ?>> Yes</label>
        <label><input type="radio" name="footer_consistent" value="no" <?php echo $triChecked('footer_consistent','no'); ?> <?php echo $disabled; ?>> No</label>
      </fieldset>
      <label class="fxv-field"><span class="fxv-label">Verified licence tier <span class="fxv-auto-note" id="fxv-tier-auto"></span></span>
        <select name="license_tier" id="fxv-tier" <?php echo $disabled; ?>>
          <?php foreach (array(''=>'—','T1'=>'T1','T2'=>'T2','T3'=>'T3','T4'=>'T4','T5'=>'T5') as $k=>$lbl): ?>
            <option value="<?php echo $k; ?>" <?php echo $f['license_tier']===$k?'selected':''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select></label>
      <div class="fxv-tier-warn" id="fxv-tier-warn" style="display:none;"></div>
      <label class="fxv-field"><span class="fxv-label">Evidence (URL / screenshot name)</span>
        <input type="text" name="ev_footer" value="<?php echo $val('ev_footer'); ?>" <?php echo $disabled; ?>></label>

      <?php
        // Additional licences (exhibition only — do NOT affect tier/score).
        $addl = isset($f['additional_licences']) && is_array($f['additional_licences']) ? $f['additional_licences'] : array();
        $regMap = AuthorsModelVerification::regulatorTierMap();
      ?>
      <div class="fxv-field fxv-addl">
        <span class="fxv-label">Additional licences <span class="fxv-auto-note">(shown on the public panel; do not change the score)</span></span>
        <div id="fxv-addl-rows">
          <?php
          $renderRow = function($idx, $rk, $num) use ($regMap, $h, $disabled, $ro) {
            ob_start(); ?>
            <div class="fxv-addl-row">
              <select name="additional_licences[<?php echo (int)$idx; ?>][regulator_key]" <?php echo $disabled; ?>>
                <option value="">— regulator —</option>
                <?php foreach ($regMap as $k => $info): ?>
                  <option value="<?php echo $h($k); ?>" <?php echo $rk === $k ? 'selected' : ''; ?>>
                    <?php echo $h($info[0]); ?> (<?php echo $h($info[1]); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="additional_licences[<?php echo (int)$idx; ?>][number]"
                     value="<?php echo $h($num); ?>" placeholder="Licence number (optional)" <?php echo $disabled; ?>>
              <?php if (!$ro): ?><button type="button" class="fxv-addl-del" aria-label="Remove">&times;</button><?php endif; ?>
            </div>
          <?php return ob_get_clean(); };

          if (!empty($addl)) {
            foreach ($addl as $i => $row) {
              $rk  = isset($row['regulator_key']) ? (string)$row['regulator_key'] : '';
              $num = isset($row['number']) ? (string)$row['number'] : '';
              echo $renderRow($i, $rk, $num);
            }
          }
          ?>
        </div>
        <?php if (!$ro): ?>
        <button type="button" id="fxv-addl-add" class="fxv-addl-add">+ Add licence</button>
        <template id="fxv-addl-tmpl"><?php echo str_replace(array("\n","  "), '', $renderRow('__IDX__', '', '')); ?></template>
        <script>
        (function(){
          var rows = document.getElementById('fxv-addl-rows'),
              addBtn = document.getElementById('fxv-addl-add'),
              tmpl = document.getElementById('fxv-addl-tmpl');
          if (!rows || !addBtn || !tmpl) return;
          var next = rows.querySelectorAll('.fxv-addl-row').length;
          addBtn.addEventListener('click', function(){
            var html = tmpl.innerHTML.replace(/__IDX__/g, String(next++));
            var wrap = document.createElement('div');
            wrap.innerHTML = html;
            rows.appendChild(wrap.firstElementChild);
          });
          rows.addEventListener('click', function(e){
            if (e.target && e.target.classList.contains('fxv-addl-del')) {
              var r = e.target.closest('.fxv-addl-row');
              if (r) r.remove();
            }
          });
        })();
        </script>
        <?php endif; ?>
      </div>

      <?php if (!$ro): ?>
      <script>
      (function(){
        var reg = document.getElementById('fxv-regulator'),
            tier = document.getElementById('fxv-tier'),
            label = document.getElementById('fxv-regulator-label'),
            auto = document.getElementById('fxv-tier-auto'),
            verif = document.getElementById('fxv-licverif'),
            warn = document.getElementById('fxv-tier-warn');
        if (!reg) return;
        function selText(sel){ return sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : ''; }
        function applyTier(userTriggered){
          var opt = reg.options[reg.selectedIndex];
          if (!opt || !opt.value) { auto.textContent=''; return; }
          var t = opt.getAttribute('data-tier') || '';
          // store a clean regulator label (strip the trailing " (Tx)")
          label.value = opt.text.replace(/\s*\([T][0-9]\)\s*$/, '').trim();
          if (userTriggered) {
            tier.value = t;
            auto.textContent = '· auto from regulator';
          }
          checkWarn();
        }
        function checkWarn(){
          var opt = reg.options[reg.selectedIndex];
          var implied = opt ? (opt.getAttribute('data-tier')||'') : '';
          warn.style.display='none'; warn.textContent='';
          // not_found in registry should drop the tier below the regulator's implied tier
          if (verif && verif.value === 'not_found' && implied && tier.value === implied && implied !== 'T5') {
            warn.style.display='block';
            warn.textContent = 'Licence not found in the registry — the verified tier should usually be lower than ' + implied + ' (treat as the next tier down, or T5 if there is no verifiable licence).';
          }
          // manual tier that's HIGHER than the regulator implies
          var rank={'T1':1,'T2':2,'T3':3,'T4':4,'T5':5};
          if (implied && tier.value && rank[tier.value] < rank[implied]) {
            warn.style.display='block';
            warn.textContent = 'Selected tier ' + tier.value + ' is higher than ' + implied + ', which is what this regulator supports. Override only if you have specific evidence.';
          }
        }
        reg.addEventListener('change', function(){ applyTier(true); });
        if (verif) verif.addEventListener('change', checkWarn);
        tier.addEventListener('change', checkWarn);
        applyTier(false); // on load: set label, run warning check, don't clobber a saved tier
      })();
      </script>
      <?php endif; ?>

    <?php elseif ($step === 2): ?>
      <p class="fxv-help">From a logged-in account: is each tool activatable <em>by the player</em> in settings, without contacting support?</p>
      <?php
      $triRow = function ($k, $label) use ($triChecked, $disabled, $h) {
        echo '<fieldset class="fxv-tri"><legend>' . $h($label) . '</legend>';
        echo '<label><input type="radio" name="'.$k.'" value="yes" '.$triChecked($k,'yes').' '.$disabled.'> Yes, self-service</label>';
        echo '<label><input type="radio" name="'.$k.'" value="no" '.$triChecked($k,'no').' '.$disabled.'> Support-only / absent</label>';
        echo '</fieldset>';
      };
      $triRow('rg_2fa', 'Two-factor authentication available?');
      $triRow('rg_limits', 'Deposit / loss limits, self-service?');
      $triRow('rg_selfexclusion', 'Self-exclusion, self-service?');
      ?>
      <label class="fxv-field"><span class="fxv-label">Evidence (dashboard screenshot)</span>
        <input type="text" name="ev_rg" value="<?php echo $val('ev_rg'); ?>" <?php echo $disabled; ?>></label>

    <?php elseif ($step === 3): ?>
      <p class="fxv-help">From the T&amp;C: is an independent ADR body named, and is it actually reachable?</p>
      <fieldset class="fxv-tri"><legend>Independent ADR named in the T&amp;C?</legend>
        <label><input type="radio" name="adr_named" value="yes" <?php echo $triChecked('adr_named','yes'); ?> <?php echo $disabled; ?>> Yes</label>
        <label><input type="radio" name="adr_named" value="no" <?php echo $triChecked('adr_named','no'); ?> <?php echo $disabled; ?>> No / internal arbitration only</label>
      </fieldset>
      <label class="fxv-field"><span class="fxv-label">ADR name (if any)</span>
        <input type="text" name="adr_name" value="<?php echo $val('adr_name'); ?>" <?php echo $disabled; ?>></label>
      <fieldset class="fxv-tri"><legend>Reachable when tested?</legend>
        <label><input type="radio" name="adr_reachable" value="yes" <?php echo $triChecked('adr_reachable','yes'); ?> <?php echo $disabled; ?>> Yes, reachable</label>
        <label><input type="radio" name="adr_reachable" value="no" <?php echo $triChecked('adr_reachable','no'); ?> <?php echo $disabled; ?>> Named but not tested</label>
      </fieldset>
      <label class="fxv-field"><span class="fxv-label">Evidence</span>
        <input type="text" name="ev_adr" value="<?php echo $val('ev_adr'); ?>" <?php echo $disabled; ?>></label>

    <?php elseif ($step === 4): ?>
      <p class="fxv-help">Tick only clauses <em>actually present</em> in the live T&amp;C. Each one costs 4 baseline points.</p>
      <?php
      $clauses = array(
        'tc_dormant'        => 'Confiscation of dormant-account balances (beyond a reasonable, gradual fee)',
        'tc_cashout_cap'    => 'Cashout cap that is a multiple of the deposit ("max 10x deposit")',
        'tc_irregular'      => 'Generic "irregular play" / "bonus abuse" clause with confiscation, no definition',
        'tc_maxbet_trap'    => 'Max-bet limit not enforced by software, applied afterwards via confiscation',
        'tc_monthly_cap'    => 'Monthly withdrawal cap below €5,000 (or equivalent)',
        'tc_unilateral'     => 'Terms changeable unilaterally "without notice", affecting active bonuses',
        'tc_math_advantage' => 'Vague ban on "mathematical advantage" / low-risk betting with confiscation',
        'tc_kyc_repeat'     => 'Repeatable KYC at discretion, no SLA, no cap on document cycles',
      );
      foreach ($clauses as $k => $label):
      ?>
        <label class="fxv-check">
          <input type="checkbox" name="<?php echo $k; ?>" value="1" <?php echo $chkChecked($k); ?> <?php echo $disabled; ?>>
          <span><?php echo $h($label); ?></span>
        </label>
      <?php endforeach; ?>
      <label class="fxv-field"><span class="fxv-label">Evidence (T&amp;C URL + clause screenshots)</span>
        <input type="text" name="ev_tc" value="<?php echo $val('ev_tc'); ?>" <?php echo $disabled; ?>></label>

    <?php elseif ($step === 5): ?>
      <p class="fxv-help">The key test. Deposit small, make it withdrawable, request the payout and <strong>note the time</strong>, then come back when it lands. You can save and exit here while it processes.</p>
      <div class="fxv-grid2">
        <label class="fxv-field"><span class="fxv-label">Deposit amount</span>
          <input type="text" name="deposit_amount" value="<?php echo $val('deposit_amount'); ?>" <?php echo $disabled; ?>></label>
        <label class="fxv-field"><span class="fxv-label">Deposit method</span>
          <input type="text" name="deposit_method" value="<?php echo $val('deposit_method'); ?>" <?php echo $disabled; ?>></label>
        <label class="fxv-field"><span class="fxv-label">Withdrawal amount</span>
          <input type="text" name="withdraw_amount" value="<?php echo $val('withdraw_amount'); ?>" <?php echo $disabled; ?>></label>
        <label class="fxv-field"><span class="fxv-label">Withdrawal method</span>
          <input type="text" name="withdraw_method" value="<?php echo $val('withdraw_method'); ?>" <?php echo $disabled; ?>></label>
        <label class="fxv-field"><span class="fxv-label">Declared time, worst case (hours)</span>
          <input type="number" step="0.5" name="declared_hours" value="<?php echo $val('declared_hours'); ?>" <?php echo $disabled; ?>></label>
        <span></span>
        <label class="fxv-field"><span class="fxv-label">Withdrawal requested at</span>
          <input type="datetime-local" name="requested_at" value="<?php echo $val('requested_at'); ?>" <?php echo $disabled; ?>></label>
        <label class="fxv-field"><span class="fxv-label">Credited at</span>
          <input type="datetime-local" name="credited_at" value="<?php echo $val('credited_at'); ?>" <?php echo $disabled; ?>></label>
      </div>
      <fieldset class="fxv-tri"><legend>KYC required before withdrawal?</legend>
        <label><input type="radio" name="kyc_required" value="yes" <?php echo $triChecked('kyc_required','yes'); ?> <?php echo $disabled; ?>> Yes</label>
        <label><input type="radio" name="kyc_required" value="no" <?php echo $triChecked('kyc_required','no'); ?> <?php echo $disabled; ?>> No</label>
      </fieldset>
      <div class="fxv-grid2">
        <label class="fxv-field"><span class="fxv-label">KYC document cycles</span>
          <input type="number" name="kyc_cycles" value="<?php echo $val('kyc_cycles'); ?>" <?php echo $disabled; ?>></label>
        <label class="fxv-field"><span class="fxv-label">KYC total duration (hours)</span>
          <input type="number" step="0.5" name="kyc_hours" value="<?php echo $val('kyc_hours'); ?>" <?php echo $disabled; ?>></label>
      </div>
      <label class="fxv-field"><span class="fxv-label">Outcome</span>
        <select name="payout_outcome" <?php echo $disabled; ?>>
          <option value="">—</option>
          <option value="paid_ok"   <?php echo $f['payout_outcome']==='paid_ok'?'selected':''; ?>>Paid without problems</option>
          <option value="paid_late" <?php echo $f['payout_outcome']==='paid_late'?'selected':''; ?>>Paid, but late</option>
          <option value="not_paid"  <?php echo $f['payout_outcome']==='not_paid'?'selected':''; ?>>Confiscated / not paid</option>
        </select></label>
      <label class="fxv-field"><span class="fxv-label">Evidence (request + credit screenshots, timestamps)</span>
        <input type="text" name="ev_payout" value="<?php echo $val('ev_payout'); ?>" <?php echo $disabled; ?>></label>
      <label class="fxv-field"><span class="fxv-label">Notes (anything anomalous)</span>
        <textarea name="notes" rows="3" <?php echo $disabled; ?>><?php echo $val('notes'); ?></textarea></label>
    <?php endif; ?>

    <?php if (!$ro): ?>
    <div class="fxv-actions">
      <?php if ($step > 1): ?>
        <button type="submit" class="btn btn-ghost" onclick="document.getElementById('fxv-after').value='prev';">&larr; Back</button>
      <?php endif; ?>
      <button type="submit" class="btn btn-ghost" onclick="document.getElementById('fxv-after').value='exit';">Save &amp; exit</button>
      <?php if ($step < $total): ?>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('fxv-after').value='next';">Save &amp; continue &rarr;</button>
      <?php else: ?>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('fxv-after').value='next';">Save &amp; review &rarr;</button>
      <?php endif; ?>
    </div>
    <?php else: ?>
      <div class="fxv-actions"><a class="btn btn-ghost" href="<?php echo 'index.php?option=com_authors&amp;view=verification'; ?>">&larr; Back to list</a></div>
    <?php endif; ?>
  </form>

  <?php if ($step >= 1 && $step <= 5) $renderEvidenceBox(); ?>

</div>
