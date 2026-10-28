<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Tournaments — show view (FEC V2.1, .tn-* editorial markup).
 *
 * Pattern note: this file mixes a minimal JView subclass with procedural
 * output that runs at include time (it predates the Joomla 2.5 convention
 * of doing all rendering inside display()). The helpers populate globals
 * ($layout, $total, $pagination, $records_per_page, $getpage, $filter, $q)
 * that the markup below consumes directly. We keep the procedural shape
 * for V2.1 — refactoring to a proper view model is a separate task and
 * touches the dispatcher.
 */

jimport('joomla.application.component.view');

class TournamentsViewShow extends JView
{
  public function display($tpl = null)
  {
    parent::display($tpl);
  }
}

// Helpers (data plumbing populates $layout + $pagination + $total etc.)
require_once JPATH_COMPONENT.DS.'helpers'.DS.'paginationnew.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'filters.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'common-render.php';

// FEC common lib — lives under com_bonuses but is shared across the site
// (breadcrumb helpers, FXCheck, schema.org builders, etc.). Other components
// don't auto-include it, so we require it explicitly. Guarded so the page
// still renders if the file is ever moved or removed during refactors —
// the breadcrumb (and any other lib-dependent block) just degrades silently.
$fecCommonLib = JPATH_SITE.'/components/com_bonuses/helpers/fec_common_lib.php';
if (is_readable($fecCommonLib)) {
  require_once $fecCommonLib;
}

// Input
$jinput = JFactory::getApplication()->input;

// Query params
$filter = $jinput->get('filter', '', 'CMD'); // fr / wf / ''
$q      = $jinput->get('q', '', 'STRING');

// Meta — keep deterministic, avoid duplicates between paginated/filtered pages
$doc   = JFactory::getDocument();
$page  = (int) $pagination->get_page();
$pages = (int) $pagination->get_pages();
$isFiltered = !empty($filter);
$isFirst = ($page === 1);

if ($isFirst && !$isFiltered) {
  $titletag = 'Online Casino Tournaments - Live Feed - '.date('F Y').' | FreeExtraChips';
  $descriptiontag = 'Live casino tournaments feed by FreeExtraChips. We currently monitor '.$total.' tournaments, freeroll and entry fee, with large prize pools to be won!';
  $doc->setTitle($titletag);
  $doc->setMetaData('description', $descriptiontag);
} else {
  $label = ($isFiltered && $filter == 'fr') ? 'Freeroll Tournaments'
         : (($isFiltered && $filter == 'wf') ? 'Tournaments with entry fee' : 'Casino Tournaments');
  $doc->setTitle($label.' - Page '.$page.' of '.$pages.' | FreeExtraChips.com');
  $doc->setMetaData('robots', 'noindex,follow');
}

// Hero copy variants
$h1     = 'Online Live Casino Tournaments';
$intro  = 'Live feed of current and upcoming tournaments. Filter by freeroll, entry fee, prize pool, or ending soon. Updates every 30 seconds.';
if ($isFiltered && $filter == 'fr') { $h1 = 'Freeroll Tournaments'; }
elseif ($isFiltered && $filter == 'wf') { $h1 = 'Tournaments with entry fee'; }
elseif (!$isFirst) { $h1 = 'Casino Tournaments'; }

$lastUpdate     = date('H:i');
$updateInterval = 30;

