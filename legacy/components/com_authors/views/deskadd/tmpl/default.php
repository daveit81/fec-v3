<?php
/**
 * Desk-add form template. Live score preview in JS mirrors the engine math;
 * the real score is written by safety-engine.php at the next recalc.
 */
defined('_JEXEC') or die;

$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$token = JHtml::_('form.token');

// --- Edit-mode state (set by the view when ?casino_id=N is present) ---
$editId   = isset($this->editId) ? (int) $this->editId : 0;
$bl       = (isset($this->baseline) && is_array($this->baseline)) ? $this->baseline : array();
$events   = (isset($this->events) && is_array($this->events)) ? $this->events : array();
$vcodes   = (isset($this->verifiedCodes) && is_array($this->verifiedCodes)) ? $this->verifiedCodes : array();
$editMode = ($editId > 0 && count($bl) > 0);

$blVal = function ($code, $key, $def) use ($bl) {
    return (isset($bl[$code]) && isset($bl[$code][$key])) ? $bl[$code][$key] : $def;
};
$blYes = function ($code) use ($blVal) { return $blVal($code, 'field_value', 'no') === 'yes'; };

$tierCode  = (string) $blVal('license_tier', 'field_value', '');
$adrPts    = (int) $blVal('adr', 'points', 0);
$tcClauses = (int) $blVal('tc_fairness', 'field_value', 0);
if ($tcClauses < 0) { $tcClauses = 0; }
if ($tcClauses > 8) { $tcClauses = 8; }
?>
<style>
.da-wrap { max-width: 720px; margin: 24px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color:#1a1a1a; }
.da-eyebrow { font-family: ui-monospace, monospace; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#0b6b62; margin-bottom:4px; }
.da-h2 { font-size:20px; font-weight:600; margin:0 0 4px; }
.da-sub { font-size:13px; color:#5a5347; margin:0 0 18px; line-height:1.5; }
.da-cols { display:flex; gap:20px; align-items:flex-start; }
.da-main { flex:1; min-width:0; }
.da-side { flex:0 0 210px; }
.da-grp { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#0b6b62; margin:18px 0 8px; border-bottom:1px solid #e3ded2; padding-bottom:4px; }
.da-field { margin-bottom:12px; }
.da-field label.lbl { display:block; font-size:12px; color:#5a5347; margin-bottom:4px; }
.da-field select, .da-field input[type=text], .da-field input[type=date] { width:100%; padding:8px; border:1px solid #d9d2c2; border-radius:6px; font-size:14px; box-sizing:border-box; }
.da-check { display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:6px; }
.da-muted { color:#8a8275; }
.da-card { background:#f3f0e8; border-radius:12px; padding:18px; text-align:center; position:sticky; top:20px; }
.da-card .k { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#5a5347; }
.da-score { font-size:42px; font-weight:600; line-height:1.1; margin:6px 0; }
.da-tier { font-size:14px; font-weight:600; }
.da-badge { display:inline-block; margin-top:8px; font-size:11px; background:#e7e0d0; color:#5a4a1a; padding:3px 10px; border-radius:100px; }
.da-cap { font-size:11px; color:#9a2828; margin-top:10px; min-height:14px; }
.da-sum { border-top:1px solid #ddd6c6; margin-top:14px; padding-top:12px; font-size:12px; color:#5a5347; text-align:left; }
.da-save { width:100%; margin-top:10px; background:#14110d; color:#fff; border:none; padding:11px; border-radius:8px; font-size:14px; cursor:pointer; }
.da-recalc { display:block; margin-top:9px; font-size:12px; color:#0b6b62; text-decoration:underline; text-align:center; }
.da-event { margin-top:10px; border:1px solid #e3ded2; border-radius:8px; padding:0 14px; }
.da-event summary { cursor:pointer; padding:12px 0; font-size:13px; font-weight:500; }
.da-event[open] { padding-bottom:14px; }
.da-evlist { border-bottom:1px solid #e3ded2; margin-bottom:12px; padding-bottom:8px; }
.da-evrow { font-size:12px; margin-bottom:6px; }
.da-evrow .d { font-family: ui-monospace, monospace; color:#5a5347; margin-right:6px; }
.da-evrow .c { font-weight:600; }
.da-evrow .w { color:#9a2828; margin-left:6px; }
.da-evrow .w.pos { color:#1c7a4a; }
.da-evrow .n { color:#8a8275; margin-top:1px; }
.da-editnote { background:#eef4f3; border:1px solid #cfe0dd; border-radius:8px; padding:8px 12px; font-size:12px; color:#0b4a44; margin:0 0 14px; }
</style>

<div class="da-wrap">
  <div class="da-eyebrow">FXShield admin</div>
  <div class="da-h2"><?php echo $editMode ? 'Edit desk baseline' : 'Add desk casino'; ?></div>
  <?php if ($editMode): ?>
  <div class="da-editnote"><strong><?php echo $h($this->editTitle !== '' ? $this->editTitle : 'Casino'); ?></strong> (#<?php echo $editId; ?>) &mdash; saved desk values loaded.
    Re-saving updates the baseline; first-party verified fields keep their verified flag<?php echo count($vcodes) ? ' (' . $h(implode(', ', $vcodes)) . ')' : ''; ?>.</div>
  <?php endif; ?>
  <p class="da-sub">A desk estimate is marked <strong>confidence D</strong> until a first-party verification upgrades it.
     Fields left at &ldquo;not assessed&rdquo; score 0 &mdash; never assumed. The score is written at the next recalc
     (weekly cron, or run <code>safety-engine.php --casino=ID</code>).</p>

  <form method="post" action="index.php?option=com_authors" class="da-cols">
    <input type="hidden" name="option" value="com_authors">
    <input type="hidden" name="task" value="deskadd.save">
    <?php echo $token; ?>

    <div class="da-main">
      <div class="da-field">
        <label class="lbl">Casino (jReviews listing) <span class="da-muted">&mdash; picking one loads its saved baseline</span></label>
        <select name="casino_id" required onchange="daLoad(this.value)">
          <option value="">&mdash; select &mdash;</option>
          <?php foreach ($this->candidates as $c): ?>
            <option value="<?php echo (int)$c['casino_id']; ?>"<?php echo ((int)$c['casino_id'] === $editId) ? ' selected' : ''; ?>>
              <?php echo $h($c['title']); ?> (#<?php echo (int)$c['casino_id']; ?>)<?php echo ((int)$c['has_baseline'] > 0) ? ' — has baseline' : ''; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="da-grp">Licence &amp; recourse</div>
      <div class="da-field">
        <label class="lbl">Licence tier <span class="da-muted">(max 32)</span></label>
        <select name="license_tier" id="tier" onchange="daRecalc()">
          <option value="0"<?php echo ($tierCode === '') ? ' selected' : ''; ?>>Not assessed (0)</option>
          <option value="32"<?php echo ($tierCode === 'T1') ? ' selected' : ''; ?>>T1 &mdash; ADM/UKGC/MGA/SGA/DGOJ/ANJ/KSA/AGCO (32)</option>
          <option value="26"<?php echo ($tierCode === 'T2') ? ' selected' : ''; ?>>T2 &mdash; Gibraltar / Isle of Man / Alderney (26)</option>
          <option value="18"<?php echo ($tierCode === 'T3') ? ' selected' : ''; ?>>T3 &mdash; Cura&ccedil;ao GCB new / Kahnawake (18)</option>
          <option value="8"<?php echo ($tierCode === 'T4') ? ' selected' : ''; ?>>T4 &mdash; Cura&ccedil;ao legacy / Anjouan / Tobique (8)</option>
          <option value="0"<?php echo ($tierCode === 'T5') ? ' selected' : ''; ?>>T5 &mdash; none / registration only (0)</option>
        </select>
      </div>
      <div class="da-field">
        <label class="lbl">ADR <span class="da-muted">(max 8)</span></label>
        <select name="adr" id="adr" onchange="daRecalc()">
          <option value="0"<?php echo ($adrPts === 0) ? ' selected' : ''; ?>>Absent (0)</option>
          <option value="4"<?php echo ($adrPts === 4) ? ' selected' : ''; ?>>Named, not reachable (4)</option>
          <option value="8"<?php echo ($adrPts === 8) ? ' selected' : ''; ?>>Named &amp; reachable (8)</option>
        </select>
      </div>

      <div class="da-grp">Entity transparency (max 12)</div>
      <label class="da-check"><input type="checkbox" name="entity_registered" value="1" id="e1" onchange="daRecalc()"<?php echo $blYes('entity_registered') ? ' checked' : ''; ?>> Operator identified w/ company reg number (4)</label>
      <label class="da-check"><input type="checkbox" name="license_footer" value="1" id="e2" onchange="daRecalc()"<?php echo $blYes('license_footer') ? ' checked' : ''; ?>> Licence number in footer, consistent (4)</label>
      <label class="da-check"><input type="checkbox" name="no_fragmentation" value="1" id="e3" onchange="daRecalc()"<?php echo $blYes('no_fragmentation') ? ' checked' : ''; ?>> No brand fragmentation (4)</label>

      <div class="da-grp">Player protection (max 16)</div>
      <label class="da-check"><input type="checkbox" name="rg_2fa" value="1" id="p1" onchange="daRecalc()"<?php echo $blYes('rg_2fa') ? ' checked' : ''; ?>> 2FA available (4)</label>
      <label class="da-check"><input type="checkbox" name="rg_limits" value="1" id="p2" onchange="daRecalc()"<?php echo $blYes('rg_limits') ? ' checked' : ''; ?>> Self-service deposit/loss limits (6)</label>
      <label class="da-check"><input type="checkbox" name="rg_selfexclusion" value="1" id="p3" onchange="daRecalc()"<?php echo $blYes('rg_selfexclusion') ? ' checked' : ''; ?>> Self-service self-exclusion (6)</label>

      <div class="da-grp">T&amp;C fairness (max 32 &mdash; minus 4 per predatory clause)</div>
      <div class="da-field">
        <label class="lbl">Predatory clauses found (0&ndash;8)</label>
        <input type="range" name="tc_clauses" id="tc" min="0" max="8" step="1" value="<?php echo $tcClauses; ?>" oninput="daRecalc()" style="width:100%;">
        <div class="da-muted" style="font-size:12px;"><span id="tcout">0</span> clauses &rarr; <span id="tcpts">32</span> pts</div>
      </div>

      <details class="da-event"<?php echo (count($events) > 0) ? ' open' : ''; ?>>
        <summary>Add an event (optional)<?php echo (count($events) > 0) ? ' &mdash; ' . count($events) . ' on ledger' : ''; ?></summary>
        <?php if (count($events) > 0): ?>
        <div class="da-evlist">
          <?php foreach ($events as $ev): ?>
          <div class="da-evrow">
            <span class="d"><?php echo $h($ev['event_date']); ?></span>
            <span class="c"><?php echo $h($ev['event_code']); ?></span>
            <span class="w<?php echo ((float)$ev['base_weight'] > 0) ? ' pos' : ''; ?>"><?php echo ((float)$ev['base_weight'] > 0 ? '+' : '') . rtrim(rtrim((string)$ev['base_weight'], '0'), '.'); ?></span>
            <?php if ($ev['evidence_note'] !== null && $ev['evidence_note'] !== ''): ?><div class="n"><?php echo $h($ev['evidence_note']); ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="da-sub" style="margin:0 0 10px;">For a fact you already know &mdash; a sanction, a blacklisting, a licence change.
           Leave blank for a clean new casino. Duplicate sanctions on the same date are blocked automatically.</p>
        <div class="da-field">
          <label class="lbl">Event</label>
          <select name="event_code">
            <option value="">&mdash; none &mdash;</option>
            <option value="WD_BLACKLIST">Watchdog blacklisting (&minus;20)</option>
            <option value="LIC_LAPSED">Licence lapsed/revoked (&minus;15)</option>
            <option value="LIC_CHANGED">Licence changed / migrated (&minus;5)</option>
            <option value="WD_WARNING">Regulator warning / sanction (&minus;10)</option>
            <option value="WD_UNFAIR_TC">Unfair T&amp;C ruling (&minus;8)</option>
            <option value="COMPLAINT_PATTERN">Complaint pattern (&minus;8)</option>
            <option value="LIC_UPGRADED">Licence upgraded (+5)</option>
            <option value="WD_DELISTED">Removed from a blacklist (+2)</option>
          </select>
        </div>
        <div class="da-field">
          <label class="lbl">Event date</label>
          <input type="date" name="event_date">
        </div>
        <div class="da-field">
          <label class="lbl">Note (source / detail)</label>
          <input type="text" name="event_note" maxlength="255" placeholder="e.g. UKGC £650k penalty, AML failings">
        </div>
      </details>
    </div>

    <div class="da-side">
      <div class="da-card">
        <div class="k">Estimated index</div>
        <div class="da-score" id="score">&mdash;</div>
        <div class="da-tier" id="tierlbl">&mdash;</div>
        <div class="da-badge">confidence D &middot; desk</div>
        <div class="da-cap" id="capnote"></div>
        <div class="da-sum">Baseline sum: <strong id="rawsum">&mdash;</strong>/100</div>
        <button type="submit" class="da-save"><?php echo $editMode ? 'Update desk baseline' : 'Save desk baseline'; ?></button>
        <?php if ($editMode): ?>
        <a class="da-recalc"
           href="<?php echo 'index.php?option=com_authors&amp;task=fxshieldadmin.recalc&amp;casino_id=' . $editId . '&amp;return=deskadd&amp;' . JSession::getFormToken() . '=1'; ?>">Recalc score now &mdash; runs the engine for this casino</a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<script>
function daLoad(v){
  if(!v){ return; }
  window.location = 'index.php?option=com_authors&view=deskadd&tmpl=component&casino_id=' + encodeURIComponent(v);
}
function daRecalc(){
  var tier=+document.getElementById('tier').value;
  var adr=+document.getElementById('adr').value;
  var e=(document.getElementById('e1').checked?4:0)+(document.getElementById('e2').checked?4:0)+(document.getElementById('e3').checked?4:0);
  var p=(document.getElementById('p1').checked?4:0)+(document.getElementById('p2').checked?6:0)+(document.getElementById('p3').checked?6:0);
  var tc=+document.getElementById('tc').value;
  var tcpts=Math.max(0,32-4*tc);
  document.getElementById('tcout').textContent=tc;
  document.getElementById('tcpts').textContent=tcpts;
  var sum=tier+adr+e+p+tcpts;
  document.getElementById('rawsum').textContent=sum;
  var score=sum, cap='';
  if(tier===8 && score>55){score=55; cap='Capped at 55 (T4 licence)';}
  if(tier===0 && score>49){score=49; cap='Capped at 49 (no verified licence)';}
  document.getElementById('score').textContent=score;
  var t,c;
  if(score>=80){t='Reliable';c='#1c7a4a';}
  else if(score>=60){t='Solid';c='#5d7a1f';}
  else if(score>=40){t='Caution';c='#a8631c';}
  else if(score>=20){t='High risk';c='#b4541a';}
  else {t='Critical';c='#9a2828';}
  var tl=document.getElementById('tierlbl'); tl.textContent=t; tl.style.color=c;
  document.getElementById('capnote').textContent=cap;
}
daRecalc();
</script>
