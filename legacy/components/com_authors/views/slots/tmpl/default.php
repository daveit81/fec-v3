<?php
/**
 * Slots index template — /slots
 *
 * 2.7.91: hero with live numbers, at-a-glance rows, provider chips, a
 * sort/filter toolbar and a client-side pager over the full card grid.
 * The server renders every card in freshness order; the browser only
 * reorders, shows and hides. Without JavaScript the full grid stays
 * visible in server order and the chips still work as links.
 *
 * Relative dates ("3 days ago") are computed in the browser from data-ts,
 * never on the server: the page sits in the Cloudflare edge cache.
 */
defined('_JEXEC') or die;

$slots           = $this->slots;
$allSlots        = $this->allSlots;
$providers       = $this->providers;
$active_provider = $this->filter_provider;
$stats           = $this->stats;
$glance          = $this->glance;

$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$nfmt = function ($n) { return number_format((int)$n); };
$pct  = function ($v) { return number_format((float)$v, 2); };

$indexUrl       = JRoute::_('index.php?option=com_authors&view=slots');
$methodologyUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=rtp-verification');
$slotUrl        = function ($slug) { return JRoute::_('index.php?option=com_authors&view=slot&slug=' . urlencode($slug)); };

// Date → unix timestamp + absolute label (server never prints relative text)
$dateTs  = function ($d) { $d = (string)$d; if ($d === '' || $d === '0000-00-00') return 0; $t = strtotime($d); return $t ? (int)$t : 0; };
$dateAbs = function ($ts) { return $ts ? date('j M Y', $ts) : ''; };

// Volatility → three buckets for the filter
$volBucket = function ($v) {
    $v = strtolower(trim((string)$v));
    if ($v === '') return '';
    if ($v === 'low' || $v === 'low-med') return 'low';
    if ($v === 'medium' || $v === 'med-high') return 'med';
    return 'high';
};

// Number of published RTP tiers, from the variants list when it has them
$tierCount = function ($s) {
    $n = 0;
    if (!empty($s->rtp_variants_json)) {
        $arr = json_decode($s->rtp_variants_json, true);
        if (is_array($arr)) {
            foreach ($arr as $v) {
                if (is_numeric($v) || preg_match('/\d{2}(\.\d+)?/', (string)$v)) $n++;
            }
        }
    }
    if ($s->is_multi_tier && $n < 2) $n = 2;
    if (!$s->is_multi_tier) $n = 1;
    return $n;
};

$total      = (int)$stats['total'];
$isFiltered = ($active_provider !== '');
$lastTs     = $dateTs($stats['lastVerified']);
$highest    = $stats['highest'];

