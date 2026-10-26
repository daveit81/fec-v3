<?php
defined('_JEXEC') or die;

/**
 * FXCheck methodology page template (Joomla layout suffix: default_fxcheck.php).
 *
 * Markup leans on the existing fec-edition.css design system:
 *   - .au-hero / .au-stats-strip / .au-main / .au-side-card / .au-side-cta
 *     reused as-is
 *   - .tag .tag-ok / .tag-info / .tag-warn / .tag-bad / .tag-mute used for the
 *     status pills in the status table
 *   - me-* classes added (in assets/methodology.css) only for patterns that
 *     don't exist in fec-edition.css (verdict box, status table grid, FAQ
 *     details, sidebar TOC, byline)
 *
 * The whole page is wrapped in <div class="fecx me-page"> so the editorial
 * tokens apply even if the site index.php hasn't been extended to add the
 * fecx body class for view=methodology.
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('fxcheck')
 *   $this->config       topic config
 *   $this->stats        live stats
 *   $this->author       byline author row (or null)
 *   $this->reviewer     reviewer row (or null)
 *   $this->jsonLdGraph  pre-encoded JSON-LD @graph
 */

$cfg      = is_array($this->config) ? $this->config : array();
$stats    = is_array($this->stats)  ? $this->stats  : array();
$author   = $this->author;
$reviewer = $this->reviewer;

// ---- Local helpers --------------------------------------------------------
$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$nfmt = function ($n) { return number_format((int)$n); };

// Stat shorthands
$minReports = isset($stats['min_reports'])         ? (int)$stats['min_reports']         : 5;
$posPct     = isset($stats['pos_threshold_pct'])   ? (int)$stats['pos_threshold_pct']   : 70;
$negPct     = isset($stats['neg_threshold_pct'])   ? (int)$stats['neg_threshold_pct']   : 70;
$recentDays = isset($stats['recent_window_days'])  ? (int)$stats['recent_window_days']  : 30;
$decayDays  = isset($stats['decay_window_days'])   ? (int)$stats['decay_window_days']   : 90;

// Last-report friendly format (mirrors author page pattern)
$lastReportFmt = '—';
if (!empty($stats['last_report_ts'])) {
    $diff = time() - (int)$stats['last_report_ts'];
    if      ($diff < 3600)        $lastReportFmt = 'Less than an hour ago';
    elseif  ($diff < 86400)       $lastReportFmt = floor($diff / 3600) . ' hours ago';
    elseif  ($diff < 86400 * 2)   $lastReportFmt = 'Yesterday';
    elseif  ($diff < 86400 * 30)  $lastReportFmt = floor($diff / 86400) . ' days ago';
    else                          $lastReportFmt = date('M Y', (int)$stats['last_report_ts']);
}

// ---- Breadcrumb -----------------------------------------------------------
$crumbHtml   = '';
$crumbJsonLd = '';
if (function_exists('fec_build_breadcrumb_items_from_path')
    && function_exists('fec_render_breadcrumb_html')) {
    $path       = JUri::getInstance()->getPath();
    $siteBase   = rtrim(JUri::base(true), '/');
    $crumbItems = fec_build_breadcrumb_items_from_path($path, $siteBase);
    if (!empty($crumbItems)) {
        $lastIdx = count($crumbItems) - 1;
        if (isset($crumbItems[$lastIdx]) && is_array($crumbItems[$lastIdx])) {
            foreach (array('name', 'label', 'title', 'text') as $k) {
                if (array_key_exists($k, $crumbItems[$lastIdx])) {
                    $crumbItems[$lastIdx][$k] = $cfg['title'];
                }
            }
        }
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
        if (function_exists('fec_render_breadcrumb_jsonld')) {
            $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
        }
    }
}

// ---- URLs ----------------------------------------------------------------
$authorUrl = '';
if ($author && !empty($author['slug'])) {
    $authorUrl = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']));
}
$reviewerUrl = '';
if ($reviewer && !empty($reviewer['slug'])) {
    $reviewerUrl = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($reviewer['slug']));
}

$lastUpdated = !empty($cfg['last_updated']) ? date('j F Y', strtotime($cfg['last_updated'])) : '';
$version     = !empty($cfg['version']) ? (string)$cfg['version'] : '';

// SVG icons (match au-* template)
$arrowUpRight = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';
$check        = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';

?>

<div class="fecx me-page">

