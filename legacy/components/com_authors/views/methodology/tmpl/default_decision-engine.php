<?php
defined('_JEXEC') or die;

/**
 * Decision Engine methodology page (Joomla layout suffix: default_decision-engine.php).
 *
 * Mirrors the scaffolding of default_editorial-standards.php (hero card,
 * stats strip, content/sidebar TOC layout). Same fec-edition.css +
 * methodology.css tokens - no new CSS shipped.
 *
 * Documents rule table v1.0 of the Interactive Decision Notes band that
 * runs on bonus detail pages (com_bonuses, helpers/fec_dn_widget.php).
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('decision-engine')
 *   $this->config       topic config (title, tagline, author/reviewer slugs, version, last_updated)
 *   $this->author       byline author row (or null)
 *   $this->reviewer     reviewer row (or null)
 *   $this->jsonLdGraph  pre-encoded JSON-LD @graph
 */

$cfg      = is_array($this->config) ? $this->config : array();
$author   = $this->author;
$reviewer = $this->reviewer;

// ---- Local helpers --------------------------------------------------------
$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$lastUpdated = !empty($cfg['last_updated']) ? date('j F Y', strtotime($cfg['last_updated'])) : '';
$version     = isset($cfg['version'])      ? (string)$cfg['version']      : '';

$authorUrl   = ($author   && !empty($author['slug']))   ? JRoute::_('index.php?option=com_authors&view=author&slug=' . $author['slug'])   : '';
$reviewerUrl = ($reviewer && !empty($reviewer['slug'])) ? JRoute::_('index.php?option=com_authors&view=author&slug=' . $reviewer['slug']) : '';

$fxcheckMethodUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck');
$methodologyHub   = JRoute::_('index.php?option=com_authors&view=methodologyhub');
$wcalUrl          = '/tools/wagering-calculator';

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
?>

<?php if (!empty($this->jsonLdGraph)): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>

<div class="fecx me-page">

