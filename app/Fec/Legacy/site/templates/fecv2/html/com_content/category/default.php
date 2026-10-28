<?php
/**
 * FreeExtraChips - Joomla 2.5 template override
 * Path: /templates/fecv2/html/com_content/category/default.php
 *
 * Replaces the default com_content category view with the FEC v2.1 casino
 * directory layout. Mirrors fec_listing_v2.php (standalone canary URL) but
 * adapted to the Joomla template context:
 *   - Joomla-style pagination URLs (?start=N) via JRoute::_()
 *   - Reads catid + Itemid from the Joomla context ($this->category,
 *     JFactory::getApplication()->input)
 *   - No <head>/<body>/<html> chrome - that's handled by the parent template
 *     (templates/fecv2/index.php)
 *   - Same view-models, same renderers, same JSON-LD as the v2 canary so
 *     visual parity is automatic
 *
 * If something goes wrong (lib missing, DB error, etc.) the file falls back
 * to a minimal notice rather than a fatal. Rollback: restore the previous
 * /templates/fecv2/html/com_content/category/default.php (back it up before
 * deploying this file).
 *
 * Lib dependency: /components/com_bonuses/helpers/fec_common_lib.php
 * Blog branch (catid FEC_BLOG_CATID): /components/com_bonuses/helpers/fec_blog.php
 */

defined('_JEXEC') or die;

// ---------------------------------------------------------------------------
// 1. Load the FEC lib (defensive: degrade gracefully if missing)
// ---------------------------------------------------------------------------
$libPath = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php'; // PORT: was JPATH_ROOT (one directory on Joomla; here the code is under JPATH_SITE)
if (!file_exists($libPath)) {
    echo '<div class="wrap"><p>Casino directory temporarily unavailable.</p></div>';
    return;
}
require_once $libPath;

// ---------------------------------------------------------------------------
// 2. Joomla context: db, app, input, category metadata
// ---------------------------------------------------------------------------
$db       = JFactory::getDbo();
$app      = JFactory::getApplication();
$jinput   = $app->input;
$category = $this->category;
$catid    = (int) (isset($category->id) ? $category->id : 0);
$catTitle = isset($category->title) ? (string) $category->title : 'Casinos';

if (defined('FEC_CLUSTERS_CATID') && FEC_CLUSTERS_CATID > 0
    && $catid === FEC_CLUSTERS_CATID && function_exists('fec_render_clusters_index')) {
    echo fec_render_clusters_index($db);
    return;
}

// Itemid: prefer the GET param, but SEF URLs usually don't carry it, so fall
// back to the active menu item. Without a correct Itemid, JRoute::_() can't
// resolve index.php?option=com_content&view=category&id=8 to /online-casinos
// and degrades to the site root (the redirect-to-home bug on filter clicks).
$Itemid = (int) $jinput->getInt('Itemid', 0);
if ($Itemid <= 0) {
    $menu = $app->getMenu();
    if ($menu) {
        $activeItem = $menu->getActive();
        if ($activeItem && isset($activeItem->id)) {
            $Itemid = (int) $activeItem->id;
        }
    }
}

// ---------------------------------------------------------------------------
// 2b. Casino Bonus Blog (catid = FEC_BLOG_CATID): own listing renderer in
//     components/com_bonuses/helpers/fec_blog.php. Guarded include; when the
//     helper is missing the category falls through to the casino directory
//     code below (harmless, it just shows an empty casino grid).
// ---------------------------------------------------------------------------
$fecBlogHelper = JPATH_SITE . '/components/com_bonuses/helpers/fec_blog.php'; // PORT: was JPATH_ROOT
if (is_readable($fecBlogHelper)) {
    require_once $fecBlogHelper;
}
if (defined('FEC_BLOG_CATID') && FEC_BLOG_CATID > 0 && $catid === (int) FEC_BLOG_CATID
    && function_exists('fec_blog_render_listing')) {
    try {
        echo fec_blog_render_listing($db, $category, array('Itemid' => $Itemid));
    } catch (Exception $e) {
        error_log('FEC blog listing render failed: ' . $e->getMessage());
        echo '<div class="wrap"><p>Blog temporarily unavailable.</p></div>';
    }
    return;
}

