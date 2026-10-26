<?php
defined('_JEXEC') or die;

/**
 * Commit preview (default_preview.php) — step 6, read-only.
 * Shows exactly what committing this verification will write. NO write happens
 * here; the commit button (B3.2) is intentionally not wired yet.
 * Data: $this->preview, $this->vid, $this->casino, $this->committed.
 */
$p   = is_array($this->preview) ? $this->preview : array();
$vid = (int)$this->vid;
$h   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$total = (int)$this->total;

$baseline = isset($p['baseline']) ? $p['baseline'] : array();
$B        = isset($p['baseline_B']) ? (int)$p['baseline_B'] : 0;
$event    = isset($p['event']) ? $p['event'] : null;
$kyc      = isset($p['kyc_event']) ? $p['kyc_event'] : null;
$payout   = isset($p['payout']) ? $p['payout'] : null;
$conf     = isset($p['confidence']) ? $p['confidence'] : array();
$warnings = isset($p['warnings']) ? $p['warnings'] : array();
$evidence = isset($this->evidence) && is_array($this->evidence) ? $this->evidence : array();

require_once JPATH_COMPONENT . '/models/verificationevidence.php';
$stepNames6 = array('ev_footer'=>'Footer & licence','ev_rg'=>'RG tools','ev_adr'=>'ADR','ev_tc'=>'T&C clauses','ev_payout'=>'Test withdrawal','ev_kyc'=>'KYC');

$fieldLabel = array(
  'license_tier'=>'Licence tier', 'adr'=>'ADR', 'entity_registered'=>'Entity registered',
  'license_footer'=>'Footer consistent', 'rg_2fa'=>'2FA', 'rg_limits'=>'Deposit/loss limits',
  'rg_selfexclusion'=>'Self-exclusion', 'tc_fairness'=>'T&C fairness',
);
?>