// -----------------------------------------------------------------
// Breadcrumb
//
// Tournaments lives under "Casinos" in our information architecture,
// but the URL is flat (/tournaments, no /online-casinos/tournaments
// parent in the URL structure), so fec_build_breadcrumb_items_from_path
// can only derive [Home, Tournaments] from the slug. We want the
// logical hierarchy Home > Casinos > Tournaments — Casinos here is
// the section index at /online-casinos (the same target the main nav
// uses) — so we build the trail explicitly. The lib helpers still
// render the HTML and the JSON-LD BreadcrumbList payload, keeping
// structured data consistent with what users see.
//
// Keys must match the helper's expected shape: 'name' + 'url'.
// 'label' / 'href' would be silently filtered out by the lib's
// foreach guard (if ($name === '') continue;). For the current item
// we still pass the canonical page URL — fec_render_breadcrumb_html
// renders the last item as a no-link <li aria-current="page">
// regardless, but fec_render_breadcrumb_jsonld requires every item
// to carry a url or it skips it from the BreadcrumbList payload,
// and Google expects the last position too.
// -----------------------------------------------------------------
$crumbHtml   = '';
$crumbJsonLd = '';

$siteBase   = rtrim(JUri::base(true), '/');
$crumbItems = array(
  array('name' => 'Home',        'url' => $siteBase . '/'),
  array('name' => 'Casinos',     'url' => $siteBase . '/online-casinos'),
  array('name' => 'Tournaments', 'url' => $siteBase . '/tournaments'),
);

if (function_exists('fec_render_breadcrumb_html')) {
  $crumbHtml = fec_render_breadcrumb_html($crumbItems);
}
if (function_exists('fec_render_breadcrumb_jsonld')) {
  $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
}

// -----------------------------------------------------------------
// FAQ
//
// Items are defined here (not inside the rendering loop) so the
// JSON-LD payload can be built up-front. The HTML loop further down
// consumes the same $faqItems array, so editing copy = one source.
//
// The Schema.org FAQPage JSON-LD is only emitted when the FAQ section
// is actually rendered on the page — first page of /tournaments,
// no filter — to avoid declaring a FAQPage to search engines on URLs
// where the FAQ section is hidden (paginated / filtered).
// In-page microdata (itemscope/itemprop on the <div class="bd-faq">
// markup further down) stays as a belt-and-braces signal too; Google
// will prefer the JSON-LD when both are present.
// -----------------------------------------------------------------
$faqItems = array(
  array(
    'q' => 'Can I cash out anything I win in a tournament?',
    'a' => "It depends on the prize structure. Cash prizes paid directly to your balance are usually withdrawable subject to standard KYC and minimum-withdrawal rules. Bonus credits are subject to the bonus wagering on whatever bonus structure the casino attaches &mdash; and that varies wildly. Always check the prize-payout section of the tournament's terms before you join.",
  ),
  array(
    'q' => 'How do I learn the details of each tournament?',
    'a' => "Click the Join button on this page. We deep-link directly to the casino's tournament page when possible, so you land on the official terms &mdash; eligible games, scoring metric, opt-in mechanic, prize breakdown. If the casino requires sign-up first, we say so.",
  ),
  array(
    'q' => "Why can't I join an online casino tournament?",
    'a' => 'Three common reasons. First: country restriction &mdash; tournaments often exclude jurisdictions the casino\'s license doesn\'t cover. Second: account status &mdash; some are new-player only, others VIP-tier only. Third: opt-in window &mdash; a tournament that\'s already "Running" may have closed late entries. The Unavailable badge on this page flags the most common case.',
  ),
  array(
    'q' => 'Which tournament should I play?',
    'a' => 'If you want to learn the format risk-free, start with freerolls in the games you already play. If you have a target prize in mind, sort by Biggest prize and check entry against your bankroll. If you want to maximize your hourly EV, look for buy-in events with low entry, moderate prize, and shorter windows &mdash; those tend to have the smallest fields and the best per-entry odds.',
  ),
  array(
    'q' => 'Can I use bonuses while playing tournaments?',
    'a' => 'Sometimes, but usually with restrictions. Some tournaments exclude bonus-funded play entirely from the leaderboard. Others count it but at a reduced weight. A few casinos pair tournaments with dedicated bonuses you can stack. Always check the tournament terms before activating a bonus while playing.',
  ),
);