// Ensure fec_listing.js loads on this page (the parent fecv2 template
// doesn't auto-attach it for com_content category views). defer=true so
// the script parses after the DOM - the JS reads .cl-list-stack at boot
// without a DOMContentLoaded guard.
JFactory::getDocument()->addScript(
    JURI::base(true) . '/js/fec_listing.js',
    'text/javascript',
    true   // defer
);

if ($catid <= 0) {
    echo '<div class="wrap"><p>Invalid category.</p></div>';
    return;
}

// ---------------------------------------------------------------------------
// 3. Query params (Joomla pagination uses ?start=N, our sort uses ?sort=KEY)
// ---------------------------------------------------------------------------
$pageLimit = 50;
$start     = max(0, (int) $jinput->getInt('start', 0));
$start     = (int) (floor($start / $pageLimit) * $pageLimit);
$page      = (int) floor($start / $pageLimit) + 1;

$sortRaw = $jinput->getString('sort', 'frank');
$sortKey = in_array($sortRaw, array('frank','newest','rating','alpha'), true) ? $sortRaw : 'frank';

// Chip filters (server-side). Comma-separated in ?filters=, whitelisted.
$filterRaw   = $jinput->getString('filters', '');
$filterWl    = array('accepts_country', 'crypto', 'high_rated', 'established_5y');
$activeFilters = array();
if ($filterRaw !== '') {
    foreach (explode(',', $filterRaw) as $fkey) {
        $fkey = trim($fkey);
        if (in_array($fkey, $filterWl, true) && !in_array($fkey, $activeFilters, true)) {
            $activeFilters[] = $fkey;
        }
    }
}

$view = ($jinput->getString('view', 'grid') === 'row') ? 'row' : 'grid';

$cfCC = '';
if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $cc = strtoupper(substr((string) $_SERVER['HTTP_CF_IPCOUNTRY'], 0, 2));
    if ($cc !== 'XX' && $cc !== 'T1') $cfCC = $cc;
}

// ---------------------------------------------------------------------------
// 4. Count + slice
// ---------------------------------------------------------------------------
$totalCount = fec_get_casino_listing_total_count($db, array(
    'catid'     => $catid,
    'visitorCC' => $cfCC,
    'filters'   => $activeFilters,
));
$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $pageLimit) : 1;
if ($page > $totalPages) {
    $page  = $totalPages;
    $start = ($page - 1) * $pageLimit;
}
$isFirstPage = ($page === 1);

// ---------------------------------------------------------------------------
// 4b. SEO guard - legacy ?page=N URLs and invalid/out-of-range ?start=N 301
//     to the clean category URL; valid deep ?start=N pages get a SELF
//     canonical; the clean page gets the clean canonical pinned (replacing
//     the site-wide emitter's raw-URI canonical, query string included).
//     Kills the GSC bucket "Duplicate, Google chose different canonical
//     than user" - e.g. /online-casinos?page=22 rendering page-1 content
//     while self-canonicalising with ?page=22.
//
//     The guard reads the RAW query string, so the $page/$start clamp above
//     does not mask an invalid request: the clamp stays as a belt-and-braces
//     fallback for environments where the guard is unavailable (older lib).
//
//     base_path also 301s legacy path aliases of this category (e.g.
//     /casino-reviews) onto the canonical SEF path. Must run BEFORE any
//     HTML output - nothing has been echoed yet on this code path.
// ---------------------------------------------------------------------------
if (function_exists('fec_canonical_page_guard')) {
    $fecGuardBase  = '';
    $fecGuardRoute = JRoute::_('index.php?option=com_content&view=category&id=' . $catid
        . ($Itemid > 0 ? '&Itemid=' . $Itemid : ''));
    $fecGuardPath  = parse_url($fecGuardRoute, PHP_URL_PATH);
    if (is_string($fecGuardPath) && $fecGuardPath !== '' && $fecGuardPath !== '/'
        && stripos($fecGuardPath, 'index.php') === false) {
        $fecGuardBase = $fecGuardPath;
    } elseif ($catid === 8) {
        // Routing degraded (Itemid unresolved / non-SEF env): known SEF path.
        $fecGuardBase = '/online-casinos';
    }
    fec_canonical_page_guard(array(
        'base_path'   => $fecGuardBase,   // '' = current path, no alias enforcement
        'allow_page'  => false,           // legacy ?page= is ALWAYS stale on this view
        'allow_start' => true,            // ?start=N is the live pagination pattern
        'per_page'    => $pageLimit,
        'total_items' => (int) $totalCount,
    ));
}

