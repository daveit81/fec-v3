<?php
defined('_JEXEC') or die;

/**
 * RTP verification methodology page (Joomla layout suffix: default_rtp-verification.php).
 *
 * Mirrors default_fxcheck.php scaffolding (hero, stats strip, content/sidebar
 * layout, JSON-LD plumbed by the view). Reuses the same fec-edition.css +
 * methodology.css tokens — no new CSS file shipped with this template.
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('rtp-verification')
 *   $this->config       topic config (title, tagline, author/reviewer slugs, version, last_updated)
 *   $this->stats        array from AuthorsModelMethodology::getRtpStats()
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
$slotsDocumented = isset($stats['slots_documented'])        ? (int)$stats['slots_documented']        : 0;
$slotsWithRtp    = isset($stats['slots_with_rtp'])          ? (int)$stats['slots_with_rtp']          : 0;
$slotsArchive    = isset($stats['slots_with_archive'])      ? (int)$stats['slots_with_archive']      : 0;
$slotsMultiTier  = isset($stats['slots_multi_tier'])        ? (int)$stats['slots_multi_tier']        : 0;
$slotsObs        = isset($stats['slots_with_observations']) ? (int)$stats['slots_with_observations'] : 0;
$obsTotal        = isset($stats['observations_total'])      ? (int)$stats['observations_total']      : 0;
$providersTop    = isset($stats['providers_top']) && is_array($stats['providers_top']) ? $stats['providers_top'] : array();

$lastVerifiedFmt = '—';
if (!empty($stats['last_verified_ts'])) {
    $diff = time() - (int)$stats['last_verified_ts'];
    if      ($diff < 86400)       $lastVerifiedFmt = 'Today';
    elseif  ($diff < 86400 * 2)   $lastVerifiedFmt = 'Yesterday';
    elseif  ($diff < 86400 * 30)  $lastVerifiedFmt = floor($diff / 86400) . ' days ago';
    else                          $lastVerifiedFmt = date('M Y', (int)$stats['last_verified_ts']);
}

// ---- Breadcrumb -----------------------------------------------------------
$crumbHtml = '';
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
$hubUrl     = JRoute::_('index.php?option=com_authors&view=methodologyhub');

$lastUpdated = !empty($cfg['last_updated']) ? date('j F Y', strtotime($cfg['last_updated'])) : '';
$version     = !empty($cfg['version']) ? (string)$cfg['version'] : '';

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

    <!-- Live stats strip -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo $nfmt($slotsDocumented); ?></div>
        <div class="lbl">Slots documented</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt($slotsArchive); ?></div>
        <div class="lbl">Sources archived</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt($slotsMultiTier); ?></div>
        <div class="lbl">Multi-tier RTP</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt($obsTotal); ?></div>
        <div class="lbl">Per-casino observations</div>
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
        Most casino review sites publish a single RTP number per slot, copied from the provider's marketing
        page. That number is often wrong for the specific operator you're playing at. Many slot providers
        — most prominently <b>Pragmatic Play</b> and <b>Play'n GO</b> — ship multiple RTP versions of the
        same game, and the operator chooses which version to serve. Wolf Gold can run at 96.01% on one
        casino and 88.13% on another. Same game, same provider, same logo on the lobby tile.
      </p>
      <p>
        FreeExtraChips verifies RTP at two layers: <b>provider-declared</b> (what the official primary source
        publishes, archived on the Wayback Machine for accountability) and <b>operator-observed</b> (what the
        in-game paytable actually shows at a specific casino, documented with timestamped screenshots).
        Where a casino is configured below the provider's headline value, we flag the affected bonuses.
      </p>
    </div>

    <h2 id="why">Why a single RTP number isn't enough</h2>
    <p>
      RTP (Return To Player) is the percentage of all wagered money a slot pays back to players over time.
      A 96% RTP means that, over a long enough horizon, $96 of every $100 wagered comes back as wins —
      and $4 is the house edge. It's a statistical long-run figure, not a guarantee for any one session,
      but it's the most important math fact about any slot.
    </p>
    <p>
      The catch is that "the RTP of slot X" is not always a single number. Three independent factors
      can change what the actual RTP is on a given spin:
    </p>
    <ol>
      <li>
        <b>Provider tier selection.</b> Several major providers ship the same slot in multiple
        configurations — typically 96.x%, 94.x%, and 88.x%. The operator picks one at integration time.
        Pragmatic Play is the best-known example, but Play'n GO, Habanero, and parts of the Yggdrasil
        catalogue do this too.
      </li>
      <li>
        <b>Bonus-round dynamics.</b> Some slots have separate RTP figures for base game vs. bonus rounds,
        or for jackpot-pool versions of the same game. We document each variant separately when applicable.
      </li>
      <li>
        <b>Operator restrictions during bonus play.</b> Even if the slot itself is configured at the
        headline RTP, a bonus's terms may exclude it or weight it at a lower contribution — which is a
        different problem, but one that compounds with low-tier configuration.
      </li>
    </ol>

    <h2 id="multi-tier">Multi-tier RTP: the Wolf Gold case study</h2>
    <p>
      <a href="https://www.pragmaticplay.com/en/games/wolf-gold/" target="_blank" rel="nofollow noopener">Wolf Gold</a>
      by Pragmatic Play is one of the most widely distributed slots in the industry. Its official RTP
      is published as <b>96.01%</b>. That number appears on Pragmatic's website, in their B2B sales decks,
      and in nearly every third-party slot database.
    </p>
    <p>
      What's almost never mentioned: Pragmatic Play also offers Wolf Gold to operators at <b>94.06%</b> and
      <b>88.13%</b>. Those alternative configurations exist in the same game certification and are picked
      at the operator's discretion. The difference between 96.01% and 88.13% is nearly <b>8 percentage points</b>
      of house edge — meaning a player at the low-tier configuration loses, on average, roughly four times
      faster than at the headline tier.
    </p>
    <p>
      For every multi-tier slot in our database we record:
    </p>
    <ul>
      <li>The <b>highest</b> tier the provider documents (<code>rtp_theoretical_max</code>).</li>
      <li>The <b>lowest</b> tier the provider documents (<code>rtp_theoretical_min</code>).</li>
      <li>The <b>full list of variants</b>, when available (<code>rtp_variants_json</code>).</li>
      <li>An archived snapshot of the provider page on the date we recorded the values.</li>
    </ul>
    <p>
      As of <?php echo $h($lastUpdated ?: $lastVerifiedFmt); ?>, <b><?php echo $nfmt($slotsMultiTier); ?></b>
      slot<?php echo $slotsMultiTier === 1 ? '' : 's'; ?> in our database carry multiple documented RTP tiers.
    </p>

    <h2 id="sources">What counts as a primary source</h2>
    <p>
      We rank sources in three tiers of evidential weight, in descending order of trust:
    </p>
    <ol>
      <li>
        <b>Regulator certificates</b> from accredited testing labs (eCOGRA, GLI, iTechLabs, BMM). These
        are the highest-trust source where available — they document the exact RTP a game was tested at
        and the date of certification. Most providers don't publish them; when they do, we cite them.
      </li>
      <li>
        <b>Provider game pages</b> — the official URL where the developer of the slot publishes the
        game's data sheet. For NetEnt slots this is now <code>games.evolution.com/slots/&lt;slug&gt;/</code>
        (NetEnt was acquired by Evolution in 2020). For Play'n GO, <code>playngo.com/games/&lt;slug&gt;</code>.
        For Pragmatic Play, <code>pragmaticplay.com/en/games/&lt;slug&gt;/</code>.
      </li>
      <li>
        <b>In-game paytable</b> — the info panel inside the slot itself, as rendered by a specific
        operator. This is the only source we have for providers that don't publish public game pages
        (RTG, SpinLogic, Rival Gaming, IGT). We document it via timestamped screenshots tied to a
        specific casino and a specific date.
      </li>
    </ol>
    <p>
      Aggregator sites (SlotCatalog, AskGamblers, slotsmate.com) are not considered primary sources.
      They're useful for cross-referencing, but we don't cite them as the basis for any value in our
      database. Their numbers are copied from somewhere; we want to be that "somewhere."
    </p>

    <h2 id="wayback">Why we archive every primary source on Wayback</h2>
    <p>
      Provider web pages change. A slot's RTP value can be updated, removed entirely, or relocated to
      a different URL when a developer is acquired (as happened to NetEnt). If we cite a URL today and
      that URL serves different content tomorrow, our claim becomes unverifiable.
    </p>
    <p>
      For every slot we document, we trigger an archive on the
      <a href="https://web.archive.org/" target="_blank" rel="nofollow noopener">Internet Archive's Wayback Machine</a>
      at the moment of verification. The archived URL is stored alongside the live URL in our database.
      As of <?php echo $h($lastUpdated ?: $lastVerifiedFmt); ?>, we have archived snapshots for
      <b><?php echo $nfmt($slotsArchive); ?></b> of our <b><?php echo $nfmt($slotsDocumented); ?></b>
      documented slots.
    </p>
    <p>
      A reader who wants to verify any single claim on a FreeExtraChips slot page can follow the
      archived URL and see the exact provider content we based the claim on — even if the live URL
      has since changed.
    </p>

    <h2 id="opaque">When providers don't publish RTP: the opaque tier</h2>
    <p>
      Several major providers do not maintain public game pages with RTP values. <b>RTG</b> (Real Time
      Gaming) and its successor <b>SpinLogic Gaming</b>, <b>Rival Gaming</b>, and the legacy <b>IGT</b>
      online catalogue are the most common examples in the US-facing market our database covers.
      Their slots collectively account for a large share of bonus offers — Cash Bandits 3 alone
      appears in 14 active bonuses on FreeExtraChips at the time of writing.
    </p>
    <p>
      For these providers we cannot cite a primary source URL. Instead we use a two-step process:
    </p>
    <ol>
      <li>
        Open the slot at an operator that carries it (e.g., SlotoCash for SpinLogic titles, Vegas
        Crest for Rival). Access the in-game paytable / info panel. Take a screenshot timestamped
        with the date and operator name. Record the RTP value the paytable shows.
      </li>
      <li>
        Repeat at one or two additional operators. If the same slot shows different RTP values
        across operators, document each — this confirms the provider ships multiple configurations
        even though they aren't publicly disclosed.
      </li>
    </ol>
    <p>
      We mark these slots with a <b>transparency flag</b> in our reviews. The absence of a public
      provider source is itself useful information for players evaluating where to play.
    </p>

    <h2 id="observations">Per-casino observations: closing the loop</h2>
    <p>
      The theoretical RTP a provider declares is one fact. The RTP a specific casino actually serves
      is a second fact, and the two don't always agree. To document the second, we maintain a
      separate per-casino observation log: each entry pairs a slot with a casino and records the RTP
      observed in the in-game paytable on a specific date, with a screenshot.
    </p>
    <p>
      As of <?php echo $h($lastUpdated ?: $lastVerifiedFmt); ?> we have
      <b><?php echo $nfmt($obsTotal); ?></b> observation<?php echo $obsTotal === 1 ? '' : 's'; ?>
      covering <b><?php echo $nfmt($slotsObs); ?></b> slot<?php echo $slotsObs === 1 ? '' : 's'; ?>.
      This is a coverage we're building up over time; we don't claim to have every slot–casino pair
      documented. When a bonus is offered specifically on a slot we've observed and the observed
      RTP is below the headline tier, we surface that fact in the bonus listing.
    </p>

    <h2 id="updates">Update and correction process</h2>
    <p>
      RTP data is not static. Providers release new versions of games, change configurations, and
      occasionally update their certifications. Operators can switch RTP tiers without notice.
      We treat the database as living data:
    </p>
    <ul>
      <li>
        <b>Scheduled refresh.</b> A weekly job re-fetches the provider primary URLs for the slots
        with public game pages, parses the listed RTP, and flags any record where the current value
        differs from what we have stored. Flagged records are reviewed by an editor — never
        auto-updated. We've seen scrapers replace correct historical data with wrong new data when
        a CDN serves a stale page; a human always confirms.
      </li>
      <li>
        <b>Reader corrections.</b> Any slot data page on FreeExtraChips carries a "Spot a mistake?"
        link that opens a contact form pre-filled with the slot's identifier. Submissions are routed
        to <code>editorial@freeextrachips.com</code> and acknowledged within five business days.
      </li>
      <li>
        <b>Version log.</b> Every change to a slot's record is timestamped with the editor who made
        the change and a brief reason (e.g., "Provider updated 2024-12 from 96.21% to 96.10%").
        Older values are kept in the version log, not overwritten. Readers can see how the RTP we
        report has evolved over time.
      </li>
      <li>
        <b>Methodology versioning.</b> This page itself is versioned. The methodology described
        here is version <?php echo $h($version ?: 'v1'); ?>, last updated <?php echo $h($lastUpdated ?: 'today'); ?>.
        Material changes to how we verify RTP will be released as a new version with a changelog
        entry, not a silent edit.
      </li>
    </ul>

    <h2 id="limits">What this methodology does not promise</h2>
    <p>
      We are explicit about the limits of what RTP verification can tell a player:
    </p>
    <ul>
      <li>
        <b>RTP is a long-run statistical figure.</b> It does not predict the outcome of any single
        spin or session. A slot at 96% RTP can pay out 50% over an hour or 200% over an hour. The
        figure converges over millions of spins, not over an evening's play.
      </li>
      <li>
        <b>Verifying RTP does not endorse a slot.</b> A high RTP doesn't make a slot a good bet —
        volatility, hit frequency, and bonus terms all matter. We document RTP because it's
        verifiable, not because it's the only thing that matters.
      </li>
      <li>
        <b>We cannot audit RNG fairness.</b> RTP figures presuppose that the random number generator
        in the slot is correctly implemented. That's the role of accredited testing labs, not ours.
        Our job is to make sure players know what RTP the operator is configured to serve.
      </li>
    </ul>

    <h2 id="faq">Frequently asked questions</h2>

    <div class="me-faq">
    <details>
      <summary>Can a casino change a slot's RTP after I've started playing?</summary>
      <p>
        Yes, in principle. An operator can switch the configured RTP tier of a slot by deploying a
        different version of the game integration. This typically happens at scheduled maintenance
        windows, not mid-session. We re-verify our observed RTP values on a rolling cadence; if a
        casino has switched a slot to a lower tier since our last check, our next observation will
        catch it and we'll flag the change.
      </p>
    </details>

    <details>
      <summary>Why don't you just trust the provider's published number?</summary>
      <p>
        Because the provider's published number is the headline tier. When Pragmatic Play publishes
        Wolf Gold at 96.01%, that's truthful — it's one of the values Wolf Gold can run at. But
        it's not necessarily the value <i>your</i> casino is running. The provider page is a
        starting point, not a conclusion.
      </p>
    </details>

    <details>
      <summary>How do you find out which tier an operator is using?</summary>
      <p>
        The in-game paytable, accessible inside the slot's info panel, shows the RTP the operator
        has configured. This is the only authoritative way to know what tier you're actually playing
        at. Some operators also disclose the figure in their bonus terms or in a "fairness" section
        of their site; when they do, we cross-reference it with the paytable.
      </p>
    </details>

    <details>
      <summary>What's the difference between this methodology and FXCheck™?</summary>
      <p>
        <a href="<?php echo $fxcheckUrl; ?>">FXCheck™</a> tracks whether a bonus is actually working — a
        binary signal sourced from player Yes/No reports. RTP verification tracks the configured payout
        percentage of the slots those bonuses target. They're complementary: FXCheck™ tells you the
        bonus exists and is honoured; RTP verification tells you whether the slot you'll be required
        to play through it is running at the headline payout or a lower-tier configuration.
      </p>
    </details>

    <details>
      <summary>Why no RTG primary URL?</summary>
      <p>
        RTG (Real Time Gaming) and SpinLogic Gaming, its post-2020 successor, do not maintain public
        game data pages. They distribute game info sheets to licensed operators, not to the public
        web. We've requested public access; we have not received it. Until that changes, in-game
        paytables are the most authoritative source we can cite for their catalogue, and we document
        them carefully.
      </p>
    </details>

    <details>
      <summary>Do you accept paid placement for higher-RTP claims?</summary>
      <p>
        No. RTP data is editorial; it's never affected by commercial relationships with operators.
        See our <a href="/affiliate-advertiser-disclosure">affiliate disclosure</a> for the full
        firewall between commercial and editorial.
      </p>
    </details>
    </div><!-- /.me-faq -->

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar">

    <div class="au-side-card me-toc">
      <div class="me-toc-label">On this page</div>
      <ul>
        <li><a href="#why">Why a single RTP number isn't enough</a></li>
        <li><a href="#multi-tier">Multi-tier RTP: Wolf Gold case study</a></li>
        <li><a href="#sources">What counts as a primary source</a></li>
        <li><a href="#wayback">Wayback archiving</a></li>
        <li><a href="#opaque">Opaque providers (RTG, Rival, IGT)</a></li>
        <li><a href="#observations">Per-casino observations</a></li>
        <li><a href="#updates">Update and correction process</a></li>
        <li><a href="#limits">What this doesn't promise</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>

    <?php if (!empty($providersTop)): ?>
    <div class="au-side-card">
      <div class="me-toc-label">Top providers documented</div>
      <ul class="me-side-list">
        <?php foreach ($providersTop as $p): ?>
          <li>
            <span><?php echo $h($p['name']); ?></span>
            <span class="me-side-count"><?php echo $nfmt($p['count']); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="au-side-card">
      <div class="me-toc-label">Related</div>
      <ul class="me-side-list">
        <li><a href="<?php echo $fxcheckUrl; ?>">FXCheck™ methodology</a></li>
        <li><a href="<?php echo $hubUrl; ?>">All methodologies</a></li>
        <li><a href="/affiliate-advertiser-disclosure">Affiliate disclosure</a></li>
      </ul>
    </div>

    <div class="au-side-cta">
      <div class="me-cta-label">Spot a mistake?</div>
      <p>
        RTP data is published with sources cited. If a value looks wrong, mail us — we acknowledge
        within 5 business days and document every correction.
      </p>
      <a class="me-cta-btn" href="mailto:editorial@freeextrachips.com?subject=RTP%20correction">
        Email the editors
      </a>
    </div>

  </aside>

</section>

</div>
