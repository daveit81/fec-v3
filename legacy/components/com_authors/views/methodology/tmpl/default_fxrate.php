<?php
defined('_JEXEC') or die;

/**
 * FXRate methodology page (Joomla layout suffix: default_fxrate.php).
 *
 * Same scaffolding as default_fxclock.php (hero card, stats strip,
 * content/sidebar TOC layout), same fec-edition.css + methodology.css
 * tokens - no new CSS shipped.
 *
 * Documents FXRate (FEC Tools No. 05): the "advertised vs effective" line
 * on welcome packages, rendered on bonus pages, casino review pages and
 * hub cards by components/com_bonuses/helpers/fec_fxrate_render.php from
 * the package tables (#__fec_bonus_packages, #__fec_bonus_package_legs).
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('fxrate')
 *   $this->config       topic config (title, tagline, author/reviewer slugs, version, last_updated)
 *   $this->stats        AuthorsModelMethodology::getFxrateStats() (live numbers; 'live' false = dashes)
 *   $this->author       byline author row (or null)
 *   $this->reviewer     reviewer row (or null)
 *   $this->jsonLdGraph  pre-encoded JSON-LD @graph
 */

$cfg      = is_array($this->config) ? $this->config : array();
$stats    = is_array($this->stats)  ? $this->stats  : array();
$author   = $this->author;
$reviewer = $this->reviewer;

// ---- Local helpers --------------------------------------------------------
$h   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$pct = function ($v) { return ($v === null || $v === '') ? '&mdash;' : number_format(round((float)$v)) . '%'; };
$num = function ($v) { return ($v === null || $v === '') ? '&mdash;' : number_format((int)$v); };
$x   = function ($v) { return ($v === null || $v === '') ? '&mdash;' : number_format((float)$v, 1) . '&times;'; };

$live        = !empty($stats['live']);
$lastUpdated = !empty($cfg['last_updated']) ? date('j F Y', strtotime($cfg['last_updated'])) : '';
$version     = isset($cfg['version'])      ? (string)$cfg['version']      : '';

$authorUrl   = ($author   && !empty($author['slug']))   ? JRoute::_('index.php?option=com_authors&view=author&slug=' . $author['slug'])   : '';
$reviewerUrl = ($reviewer && !empty($reviewer['slug'])) ? JRoute::_('index.php?option=com_authors&view=author&slug=' . $reviewer['slug']) : '';