$faqJsonLd = '';
if ($isFirst && !$isFiltered) {
  $faqMainEntity = array();
  foreach ($faqItems as $f) {
    // Strip the HTML entities we use for typographic dashes / quotes in
    // the answers — schema.org rich-results consumers expect plain text,
    // not encoded entities. Browser-side the entities still render as
    // intended in the visible accordion; here we only normalize for the
    // structured-data string.
    $answerText = html_entity_decode((string)$f['a'], ENT_QUOTES, 'UTF-8');
    $faqMainEntity[] = array(
      '@type' => 'Question',
      'name'  => (string)$f['q'],
      'acceptedAnswer' => array(
        '@type' => 'Answer',
        'text'  => $answerText,
      ),
    );
  }
  $faqPayload = array(
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $faqMainEntity,
  );

  // PHP 5.3 doesn't have JSON_UNESCAPED_*; defend.
  $flags = 0;
  if (defined('JSON_UNESCAPED_SLASHES')) $flags |= JSON_UNESCAPED_SLASHES;
  if (defined('JSON_UNESCAPED_UNICODE')) $flags |= JSON_UNESCAPED_UNICODE;
  $encoded = json_encode($faqPayload, $flags);
  if ($encoded !== false) {
    $faqJsonLd = $encoded;
  }
}

// -----------------------------------------------------------------
// Quick chips — URL-preset bookmarks. Each chip is an <a> linking to
// /tournaments + its preset params. "All" clears every preset (but
// preserves the search `q` — search is orthogonal to chip selection).
// A chip is "active" when its preset matches the current URL exactly
// (modulo `q`, `page`, `limitstart`).
// -----------------------------------------------------------------
$baseUrl = JUri::base(true) . '/tournaments';

$curParams = $_GET;
unset($curParams['option'], $curParams['view'], $curParams['Itemid']);
unset($curParams['page'], $curParams['limitstart']);
$curForChip = $curParams;
unset($curForChip['q']);

function _tn_chip_match($preset, $cur) {
  if (empty($preset) && empty($cur)) return true;
  if (count($preset) !== count($cur)) return false;
  foreach ($preset as $k => $v) {
    if (!array_key_exists($k, $cur)) return false;
    if (is_array($v)) {
      if (!is_array($cur[$k])) return false;
      if (array_values($v) != array_values($cur[$k])) return false;
    } else {
      if ((string)$cur[$k] !== (string)$v) return false;
    }
  }
  return true;
}

function _tn_chip_url($baseUrl, $preset, $q) {
  $params = $preset;
  if ($q !== '') $params['q'] = $q;
  if (empty($params)) return $baseUrl;
  return $baseUrl . '?' . http_build_query($params);
}

$chips = array(
  array('label' => 'All',                'preset' => array()),
  array('label' => 'Freerolls',          'preset' => array('type' => array('freeroll'))),
  array('label' => 'Ending in 1h',       'preset' => array('ends_within' => '1h')),
  array('label' => 'Big prize ($1k+)',   'preset' => array('prize_min' => '1000')),
  array('label' => 'Newest',             'preset' => array('sort' => 'newest')),
);

?>

<?php echo $crumbHtml; ?>
<?php if ($crumbJsonLd !== ''): ?>
<?php echo $crumbJsonLd; /* fec_render_breadcrumb_jsonld() already wraps in <script type="application/ld+json">…</script> — no extra wrapper here */ ?>
<?php endif; ?>

