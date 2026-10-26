<?php
defined('_JEXEC') or die;

/**
 * Single Author profile template — au-* design system.
 *
 * FEC V2.1 migration: markup is .au-* family, styled by fec-edition.css
 * (loaded centrally by index.php when fec_is_fecx_author() returns true).
 *
 * Data plumbed by views/author/view.html.php:
 *   $this->item          augmented author (initials, avatar_hue, expertise[],
 *                        credentials[], languages[], joined_year, years_at_fec)
 *   $this->counts        {bonuses, bonuses_active, casinos, fxchecks, guides, latest_ts}
 *   $this->activityAvg   "3.2 / day" string for sidebar Quick Facts
 *   $this->bonusItems    paginated rich rows (bid, cid, brand, type, bonus,
 *                        max_bonus, currency, game, fp_time*, wr, wr_meth, code,
 *                        status, added, fx_yes, fx_no)
 *   $this->bonusTotal    total count for active filter scope
 *   $this->activeOnly    0|1 — drives the All / Active-only sub-chips
 *   $this->tab           'bonuses'|'casinos'|'fxchecks'|'guides'|'rtp-verifications'|'blog'
 *   $this->blogItems     V2.7.65 — Casino Bonus Blog rows for the 'blog' tab
 *   $this->blogTotal     total posts (pagination), $this->blogPagination
 *   $this->pagination    JPagination instance
 *   $this->bonustypeMap  type code → human label
 *   $this->currencyMap   currency code → symbol
 *   $this->jsonLdGraph   pre-encoded JSON-LD @graph string
 *
 * Skipped per V1 product decisions: location, socials block (privacy).
 */

$a            = $this->item;
$counts       = is_array($this->counts) ? $this->counts : array();
$tab          = $this->tab;
$activeOnly   = (int)$this->activeOnly;
$bonusItems   = is_array($this->bonusItems) ? $this->bonusItems : array();
$bonustypeMap = is_array($this->bonustypeMap) ? $this->bonustypeMap : array();
$currencyMap  = is_array($this->currencyMap)  ? $this->currencyMap  : array();

// ---- Local helpers ---------------------------------------------------------
$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$nfmt = function ($n) { return number_format((int)$n); };

// ---- Breadcrumb (auto-derived from URL path) --------------------------------
$crumbHtml   = '';
$crumbJsonLd = '';
if (function_exists('fec_build_breadcrumb_items_from_path')
    && function_exists('fec_render_breadcrumb_html')) {
    $path       = JUri::getInstance()->getPath();
    $siteBase   = rtrim(JUri::base(true), '/');
    $crumbItems = fec_build_breadcrumb_items_from_path($path, $siteBase);
    if (!empty($crumbItems)) {
        // Override last crumb label with author display name, otherwise the
        // helper title-cases the URL slug and loses characters like the
        // apostrophe in "Sabrina O'Connell" (slug: sabrina-oconnell). Defensive:
        // overwrite whichever common label key the helper uses.
        $lastIdx = count($crumbItems) - 1;
        if (isset($crumbItems[$lastIdx]) && is_array($crumbItems[$lastIdx])) {
            foreach (array('name', 'label', 'title', 'text') as $labelKey) {
                if (array_key_exists($labelKey, $crumbItems[$lastIdx])) {
                    $crumbItems[$lastIdx][$labelKey] = $a['name'];
                }
            }
        }
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
        if (function_exists('fec_render_breadcrumb_jsonld')) {
            $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
        }
    }
}

// ---- Author URL helpers (for tab + sub-filter switching) -------------------
$slugEnc    = urlencode((string)$a['slug']);
$baseAuthor = JRoute::_('index.php?option=com_authors&view=author&slug=' . $slugEnc);
$teamUrl    = JRoute::_('index.php?option=com_authors&view=authors');

// Byline policy anchor on the Editorial Standards page (V2.7.59).
// AuthorsHelper is required by views/author/view.html.php.
$bylinePolicyUrl = AuthorsHelper::methodologyUrl('editorial-standards') . '#bylines';

