<?php
/**
 * FEC v2.1  --  Casino directory listing page (catid=8)  --  PRODUCTION
 *
 * Standalone Joomla-bootstrapped page rendering the full casino directory
 * via fec_render_casino_listing_grid(). Deploy at webroot, then link from
 * a menu item (External URL) or .htaccess rewrite.
 *
 * REVERTIBILITY
 *   This file is fully isolated. To revert: rm fec_listing_v2.php.
 *   Nothing else is touched (no template overrides, no DB changes, no
 *   modifications to fec_common_lib.php or fec-edition.css).
 *
 * DEPLOY CHECKLIST
 *   1. Upload this file to: /home/freeextr/public_html/fec_listing_v2.php
 *   2. Upload JS to:        /home/freeextr/public_html/js/fec_listing.js
 *   3. CSS (one of):
 *      a) Append SPRINT3_listing_full.css to /css/fec-edition.css,
 *         then open: /fec_listing_v2.php  (zero query params)
 *      b) Skip the CSS deploy for now and use the interim toggle:
 *         /fec_listing_v2.php?bundle=1  (inlines SPRINT3 bundle)
 *
 * URL PARAMS
 *   ?bundle=1     Inline /SPRINT3_listing_full.css interim - lets you test
 *                 before appending to fec-edition.css of prod.
 *   ?page=N       Pagination page (1, 2, 3, ...). Standard hub-bonuses
 *                 semantics. Page 1 = no ?page param (default).
 *   ?view=row|grid  Initial layout (default: row). Toggle persists in URL
 *                 once user clicks the bar.
 *   ?sort=frank|newest|rating|alpha  Client-side sort applied at boot.
 *   ?filters=crypto,high_rated,...   Pre-activate filter chips at boot.
 *   ?prof=1       Append <!-- FEC-PROF --> profiling block to the body.
 *
 * NOT IN THIS PAGE YET (next iteration if validated)
 *   - Joomla topbar/footer module integration (page is currently a "blank
 *     shell" - listing renders correctly but site chrome is absent).
 */

$here = dirname(__FILE__);
chdir($here);

// --- 1. Joomla bootstrap ---
define('_JEXEC', 1);
define('JPATH_BASE', $here);

$defines   = $here . '/includes/defines.php';
$framework = $here . '/includes/framework.php';
if (!is_readable($defines) || !is_readable($framework)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Listing temporarily unavailable.';
    exit;
}
require_once $defines;
require_once $framework;

try {
    $app = JFactory::getApplication('site');
    // initialise() loads system plugins including the SEF router and the
    // language filter. Required for JRoute::_() to produce the same URLs
    // the homepage produces - the AJAX endpoint pattern (skip initialise)
    // documented in lesson #20 only applies to pure data endpoints.
    $app->initialise();
    $db = JFactory::getDbo();
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Listing temporarily unavailable.';
    exit;
}

// --- 2. Helpers ---
$libPath = $here . '/components/com_bonuses/helpers/fec_common_lib.php';
if (!is_readable($libPath)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Listing helpers missing.';
    exit;
}
require_once $libPath;

if (!function_exists('fec_get_casino_listing_view_models')
    || !function_exists('fec_render_casino_listing_grid')
    || !function_exists('fec_get_casino_listing_total_count')
    || !function_exists('fec_render_listing_pagination')) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Listing helpers incomplete - update fec_common_lib.php.';
    exit;
}

// Pagination is rendered by fec_render_listing_pagination (no JPagination).

// --- 3. Country detection (CF-IPCountry) ---
$cfCC = '';
if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $cfCC = strtoupper(substr(trim((string) $_SERVER['HTTP_CF_IPCOUNTRY']), 0, 2));
}

