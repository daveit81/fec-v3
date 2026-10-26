<?php
defined('_JEXEC') or die;

/**
 * Editorial Team — listing template.
 *
 * FEC V2.1 migration: markup is .tm-* family, styled by fec-edition.css
 * (loaded centrally by index.php when fec_is_fecx_team() returns true).
 *
 * Data plumbed by views/authors/view.html.php:
 *   $this->items       — augmented authors (incl. initials, avatar_hue,
 *                        stats {bonuses, casinos, latest_fmt}, recent[],
 *                        years_at_fec)
 *   $this->heroStats   — site-wide counts {specialists, bonuses,
 *                        casinos, active}
 *   $this->params      — JRegistry from active menu item; admin-editable
 *                        text fields (hero copy, principles, contact CTA)
 *
 * Helpers from fec_common_lib.php available globally (index.php loads
 * the lib at the top): fec_render_breadcrumb_html, fec_render_breadcrumb_jsonld,
 * fec_build_breadcrumb_items_from_path.
 */

$p          = $this->params;
$items      = is_array($this->items) ? $this->items : array();
$heroStats  = is_array($this->heroStats) ? $this->heroStats : array();
$bonustypeMap = is_array($this->bonustypeMap) ? $this->bonustypeMap : array();
$currencyMap  = is_array($this->currencyMap)  ? $this->currencyMap  : array();

// ---- Default content -------------------------------------------------------
// Joomla's XML <field default="..."> values are not always applied at runtime
// (they kick in only after the menu item is opened+saved in admin once, so
// the params are persisted). We mirror the XML defaults here in PHP so the
// page reads correctly even before any admin configuration. Each $p->get()
// below uses these as the fallback argument.
$defaults = array(
    'hero_eyebrow'   => 'Masthead · About the team',
    'hero_title'     => 'The analysts behind every FXCheck&trade; verdict.',
    'hero_intro'     => 'Specialists with one job: read the fine print so you don\'t have to. We sign up for bonuses, claim them, log the actual terms, and request withdrawals. Every byline on this site links back to a person whose work you can audit.',

    'principles_eyebrow'   => 'Editorial standards',
    'principles_title'     => 'How we work.',
    'principles_methodology_url' => '',

    'bylines_eyebrow' => 'Bylines',
    'bylines_title'   => 'The team.',
    'bylines_sub'     => 'Click any name to see every article and FXCheck report that person has signed.',

    // V2.7.59 — byline policy. Pen names are declared, not hidden: the
    // block states that every name belongs to one real editor and that
    // editorial responsibility sits with the publisher. Every sentence
    // here must stay literally true; edit via the menu item params.
    'bylines_policy_label' => 'Why our editors write under pen names',
    'bylines_policy_p1'    => 'The names on this page are professional pen names. Each one belongs to one real person, a member of the FreeExtraChips editorial team or a regular contributor, and to that person only. The gambling industry is small and not always friendly, and our writers keep their private identities out of it.',
    'bylines_policy_p2'    => 'The byline tells you who did the work. Editorial responsibility for everything we publish sits with FreeExtraChips as the publisher. Each profile describes what that editor actually covers and checks. Nothing in these profiles is invented. The portraits are stylised renderings of the editors themselves, not stock images or invented faces.',
    'bylines_policy_p3'    => 'If something we published is wrong, tell us: corrections are made on the page and signed, as described in our correction policy.',
    'bylines_policy_link_label' => 'How our bylines work',

    'contact_eyebrow' => 'Tips, corrections, complaints',
    'contact_title'   => 'Saw something we got wrong? Tell us.',
    'contact_body'    => 'Player reports keep FXCheck honest. So do correction emails. If a bonus paid differently than we said it would, if a casino changed terms after you claimed, or if you spotted a typo — write us. Real humans read this inbox.',
    'contact_action1_label' => 'tips@freeextrachips.com',
    'contact_action1_href'  => 'mailto:tips@freeextrachips.com',
    'contact_action2_label' => 'File a correction',
    'contact_action2_href'  => 'mailto:corrections@freeextrachips.com',
);