?>
<div class="fecx slt-index-page">

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow">Slot Database</div>
        <h1 class="me-title">RTP-Verified Slots<?php if ($isFiltered): ?> · <?php echo $h($active_provider); ?><?php endif; ?></h1>
        <p class="me-tagline">
          Slot data verified against provider primary sources, archived on the Wayback Machine,
          and cross-checked with in-game paytables at licensed operators.
          <?php if ($isFiltered): ?>
            <?php echo $nfmt(count($slots)); ?> <?php echo $h($active_provider); ?> slot<?php echo count($slots) === 1 ? '' : 's'; ?>
            of <?php echo $nfmt($total); ?> documented.
          <?php else: ?>
            <?php echo $nfmt($total); ?> slot<?php echo $total === 1 ? '' : 's'; ?> currently documented;
            newest verification first, re-order below.
          <?php endif; ?>
        </p>
        <div class="me-byline">
          <span><a href="<?php echo $methodologyUrl; ?>">Read methodology →</a></span>
          <span class="div">·</span>
          <span><a href="#slt-legend">How to read a card</a></span>
          <?php if ($isFiltered): ?>
            <span class="div">·</span>
            <span><a href="<?php echo $h($indexUrl); ?>">All slots</a></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($total > 0): ?>
    <!-- Stats strip: whole database, even on a filtered page -->
    <div class="au-stats-strip slt-index-stats">
      <div class="stat">
        <div class="num"><?php echo $nfmt($total); ?></div>
        <div class="lbl">Slots documented</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $nfmt($stats['providers']); ?></div>
        <div class="lbl">Providers</div>
      </div>
      <div class="stat<?php echo $stats['multi'] > 0 ? ' slt-warn' : ''; ?>">
        <div class="num"><?php echo $nfmt($stats['multi']); ?></div>
        <div class="lbl">Multi-tier titles<?php echo $stats['multi'] > 0 ? ' ⚠' : ''; ?></div>
      </div>
      <?php if ($highest): ?>
      <div class="stat">
        <div class="num"><?php echo $h($pct($highest->rtp_theoretical_max)); ?>%</div>
        <div class="lbl">Highest RTP · <?php echo $h($highest->game_name); ?></div>
      </div>
      <?php endif; ?>
      <?php if ($lastTs): ?>
      <div class="stat">
        <div class="num"><time datetime="<?php echo $h($stats['lastVerified']); ?>" data-ts="<?php echo (int)$lastTs; ?>"><?php echo $h($dateAbs($lastTs)); ?></time></div>
        <div class="lbl">Last verification<?php if ($stats['lastSlot']): ?> · <?php echo $h($stats['lastSlot']->game_name); ?><?php endif; ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="wrap slt-index-main">

  <?php if (!$isFiltered && $total >= 6): ?>
  <!-- At a glance: three short lists, computed over the whole database -->
  <div class="slt-glance" aria-label="At a glance">
    <?php
      $glanceCols = array(
        array('Highest RTP on file', $glance['rtp'], 'rtp'),
        array('Widest tier spread', $glance['spread'], 'spread'),
        array('Verified most recently', $glance['recent'], 'recent'),
      );
      foreach ($glanceCols as $col):
        list($title, $rows, $kind) = $col;
        if (empty($rows)) continue;
    ?>
      <div class="slt-glance-col">
        <div class="slt-glance-title"><?php echo $h($title); ?></div>
        <ol class="slt-glance-list">
          <?php foreach ($rows as $g): ?>
            <li>
              <a href="<?php echo $h($slotUrl($g->slug)); ?>"><?php echo $h($g->game_name); ?></a>
              <span class="slt-glance-val">
                <?php if ($kind === 'rtp'): ?>
                  <?php echo $h($pct($g->rtp_theoretical_max)); ?>%
                <?php elseif ($kind === 'spread'): ?>
                  <?php echo $h(number_format((float)$g->rtp_theoretical_max - (float)$g->rtp_theoretical_min, 2)); ?> pp
                <?php else: $gts = $dateTs($g->last_verified_date); ?>
                  <time datetime="<?php echo $h($g->last_verified_date); ?>" data-ts="<?php echo (int)$gts; ?>"><?php echo $h($dateAbs($gts)); ?></time>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Provider chips: links on a filtered page, client-side filters on the full one -->
  <?php if (!empty($providers)): ?>
    <div class="slt-index-filters" id="slt-chips">
      <a class="slt-filter-chip<?php echo $active_provider === '' ? ' is-active' : ''; ?>"
         href="<?php echo $h($indexUrl); ?>" data-provider="">All <span class="cnt"><?php echo $nfmt($total); ?></span></a>
      <?php foreach ($providers as $p):
        $providerUrl = JRoute::_('index.php?option=com_authors&view=slots&provider=' . urlencode($p->name));
      ?>
        <a class="slt-filter-chip<?php echo $active_provider === $p->name ? ' is-active' : ''; ?>"
           href="<?php echo $h($providerUrl); ?>" data-provider="<?php echo $h(strtolower($p->name)); ?>">
          <?php echo $h($p->name); ?> <span class="cnt"><?php echo $nfmt($p->cnt); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($slots)): ?>
    <p class="slt-empty">
      No slots published yet for this filter. New entries are published from the editorial dashboard.
    </p>
  <?php else: ?>

    <!-- Toolbar: order, filters, search. Client side only. -->
    <form class="wp-controls slt-controls" id="slt-controls" onsubmit="return false;" aria-label="Sort and filter">
      <label class="wp-ctl">
        <span>Order</span>
        <select id="slt-sort">
          <option value="verified-desc" selected>Recently verified</option>
          <option value="rtp-desc">RTP, highest first</option>
          <option value="rtp-asc">RTP, lowest first</option>
          <option value="spread-desc">Widest tier spread</option>
          <option value="bonuses-desc">Most active bonuses</option>
          <option value="maxwin-desc">Biggest max win</option>
          <option value="year-desc">Newest release</option>
          <option value="name-asc">Name, A to Z</option>
        </select>
      </label>
      <label class="wp-ctl">
        <span>RTP tiers</span>
        <select id="slt-tier">
          <option value="all" selected>Any</option>
          <option value="single">Single-tier</option>
          <option value="multi">Multi-tier</option>
        </select>
      </label>
      <label class="wp-ctl">
        <span>RTP band</span>
        <select id="slt-rtp">
          <option value="all" selected>Any</option>
          <option value="97">97% and above</option>
          <option value="96">96% to 96.99%</option>
          <option value="lt96">Below 96%</option>
        </select>
      </label>
      <label class="wp-ctl">
        <span>Volatility</span>
        <select id="slt-vol">
          <option value="all" selected>Any</option>
          <option value="low">Low</option>
          <option value="med">Medium</option>
          <option value="high">High and above</option>
        </select>
      </label>
      <label class="wp-ctl wp-ctl--search">
        <span>Slot or provider</span>
        <input type="search" id="slt-q" placeholder="Type a name" autocomplete="off">
      </label>
      <label class="wp-ctl wp-ctl--check">
        <input type="checkbox" id="slt-bonus"> <span class="wp-check-label">With active bonuses only</span>
      </label>
      <span class="wp-count" id="slt-count" aria-live="polite"></span>
    </form>

    <p class="wp-none" id="slt-none" hidden>No slot matches these filters. Clear a filter or pick another provider.</p>

    <!-- Slot grid: every published card, in freshness order -->
    <ul class="slt-index-grid" id="slt-grid" data-filtered="<?php echo $isFiltered ? '1' : '0'; ?>">
      <?php foreach ($slots as $s):
        $rtpMax   = $s->rtp_theoretical_max !== null ? $pct($s->rtp_theoretical_max) : null;
        $rtpMin   = $s->rtp_theoretical_min !== null ? $pct($s->rtp_theoretical_min) : null;
        $spread   = ($s->is_multi_tier && $rtpMax !== null && $rtpMin !== null)
                    ? number_format((float)$s->rtp_theoretical_max - (float)$s->rtp_theoretical_min, 2) : '';
        $tiers    = $tierCount($s);
        $isHigh   = ($s->rtp_theoretical_max !== null && (float)$s->rtp_theoretical_max >= 97.0);
        $vts      = $dateTs($s->last_verified_date);
        $pts      = $dateTs($s->published_at);
        $url      = $slotUrl($s->slug);
        $classes  = 'slt-card';
        if ($s->is_multi_tier) $classes .= ' is-multi-tier';
        if ($isHigh)           $classes .= ' is-high-rtp';
      ?>
        <li class="<?php echo $classes; ?>"
            data-name="<?php echo $h(strtolower($s->game_name)); ?>"
            data-provider="<?php echo $h(strtolower($s->provider_name)); ?>"
            data-rtp="<?php echo $rtpMax !== null ? $h($rtpMax) : ''; ?>"
            data-rtpmin="<?php echo $rtpMin !== null ? $h($rtpMin) : ''; ?>"
            data-spread="<?php echo $h($spread); ?>"
            data-tier="<?php echo $s->is_multi_tier ? 'multi' : 'single'; ?>"
            data-vol="<?php echo $h($volBucket($s->volatility)); ?>"
            data-bonuses="<?php echo (int)$s->active_bonus_count; ?>"
            data-maxwin="<?php echo !empty($s->max_win_x) ? (int)$s->max_win_x : ''; ?>"
            data-year="<?php echo !empty($s->release_year) ? (int)$s->release_year : ''; ?>"
            data-verified="<?php echo (int)$vts; ?>"
            data-published="<?php echo (int)$pts; ?>">
          <a class="slt-card-link" href="<?php echo $h($url); ?>">
            <div class="slt-card-head">
              <h3 class="slt-card-name"><?php echo $h($s->game_name); ?></h3>
              <div class="slt-card-provider"><?php echo $h($s->provider_name); ?></div>
            </div>
            <div class="slt-card-stats">
              <?php if ($rtpMax !== null): ?>
                <div class="slt-card-rtp">
                  <span class="num"><?php echo $h($rtpMax); ?>%</span>
                  <span class="lbl">RTP<?php echo $s->is_multi_tier ? ' max' : ''; ?></span>
                </div>
                <?php if ($s->is_multi_tier && $rtpMin !== null): ?>
                  <div class="slt-card-rtp slt-card-rtp-min">
                    <span class="num"><?php echo $h($rtpMin); ?>%</span>
                    <span class="lbl">min</span>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="slt-card-rtp">
                  <span class="num slt-card-na">—</span>
                  <span class="lbl">RTP not stated</span>
                </div>
              <?php endif; ?>
              <?php if (!empty($s->volatility)): ?>
                <div class="slt-card-tag"><?php echo $h(AuthorsHelper::volatilityLabel($s->volatility)); ?></div>
              <?php endif; ?>
            </div>
            <div class="slt-card-facts">
              <?php if ($s->is_multi_tier): ?>
                <span class="slt-card-fact is-warn"><?php echo (int)$tiers; ?> tiers<?php if ($spread !== ''): ?> · <?php echo $h($spread); ?> pp spread<?php endif; ?></span>
              <?php else: ?>
                <span class="slt-card-fact">Single tier</span>
              <?php endif; ?>
              <?php if (!empty($s->max_win_x)): ?>
                <span class="slt-card-fact">Max win <?php echo $nfmt($s->max_win_x); ?>×</span>
              <?php endif; ?>
              <?php if (!empty($s->release_year)): ?>
                <span class="slt-card-fact"><?php echo (int)$s->release_year; ?></span>
              <?php endif; ?>
            </div>
            <div class="slt-card-meta">
              <?php if ((int)$s->active_bonus_count > 0): ?>
                <span><?php echo $nfmt($s->active_bonus_count); ?> active bonus<?php echo (int)$s->active_bonus_count === 1 ? '' : 'es'; ?></span>
              <?php else: ?>
                <span class="slt-card-mute">No active bonus</span>
              <?php endif; ?>
              <?php if ((int)$s->obs_count > 0): ?>
                <span><?php echo $nfmt($s->obs_count); ?> obs</span>
              <?php endif; ?>
              <?php if ($isHigh): ?>
                <span class="slt-card-flag is-high">97%+</span>
              <?php endif; ?>
              <?php if ($s->is_multi_tier): ?>
                <span class="slt-card-flag">⚠ multi-tier</span>
              <?php endif; ?>
            </div>
            <div class="slt-card-verified">
              <?php if ($vts): ?>
                Verified <time datetime="<?php echo $h($s->last_verified_date); ?>" data-ts="<?php echo (int)$vts; ?>"><?php echo $h($dateAbs($vts)); ?></time>
                <?php if (!empty($s->author_name)): ?>
                  <span class="div">·</span> <?php echo $h($s->author_name); ?>
                <?php endif; ?>
              <?php else: ?>
                <span class="slt-card-mute">Verification date not recorded</span>
              <?php endif; ?>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <nav class="wp-pager fec-pg" id="slt-pager" hidden aria-label="Slot pages"></nav>

  <?php endif; ?>

  <div class="slt-index-footer" id="slt-legend">
    <p>
      <b>How were these verified?</b>
      Every record links to the primary provider source (or, where unavailable, to per-casino paytable
      observations). Read the <a href="<?php echo $methodologyUrl; ?>">full methodology</a>.
    </p>
    <ul class="slt-legend">
      <li><b>RTP max / min</b> — the highest and lowest return the provider ships for that title. One number means one published configuration.</li>
      <li><b>Multi-tier</b> (amber edge) — the operator chooses the version; the spread in percentage points is what that choice can cost you. Check the in-game paytable before you play.</li>
      <li><b>97%+</b> (teal edge) — among the highest-returning slots in circulation. Conditional figures, such as Supermeter modes, are explained on the slot page.</li>
      <li><b>Verified</b> — the day an editor last checked the primary source. Dates are relative to today.</li>
    </ul>
  </div>

