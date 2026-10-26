<?php
/**
 * Default template for the FXShield Verification Report.
 * Sober, institutional, print-ready. Self-contained styles + @media print.
 */
defined('_JEXEC') or die;

$d        = $this->data;
$score    = $d['score'];
$events   = $d['events'];
$payout   = $d['payout'];
$h        = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$si    = ($score['si_score'] !== null) ? (int)$score['si_score'] : null;
$tier  = (string)$score['si_tier'];
$conf  = strtoupper((string)$score['si_confidence']);
$caps  = (string)$score['si_caps_applied'];
$prg   = ($score['prg_ratio'] !== null && $score['prg_ratio'] !== '') ? (float)$score['prg_ratio'] : null;

// System/technical events are timeline annotations (weight 0), not facts about
// the casino — they must not appear in the public report. Filter them out so
// the "Recorded events" section shows only substantive events (or hides if none).
$systemCodes = array('SYSTEM_RECALC');
$displayEvents = array();
foreach ($events as $e) {
    if (in_array($e['event_code'], $systemCodes, true)) continue;
    $displayEvents[] = $e;
}

// Verification date = most recent valid verified_at across baseline fields.
$vDate = '';
foreach ($d['baseline'] as $bf) {
    $va = isset($bf['verified_at']) ? (string)$bf['verified_at'] : '';
    if ($va === '' || strpos($va, '0000-00-00') === 0) continue;
    if ($va > $vDate) $vDate = $va;
}
$vDateFmt = $vDate !== '' ? JHtml::_('date', $vDate, 'd M Y') : '&mdash;';

// Tier colour
$tierColor = '#5a5347';
if ($si !== null) {
    if ($si >= 80) $tierColor = '#1c7a4a';
    elseif ($si >= 60) $tierColor = '#5d7a1f';
    elseif ($si >= 40) $tierColor = '#a8631c';
    elseif ($si >= 20) $tierColor = '#b4541a';
    else $tierColor = '#9a2828';
}

// Ring geometry (bicolor: green achieved, amber remainder)
$r = 46; $circ = 2 * M_PI * $r;
$frac = ($si !== null) ? max(0, min(100, $si)) / 100.0 : 0;
$green = round($circ * $frac, 1);
$amber = round($circ * (1 - $frac), 1);

// Readable event labels
$evLabels = array(
    'ST_PAYOUT_FAST'      => 'Fast staff payout test',
    'ST_PAYOUT_OK'        => 'Staff payout test (on time)',
    'ST_PAYOUT_SLOW'      => 'Slow staff payout test',
    'ST_KYC_OBSTRUCTION'  => 'KYC obstruction',
    'WD_WARNING'          => 'Regulator warning / sanction',
    'WD_BLACKLIST'        => 'Watchdog blacklisting',
    'WD_UNFAIR_TC'        => 'Unfair T&C ruling',
    'WD_DELISTED'         => 'Regulator delisting',
    'LIC_LAPSED'          => 'Licence lapsed',
    'LIC_UPGRADED'        => 'Licence upgraded',
    'GRP_CONTAGION'       => 'Group contagion (sister brand)',
    'COMPLAINT_PATTERN'   => 'Complaint pattern',
);