// Four editorial principles (numbered 01..04). Matches the bundled mockup.
$principlesDefaults = array(
    1 => array(
        'title' => 'Player-first, never paid placement.',
        'desc'  => 'Affiliate revenue funds the operation, but no operator buys a listing position, a Frank Score, or an FXCheck verdict. The order of offers on this site is the same whether or not a casino runs an active commercial agreement with us.',
    ),
    2 => array(
        'title' => 'We claim every bonus we list.',
        'desc'  => 'Every offer published goes through a sign-up, claim, and terms-verification cycle. Where possible, a specialist also plays through wagering and attempts a withdrawal — and logs what actually happens, not what the promo page promised.',
    ),
    3 => array(
        'title' => 'Player reports above marketing copy.',
        'desc'  => 'FXCheck aggregates Yes/No outcomes from real players who claimed the offer. When player data and casino claims diverge, player data wins and the listing is updated within 24 hours.',
    ),
    4 => array(
        'title' => 'Public corrections, signed names.',
        'desc'  => 'Errors get flagged on the page itself with the date, the correction, and the editor who made it. There are no silent updates. If we changed our mind, we say so.',
    ),
);

// ---- Helpers ----------------------------------------------------------------
$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$nfmt = function ($n) { return number_format((int)$n); };

// ---- Breadcrumb (auto-derived from URL path) --------------------------------
// Path-based derivation: works whether the team URL is /editorial-team,
// /about/editorial-team, or anything else SEF-friendly. Uses the same
// component-agnostic helpers that com_bonuses pages use.
$crumbHtml   = '';
$crumbJsonLd = '';
if (function_exists('fec_build_breadcrumb_items_from_path')
    && function_exists('fec_render_breadcrumb_html')) {
    $path        = JUri::getInstance()->getPath();
    $siteBase    = rtrim(JUri::base(true), '/');
    $crumbItems  = fec_build_breadcrumb_items_from_path($path, $siteBase);
    if (!empty($crumbItems)) {
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
        if (function_exists('fec_render_breadcrumb_jsonld')) {
            $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
        }
    }
}

// ---- Section toggles --------------------------------------------------------
$showPrinciples = ((int)$p->get('show_principles', 1) === 1);
$showContact    = ((int)$p->get('show_contact', 1) === 1);
$showBylinePolicy = ((int)$p->get('show_bylines_policy', 1) === 1);

// ---- Byline policy link target (V2.7.59) -----------------------------------
// Points at the "Bylines and pen names" section of the Editorial Standards
// methodology page. Built through AuthorsHelper so the route follows the
// methodology menu item, if one exists. Overridable from the menu params.
$bylinePolicyUrl = trim((string)$p->get('bylines_policy_url', ''));
if ($bylinePolicyUrl === '') {
    // AuthorsHelper is required by views/authors/view.html.php.
    $bylinePolicyUrl = AuthorsHelper::methodologyUrl('editorial-standards') . '#bylines';
}

// ---- Bonus type → human label (for the .tm-recent kind chip) ----------------
// Best-effort mapping; falls back to the raw type string in upper case.
$typeHuman = function ($t) {
    $t = strtolower(trim((string)$t));
    $map = array(
        'nd'         => 'No deposit',
        'no_deposit' => 'No deposit',
        'fs'         => 'Free spins',
        'free_spins' => 'Free spins',
        '1st'        => '1st deposit',
        'first'      => '1st deposit',
        'match'      => 'Match',
        'reload'     => 'Reload',
        'cashback'   => 'Cashback',
        'tournament' => 'Tournament',
    );
    if (isset($map[$t])) return $map[$t];
    if ($t === '') return 'Bonus';
    return ucwords(str_replace(array('_', '-'), ' ', $t));
};

// ---- Bonus type → kind class variant (matches mockup tag styles) ------------
// Three styles available: 'review' (accent), 'guide' (neutral), 'report' (verified green).
$typeKindClass = function ($t) {
    $t = strtolower(trim((string)$t));
    if ($t === 'nd' || $t === 'no_deposit')             return 'report';   // highest value -> green
    if ($t === 'fs' || $t === 'free_spins')             return 'guide';    // neutral
    if (in_array($t, array('1st','first','match','reload','cashback'), true)) return 'review';
    return 'guide';
};

