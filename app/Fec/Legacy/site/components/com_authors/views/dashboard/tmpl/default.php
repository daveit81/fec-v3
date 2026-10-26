<?php
/**
 * Dashboard main template.
 *
 * Single-page UI: server-renders the slot list, then JS swaps in detail
 * panels and modal forms on tap. All writes go through AJAX (controllers/
 * dashboard.php) so the page never reloads.
 *
 * Mobile-first: targets 380px viewport, scales up. Bottom-sheet modals.
 */
defined('_JEXEC') or die;

$slots    = $this->slots;
$coverage = $this->coverage;
$search   = htmlspecialchars($this->search, ENT_QUOTES, 'UTF-8');
$filter   = $this->filter;
$total    = (int)$coverage['total'];
$doc      = (int)$coverage['documented'];
$pct      = $total > 0 ? round(($doc / $total) * 100) : 0;
$target_n = (int)$this->top_n_target;

// Status badges config (icon + label + CSS class)
$statusMap = array(
    'verified'      => array('icon' => '✓', 'label' => 'Verified',      'cls' => 'st-ok'),
    'needs_refresh' => array('icon' => '!', 'label' => 'Needs refresh', 'cls' => 'st-warn'),
    'partial'       => array('icon' => '~', 'label' => 'No observations', 'cls' => 'st-partial'),
    'missing'       => array('icon' => '×', 'label' => 'Not documented', 'cls' => 'st-miss'),
);
?>
<div class="fecx dashx">

  <header class="dashx-head">
    <div class="dashx-head-row">
      <h1>RTP Database</h1>
      <div class="dashx-head-actions">
        <button type="button" class="dashx-btn-add" id="dashx-add-btn">+ Add slot</button>
        <a class="dashx-btn-link" href="<?php echo JRoute::_('index.php?option=com_authors&view=methodologyadmin'); ?>">Methodology pages</a>
        <span class="dashx-user">user #<?php echo (int)JFactory::getUser()->id; ?></span>
      </div>
    </div>
    <p class="dashx-sub">Top <?php echo $target_n; ?> priority slots — by active-bonus occurrences</p>

    <div class="dashx-coverage" role="status" aria-live="polite">
      <div class="dashx-cov-num">
        <strong><?php echo $doc; ?></strong> / <?php echo $target_n; ?>
        <span class="dashx-cov-pct"><?php echo $pct; ?>%</span>
      </div>
      <div class="dashx-cov-bar"><span style="width:<?php echo $pct; ?>%"></span></div>
    </div>
  </header>

  <!-- Add slot modal (hidden by default; toggled by JS) -->
  <div class="dashx-add-modal" id="dashx-add-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="dashx-add-title">
    <div class="dashx-add-backdrop" data-close="add-modal"></div>
    <div class="dashx-add-sheet">
      <div class="dashx-add-head">
        <h2 id="dashx-add-title">Add a slot to document</h2>
        <button type="button" class="dashx-add-close" data-close="add-modal" aria-label="Close">&times;</button>
      </div>
      <div class="dashx-add-body">
        <input type="search"
               id="dashx-add-search"
               class="dashx-add-search"
               placeholder="Search slot name in #__games…"
               autocomplete="off"
               inputmode="search">
        <p class="dashx-add-hint">Shows slots from <code>#__games</code> not yet in the RTP database, ranked by active bonus count.</p>
        <ul class="dashx-add-results" id="dashx-add-results"></ul>
      </div>
    </div>
  </div>

  <form class="dashx-filters" method="get" action="" id="dashx-filter-form">
    <input type="hidden" name="option" value="com_authors">
    <input type="hidden" name="view" value="dashboard">
    <input type="search" name="q" value="<?php echo $search; ?>" placeholder="Search slot or provider…" autocomplete="off" inputmode="search">
    <div class="dashx-tabs" role="tablist">
      <?php
        $tabs = array(
          'all'           => 'All',
          'missing'       => 'Missing',
          'needs_refresh' => 'Stale',
          'verified'      => 'OK',
        );
        foreach ($tabs as $k => $label):
          $active = ($filter === $k) ? ' is-active' : '';
      ?>
        <button type="submit" name="filter" value="<?php echo $k; ?>" class="dashx-tab<?php echo $active; ?>" role="tab" aria-selected="<?php echo $active ? 'true' : 'false'; ?>"><?php echo $label; ?></button>
      <?php endforeach; ?>
    </div>
  </form>

  <ul class="dashx-list" id="dashx-list">
    <?php if (empty($slots)): ?>
      <li class="dashx-empty">No slots match the current filter.</li>
    <?php else: ?>
      <?php foreach ($slots as $i => $s):
        $st = isset($statusMap[$s->status_code]) ? $statusMap[$s->status_code] : $statusMap['missing'];
        $rtpLabel = '';
        if ($s->rtp_theoretical_max !== null) {
            $rtpLabel = number_format((float)$s->rtp_theoretical_max, 2) . '%';
            if ($s->rtp_theoretical_min !== null && (float)$s->rtp_theoretical_min < (float)$s->rtp_theoretical_max) {
                $rtpLabel .= ' / ' . number_format((float)$s->rtp_theoretical_min, 2) . '%';
            }
        }
        $verifiedLabel = '';
        if (!empty($s->last_verified_date) && $s->last_verified_date !== '0000-00-00') {
            $ts = strtotime($s->last_verified_date);
            $diffDays = floor((time() - $ts) / 86400);
            if ($diffDays === 0.0)       $verifiedLabel = 'today';
            elseif ($diffDays === 1.0)   $verifiedLabel = 'yesterday';
            elseif ($diffDays < 30)      $verifiedLabel = (int)$diffDays . ' days ago';
            elseif ($diffDays < 365)     $verifiedLabel = round($diffDays / 30) . ' mo ago';
            else                         $verifiedLabel = round($diffDays / 365) . ' yr ago';
        }
        // Rank pill — top 30 get a special "MVP" mark
        $rank = $this->limitstart + $i + 1;
        $mvp = ($rank <= $target_n);

        // Publish state badge — at-a-glance Live / Draft / Retired
        $isRetired   = ((int)(isset($s->retired) ? $s->retired : 0) === 1);
        $isPublished = ((int)(isset($s->published) ? $s->published : 0) === 1);
        if ($isRetired)        { $stateLabel = 'RETIRED'; $stateCls = 'is-retired'; }
        elseif ($isPublished)  { $stateLabel = 'LIVE';    $stateCls = 'is-live'; }
        elseif ($s->has_rtp)   { $stateLabel = 'DRAFT';   $stateCls = 'is-draft'; }
        else                   { $stateLabel = '';        $stateCls = ''; }

        $visitUrl = ($isPublished && !$isRetired && !empty($s->slug))
            ? '/slots/' . rawurlencode($s->slug)
            : '';
      ?>
      <li class="dashx-item" data-gid="<?php echo (int)$s->gid; ?>" data-name="<?php echo htmlspecialchars($s->game_name, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="dashx-item-tap" aria-label="Edit slot">
          <span class="dashx-rank<?php echo $mvp ? ' is-mvp' : ''; ?>">#<?php echo $rank; ?></span>
          <span class="dashx-item-main">
            <span class="dashx-name">
              <?php echo htmlspecialchars($s->game_name, ENT_QUOTES, 'UTF-8'); ?>
              <?php if ($stateLabel): ?><span class="dashx-state <?php echo $stateCls; ?>"><?php echo $stateLabel; ?></span><?php endif; ?>
            </span>
            <span class="dashx-meta">
              <span class="dashx-provider"><?php echo htmlspecialchars($s->provider_name, ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="dashx-bonuses"><?php echo (int)$s->active_bonus_count; ?> bonus<?php echo ((int)$s->active_bonus_count === 1 ? '' : 'es'); ?></span>
              <?php if ($rtpLabel): ?><span class="dashx-rtp"><?php echo $rtpLabel; ?></span><?php endif; ?>
              <?php if ($verifiedLabel): ?><span class="dashx-when"><?php echo $verifiedLabel; ?></span><?php endif; ?>
              <?php if ((int)$s->obs_count > 0): ?><span class="dashx-obs"><?php echo (int)$s->obs_count; ?> obs</span><?php endif; ?>
            </span>
          </span>
          <span class="dashx-status <?php echo $st['cls']; ?>" title="<?php echo $st['label']; ?>"><?php echo $st['icon']; ?></span>
        </button>
        <?php if ($visitUrl !== ''): ?>
          <a class="dashx-visit" href="<?php echo htmlspecialchars($visitUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" title="View public page" aria-label="View public page">↗</a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>

  <?php
    // Pagination — render only when total exceeds one page
    $total = isset($this->totalSlots) ? (int)$this->totalSlots : count($slots);
    $limit = isset($this->limit) ? (int)$this->limit : 50;
    $start = isset($this->limitstart) ? (int)$this->limitstart : 0;
    if ($total > $limit):
      $currentPage = (int)floor($start / $limit) + 1;
      $totalPages  = (int)ceil($total / $limit);

      // Build base URL preserving current search/filter
      $params = array();
      if ($this->search !== '') $params['q'] = $this->search;
      if ($this->filter !== 'all') $params['filter'] = $this->filter;
      $baseQs = !empty($params) ? '&' . http_build_query($params) : '';

      $prevUrl = '?option=com_authors&view=dashboard' . $baseQs;
      if ($start > $limit) {
          $prevUrl .= '&limitstart=' . ($start - $limit);
      }
      $nextUrl = '?option=com_authors&view=dashboard&limitstart=' . ($start + $limit) . $baseQs;
  ?>
  <nav class="dashx-pagination" aria-label="Slots pagination">
    <?php if ($currentPage > 1): ?>
      <a class="dashx-page-link" href="<?php echo htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="prev">← Prev</a>
    <?php else: ?>
      <span class="dashx-page-link is-disabled">← Prev</span>
    <?php endif; ?>

    <span class="dashx-page-info">
      <?php echo $start + 1; ?>–<?php echo min($start + $limit, $total); ?>
      <span class="dashx-page-of">of <?php echo number_format($total); ?></span>
    </span>

    <?php if ($currentPage < $totalPages): ?>
      <a class="dashx-page-link" href="<?php echo htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="next">Next →</a>
    <?php else: ?>
      <span class="dashx-page-link is-disabled">Next →</span>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

  <!-- ============================================================
       Slot detail panel — hidden by default, populated by JS via
       AJAX (task=fetchSlot) when an item is tapped. Slides up from
       the bottom on mobile, displays as side panel on desktop.
       ============================================================ -->
  <div class="dashx-panel" id="dashx-panel" aria-hidden="true" role="dialog">
    <div class="dashx-panel-inner" role="document">
      <header class="dashx-panel-head">
        <button type="button" class="dashx-close" data-close="panel" aria-label="Close">←</button>
        <h2 id="dashx-panel-title">…</h2>
      </header>

      <div class="dashx-panel-body" id="dashx-panel-body">
        <!-- JS injects:
             - slot metadata
             - RTP form
             - observation list
             - [+ Add observation] button -->
        <p class="dashx-loading">Loading…</p>
      </div>
    </div>
  </div>

  <!-- ============================================================
       Observation modal — adds a new per-casino RTP observation.
       Populated by JS with the current slot gid + casino picker.
       ============================================================ -->
  <div class="dashx-modal" id="dashx-obs-modal" aria-hidden="true" role="dialog">
    <div class="dashx-modal-inner">
      <header class="dashx-modal-head">
        <h3>Add observation</h3>
        <button type="button" class="dashx-close" data-close="obs-modal" aria-label="Close">×</button>
      </header>
      <form class="dashx-obs-form" id="dashx-obs-form">
        <input type="hidden" name="gid" value="">

        <label>
          <span>Casino</span>
          <select name="cid" required>
            <option value="">Select casino…</option>
            <!-- options injected by JS -->
          </select>
        </label>

        <label>
          <span>RTP observed (%)</span>
          <input type="number" name="rtp_observed" step="0.01" min="50" max="100" inputmode="decimal" placeholder="e.g. 96.50" required>
        </label>

        <label>
          <span>Observed date</span>
          <input type="date" name="observed_date" required>
        </label>

        <label>
          <span>Evidence type</span>
          <select name="evidence_type">
            <option value="paytable_screenshot" selected>Paytable screenshot</option>
            <option value="casino_disclosure">Casino disclosure</option>
            <option value="regulator_certificate">Regulator certificate</option>
            <option value="player_report">Player report</option>
          </select>
        </label>

        <label class="dashx-upload">
          <span>Screenshot (camera)</span>
          <input type="file" name="screenshot" accept="image/*" capture="environment">
          <small class="dashx-hint">Auto-resized to 1600px max side, saved to /images/rtp/&lt;gid&gt;/</small>
        </label>

        <div class="dashx-upload-preview" id="dashx-obs-preview" hidden>
          <img alt="Screenshot preview">
          <button type="button" class="dashx-btn-ghost" data-action="clear-screenshot">Remove</button>
        </div>

        <label>
          <span>Notes (optional)</span>
          <textarea name="notes" rows="2" placeholder="e.g. Found in-game paytable, confirmed via T&amp;C."></textarea>
        </label>

        <div class="dashx-form-actions">
          <button type="button" class="dashx-btn-ghost" data-close="obs-modal">Cancel</button>
          <button type="submit" class="dashx-btn-primary">Save observation</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Toast container (transient messages) -->
  <div class="dashx-toast" id="dashx-toast" role="status" aria-live="polite"></div>

</div>