// PRG good/bad
$gapColor = ($prg !== null && $prg > 1.05) ? '#9a2828' : '#1c7a4a';
$fmtH = function ($v) { return rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . 'h'; };
$fmtN = function ($v) { return rtrim(rtrim(number_format((float)$v, 1), '0'), '.'); };
?>
<style>
.fxr-wrap { max-width: 720px; margin: 24px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1a1a1a; }
.fxr-doc { background:#fff; border:1px solid #ddd; border-radius:10px; overflow:hidden; }
.fxr-head { border-bottom:2px solid #14110d; padding:24px 32px 18px; display:flex; justify-content:space-between; align-items:flex-start; }
.fxr-eyebrow { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#0b6b62; margin-bottom:6px; }
.fxr-casino { font-size:22px; font-weight:600; color:#14110d; }
.fxr-issuer { text-align:right; font-size:11px; color:#8a8275; line-height:1.6; }
.fxr-section { padding:20px 32px; border-bottom:1px solid #eee; }
.fxr-score-row { display:flex; gap:30px; align-items:center; }
.fxr-ring { width:110px; height:110px; flex:0 0 auto; position:relative; }
.fxr-ring-num { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.fxr-ring-num b { font-size:30px; font-weight:600; color:#14110d; line-height:1; }
.fxr-ring-num span { font-size:9px; letter-spacing:.5px; color:#8a8275; margin-top:2px; }
.fxr-kv { width:100%; font-size:14px; border-collapse:collapse; }
.fxr-kv td { padding:6px 0; }
.fxr-kv td:first-child { color:#5a5347; }
.fxr-kv td:last-child { text-align:right; font-weight:500; }
.fxr-kv tr + tr td { border-top:1px solid #f0f0f0; }
.fxr-label { font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#8a8275; margin-bottom:12px; }
.fxr-prg { display:flex; }
.fxr-prg-cell { flex:1; }
.fxr-prg-cell + .fxr-prg-cell { padding-left:18px; border-left:1px solid #eee; }
.fxr-prg-cell .k { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#8a8275; }
.fxr-prg-cell .v { font-size:18px; font-weight:500; margin-top:2px; }
.fxr-ev { width:100%; font-size:13px; border-collapse:collapse; }
.fxr-ev td { padding:5px 0; }
.fxr-ev tr + tr td { border-top:1px solid #f5f5f5; }
.fxr-ev .date { color:#5a5347; white-space:nowrap; width:90px; }
.fxr-ev .amt { text-align:right; font-weight:500; white-space:nowrap; width:50px; }
.fxr-foot { padding:18px 32px; display:flex; justify-content:space-between; align-items:center; background:#faf8f3; font-size:11px; color:#8a8275; line-height:1.6; }
.fxr-actions { max-width:720px; margin:14px auto 0; text-align:right; }
.fxr-print-btn { font-size:13px; padding:8px 16px; border:1px solid #14110d; background:#14110d; color:#fff; border-radius:8px; cursor:pointer; }
@media print {
  .fxr-actions { display:none; }
  .fxr-wrap { margin:0; max-width:100%; }
  .fxr-doc { border:none; border-radius:0; }
  body { background:#fff; }
}
</style>

<div class="fxr-wrap">
  <div class="fxr-doc">

    <div class="fxr-head">
      <div>
        <div class="fxr-eyebrow">FXShield&trade; verification report</div>
        <div class="fxr-casino"><?php echo $h($d['title']); ?></div>
      </div>
      <div class="fxr-issuer">
        FreeExtraChips<br>
        Report #FX-<?php echo (int)$d['casino_id']; ?>
      </div>
    </div>

    <div class="fxr-section">
      <div class="fxr-score-row">
        <div class="fxr-ring">
          <svg viewBox="0 0 110 110" width="110" height="110">
            <circle cx="55" cy="55" r="<?php echo $r; ?>" fill="none" stroke="#e7e0d0" stroke-width="11"></circle>
            <circle cx="55" cy="55" r="<?php echo $r; ?>" fill="none" stroke="#a8631c" stroke-width="11"
                    stroke-dasharray="<?php echo $amber; ?> <?php echo $green; ?>" stroke-dashoffset="<?php echo -$green; ?>"
                    transform="rotate(-90 55 55)"></circle>
            <circle cx="55" cy="55" r="<?php echo $r; ?>" fill="none" stroke="#1c7a4a" stroke-width="11" stroke-linecap="round"
                    stroke-dasharray="<?php echo $green; ?> <?php echo $amber; ?>" transform="rotate(-90 55 55)"></circle>
          </svg>
          <div class="fxr-ring-num"><b><?php echo $si !== null ? $si : '&mdash;'; ?></b><span>/ 100</span></div>
        </div>
        <div style="flex:1;">
          <table class="fxr-kv">
            <tr><td>Safety tier</td><td style="color:<?php echo $tierColor; ?>"><?php echo $h($tier !== '' ? $tier : 'Not rated'); ?></td></tr>
            <tr><td>Confidence grade</td><td><?php echo $h($conf); ?></td></tr>
            <?php if ($caps !== ''): ?>
            <tr><td>Licence cap</td><td style="color:#9a2828"><?php echo $h($caps); ?></td></tr>
            <?php endif; ?>
            <tr><td>Verification date</td><td><?php echo $vDateFmt; ?></td></tr>
            <tr><td>Baseline verified first-party</td><td><?php echo (int)$this->fpCount; ?> / <?php echo (int)$this->totalFields; ?></td></tr>
          </table>
        </div>
      </div>
    </div>

    <?php if ($prg !== null): ?>
    <div class="fxr-section">
      <div class="fxr-label">Payout speed &mdash; declared vs measured withdrawal time</div>
      <div class="fxr-prg">
        <div class="fxr-prg-cell"><div class="k">Declares</div><div class="v"><?php echo ($payout && $payout['declared_hours'] !== null) ? $fmtH($payout['declared_hours']) : '&mdash;'; ?></div></div>
        <div class="fxr-prg-cell"><div class="k">Measured</div><div class="v"><?php echo ($payout && $payout['measured_hours'] !== null) ? $fmtH($payout['measured_hours']) : '&mdash;'; ?></div></div>
        <div class="fxr-prg-cell"><div class="k">Payout gap</div><div class="v" style="color:<?php echo $gapColor; ?>"><?php echo $h($fmtN($prg)); ?>&times;</div></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($displayEvents)): ?>
    <div class="fxr-section">
      <div class="fxr-label">Recorded events</div>
      <table class="fxr-ev">
        <?php foreach ($displayEvents as $e):
          $lbl = isset($evLabels[$e['event_code']]) ? $evLabels[$e['event_code']] : $e['event_code'];
          $w = (float)$e['base_weight'];
          $wTxt = ($w > 0 ? '+' : ($w < 0 ? '&minus;' : '')) . $fmtN(abs($w));
          $wColor = $w > 0 ? '#1c7a4a' : ($w < 0 ? '#9a2828' : '#5a5347');
          $evDate = $e['event_date'] !== null ? JHtml::_('date', $e['event_date'], 'd M Y') : '';
        ?>
        <tr>
          <td class="date"><?php echo $h($evDate); ?></td>
          <td><?php echo $h($lbl); ?></td>
          <td class="amt" style="color:<?php echo $wColor; ?>"><?php echo $wTxt; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <div class="fxr-foot">
      <div>
        Verified first-party by FreeExtraChips editorial staff.<br>
        Verify authenticity at freeextrachips.com/methodology/fxshield
      </div>
      <div style="text-align:right;">
        SI v1.1<br>
        <?php echo (int)$d['evidence_n']; ?> evidence file<?php echo (int)$d['evidence_n'] === 1 ? '' : 's'; ?>
      </div>
    </div>

  </div>

  <div class="fxr-actions">
    <button type="button" class="fxr-print-btn" onclick="window.print()">Download PDF / Print</button>
  </div>
</div>
<?php
// Fallback: if tmpl=component stripped the <jdoc:include type="head" />, the
// JDocument title may not have rendered. Guarantee a sensible document.title
// (also used as the default PDF filename on print) without emitting invalid
// in-body <title> markup.
?>
<script>
(function(){
  var want = <?php echo json_encode($d['title'] . ' — FXShield Verification Report | FreeExtraChips'); ?>;
  if (!document.title || document.title.indexOf('FXShield') === -1) { document.title = want; }
})();
</script>