// SVG snippets reused below.
$arrowUpRight = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';
$arrowRight   = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"></path></svg>';

?>

<?php echo $crumbHtml; ?>

<!-- ========================== HERO ========================== -->
<section class="tm-hero">
  <div class="wrap tm-hero-grid">
    <div>
      <div class="eyebrow"><?php echo $h($p->get('hero_eyebrow', $defaults['hero_eyebrow'])); ?></div>
      <h1 class="tm-title"><?php echo $p->get('hero_title', $defaults['hero_title']); /* allow inline <em> */ ?></h1>
      <p class="tm-intro"><?php echo $h($p->get('hero_intro', $defaults['hero_intro'])); ?></p>
    </div>
    <div class="tm-hero-stats">
      <div class="tm-hero-stat">
        <div class="num"><?php echo $nfmt(isset($heroStats['specialists']) ? $heroStats['specialists'] : 0); ?></div>
        <div class="lbl">Specialists on staff</div>
      </div>
      <div class="tm-hero-stat">
        <div class="num"><?php echo $nfmt(isset($heroStats['bonuses']) ? $heroStats['bonuses'] : 0); ?></div>
        <div class="lbl">Bonuses listed</div>
      </div>
      <div class="tm-hero-stat">
        <div class="num"><?php echo $nfmt(isset($heroStats['casinos']) ? $heroStats['casinos'] : 0); ?></div>
        <div class="lbl">Casinos reviewed</div>
      </div>
      <div class="tm-hero-stat">
        <div class="num"><?php echo $nfmt(isset($heroStats['reports']) ? $heroStats['reports'] : 0); ?></div>
        <div class="lbl">Player reports</div>
      </div>
    </div>
  </div>
</section>

<?php if ($showPrinciples): ?>
<!-- ========================== PRINCIPLES ========================== -->
<section class="tm-principles">
  <div class="wrap">
    <div class="tm-principles-head">
      <div>
        <div class="eyebrow"><?php echo $h($p->get('principles_eyebrow', $defaults['principles_eyebrow'])); ?></div>
        <h2><?php echo $h($p->get('principles_title', $defaults['principles_title'])); ?></h2>
      </div>
      <?php $methodologyUrl = trim((string)$p->get('principles_methodology_url', $defaults['principles_methodology_url'])); ?>
      <?php if ($methodologyUrl !== ''): ?>
        <a class="sec-link" href="<?php echo $h($methodologyUrl); ?>">
          Read the full methodology <?php echo $arrowRight; ?>
        </a>
      <?php endif; ?>
    </div>

    <div class="tm-principles-grid">
      <?php for ($i = 1; $i <= 4; $i++):
        $pt = trim((string)$p->get("principle{$i}_title", $principlesDefaults[$i]['title']));
        $pd = trim((string)$p->get("principle{$i}_desc",  $principlesDefaults[$i]['desc']));
        if ($pt === '' && $pd === '') continue;
      ?>
        <div class="tm-principle">
          <div class="num"><?php echo sprintf('%02d', $i); ?></div>
          <?php if ($pt !== ''): ?><h3><?php echo $h($pt); ?></h3><?php endif; ?>
          <?php if ($pd !== ''): ?><p><?php echo $h($pd); ?></p><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========================== BYLINES (team grid) ========================== -->