// Hero stats - computed across the full catalog (one SQL query, 3 metrics)
$heroStats = function_exists('fec_get_listing_hero_stats')
    ? fec_get_listing_hero_stats($db, array('catid' => $catid))
    : array('total' => $totalCount, 'active' => 0, 'recent' => 0);

// Directory data clock (lib v96): the later of the last review touch and
// the last FXShield computation. This is the date the page prints as
// "Directory data" - the minirow and the byline used to print today's date,
// which said nothing and moved every midnight. Empty when the lib predates
// v96 or nothing is dated; the callers then print no date at all.
$dirDataSql   = isset($heroStats['data_as_of']) ? (string) $heroStats['data_as_of'] : '';
$dirDataLabel = ($dirDataSql !== '' && function_exists('fec_format_date_short')) ? fec_format_date_short($dirDataSql) : '';
$dirDataIso   = '';
if ($dirDataLabel !== '') {
    $dirDataTs  = strtotime($dirDataSql);
    $dirDataIso = $dirDataTs ? date('c', $dirDataTs) : '';
}
$dirClosed = ((int) $heroStats['total'] > 0 && (int) $heroStats['active'] > 0)
    ? max(0, (int) $heroStats['total'] - (int) $heroStats['active']) : 0;

$vms = fec_get_casino_listing_view_models($db, array(
    'catid'     => $catid,
    'visitorCC' => $cfCC,
    'limit'     => $pageLimit,
    'offset'    => $start,
    'sort'      => $sortKey,
    'filters'   => $activeFilters,
));

// ---------------------------------------------------------------------------
// 5. Pagination HTML (Joomla-style URLs via JRoute, ?start=N)
// ---------------------------------------------------------------------------
$baseRouteRaw = 'index.php?option=com_content&view=category&id=' . $catid;
if ($Itemid > 0) $baseRouteRaw .= '&Itemid=' . $Itemid;
$baseUrl = JRoute::_($baseRouteRaw);

// Safety net: if routing degraded to the site root (e.g. Itemid still
// unresolved, or a non-SEF environment), JRoute can return "/" or
// "/index.php". Detect that and fall back to the known SEF path so chip and
// pagination links never bounce to the homepage. catid=8 -> /online-casinos.
$basePath = parse_url($baseUrl, PHP_URL_PATH);
if ($basePath === null) $basePath = $baseUrl;
$looksLikeRoot = ($basePath === '' || $basePath === '/' || $basePath === '/index.php');
if ($looksLikeRoot && $catid === 8) {
    $baseUrl = rtrim(JURI::base(true), '/') . '/online-casinos';
}

// Router helper: build SEF URLs with filter/sort/view/start params routed
// through JRoute, so the SEF system recognises them as part of the menu URL
// (raw ?param concatenation onto the SEF path can bounce to the homepage).
$routeWith = function (array $extra) use ($catid, $Itemid) {
    $q = 'index.php?option=com_content&view=category&id=' . $catid;
    if ($Itemid > 0) $q .= '&Itemid=' . $Itemid;
    foreach ($extra as $k => $v) {
        if ($v === '' || $v === null) continue;
        $q .= '&' . $k . '=' . rawurlencode((string) $v);
    }
    return JRoute::_($q);
};

$paginationUrlBuilder = function ($p) use ($routeWith, $pageLimit, $activeFilters, $sortKey, $view) {
    $extra = array();
    if ($p > 1)                  $extra['start']   = ($p - 1) * $pageLimit;
    if (!empty($activeFilters))  $extra['filters'] = implode(',', $activeFilters);
    if ($sortKey !== 'frank')    $extra['sort']    = $sortKey;
    if ($view === 'row')         $extra['view']    = 'row';
    return $routeWith($extra);
};

$paginationHtml = fec_render_listing_pagination(
    $totalCount, $page, $pageLimit, $baseUrl,
    array('url_builder' => $paginationUrlBuilder)
);