<!-- ========================== TN-HEAD ========================== -->
<section class="tn-head">
  <div class="wrap">
    <div class="tn-head-card">
      <div class="tn-head-left">
        <div class="eyebrow">Live feed &middot; Casinos</div>
        <h1 class="tn-head-title">
          <?php echo htmlspecialchars($h1, ENT_QUOTES, 'UTF-8'); ?>
          <?php if (!$isFirst): ?>
            <span class="tn-head-page">&mdash; Page <?php echo $page; ?> of <?php echo $pages; ?></span>
          <?php endif; ?>
        </h1>
        <p class="tn-head-intro"><?php echo htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="tn-head-pills">
          <span class="tn-trust-pill">Live updates every 30s</span>
          <span class="tn-trust-pill">Freeroll + entry-fee filters</span>
          <span class="tn-trust-pill">Ending-soon sorting</span>
          <span class="tn-trust-pill">Prize pools shown upfront</span>
        </div>

        <div class="tn-tip">
          <b>Editor tip:</b> Sort by <em>Ending soon</em> to catch tournaments you can still join.
        </div>
      </div>

      <div class="tn-status">
        <span class="tn-live">
          <span class="live-dot" aria-hidden="true"></span> LIVE
        </span>
        <span class="tn-monitoring">Monitoring <b><?php echo (int)$total; ?></b> tournaments</span>
        <div class="tn-update-row">
          <span class="pill live">Next update <b data-live-countdown><?php echo (int)$updateInterval; ?>s</b></span>
          <span class="pill">Last <b data-live-lastupdate><?php echo htmlspecialchars($lastUpdate, ENT_QUOTES, 'UTF-8'); ?></b></span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== TN-FILTER (sticky) ========================== -->
<div class="wrap">
  <div class="tn-filter">
    <span class="search-lbl">Search</span>

    <form class="search-wrap" role="search" aria-label="Search tournaments" method="get"
          action="<?php echo htmlspecialchars(JUri::current(), ENT_QUOTES, 'UTF-8'); ?>"
          id="tnSearchForm">
      <?php if ($isFiltered): ?>
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
      <?php endif; ?>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path>
      </svg>
      <input id="tsearch" type="search" name="q" inputmode="search" autocomplete="off"
             placeholder="Search tournament, casino, or game&hellip;"
             value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
      <?php if ($q !== ''): ?>
        <button type="button" class="search-clear" data-action="clear-search" aria-label="Clear search">&#10005;</button>
      <?php endif; ?>
    </form>

    <button class="filter-btn" type="button" data-action="open-filters" aria-controls="tfilters" aria-expanded="false">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 6h18M6 12h12M10 18h4"></path>
      </svg>
      Filters <span class="badge" data-activecount hidden>0</span>
    </button>

    <span class="sort-lbl">Sort</span>
    <span class="sort-select">
      <select id="tsort" name="sort" aria-label="Sort tournaments">
        <option value="ending_soon">Ending soon</option>
        <option value="starting_soon">Starting soon</option>
        <option value="prize_high">Biggest prize</option>
        <option value="entry_low">Lowest entry</option>
        <option value="newest">Newest</option>
      </select>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </span>
  </div>
</div>

<!-- ========================== TN-QUICKCHIPS ========================== -->
<div class="wrap">
  <div class="tn-quickchips">
    <?php foreach ($chips as $c):
      $isActive = _tn_chip_match($c['preset'], $curForChip);
      $href     = _tn_chip_url($baseUrl, $c['preset'], $q);
    ?>
      <a class="chip<?php echo $isActive ? ' active' : ''; ?>"
         href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
         <?php echo $isActive ? 'aria-current="true"' : ''; ?>>
        <?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ========================== TN-TABLE ========================== -->
<div class="wrap">
  <div class="tn-zero" id="tnZeroState" hidden>
    <h3 class="tn-zero__title">No tournaments found</h3>
    <p class="tn-zero__text">Your current filters or search didn&rsquo;t match any active tournaments.</p>
    <div class="tn-zero__actions">
      <button type="button" class="btn btn-ghost" data-action="reset-filters">Reset filters</button>
      <?php if ($q !== ''): ?>
        <button type="button" class="btn btn-ghost" data-action="clear-search">Clear search</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="tn-table-wrap" id="tnTableWrap">
    <table class="tn-table" id="tnTable">
      <thead>
        <tr>
          <th style="width: 32%;">Tournament</th>
          <th>Entry</th>
          <th>Prize</th>
          <th>Start</th>
          <th>Ends in</th>
          <th aria-label="Action"></th>
        </tr>
      </thead>
      <tbody>
        <?php echo $layout; /* rendered by helpers/common-render.php with .tn-* markup */ ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination (reuses Zebra pagination renderer; .hub-pager classes
       are applied via fec-edition.css overrides scoped under .fecx). -->
  <nav class="hub-pager" aria-label="Pagination" id="tnPager">
    <?php
      $pagination->padding(false);
      echo $pagination->render();
    ?>
  </nav>