<section class="tm-bylines" id="team">
  <div class="wrap">
    <div class="tm-bylines-head">
      <div class="eyebrow"><?php echo $h($p->get('bylines_eyebrow', $defaults['bylines_eyebrow'])); ?></div>
      <h2><?php echo $h($p->get('bylines_title', $defaults['bylines_title'])); ?></h2>
      <p class="sub"><?php echo $h($p->get('bylines_sub', $defaults['bylines_sub'])); ?></p>
    </div>

    <?php if ($showBylinePolicy): ?>
      <!-- Byline policy (V2.7.59): pen names declared in plain words. Every
           sentence is a factual claim about the desk; keep it literally true. -->
      <?php
        $bpLabel = trim((string)$p->get('bylines_policy_label', $defaults['bylines_policy_label']));
        $bpParas = array();
        for ($i = 1; $i <= 3; $i++) {
            $bpText = trim((string)$p->get("bylines_policy_p{$i}", $defaults["bylines_policy_p{$i}"]));
            if ($bpText !== '') $bpParas[] = $bpText;
        }
        $bpLinkLabel = trim((string)$p->get('bylines_policy_link_label', $defaults['bylines_policy_link_label']));
      ?>
      <?php if (!empty($bpParas)): ?>
        <aside class="tm-byline-policy" id="byline-policy" aria-labelledby="byline-policy-label">
          <?php if ($bpLabel !== ''): ?>
            <div class="tm-byline-policy-label" id="byline-policy-label"><?php echo $h($bpLabel); ?></div>
          <?php endif; ?>
          <?php foreach ($bpParas as $bpText): ?>
            <p><?php echo $h($bpText); ?></p>
          <?php endforeach; ?>
          <?php if ($bpLinkLabel !== '' && $bylinePolicyUrl !== ''): ?>
            <a class="tm-byline-policy-link" href="<?php echo $h($bylinePolicyUrl); ?>"><?php echo $h($bpLinkLabel); ?> <?php echo $arrowRight; ?></a>
          <?php endif; ?>
        </aside>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($items)): ?>
      <p>No team members listed.</p>
    <?php else: ?>
      <div class="tm-grid">
        <?php foreach ($items as $a):
          $authorUrl = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode((string)$a['slug']));
          $photoUrl  = trim((string)$a['avatar_url']);
          $hasPhoto  = ($photoUrl !== '');
          $hue       = isset($a['avatar_hue']) ? (int)$a['avatar_hue'] : 220;
          $stats     = isset($a['stats']) && is_array($a['stats']) ? $a['stats'] : array();
          $recent    = isset($a['recent']) && is_array($a['recent']) ? $a['recent'] : array();
          $recentBlog = isset($a['recent_blog']) && is_array($a['recent_blog']) ? $a['recent_blog'] : array();
          $blogPosts = (int)(isset($stats['blog_posts']) ? $stats['blog_posts'] : 0);
          $years     = isset($a['years_at_fec']) ? (int)$a['years_at_fec'] : 0;
          $hasStats  = ((int)(isset($stats['bonuses']) ? $stats['bonuses'] : 0) > 0)
                    || ((int)(isset($stats['casinos']) ? $stats['casinos'] : 0) > 0)
                    || ($blogPosts > 0)
                    || ($years > 0);
        ?>
          <article class="tm-card">
            <!-- AVATAR: photo if avatar_url set, otherwise initials over hue circle -->
            <div class="tm-avatar<?php echo $hasPhoto ? ' tm-avatar--photo' : ''; ?>"
                 style="--avatar-hue: <?php echo $hue; ?>;">
              <?php if ($hasPhoto): ?>
                <img src="<?php echo $h($photoUrl); ?>"
                     alt="<?php echo $h($a['name']); ?>"
                     width="96" height="96" loading="lazy">
              <?php else: ?>
                <span class="head" aria-hidden="true"></span>
                <span class="initials"><?php echo $h($a['initials']); ?></span>
              <?php endif; ?>
            </div>

            <div class="body">
              <!-- Name + role -->
              <div class="tm-card-titleline">
                <h3 class="tm-card-name">
                  <a href="<?php echo $authorUrl; ?>"><?php echo $h($a['name']); ?></a>
                </h3>
                <div class="tm-card-role"><?php echo $h($a['role']); ?></div>
              </div>

              <!-- Tagline (short_bio) -->
              <?php if (trim((string)$a['short_bio']) !== ''): ?>
                <p class="tm-card-tagline"><?php echo $h($a['short_bio']); ?></p>
              <?php endif; ?>

              <!-- Full bio -->
              <?php if (trim((string)$a['bio']) !== ''): ?>
                <p class="tm-card-bio"><?php echo $h($a['bio']); ?></p>
              <?php endif; ?>

              <!-- Stats row (only renders if at least one stat > 0) -->
              <?php if ($hasStats): ?>
                <div class="tm-stats">
                  <?php if ((int)(isset($stats['bonuses']) ? $stats['bonuses'] : 0) > 0): ?>
                    <div class="stat">
                      <div class="num"><?php echo $nfmt($stats['bonuses']); ?></div>
                      <div class="lbl">Bonuses tested</div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int)(isset($stats['casinos']) ? $stats['casinos'] : 0) > 0): ?>
                    <div class="stat">
                      <div class="num"><?php echo $nfmt($stats['casinos']); ?></div>
                      <div class="lbl">Casinos covered</div>
                    </div>
                  <?php endif; ?>
                  <?php if ($blogPosts > 0): ?>
                    <div class="stat">
                      <div class="num"><?php echo $nfmt($blogPosts); ?></div>
                      <div class="lbl">Blog posts</div>
                    </div>
                  <?php endif; ?>
                  <?php if ($years > 0): ?>
                    <div class="stat">
                      <div class="num"><?php echo (int)$years; ?>+ yrs</div>
                      <div class="lbl">At FreeExtra&middot;Chips</div>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Recent work (last 3 bonuses) -->
              <?php if (!empty($recent)): ?>
                <div class="tm-recent">
                  <div class="lbl-row">Recent work</div>
                  <?php foreach ($recent as $r):
                    $bid       = (int)$r['bid'];
                    $cid       = (int)$r['cid'];
                    $brand     = trim((string)$r['brand']);
                    $type      = (string)$r['type'];
                    $brandLabel = ($brand !== '' ? $brand : 'Casino');
                    // Full bonus title using canonical lib helper (e.g.
                    // "100 Free Spins on Wolf Gold", "$25 No Deposit Bonus",
                    // "200% Match up to $500"). Country code '' = generic
                    // (non-GB) labelling. No map-empty guard: the function
                    // gracefully degrades — empty maps just produce a less
                    // pretty title (e.g. "100 fs" instead of "100 Free
                    // Spins on Wolf Gold"). Better than no title at all.
                    $bonusTitle = function_exists('fec_bonus_full_title')
                        ? trim((string)fec_bonus_full_title($r, '', $currencyMap, $bonustypeMap))
                        : '';
                    // Casino chip (round, brand initial, hue-from-name).
                    // Reuses the canonical fec_casino_chip helper so the
                    // chip styling stays in sync with other parts of the
                    // editorial system.
                    $chipHtml = function_exists('fec_casino_chip')
                        ? fec_casino_chip($brandLabel, 36)
                        : '';
                    // Canonical JRoute pattern: type+bid+cid are ALL required for
                    // the JReviews router to resolve the casino slug. Missing cid
                    // (or type) leads to malformed/duplicate URLs.
                    $bonusUrl  = JRoute::_('index.php?option=com_bonuses&view=details'
                                . '&type=' . $type
                                . '&bid='  . $bid
                                . '&cid='  . $cid);
                  ?>
                    <div class="item">
                      <?php echo $chipHtml; ?>
                      <a href="<?php echo $bonusUrl; ?>" class="item-link">
                        <span class="item-brand"><?php echo $h($brandLabel); ?></span>
                        <?php if ($bonusTitle !== ''): ?>
                          <span class="item-title"><?php echo $h($bonusTitle); ?></span>
                        <?php endif; ?>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Recent posts (V2.7.65) — Casino Bonus Blog, shown when the
                   author has no recent bonuses to list (contributors) -->
              <?php if (empty($recent) && !empty($recentBlog)): ?>
                <div class="tm-recent">
                  <div class="lbl-row">Recent posts</div>
                  <?php foreach ($recentBlog as $bp):
                    $bpTitle = isset($bp['title']) ? trim((string)$bp['title']) : '';
                    if ($bpTitle === '') continue;
                    $bpUrl   = AuthorsHelper::blogPostUrl($bp['id'], $bp['alias'], isset($bp['catid']) ? $bp['catid'] : 0);
                    $bpLabel = AuthorsHelper::blogPostLabel($bp);
                    $bpDate  = AuthorsHelper::blogDate(isset($bp['created']) ? $bp['created'] : '');
                    $bpThumb = AuthorsHelper::blogPostThumb($bp, 72, 72);
                  ?>
                    <div class="item">
                      <span class="blog-chip" aria-hidden="true"><?php echo $bpThumb; ?></span>
                      <a href="<?php echo $h($bpUrl); ?>" class="item-link">
                        <span class="item-brand"><?php echo $h($bpTitle); ?></span>
                        <span class="item-title"><?php echo $h($bpLabel . ($bpDate !== '' ? ' · ' . $bpDate : '')); ?></span>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Footer links -->
              <div class="tm-card-foot">
                <a href="<?php echo $authorUrl; ?>">All articles <?php echo $arrowUpRight; ?></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($showContact): ?>