// --- 4. Query params + profiling setup ---
// Default view = grid (mockup-aligned). ?view=row switches to the row layout.
$view         = (isset($_GET['view']) && $_GET['view'] === 'row') ? 'row' : 'grid';
// Sort key (?sort=newest|rating|alpha; default 'frank'). Whitelisted to
// avoid injection; unknown values fall back to 'frank'.
$sortRaw      = isset($_GET['sort']) ? (string) $_GET['sort'] : 'frank';
$sortKey      = in_array($sortRaw, array('frank','newest','rating','alpha'), true) ? $sortRaw : 'frank';
$inlineBundle = !empty($_GET['bundle']);
$debugIntro   = isset($_GET['debug']) && $_GET['debug'] === 'intro';
$prof         = !empty($_GET['prof']);
$tStart       = $prof ? microtime(true) : 0;
$tMarks       = array();

// --- 4b. Debug: ?debug=intro dumps the intro-article fetch diagnostic and exits ---
if ($debugIntro) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "FEC intro-article fetch diagnostic for id=1172\n";
    echo str_repeat('-', 60) . "\n\n";
    echo "function_exists('fec_vm_fetch_article'):           "
       . (function_exists('fec_vm_fetch_article') ? 'YES' : 'NO') . "\n";
    echo "function_exists('fec_render_listing_intro_article'): "
       . (function_exists('fec_render_listing_intro_article') ? 'YES' : 'NO') . "\n\n";

    try {
        $q = $db->getQuery(true)
            ->select(array(
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('alias'),
                $db->quoteName('state'),
                $db->quoteName('publish_up'),
                $db->quoteName('publish_down'),
                'LENGTH(' . $db->quoteName('introtext') . ') AS introtext_len',
                'LENGTH(' . $db->quoteName('fulltext')  . ') AS fulltext_len',
                $db->quoteName('catid'),
                $db->quoteName('access'),
            ))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = 1172');
        $db->setQuery($q);
        $raw = $db->loadAssoc();
        echo "Direct DB row for id=1172:\n";
        if (!$raw) {
            echo "  (no row found - article id 1172 doesn't exist or different prefix)\n";
        } else {
            foreach ($raw as $k => $v) {
                echo "  $k = " . var_export($v, true) . "\n";
            }
            $stateMap = array(1 => 'published', 0 => 'unpublished', -2 => 'trashed', 2 => 'archived');
            $sLabel = isset($stateMap[(int)$raw['state']]) ? $stateMap[(int)$raw['state']] : 'unknown';
            echo "\n  -> state interpreted as: $sLabel\n";
            $now = time();
            $pu = (!empty($raw['publish_up']) && $raw['publish_up'] !== '0000-00-00 00:00:00') ? strtotime($raw['publish_up']) : 0;
            $pd = (!empty($raw['publish_down']) && $raw['publish_down'] !== '0000-00-00 00:00:00') ? strtotime($raw['publish_down']) : 0;
            echo "  -> publish window: ";
            if ($pu > 0 && $pu > $now) echo "NOT YET (publish_up in future)";
            elseif ($pd > 0 && $pd < $now) echo "EXPIRED (publish_down in past)";
            else echo "OK (within window)";
            echo "\n";
        }
    } catch (Exception $e) {
        echo "Direct query failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
    if (function_exists('fec_vm_fetch_article')) {
        $art = fec_vm_fetch_article($db, 1172);
        if ($art === null) {
            echo "fec_vm_fetch_article(\$db, 1172) returned NULL\n";
        } else {
            echo "fec_vm_fetch_article(\$db, 1172) returned a row:\n";
            echo "  title          = " . $art['title'] . "\n";
            echo "  state          = " . $art['state'] . "\n";
            echo "  publish_up     = " . $art['publish_up'] . "\n";
            echo "  publish_down   = " . $art['publish_down'] . "\n";
            echo "  introtext len  = " . strlen((string)$art['introtext']) . "\n";
            echo "  fulltext len   = " . strlen((string)$art['fulltext']) . "\n";
            echo "  introtext head = " . substr((string)$art['introtext'], 0, 200) . "\n";
        }
    }
    exit;
}