<?php if ($crumbHtml !== ''): ?>
<div class="wrap"><?php echo $crumbHtml; ?></div>
<?php endif; ?>

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
            <span class="div">&middot;</span>
            <span>Reviewed by
              <?php if ($reviewerUrl !== ''): ?>
                <a href="<?php echo $reviewerUrl; ?>"><b><?php echo $h($reviewer['name']); ?></b></a>
              <?php else: ?>
                <b><?php echo $h($reviewer['name']); ?></b>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($lastUpdated !== ''): ?>
            <span class="div">&middot;</span>
            <span>Updated <b><?php echo $h($lastUpdated); ?></b></span>
          <?php endif; ?>
          <?php if ($version !== ''): ?>
            <span class="div">&middot;</span>
            <span>Rule table <b><?php echo $h($version); ?></b></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Stats strip - engine facts -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num">3</div>
        <div class="lbl">Questions, max</div>
      </div>
      <div class="stat">
        <div class="num">2,180</div>
        <div class="lbl">Bonuses in calibration</div>
      </div>
      <div class="stat">
        <div class="num">7</div>
        <div class="lbl">Rules, all published</div>
      </div>
      <div class="stat">
        <div class="num">0</div>
        <div class="lbl">Data sent to servers</div>
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
      <div class="me-verdict-label">The principle</div>
      <p>
        The same bonus is a different proposition for different players. The interactive band on each
        bonus page asks who is reading &mdash; planned deposit, preferred game, main goal &mdash; and scopes
        the arithmetic of the published terms to the answer. <b>Every reason under a verdict is a number
        derived from a verified clause.</b> Nothing is generated, estimated, or personalised by anything
        other than the reader&rsquo;s own answers.
      </p>
    </div>

    <h2 id="why-profile">Why a verdict per profile</h2>
      <p>
        A 35&times; requirement on deposit plus bonus is routine volume for a &euro;500 slots player and an
        unrealistic grind for a &euro;25 one; a &euro;100 cashout ceiling is irrelevant to someone clearing for
        fun and a dealbreaker for someone chasing a big win. Written decision notes address a general
        reader. The band removes that compromise: on a deposit bonus it asks three questions, on a
        no-deposit bonus two &mdash; there is nothing to deposit, so nothing is asked about one. Answers are
        remembered by the browser, every later bonus page opens already personalised, and changing one
        answer re-computes every verdict.
      </p>

    <h2 id="what-it-reads">What the engine reads</h2>
      <p>
        The verdict is computed exclusively from the verified fields of the bonus record: the wagering
        multiplier and its base (bonus only, or deposit plus bonus), the bonus size and its cap, the
        minimum deposit, whether the bonus is cashable or removed at withdrawal, any maximum bet, any
        maximum cashout, and the editorial status of the operator. Game contribution follows the reader&rsquo;s
        declared game &mdash; the single biggest multiplier of real clearing volume, and the one promotions
        never mention.
      </p>

    <h2 id="rule-table">The rule table (v1.0)</h2>
      <p>
        Rules run in order; the verdict is the most severe outcome triggered, and every triggered reason
        stays visible so the full picture is never reduced to a label.
      </p>
      <ul>
        <li><b>R0 &mdash; Operator override.</b> An editorial flag on the operator caps the verdict at
          Caution; a suspended or blacklisted operator forces Skip. No combination of favourable terms
          and profile can outrank an operator-level warning.</li>
        <li><b>R1 &mdash; Minimum deposit.</b> A minimum deposit above the declared bankroll is a Skip.</li>
        <li><b>R2 &mdash; Real turnover versus bankroll.</b> Required stakes on the chosen game, after
          contribution, compared with the deposit. Above 300&times; the deposit: Skip &mdash; at typical slot
          RTP that volume carries an expected clearing cost above 12&times; the deposit. Between 120&times;
          and 300&times;: Caution, an expected cost around 5&times; the deposit.</li>
        <li><b>R3 &mdash; Verified cashout ceilings.</b> When the cap has been verified as an amount, a
          multiple of the deposit, or a multiple of the bonus: under 2&times; the deposit with a max-win
          goal is a Skip, under 5&times; a Caution. Caps on record whose unit is not yet verified are
          reported as unverified and never assumed.</li>
        <li><b>R4 &mdash; Sticky bonuses.</b> A non-cashable bonus with a max-win goal is a Caution: the
          bonus amount leaves the balance at withdrawal.</li>
        <li><b>R5 &mdash; Fast clearing.</b> For a fast-clearing goal, a deposit-plus-bonus base or a
          multiplier above 30&times; each raise a Caution.</li>
        <li><b>R6 &mdash; Max-bet friction.</b> A maximum bet forcing more than 5,000 minimum bets is
          noted, so the time cost is visible before claiming.</li>
      </ul>
      <p>
        The R2 thresholds were not chosen by feel. They were calibrated against the live catalogue &mdash;
        2,180 active deposit bonuses at the reference profile &mdash; so that roughly nine offers in ten stay
        claimable on slots at full contribution, one in ten earns a Caution, and the extreme tail is named
        for what it is. On table games at 10% contribution the same thresholds turn strict, which is the
        honest outcome: chasing wagering on low-contribution games is the most reliable way to turn a
        bonus into a loss.
      </p>

    <h2 id="refusals">What the engine refuses to do</h2>
      <ul>
        <li><b>It never overrides an editorial warning.</b> The rule table starts, not ends, with the
          operator status.</li>
        <li><b>It never guesses.</b> An unverified cashout cap is declared unverified; a missing field
          means the relevant rule stays silent rather than inventing an input.</li>
        <li><b>It does not compute on free spins.</b> Spin counts are not money until the spins pay out,
          so free-spins offers carry no interactive verdict &mdash; the written note and the
          <a href="<?php echo $h($wcalUrl); ?>">wagering calculator</a> cover them.</li>
        <li><b>It does not follow you.</b> The profile lives only in the browser&rsquo;s local storage: no
          account, nothing sent to a server, nothing tied to a person. Clearing browser data clears it.</li>
      </ul>
      <p>
        One personal note, since I designed the flow: the goal was never to tell anyone what to play. It
        is to make sure the two minutes before a deposit contain the same numbers the terms will enforce
        afterwards.
      </p>

    <h2 id="versioning">Versioning</h2>
      <p>
        The rule table is versioned and threshold changes are documented on this page, so any verdict can
        be traced to the rules that produced it. The current table is <b>v1.0</b>, published August 2026.
        The signed decision note on each bonus page remains the editorial judgment; the engine scopes it
        to a profile, and the casino&rsquo;s current terms and conditions always prevail.
      </p>

    <h2 id="faq">FAQ</h2>

      <details>
        <summary>Why does my verdict differ from someone else&rsquo;s on the same bonus?</summary>
        <p>
          Because the inputs differ. The engine scales the same published clauses to a different deposit,
          game contribution, and goal &mdash; two profiles can legitimately receive opposite verdicts from
          identical terms, and both verdicts show the arithmetic that produced them.
        </p>
      </details>

      <details>
        <summary>Why is there no verdict on free spins bonuses?</summary>
        <p>
          Free spins winnings do not exist until the spins pay out, and a spin count is not money. Any
          verdict computed on it would be an invented number, which is the one thing the engine is not
          allowed to produce. Once winnings are known, the wagering calculator handles the clearing math.
        </p>
      </details>

      <details>
        <summary>Where are my answers stored?</summary>
        <p>
          In your browser&rsquo;s local storage, under a single key, readable only by this site and only on
          your device. Nothing reaches a server, there is no account, and clearing site data resets the
          profile.
        </p>
      </details>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar" aria-label="On this page">

    <div class="au-side-card me-toc">
      <div class="me-toc-label">On this page</div>
      <ul>
        <li><a href="#why-profile">Why a verdict per profile</a></li>
        <li><a href="#what-it-reads">What the engine reads</a></li>
        <li><a href="#rule-table">The rule table (v1.0)</a></li>
        <li><a href="#refusals">What it refuses to do</a></li>
        <li><a href="#versioning">Versioning</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>

    <div class="au-side-card">
      <div class="me-toc-label">Related</div>
      <ul class="me-side-list">
        <li><a href="<?php echo $h($wcalUrl); ?>">Wagering Calculator</a></li>
        <li><a href="<?php echo $fxcheckMethodUrl; ?>">FXCheck&trade; methodology</a></li>
        <li><a href="<?php echo $methodologyHub; ?>">All methodologies</a></li>
      </ul>
    </div>

    <div class="au-side-cta">
      <div class="me-cta-label">Spot an error?</div>
      <p>
        Every threshold on this page is published and versioned. If a verdict looks wrong for the terms
        shown, mail us &mdash; we acknowledge within 48 hours and document every correction.
      </p>
      <a class="me-cta-btn" href="mailto:editorial@freeextrachips.com?subject=Decision%20engine%20feedback">
        Email the editors
      </a>
    </div>

  </aside>

</section>

</div>