<!-- ========================== CONTACT CTA ========================== -->
<section class="tm-contact">
  <div class="wrap">
    <div class="tm-contact-inner">
      <div>
        <div class="eyebrow"><?php echo $h($p->get('contact_eyebrow', $defaults['contact_eyebrow'])); ?></div>
        <h2><?php echo $h($p->get('contact_title', $defaults['contact_title'])); ?></h2>
        <p><?php echo $h($p->get('contact_body', $defaults['contact_body'])); ?></p>
      </div>
      <div class="tm-contact-actions">
        <?php
          $a1l = trim((string)$p->get('contact_action1_label', $defaults['contact_action1_label']));
          $a1h = trim((string)$p->get('contact_action1_href',  $defaults['contact_action1_href']));
          $a2l = trim((string)$p->get('contact_action2_label', $defaults['contact_action2_label']));
          $a2h = trim((string)$p->get('contact_action2_href',  $defaults['contact_action2_href']));
        ?>
        <?php if ($a1l !== '' && $a1h !== ''): ?>
          <a class="btn btn-primary" href="<?php echo $h($a1h); ?>"><?php echo $h($a1l); ?></a>
        <?php endif; ?>
        <?php if ($a2l !== '' && $a2h !== ''): ?>
          <a class="btn btn-ghost" href="<?php echo $h($a2h); ?>"><?php echo $h($a2l); ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php echo $crumbJsonLd; ?>