</div>

<!-- ========================== TN-HOWITWORKS (static content) ========================== -->
<?php if ($isFirst && !$isFiltered): ?>
<section class="tn-howitworks">
  <div class="wrap">
    <div class="tn-howitworks-grid">
      <div>
        <div class="eyebrow">Field guide</div>
        <h2>How casino tournaments actually work.</h2>
        <p>An online casino tournament is a leaderboard competition where players compete based on play activity &mdash; total wagered, biggest win, or net winnings, depending on the format. The casino sets a prize pool, an entry fee (often zero for freerolls), and a fixed start and end window.</p>
        <p>Two formats dominate: leaderboard tournaments rank players by a metric across the whole window and pay out top finishers; prize-draw tournaments enter players into a random pool, weighted by activity. Both look similar from the outside but reward very different play patterns.</p>
        <p>Three things matter before you join: eligibility (some tournaments are restricted by country, VIP tier, or new-player status), opt-in mechanic (some are automatic, some require a single click on the promotion page), and qualifying games &mdash; playing the wrong slot leaves your spins outside the leaderboard.</p>
      </div>
      <div class="tn-howitworks-points">
        <div class="point">
          <div class="t">Leaderboards</div>
          <div class="d">Top X players by metric (wager, win, net) split the prize pool. Strategy: high volume of qualifying spins.</div>
        </div>
        <div class="point">
          <div class="t">Prize draws</div>
          <div class="d">Random pool, often weighted by activity. Strategy: meet the activity threshold, then volume has diminishing returns.</div>
        </div>
        <div class="point">
          <div class="t">Freerolls</div>
          <div class="d">Zero entry fee. Almost always restricted to specific slots and have a small prize cap. Good no-risk practice.</div>
        </div>
        <div class="point">
          <div class="t">Buy-in events</div>
          <div class="d">Real entry fee, real prize pool. Treat them like cash games &mdash; only join if the EV math makes sense for you.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== TN-FAQ (static content) ========================== -->
<section class="tn-faq-section">
  <div class="wrap">
    <div class="eyebrow">Answers</div>
    <h2>Frequently asked questions.</h2>
    <p class="sub">Quick answers before you join a tournament.</p>

    <div class="tn-faq-controls" role="group" aria-label="FAQ controls">
      <button class="chip" type="button" data-faq-action="open">Open all</button>
      <button class="chip" type="button" data-faq-action="close">Close all</button>
    </div>

    <div class="bd-faq" itemscope itemtype="https://schema.org/FAQPage">
      <?php
        // $faqItems is defined up-top alongside the FAQ JSON-LD payload —
        // single source of truth, so editing copy means editing one place
        // and the structured-data stays in lockstep with what users see.
        // If admin editing becomes a need, move the array to XML <fields>
        // in default.xml and read via $this->params (mirrors team page).
        foreach ($faqItems as $i => $f):
          $isOpen = ($i === 0); // first item open by default
      ?>
        <div class="item<?php echo $isOpen ? ' open' : ''; ?>" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
          <div class="q" data-faq-toggle>
            <span itemprop="name"><?php echo $f['q']; ?></span>
            <span class="chev" aria-hidden="true">&rsaquo;</span>
          </div>
          <div class="a" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
            <div itemprop="text"><?php echo $f['a']; ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($faqJsonLd !== ''): ?>
