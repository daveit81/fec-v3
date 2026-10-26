<?php
defined('_JEXEC') or die;

/**
 * FXShield admin cockpit — default template (Phase A, read-only).
 * Data from AuthorsViewFxshieldadmin: $this->rows, $this->summary, $this->stats.
 */

$rows    = is_array($this->rows)    ? $this->rows    : array();
$summary = is_array($this->summary) ? $this->summary : array();
$stats   = is_array($this->stats)   ? $this->stats   : array();

$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

// Tier -> css modifier (reuse me- palette; map to severity bands)
$tierClass = function ($tier) {
    switch ((string)$tier) {
        case 'Reliable':  return 'fxa-tier-reliable';
        case 'Solid':     return 'fxa-tier-solid';
        case 'Caution':   return 'fxa-tier-caution';
        case 'High risk': return 'fxa-tier-highrisk';
        case 'Critical':  return 'fxa-tier-critical';
        case 'Excluded':  return 'fxa-tier-excluded';
        default:          return 'fxa-tier-none';
    }
};
$confClass = function ($c) {
    $c = strtoupper((string)$c);
    return in_array($c, array('A','B','C','D'), true) ? 'fxa-conf-' . $c : 'fxa-conf-none';
};

// Friendly "last computed"
$lastCalc = '—';
if (!empty($stats['last_computed_ts'])) {
    $diff = time() - (int)$stats['last_computed_ts'];
    if      ($diff < 3600)       $lastCalc = 'less than an hour ago';
    elseif  ($diff < 86400)      $lastCalc = floor($diff / 3600) . 'h ago';
    elseif  ($diff < 86400 * 30) $lastCalc = floor($diff / 86400) . 'd ago';
    else                         $lastCalc = date('j M Y', (int)$stats['last_computed_ts']);
}

$engineLive = !empty($stats['engine_live']);
?>