</section>

<?php if (!empty($slots)): ?>
<script>
/* RTP-verified slots index (2.7.91): client-side order, filters, search and
   pages over the server-rendered cards. Values live in data-* attributes;
   the DOM is only reordered and shown/hidden. Relative dates come from
   data-ts in the browser because the page is edge-cached. Without this
   script every card stays visible in server order (newest verification
   first) and the provider chips keep working as plain links. */
(function () {
  var PAGE = 24;
  var grid = document.getElementById('slt-grid');
  if (!grid) return;

  /* relative dates */
  function rel(ts) {
    var d = (Date.now() / 1000 - ts) / 86400;
    if (d < 1) return 'today';
    if (d < 2) return 'yesterday';
    if (d < 14) return Math.floor(d) + ' days ago';
    if (d < 60) return Math.floor(d / 7) + ' weeks ago';
    if (d < 365) return Math.floor(d / 30) + ' months ago';
    var y = Math.floor(d / 365);
    return y + (y === 1 ? ' year ago' : ' years ago');
  }
  var times = document.querySelectorAll('[data-ts]');
  for (var t = 0; t < times.length; t++) {
    var ts = parseInt(times[t].getAttribute('data-ts'), 10);
    if (!ts) continue;
    times[t].setAttribute('title', times[t].textContent);
    times[t].textContent = rel(ts);
  }

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.slt-card'));
  var serverFiltered = grid.getAttribute('data-filtered') === '1';
  var sortEl  = document.getElementById('slt-sort');
  var tierEl  = document.getElementById('slt-tier');
  var rtpEl   = document.getElementById('slt-rtp');
  var volEl   = document.getElementById('slt-vol');
  var qEl     = document.getElementById('slt-q');
  var bonusEl = document.getElementById('slt-bonus');
  var countEl = document.getElementById('slt-count');
  var noneEl  = document.getElementById('slt-none');
  var pagerEl = document.getElementById('slt-pager');
  var chipsEl = document.getElementById('slt-chips');
  var controls = document.getElementById('slt-controls');
  if (!sortEl || !tierEl || !rtpEl || !volEl || !qEl || !bonusEl) return;

  var state = { provider: '' };

  /* pager: pages a list of cards, draws the .fec-pg nav, updates the count */
  function makePager(items, pager, counter, scrollTo) {
    var st = { page: 1, all: false, visible: items.slice() };
    function draw() {
      var total = st.visible.length;
      var pages = st.all ? 1 : Math.max(1, Math.ceil(total / PAGE));
      if (st.page > pages) st.page = pages;
      var from = st.all ? 0 : (st.page - 1) * PAGE;
      var to = st.all ? total : Math.min(total, from + PAGE);
      for (var i = 0; i < items.length; i++) items[i].style.display = 'none';
      for (var j = from; j < to; j++) st.visible[j].style.display = '';
      if (counter) counter.textContent = total === 0 ? 'Showing 0 of 0' : ('Showing ' + (from + 1) + '–' + to + ' of ' + total);
      if (!pager) return;
      if (total <= PAGE) { pager.hidden = true; pager.innerHTML = ''; return; }
      var out = [];
      function li(label, page, cls, aria) {
        out.push('<li class="' + (cls || '') + '"><a href="#slt-controls" data-page="' + page + '"' + (aria ? ' aria-label="' + aria + '"' : '') + '>' + label + '</a></li>');
      }
      if (st.all) {
        li('Show ' + PAGE + ' per page', 'paged', '');
      } else {
        li('‹', st.page - 1, st.page === 1 ? 'disabled' : '', 'Previous page');
        var last = 0;
        for (var p = 1; p <= pages; p++) {
          var near = Math.abs(p - st.page) <= 1 || p === 1 || p === pages;
          if (!near) { if (last !== -1) { out.push('<li><span>…</span></li>'); last = -1; } continue; }
          li(String(p), p, p === st.page ? 'active' : '');
          last = p;
        }
        li('›', st.page + 1, st.page === pages ? 'disabled' : '', 'Next page');
        li('Show all ' + total, 'all', '');
      }
      pager.innerHTML = '<ul class="pagination">' + out.join('') + '</ul>';
      pager.hidden = false;
    }
    if (pager) {
      pager.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-page]') : null;
        if (!a) return;
        e.preventDefault();
        if (a.parentNode.className.indexOf('disabled') !== -1) return;
        var v = a.getAttribute('data-page');
        if (v === 'all') { st.all = true; }
        else if (v === 'paged') { st.all = false; st.page = 1; }
        else { st.page = parseInt(v, 10) || 1; }
        draw();
        if (scrollTo && scrollTo.getBoundingClientRect().top < 0) scrollTo.scrollIntoView({ block: 'start' });
      });
    }
    return { set: function (visible) { st.visible = visible; st.page = 1; draw(); } };
  }
  var pager = makePager(cards, pagerEl, countEl, controls);

  /* orders */
  function num(c, k) { var v = c.getAttribute('data-' + k); return (v === null || v === '') ? null : parseFloat(v); }
  function byName(a, b) { return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name')); }
  function cmp(key, dir, tiebreak) {
    return function (a, b) {
      var x = num(a, key), y = num(b, key);
      if (x === null && y === null) return byName(a, b);
      if (x === null) return 1;      /* not stated always last */
      if (y === null) return -1;
      if (x === y) {
        if (tiebreak) { var p = num(a, tiebreak) || 0, q = num(b, tiebreak) || 0; if (p !== q) return dir * (p - q); }
        return byName(a, b);
      }
      return dir * (x - y);
    };
  }
  var orders = {
    'verified-desc': cmp('verified', -1, 'published'),
    'rtp-desc': cmp('rtp', -1), 'rtp-asc': cmp('rtp', 1),
    'spread-desc': cmp('spread', -1), 'bonuses-desc': cmp('bonuses', -1, 'rtp'),
    'maxwin-desc': cmp('maxwin', -1), 'year-desc': cmp('year', -1, 'verified'),
    'name-asc': byName
  };
  var defaults = { sort: 'verified-desc', tier: 'all', rtp: 'all', vol: 'all', q: '', bonus: false, p: '' };

  /* URL state: only non-default values are written; the server ignores them */
  function readUrl() {
    var out = {};
    var qs = (location.search || '').replace(/^\?/, '');
    if (qs === '') return out;
    var parts = qs.split('&');
    for (var i = 0; i < parts.length; i++) {
      var kv = parts[i].split('=');
      if (!kv[0]) continue;
      try { out[decodeURIComponent(kv[0])] = decodeURIComponent((kv[1] || '').replace(/\+/g, ' ')); } catch (e) {}
    }
    return out;
  }
  function writeUrl() {
    if (!window.history || !history.replaceState) return;
    var params = [];
    if (sortEl.value !== defaults.sort) params.push('sort=' + encodeURIComponent(sortEl.value));
    if (tierEl.value !== defaults.tier) params.push('tier=' + encodeURIComponent(tierEl.value));
    if (rtpEl.value !== defaults.rtp) params.push('rtp=' + encodeURIComponent(rtpEl.value));
    if (volEl.value !== defaults.vol) params.push('vol=' + encodeURIComponent(volEl.value));
    if (bonusEl.checked) params.push('bonus=1');
    if (qEl.value.replace(/^\s+|\s+$/g, '') !== '') params.push('q=' + encodeURIComponent(qEl.value.replace(/^\s+|\s+$/g, '')));
    if (!serverFiltered && state.provider !== '') params.push('p=' + encodeURIComponent(state.provider));
    var keep = readUrl();   /* preserve params that are not ours (e.g. provider on a filtered page) */
    for (var k in keep) {
      if (!Object.prototype.hasOwnProperty.call(keep, k)) continue;
      if (k === 'sort' || k === 'tier' || k === 'rtp' || k === 'vol' || k === 'bonus' || k === 'q' || k === 'p') continue;
      params.push(encodeURIComponent(k) + '=' + encodeURIComponent(keep[k]));
    }
    try { history.replaceState(null, '', location.pathname + (params.length ? '?' + params.join('&') : '') + location.hash); } catch (e) {}
  }

  function setChip(provider) {
    if (!chipsEl) return;
    var chips = chipsEl.querySelectorAll('.slt-filter-chip');
    for (var i = 0; i < chips.length; i++) {
      var p = chips[i].getAttribute('data-provider') || '';
      chips[i].className = chips[i].className.replace(/\s*is-active/g, '');
      if (p === provider) {
        chips[i].className += ' is-active';
        if (chips[i].scrollIntoView) { try { chips[i].scrollIntoView({ inline: 'nearest', block: 'nearest' }); } catch (e) {} }
      }
    }
  }

  function apply(push) {
    var order = orders[sortEl.value] || orders[defaults.sort];
    var tier = tierEl.value, band = rtpEl.value, vol = volEl.value;
    var q = (qEl.value || '').toLowerCase().replace(/^\s+|\s+$/g, '');
    cards.sort(order);
    var visible = [];
    for (var i = 0; i < cards.length; i++) {
      var c = cards[i], ok = true;
      if (state.provider !== '' && c.getAttribute('data-provider') !== state.provider) ok = false;
      if (ok && tier !== 'all' && c.getAttribute('data-tier') !== tier) ok = false;
      if (ok && band !== 'all') {
        var r = num(c, 'rtp');
        if (r === null) ok = false;
        else if (band === '97' && r < 97) ok = false;
        else if (band === '96' && (r < 96 || r >= 97)) ok = false;
        else if (band === 'lt96' && r >= 96) ok = false;
      }
      if (ok && vol !== 'all' && c.getAttribute('data-vol') !== vol) ok = false;
      if (ok && bonusEl.checked && (num(c, 'bonuses') || 0) < 1) ok = false;
      if (ok && q !== '' && c.getAttribute('data-name').indexOf(q) === -1 && c.getAttribute('data-provider').indexOf(q) === -1) ok = false;
      if (ok) visible.push(c);
      grid.appendChild(c);
    }
    pager.set(visible);
    if (noneEl) noneEl.hidden = (visible.length > 0);
    if (push) writeUrl();
  }

  /* initial state from the URL */
  var init = readUrl();
  if (init.sort && orders[init.sort]) sortEl.value = init.sort;
  if (init.tier === 'single' || init.tier === 'multi') tierEl.value = init.tier;
  if (init.rtp === '97' || init.rtp === '96' || init.rtp === 'lt96') rtpEl.value = init.rtp;
  if (init.vol === 'low' || init.vol === 'med' || init.vol === 'high') volEl.value = init.vol;
  if (init.bonus === '1') bonusEl.checked = true;
  if (init.q) qEl.value = init.q;
  if (!serverFiltered && init.p) {
    state.provider = String(init.p).toLowerCase();
    setChip(state.provider);
  }

  sortEl.addEventListener('change', function () { apply(true); });
  tierEl.addEventListener('change', function () { apply(true); });
  rtpEl.addEventListener('change', function () { apply(true); });
  volEl.addEventListener('change', function () { apply(true); });
  bonusEl.addEventListener('change', function () { apply(true); });
  qEl.addEventListener('input', function () { apply(true); });

  /* chips: on the full page they filter in place; on a /slots/p/ page they stay links */
  if (chipsEl && !serverFiltered) {
    chipsEl.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('.slt-filter-chip') : null;
      if (!a) return;
      e.preventDefault();
      state.provider = a.getAttribute('data-provider') || '';
      setChip(state.provider);
      apply(true);
    });
  }

  apply(false);
})();
</script>
<?php endif; ?>

</div>