<script type="application/ld+json"><?php echo $faqJsonLd; ?></script>
<?php endif; ?>
<?php endif; ?>

<!-- ========================== TN-DRAWER (filter side panel) ========================== -->
<!-- Overlay is a sibling, not a child, of the drawer (matches mockup CSS:
     both position:fixed, opacity toggled by the .open class on each). -->
<div class="tn-drawer-overlay" data-action="close-filters" aria-hidden="true"></div>

<aside class="tn-drawer" id="tfilters" aria-label="Filters" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tnDrawerTitle">
  <header class="tn-drawer-head">
    <h3 id="tnDrawerTitle">Filters</h3>
    <button class="close" type="button" data-action="close-filters" aria-label="Close filters">&#10005;</button>
  </header>

  <form class="tn-drawer-body" id="tfiltersForm" method="get"
        action="<?php echo htmlspecialchars(JUri::current(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($isFiltered): ?>
      <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (!empty($q)): ?>
      <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <div class="tn-drawer-group">
      <h4>Status</h4>
      <label><input type="checkbox" name="status[]" value="running"> Running</label>
      <label><input type="checkbox" name="status[]" value="starting_soon"> Starting soon</label>
      <label><input type="checkbox" name="status[]" value="upcoming"> Upcoming</label>
    </div>

    <div class="tn-drawer-group">
      <h4>Type</h4>
      <label><input type="checkbox" name="type[]" value="freeroll"> Freeroll</label>
      <label><input type="checkbox" name="type[]" value="with_fee"> With fee</label>
    </div>

    <div class="tn-drawer-group">
      <h4>Entry fee</h4>
      <input type="number" name="entry_max" min="0" step="0.01" placeholder="Max entry (e.g. 5)">
      <div class="hint">Tip: use 0 for freerolls.</div>
    </div>

    <div class="tn-drawer-group">
      <h4>Prize pool</h4>
      <input type="number" name="prize_min" min="0" step="1" placeholder="Min prize (e.g. 1000)">
    </div>

    <div class="tn-drawer-group">
      <h4>Ends within</h4>
      <select name="ends_within">
        <option value="">Any</option>
        <option value="1h">1 hour</option>
        <option value="6h">6 hours</option>
        <option value="24h">24 hours</option>
        <option value="3d">3 days</option>
      </select>
    </div>
  </form>

  <footer class="tn-drawer-foot">
    <button class="btn btn-ghost" type="button" data-action="reset-filters">Reset</button>
    <button class="btn btn-primary" type="button" data-action="apply-filters">Apply</button>
  </footer>
</aside>

<script>
/* =============================================================================
 * Tournaments — page JS (FEC V2.1).
 * Streamlined from the legacy view: dropped the mobile-cards generation path
 * (the new .tn-table uses CSS-only responsive hiding via @media (max-width:
 * 1000px) in fec-edition.css, so the runtime DOM stays a single source of truth)
 * and the disabled-join inference helpers (the server now emits the .tn-unavail
 * markup directly). What remains: drawer open/close, filter-state init from URL,
 * active-count pill, sort dropdown, search clear, FAQ toggle, zero-state, and
 * bfcache rerun.
 * ========================================================================== */