// --- 5. Pagination math (50 per page, ?page=N URL pattern) ---
// Hero, COFM/picks and editorial intro article are emitted ONLY on page 1
// (page=1) to avoid SEO-duplicate content across paginated URLs.
$pageLimit  = 50;
$page       = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalCount = fec_get_casino_listing_total_count($db, array('catid' => 8));
if ($prof) $tMarks['count_query'] = microtime(true) - $tStart;

// Clamp $page to a valid range (stale URL with ?page=N where N exceeds the
// total pages -> snap to last page).
$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $pageLimit) : 1;
if ($page > $totalPages) $page = $totalPages;
$start = ($page - 1) * $pageLimit;
$isFirstPage = ($page === 1);

// --- 6. Fetch VMs (current page slice) ---
$vms = fec_get_casino_listing_view_models($db, array(
    'catid'     => 8,
    'visitorCC' => $cfCC,
    'limit'     => $pageLimit,
    'offset'    => $start,
    'sort'      => $sortKey,
));
if ($prof) $tMarks['vms_query'] = microtime(true) - $tStart;

// Hero stats: real numbers across the full catalog (one SQL query, 3 metrics)
$heroStats = function_exists('fec_get_listing_hero_stats')
    ? fec_get_listing_hero_stats($db, array('catid' => 8))
    : array('total' => $totalCount, 'active' => 0, 'recent' => 0);
if ($prof) $tMarks['hero_stats_query'] = microtime(true) - $tStart;

// --- 7. Pagination HTML (hub-bonuses style: nav.fec-pg > ul.pagination) ---
$paginationHtml = fec_render_listing_pagination(
    $totalCount,
    $page,
    $pageLimit,
    '/fec_listing_v2.php'
);

// --- 7b. Breadcrumb (HTML + JSON-LD) - shown on every page ---
$breadcrumbItems = array(
    array('name' => 'Home',           'url' => '/'),
    array('name' => 'Online Casinos', 'url' => '/online-casinos/'),
);
$breadcrumbHtml = function_exists('fec_render_breadcrumb_html')
    ? fec_render_breadcrumb_html($breadcrumbItems) : '';
$breadcrumbJsonLd = function_exists('fec_render_breadcrumb_jsonld')
    ? fec_render_breadcrumb_jsonld($breadcrumbItems) : '';

// --- 7c. ItemList JSON-LD (page 1 only - avoid SEO duplicate across pages) ---
$itemListJsonLd = '';
if ($isFirstPage && function_exists('fec_render_list_itemlist_jsonld')) {
    $detailUrls = array();
    foreach ($vms as $vm) {
        if (!empty($vm['review_url'])) $detailUrls[] = (string) $vm['review_url'];
    }
    if (!empty($detailUrls)) {
        $itemListJsonLd = fec_render_list_itemlist_jsonld($detailUrls, array(
            'title'    => 'Online Casino Reviews',
            'per_page' => $pageLimit,
        ));
    }
}

// --- 7d. Status minirow (Updated · Showing N · How we verify) - every page ---
$nowLabel = date('F j, Y');
$minirowHtml = '<div class="fec-minirow" role="region" aria-label="Casino directory status">'
             .   '<div class="fec-minirow__inner">'
             .     '<div class="fec-minirow__left">'
             .       '<span class="fec-minirow__item">Updated: <strong>' . htmlspecialchars($nowLabel, ENT_QUOTES, 'UTF-8') . '</strong></span>'
             .       '<span class="fec-minirow__dot" aria-hidden="true">&bull;</span>'
             .       '<span class="fec-minirow__item">Showing: <strong>' . (int) $totalCount . '</strong> casinos</span>'
             .     '</div>'
             .     '<div class="fec-minirow__right">'
             .       '<a class="fec-minirow__link" href="/methodology">How we verify</a>'
             .     '</div>'
             .   '</div>'
             . '</div>';

