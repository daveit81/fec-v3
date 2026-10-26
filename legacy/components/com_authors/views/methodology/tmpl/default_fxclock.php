<?php
defined('_JEXEC') or die;

/**
 * FXClock methodology page (Joomla layout suffix: default_fxclock.php).
 *
 * Mirrors the scaffolding of default_decision-engine.php (hero card, stats
 * strip, content/sidebar TOC layout). Same fec-edition.css + methodology.css
 * tokens - no new CSS shipped.
 *
 * Documents FXClock (FEC Tools No. 04): player-timed withdrawals shown on
 * casino review pages (helpers/fxclock_widget.php, /fxclock/ endpoints).
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('fxclock')
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

$fxshieldMethodUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxshield');
$fxcheckMethodUrl  = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck');
$methodologyHub    = JRoute::_('index.php?option=com_authors&view=methodologyhub');
$casinosUrl        = '/online-casinos';

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
            <span>Rules <b><?php echo $h($version); ?></b></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Stats strip - system facts -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num">2</div>
        <div class="lbl">Taps per review</div>
      </div>
      <div class="stat">
        <div class="num">10</div>
        <div class="lbl">Clocks before a median shows</div>
      </div>
      <div class="stat">
        <div class="num">4</div>
        <div class="lbl">Verification grades</div>
      </div>
      <div class="stat">
        <div class="num">0</div>
        <div class="lbl">Editorial edits to a clock</div>
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
        A player review is only as good as the one thing the player knows and nobody else does: what
        happened to their money, and when. FXClock asks for that and nothing else. <b>One tap when the
        withdrawal is requested, one tap when the money lands.</b> The interval is the review. It is
        recorded while it happens, shown while it runs, and aggregated only once there are enough of them
        to mean something.
      </p>
    </div>

    <h2 id="why-clock">Why a stopwatch instead of stars</h2>
      <p>
        Star ratings and free-text reviews collect opinions, mostly from people who are either angry or
        paid, and they collect them after the fact. The result is unverifiable, unquantifiable and, in
        this industry, routinely gamed. A withdrawal time is different: it is a fact about the player&rsquo;s
        own experience, it has a natural unit, it can be compared across players and across casinos, and
        it is expensive to fake at scale because a fake clock has to be started and stopped at plausible
        times by someone who keeps coming back. Everything else FXClock records is a structured answer,
        never an adjective.
      </p>

    <h2 id="how-it-works">How a clock works</h2>
      <p>
        On any casino review with FXClock enabled, a player taps <b>Start a clock</b> the moment they
        request a withdrawal, choosing the payment method and, optionally, an amount band and a way to be
        reminded (Telegram, email, or none). The clock appears on the page immediately, ticking, visible
        to every reader &mdash; including the casino. When the money arrives the player taps
        <b>Received</b> and answers three questions while the memory is fresh: whether identity checks
        were requested and when, whether documents beyond an ID were asked for, and whether the full
        amount arrived. That stops the clock. A player can also close a clock as <b>refused</b>.
      </p>
      <p>
        Reminders go out on day 3, 7 and 15. A clock still running after 30 days is closed automatically
        as <b>unresolved</b> and counted as such on the brand page: a withdrawal that never arrives is
        data, not silence. Past payouts can be logged from memory as well; they are recorded at the lowest
        grade and kept out of the headline number.
      </p>

    <h2 id="grades">The four verification grades</h2>
      <p>
        Every clock carries a letter that says how much we can vouch for it. The letter is the only
        identity a clock has; FXClock never asks for a name.
      </p>
      <ul>
        <li><b>A &mdash; Stop time locked on-chain.</b> For crypto payouts, the player pastes the
          transaction id. We look it up on the public chain, check that it is confirmed and that its
          timestamp falls inside the clock&rsquo;s window, and replace the tap with the block time. The stop
          is then a fact recorded by the blockchain, not by the player.</li>
        <li><b>B &mdash; Deposit verified.</b> The clock is matched to a first deposit reported to us by
          the casino&rsquo;s affiliate program for the same click. Available only where the program passes
          the data needed to match; shown as unavailable elsewhere.</li>
        <li><b>C &mdash; Contact verified.</b> The player reached the clock through a Telegram account
          or an email link they own.</li>
        <li><b>D &mdash; Anonymous.</b> No channel. Still counted, still shown as anonymous.</li>
      </ul>
      <p>
        The grade never changes the arithmetic of the median: every live clock that was stopped counts
        once. It changes how much weight the desk gives a clock when it reads the data, and how the
        clock is labelled to readers.
      </p>

    <h2 id="numbers">What the numbers mean</h2>
      <p>
        The headline figure on a brand page is the <b>median</b> time from request to receipt across
        stopped clocks, with a distribution in four bands: under 24 hours, one to three days, four to
        seven days, over a week. The median is shown only once a brand has <b>ten</b> stopped clocks.
        Below that, the page says so and shows nothing weaker. Refused withdrawals, unresolved clocks
        and clocks excluded by our checks are counted separately and shown separately; none of them is
        folded into the median.
      </p>
      <p>
        FXClock output is never presented as a rating. It is not marked up as one, it does not feed the
        <a href="<?php echo $fxshieldMethodUrl; ?>">FXShield&trade; Safety Index</a>, and it is not
        blended with the desk&rsquo;s own withdrawal test. Where both exist on a page, they are shown as
        two numbers with two labels: what the desk measured once under controlled conditions, and what
        players are timing right now. They inform each other; they never merge.
      </p>

    <h2 id="desk">What the desk can and cannot do</h2>
      <ul>
        <li><b>Cannot</b> start, stop, re-time, re-grade or edit a clock. There is no interface for it,
          and the record of every clock is an append-only event log.</li>
        <li><b>Can</b> approve or reject the optional one-line note a player leaves. Notes are never
          counted and never shown before moderation.</li>
        <li><b>Can</b> hide a clock for abuse, with a reason from a fixed list, and restore it. Hidden
          clocks stay stored and appear in the &ldquo;excluded&rdquo; count on the brand page, so the
          intervention is visible.</li>
        <li><b>Can</b> switch FXClock on or off per casino.</li>
      </ul>

    <h2 id="abuse">Abuse controls</h2>
      <p>
        Clocks are rate-limited per connection, one running clock per player per casino, and scored at
        stop. A stop within two minutes of the start, repeated clocks from the same contact on the same
        brand, bursts from the same network, and notes containing links or contact details all add to
        the score; at three points the clock is excluded automatically. Transaction ids are unique across
        the whole system, so one payout cannot back two clocks. Nothing is deleted: excluded clocks are
        kept and counted as excluded.
      </p>

    <h2 id="privacy">What we keep, and for how long</h2>
      <p>
        Timestamps, the structured answers, the payment method and amount band, the verification grade,
        and the country reported by our network provider at the start. A reminder contact, when given, is
        stored encrypted and used only for the three reminders and the closing message; it is deleted 90
        days after the clock closes, together with every connection fingerprint. Receipts show no player
        identifier. Transaction ids are public chain data and are not shown on receipts.
      </p>

    <h2 id="versioning">Versioning</h2>
      <p>
        The rules on this page &mdash; the threshold of ten, the 30-day closure, the abuse scoring, the
        grade definitions &mdash; are versioned. The current set is <b>v1.0</b>, published September 2026.
        Changes are documented here before they take effect, so any number on a brand page can be traced
        to the rules that produced it.
      </p>

    <h2 id="faq">FAQ</h2>

      <details>
        <summary>Why do you not ask for a name or a screenshot?</summary>
        <p>
          A name is the easiest thing to invent and the hardest to moderate, and a screenshot proves
          little without the account it came from. The verification grade tells readers exactly how much
          a clock has been checked, which is more than a nickname ever could. Where real proof exists
          &mdash; a transaction on a public chain &mdash; FXClock uses it.
        </p>
      </details>

      <details>
        <summary>Can a casino remove a clock it does not like?</summary>
        <p>
          No. Nobody can edit a clock, including us. A casino that believes a clock is fraudulent can
          write to the editors like anyone else; if our checks agree, the clock is hidden with a stated
          reason and stays visible in the excluded count.
        </p>
      </details>

      <details>
        <summary>Why is the median shown only from ten clocks?</summary>
        <p>
          Because three numbers do not describe a casino, they describe three people. Ten is the point
          at which a median stops moving wildly with each new clock. Until then the page shows the clocks
          it has and says plainly that it is not enough.
        </p>
      </details>

      <details>
        <summary>My withdrawal arrived after the clock closed as unresolved. What now?</summary>
        <p>
          Log it as a past payout on the same page. It is recorded at grade D and kept out of the median,
          because it was timed from memory rather than as it happened, but it is recorded.
        </p>
      </details>

      <details>
        <summary>Does the desk&rsquo;s own withdrawal test use FXClock data?</summary>
        <p>
          No. The FXShield test is run by the desk, once, under controlled conditions, and produces the
          Safety Index. FXClock is the crowd&rsquo;s version: many clocks, uncontrolled, from real players.
          The two are shown side by side and never combined.
        </p>
      </details>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar" aria-label="On this page">

    <div class="au-side-card me-toc">
      <div class="me-toc-label">On this page</div>
      <ul>
        <li><a href="#why-clock">Why a stopwatch</a></li>
        <li><a href="#how-it-works">How a clock works</a></li>
        <li><a href="#grades">The four grades</a></li>
        <li><a href="#numbers">What the numbers mean</a></li>
        <li><a href="#desk">What the desk can do</a></li>
        <li><a href="#abuse">Abuse controls</a></li>
        <li><a href="#privacy">What we keep</a></li>
        <li><a href="#versioning">Versioning</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>

    <div class="au-side-card">
      <div class="me-toc-label">Related</div>
      <ul class="me-side-list">
        <li><a href="<?php echo $h($casinosUrl); ?>">Casino reviews with FXClock</a></li>
        <li><a href="<?php echo $fxshieldMethodUrl; ?>">FXShield&trade; methodology</a></li>
        <li><a href="<?php echo $fxcheckMethodUrl; ?>">FXCheck&trade; methodology</a></li>
        <li><a href="<?php echo $methodologyHub; ?>">All methodologies</a></li>
      </ul>
    </div>

    <div class="au-side-cta">
      <div class="me-cta-label">Spot an error?</div>
      <p>
        Every rule on this page is published and versioned. If a number on a brand page looks wrong for
        the clocks shown, mail us &mdash; we acknowledge within 48 hours and document every correction.
      </p>
      <a class="me-cta-btn" href="mailto:editorial@freeextrachips.com?subject=FXClock%20feedback">
        Email the editors
      </a>
    </div>

  </aside>

</section>

</div>