(function () {
  // ---------- helpers ----------
  function qs(s, r) { return (r || document).querySelector(s); }
  function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function isEmpty(v) { return v == null || String(v).trim() === ''; }
  function safeURL() { try { return new URL(window.location.href); } catch (e) { return null; } }

  function stripEmptyParams(formEl) {
    if (!formEl) return;
    qsa('input, select, textarea', formEl).forEach(function (el) {
      if (!el.name) return;
      if (el.name === 'option' || el.name === 'view') return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (!el.checked) el.disabled = true;
        return;
      }
      if (isEmpty(el.value)) el.disabled = true;
    });
  }
  function enableAll(formEl) {
    if (!formEl) return;
    qsa('input, select, textarea', formEl).forEach(function (el) { el.disabled = false; });
  }

  // ---------- elements ----------
  var openBtn     = qs('[data-action="open-filters"]');
  var drawer      = qs('#tfilters');
  var closeBtns   = qsa('[data-action="close-filters"]');
  var form        = qs('#tfiltersForm');
  var pill        = qs('[data-activecount]');
  var sortSel     = qs('#tsort');
  var searchInput = qs('#tsearch');
  var filterRoot  = qs('.tn-filter');

  // ---------- filter state from URL ----------
  function initFiltersFromUrl() {
    if (!form) return;
    var url = safeURL(); if (!url) return;
    var sp = url.searchParams;

    qsa('input[type="checkbox"]', form).forEach(function (cb) {
      if (!cb.name) return;
      var vals = sp.getAll(cb.name);
      if (!vals.length && /\[\]$/.test(cb.name)) {
        vals = sp.getAll(cb.name.replace(/\[\]$/, ''));
      }
      cb.checked = vals.indexOf(cb.value) !== -1;
    });

    ['entry_max', 'prize_min'].forEach(function (n) {
      var el = qs('[name="' + n + '"]', form);
      if (!el) return;
      var v = sp.get(n);
      if (v !== null) el.value = v;
    });

    var ends = qs('[name="ends_within"]', form);
    if (ends) {
      var v2 = sp.get('ends_within');
      if (v2 !== null) ends.value = v2;
    }
  }

  // ---------- active count pill ----------
  function countActive() {
    if (!form) return 0;
    var n = 0;
    qsa('input[type="checkbox"]', form).forEach(function (c) { if (c.checked) n++; });
    ['entry_max', 'prize_min', 'ends_within'].forEach(function (name) {
      var el = qs('[name="' + name + '"]', form);
      if (el && !isEmpty(el.value)) n++;
    });
    return n;
  }
  function syncCount() {
    if (!pill) return;
    var n = countActive();
    pill.hidden = (n === 0);
    pill.textContent = String(n);
    if (filterRoot) filterRoot.classList.toggle('has-active-filters', n > 0);
  }
  document.addEventListener('change', function (e) {
    if (form && form.contains(e.target)) syncCount();
  });

  // ---------- drawer ----------
  var drawerOverlay = qs('.tn-drawer-overlay');
  function setDrawer(open) {
    if (!drawer || !openBtn) return;
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    drawer.classList.toggle('open', !!open);
    if (drawerOverlay) drawerOverlay.classList.toggle('open', !!open);
    openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.documentElement.classList.toggle('fec-lock', !!open);
    document.body.classList.toggle('fec-lock', !!open);
    if (open) {
      var first = qs('.tn-drawer input, .tn-drawer select, .tn-drawer button', drawer);
      if (first) first.focus();
    } else {
      openBtn.focus();
      enableAll(form);
    }
  }
  if (openBtn && drawer) {
    openBtn.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      var isOpen = drawer.getAttribute('aria-hidden') === 'false';
      setDrawer(!isOpen);
    });
  }
  closeBtns.forEach(function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); setDrawer(false); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setDrawer(false);
  });
  document.addEventListener('click', function (e) {
    if (!drawer || drawer.getAttribute('aria-hidden') !== 'false') return;
    // Drawer IS the panel (overlay is a sibling). Clicks inside the drawer
    // stay open; clicks on the overlay (or anywhere else outside) close.
    if (drawer.contains(e.target)) return;
    var overlay = e.target.closest && e.target.closest('.tn-drawer-overlay');
    if (overlay) setDrawer(false);
  });

  // ---------- apply / reset ----------
  qsa('[data-action="apply-filters"]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!form) return;
      stripEmptyParams(form);
      form.submit();
    });
  });
  qsa('[data-action="reset-filters"]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!form) return;
      qsa('input[type="checkbox"]', form).forEach(function (c) { c.checked = false; });
      ['entry_max', 'prize_min'].forEach(function (n) {
        var el = qs('[name="' + n + '"]', form);
        if (el) el.value = '';
      });
      var ends = qs('[name="ends_within"]', form);
      if (ends) ends.value = '';
      syncCount();
      stripEmptyParams(form);
      form.submit();
    });
  });

  // ---------- sort ----------
  if (sortSel) {
    var u0 = safeURL();
    if (u0) {
      var curSort = u0.searchParams.get('sort');
      if (curSort) sortSel.value = curSort;
    }
    sortSel.addEventListener('change', function () {
      var url = safeURL(); if (!url) return;
      url.searchParams.set('sort', sortSel.value);
      url.searchParams.delete('page');
      url.searchParams.delete('limitstart');
      window.location.href = url.toString();
    });
  }

  // ---------- search clear ----------
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-action="clear-search"]');
    if (!btn) return;
    e.preventDefault(); e.stopPropagation();
    if (searchInput) searchInput.value = '';
    var url = safeURL(); if (!url) return;
    ['q', 'search', 'query', 's', 'page', 'limitstart'].forEach(function (p) {
      url.searchParams.delete(p);
    });
    window.location.href = url.toString();
  }, true);

  // ---------- FAQ accordion ----------
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest && e.target.closest('[data-faq-toggle]');
    if (toggle) {
      var item = toggle.closest('.bd-faq .item');
      if (item) item.classList.toggle('open');
      return;
    }
    var ctl = e.target.closest && e.target.closest('[data-faq-action]');
    if (ctl) {
      var action = ctl.getAttribute('data-faq-action');
      qsa('.bd-faq .item').forEach(function (it) {
        it.classList.toggle('open', action === 'open');
      });
    }
  });

  // ---------- zero state ----------
  function syncZeroState() {
    var zero  = qs('#tnZeroState');
    var table = qs('#tnTable');
    var wrap  = qs('#tnTableWrap');
    var pager = qs('#tnPager');
    if (!zero || !table) return;

    var hasRows = qsa('tbody tr', table).length > 0;
    var q = '';
    var u = safeURL();
    if (u) q = (u.searchParams.get('q') || '').trim();

    var textEl = qs('.tn-zero__text', zero);
    if (!hasRows) {
      zero.hidden = false;
      if (textEl) {
        textEl.textContent = q
          ? 'No results for "' + q + '". Try a different search or clear it.'
          : "Your current filters didn\u2019t match any active tournaments.";
      }
      if (wrap)  wrap.hidden  = true;
      if (pager) pager.hidden = true;
    } else {
      zero.hidden = true;
      if (wrap)  wrap.hidden  = false;
      if (pager) pager.hidden = false;
    }
  }

  // ---------- live countdown ticker (header status pill) ----------
  function liveCountdown() {
    var el = qs('[data-live-countdown]');
    var lastEl = qs('[data-live-lastupdate]');
    if (!el) return;
    var interval = <?php echo (int)$updateInterval; ?>;
    var n = interval;
    setInterval(function () {
      n = (n > 1) ? n - 1 : interval;
      el.textContent = n + 's';
      // When the counter resets, refresh the "Last" label client-side. The
      // table is NOT re-fetched (full SSR page); the label is cosmetic, a
      // signal that the server view is fresh on next navigation.
      if (n === interval && lastEl) {
        var d = new Date();
        var hh = String(d.getHours()).padStart(2, '0');
        var mm = String(d.getMinutes()).padStart(2, '0');
        lastEl.textContent = hh + ':' + mm;
      }
    }, 1000);
  }

  // ---------- init ----------
  function init() {
    initFiltersFromUrl();
    syncCount();
    syncZeroState();
    liveCountdown();
  }
  init();

  // bfcache restore (Back/Forward) — rerun state sync
  window.addEventListener('pageshow', function (e) { if (e.persisted) init(); });
})();
</script>