// Per-chip toggle URLs (current filter set with that chip flipped).
$chipUrls = array();
foreach ($filterWl as $fname) {
    $next = $activeFilters;
    $pos  = array_search($fname, $next, true);
    if ($pos === false) { $next[] = $fname; } else { array_splice($next, $pos, 1); }
    sort($next);
    $extra = array();
    if (!empty($next))            $extra['filters'] = implode(',', $next);
    if ($sortKey !== 'frank')     $extra['sort']    = $sortKey;
    if ($view === 'row')          $extra['view']    = 'row';
    $chipUrls[$fname] = $routeWith($extra);
}
// Per-sort URLs (keep current filters/view).
$sortUrls = array();
foreach (array('frank','newest','rating','alpha') as $sname) {
    $extra = array();
    if (!empty($activeFilters))   $extra['filters'] = implode(',', $activeFilters);
    if ($sname !== 'frank')       $extra['sort']    = $sname;
    if ($view === 'row')          $extra['view']    = 'row';
    $sortUrls[$sname] = $routeWith($extra);
}

// ---------------------------------------------------------------------------
// 6. Breadcrumb (HTML + JSON-LD on every page)
// ---------------------------------------------------------------------------
$breadcrumbItems = array(
    array('name' => 'Home',    'url' => '/'),
    array('name' => $catTitle, 'url' => $baseUrl),
);
$breadcrumbHtml = function_exists('fec_render_breadcrumb_html')
    ? fec_render_breadcrumb_html($breadcrumbItems) : '';
$breadcrumbJsonLd = function_exists('fec_render_breadcrumb_jsonld')
    ? fec_render_breadcrumb_jsonld($breadcrumbItems) : '';

// ---------------------------------------------------------------------------
// 7. ItemList JSON-LD (page 1 only - avoid duplicate across paginated pages)
// ---------------------------------------------------------------------------
$itemListJsonLd = '';
if ($isFirstPage && function_exists('fec_render_list_itemlist_jsonld')) {
    $detailUrls = array();
    foreach ($vms as $vm) {
        if (!empty($vm['review_url'])) $detailUrls[] = (string) $vm['review_url'];
    }
    if (!empty($detailUrls)) {
        $itemListJsonLd = fec_render_list_itemlist_jsonld($detailUrls, array(
            'title'    => $catTitle,
            'per_page' => $pageLimit,
        ));
    }
}

// ---------------------------------------------------------------------------
// 8. Status minirow (every page)
// ---------------------------------------------------------------------------
// What the row says (2026-10-21): the data clock, then how the catalogue
// splits - the directory keeps closed and suspended casinos on file (they
// sink to the last pages), and nothing said so while "Showing: 804" sat
// next to "Active casinos 343". No date is printed when none is known.
$minirowItems = array();
if ($dirDataLabel !== '') {
    $minirowItems[] = '<span class="fec-minirow__item">Directory data <strong>' . htmlspecialchars($dirDataLabel, ENT_QUOTES, 'UTF-8') . '</strong></span>';
}
if ((int) $heroStats['active'] > 0) {
    $minirowItems[] = '<span class="fec-minirow__item"><strong>' . (int) $heroStats['active'] . '</strong> operating'
                    . ($dirClosed > 0 ? ' &middot; <strong>' . (int) $dirClosed . '</strong> closed or suspended, kept on file' : '')
                    . '</span>';
} else {
    $minirowItems[] = '<span class="fec-minirow__item"><strong>' . (int) $totalCount . '</strong> casinos</span>';
}
$minirowHtml = '<div class="fec-minirow" role="region" aria-label="Casino directory status">'
             .   '<div class="fec-minirow__inner">'
             .     '<div class="fec-minirow__left">'
             .       implode('<span class="fec-minirow__dot" aria-hidden="true">&bull;</span>', $minirowItems)
             .     '</div>'
             .     '<div class="fec-minirow__right">'
             .       '<a class="fec-minirow__link" href="/methodology">How we verify</a>'
             .     '</div>'
             .   '</div>'
             . '</div>';