<?php
/* ============================================================================
 * JSON-LD: Editorial Team @graph
 *
 * Emits Schema.org Person × N (one per active author) plus a canonical
 * Organization node and an AboutPage container. This is the primary E-E-A-T
 * signal Google looks at for YMYL content (gambling): identifiable editors
 * with jobTitle, image, description, and a dedicated bio page (url).
 *
 * Graph shape:
 *   Organization (@id = "<site>/#organization") — referenced by every Person
 *     and the AboutPage as publisher.
 *   WebSite (@id = "<site>/#website") — anchored by AboutPage.isPartOf;
 *     publisher → Organization. Keeps the cross-page graph (this page +
 *     /authors/<slug>) consistent.
 *   AboutPage (@id = "<page>#aboutpage") — wraps the team page, mainEntity
 *     ItemList referencing each Person @id (so the list is parseable).
 *   Person × N — one per author; @id = "<authorUrl>#person".
 *
 * Person @id intentionally matches the canonical the future
 * com_authors view=author template will emit on the single-author page,
 * so the graph stays consistent across both pages.
 * ========================================================================== */
$siteRoot       = rtrim(JUri::root(), '/');                // e.g. https://www.freeextrachips.com
$teamPageAbsUrl = $siteRoot . JUri::getInstance()->getPath();
$orgId          = $siteRoot . '/#organization';
$webSiteId      = $siteRoot . '/#website';

// Absoluteify helper: leaves http(s):// URLs alone, prefixes site root to relative paths.
$abs = function ($u) use ($siteRoot) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    return $siteRoot . '/' . ltrim($u, '/');
};

$graph = array();

