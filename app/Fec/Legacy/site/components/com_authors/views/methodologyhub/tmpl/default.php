<?php
defined('_JEXEC') or die;

/**
 * Methodology Hub default template.
 *
 * Reused fec-edition.css primitives:
 *   .au-hero, .au-stats-strip, .wrap, .eyebrow
 *
 * New me-* (in methodology.css, hub-specific block):
 *   .me-hub-grid       — 2-column responsive grid of methodology cards
 *   .me-hub-card       — individual methodology card
 *   .me-hub-card .badge — Live / Coming soon / External status
 *
 * Plumbed by views/methodologyhub/view.html.php:
 *   $this->topics      registry from AuthorsModelMethodology::getTopics()
 *   $this->stats       aggregate stats (total_reports, methodologies_live, ...)
 *   $this->jsonLdGraph pre-encoded JSON-LD @graph
 */

$topics = is_array($this->topics) ? $this->topics : array();
$stats  = is_array($this->stats)  ? $this->stats  : array();

$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$nfmt = function ($n) { return number_format((int)$n); };

$recentDays = isset($stats['recent_window_days']) ? (int)$stats['recent_window_days'] : 30;

// Last methodology update — friendly format
$lastUpd = '';
if (!empty($stats['last_methodology_update'])) {
    $ts = strtotime($stats['last_methodology_update']);
    if ($ts) $lastUpd = date('j F Y', $ts);
}

// Breadcrumb (uses fec_render_breadcrumb_html if available)
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
                    $crumbItems[$lastIdx][$k] = 'How We Verify';
                }
            }
        }
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
        if (function_exists('fec_render_breadcrumb_jsonld')) {
            $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
        }
    }
}

$arrowUpRight = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';

// Card link helper — internal (live), external (FRank-style), or disabled (coming soon)
$cardUrlFor = function ($slug, $t) {
    if (!isset($t['status'])) return '';
    if ($t['status'] === 'live') {
        // Route by kind, as the admin list does (2.7.38): page topics live under
        // the page view and their own SEF URL; the methodology view refuses them
        // (404). A topic with no kind is methodology (pre-2.7.38 rows).
        $kind = (isset($t['kind']) && $t['kind'] !== '') ? $t['kind'] : 'methodology';
        if ($kind === 'page') {
            return JRoute::_('index.php?option=com_authors&view=page&slug=' . urlencode($slug));
        }
        return JRoute::_('index.php?option=com_authors&view=methodology&topic=' . urlencode($slug));
    }
    if ($t['status'] === 'external' && !empty($t['external_url'])) {
        return (string)$t['external_url'];
    }
    return ''; // coming_soon → no URL, card not clickable
};

?>

<div class="fecx me-page">