// ---------------------------------------------------------------------------
// 9. Read-first anchors + intro article pre-fetch (page 1 only)
// ---------------------------------------------------------------------------
// The intro article id for /online-casinos/ (catid=8) is hardcoded to 1172.
// TODO: derive from category metadata if other categories need their own intro.
$INTRO_ARTICLE_ID = ($catid === 8) ? 1172 : 0;

$readfirstHtml = '';
$introArticle  = null;
if ($isFirstPage && $INTRO_ARTICLE_ID > 0 && function_exists('fec_vm_fetch_article')) {
    try {
        $introArticle = fec_vm_fetch_article($db, $INTRO_ARTICLE_ID);
    } catch (Exception $e) {
        error_log('FEC default.php: intro article fetch failed: ' . $e->getMessage());
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

// Explainer credit (2026-10-21): the intro article has an author and an
// edit date of its own in jos_content (author_id -> jos_casino_authors,
// modified). The page byline prints them, so the article body no longer
// needs a hand-written "By ... Last updated ..." paragraph that goes stale.
$explainer = null;
if ($isFirstPage && $introArticle && $INTRO_ARTICLE_ID > 0 && function_exists('fec_get_content_author')) {
    try {
        $xq = $db->getQuery(true)
            ->select(array($db->quoteName('author_id'), $db->quoteName('created'), $db->quoteName('modified')))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $INTRO_ARTICLE_ID);
        $db->setQuery($xq);
        $xrow = $db->loadAssoc();
        if (!$db->getErrorNum() && is_array($xrow) && (int) $xrow['author_id'] > 0) {
            $xa = fec_get_content_author($db, (int) $xrow['author_id']);
            if (is_array($xa) && !empty($xa['name'])) {
                $xMod = (string) $xrow['modified'];
                $xCre = (string) $xrow['created'];
                if ($xMod === '' || $xMod === '0000-00-00 00:00:00') $xMod = '';
                if ($xCre === '' || $xCre === '0000-00-00 00:00:00') $xCre = '';
                $xTouch = ($xMod > $xCre) ? $xMod : $xCre;
                $explainer = array(
                    'author' => $xa,
                    'edited' => ($xTouch !== '' && function_exists('fec_format_date_short')) ? fec_format_date_short($xTouch) : '',
                    'edited_iso' => ($xTouch !== '' && strtotime($xTouch)) ? date('c', strtotime($xTouch)) : '',
                );
            }
        }
    } catch (Exception $e) {
        error_log('FEC default.php: explainer credit failed: ' . $e->getMessage());
        $explainer = null;
    }
}

// ---------------------------------------------------------------------------
// 10. Casino of the Month (page 1 only, sort/page-independent)
// ---------------------------------------------------------------------------
$cofmVm = null;
if ($isFirstPage && empty($activeFilters) && function_exists('fec_pick_cofm_vm')) {
    try {
        $cofmCandidates = fec_get_casino_listing_view_models($db, array(
            'catid'          => $catid,
            'visitorCC'      => $cfCC,
            'limit'          => 0,
            'where_has_cofm' => true,
        ));
        if (!empty($cofmCandidates)) {
            $cofmVm = fec_pick_cofm_vm($cofmCandidates, $db, $cfCC);
        }
    } catch (Exception $e) {
        error_log('FEC default.php: COFM prefetch failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// 11. Byline + authors @graph JSON-LD (page 1 only)
// ---------------------------------------------------------------------------
$bylineHtml    = '';
$authorsJsonLd = '';
if ($isFirstPage) {
    // Dates (2026-10-21): "Directory data" is the data clock computed above,
    // never today's date; the explainer credit carries the article's own
    // author and edit date. JSON-LD dateModified follows the data clock.
    $updatedIso   = ($dirDataIso !== '') ? $dirDataIso : date('c');
    $explainerHtml = '';
    if (is_array($explainer)) {
        $xa    = $explainer['author'];
        $xSlug = isset($xa['slug']) ? (string) $xa['slug'] : '';
        $xName = htmlspecialchars((string) $xa['name'], ENT_QUOTES, 'UTF-8');
        $xRole = isset($xa['role']) ? htmlspecialchars((string) $xa['role'], ENT_QUOTES, 'UTF-8') : '';
        $xRoleBits = array();
        if ($xRole !== '') $xRoleBits[] = $xRole;
        if ($explainer['edited'] !== '') $xRoleBits[] = 'edited ' . htmlspecialchars($explainer['edited'], ENT_QUOTES, 'UTF-8');
        $explainerHtml = '<div class="person">'
                       .   '<span class="who">Explainer by</span>'
                       .   '<span class="name">' . ($xSlug !== '' ? '<a href="/authors/' . htmlspecialchars($xSlug, ENT_QUOTES, 'UTF-8') . '/">' . $xName . '</a>' : $xName) . '</span>'
                       .   (!empty($xRoleBits) ? '<span class="role">' . implode(' &middot; ', $xRoleBits) . '</span>' : '')
                       . '</div>';
    }
    $bylineHtml = '<div class="wrap">'
                .   '<div class="hub-byline">'
                .     '<div class="person">'
                .       '<span class="who">Directory by</span>'
                .       '<span class="name"><a href="/authors/david-romano/" rel="author">David Romano</a></span>'
                .       '<span class="role">Lead Casino Analyst &middot; Editorial Director</span>'
                .     '</div>'
                .     '<div class="person">'
                .       '<span class="who">Reviewed by</span>'
                .       '<span class="name"><a href="/authors/sabrina-oconnell/">Sabrina O&rsquo;Connell</a></span>'
                .       '<span class="role">Compliance &amp; Operator Vetting</span>'
                .     '</div>'
                .     $explainerHtml
                .     ($dirDataLabel !== ''
                        ? '<div class="updated">Directory data '
                          . ($dirDataIso !== '' ? '<time datetime="' . htmlspecialchars($dirDataIso, ENT_QUOTES, 'UTF-8') . '">' : '')
                          . htmlspecialchars($dirDataLabel, ENT_QUOTES, 'UTF-8')
                          . ($dirDataIso !== '' ? '</time>' : '')
                          . '</div>'
                        : '')
                .   '</div>'
                . '</div>';

    $canonicalUrl = rtrim(JURI::root(), '/') . '/online-casinos/';
    $contributors = array(array('@id' => 'https://www.freeextrachips.com/#author-9'));
    $explainerNode = null;
    if (is_array($explainer) && !empty($explainer['author']['id'])) {
        $xa = $explainer['author'];
        $explainerNode = array(
            '@type'    => 'Person',
            '@id'      => 'https://www.freeextrachips.com/#author-' . (int) $xa['id'],
            'name'     => (string) $xa['name'],
            'url'      => 'https://www.freeextrachips.com/authors/' . (isset($xa['slug']) ? (string) $xa['slug'] : '') . '/',
            'jobTitle' => isset($xa['role']) ? (string) $xa['role'] : '',
            'worksFor' => array('@type' => 'Organization', 'name' => 'FreeExtraChips', 'url' => 'https://www.freeextrachips.com/'),
        );
        if ((int) $xa['id'] !== 8 && (int) $xa['id'] !== 9) {
            $contributors[] = array('@id' => $explainerNode['@id']);
        } else {
            $explainerNode = null; // already in the graph
        }
    }
    $authorsGraph = array(
        '@context' => 'https://schema.org',
        '@graph'   => array(
            array(
                '@type'    => 'Person',
                '@id'      => 'https://www.freeextrachips.com/#author-8',
                'name'     => 'David Romano',
                'url'      => 'https://www.freeextrachips.com/authors/david-romano/',
                'jobTitle' => "Lead Casino Analyst \xc2\xb7 Editorial Director",
                'worksFor' => array('@type' => 'Organization', 'name' => 'FreeExtraChips', 'url' => 'https://www.freeextrachips.com/'),
            ),
            array(
                '@type'    => 'Person',
                '@id'      => 'https://www.freeextrachips.com/#author-9',
                'name'     => "Sabrina O\xe2\x80\x99Connell",
                'url'      => 'https://www.freeextrachips.com/authors/sabrina-oconnell/',
                'jobTitle' => 'Compliance & Operator Vetting',
                'worksFor' => array('@type' => 'Organization', 'name' => 'FreeExtraChips', 'url' => 'https://www.freeextrachips.com/'),
            ),
            array(
                '@type'    => 'WebPage',
                '@id'      => $canonicalUrl . '#webpage',
                'url'      => $canonicalUrl,
                'name'     => $catTitle,
                'isPartOf' => array('@type' => 'WebSite', '@id' => 'https://www.freeextrachips.com/#website', 'name' => 'FreeExtraChips', 'url' => 'https://www.freeextrachips.com/'),
                'author'      => array('@id' => 'https://www.freeextrachips.com/#author-8'),
                'contributor' => (count($contributors) === 1) ? $contributors[0] : $contributors,
            ),
            array(
                '@type'            => 'Article',
                '@id'              => $canonicalUrl . '#article',
                'headline'         => $catTitle,
                'mainEntityOfPage' => array('@id' => $canonicalUrl . '#webpage'),
                'author'           => array('@id' => 'https://www.freeextrachips.com/#author-8'),
                'contributor'      => (count($contributors) === 1) ? $contributors[0] : $contributors,
                'publisher'        => array('@type' => 'Organization', '@id' => 'https://www.freeextrachips.com/#organization', 'name' => 'FreeExtraChips', 'url' => 'https://www.freeextrachips.com/'),
                'dateModified'     => $updatedIso,
            ),
        ),
    );
    if ($explainerNode !== null) {
        // Person nodes first, page nodes after - insert before the WebPage.
        array_splice($authorsGraph['@graph'], 2, 0, array($explainerNode));
    }
    $authorsJsonEnc = json_encode($authorsGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($authorsJsonEnc) {
        $authorsJsonLd = '<script type="application/ld+json">' . $authorsJsonEnc . '</script>';
    }
}

// ---------------------------------------------------------------------------
// 12. Render listing (single lib call emits hero, COFM, filterbar, cards,
//     pagination, intro article 'after')
// ---------------------------------------------------------------------------
$listingHtml = fec_render_casino_listing_grid($vms, array(
    'editorsPickIdx'   => ($isFirstPage && empty($activeFilters) && $sortKey === 'frank') ? 0 : -1,
    'view'             => $view,
    'chunk_initial'    => 0,
    'show_hero'        => $isFirstPage,
    'show_alphabet'    => false,
    'picks_mode'       => ($isFirstPage && empty($activeFilters)) ? 'cofm' : 'none',
    'cofm_vm'          => $cofmVm,
    'intro_position'   => 'after',
    'intro_article'    => $isFirstPage ? $introArticle : null,
    'intro_article_id' => 0,
    'minirow_html'     => $minirowHtml,
    'readfirst_html'   => $readfirstHtml,
    'pagination_html'  => $paginationHtml,
    'hero' => array(
        'title' => $catTitle,
        'intro' => 'Editorially curated catalogue of real-money online casinos. Every listing is hand-reviewed against the FreeExtraChips Frank Score methodology and continuously monitored for anti-fraud signals.',
        'pills' => array('Hand-tested', 'Frank Score audited', 'Anti-fraud monitoring'),
        // Stats override - real numbers from fec_get_listing_hero_stats
        // (computed across the FULL catalog, not the current slice).
        'totalCount' => $heroStats['total'],
        'stats'      => array(
            'Total reviewed' => (string) $heroStats['total'],
            'Active casinos' => (string) $heroStats['active'],
            'Added recently' => (string) $heroStats['recent'] . ' (last 30 days)',
            'Sort default'   => 'FRank ranking',
        ),
    ),
    'filterbar' => array(
        'visitorCountry' => $cfCC,
        'activeSort'     => $sortKey,
        // lib v96: "Showing 1-50 of 804 . FRank ranking" (older lib ignores these)
        'rangeStart'     => !empty($vms) ? $start + 1 : 0,
        'rangeEnd'       => $start + count($vms),
        'rangeTotal'     => (int) $totalCount,
        'base_url'       => $baseUrl,
        'active_filters' => $activeFilters,
        'chip_urls'      => $chipUrls,
        'sort_urls'      => $sortUrls,
    ),
));

// ---------------------------------------------------------------------------
// 13. Emit (the Joomla parent template wraps this in its own chrome)
// ---------------------------------------------------------------------------
echo $breadcrumbHtml;
echo $breadcrumbJsonLd;
echo $itemListJsonLd;
echo $listingHtml;
echo $bylineHtml;
echo $authorsJsonLd;