// --- Organization (anchor referenced by every Person + AboutPage) ----------
// publishingPrinciples (V2.7.59): the Editorial Standards page, which now
// carries the byline / pen-name policy. The Organization @id is shared by
// every com_authors page, so stating it here propagates to the whole graph.
$orgNode = array(
    '@type' => 'Organization',
    '@id'   => $orgId,
    'name'  => 'FreeExtraChips',
    'url'   => $siteRoot . '/',
);
$standardsAbsUrl = $abs(AuthorsHelper::methodologyUrl('editorial-standards'));
if ($standardsAbsUrl !== '') $orgNode['publishingPrinciples'] = $standardsAbsUrl;
$graph[] = $orgNode;

// --- WebSite (idiomatic anchor for AboutPage.isPartOf, and consistent with
//     the single-author /authors/<slug> page which links its ProfilePage
//     to the same WebSite node). publisher → Organization keeps the chain
//     Organization ← WebSite ← AboutPage navigable for crawlers.
$graph[] = array(
    '@type'     => 'WebSite',
    '@id'       => $webSiteId,
    'url'       => $siteRoot . '/',
    'name'      => 'FreeExtraChips',
    'publisher' => array('@id' => $orgId),
);

// --- AboutPage ---------------------------------------------------------------
$personRefs = array();
foreach ($items as $idx => $a) {
    $slug         = isset($a['slug']) ? (string)$a['slug'] : '';
    if ($slug === '') continue;
    $authorRel    = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($slug));
    $authorAbsUrl = $abs($authorRel);
    $personRefs[] = array(
        '@type'    => 'ListItem',
        'position' => $idx + 1,
        'item'     => array('@id' => $authorAbsUrl . '#person'),
    );
}

$aboutPage = array(
    '@type'     => 'AboutPage',
    '@id'       => $teamPageAbsUrl . '#aboutpage',
    'name'      => trim((string)$p->get('page_title', 'Editorial Team — FreeExtraChips')),
    'url'       => $teamPageAbsUrl,
    'isPartOf'  => array('@id' => $webSiteId),
    'publisher' => array('@id' => $orgId),
);
$metaDesc = trim((string)$p->get('meta_description', ''));
if ($metaDesc !== '') $aboutPage['description'] = $metaDesc;
if (!empty($personRefs)) {
    $aboutPage['mainEntity'] = array(
        '@type'           => 'ItemList',
        'itemListElement' => $personRefs,
    );
}
$graph[] = $aboutPage;

// --- Person × N --------------------------------------------------------------
foreach ($items as $a) {
    $slug = isset($a['slug']) ? (string)$a['slug'] : '';
    $name = isset($a['name']) ? trim((string)$a['name']) : '';
    if ($slug === '' || $name === '') continue;

    $authorRel    = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($slug));
    $authorAbsUrl = $abs($authorRel);

    $person = array(
        '@type'    => 'Person',
        '@id'      => $authorAbsUrl . '#person',
        'name'     => $name,
        'url'      => $authorAbsUrl,
        'worksFor' => array('@id' => $orgId),
    );

    $role = isset($a['role']) ? trim((string)$a['role']) : '';
    if ($role !== '') $person['jobTitle'] = $role;

    // Description: prefer short_bio (tagline) over bio (full paragraph).
    // Google prefers concise descriptions for Person.
    $shortBio = isset($a['short_bio']) ? trim((string)$a['short_bio']) : '';
    $bio      = isset($a['bio'])       ? trim((string)$a['bio'])       : '';
    if      ($shortBio !== '') $person['description'] = $shortBio;
    elseif  ($bio      !== '') $person['description'] = $bio;

    $avatar = isset($a['avatar_url']) ? trim((string)$a['avatar_url']) : '';
    if ($avatar !== '') $person['image'] = $abs($avatar);

    $graph[] = $person;
}

// Single JSON-LD script (PHP 5.4+ flags both available on PHP 5.6 host).
$jsonLdDoc = array(
    '@context' => 'https://schema.org',
    '@graph'   => $graph,
);
$jsonLdOut = json_encode($jsonLdDoc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($jsonLdOut !== false) {
    echo "\n" . '<script type="application/ld+json">' . $jsonLdOut . '</script>' . "\n";
}
?>