$fxcheckMethodUrl  = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck');
$decisionMethodUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=decision-engine');
$methodologyHub    = JRoute::_('index.php?option=com_authors&view=methodologyhub');
$bonusesUrl        = '/bonuses/';

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

    <!-- Stats strip - live numbers from the package tables; dashes until the core is deployed -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo $live ? $num($stats['packages_priced']) : '&mdash;'; ?></div>
        <div class="lbl">Packages priced today</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $live ? $pct($stats['median_advertised']) : '&mdash;'; ?></div>
        <div class="lbl">Median advertised match</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $live ? $pct($stats['median_effective']) : '&mdash;'; ?></div>
        <div class="lbl">Median effective match</div>
      </div>
      <div class="stat">
        <div class="num">0</div>
        <div class="lbl">Estimates in any number</div>
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
        &ldquo;250% up to $1,500&rdquo; is not a rate. It is three bonuses added together with the three
        deposits left out. <b>FXRate puts the deposits back.</b> It reads the terms of each deposit as the
        casino states them, works out what has to be paid in to collect every cap, and prints the match
        rate on that money next to the one on the banner. Four numbers, computed the same way on every
        package, with &ldquo;not stated&rdquo; wherever a term is missing. Nothing is estimated.
      </p>
    </div>

    <h2 id="why">Why the sum on the banner is not a rate</h2>
      <p>
        A single deposit match is honest arithmetic: 100% up to $500 means a $500 deposit becomes $1,000.
        A welcome package stacks several of these and advertises the <b>sum of the percentages</b> and
        the <b>sum of the caps</b>. The sum is real. What it hides is that each cap sits on its own
        deposit, and the deposits do not add up the way the percentages do.
      </p>
      <p>
        Take 100% up to $500, then 50% up to $500, then 100% up to $500. The banner says 250% up to
        $1,500. Collecting the full $1,500 takes $500 on the first deposit, <b>$1,000</b> on the second
        (a 50% match needs twice the money to reach the same cap) and $500 on the third: $2,000 in. The
        package pays $1,500 on $2,000, which is a 75% match. Not 250%. The second leg is doing most of the
        work on the banner and least of the work in the account.
      </p>
      <div class="me-callout">
        The percentage on the banner grows with the number of deposits. The money you get back grows with
        the caps and shrinks with every leg below 100%. FXRate measures the second thing.
      </div>

    <h2 id="four-numbers">The four numbers</h2>
      <p>
        Every welcome package on FreeExtraChips carries one line in the same order. Each number is a
        function of the terms listed on the bonus pages and nothing else.
      </p>
      <table class="me-table">
        <thead>
          <tr><th>Number</th><th>How it is computed</th><th>What it answers</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><b>Advertised</b></td>
            <td>The casino&rsquo;s own headline when it states one; otherwise the sum of the match
              percentages and the sum of the caps across the legs. Free spins are counted here and nowhere
              else.</td>
            <td>What the banner says.</td>
          </tr>
          <tr>
            <td><b>You deposit</b></td>
            <td>Per leg, cap &divide; match rate, rounded up to the cent; then the sum. A leg whose
              minimum deposit already exceeds that figure is priced at the minimum; a leg whose cap needs
              more than the maximum deposit is priced at the maximum, with the bonus it actually yields.</td>
            <td>What you must put in to collect every cap in full.</td>
          </tr>
          <tr>
            <td><b>Effective</b></td>
            <td>Total bonus at the caps &divide; total deposit, as a percentage.</td>
            <td>The real match rate on the money you put in.</td>
          </tr>
          <tr>
            <td><b>Turnover</b></td>
            <td>Per leg, wagering multiplier &times; its stated base: the bonus alone, or deposit plus
              bonus; then the sum.</td>
            <td>The stake you must place before anything can be withdrawn.</td>
          </tr>
        </tbody>
      </table>

    <h2 id="worked">Two packages, worked by hand</h2>
      <p>
        The first is the package above with 35&times; wagering on deposit plus bonus. The second is three
        identical legs of 200% up to $2,000 with 30&times; on deposit plus bonus &mdash; the kind of
        package whose banner reads 600% up to $6,000.
      </p>
      <table class="me-table">
        <thead>
          <tr><th>Leg</th><th>Terms</th><th class="num">Deposit</th><th class="num">Bonus</th><th class="num">Turnover</th></tr>
        </thead>
        <tbody>
          <tr><td>1st</td><td>100% up to $500</td><td class="num">$500</td><td class="num">$500</td><td class="num">$35,000</td></tr>
          <tr><td>2nd</td><td>50% up to $500</td><td class="num">$1,000</td><td class="num">$500</td><td class="num">$52,500</td></tr>
          <tr><td>3rd</td><td>100% up to $500</td><td class="num">$500</td><td class="num">$500</td><td class="num">$35,000</td></tr>
          <tr><td><b>Package</b></td><td><b>Advertised 250% up to $1,500</b></td><td class="num"><b>$2,000</b></td><td class="num"><b>$1,500</b></td><td class="num"><b>$122,500</b></td></tr>
        </tbody>
      </table>
      <p>
        <b>Effective 75%.</b> The line on the bonus page reads: Advertised 250% up to $1,500 &middot;
        You deposit $2,000 &middot; Effective 75% &middot; Turnover $122,500.
      </p>
      <table class="me-table">
        <thead>
          <tr><th>Leg</th><th>Terms</th><th class="num">Deposit</th><th class="num">Bonus</th><th class="num">Turnover</th></tr>
        </thead>
        <tbody>
          <tr><td>1st</td><td>200% up to $2,000</td><td class="num">$1,000</td><td class="num">$2,000</td><td class="num">$90,000</td></tr>
          <tr><td>2nd</td><td>200% up to $2,000</td><td class="num">$1,000</td><td class="num">$2,000</td><td class="num">$90,000</td></tr>
          <tr><td>3rd</td><td>200% up to $2,000</td><td class="num">$1,000</td><td class="num">$2,000</td><td class="num">$90,000</td></tr>
          <tr><td><b>Package</b></td><td><b>Advertised 600% up to $6,000</b></td><td class="num"><b>$3,000</b></td><td class="num"><b>$6,000</b></td><td class="num"><b>$270,000</b></td></tr>
        </tbody>
      </table>
      <p>
        <b>Effective 200%.</b> Three legs at the same rate add up without a trick: the banner is big and
        the money is big in the same proportion. FXRate does not penalise a large package. It penalises a
        headline that counts percentages the account never sees. The two packages look alike on a banner
        and are 125 points apart in the cashier.
      </p>

    <h2 id="rules">Reading rules</h2>
      <p>
        The numbers are only as good as the terms, and terms go missing. These rules decide what happens
        then. They are applied by code, identically, to every package.
      </p>
      <ul class="me-bullets">
        <li><b>Cap not stated.</b> A leg without a stated maximum bonus cannot be priced. It is shown in
          the ladder at its minimum deposit, for scale, and left out of every total. The package line then
          reads &ldquo;not stated&rdquo; for the deposit and the effective rate.</li>
        <li><b>Match not stated.</b> A deposit the casino promises but whose terms it does not publish
          appears as &ldquo;terms not stated&rdquo;. The package is counted as having that many deposits;
          nothing is filled in for it.</li>
        <li><b>Deposit limits.</b> Minimum and maximum deposits bound the deposit that reaches the cap
          (see the table above). Both cases are flagged under the ladder.</li>
        <li><b>Wagering.</b> No multiplier, no turnover. A multiplier without a stated base (bonus, or
          deposit plus bonus) gives no turnover either, because the two bases differ by the whole deposit.
          A stated multiplier of zero is read as no wagering requirement and prints a turnover of zero.</li>
        <li><b>Free spins.</b> Counted in the headline, never priced. A spin has no stated cash value
          until it has been played, and any figure put on it would be a guess.</li>
        <li><b>Spins legs</b> (v1.1). A deposit that buys free spins and
          no match (&ldquo;3rd deposit: 100 free spins&rdquo;) is a leg of the ladder and counts as one of
          its deposits, but it has nothing to price: no rate, no cap. It is listed on its own line with
          its minimum deposit, its spins join the headline, and it stays out of the four numbers, which
          price the match legs alone. Putting its deposit into the total with a bonus of zero would value
          the spins at exactly nothing, which is a guess like any other.</li>
        <li><b>The entry line</b> (v1.1). Under the four numbers: the stated minimum deposits added
          up, and how many times that sum goes into the deposit needed to collect every cap
          (&ldquo;Minimum deposits add up to BTC 0.00108. Collecting every cap takes BTC 6.6667,
          6,173&times; the minimum.&rdquo;). Two stated figures, one division; it appears only when
          every leg in the total states a minimum, and reads &ldquo;the minimum deposits already
          collect every cap&rdquo; when they do.</li>
        <li><b>Maximum cashout</b> (v1.2). Some operators cap what a leg&rsquo;s winnings can turn
          into (&ldquo;max cashout 5&times; the deposit&rdquo;, &ldquo;&euro;1,000&rdquo;). The cap is
          reported beside the leg as the operator states it &mdash; with its unit when one is given
          (money, times the deposit, times the bonus), as a bare number when none is &mdash; and, where
          the unit and its base are known, as money at the deposit the leg is priced at. It is never
          priced into the four numbers: the effective match is a property of the bonus credited, the
          cap a limit on what may leave; folding one into the other would need a convention of ours
          about which winnings the cap covers, and that is a guess. The index marks capped packages
          and lets the reader hide them.</li>
        <li><b>Spins wagering</b> (v1.3). A leg that bundles free spins has two wagering terms, not
          one: the match&rsquo;s (&ldquo;35&times; deposit plus bonus&rdquo;) and the spins&rsquo; own, on
          what the spins win (&ldquo;50 free spins, no wagering&rdquo;, &ldquo;40&times; winnings&rdquo;).
          Operators state them separately, so the ladder prints them separately: the match&rsquo;s
          term on the leg as before, and under it &ldquo;spins: no wagering&rdquo;, &ldquo;spins: 40&times;
          winnings&rdquo; or &ldquo;spins: WR not stated&rdquo;. One never speaks for the other &mdash; a
          leg whose match carries 35&times; is not read as 35&times; on the spins, and wager-free spins do
          not soften the match. Like the spins themselves, their wagering stays out of the four numbers:
          the turnover is the match legs&rsquo; alone.</li>
        <li><b>Two packages at one casino.</b> A casino may run a standard track beside a high-roller
          or crypto track. Each is a package of its own, priced on its own, listed on its own row; the
          casino counts once. Where the desk has linked the two, each page names the other as an
          alternative, because a player takes one or the other, not both.</li>
        <li><b>Currencies.</b> Legs stated in different currencies are not summed. The line shows
          percentages only.</li>
        <li><b>Expired legs.</b> A leg whose listing has expired stays in the ladder, labelled, so the
          package still adds up; the casino page stops showing a package whose first deposit has expired.</li>
        <li><b>Rounding.</b> Deposits round up to the cent (you cannot deposit less than the cap needs).
          Percentages are shown whole. Totals are computed before rounding.</li>
      </ul>

    <h2 id="not">What FXRate is not</h2>
      <ul class="me-bullets">
        <li><b>Not an expected value.</b> FXRate makes no assumption about return-to-player, game
          choice, bet size or how long anyone plays. It is arithmetic on the terms, which is why two
          readers get the same number and why a casino cannot dispute it without disputing its own
          terms.</li>
        <li><b>Not a ranking.</b> No score, no stars, no &ldquo;best package&rdquo;. The four numbers are
          shown and the reader compares.</li>
        <li><b>Not advice.</b> A package with a low effective rate can still be the right one for a
          player who was going to make one deposit anyway and take the first leg alone; the first leg is
          priced on its own line for exactly that reason.</li>
        <li><b>Not an FXCheck&trade; signal.</b> Whether a bonus actually paid is a separate question,
          answered by player reports, and the two are never blended.</li>
      </ul>

    <h2 id="where">Where it appears</h2>
      <p>
        On the page of every bonus that is a leg of a package: a band under the headline with the four
        numbers, the ladder of deposits with the calculation per leg, the current leg marked, and a note
        for every rule that applied. On the casino review page: one card per package, above the active
        bonuses, so a reader sees the package before the parts. On the bonus cards across the site: a
        pill with the effective rate on the first-deposit card and the position (&ldquo;deposit 2 of
        4&rdquo;) on the others.
      </p>

    <h2 id="data">Where the terms come from</h2>
      <p>
        Each deposit of a package is either a bonus listed on FreeExtraChips, read live from its listing,
        or a set of terms the desk entered for a deposit the casino describes but does not list
        separately. The membership of a package is set by the desk; when the casino&rsquo;s own bonus
        description names a multi-deposit package, the desk is prompted with the deposits and terms the
        text states, and nothing is written without a stated figure. Terms are never inherited from a
        sister brand or filled from a category norm. A package is recomputed on every page view, so a
        change in a listing changes the line the same minute.
      </p>

    <h2 id="verify">Verify it yourself</h2>
      <ol class="me-numbered">
        <li>For each deposit, divide the cap by the match rate: $500 at 50% is 500 &divide; 0.5 =
          $1,000. That is the deposit that reaches the cap.</li>
        <li>Add the deposits. Add the caps. Divide the second by the first: that is the effective
          rate.</li>
        <li>For turnover, multiply the wagering requirement by its base for each deposit (bonus, or
          deposit plus bonus, as the terms say) and add them up.</li>
        <li>Compare with the line on the page. Where the page says &ldquo;not stated&rdquo;, the casino
          has not published the figure you would need; that is the finding.</li>
      </ol>

    <h2 id="numbers-today">The numbers today</h2>
      <?php if ($live && (int)$stats['packages_total'] > 0): ?>
      <p>
        Live from the package tables, over the packages shown on the site right now. &ldquo;Priced&rdquo;
        means every match and cap is stated, so an effective rate exists.
      </p>
      <table class="me-table">
        <tbody>
          <tr><td>Welcome packages tracked</td><td class="num"><?php echo $num($stats['packages_total']); ?></td></tr>
          <tr><td>Casinos</td><td class="num"><?php echo $num($stats['casinos']); ?></td></tr>
          <tr><td>Deposits across them</td><td class="num"><?php echo $num($stats['legs_total']); ?></td></tr>
          <tr><td>Packages priced</td><td class="num"><?php echo $num($stats['packages_priced']); ?></td></tr>
          <tr><td>Median advertised match</td><td class="num"><?php echo $pct($stats['median_advertised']); ?></td></tr>
          <tr><td>Median effective match</td><td class="num"><?php echo $pct($stats['median_effective']); ?></td></tr>
          <tr><td>Median ratio, advertised to effective</td><td class="num"><?php echo $x($stats['median_ratio']); ?></td></tr>
          <tr><td>Priced packages with an effective rate below 100%</td><td class="num"><?php echo $num($stats['below_100']); ?></td></tr>
          <tr><td>Priced packages at 50% or less</td><td class="num"><?php echo $num($stats['below_50']); ?></td></tr>
          <tr><td>Widest gap seen</td><td class="num"><?php echo $x($stats['max_ratio']); ?></td></tr>
        </tbody>
      </table>
      <?php else: ?>
      <p>
        The live counters are not available on this page right now. The numbers on bonus and casino
        pages are computed independently and are unaffected.
      </p>
      <?php endif; ?>

    <h2 id="versioning">Versioning</h2>
      <p>
        The rules on this page &mdash; the four definitions, the treatment of missing caps, deposit
        limits, wagering bases, spins and currencies &mdash; are versioned. The current set is
        <b>v1.3</b>. Changes are documented here before they take effect, so any line on a bonus page
        can be traced to the rules that produced it.
      </p>
      <ul>
        <li><b>v1.3</b>, 19 October 2026 &mdash; spins wagering: a leg&rsquo;s free spins carry their
          own wagering term, printed beside the match&rsquo;s (&ldquo;spins: no wagering&rdquo;,
          &ldquo;spins: 40&times; winnings&rdquo;) and never read from it; left out of the four numbers
          with the spins.</li>
        <li><b>v1.2</b>, 16 October 2026 &mdash; maximum cashout: a leg&rsquo;s withdrawal cap is
          reported beside the leg as stated (unit and money equivalent where known) and left out of
          the four numbers.</li>
        <li><b>v1.1</b>, 15 October 2026 &mdash; spins legs: a deposit that buys only free spins is counted
          as a deposit of the ladder and left out of the four numbers (before, a leg without a match rate
          kept the whole package at &ldquo;not stated&rdquo;); the entry line (minimum deposits against
          the deposit needed); money below one unit printed with up to eight decimals.</li>
        <li><b>v1.0</b>, 15 October 2026 &mdash; first published set.</li>
      </ul>

    <h2 id="faq">FAQ</h2>
    <div class="me-faq">

      <details>
        <summary>The third deposit only gives free spins. Is that still a package?</summary>
        <p>
          Yes: three deposits, three legs. The spins leg counts as a deposit and its spins are in the
          headline, but it has no match rate to price, so the four numbers cover the two match legs and
          the line says so. The index marks such packages in the Deposits column.
        </p>
      </details>

      <details>
        <summary>Why not account for RTP and compute what the bonus is really worth?</summary>
        <p>
          Because that number depends on the game, the bet size and the player, and three calculators
          will give three answers. FXRate stops where the casino&rsquo;s own terms stop. The effective
          rate is a property of the offer, not of the player, and it is the same for everyone who reads
          it.
        </p>
      </details>

      <details>
        <summary>Can the first deposit alone be better than the whole package?</summary>
        <p>
          Often. In the first worked example the first leg is a 100% match and the package is 75%. The
          ladder prices each leg separately for this reason: a reader who intends one deposit should see
          the first leg&rsquo;s own numbers, not the package average.
        </p>
      </details>

      <details>
        <summary>Why are free spins in the headline but not in the numbers?</summary>
        <p>
          A spin is a stake the casino places for you on one game at one bet level, and its value is
          whatever that spin wins. There is no stated cash amount to add to a cap. Counting the spins
          keeps the headline faithful to the banner; pricing them would be an estimate, and the line
          carries none. What the ladder does print for them is their wagering, which operators state
          apart from the match&rsquo;s: &ldquo;50 free spins, no wagering&rdquo; is a different offer from
          fifty spins whose winnings must be turned over forty times, and the line says which.
        </p>
      </details>

      <details>
        <summary>Why &ldquo;not stated&rdquo; instead of a typical value?</summary>
        <p>
          Because a typical value would be a number FreeExtraChips made up and the casino never
          published. A missing cap is information: the package cannot be priced because the casino has not
          said how much it pays. The line says so and stops.
        </p>
      </details>

      <details>
        <summary>Can a casino change its FXRate?</summary>
        <p>
          Yes, by changing its terms. The line is recomputed from the listing on every page view. A
          casino that raises a second-deposit match from 50% to 100% sees its effective rate move the same
          day the listing is updated; a casino that only changes the banner sees nothing move.
        </p>
      </details>

      <details>
        <summary>What if a listing is wrong?</summary>
        <p>
          Then the line is wrong, and the fix is the listing. Write to the editors with the clause and
          the page; corrections are acknowledged within 48 hours and the recomputed line appears as soon
          as the listing is corrected.
        </p>
      </details>

    </div>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar" aria-label="On this page">

    <div class="au-side-card me-toc">
      <div class="me-toc-label">On this page</div>
      <ul>
        <li><a href="#why">Why the sum is not a rate</a></li>
        <li><a href="#four-numbers">The four numbers</a></li>
        <li><a href="#worked">Two packages, worked</a></li>
        <li><a href="#rules">Reading rules</a></li>
        <li><a href="#not">What FXRate is not</a></li>
        <li><a href="#where">Where it appears</a></li>
        <li><a href="#data">Where the terms come from</a></li>
        <li><a href="#verify">Verify it yourself</a></li>
        <li><a href="#numbers-today">The numbers today</a></li>
        <li><a href="#versioning">Versioning</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>

    <div class="au-side-card">
      <div class="me-toc-label">Related</div>
      <ul class="me-side-list">
        <li><a href="<?php echo $h($bonusesUrl); ?>">Bonuses with FXRate</a></li>
        <li><a href="<?php echo $fxcheckMethodUrl; ?>">FXCheck&trade; methodology</a></li>
        <li><a href="<?php echo $decisionMethodUrl; ?>">Decision Notes methodology</a></li>
        <li><a href="<?php echo $methodologyHub; ?>">All methodologies</a></li>
      </ul>
    </div>

    <div class="au-side-cta">
      <div class="me-cta-label">Spot an error?</div>
      <p>
        Every rule on this page is published and versioned. If a line on a bonus page does not match the
        terms shown, mail us &mdash; we acknowledge within 48 hours and document every correction.
      </p>
      <a class="me-cta-btn" href="mailto:editorial@freeextrachips.com?subject=FXRate%20feedback">
        Email the editors
      </a>
    </div>

  </aside>

</section>

</div>