<div class="fecx wrap fxa-page">

  <div class="fxa-head">
    <div class="eyebrow">Admin · read-only</div>
    <h1>FXShield&trade; status</h1>
    <p class="fxa-sub">
      Every casino with an FXShield footprint. This is the work-queue view:
      spot which casinos sit at confidence D and need a hands-on verification,
      which have an incomplete baseline, and which were never touched.
      <?php if ($engineLive): ?>
        Last recalculation: <b><?php echo $h($lastCalc); ?></b>.
      <?php else: ?>
        <b>Engine has not produced any scores yet.</b>
      <?php endif; ?>
    </p>
    <p class="fxa-actions-top">
      <a class="btn btn-primary fxa-deskadd-btn" href="index.php?option=com_authors&amp;view=deskadd">+ Add desk casino</a>
      <a class="btn btn-ghost fxa-recalcall-btn"
         href="<?php echo 'index.php?option=com_authors&amp;task=fxshieldadmin.recalcall&amp;' . JSession::getFormToken() . '=1'; ?>"
         onclick="return confirm('Run the full safety engine now for every casino in the scoring universe? This is the same run the weekly cron performs.');"
         title="Run the safety engine for every casino now, without waiting for the weekly cron">&#8635; Recalc all</a>
    </p>
  </div>

  <?php if (empty($rows)): ?>

    <div class="fxa-empty">
      <p>No FXShield data found. Either the <code>fec_*</code> tables aren&rsquo;t
      deployed on this database yet, or no casino has a baseline, an event, or a
      score. Load the schema and run <code>safety-engine.php</code>, then refresh.</p>
    </div>

  <?php else: ?>

    <!-- Summary strip -->
    <div class="fxa-summary">
      <div class="fxa-card">
        <div class="fxa-num"><?php echo (int)$summary['scored']; ?></div>
        <div class="fxa-lbl">Scored</div>
      </div>
      <div class="fxa-card">
        <div class="fxa-num"><?php echo (int)$summary['unscored']; ?></div>
        <div class="fxa-lbl">Not yet scored</div>
      </div>
      <div class="fxa-card fxa-card-warn">
        <div class="fxa-num"><?php echo (int)$summary['need_verify']; ?></div>
        <div class="fxa-lbl">Need verification (conf. D)</div>
      </div>
      <div class="fxa-card">
        <div class="fxa-num"><?php echo (int)$summary['incomplete']; ?></div>
        <div class="fxa-lbl">Incomplete baseline</div>
      </div>
      <div class="fxa-card">
        <div class="fxa-conf-bar">
          <span class="fxa-conf-A"><?php echo (int)$summary['conf']['A']; ?></span>
          <span class="fxa-conf-B"><?php echo (int)$summary['conf']['B']; ?></span>
          <span class="fxa-conf-C"><?php echo (int)$summary['conf']['C']; ?></span>
          <span class="fxa-conf-D"><?php echo (int)$summary['conf']['D']; ?></span>
        </div>
        <div class="fxa-lbl">Confidence A / B / C / D</div>
      </div>
    </div>

    <!-- Status table -->
    <div class="fxa-tablewrap">
      <table class="fxa-table">
        <thead>
          <tr>
            <th>Casino</th>
            <th class="num">Score</th>
            <th>Tier</th>
            <th>Conf.</th>
            <th class="num">Baseline</th>
            <th class="num">Events</th>
            <th class="num">PRG</th>
            <th class="num">Δ30d</th>
            <th>Caps</th>
            <th>Last first-party</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $cid       = (int)$r['casino_id'];
            $title     = ($r['title'] !== null && $r['title'] !== '') ? $r['title'] : ('#' . $cid);
            $hasScore  = ($r['si_score'] !== null && $r['si_score'] !== '');
            $bFields   = (int)$r['baseline_fields'];
            $bVerified = (int)$r['baseline_verified'];
            $events    = (int)$r['events_active'];
            $conf      = strtoupper((string)$r['si_confidence']);
            $needsWork = ($hasScore && $conf === 'D');
            $rowCls    = $needsWork ? 'fxa-row-flag' : '';
          ?>
          <tr class="<?php echo $rowCls; ?>">
            <td class="fxa-name">
              <?php echo $h($title); ?>
              <span class="fxa-cid">#<?php echo $cid; ?></span>
            </td>
            <td class="num">
              <?php echo $hasScore ? (int)$r['si_score'] : '<span class="fxa-dash">—</span>'; ?>
            </td>
            <td>
              <span class="fxa-tier <?php echo $tierClass($r['si_tier']); ?>">
                <?php echo $hasScore ? $h($r['si_tier']) : 'not scored'; ?>
              </span>
            </td>
            <td>
              <?php if ($hasScore): ?>
                <span class="fxa-conf <?php echo $confClass($conf); ?>"><?php echo $h($conf); ?></span>
              <?php else: ?>
                <span class="fxa-dash">—</span>
              <?php endif; ?>
            </td>
            <td class="num">
              <span class="fxa-baseline<?php echo ($bFields < 9 ? ' fxa-baseline-low' : ''); ?>"
                    title="<?php echo $bVerified; ?> of <?php echo $bFields; ?> verified first-party">
                <?php echo $bVerified; ?>/<?php echo $bFields; ?>
              </span>
            </td>
            <td class="num"><?php echo $events; ?></td>
            <td class="num">
              <?php echo ($r['prg_ratio'] !== null && $r['prg_ratio'] !== '')
                ? $h(rtrim(rtrim(number_format((float)$r['prg_ratio'], 1), '0'), '.')) . '&times;'
                : '<span class="fxa-dash">—</span>'; ?>
            </td>
            <td class="num">
              <?php
                if ($r['si_delta_30d'] !== null && $r['si_delta_30d'] !== '') {
                    $d = (float)$r['si_delta_30d'];
                    $cls = $d > 0 ? 'fxa-up' : ($d < 0 ? 'fxa-down' : '');
                    $sign = $d > 0 ? '+' : '';
                    echo '<span class="' . $cls . '">' . $sign . $h(rtrim(rtrim(number_format($d, 1), '0'), '.')) . '</span>';
                } else {
                    echo '<span class="fxa-dash">—</span>';
                }
              ?>
            </td>
            <td>
              <?php echo ($r['si_caps_applied'] !== null && $r['si_caps_applied'] !== '')
                ? '<span class="fxa-cap">' . $h($r['si_caps_applied']) . '</span>'
                : '<span class="fxa-dash">—</span>'; ?>
            </td>
            <td>
              <?php echo ($r['last_first_party'] !== null && $r['last_first_party'] !== '')
                ? $h(date('j M Y', strtotime($r['last_first_party'])))
                : '<span class="fxa-dash fxa-never">never</span>'; ?>
            </td>
            <td>
              <a class="btn btn-ghost fxa-baseline-btn"
                 href="<?php echo 'index.php?option=com_authors&amp;view=deskadd&amp;casino_id=' . $cid; ?>"
                 title="<?php echo ($bFields > 0)
                    ? 'Open the desk form with the saved baseline loaded — adjust fields or add ledger events'
                    : 'Open the desk form with this casino preselected'; ?>"><?php echo ($bFields > 0) ? 'Edit baseline' : 'Add baseline'; ?></a>
              <a class="btn btn-ghost fxa-recalc-btn"
                 href="<?php echo 'index.php?option=com_authors&amp;task=fxshieldadmin.recalc&amp;casino_id=' . $cid . '&amp;' . JSession::getFormToken() . '=1'; ?>"
                 title="Run the safety engine for this casino now, without waiting for the weekly cron">Recalc</a>
              <a class="btn btn-ghost fxa-verify-btn"
                 href="<?php echo 'index.php?option=com_authors&amp;view=verification'; ?>">Verify</a>
              <?php if ($hasScore): ?>
              <a class="btn btn-ghost fxa-export-btn"
                 href="<?php echo 'index.php?option=com_authors&amp;task=fxshieldadmin.exportmd&amp;casino_id=' . $cid; ?>"
                 title="Download an FXShield fact-sheet (.md) for the review generator">Export MD</a>
              <a class="btn btn-ghost fxa-dossier-btn"
                 href="<?php echo 'index.php?option=com_authors&amp;task=fxshieldadmin.exportdossier&amp;casino_id=' . $cid; ?>"
                 title="Download the full internal dossier (.md) — detailed, for writing reviews">Dossier</a>
              <?php endif; ?>
              <?php if ((int)$r['baseline_verified'] > 0): ?>
              <a class="btn btn-ghost fxa-report-btn"
                 href="<?php echo 'index.php?option=com_authors&amp;view=fxreport&amp;casino_id=' . $cid; ?>"
                 target="_blank"
                 title="Open the first-party Verification Report (print to PDF)">Report</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="fxa-foot">
      Rows highlighted need a first-party verification: they hold a score but
      confidence D, meaning the number rests on desk research. The verification
      workflow (Phase B) will write directly from here; for now, use the
      verification sheet and the SQL import flow.
    </p>

  <?php endif; ?>

</div><!-- /.fxa-page -->
