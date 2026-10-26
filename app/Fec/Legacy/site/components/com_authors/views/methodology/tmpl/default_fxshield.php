<?php
defined('_JEXEC') or die;

/**
 * FXShield methodology page template (Joomla layout suffix: default_fxshield.php).
 *
 * Publishes the complete FXShield formula (SI v1.1): baseline weights, hard
 * caps, event taxonomy with half-lives, decay, FXCheck signal, Payout Reality
 * Gap, group-risk coefficients, confidence grades, dispute process.
 *
 * Markup follows the fxcheck/rtp-verification template family:
 *   - .au-hero / .au-stats-strip / .au-main / .au-side-card / .au-side-cta
 *   - me-* patterns from assets/methodology.css (verdict, callout, FAQ, TOC)
 *   - .me-table (added in V2.7.12) for the formula tables
 *
 * Live-stats degradation: $this->stats comes from
 * AuthorsModelMethodology::getFxshieldStats(), which returns zeros while the
 * safety engine tables (#__fec_safety_scores / #__fec_safety_events) don't
 * exist yet. Pre-launch the stats strip renders static formula facts instead
 * of a row of zeros; post-launch it switches to live counters automatically.
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('fxshield')
 *   $this->config       topic config
 *   $this->stats        live engine stats (degraded to zeros pre-launch)
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

// Stat shorthands (all degrade to 0 pre-launch)
$engineLive    = !empty($stats['engine_live']);
$casinosScored = isset($stats['casinos_scored'])   ? (int)$stats['casinos_scored']   : 0;
$eventsPublic  = isset($stats['events_public'])    ? (int)$stats['events_public']    : 0;
$staffTests    = isset($stats['staff_tests'])      ? (int)$stats['staff_tests']      : 0;

// Last-recalculation friendly format (mirrors fxcheck pattern)
$lastCalcFmt = '&mdash;';
if (!empty($stats['last_computed_ts'])) {
    $diff = time() - (int)$stats['last_computed_ts'];
    if      ($diff < 3600)       $lastCalcFmt = 'Less than an hour ago';
    elseif  ($diff < 86400)      $lastCalcFmt = floor($diff / 3600) . ' hours ago';
    elseif  ($diff < 86400 * 2)  $lastCalcFmt = 'Yesterday';
    elseif  ($diff < 86400 * 30) $lastCalcFmt = floor($diff / 86400) . ' days ago';
    else                         $lastCalcFmt = date('M Y', (int)$stats['last_computed_ts']);
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
$fxcheckUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck');

$lastUpdated = !empty($cfg['last_updated']) ? date('j F Y', strtotime($cfg['last_updated'])) : '';
$version     = !empty($cfg['version']) ? (string)$cfg['version'] : '';

// ---- FAQPage JSON-LD (kept in sync with the visible FAQ below) ------------
$siteRoot = rtrim(JUri::root(), '/');
$pageRel  = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxshield');
$pageAbs  = preg_match('#^https?://#i', $pageRel) ? $pageRel : $siteRoot . '/' . ltrim($pageRel, '/');
$faqLd    = array(
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    '@id'        => $pageAbs . '#faq',
    'mainEntity' => array(
        array('@type' => 'Question',
            'name' => 'Can a casino pay to improve its FXShield score?',
            'acceptedAnswer' => array('@type' => 'Answer', 'text' =>
                'No. Commercial relationships are not an input to the calculation, and the formula published on this page is the entire formula. The only way a score rises is new evidence: a verified licence upgrade, removed predatory clauses, paid test withdrawals, or sustained positive player reports.')),
        array('@type' => 'Question',
            'name' => "Why did a score change when the review didn't?",
            'acceptedAnswer' => array('@type' => 'Answer', 'text' =>
                'FXShield recalculates weekly even when nothing new happens: older events lose weight under published half-lives, the 90-day FXCheck window rolls forward, and group-risk events decay. The public timeline on each review page shows which components moved.')),
        array('@type' => 'Question',
            'name' => 'What does a low confidence grade mean?',
            'acceptedAnswer' => array('@type' => 'Answer', 'text' =>
                'It means the evidence behind the score is thin or stale, usually a casino without a recent hands-on test or with few player reports. It is not a hidden penalty on the score; it is a separate statement about how much weight to give the number.')),
        array('@type' => 'Question',
            'name' => 'Why is a casino penalised for something a sister brand did?',
            'acceptedAnswer' => array('@type' => 'Answer', 'text' =>
                'Related brands share management, payment processing and often identical terms. Only severe events transfer between sisters, at published rates from 5% to 35%, capped at 15 points in total, and each transferred event links to the original so readers can judge the connection themselves.')),
        array('@type' => 'Question',
            'name' => 'How is FXShield different from other casino safety ratings?',
            'acceptedAnswer' => array('@type' => 'Answer', 'text' =>
                'Every weight, threshold and coefficient is published in full. Every point traces to a dated, sourced event in a public timeline, including hands-on withdrawal tests and measured payout times. And every score carries an A-to-D confidence grade describing how fresh and first-hand the evidence is.')),
    ),
);
$faqLdJson = json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// SVG icon (matches au-* template)
$arrowUpRight = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';

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
            <span>Formula <b><?php echo $h($version); ?></b></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Stats strip: live counters once the engine runs, formula facts before -->
    <div class="au-stats-strip">
      <?php if ($engineLive): ?>
        <div class="stat">
          <div class="num"><?php echo $nfmt($casinosScored); ?></div>
          <div class="lbl">Casinos scored</div>
        </div>
        <div class="stat">
          <div class="num"><?php echo $nfmt($eventsPublic); ?></div>
          <div class="lbl">Public log events</div>
        </div>
        <div class="stat">
          <div class="num"><?php echo $nfmt($staffTests); ?></div>
          <div class="lbl">Staff test events</div>
        </div>
        <div class="stat">
          <div class="num"><?php echo $lastCalcFmt; ?></div>
          <div class="lbl">Last recalculation</div>
        </div>
      <?php else: ?>
        <div class="stat">
          <div class="num">0&ndash;100</div>
          <div class="lbl">Score scale</div>
        </div>
        <div class="stat">
          <div class="num">17</div>
          <div class="lbl">Event types</div>
        </div>
        <div class="stat">
          <div class="num">100%</div>
          <div class="lbl">Formula published</div>
        </div>
        <div class="stat">
          <div class="num">A&ndash;D</div>
          <div class="lbl">Confidence grades</div>
        </div>
      <?php endif; ?>
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
        FXShield&trade; is our safety index for online casinos. It answers one question: can you trust
        this casino with a deposit and, more importantly, with a withdrawal &mdash; <i>today</i>. Every
        score is built only from verified facts and from dated, sourced events in a public log.
        <?php if ($engineLive): ?>
          FXShield&trade; currently scores <b><?php echo $nfmt($casinosScored); ?></b> casinos from a
          public log of <b><?php echo $nfmt($eventsPublic); ?></b> events, including
          <b><?php echo $nfmt($staffTests); ?></b> hands-on staff tests.
        <?php else: ?>
          If we can&rsquo;t show you the evidence, it doesn&rsquo;t score.
        <?php endif; ?>
        This page documents the complete formula: every weight, threshold, half-life and coefficient.
        None of it is a trade secret.
      </p>
    </div>

    <!-- Scope -->
    <h2 id="scope">What the score measures, and what it doesn&rsquo;t</h2>
    <p>FXShield&trade; measures safety: licensing and recourse, fairness of the terms, payout behaviour,
    player protection tools, and the track record of the operator and its sister brands. It does not
    measure how good a casino is to play at. Game selection, bonus value, site usability and support
    quality belong to <a href="/who-is-frank">FRank</a>, our overall casino rating. The two are
    calculated from separate inputs and don&rsquo;t influence each other. A casino can hold a high
    FRank and a low FXShield&trade; at the same time &mdash; a polished product with unreliable payouts
    &mdash; and that is exactly what you need to see before depositing.</p>

    <p>Every casino review shows the result in the same format: a score from 0 to 100, a tier, and a
    confidence grade. Example: <b>69 &middot; Solid &middot; Confidence A</b>.</p>

    <table class="me-table">
      <thead><tr><th>Tier</th><th>Score</th><th>What it means</th></tr></thead>
      <tbody>
        <tr><td><b>Reliable</b></td><td class="num">80&ndash;100</td><td>Low deposit and withdrawal risk; real recourse if a dispute goes wrong</td></tr>
        <tr><td><b>Solid</b></td><td class="num">60&ndash;79</td><td>Contained risk; read the Watch Out section of the review</td></tr>
        <tr><td><b>Caution</b></td><td class="num">40&ndash;59</td><td>Concrete, documented risks; only play with money you can afford to lose</td></tr>
        <tr><td><b>High risk</b></td><td class="num">20&ndash;39</td><td>Active negative patterns; we advise against playing here</td></tr>
        <tr><td><b>Critical</b></td><td class="num">0&ndash;19</td><td>Active evidence of player harm</td></tr>
      </tbody>
    </table>

    <p>Closed casinos, suspended casinos and casinos on our internal blacklist carry no score at all.
    They are marked <b>Excluded</b>, with the reason and the date. A low score is information; a dead
    casino is not a score.</p>

    <!-- Formula -->
    <h2 id="formula">The formula</h2>
    <p><code>Score = Baseline + active events + FXCheck signal + group risk</code>, limited to the
    0&ndash;100 range, then checked against hard caps that no amount of positive points can override.
    The four parts:</p>

    <table class="me-table">
      <thead><tr><th>Component</th><th>What it is</th><th>Range</th></tr></thead>
      <tbody>
        <tr><td><b>Baseline</b></td><td>A checklist of verified structural facts: licence, terms, transparency, protection tools</td><td class="num">0 to 100</td></tr>
        <tr><td><b>Active events</b></td><td>Dated, sourced events from the public log, each losing weight over time</td><td class="num">open</td></tr>
        <tr><td><b>FXCheck&trade; signal</b></td><td>A live modifier from real player feedback over the last 90 days</td><td class="num">&minus;10 to +6</td></tr>
        <tr><td><b>Group risk</b></td><td>Severe events at sister casinos, transferred at a published rate</td><td class="num">&minus;15 to 0</td></tr>
      </tbody>
    </table>

    <!-- Baseline -->
    <h2 id="baseline">The baseline: what we verify on every casino</h2>
    <p>The baseline is a checklist worth up to 100 points. Every field carries a verification date and
    evidence &mdash; a screenshot, a regulator registry entry, the exact clause from the terms.
    Unverified fields score zero. We never assume the benefit of the doubt: a licence claimed in a
    footer but missing from the regulator&rsquo;s public registry is treated as the tier below, or as
    no licence at all.</p>

    <p><b>Licence and recourse &ndash; up to 40 points.</b> 32 for the licence tier, 8 for dispute
    resolution:</p>

    <table class="me-table">
      <thead><tr><th>Tier</th><th>Regulators</th><th>Points</th></tr></thead>
      <tbody>
        <tr><td><b>Tier 1</b></td><td>ADM, UKGC, MGA, Spelinspektionen, DGOJ, ANJ, KSA, AGCO</td><td class="num">32</td></tr>
        <tr><td><b>Tier 2</b></td><td>Gibraltar, Isle of Man, Alderney</td><td class="num">26</td></tr>
        <tr><td><b>Tier 3</b></td><td>Cura&ccedil;ao GCB (new regime) or Kahnawake, with the licence number verified in the public registry</td><td class="num">18</td></tr>
        <tr><td><b>Tier 4</b></td><td>Legacy Cura&ccedil;ao sub-licences, Anjouan, and other licences we cannot verify in a registry</td><td class="num">8</td></tr>
        <tr><td><b>Tier 5</b></td><td>No gambling licence; corporate registration only</td><td class="num">0</td></tr>
      </tbody>
    </table>

    <p>An independent dispute body (ADR) named in the terms <i>and</i> confirmed reachable by our staff
    adds 8 points; named but untested adds 4; absent adds nothing.</p>

    <p><b>Entity transparency &ndash; up to 12 points.</b> Operating company identified with a company
    registration number (4), licence number visible in the footer and consistent with the terms (4),
    no confusing brand fragmentation (4). When several unrelated entities run sites under the same
    casino name, that last field drops to zero: if you can&rsquo;t identify your counterparty, recourse
    is theoretical.</p>

    <p><b>Player protection tools &ndash; up to 16 points.</b> Two-factor authentication on accounts
    (4), self-service deposit and loss limits (6), self-service self-exclusion (6). Tools available
    only by writing to support score zero for that field.</p>

    <p><b>Fairness of the terms &ndash; up to 32 points.</b> We read the full terms and check them
    against a fixed list of eight predatory clauses. Each clause found costs 4 points, and the finding
    is published with the quoted clause:</p>

    <ol class="me-numbered">
      <li>Confiscation of dormant account balances beyond a reasonable, gradual fee</li>
      <li>Cashout caps that are a multiple of the deposit (the &ldquo;max 10x deposit&rdquo; pattern)</li>
      <li>A generic &ldquo;irregular play&rdquo; or &ldquo;bonus abuse&rdquo; clause with confiscation and no operative definition</li>
      <li>Maximum bet limits during bonus play that the software doesn&rsquo;t enforce, applied afterwards through confiscation</li>
      <li>Monthly withdrawal caps below &euro;5,000 or equivalent</li>
      <li>Terms changeable unilaterally and &ldquo;without notice&rdquo;, applied to bonuses already active</li>
      <li>A vague ban on &ldquo;mathematical advantage&rdquo; or low-risk betting, backed by confiscation</li>
      <li>Repeatable KYC requests at the operator&rsquo;s discretion, with no time limit and no cap on document cycles</li>
    </ol>

    <!-- Caps -->
    <h2 id="caps">Hard caps</h2>
    <p>Some risks cannot be compensated with points elsewhere. After the score is summed, these
    ceilings apply:</p>

    <table class="me-table">
      <thead><tr><th>Condition</th><th>Maximum score</th></tr></thead>
      <tbody>
        <tr><td>Tier 4 licence</td><td class="num">55</td></tr>
        <tr><td>Tier 5 (no verifiable licence)</td><td class="num">25</td></tr>
        <tr><td>Formal &ldquo;unfair terms&rdquo; ruling by a recognised watchdog, still active</td><td class="num">49</td></tr>
        <tr><td>Active blacklisting by a recognised watchdog</td><td class="num">19</td></tr>
      </tbody>
    </table>

    <p>The reasoning: no run of successful test withdrawals makes up for the absence of recourse.
    Without the caps, a casino could stack small positives over a broken foundation.</p>

    <!-- Event log -->
    <h2 id="event-log">The event log</h2>
    <p>Everything that moves the score after the baseline is an event: dated, sourced, weighted, and
    published in the timeline on the casino&rsquo;s review page. The log is append-only. We never edit
    or delete an event; if one turns out to be wrong, we revoke it with a public note, and the
    revocation itself stays visible. Event types and weights:</p>

    <table class="me-table">
      <thead><tr><th>Negative event</th><th>Points</th><th>Half-life</th></tr></thead>
      <tbody>
        <tr><td>Test withdrawal not paid, or confiscated without valid reason</td><td class="num">&minus;25</td><td class="num">24 months</td></tr>
        <tr><td>Blacklisted by a recognised watchdog</td><td class="num">&minus;20</td><td class="num">24 months</td></tr>
        <tr><td>Licence lapsed or revoked</td><td class="num">&minus;15</td><td class="num">24 months</td></tr>
        <tr><td>Winnings confiscated on a technicality during a staff test</td><td class="num">&minus;12</td><td class="num">24 months</td></tr>
        <tr><td>Formal warning by a recognised watchdog</td><td class="num">&minus;10</td><td class="num">24 months</td></tr>
        <tr><td>Formal &ldquo;unfair terms&rdquo; ruling</td><td class="num">&minus;8</td><td class="num">24 months</td></tr>
        <tr><td>Test withdrawal took 3&times; the declared time or more</td><td class="num">&minus;8</td><td class="num">12 months</td></tr>
        <tr><td>Verified pattern of independent player complaints (3+ in 90 days)</td><td class="num">&minus;8</td><td class="num">12 months</td></tr>
        <tr><td>KYC obstruction during a staff test (repeated document cycles, or over 7 days)</td><td class="num">&minus;6</td><td class="num">12 months</td></tr>
        <tr><td>Test withdrawal took 1.5&ndash;3&times; the declared time</td><td class="num">&minus;4</td><td class="num">12 months</td></tr>
        <tr><td>New predatory clause added to the terms</td><td class="num">&minus;4</td><td class="num">12 months</td></tr>
      </tbody>
    </table>

    <table class="me-table">
      <thead><tr><th>Positive event</th><th>Points</th><th>Half-life</th></tr></thead>
      <tbody>
        <tr><td>Licence upgraded to a higher tier</td><td class="num">+5</td><td class="num">12 months</td></tr>
        <tr><td>Test withdrawal paid within 24 hours</td><td class="num">+4</td><td class="num">12 months</td></tr>
        <tr><td>Predatory clause removed from the terms</td><td class="num">+4</td><td class="num">12 months</td></tr>
        <tr><td>Test withdrawal paid within the declared time</td><td class="num">+3</td><td class="num">12 months</td></tr>
        <tr><td>Removed from a watchdog blacklist (the blacklist event is also revoked)</td><td class="num">+2</td><td class="num">12 months</td></tr>
        <tr><td>Documented player dispute resolved in the player&rsquo;s favour</td><td class="num">+2</td><td class="num">3 months</td></tr>
      </tbody>
    </table>

    <div class="me-callout">Two built-in asymmetries are deliberate. Negative events weigh more than
    positive ones and decay more slowly, because the cost of recommending a casino that doesn&rsquo;t
    pay is far higher than the cost of underrating one that does. And positive test events are capped:
    successful withdrawals can add at most <b>9 active points</b> in total, so a casino cannot farm its
    way up the index while a structural problem stands.</div>

    <!-- Decay -->
    <h2 id="decay">Time decay: the index measures the casino of today</h2>
    <p>Every event loses weight as it ages: <code>weight today = base weight &times; 0.5 ^ (days since
    the event &divide; half-life)</code>. A &minus;10 warning is worth &minus;10 on the day it happens,
    about &minus;7 a year later, &minus;5 after two years. When an event&rsquo;s remaining weight falls
    below half a point it expires: it stops counting, but it stays visible in the timeline, greyed out.
    The history is never erased; it just stops weighing.</p>

    <p>This is a design choice. Most reputation systems accumulate forever, which punishes operators
    that genuinely cleaned up and shields decaying ones behind an old average. FXShield&trade; is a
    picture of the present, with the past available one click away.</p>

    <!-- FXCheck signal -->
    <h2 id="fxcheck-signal">The FXCheck&trade; signal</h2>
    <p>Every bonus page on FreeExtraChips asks players one question after they try an offer: did it
    work? Those yes/no reports power <a href="<?php echo $fxcheckUrl; ?>">FXCheck&trade;</a>, and the
    same data feeds a live component of FXShield&trade;. Over a rolling 90-day window, after duplicate
    and rate-limit filtering:</p>

    <p><code>signal = (success rate &minus; 0.70) &times; 20</code>, limited to the range &minus;10 to
    +6. Fewer than 10 valid reports means no signal at all &mdash; it lowers the confidence grade
    instead. The neutral point sits at 70% rather than 100% because a share of failed redemptions is
    player-side: expired codes, unread requirements. We will recalibrate that constant against real
    data and document the change in the version history below.</p>

    <p>The signal is damped on purpose: it only moves when the recalculated value differs by 2 points
    or more, and never by more than 3 points per weekly update. A sudden spike in reports &mdash; more
    than five times the 8-week average &mdash; freezes the signal at its previous value and flags the
    window for manual review. The worst a coordinated vote attack can do is bounded, slow, and reviewed
    by a human.</p>

    <!-- PRG -->
    <h2 id="prg">Payout Reality Gap</h2>
    <p>Next to the score, reviews of tested casinos show a second number: the Payout Reality Gap. It
    compares what the operator declares with what we measure. The declared figure is the worst end of
    the operator&rsquo;s own stated range (&ldquo;24&ndash;48 hours&rdquo; counts as 48). The measured
    figure is the median of our staff test withdrawals over the last 12 months, combined with
    time-stamped player reports at half weight. It takes at least three data points, including at least
    one staff test, before we publish it; below that, the review says &ldquo;not yet measured&rdquo;.
    Never an estimate.</p>

    <p>Example of the published line: <i>Declares: 24h &middot; Measured: 4.2 days &middot;
    PRG&nbsp;4.2&times;</i>. The gap itself doesn&rsquo;t add or remove points &mdash; the individual
    test withdrawals already entered the event log &mdash; but it is the single most honest number we
    can print about a casino.</p>

    <!-- Group risk -->
    <h2 id="group-risk">Related casinos: group risk</h2>
    <p>Casinos run in stables. When one brand in a group stops paying, its sisters share the
    management, the payment pipeline and often the same terms, so pretending they are unrelated would
    be negligent. FXShield&trade; transfers severe negative events between related casinos at a
    published rate:</p>

    <table class="me-table">
      <thead><tr><th>Relationship</th><th>Transfer rate</th></tr></thead>
      <tbody>
        <tr><td>Same owner and same licence holder</td><td class="num">35%</td></tr>
        <tr><td>Same owner</td><td class="num">25%</td></tr>
        <tr><td>Same licence holder, or same white-label platform</td><td class="num">15%</td></tr>
        <tr><td>Same affiliate programme only</td><td class="num">5%</td></tr>
      </tbody>
    </table>

    <p>Only the most serious events transfer: blacklistings, formal warnings, unpaid test withdrawals,
    confiscations, lapsed licences. The transferred event appears in the sister casino&rsquo;s timeline
    with a link to the original, decays on the same clock, and the total group effect is capped at
    &minus;15 points per casino. If the original event is revoked, every transferred copy is revoked
    with it.</p>

    <p>Positive events do not transfer. Risk travels through shared management; reliability has to be
    proven one brand at a time.</p>

    <!-- Confidence -->
    <h2 id="confidence">The confidence grade</h2>
    <p>A score is only as good as the evidence behind it, so every FXShield&trade; score carries a
    grade from A to D answering a separate question: how fresh and how first-hand is that evidence?
    Three factors:</p>

    <table class="me-table">
      <thead><tr><th>Factor</th><th>Weight</th><th>How it&rsquo;s measured</th></tr></thead>
      <tbody>
        <tr><td><b>Freshness</b></td><td class="num">50%</td><td>Days since our last hands-on check: within 90 days scores 1.0, within 180 days 0.7, within a year 0.4, older 0.1</td></tr>
        <tr><td><b>Verification</b></td><td class="num">30%</td><td>Share of the nine baseline checks verified first-hand by our staff rather than taken from external sources</td></tr>
        <tr><td><b>Player sample</b></td><td class="num">20%</td><td>Valid FXCheck&trade; reports in the window: 30 or more scores 1.0, 10&ndash;29 scores 0.6, 1&ndash;9 scores 0.3, none 0.0</td></tr>
      </tbody>
    </table>

    <p>The weighted total maps to a grade: <b>A</b> at 0.80 or higher, <b>B</b> at 0.60, <b>C</b> at
    0.35, <b>D</b> below that. New casinos always start at D, labelled &ldquo;limited data &ndash;
    first verification in progress&rdquo;, until a staff check exists. In our listings, a D-grade
    casino never outranks a casino of the same tier holding grade B or better.</p>

    <p>Score and confidence are independent on purpose. <i>7 &middot; Critical &middot; Confidence
    A</i> is a legitimate result: it means we are very sure the casino is a bad place for your money.
    Most rating systems publish a number and leave you to guess how much the publisher believes it.
    We print it.</p>

    <!-- Worked example -->
    <h2 id="worked-example">A worked example</h2>
    <p>A casino licensed under the new Cura&ccedil;ao GCB regime, number verified in the registry (18
    points), with a named but untested ADR (4). Entity fully transparent (12). Self-service deposit
    limits but no two-factor authentication and support-only self-exclusion (6). Two predatory clauses
    in the terms &mdash; a deposit-multiple cashout cap and a vague irregular-play clause &mdash;
    published with quotes (24 of 32). Baseline: <b>64</b>.</p>

    <p>Two months ago a staff test withdrawal was paid within the declared time: +3, decayed to +2.7
    today. FXCheck&trade; shows 41 valid reports at an 82% success rate: signal +2. No group events.
    Total: 64 + 2.7 + 2 = 68.7, rounded to <b>69 &middot; Solid</b>. The staff check is recent, eight
    of nine baseline fields are verified first-hand, and the player sample is strong: <b>Confidence
    A</b>. Every number in this paragraph would be visible in that casino&rsquo;s public timeline, with
    the evidence linked.</p>

    <!-- Integrity -->
    <h2 id="integrity">What keeps the score honest</h2>
    <p>The event log is append-only, and corrections are public. If we get something wrong, the event
    is revoked with a dated note in the timeline saying what was wrong and why &mdash; the correction
    is as visible as the accusation was.</p>

    <p>External findings &mdash; blacklistings, warnings, unfair-terms rulings &mdash; only enter the
    log from a fixed list of recognised watchdogs and regulator registries, and only after an editor
    confirms the finding at the source. Forum posts and social media reports never enter the log
    directly; at most, three or more independent, documented complaints within 90 days can become a
    single verified complaint-pattern event after editorial review.</p>

    <p>Operators can dispute any event through our
    <a href="mailto:corrections@freeextrachips.com">corrections inbox</a>, with evidence. We answer
    within 14 days. An accepted dispute ends in a public revocation note; a rejected one leaves the
    event standing. What an operator cannot do, under any commercial arrangement, is buy a point.</p>

    <!-- Independence -->
    <h2 id="independence">Independence</h2>
    <p>FreeExtraChips earns affiliate commissions from some of the casinos we review, disclosed as
    described in our <a href="/methodology/editorial-standards">editorial standards</a>. Commercial
    status is not an input to FXShield&trade;: the calculation reads the verified baseline, the event
    log, the FXCheck&trade; window and the group relations &mdash; nothing else. There is no field for
    it. The score computes itself from the log, and the only way to change it is to add evidence.</p>

    <!-- Versions -->
    <h2 id="versions">Version history</h2>
    <p>The formula is versioned. Any change to a weight, threshold, half-life or coefficient produces a
    new version, a note in this section, and a dated &ldquo;recalculated under formula vX.X&rdquo;
    entry in every affected timeline. Scores are never silently re-based.</p>
    <ul class="me-bullets">
      <li><b>SI v1.1</b> &mdash; 21 Aug 2026.
      Event taxonomy extended with <code>LIC_CHANGED</code> (&minus;5, standard decay): lateral licence
      migrations &mdash; an operator leaving one licensing regime for another without an upgrade or a
      revocation &mdash; now enter the ledger as a dated, decaying signal. Weights, thresholds and caps
      are unchanged; no existing score was re-based by this release.</li>
      <li><b>SI v1.0</b> &mdash; <?php echo ($lastUpdated !== '') ? $h($lastUpdated) : 'at launch'; ?>.
      Initial public release of the formula documented on this page.</li>
    </ul>

    <!-- FAQ -->
    <h2 id="faq">Frequently asked questions</h2>

    <div class="me-faq">
      <details>
        <summary>Can a casino pay to improve its FXShield&trade; score?</summary>
        <p>No. Commercial relationships are not an input to the calculation, and the formula published
        on this page is the entire formula. The only way a score rises is new evidence: a verified
        licence upgrade, removed predatory clauses, paid test withdrawals, or sustained positive player
        reports.</p>
      </details>

      <details>
        <summary>Why did a score change when the review didn&rsquo;t?</summary>
        <p>The score recalculates weekly even when nothing new happens: older events lose weight under
        the published half-lives, the 90-day FXCheck&trade; window rolls forward, and group-risk events
        decay. The timeline on the review page shows exactly which components moved.</p>
      </details>

      <details>
        <summary>What does a low confidence grade mean?</summary>
        <p>That the evidence behind the score is thin or stale &mdash; usually a casino we haven&rsquo;t
        hands-on tested recently, or one with few player reports. It is not a hidden penalty on the
        score; it is a separate, honest statement about how much weight to give it.</p>
      </details>

      <details>
        <summary>Why is a casino penalised for something a sister brand did?</summary>
        <p>Because the brands share management, payment processing and often identical terms. The
        transfer rates are published above, only severe events transfer, the effect is capped at
        &minus;15 points, and the transferred event in the timeline links to the original so you can
        judge the connection yourself.</p>
      </details>

      <details>
        <summary>How is FXShield&trade; different from other casino safety ratings?</summary>
        <p>Three things. Every weight, threshold and coefficient is published on this page &mdash;
        nothing is a trade secret. Every point traces to a dated, sourced event in a public timeline,
        including our own hands-on withdrawal tests and measured payout times. And every score carries
        a confidence grade that tells you how fresh and first-hand the evidence is.</p>
      </details>
    </div>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar">

    <!-- TOC -->
    <div class="au-side-card">
      <h4>On this page</h4>
      <nav class="me-toc">
        <a href="#scope">What it measures</a>
        <a href="#formula">The formula</a>
        <a href="#baseline">The baseline</a>
        <a href="#caps">Hard caps</a>
        <a href="#event-log">The event log</a>
        <a href="#decay">Time decay</a>
        <a href="#fxcheck-signal">The FXCheck&trade; signal</a>
        <a href="#prg">Payout Reality Gap</a>
        <a href="#group-risk">Group risk</a>
        <a href="#confidence">The confidence grade</a>
        <a href="#worked-example">Worked example</a>
        <a href="#integrity">What keeps it honest</a>
        <a href="#independence">Independence</a>
        <a href="#versions">Version history</a>
        <a href="#faq">FAQ</a>
      </nav>
    </div>

    <!-- Methodology meta -->
    <div class="au-side-card">
      <h4>Methodology</h4>
      <?php if ($version !== ''): ?>
        <div class="row"><span class="k">Formula</span><span class="v"><?php echo $h($version); ?></span></div>
      <?php endif; ?>
      <?php if ($lastUpdated !== ''): ?>
        <div class="row"><span class="k">Last updated</span><span class="v"><?php echo $h($lastUpdated); ?></span></div>
      <?php endif; ?>
      <?php if ($engineLive): ?>
        <div class="row"><span class="k">Casinos scored</span><span class="v"><?php echo $nfmt($casinosScored); ?></span></div>
        <div class="row"><span class="k">Last recalculation</span><span class="v"><?php echo $lastCalcFmt; ?></span></div>
      <?php endif; ?>
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
      <h4>Spot something wrong?</h4>
      <p>Every weight on this page is the live formula. If a published score doesn&rsquo;t match the
      math, or an event in a timeline is wrong, tell us &mdash; corrections are public.</p>
      <a class="btn" href="mailto:corrections@freeextrachips.com">File a correction <?php echo $arrowUpRight; ?></a>
    </div>

  </aside>
</section>

</div><!-- /.fecx -->

<?php echo $crumbJsonLd; ?>

<?php if (!empty($this->jsonLdGraph)): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>

<?php if (!empty($faqLdJson)): ?>
<script type="application/ld+json"><?php echo $faqLdJson; ?></script>
<?php endif; ?>