// --- 7e. Read-first anchor strip (page 1 only) ---
// Anchors are auto-extracted from the article's own <aside class="hub-toc">
// so they stay in sync with whatever the editor lists in the CMS. Falls
// back to a placeholder set if the article body doesn't include a TOC
// (defensive - the casino directory article 1172 does include one).
$readfirstHtml = '';
$introArticle  = null;
if ($isFirstPage && function_exists('fec_vm_fetch_article')) {
    // Pre-fetch the article so we can both extract anchors AND hand the
    // already-loaded row to the lib via 'intro_article' opt (avoids a
    // second DB hit for the same article).
    try {
        $introArticle = fec_vm_fetch_article($db, 1172);
    } catch (Exception $e) {
        error_log('fec_listing_v2: intro article fetch failed: ' . $e->getMessage());
        $introArticle = null;
    }

    $anchors = array();
    if ($introArticle && function_exists('fec_extract_intro_anchors_from_html')) {
        $bodyForToc = (string) (isset($introArticle['introtext']) ? $introArticle['introtext'] : '');
        $anchors = fec_extract_intro_anchors_from_html($bodyForToc, 5);
    }

    if (!empty($anchors)) {
        $rf  = '<div class="wrap"><div class="hub-readfirst">';
        $rf .=   '<span class="lbl">Read first</span>';
        foreach ($anchors as $a) {
            $rf .= '<a href="#' . htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') . '">'
                 . htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8')
                 . '</a>';
        }
        $rf .= '</div></div>';
        $readfirstHtml = $rf;
    }
}

// --- 7f. Casino of the Month (page 1 only, sort/page-independent) ---
// COFM is editorial monthly state - pick must be stable regardless of the
// current sort or page. Pre-fetch the (small) set of has_cofm candidates
// via a dedicated lib query, then run fec_pick_cofm_vm to honor the
// "current month -> carryover" preference.
$cofmVm = null;
if ($isFirstPage && function_exists('fec_pick_cofm_vm')) {
    try {
        $cofmCandidates = fec_get_casino_listing_view_models($db, array(
            'catid'          => 8,
            'visitorCC'      => $cfCC,
            'limit'          => 0,       // unbounded - typically 1-3 rows
            'where_has_cofm' => true,
        ));
        if (!empty($cofmCandidates)) {
            $cofmVm = fec_pick_cofm_vm($cofmCandidates, $db, $cfCC);
        }
    } catch (Exception $e) {
        error_log('fec_listing_v2: COFM prefetch failed: ' . $e->getMessage());
    }
    if ($prof) $tMarks['cofm_query'] = microtime(true) - $tStart;
}