<?php echo $crumbHtml; ?>

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow">Methodology</div>
        <h1 class="me-title"><?php echo $h($cfg['title']); ?></h1>
        <?php if (!empty($cfg['tagline'])): ?>
          <p class="me-tagline"><?php echo $h($cfg['tagline']); ?></p>
        <?php endif; ?>

        <div class="me-byline">
          <?php if ($author): ?>
            <span>By
              <?php if ($authorUrl !== ''): ?>
                <a href="<?php echo $authorUrl; ?>"><b><?php echo $h($author['name']); ?></b></a>
              <?php else: ?>
                <b><?php echo $h($author['name']); ?></b>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($reviewer): ?>
            <span class="div">·</span>
            <span>Reviewed by
              <?php if ($reviewerUrl !== ''): ?>
                <a href="<?php echo $reviewerUrl; ?>"><b><?php echo $h($reviewer['name']); ?></b></a>
              <?php else: ?>
                <b><?php echo $h($reviewer['name']); ?></b>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($lastUpdated !== ''): ?>
            <span class="div">·</span>
            <span>Updated <b><?php echo $h($lastUpdated); ?></b></span>
          <?php endif; ?>
          <?php if ($version !== ''): ?>
            <span class="div">·</span>
            <span>Version <b><?php echo $h($version); ?></b></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Live stats strip (au-* primitive) -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['total_reports']) ? $stats['total_reports'] : 0); ?></div>
        <div class="lbl">Total reports</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['reports_recent']) ? $stats['reports_recent'] : 0); ?></div>
        <div class="lbl">Last <?php echo $recentDays; ?> days</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['casinos_covered']) ? $stats['casinos_covered'] : 0); ?></div>
        <div class="lbl">Casinos covered</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['status_verified']) ? $stats['status_verified'] : 0); ?></div>
        <div class="lbl">Verified bonuses</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">

  <!-- ============ CONTENT ============ -->
  <article class="me-content">

    <!-- Quick verdict -->
    <div class="me-verdict">
      <div class="me-verdict-label">Quick verdict</div>
      <p>
        FXCheck&trade; is the player-verified status system that tells you whether a casino bonus is actually
        working <i>right now</i>, not just whether we listed it. Every bonus on FreeExtraChips shows a live
        status based on real Yes/No reports from players who attempted the offer. To date, FXCheck&trade; has
        processed <b><?php echo $nfmt(isset($stats['total_reports']) ? $stats['total_reports'] : 0); ?></b>
        verified reports across <b><?php echo $nfmt(isset($stats['casinos_covered']) ? $stats['casinos_covered'] : 0); ?></b>
        casinos, with <b><?php echo $nfmt(isset($stats['reports_recent']) ? $stats['reports_recent'] : 0); ?></b>
        new reports in the last <?php echo $recentDays; ?> days.
      </p>
    </div>

    <!-- Problem -->
    <h2 id="problem">The problem FXCheck&trade; solves</h2>
    <p>Casino bonus listings go stale fast. Operators change wagering requirements, tighten country
    restrictions, swap eligible games, or quietly terminate offers without notice. Standard
    &ldquo;Updated on [date]&rdquo; timestamps don&rsquo;t tell you whether the bonus you&rsquo;re
    about to claim still works for <i>your</i> situation &mdash; your country, your account status,
    your chosen game.</p>

    <p>Editorial verification catches structural changes but misses real-world friction: a code that
    the system accepts but the cashier blocks, a free spin offer that loads on the wrong slot, a
    no-deposit chip that disappears before wagering completes.</p>

    <p>FXCheck&trade; closes that gap by surfacing what players actually experience when they try the offer.</p>

    <!-- How a status is calculated -->
    <h2 id="status-calculation">How a bonus status is calculated</h2>
    <p>Every bonus on FreeExtraChips carries one of five FXCheck&trade; statuses, computed from player reports
    submitted in the last <?php echo $recentDays; ?> days.</p>

    <div class="me-status-table">
      <div class="me-status-row me-status-row--head">
        <div class="me-status-name">Status</div>
        <div class="me-status-criteria">Criteria</div>
        <div class="me-status-meaning">What it means for you</div>
        <div class="me-status-count">Live count</div>
      </div>

      <div class="me-status-row">
        <div class="me-status-name"><span class="tag tag-ok"><?php echo $check; ?> Verified</span></div>
        <div class="me-status-criteria">&ge;<?php echo $minReports; ?> reports, &ge;<?php echo $posPct; ?>% positive</div>
        <div class="me-status-meaning">High confidence the bonus is currently working. Safe to claim under listed terms.</div>
        <div class="me-status-count"><?php echo $nfmt(isset($stats['status_verified']) ? $stats['status_verified'] : 0); ?></div>
      </div>

      <div class="me-status-row">
        <div class="me-status-name"><span class="tag tag-info">Active</span></div>
        <div class="me-status-criteria">Listed editorially, fewer than <?php echo $minReports; ?> recent reports</div>
        <div class="me-status-meaning">Bonus exists per the operator&rsquo;s terms but lacks recent player confirmation. Worth claiming with normal due diligence.</div>
        <div class="me-status-count"><span class="me-muted">&mdash;</span></div>
      </div>

      <div class="me-status-row">
        <div class="me-status-name"><span class="tag tag-warn">Mixed signals</span></div>
        <div class="me-status-criteria">&ge;<?php echo $minReports; ?> reports, between thresholds</div>
        <div class="me-status-meaning">Players are reporting inconsistent outcomes. Check the terms carefully and read the player notes before claiming.</div>
        <div class="me-status-count"><?php echo $nfmt(isset($stats['status_mixed']) ? $stats['status_mixed'] : 0); ?></div>
      </div>

      <div class="me-status-row">
        <div class="me-status-name"><span class="tag tag-bad">Likely expired</span></div>
        <div class="me-status-criteria">&ge;<?php echo $negPct; ?>% negative in last <?php echo $recentDays; ?> days</div>
        <div class="me-status-meaning">Multiple recent failures. Hidden from the main listing by default. Display via the &ldquo;Show likely expired&rdquo; filter.</div>
        <div class="me-status-count"><?php echo $nfmt(isset($stats['status_likely_expired']) ? $stats['status_likely_expired'] : 0); ?></div>
      </div>

      <div class="me-status-row">
        <div class="me-status-name"><span class="tag tag-mute">Closed</span></div>
        <div class="me-status-criteria">Editorial confirmation</div>
        <div class="me-status-meaning">Operator confirmed termination, license revoked, or casino closed. Kept visible for archival reference only.</div>
        <div class="me-status-count"><span class="me-muted">&mdash;</span></div>
      </div>
    </div>

    <div class="me-callout">The status updates as new reports arrive &mdash; there is no scheduled refresh
    interval. A <b>Verified</b> bonus can shift to <b>Mixed signals</b> within minutes if a cluster of
    negative reports comes in.</div>

    <!-- Valid report rules -->
    <h2 id="valid-reports">What counts as a valid report</h2>
    <p>Not every click is a report. Submissions go through three filters before they affect the status:</p>
    <ol class="me-numbered">
      <li><b>Eligibility</b> &mdash; One report per device/account per bonus. Logged-in users with claim
      history weighted higher than anonymous submissions.</li>
      <li><b>Rate limiting</b> &mdash; Bursts of identical reports from the same IP range or device
      fingerprint are flagged and excluded from the score until reviewed.</li>
      <li><b>Pattern detection</b> &mdash; Reports that arrive in coordinated waves (suspicious timing,
      identical user-agent patterns, geographic clustering inconsistent with the bonus eligibility region)
      are quarantined for manual review.</li>
    </ol>
    <p>We do not remove negative reports because an operator complains. Operators can submit an editorial
    correction request through the contact form, which triggers a manual review &mdash; but the underlying
    player reports remain in the dataset.</p>

    <!-- Recency weighting -->
    <h2 id="recency">How recency affects the score</h2>
    <p>Reports are not equal. A claim that worked six months ago tells you very little about whether the
    same bonus works today. FXCheck&trade; weights reports on a decay curve:</p>
    <ul class="me-bullets">
      <li><b>Last 7 days</b>: full weight. These dominate the displayed status.</li>
      <li><b>8 to <?php echo $recentDays; ?> days</b>: standard weight. Contribute to the status but can be
      overridden by recent reports.</li>
      <li><b><?php echo $recentDays + 1; ?> to <?php echo $decayDays; ?> days</b>: decaying weight, halved
      at each midpoint.</li>
      <li><b>Older than <?php echo $decayDays; ?> days</b>: archived. Not used in current status. Visible
      in the bonus history view for trend analysis.</li>
    </ul>
    <p>This is why a long-running bonus with thousands of historical reports can still show <b>Mixed signals</b>
    if recent player experience has shifted.</p>

    <!-- Editorial overrides -->
    <h2 id="overrides">When editorial overrides FXCheck&trade;</h2>
    <p>Three situations override the automated status:</p>
    <ol class="me-numbered">
      <li><b>Operator confirmation of termination</b> &mdash; when a casino formally confirms a bonus has
      ended, the status switches to <b>Closed</b> immediately, regardless of pending player reports.</li>
      <li><b>License or compliance change</b> &mdash; if the operator loses its license, is added to a
      regulator blacklist, or is flagged in a compliance review<?php if ($reviewer): ?>
      by <?php if ($reviewerUrl !== ''): ?><a href="<?php echo $reviewerUrl; ?>"><?php echo $h($reviewer['name']); ?></a><?php else: ?><?php echo $h($reviewer['name']); ?><?php endif; ?><?php endif; ?>,
      the bonus is moved to <b>Closed</b> with an editorial note.</li>
      <li><b>Material terms change</b> &mdash; when wagering, max cashout, eligible games, or country
      restrictions change in a way that invalidates prior reports, the report history is reset and the
      bonus returns to <b>Active</b> until a new pool of reports accumulates.</li>
    </ol>
    <p>Every override is logged in the bonus page&rsquo;s change history, visible to all users.</p>

    <!-- How to read -->
    <h2 id="how-to-read">How to read FXCheck&trade; as a player</h2>
    <p>A practical decision tree:</p>
    <ul class="me-bullets me-bullets--lg">
      <li><b>Verified + &ge;80% positive in last 7 days</b> &rarr; claim with confidence under listed terms.</li>
      <li><b>Verified + &ge;<?php echo $posPct; ?>% positive overall but lower in last 7 days</b> &rarr; claim,
      but read the latest negative reports first. Common pattern: country restriction tightened recently.</li>
      <li><b>Active</b> &rarr; low signal. Read the terms yourself, check the operator&rsquo;s site for
      changes, claim if comfortable.</li>
      <li><b>Mixed signals</b> &rarr; assume something has changed. Frequent causes: silent wagering
      increase, eligible games narrowed, new max cashout cap.</li>
      <li><b>Likely expired</b> &rarr; don&rsquo;t claim without contacting the operator first. The bonus
      may technically exist in the cashier but is failing for new players.</li>
    </ul>
    <p>FXCheck&trade; is one signal among several. Always cross-check the wagering multiplier, max cashout, and
    country eligibility shown on the bonus page against the operator&rsquo;s terms at the moment of claim.</p>

    <!-- Transparency -->
    <h2 id="transparency">Transparency commitments</h2>
    <p>The numbers above update in real time. As of this page load:</p>
    <ul class="me-bullets">
      <li><b><?php echo $nfmt(isset($stats['total_reports']) ? $stats['total_reports'] : 0); ?></b> total
      reports collected<?php if (!empty($stats['launch_year'])): ?> since <?php echo (int)$stats['launch_year']; ?><?php endif; ?>.</li>
      <li><b><?php echo $nfmt(isset($stats['reports_recent']) ? $stats['reports_recent'] : 0); ?></b>
      reports in the last <?php echo $recentDays; ?> days.</li>
      <li><b><?php echo $nfmt(isset($stats['bonuses_tracked']) ? $stats['bonuses_tracked'] : 0); ?></b>
      bonuses with at least one FXCheck&trade; report.</li>
      <li><b><?php echo $nfmt(isset($stats['casinos_covered']) ? $stats['casinos_covered'] : 0); ?></b>
      casinos covered.</li>
      <li>Most recent report: <b><?php echo $h($lastReportFmt); ?></b>.</li>
    </ul>
    <p>If a discrepancy is found, submit a correction through our
    <a href="mailto:corrections@freeextrachips.com">corrections inbox</a>.</p>

    <!-- FAQ -->
    <h2 id="faq">Frequently asked questions</h2>

    <div class="me-faq">
      <details>
        <summary>Can casinos pay to manipulate their FXCheck&trade; status?</summary>
        <p>No. FXCheck&trade; status is computed mechanically from player reports filtered through our
        anti-spam pipeline. Affiliate relationships do not affect the status calculation.</p>
      </details>

      <details>
        <summary>What if I disagree with the status shown for a bonus?</summary>
        <p>Submit your own report on the bonus page. If you believe the status is mathematically wrong
        (the math doesn&rsquo;t match the reports), use the corrections form and include screenshots.
        We respond within 72 hours.</p>
      </details>

      <details>
        <summary>Do you remove negative reports?</summary>
        <p>Only when they fail the anti-spam filter (pattern detection on coordinated submissions) or
        are submitted by users with a track record of unverified claims. We never remove individual
        negative reports at an operator&rsquo;s request without an editorial review, and even then only
        when the report is demonstrably about a different bonus or different operator.</p>
      </details>

      <details>
        <summary>Why don&rsquo;t you show every individual report publicly?</summary>
        <p>Player reports often include account-level details (claim time, partial transaction info)
        that would be a privacy risk to expose. The aggregate numbers, status history, and editorial
        notes are public. The raw reports are not.</p>
      </details>

      <details>
        <summary>How is FXCheck&trade; different from regular user reviews?</summary>
        <p>FXCheck&trade; asks one specific question: did this bonus work for you, exactly as listed?
        A regular review asks for an overall opinion of the casino. The two complement each other
        &mdash; FXCheck&trade; tells you whether to claim the offer; reviews tell you whether to stick
        around at the casino afterwards.</p>
      </details>
    </div>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar">

    <!-- TOC -->
    <div class="au-side-card">
      <h4>On this page</h4>
      <nav class="me-toc">
        <a href="#problem">The problem we solve</a>
        <a href="#status-calculation">How status is calculated</a>
        <a href="#valid-reports">Valid reports</a>
        <a href="#recency">Recency weighting</a>
        <a href="#overrides">Editorial overrides</a>
        <a href="#how-to-read">How to read FXCheck&trade;</a>
        <a href="#transparency">Transparency</a>
        <a href="#faq">FAQ</a>
      </nav>
    </div>

    <!-- Methodology meta -->
    <div class="au-side-card">
      <h4>Methodology</h4>
      <?php if ($version !== ''): ?>
        <div class="row"><span class="k">Version</span><span class="v"><?php echo $h($version); ?></span></div>
      <?php endif; ?>
      <?php if ($lastUpdated !== ''): ?>
        <div class="row"><span class="k">Last updated</span><span class="v"><?php echo $h($lastUpdated); ?></span></div>
      <?php endif; ?>
      <?php if (!empty($stats['launch_year'])): ?>
        <div class="row"><span class="k">In service since</span><span class="v"><?php echo (int)$stats['launch_year']; ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="k">Last report</span><span class="v"><?php echo $h($lastReportFmt); ?></span></div>
    </div>

    <!-- Authors -->
    <?php if ($author || $reviewer): ?>
      <div class="au-side-card">
        <h4>Authors</h4>
        <?php if ($author): ?>
          <div class="me-author-row">
            <?php if ($authorUrl !== ''): ?>
              <a href="<?php echo $authorUrl; ?>"><?php echo $h($author['name']); ?></a>
            <?php else: ?>
              <span><?php echo $h($author['name']); ?></span>
            <?php endif; ?>
            <?php if (!empty($author['role'])): ?>
              <span class="me-author-role"><?php echo $h($author['role']); ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($reviewer): ?>
          <div class="me-author-row">
            <?php if ($reviewerUrl !== ''): ?>
              <a href="<?php echo $reviewerUrl; ?>"><?php echo $h($reviewer['name']); ?></a>
            <?php else: ?>
              <span><?php echo $h($reviewer['name']); ?></span>
            <?php endif; ?>
            <span class="me-author-role">Reviewer<?php if (!empty($reviewer['role'])): ?> &middot; <?php echo $h($reviewer['role']); ?><?php endif; ?></span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Correction CTA (au-* primitive) -->
    <div class="au-side-cta">
      <h4>Spot a math error?</h4>
      <p>The status calculations on this page are derived live from the database. If something looks off, tell us &mdash; we fix it.</p>
      <a class="btn" href="mailto:corrections@freeextrachips.com">File a correction <?php echo $arrowUpRight; ?></a>
    </div>

  </aside>
</section>

</div><!-- /.fecx -->

<?php echo $crumbJsonLd; ?>

<?php if (!empty($this->jsonLdGraph)): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>