// Build the URL for a given tab, preserving activeOnly only on bonuses tab
$tabUrl = function ($tabSlug, $withActive = null) use ($baseAuthor, $activeOnly) {
    $params = array();
    if ($tabSlug !== 'bonuses') $params[] = 'tab=' . urlencode($tabSlug);
    $applyActive = ($withActive === null) ? $activeOnly : (int)$withActive;
    if ($tabSlug === 'bonuses' && $applyActive === 1) $params[] = 'active=1';
    $qs = !empty($params) ? '?' . implode('&', $params) : '';
    return $baseAuthor . $qs;
};

// ---- Latest activity → friendly string ("Today" / "2 days ago" / date) -----
$latestTs   = isset($counts['latest_ts']) ? (int)$counts['latest_ts'] : 0;
$latestFmt  = '—';
if ($latestTs > 0) {
    $diffSec = time() - $latestTs;
    if      ($diffSec < 86400)        $latestFmt = 'Today';
    elseif  ($diffSec < 86400 * 2)    $latestFmt = 'Yesterday';
    elseif  ($diffSec < 86400 * 30)   $latestFmt = floor($diffSec / 86400) . ' days ago';
    else                              $latestFmt = date('M Y', $latestTs);
}

// ---- Role parsing: "Title · Subtitle" --------------------------------------
$role     = trim((string)$a['role']);
$roleParts = preg_split('/\s*[·•]\s*/u', $role);
$roleTitle    = isset($roleParts[0]) ? trim((string)$roleParts[0]) : $role;
$roleSubtitle = (count($roleParts) > 1) ? trim((string)$roleParts[1]) : '';

// ---- Contributor profiles (V2.7.65) -----------------------------------------
// author_type 'contributor' = outside writer with a pen name (Casino Bonus
// Blog). Same page, three differences: the hero eyebrow and pen-name line
// say so, the stats strip leads with the blog, and tabs with nothing in
// them are hidden (an editor's zero tabs stay visible, as before).
$isContributor = (isset($a['author_type']) && (string)$a['author_type'] === 'contributor');
$blogCount     = isset($counts['blog_posts']) ? (int)$counts['blog_posts'] : 0;
$eyebrow       = $isContributor ? 'Contributing writer' : 'Editorial staff';
$penWho        = $isContributor ? 'one real writer behind it' : 'one real editor behind it';
$blogIndexUrl  = AuthorsHelper::blogIndexUrl();

// ---- Avatar: photo if avatar_url is set, else initials over hue gradient ---
$photoUrl = trim((string)$a['avatar_url']);
$hasPhoto = ($photoUrl !== '');
$hue      = isset($a['avatar_hue']) ? (int)$a['avatar_hue'] : 220;
$initials = isset($a['initials']) ? (string)$a['initials'] : '';

// SVG snippets reused below
$arrowUpRight = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';
$arrowLeft    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>';

?>