<div class="fecx wrap fxa-page fxv-wizard">

  <div class="fxa-head">
    <div class="eyebrow">FXShield verification · <?php echo $h($this->casino); ?></div>
    <h1>Step 6 of 6 — Review &amp; commit</h1>
  </div>

  <!-- Progress bar (all data steps done, on the review step) -->
  <ol class="fxv-progress">
    <?php
    $names = array(1=>'Footer & licence',2=>'RG tools',3=>'ADR',4=>'T&C clauses',5=>'Test withdrawal',6=>'Review');
    for ($i = 1; $i <= 6; $i++):
      $cls = $i < 6 ? 'done' : 'current';
      $url = 'index.php?option=com_authors&amp;view=verification&amp;id=' . $vid . '&amp;step=' . $i;
    ?>
      <li class="fxv-prog-<?php echo $cls; ?>">
        <?php if ($i < 6): ?><a href="<?php echo $url; ?>"><?php echo $i; ?></a>
        <?php else: ?><span><?php echo $i; ?></span><?php endif; ?>
        <em><?php echo $h($names[$i]); ?></em>
      </li>
    <?php endfor; ?>
  </ol>

  <p class="fxv-help">This is what committing will write. Nothing has been saved
  to the score yet — review the numbers against your notes, then go back to fix
  any step.</p>

  <?php if (trim((string)$this->error) !== ''): ?>
    <div class="fxa-flash fxa-flash-error"><?php echo $h($this->error); ?></div>
  <?php endif; ?>

  <?php if (!empty($warnings)): ?>
    <div class="fxa-flash fxa-flash-warn">
      <strong>Notes:</strong>
      <ul style="margin:6px 0 0 18px;">
        <?php foreach ($warnings as $w): ?><li><?php echo $h($w); ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- Baseline -->
  <h2 class="fxv-h2">Baseline &rarr; <?php echo $B; ?> / 100</h2>
  <div class="fxa-tablewrap">
    <table class="fxa-table">
      <thead><tr><th>Field</th><th>Verified value</th><th class="num">Points</th></tr></thead>
      <tbody>
        <?php foreach ($baseline as $row): ?>
          <tr>
            <td class="fxa-name"><?php echo $h(isset($fieldLabel[$row['field_code']]) ? $fieldLabel[$row['field_code']] : $row['field_code']); ?></td>
            <td><?php echo $h($row['value']); ?></td>
            <td class="num"><?php echo (int)$row['points']; ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="font-weight:700;border-top:2px solid var(--rule-2,#ddd);">
          <td colspan="2">Baseline total</td><td class="num"><?php echo $B; ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <p class="fxv-help">All these fields will be marked <em>verified first-party</em> with today&rsquo;s date.</p>

  <?php
    // Additional licences (exhibition only — shown so the editor confirms them
    // before commit; they do NOT change the baseline or score).
    $addlPv = isset($p['fields']['additional_licences']) && is_array($p['fields']['additional_licences'])
            ? $p['fields']['additional_licences'] : array();
    if (empty($addlPv) && isset($fields['additional_licences']) && is_array($fields['additional_licences'])) {
      $addlPv = $fields['additional_licences'];
    }
    if (!empty($addlPv)):
      $regMapPv = AuthorsModelVerification::regulatorTierMap();
  ?>
  <div class="fxv-addl-preview">
    <h3 class="fxv-h3" style="margin:8px 0 4px;">Additional licences <span class="fxv-help" style="font-weight:400;">(shown on the public panel; no score effect)</span></h3>
    <ul class="fxv-addl-list">
      <?php foreach ($addlPv as $row):
        if (!is_array($row)) continue;
        $rk = isset($row['regulator_key']) ? (string)$row['regulator_key'] : '';
        $num = isset($row['number']) ? (string)$row['number'] : '';
        if ($rk === '' && $num === '') continue;
        $lab = ($rk !== '' && isset($regMapPv[$rk])) ? $regMapPv[$rk][0] . ' (' . $regMapPv[$rk][1] . ')' : $rk;
      ?>
        <li><?php echo $h($lab); ?><?php echo $num !== '' ? ' — ' . $h($num) : ''; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <!-- Events -->
  <h2 class="fxv-h2">Ledger event<?php echo ($event && $kyc) ? 's' : ''; ?></h2>
  <?php if (!$event && !$kyc): ?>
    <p class="fxa-dash">No payout event (incomplete test withdrawal). The verification still raises confidence.</p>
  <?php else: ?>
    <div class="fxa-tablewrap">
      <table class="fxa-table">
        <thead><tr><th>Event</th><th class="num">Weight</th><th>Decay</th><th>Note</th></tr></thead>
        <tbody>
          <?php if ($event): ?>
            <tr><td><span class="fxa-cap"><?php echo $h($event['code']); ?></span></td>
              <td class="num"><?php echo ($event['weight']>0?'+':'').$event['weight']; ?></td>
              <td><?php echo $h($event['decay']); ?></td>
              <td><?php echo $h($event['note']); ?></td></tr>
          <?php endif; ?>
          <?php if ($kyc): ?>
            <tr><td><span class="fxa-cap"><?php echo $h($kyc['code']); ?></span></td>
              <td class="num"><?php echo $kyc['weight']; ?></td>
              <td><?php echo $h($kyc['decay']); ?></td>
              <td><?php echo $h($kyc['note']); ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- PRG -->
  <?php if ($payout): ?>
    <h2 class="fxv-h2">Payout Reality Gap</h2>
    <p class="fxv-prg-line">
      Declares: <b><?php echo $h(rtrim(rtrim(number_format($payout['declared_hours'],1),'0'),'.')); ?>h</b> ·
      Measured: <b><?php echo $h(rtrim(rtrim(number_format($payout['measured_hours'],1),'0'),'.')); ?>h</b> ·
      <b>PRG <?php echo $h(rtrim(rtrim(number_format($payout['prg'],1),'0'),'.')); ?>&times;</b>
    </p>
  <?php endif; ?>

  <!-- Evidence gallery -->
  <h2 class="fxv-h2">Evidence</h2>
  <?php
  $totalFiles = 0; foreach ($evidence as $list) $totalFiles += count($list);
  if ($totalFiles === 0): ?>
    <p class="fxa-dash">No screenshots attached. Evidence is optional, but a published score is far stronger with the registry/footer shots behind it.</p>
  <?php else: ?>
    <div class="fxv-gallery">
      <?php foreach ($evidence as $fieldCode => $files):
        foreach ($files as $file):
          $isPublic = ($file['visibility'] === 'public');
          $url = AuthorsModelVerificationEvidence::publicUrl($file);
          $isImg = (strpos($file['mime_type'], 'image/') === 0);
          $label = isset($stepNames6[$fieldCode]) ? $stepNames6[$fieldCode] : $fieldCode;
      ?>
        <figure class="fxv-ev-card">
          <?php if ($isPublic && $isImg && $url): ?>
            <a href="<?php echo $h($url); ?>" target="_blank" rel="noopener">
              <img src="<?php echo $h($url); ?>" alt="<?php echo $h($file['original_name']); ?>" loading="lazy">
            </a>
          <?php elseif ($isPublic && $url): ?>
            <a class="fxv-ev-pdf" href="<?php echo $h($url); ?>" target="_blank" rel="noopener">PDF</a>
          <?php else: ?>
            <div class="fxv-ev-locked" title="Private — not published">&#128274;</div>
          <?php endif; ?>
          <figcaption>
            <span class="fxv-ev-caplabel"><?php echo $h($label); ?></span>
            <span class="fxv-ev-tag <?php echo $isPublic ? 'fxv-ev-public' : 'fxv-ev-private'; ?>">
              <?php echo $isPublic ? 'public' : 'private'; ?>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; endforeach; ?>
    </div>
    <p class="fxv-help">Public files will appear as proof on the casino&rsquo;s FXShield page. Private files (withdrawal, KYC) stay internal — the measured payout figure is what gets published, not the screenshot.</p>
  <?php endif; ?>

  <!-- Confidence -->
  <h2 class="fxv-h2">Confidence</h2>
  <p class="fxv-help"><?php echo $h(isset($conf['note']) ? $conf['note'] : ''); ?></p>

  <!-- Actions -->
  <?php if ($this->committed): ?>
    <div class="fxv-actions">
      <a class="btn btn-ghost" href="index.php?option=com_authors&amp;view=verification">&larr; Back to list</a>
      <span class="fxv-commit-note">This verification is committed. Its data is in the baseline and ledger.</span>
    </div>
  <?php else: ?>
    <form method="post" action="<?php echo 'index.php?option=com_authors'; ?>" class="fxv-commit-form"
          onsubmit="var b=document.getElementById('fxv-commit-btn'); b.disabled=true; b.textContent='Committing…'; return true;">
      <input type="hidden" name="option" value="com_authors">
      <input type="hidden" name="task" value="verification.commit">
      <input type="hidden" name="id" value="<?php echo $vid; ?>">
      <?php echo JHtml::_('form.token'); ?>
      <div class="fxv-actions">
        <a class="btn btn-ghost" href="index.php?option=com_authors&amp;view=verification&amp;id=<?php echo $vid; ?>&amp;step=5">&larr; Back to edit</a>
        <button type="submit" id="fxv-commit-btn" class="btn btn-primary"
          onclick="return confirm('Commit this verification? It writes the baseline and ledger, marks the verification permanent, and can\'t be edited afterwards. The score updates on the next recalculation.');">
          Commit verification
        </button>
        <span class="fxv-commit-note">Writes baseline + ledger. Score updates on next recalc.</span>
      </div>
    </form>
  <?php endif; ?>

</div>