// --- 7g. Byline + authors @graph JSON-LD (page 1 only, emitted AFTER intro) ---
$bylineHtml = '';
$authorsJsonLd = '';
if ($isFirstPage) {
    $updatedIso  = date('c');
    $updatedHuman = $nowLabel;
    $bylineHtml = '<div class="wrap">'
                .   '<div class="hub-byline">'
                .     '<div class="person">'
                .       '<span class="who">Written by</span>'
                .       '<span class="name"><a href="/authors/david-romano/" rel="author">David Romano</a></span>'
                .       '<span class="role">Lead Casino Analyst &middot; Editorial Director</span>'
                .     '</div>'
                .     '<div class="person">'
                .       '<span class="who">Reviewed by</span>'
                .       '<span class="name"><a href="/authors/sabrina-oconnell/">Sabrina O&rsquo;Connell</a></span>'
                .       '<span class="role">Compliance &amp; Operator Vetting</span>'
                .     '</div>'
                .     '<div class="updated">Updated: <time datetime="' . htmlspecialchars($updatedIso, ENT_QUOTES, 'UTF-8') . '">'
                .       htmlspecialchars($updatedHuman, ENT_QUOTES, 'UTF-8')
                .     '</time></div>'
                .   '</div>'
                . '</div>';

    $authorsGraph = array(
        '@context' => 'https://schema.org',
        '@graph'   => array(
            array(
                '@type'    => 'Person',
                '@id'      => 'https://www.freeextrachips.com/#author-8',
                'name'     => 'David Romano',
                'url'      => 'https://www.freeextrachips.com/authors/david-romano/',
                'jobTitle' => 'Lead Casino Analyst \xc2\xb7 Editorial Director',
                'worksFor' => array(
                    '@type' => 'Organization',
                    'name'  => 'FreeExtraChips',
                    'url'   => 'https://www.freeextrachips.com/',
                ),
            ),
            array(
                '@type'    => 'Person',
                '@id'      => 'https://www.freeextrachips.com/#author-9',
                'name'     => "Sabrina O\xe2\x80\x99Connell",
                'url'      => 'https://www.freeextrachips.com/authors/sabrina-oconnell/',
                'jobTitle' => 'Compliance & Operator Vetting',
                'worksFor' => array(
                    '@type' => 'Organization',
                    'name'  => 'FreeExtraChips',
                    'url'   => 'https://www.freeextrachips.com/',
                ),
            ),
            array(
                '@type'       => 'WebPage',
                '@id'         => 'https://www.freeextrachips.com/online-casinos/#webpage',
                'url'         => 'https://www.freeextrachips.com/online-casinos/',
                'name'        => 'Online Casino Reviews',
                'isPartOf'    => array(
                    '@type' => 'WebSite',
                    '@id'   => 'https://www.freeextrachips.com/#website',
                    'name'  => 'FreeExtraChips',
                    'url'   => 'https://www.freeextrachips.com/',
                ),
                'author'      => array('@id' => 'https://www.freeextrachips.com/#author-8'),
                'contributor' => array('@id' => 'https://www.freeextrachips.com/#author-9'),
            ),
            array(
                '@type'            => 'Article',
                '@id'              => 'https://www.freeextrachips.com/online-casinos/#article',
                'headline'         => 'Online Casino Reviews',
                'mainEntityOfPage' => array('@id' => 'https://www.freeextrachips.com/online-casinos/#webpage'),
                'author'           => array('@id' => 'https://www.freeextrachips.com/#author-8'),
                'contributor'      => array('@id' => 'https://www.freeextrachips.com/#author-9'),
                'publisher'        => array(
                    '@type' => 'Organization',
                    '@id'   => 'https://www.freeextrachips.com/#organization',
                    'name'  => 'FreeExtraChips',
                    'url'   => 'https://www.freeextrachips.com/',
                ),
                'dateModified'     => $updatedIso,
            ),
        ),
    );
    $authorsJsonEnc = json_encode($authorsGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($authorsJsonEnc) {
        $authorsJsonLd = '<script type="application/ld+json">' . $authorsJsonEnc . '</script>';
    }
}

// --- 8. Render listing ---
// Page 1 (start=0): hero + minirow + readfirst + COFM spotlight +
//                   filterbar + 50 cards + pagination + intro article 1172
//                   + byline.
// Pages 2+ :        minirow + filterbar + 50 cards + pagination only.
// Editor's picks:   COFM single-card spotlight (picks_mode='cofm'). To
//                   switch to a 3-card .hub-picks-band, set 'band' instead.
// Alphabet bar:     removed entirely (show_alphabet=false).
// Intro article:    AFTER the list stack (directory above-the-fold; editorial
//                   content as E-E-A-T footer). Only on page 1, SEO-safe.
// chunk_initial=0:  disable in-page lazy reveal - we paginate server-side.
$listingHtml = fec_render_casino_listing_grid($vms, array(
    // Editor's pick = top of the catalog ribbon. Only meaningful on:
    //   - first page (otherwise it'd flag a random row mid-catalog)
    //   - default sort (frank): the first row IS the top editorial pick.
    //     On 'newest'/'rating'/'alpha' the first row is just "the most
    //     recent" / "the highest rated" / "the alphabetically first" -
    //     none of those is the editorial pick.
    // -1 = no pick on this render (lib branch checked by editorsIdx >= 0).
    'editorsPickIdx'   => ($isFirstPage && $sortKey === 'frank') ? 0 : -1,
    'view'             => $view,
    'chunk_initial'    => 0,
    'show_hero'        => $isFirstPage,
    'show_alphabet'    => false,
    'picks_mode'       => $isFirstPage ? 'cofm' : 'none',
    'cofm_vm'          => $cofmVm,
    'intro_position'   => 'after',
    'intro_article'    => $isFirstPage ? $introArticle : null,
    'intro_article_id' => 0,
    'minirow_html'     => $minirowHtml,
    'readfirst_html'   => $readfirstHtml,
    'pagination_html'  => $paginationHtml,
    'hero' => array(
        'title' => 'Online Casino Reviews',
        'intro' => 'Editorially curated catalogue of real-money online casinos. Every listing is hand-reviewed against the FreeExtraChips Frank Score methodology and continuously monitored for anti-fraud signals.',
        'pills' => array('Hand-tested', 'Frank Score audited', 'Anti-fraud monitoring'),
        'totalCount' => $heroStats['total'],
        'stats'      => array(
            'Total reviewed' => (string) $heroStats['total'],
            'Active casinos' => (string) $heroStats['active'],
            'Added recently' => (string) $heroStats['recent'] . ' (last 30 days)',
            'Sort default'   => 'Frank Score',
        ),
    ),
    'filterbar' => array(
        'visitorCountry' => $cfCC,
        'activeSort'     => $sortKey,
    ),
));
if ($prof) $tMarks['render_grid'] = microtime(true) - $tStart;

// --- 9. Optional CSS bundle (interim, pre-deploy) ---
$inlineBundleCss = '';
if ($inlineBundle) {
    $bundlePath = $here . '/SPRINT3_listing_full.css';
    if (is_readable($bundlePath)) {
        $inlineBundleCss = file_get_contents($bundlePath);
    }
}

$esc = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$canonical = 'https://www.freeextrachips.com/online-casinos/';

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Online Casino Reviews — Hand-tested Directory · FreeExtraChips</title>
<meta name="description" content="Editorially curated catalogue of real-money online casinos. Frank Score audited, anti-fraud monitoring, hand-tested by the FreeExtraChips editorial team.">
<link rel="canonical" href="<?php echo $esc($canonical); ?>">
<meta property="og:title" content="Online Casino Reviews · FreeExtraChips">
<meta property="og:description" content="Editorially curated catalogue of real-money online casinos.">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo $esc($canonical); ?>">
<meta name="robots" content="index,follow">

<!-- Production stylesheets (lesson #4: flat /css/ paths from webroot) -->
<link rel="stylesheet" href="/css/fecv2.css">
<link rel="stylesheet" href="/css/fec-edition.css">
<?php if ($inlineBundleCss !== ''): ?>
<style id="sprint3-listing-bundle-interim">
<?php echo $inlineBundleCss; ?>
</style>
<?php endif; ?>
</head>

<!-- Body class fecx (lesson #10) enables the editorial design system scope.
     fecv2 keeps the legacy stylesheet active alongside until full migration. -->
<body class="fecv2 fecx">

<main role="main">
<?php
echo $breadcrumbHtml;
echo $breadcrumbJsonLd;
echo $itemListJsonLd;
echo $listingHtml;
echo $bylineHtml;
echo $authorsJsonLd;
?>
</main>

<!-- sprint 3 step 2 client behaviour (view toggle, sort, filter chips,
     FAQ bulk controls, hub-toc scrollspy). DOM-ready-safe via internal
     DOMContentLoaded guard; defer here is belt-and-braces. -->
<script src="/js/fec_listing.js" defer></script>

<?php if ($prof): ?>
<!-- FEC-PROF
  page:          <?php echo $page; ?>

  total_count:   <?php echo $totalCount; ?>

  total_pages:   <?php echo $totalPages; ?>

  vms_rows:      <?php echo is_array($vms) ? count($vms) : 0; ?>

<?php foreach ($tMarks as $label => $secs): ?>
  <?php echo str_pad($label . ':', 15) . sprintf('%.3f', $secs); ?>s
<?php endforeach; ?>
-->
<?php endif; ?>

</body>
</html>