<?php echo $crumbHtml; ?>

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="au-hero-card">
      <!-- Avatar -->
      <div class="au-avatar<?php echo $hasPhoto ? ' au-avatar--photo' : ''; ?>"
           style="--avatar-hue: <?php echo $hue; ?>;">
        <?php if ($hasPhoto): ?>
          <img src="<?php echo $h($photoUrl); ?>"
               alt="<?php echo $h($a['name']); ?>"
               width="120" height="120" loading="lazy">
        <?php else: ?>
          <span class="head" aria-hidden="true"></span>
          <span class="initials"><?php echo $h($initials); ?></span>
        <?php endif; ?>
      </div>

      <!-- Hero body -->
      <div class="au-hero-body">
        <div class="eyebrow"><?php echo $h($eyebrow); ?></div>
        <h1 class="au-name"><?php echo $h($a['name']); ?></h1>
        <?php if ($role !== ''): ?>
          <div class="au-role">
            <span><?php echo $h($roleTitle); ?></span>
            <?php if ($roleSubtitle !== ''): ?>
              <span class="div">&middot;</span><span class="sub"><?php echo $h($roleSubtitle); ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (trim((string)$a['short_bio']) !== ''): ?>
          <p class="au-tagline"><?php echo $h($a['short_bio']); ?></p>
        <?php endif; ?>

        <?php $langs = isset($a['languages']) && is_array($a['languages']) ? $a['languages'] : array(); ?>
        <?php if (!empty($langs)): ?>
          <div class="au-meta-row">
            <span>Languages: <b><?php echo $h(implode(' · ', $langs)); ?></b></span>
          </div>
        <?php endif; ?>

        <!-- Pen-name line (V2.7.59): one real editor behind the byline.
             Same .au-meta-row pattern as the Languages line above. -->
        <div class="au-meta-row au-pen-name">
          <span>Professional pen name &middot; <?php echo $h($penWho); ?> &middot;
            <a href="<?php echo $h($bylinePolicyUrl); ?>">How our bylines work</a></span>
        </div>
      </div>

      <!-- Hero actions -->
      <div class="au-hero-actions">
        <a class="btn btn-ghost" href="<?php echo $teamUrl; ?>"><?php echo $arrowLeft; ?> Meet the team</a>
      </div>
    </div>

    <!-- Stats strip. Editors: the four verification counters + years, as
         before. Contributors (V2.7.65): blog posts first, then only the
         counters that are non-zero, then years. -->
    <?php
      $yrs   = (int)$a['years_at_fec'];
      $tiles = array(
        array('key' => 'blog_posts',        'lbl' => 'Blog posts'),
        array('key' => 'bonuses',           'lbl' => 'Bonuses reviewed'),
        array('key' => 'casinos',           'lbl' => 'Casinos covered'),
        array('key' => 'rtp_verifications', 'lbl' => 'RTP verifications'),
        array('key' => 'fxchecks',          'lbl' => 'FXCheck reports'),
        array('key' => 'guides',            'lbl' => 'Guides'),
      );
      $shown = array();
      foreach ($tiles as $t) {
        $v = isset($counts[$t['key']]) ? (int)$counts[$t['key']] : 0;
        $core = in_array($t['key'], array('bonuses', 'casinos', 'rtp_verifications', 'fxchecks'), true);
        if ($isContributor ? ($v > 0) : ($core || $v > 0)) $shown[] = array('num' => $nfmt($v), 'lbl' => $t['lbl']);
      }
      $shown[] = array('num' => $yrs > 0 ? $yrs . '+ yrs' : '—', 'lbl' => 'At FreeExtra&middot;Chips');
    ?>
    <div class="au-stats-strip au-stats-strip--<?php echo count($shown); ?>">
      <?php foreach ($shown as $t): ?>
        <div class="stat">
          <div class="num"><?php echo $t['num']; ?></div>
          <div class="lbl"><?php echo $t['lbl']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ========================== MAIN (content + sidebar) ========================== -->