<?php echo $crumbHtml; ?>

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow">Methodology</div>
        <h1 class="me-title">How We Verify</h1>
        <p class="me-tagline">
          Every bonus listed on FreeExtraChips passes through editorial review and player-verified
          checks. These methodology pages document exactly how we do it &mdash; what signals we use,
          what thresholds we apply, and how decisions get made.
        </p>
      </div>
    </div>

    <!-- Aggregate trust stats -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['total_reports']) ? $stats['total_reports'] : 0); ?></div>
        <div class="lbl">Player reports</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['casinos_covered']) ? $stats['casinos_covered'] : 0); ?></div>
        <div class="lbl">Casinos covered</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt(isset($stats['reports_recent']) ? $stats['reports_recent'] : 0); ?></div>
        <div class="lbl">Last <?php echo $recentDays; ?> days</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo (int)(isset($stats['methodologies_live']) ? $stats['methodologies_live'] : 0); ?>/<?php echo (int)(isset($stats['methodologies_total']) ? $stats['methodologies_total'] : 0); ?></div>
        <div class="lbl">Methodologies live</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">
  <article class="me-content">

    <h2 id="approach">Our approach in one paragraph</h2>
    <p>FreeExtraChips combines three independent verification systems. <b>FRank</b> ranks the casinos
    themselves, based on license validity, software stack, banking options, and weighted player reviews
    &mdash; running continuously since 2007. <b>FXCheck™</b> verifies whether individual bonus offers
    are still working, using real-time Yes/No reports from players who attempted the offer. <b>In-game
    RTP verification</b> catches bonuses tied to slot configurations whose payout percentage is set
    below the provider's published value. Together they cover the three places where a casino bonus
    can disappoint a player: a dodgy operator, a stale offer, or a quietly nerfed game.</p>

    <h2 id="methodologies">The methodologies</h2>

    <div class="me-hub-grid">
      <?php foreach ($topics as $slug => $t):
        $title    = isset($t['title']) ? (string)$t['title'] : $slug;
        $desc     = isset($t['short_description']) ? (string)$t['short_description'] : '';
        $status   = isset($t['status']) ? (string)$t['status'] : 'coming_soon';
        $url      = $cardUrlFor($slug, $t);
        $clickable = ($url !== '');
        $cardTag  = $clickable ? 'a' : 'div';

        // Badge per status
        switch ($status) {
            case 'live':         $badgeCls = 'tag tag-ok';   $badgeTxt = 'Live'; break;
            case 'external':     $badgeCls = 'tag tag-info'; $badgeTxt = 'Existing page'; break;
            case 'coming_soon':
            default:             $badgeCls = 'tag tag-mute'; $badgeTxt = 'Coming soon'; break;
        }
      ?>
        <<?php echo $cardTag; ?>
          class="me-hub-card<?php echo $clickable ? '' : ' me-hub-card--disabled'; ?>"
          <?php if ($clickable): ?>href="<?php echo $h($url); ?>"<?php endif; ?>
        >
          <div class="me-hub-card-head">
            <h3><?php echo $h($title); ?></h3>
            <span class="<?php echo $badgeCls; ?>"><?php echo $h($badgeTxt); ?></span>
          </div>
          <?php if ($desc !== ''): ?>
            <p class="me-hub-card-desc"><?php echo $h($desc); ?></p>
          <?php endif; ?>
          <?php
            // v2.7.8 — show methodology version + last update on each card.
            // Strip a leading 'v' so legacy values like 'v3' don't render as 'vv3'.
            $cardVersion = isset($t['version']) ? trim((string)$t['version']) : '';
            $cardVersion = ltrim($cardVersion, 'vV');
            $cardUpdated = isset($t['last_updated']) ? trim((string)$t['last_updated']) : '';
            $hasVersion  = ($cardVersion !== '');
            $hasUpdated  = ($cardUpdated !== '' && $cardUpdated !== '0000-00-00');
          ?>
          <?php if ($hasVersion || $hasUpdated): ?>
            <p class="me-hub-card-meta">
              <?php if ($hasVersion): ?>
                <span class="meta-version">v<?php echo $h($cardVersion); ?></span>
              <?php endif; ?>
              <?php if ($hasVersion && $hasUpdated): ?>
                <span class="meta-sep" aria-hidden="true">·</span>
              <?php endif; ?>
              <?php if ($hasUpdated): ?>
                <span class="meta-updated">updated <?php echo $h(date('j M Y', strtotime($cardUpdated))); ?></span>
              <?php endif; ?>
            </p>
          <?php endif; ?>
          <?php if ($clickable): ?>
            <div class="me-hub-card-cta">
              <?php echo ($status === 'external') ? 'Open page' : 'Read methodology'; ?>
              <?php echo $arrowUpRight; ?>
            </div>
          <?php endif; ?>
        </<?php echo $cardTag; ?>>
      <?php endforeach; ?>
    </div>

    <h2 id="updates">When this gets updated</h2>
    <p>Methodologies are versioned. Material changes &mdash; thresholds, weighting, anti-spam logic,
    audit-trail policy &mdash; are logged with date and rationale on each methodology page. The most
    recent update across all methodologies was on
    <b><?php echo $lastUpd !== '' ? $h($lastUpd) : '&mdash;'; ?></b>.</p>

    <p>If you spot something that doesn't match what you see on a bonus page or in the cashier, tell us
    via the <a href="mailto:corrections@freeextrachips.com">corrections inbox</a>. Corrections from real
    players are the single most important input to keeping these methodologies honest.</p>

  </article>

  <aside class="au-sidebar">
    <div class="au-side-card">
      <h4>What this page covers</h4>
      <nav class="me-toc">
        <a href="#approach">Our approach</a>
        <a href="#methodologies">The methodologies</a>
        <a href="#updates">When this gets updated</a>
      </nav>
    </div>

    <div class="au-side-card">
      <h4>Trust snapshot</h4>
      <?php if (!empty($stats['total_reports'])): ?>
        <div class="row"><span class="k">Player reports</span><span class="v"><?php echo $nfmt($stats['total_reports']); ?></span></div>
      <?php endif; ?>
      <?php if (!empty($stats['casinos_covered'])): ?>
        <div class="row"><span class="k">Casinos covered</span><span class="v"><?php echo $nfmt($stats['casinos_covered']); ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="k">Methodologies live</span><span class="v"><?php echo (int)$stats['methodologies_live']; ?> / <?php echo (int)$stats['methodologies_total']; ?></span></div>
      <?php if ($lastUpd !== ''): ?>
        <div class="row"><span class="k">Last update</span><span class="v"><?php echo $h($lastUpd); ?></span></div>
      <?php endif; ?>
    </div>

    <div class="au-side-cta">
      <h4>Found a discrepancy?</h4>
      <p>The numbers and statuses on these pages come straight from the database. If something is wrong, we want to know.</p>
      <a class="btn" href="mailto:corrections@freeextrachips.com">File a correction <?php echo $arrowUpRight; ?></a>
    </div>
  </aside>
</section>

</div><!-- /.fecx -->

<?php echo $crumbJsonLd; ?>

<?php if (!empty($this->jsonLdGraph)): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>