<section class="au-main wrap">

  <!-- ============ LEFT: CONTENT ============ -->
  <div>
    <!-- Bio card -->
    <?php if (trim((string)$a['bio']) !== ''): ?>
      <div class="au-bio-card">
        <h3>Bio</h3>
        <p><?php echo $h($a['bio']); ?></p>
      </div>
    <?php endif; ?>

    <!-- Credentials & policies -->
    <?php $creds = isset($a['credentials']) && is_array($a['credentials']) ? $a['credentials'] : array(); ?>
    <?php if (!empty($creds)): ?>
      <div class="au-creds">
        <div class="heading">
          <span>Credentials &amp; policies</span>
          <?php if ((int)(isset($counts['bonuses']) ? $counts['bonuses'] : 0) > 0 || (int)(isset($counts['fxchecks']) ? $counts['fxchecks'] : 0) > 0): ?>
            <span class="verified">FXCheck&trade; verified</span>
          <?php else: ?>
            <span class="verified">Signed byline</span>
          <?php endif; ?>
        </div>
        <ul>
          <?php foreach ($creds as $c):
            $ct = isset($c['t']) ? trim((string)$c['t']) : '';
            $cd = isset($c['d']) ? trim((string)$c['d']) : '';
            if ($ct === '' && $cd === '') continue;
          ?>
            <li>
              <?php if ($ct !== ''): ?><span class="t"><?php echo $h($ct); ?></span><?php endif; ?>
              <?php if ($cd !== ''): ?><span class="d"><?php echo $h($cd); ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Activity tabs. The "Blog posts" tab (V2.7.65) appears when the
         author has posts; for contributors it comes first and the empty
         tabs are hidden. The active tab is always rendered. -->
    <?php
      $tabDefs = array(
        array('slug' => 'bonuses',           'label' => 'Bonuses reviewed',     'key' => 'bonuses'),
        array('slug' => 'casinos',           'label' => 'Casino reviews',       'key' => 'casinos'),
        array('slug' => 'rtp-verifications', 'label' => 'RTP verifications',    'key' => 'rtp_verifications'),
        array('slug' => 'fxchecks',          'label' => 'FXCheck&trade; reports', 'key' => 'fxchecks'),
        array('slug' => 'guides',            'label' => 'Guides',               'key' => 'guides'),
      );
      $blogTab = array('slug' => 'blog', 'label' => 'Blog posts', 'key' => 'blog_posts');
      if ($isContributor) array_unshift($tabDefs, $blogTab); else $tabDefs[] = $blogTab;
    ?>
    <div class="au-tabs">
      <?php foreach ($tabDefs as $td):
        $n = isset($counts[$td['key']]) ? (int)$counts[$td['key']] : 0;
        $isActiveTab = ($tab === $td['slug']);
        if (!$isActiveTab) {
          if ($td['slug'] === 'blog' && $n === 0) continue;        // no posts: no tab
          if ($isContributor && $n === 0) continue;                // contributor: empty tabs hidden
        }
      ?>
        <a class="tab<?php echo $isActiveTab ? ' active' : ''; ?>" href="<?php echo $tabUrl($td['slug']); ?>">
          <?php echo $td['label']; ?> <span class="count"><?php echo $nfmt($n); ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'bonuses'): ?>
      <!-- Bonuses tab toolbar (sub-chips + count) -->
      <div class="au-tab-toolbar">
        <div class="sub-chips">
          <a class="chip<?php echo $activeOnly === 0 ? ' active' : ''; ?>" href="<?php echo $tabUrl('bonuses', 0); ?>">All</a>
          <a class="chip<?php echo $activeOnly === 1 ? ' active' : ''; ?>" href="<?php echo $tabUrl('bonuses', 1); ?>">Active only</a>
        </div>
        <span class="total">
          <?php echo $nfmt($this->bonusTotal); ?> total &middot; sorted by newest
        </span>
      </div>

      <?php if (empty($bonusItems)): ?>
        <div class="au-list" style="padding: 48px; text-align: center;">
          <p style="color: var(--ink-3); margin: 0;">No bonuses match this filter.</p>
        </div>
      <?php else: ?>
        <div class="au-list">
          <?php foreach ($bonusItems as $b):
            $bid       = (int)$b['bid'];
            $cid       = (int)$b['cid'];
            $type      = (string)$b['type'];
            $brand     = trim((string)$b['brand']);
            $brandShow = $brand !== '' ? $brand : 'Casino';
            $code      = trim((string)$b['code']);
            $status    = trim((string)$b['status']);
            $added     = trim((string)$b['added']);
            $fxYes     = (int)$b['fx_yes'];
            $fxNo      = (int)$b['fx_no'];

            // Composed labels via canonical lib helpers
            $sub      = function_exists('fec_bonus_full_title')
                          ? trim((string)fec_bonus_full_title($b, '', $currencyMap, $bonustypeMap))
                          : '';
            $amount   = function_exists('fec_amount_primary')
                          ? trim((string)fec_amount_primary($b, $currencyMap))
                          : '';
            $wager    = function_exists('fec_vm_wager_label')
                          ? trim((string)fec_vm_wager_label($b, $type, isset($b['wr_meth']) ? $b['wr_meth'] : ''))
                          : '';

            $chip = function_exists('fec_casino_chip') ? fec_casino_chip($brandShow, 36) : '';

            // Canonical JRoute pattern (type + bid + cid all required)
            $bonusUrl = JRoute::_('index.php?option=com_bonuses&view=details'
                                . '&type=' . $type
                                . '&bid='  . $bid
                                . '&cid='  . $cid);

            $isActive = ($status === 'active');
            $rowCls   = 'au-row' . (!$isActive ? ' expired' : '');
          ?>
            <a class="<?php echo $rowCls; ?>" href="<?php echo $bonusUrl; ?>">
              <?php echo $chip; ?>
              <div class="main">
                <div class="top">
                  <span class="casino"><?php echo $h($brandShow); ?></span>
                  <span class="id">#<?php echo $bid; ?></span>
                </div>
                <?php if ($sub !== ''): ?>
                  <div class="sub"><?php echo $h($sub); ?></div>
                <?php endif; ?>
                <div class="meta-row">
                  <?php if ($code !== ''): ?>
                    <span>Code: <b><?php echo $h($code); ?></b></span>
                  <?php endif; ?>
                  <?php if ($added !== ''): ?>
                    <span>Added: <?php echo $h($added); ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($amount !== ''): ?>
                <span class="amount"><?php echo $h($amount); ?></span>
              <?php endif; ?>
              <?php if ($wager !== ''): ?>
                <span class="wager"><?php echo $h($wager); ?></span>
              <?php endif; ?>
              <?php if (($fxYes + $fxNo) > 0): ?>
                <span class="fx">FX <b class="yes"><?php echo $fxYes; ?></b>/<b class="no"><?php echo $fxNo; ?></b></span>
              <?php endif; ?>
              <span class="status">
                <?php if ($isActive): ?>
                  <span class="tag tag-active">&bull; active</span>
                <?php else: ?>
                  <span class="tag" style="background: var(--bg-2); color: var(--ink-4);">&bull; <?php echo $h($status); ?></span>
                <?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if ($this->pagination && $this->bonusTotal > count($bonusItems)): ?>
          <div class="au-bonuses__pagination" style="margin-top: 24px;">
            <?php echo $this->pagination->getPagesLinks(); ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php elseif ($tab === 'rtp-verifications'): ?>
      <!-- RTP verifications tab — slots verified by this author -->
      <?php $verifiedSlots = isset($this->verifiedSlots) && is_array($this->verifiedSlots) ? $this->verifiedSlots : array(); ?>
      <?php $rtpCount = isset($counts['rtp_verifications']) ? (int)$counts['rtp_verifications'] : 0; ?>

      <div class="au-tab-toolbar">
        <div class="au-tab-intro">
          <p>RTP data verified by <?php echo $h($a['name']); ?> against provider primary sources. Each entry links to the full record with source URL, archive snapshot, and any per-casino paytable observations.</p>
        </div>
        <span class="total">
          <?php echo $nfmt($rtpCount); ?> verified
        </span>
      </div>

      <?php if (empty($verifiedSlots)): ?>
        <div class="au-list" style="padding: 48px; text-align: center;">
          <p style="color: var(--ink-3); margin: 0;"><?php echo $h($a['name']); ?> is currently building their verification log. Check back soon.</p>
        </div>
      <?php else: ?>
        <div class="au-rtp-list">
          <?php foreach ($verifiedSlots as $vs):
            $slug         = isset($vs['slug']) ? (string)$vs['slug'] : '';
            $gameName     = isset($vs['game_name']) ? (string)$vs['game_name'] : '';
            $provider     = isset($vs['provider_name']) ? (string)$vs['provider_name'] : '';
            $rtpMax       = isset($vs['rtp_theoretical_max']) ? $vs['rtp_theoretical_max'] : null;
            $rtpMin       = isset($vs['rtp_theoretical_min']) ? $vs['rtp_theoretical_min'] : null;
            $lastVerified = isset($vs['last_verified_date']) ? (string)$vs['last_verified_date'] : '';

            if ($slug === '' || $gameName === '') continue;

            // RTP label — show range if multi-tier, else single value
            $rtpLabel = '';
            if ($rtpMax !== null) {
                $rtpLabel = number_format((float)$rtpMax, 2) . '%';
                if ($rtpMin !== null && (float)$rtpMin < (float)$rtpMax) {
                    $rtpLabel .= ' / ' . number_format((float)$rtpMin, 2) . '%';
                }
            }

            // "Verified X days ago" — relative time
            $verifiedLabel = '';
            if ($lastVerified !== '' && $lastVerified !== '0000-00-00') {
                $ts = strtotime($lastVerified);
                if ($ts) {
                    $diffDays = floor((time() - $ts) / 86400);
                    if      ($diffDays <= 0)   $verifiedLabel = 'today';
                    elseif  ($diffDays === 1.0) $verifiedLabel = 'yesterday';
                    elseif  ($diffDays < 30)   $verifiedLabel = (int)$diffDays . ' days ago';
                    elseif  ($diffDays < 365)  $verifiedLabel = round($diffDays / 30) . ' mo ago';
                    else                       $verifiedLabel = round($diffDays / 365) . ' yr ago';
                }
            }

            $slotUrl = JRoute::_('index.php?option=com_authors&view=slot&slug=' . urlencode($slug));
          ?>
            <a class="au-rtp-row" href="<?php echo $h($slotUrl); ?>">
              <div class="main">
                <div class="top">
                  <span class="name"><?php echo $h($gameName); ?></span>
                  <?php if ($provider !== ''): ?><span class="provider"><?php echo $h($provider); ?></span><?php endif; ?>
                </div>
                <?php if ($verifiedLabel !== ''): ?>
                  <div class="meta-row">
                    <span>Verified <?php echo $h($verifiedLabel); ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <?php if ($rtpLabel !== ''): ?>
                <span class="rtp"><?php echo $h($rtpLabel); ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($tab === 'blog'): ?>
      <!-- Blog posts tab (V2.7.65) — Casino Bonus Blog posts by this author -->
      <?php
        $blogItems = isset($this->blogItems) && is_array($this->blogItems) ? $this->blogItems : array();
        $blogTotal = (int)$this->blogTotal;
      ?>
      <div class="au-tab-toolbar">
        <div class="au-tab-intro">
          <p>Posts <?php echo $h($a['name']); ?> signed on the <a href="<?php echo $h($blogIndexUrl); ?>">Casino Bonus Blog</a>: slot releases, jackpot wins, live-casino tables and industry news, newest first.</p>
        </div>
        <span class="total">
          <?php echo $nfmt($blogTotal); ?> posts
        </span>
      </div>

      <?php if (empty($blogItems)): ?>
        <div class="au-list" style="padding: 48px; text-align: center;">
          <p style="color: var(--ink-3); margin: 0;">No posts yet.</p>
        </div>
      <?php else: ?>
        <div class="au-rtp-list au-blog-list">
          <?php foreach ($blogItems as $bp):
            $bpTitle = isset($bp['title']) ? trim((string)$bp['title']) : '';
            if ($bpTitle === '') continue;
            $bpUrl   = AuthorsHelper::blogPostUrl($bp['id'], $bp['alias'], isset($bp['catid']) ? $bp['catid'] : 0);
            $bpLabel = AuthorsHelper::blogPostLabel($bp);
            $bpDate  = AuthorsHelper::blogDate(isset($bp['created']) ? $bp['created'] : '');
            $bpThumb = AuthorsHelper::blogPostThumb($bp, 112, 72);
          ?>
            <a class="au-rtp-row au-blog-row" href="<?php echo $h($bpUrl); ?>">
              <span class="thumb<?php echo $bpThumb === '' ? ' thumb--empty' : ''; ?>" aria-hidden="true"><?php echo $bpThumb; ?></span>
              <div class="main">
                <div class="top">
                  <span class="name"><?php echo $h($bpTitle); ?></span>
                  <?php if ($bpLabel !== ''): ?><span class="provider"><?php echo $h($bpLabel); ?></span><?php endif; ?>
                </div>
                <?php if ($bpDate !== ''): ?>
                  <div class="meta-row">
                    <span><?php echo $h($bpDate); ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if ($this->blogPagination && $blogTotal > count($blogItems)): ?>
          <div class="au-bonuses__pagination" style="margin-top: 24px;">
            <?php echo $this->blogPagination->getPagesLinks(); ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php else: ?>
      <!-- Casinos / FXCheck / Guides tabs — count only; full lists are
           a future migration item (see todos in conversation log). -->
      <div class="au-list" style="padding: 48px; text-align: center;">
        <div class="kicker" style="font-family: var(--mono); font-size: 10px; letter-spacing: 0.1em; color: var(--ink-3); margin-bottom: 8px; text-transform: uppercase;">
          <?php
            $tabLabels = array(
              'casinos'  => 'Casino reviews',
              'fxchecks' => 'FXCheck reports',
              'guides'   => 'Guides written',
            );
            echo $h(isset($tabLabels[$tab]) ? $tabLabels[$tab] : $tab);
          ?>
        </div>
        <p style="color: var(--ink-3); margin: 0;">
          <?php
            $tabCount = isset($counts[$tab]) ? (int)$counts[$tab] : 0;
            if ($tabCount > 0) {
                echo $nfmt($tabCount) . ' entries — detailed listing for this tab is on the roadmap.';
            } else {
                echo 'No entries yet.';
            }
          ?>
        </p>
      </div>
    <?php endif; ?>
  </div>

  <!-- ============ RIGHT: SIDEBAR ============ -->
  <aside class="au-sidebar">
    <!-- Expertise -->
    <?php $exp = isset($a['expertise']) && is_array($a['expertise']) ? $a['expertise'] : array(); ?>
    <?php if (!empty($exp)): ?>
      <div class="au-side-card">
        <h4>Expertise</h4>
        <div class="au-expertise">
          <?php foreach ($exp as $tag):
            $tag = trim((string)$tag);
            if ($tag === '') continue;
          ?>
            <span class="item"><?php echo $h($tag); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Quick facts -->
    <div class="au-side-card">
      <h4>Quick facts</h4>
      <?php if ((int)$a['joined_year'] > 0): ?>
        <div class="row"><span class="k">Member since</span><span class="v"><?php echo (int)$a['joined_year']; ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="k">Latest activity</span><span class="v"><?php echo $h($latestFmt); ?></span></div>
      <div class="row"><span class="k">Avg. update</span><span class="v"><?php echo $h($this->activityAvg); ?></span></div>
      <?php if ($role !== ''): ?>
        <div class="row"><span class="k">Role</span><span class="v"><?php echo $h($roleTitle); ?></span></div>
      <?php endif; ?>
      <?php if ($blogCount > 0): ?>
        <div class="row"><span class="k">Writes for</span><span class="v"><a href="<?php echo $h($blogIndexUrl); ?>">Casino Bonus Blog</a></span></div>
      <?php endif; ?>
    </div>

    <!-- Correction CTA -->
    <div class="au-side-cta">
      <h4>Spot something we got wrong?</h4>
      <p>Corrections from real players are how FXCheck&trade; stays honest. Drop the editorial team a line.</p>
      <a class="btn" href="mailto:corrections@freeextrachips.com">File a correction <?php echo $arrowUpRight; ?></a>
    </div>
  </aside>
</section>

<?php echo $crumbJsonLd; ?>

<?php if (!empty($this->jsonLdGraph)): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>