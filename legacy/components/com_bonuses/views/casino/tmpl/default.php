<?php
defined('_JEXEC') or die('Restricted access');

$h = function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$sets        = isset($this->sets) ? $this->sets : array();

$countrycode = isset($this->countrycode) ? $this->countrycode : 'us';
$countryname = isset($this->countryname) ? $this->countryname : 'United States';
$countrycode = strtolower(trim((string)$countrycode));
$cc = $countrycode;


$bidIds = array();
foreach ($sets as $s) {
  if (!empty($s['bid'])) $bidIds[] = (int)$s['bid'];
}
$bidIds = array_values(array_unique(array_filter($bidIds)));



$app    = JFactory::getApplication();
$input  = $app->input;

// ------------------------------------------------------------
// SEO guard — legacy ?page=N hub URLs 301 to the clean brand
// URL; expired-archive views (?show_expired=1) are noindexed;
// the canonical is pinned to the clean path. This drains the
// GSC bucket "Duplicate, Google chose different canonical
// than user" fed by the pre-V2 paginated template, whose
// ?page=2/3/... URLs still resolved 200 with content identical
// to page 1 while self-canonicalising with the query string.
// ------------------------------------------------------------
$fecShowExpired = ((int)$input->getInt('show_expired', 0) === 1);
$fecPagiObj     = isset($this->pagination) ? $this->pagination : null;
$fecPagesTotal  = ($fecPagiObj && is_object($fecPagiObj) && isset($fecPagiObj->pagesTotal))
  ? max(1, (int)$fecPagiObj->pagesTotal)
  : 1;
$fecHidePagi    = !empty($GLOBALS['fec_hide_pagination']);

if (function_exists('fec_canonical_page_guard')) {
  fec_canonical_page_guard(array(
    'total_pages' => ($fecHidePagi ? 1 : $fecPagesTotal),
    'allow_page'  => ($fecShowExpired && !$fecHidePagi && $fecPagesTotal > 1),
    'noindex'     => $fecShowExpired,
    'keep_params' => ($fecShowExpired ? array('show_expired') : array()),
  ));
}

$fxScope = strtolower(trim((string)$input->getCmd('fx_scope', 'country')));
if ($fxScope !== 'global') $fxScope = 'country';

$cidPage = (!empty($sets) && !empty($sets[0]['cid'])) ? (int)$sets[0]['cid'] : 0;


// ------------------------------------------------------------
// FXCheck widget (casino-level): summary + latest reports
// ------------------------------------------------------------
$db = JFactory::getDbo();

$cidPage = (!empty($sets) && !empty($sets[0]['cid'])) ? (int)$sets[0]['cid'] : 0;

$countrycode = strtolower(trim((string)$countrycode));
$cc = $countrycode; // già normalized

// country filter clause


// tweakables
$fx_days = 45;
$fx_limit = 5;

$fx_summary = array(
  'yes' => 0, 'no' => 0, 'total' => 0,
  'pct_yes' => null,
  'badge' => 'lowdata', // lowdata|verified|mixed|issues
);

$fx_latest = array();

// --- 1) Summary (last N days, casino + country)
// Columns assumptions in #__bonus_feedback:
// - cid (int), bid (int), country_code (varchar), created_at (datetime)
// - vote or is_yes / is_no (int/bool) OR (yes/no as tinyint)
//
// If your schema uses vote=1 for yes and vote=0 for no, swap in below.

if (!empty($bidIds)) {
  // Summary query: count yes/no in last N days for this casino & country
  $q = $db->getQuery(true)
    ->select(array(
      "SUM(CASE WHEN f.vote = 1 THEN 1 ELSE 0 END) AS yes_count",
      "SUM(CASE WHEN f.vote = 0 THEN 1 ELSE 0 END) AS no_count",
      "COUNT(*) AS total_count"
    ))
    ->from($db->quoteName('#__bonus_feedback', 'f'))
    ->where('f.bid IN (' . implode(',', $bidIds) . ')')
    ->where('(f.country_code IS NULL OR f.country_code = "" OR LOWER(f.country_code) = ' . $db->quote($cc) . ')')
    ->where('f.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$fx_days . ' DAY)');

  // NOTE: If your table uses different field name than "vote",
  // replace the CASEs accordingly (e.g., f.is_yes = 1).
  $db->setQuery($q);
  $sum = $db->loadAssoc();

  if ($sum) {
    $fx_summary['yes'] = (int)$sum['yes_count'];
    $fx_summary['no']  = (int)$sum['no_count'];
    $fx_summary['total'] = (int)$sum['total_count'];
    if ($fx_summary['total'] > 0) {
      $fx_summary['pct_yes'] = (int)round(($fx_summary['yes'] / max(1, $fx_summary['total'])) * 100);
    }

    // badge (simple, decision-first)
    // - lowdata: < 8 reports
    // - verified: >= 80% yes with enough data
    // - issues:   <= 40% yes with enough data
    // - mixed:    everything else with enough data
    if ($fx_summary['total'] < 8) {
      $fx_summary['badge'] = 'lowdata';
    } else {
      $pct = (int)$fx_summary['pct_yes'];
      if ($pct >= 80) $fx_summary['badge'] = 'verified';
      else if ($pct <= 40) $fx_summary['badge'] = 'issues';
      else $fx_summary['badge'] = 'mixed';
    }
  }

  // --- 2) Latest reports (last N days, limit)
  $q2 = $db->getQuery(true)
    ->select(array(
      'f.bid',
      'f.created_at',
      'f.vote',
      'f.comment'
    ))
    ->from($db->quoteName('#__bonus_feedback', 'f'))
    ->where('f.bid IN (' . implode(',', $bidIds) . ')')
    ->where('(f.country_code IS NULL OR f.country_code = "" OR LOWER(f.country_code) = ' . $db->quote($cc) . ')')
    ->where('f.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$fx_days . ' DAY)')
    ->order('f.created_at DESC');

  $db->setQuery($q2, 0, (int)$fx_limit);
  $fx_latest = (array)$db->loadAssocList();
}

$fx_summary_g = array('yes'=>0,'no'=>0,'total'=>0,'pct_yes'=>null,'badge'=>'lowdata');
$fx_latest_g  = array();

if (!empty($bidIds)) {

  // --- GLOBAL summary (no country filter)
  $qg = $db->getQuery(true)
    ->select(array(
      "SUM(CASE WHEN f.vote = 1 THEN 1 ELSE 0 END) AS yes_count",
      "SUM(CASE WHEN f.vote = 0 THEN 1 ELSE 0 END) AS no_count",
      "COUNT(*) AS total_count"
    ))
    ->from($db->quoteName('#__bonus_feedback', 'f'))
    ->where('f.bid IN (' . implode(',', $bidIds) . ')')
    ->where('f.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$fx_days . ' DAY)');

  $db->setQuery($qg);
  $sumg = $db->loadAssoc();

  if ($sumg) {
    $fx_summary_g['yes']   = (int)$sumg['yes_count'];
    $fx_summary_g['no']    = (int)$sumg['no_count'];
    $fx_summary_g['total'] = (int)$sumg['total_count'];

    if ($fx_summary_g['total'] > 0) {
      $fx_summary_g['pct_yes'] = (int)round(($fx_summary_g['yes'] / max(1,$fx_summary_g['total'])) * 100);
    }

    if ($fx_summary_g['total'] < 8) $fx_summary_g['badge'] = 'lowdata';
    else {
      $pct = (int)$fx_summary_g['pct_yes'];
      if ($pct >= 80) $fx_summary_g['badge'] = 'verified';
      else if ($pct <= 40) $fx_summary_g['badge'] = 'issues';
      else $fx_summary_g['badge'] = 'mixed';
    }
  }

  // --- GLOBAL latest (no country filter)
  $q2g = $db->getQuery(true)
    ->select(array('f.bid','f.created_at','f.vote','f.comment'))
    ->from($db->quoteName('#__bonus_feedback', 'f'))
    ->where('f.bid IN (' . implode(',', $bidIds) . ')')
    ->where('f.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$fx_days . ' DAY)')
    ->order('f.created_at DESC');

  $db->setQuery($q2g, 0, (int)$fx_limit);
  $fx_latest_g = (array)$db->loadAssocList();
}

// helper: relative time (lightweight)
function fec_rel_time($dt) {
  $ts = strtotime($dt);
  if (!$ts) return '';
  $diff = time() - $ts;
  if ($diff < 60) return 'just now';
  $m = floor($diff/60);
  if ($m < 60) return $m.' min ago';
  $h = floor($m/60);
  if ($h < 24) return $h.'h ago';
  $d = floor($h/24);
  if ($d < 14) return $d.'d ago';
  $w = floor($d/7);
  return $w.'w ago';
}

// helper: type label per bid (so we can show "Free Spins / 1st Deposit Match / Cashback")
function fec_bonus_type_label_for_bid($bid, $sets, $bonustypeMap, $countrycode) {
  foreach ($sets as $s) {
    if ((int)$s['bid'] === (int)$bid) {
      $t = isset($s['type']) ? (string)$s['type'] : '';
      // use your map; fallback to type code
      return isset($bonustypeMap[$t]) ? (string)$bonustypeMap[$t] : $t;
    }
  }
  return '';
}


// Review URL (stesso schema della lib)
$reviewUrl = ($cidPage > 0)
  ? JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$cidPage.'&lang=en&view=article')
  : '';

// Play URL “casino-level”: prendo il primo bonus ACTIVE (o fallback primo set)
$playUrl = '';
$playBid = 0;

$bonustypeMap = isset($this->bonustypeMap) ? $this->bonustypeMap : array();
$currencyMap  = isset($this->currencyMap)  ? $this->currencyMap  : array();

$pick = null;
foreach ($sets as $s) {
  $d = fec_decide_state($s, $countrycode);
  $st = isset($d['state']) ? (string)$d['state'] : 'ACTIVE';
  if ($st === 'ACTIVE') { $pick = $s; break; }
}
if ($pick === null && !empty($sets)) $pick = $sets[0];

if ($pick) {
  $cidRow = isset($pick['cid']) ? (int)$pick['cid'] : 0;
  $bidRow = isset($pick['bid']) ? (int)$pick['bid'] : 0;
  $link   = isset($pick['link']) ? trim((string)$pick['link']) : '';

  if ($link !== '' && $link !== '0' && $cidRow > 0 && $bidRow > 0) {
    $playUrl = JRoute::_('index.php?option=com_bonuses&view=ads&cid='.$cidRow.'&bid='.$bidRow.'&tmpl=component');
  } else {
    $playUrl = fec_pick_play_url($pick);
  }
}




$pagination  = isset($this->pagination) ? $this->pagination : null;


$pageUpdatedDate     = isset($this->pageUpdatedDate) ? $this->pageUpdatedDate : '';
$pageUpdatedRelative = isset($this->pageUpdatedRelative) ? $this->pageUpdatedRelative : '';
$pageUpdatedIso      = isset($this->pageUpdatedIso) ? $this->pageUpdatedIso : '';

if (!is_array($sets)) $sets = array();

// --- derive casino name (from first set)
$casinoName = 'Casino';

if (!empty($sets)) {
  $first = $sets[0];
  if (!empty($first['brand'])) $casinoName = (string)$first['brand'];
  // Se hai URL già in set, attacca qui (dipende dal tuo schema)
  // if (!empty($first['review_url'])) $reviewUrl = (string)$first['review_url'];
  // if (!empty($first['play_url']))   $playUrl   = (string)$first['play_url'];
}

// --- build buckets + metrics
$active  = array();
$expired = array();

$cntCodes = 0;
$cntCashableYes = 0;
$wrNums = array();
$fxYes = 0; $fxNo = 0;
$cntGeo = 0; // active offers the visitor cannot claim (GEO_BLOCK)

foreach ($sets as $set) {
  $dec = function_exists('fec_decide_state') ? fec_decide_state($set, $countrycode) : array('state' => 'active');
  $st  = isset($dec['state']) ? strtolower((string)$dec['state']) : 'active';

  if ($st === 'skip') continue;

  if ($st === 'expired') $expired[] = $set;
  else $active[] = $set;
  if ($st === 'geo_block') $cntGeo++;

  $code = isset($set['code']) ? trim((string)$set['code']) : '';
  if ($code !== '') $cntCodes++;

  $cash = isset($set['cashable']) ? strtolower(trim((string)$set['cashable'])) : '';
  if ($cash === 'yes') $cntCashableYes++;

  $wrN = function_exists('fec_vm_wr_number')
    ? fec_vm_wr_number($set, isset($set['type']) ? (string)$set['type'] : '', isset($set['wr_meth']) ? (string)$set['wr_meth'] : '')
    : '';
  if ($wrN !== '' && is_numeric($wrN)) $wrNums[] = (float)$wrN;

  $fxYes += isset($set['fec_yes']) ? (int)$set['fec_yes'] : 0;
  $fxNo  += isset($set['fec_no'])  ? (int)$set['fec_no']  : 0;
}

$cntActive  = count($active);
$cntExpired = count($expired);
$cntAll     = $cntActive + $cntExpired;

// Casino closed to the visitor (2026-10-21, lib v104): every active offer is
// geo-blocked for the detected country. The page says it once, in a banner
// under the title; the cards then drop their per-card "Not available in
// your country" note (the lib reads $GLOBALS['fec_geo_said']) and keep the
// disabled CTA. Before, a US reader met "Showing for United States" and
// eight cards each telling him he could not claim, and no sentence saying
// the casino does not take US players.
$casinoClosedHere = ($cntActive > 0 && $cntGeo === $cntActive);
if ($casinoClosedHere) $GLOBALS['fec_geo_said'] = true;


// ---- toolbar state
$showExpired = (int)$input->getInt('show_expired', 0) === 1;

// helper: build url toggles without duplicating params
$uri = JUri::getInstance();
$q   = $uri->getQuery(true);

$baseQ = $q;
unset($baseQ['show_expired']);
// Never propagate pagination or widget state into the expired
// toggles: the legacy ?page=N kept re-entering the internal link
// graph through these links (?page=3&show_expired=1 in the live
// source), which is what kept Googlebot recrawling the dead
// paginated URLs.
unset($baseQ['page']);
unset($baseQ['fx_scope']);

$baseUrl = $uri->toString(array('path')) . (count($baseQ) ? ('?'.http_build_query($baseQ)) : '');

$showUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'show_expired=1';
$hideUrl = $baseUrl; // no param


$wrMin = null; $wrMax = null;
if (!empty($wrNums)) { $wrMin = min($wrNums); $wrMax = max($wrNums); }

$typicalWr = '—';
if ($wrMin !== null && $wrMax !== null) {
  $typicalWr = ($wrMin === $wrMax) ? ($wrMin.'x') : ($wrMin.'–'.$wrMax.'x');
}

$fxTotal = $fxYes + $fxNo;
$fxSummary = ($fxTotal > 0) ? ($fxYes.' yes / '.$fxNo.' no') : 'Low data';

// ============================================================
// JSON-LD: WebPage + ItemList (NO Article/Author per le brand pages)
// ============================================================
$siteBase = rtrim(JUri::root(), '/');

// Page URL (canonical-aligned)
$uri = JUri::getInstance();
$pageUrl = $uri->toString(array('scheme','host','path'));
if (substr($pageUrl, -1) !== '/') $pageUrl .= '/'; // brand pages have trailing slash
$pageUrl = preg_replace('/\s+/', '', $pageUrl);

$webPageId = $pageUrl . '#webpage';
$orgId     = $siteBase . '#org';

// WebPage name
$webPageName = (preg_match('/\bcasino\b\s*$/i', $casinoName))
  ? $casinoName . ' Bonuses'
  : $casinoName . ' Casino Bonuses';

// Build ItemList from $active offers
$itemListElements = array();
$pos = 1;
foreach ($active as $set) {
  $bidRow = isset($set['bid']) ? (int)$set['bid'] : 0;
  $cidRow = isset($set['cid']) ? (int)$set['cid'] : 0;
  $type   = isset($set['type']) ? (string)$set['type'] : '';
  if ($bidRow <= 0 || $cidRow <= 0 || $type === '') continue;

  // Build absolute detail URL
  $detailRoute = JRoute::_('index.php?option=com_bonuses&view=details&type='.$type.'&bid='.$bidRow.'&cid='.$cidRow);
  
  // protocol-relative to absolute
  if (strpos($detailRoute, '//') === 0) {
    $detailUrl = 'https:' . $detailRoute;
  } elseif (!preg_match('#^https?://#i', $detailRoute)) {
$hostBase = rtrim($uri->toString(array('scheme','host','port')), '/');
    if (substr($detailRoute, 0, 1) !== '/') $detailRoute = '/' . $detailRoute;
    $detailUrl = $hostBase . $detailRoute;
  } else {
    $detailUrl = $detailRoute;
  }
  
  $detailUrl = preg_replace('/\s+/', '', $detailUrl);

  $itemListElements[] = array(
    '@type' => 'ListItem',
    'position' => $pos,
    'url' => $detailUrl
  );
  $pos++;
}

// Build the JSON-LD graph
$jsonLdGraph = array(
  '@context' => 'https://schema.org',
  '@graph' => array(
    array(
      '@type' => 'Organization',
      '@id'   => $orgId,
      'name'  => 'FreeExtraChips',
      'url'   => $siteBase
    ),
    array(
      '@type' => 'WebPage',
      '@id'   => $webPageId,
      'url'   => $pageUrl,
      'name'  => $webPageName,
      'isPartOf' => array(
        '@type' => 'WebSite',
        '@id'   => $siteBase . '#website',
        'name'  => 'FreeExtraChips',
        'url'   => $siteBase . '/'
      ),
      'publisher' => array('@id' => $orgId),
      'mainEntity' => array(
        '@type' => 'ItemList',
        'name' => 'Active ' . (preg_match('/\bcasino\b\s*$/i', $casinoName) ? $casinoName : $casinoName . ' Casino') . ' Bonuses',
        'numberOfItems' => count($itemListElements),
        'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
        'itemListElement' => $itemListElements
      )
    )
  )
);

// Add dateModified if available
if (!empty($pageUpdatedIso)) {
  $jsonLdGraph['@graph'][1]['dateModified'] = $pageUpdatedIso;
}

$flags = 0;
if (defined('JSON_UNESCAPED_SLASHES')) $flags |= JSON_UNESCAPED_SLASHES;
if (defined('JSON_UNESCAPED_UNICODE')) $flags |= JSON_UNESCAPED_UNICODE;
$casinoJsonLd = json_encode($jsonLdGraph, $flags);

?>
<?php echo isset($GLOBALS['fec_breadcrumb_html']) ? $GLOBALS['fec_breadcrumb_html'] : '';
?>

<div class="wrap fecx">

  <!-- HEADER (cb-head) -->
  <header class="cb-head">
    <div class="cb-head-grid">
      <div class="cb-head-left">
        <div class="cb-head-eyebrow">Casino bonuses</div>
        <h1 class="cb-head-title"><?php echo $h($casinoName); ?> &mdash; Bonuses &amp; Codes</h1>
        <div class="cb-head-meta">
          <span><?php echo (int)$cntAll; ?> offers tracked</span>
          <span class="dot" aria-hidden="true"></span>
          <span>Showing for <?php echo $h($countryname); ?></span>
        </div>
        <p class="cb-head-tagline">All offers for <?php echo $h($casinoName); ?> in one place &mdash; codes, wagering, cashability, FXCheck&trade;.</p>
      </div>
      <?php if ($pageUpdatedDate !== ''): ?>
        <?php
          // The outer pill already carries an "UPDATED" label, so the mini-pill
          // should only show the relative time (e.g. "7 days ago") -- never
          // "Updated 7 days ago", which would read as a duplicate.
          // lib v104 (2026-10-21): the relative part is computed in the
          // browser from data-ts (the lib script, data-fec-bare), so a cached
          // copy of this page never says "3 days ago" a week later. Without
          // JS, or with an older lib, the server text prints as before.
          $relText = trim((string)$pageUpdatedRelative);
          $relText = preg_replace('/^updated\s+/i', '', $relText);
          $relTs   = ($pageUpdatedIso !== '') ? (int)strtotime((string)$pageUpdatedIso) : 0;
          $relLive = ($relTs > 0 && function_exists('fec_render_hub_cards_assets'));
          if ($relLive) echo fec_render_hub_cards_assets();
        ?>
        <div class="cb-head-tag">
          <div class="cb-updated">
            <span class="cb-updated-label">Updated</span>
            <?php if ($pageUpdatedIso !== ''): ?>
              <time class="cb-updated-date" datetime="<?php echo $h($pageUpdatedIso); ?>"><?php echo $h($pageUpdatedDate); ?></time>
            <?php else: ?>
              <span class="cb-updated-date"><?php echo $h($pageUpdatedDate); ?></span>
            <?php endif; ?>
            <?php if ($relLive): ?>
              <span class="cb-updated-rel" data-fec-fresh="updated" data-ts="<?php echo (int)$relTs; ?>" data-fec-ladder="long" data-fec-bare="1"></span>
            <?php elseif ($relText !== ''): ?>
              <span class="cb-updated-rel"><?php echo $h($relText); ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($casinoClosedHere): ?>
  <?php
    // One sentence for the whole page (see $casinoClosedHere). Inline style
    // is a fallback until fec-edition.css carries .cb-geo-note.
    $cbDirUrl = JRoute::_('index.php?option=com_content&view=category&id=8') ;
    if ($cbDirUrl === '' || $cbDirUrl === '/' || stripos($cbDirUrl, 'index.php') !== false) $cbDirUrl = '/online-casinos';
    $cbDirUrl .= (strpos($cbDirUrl, '?') !== false ? '&' : '?') . 'filters=accepts_country';
  ?>
  <section class="cb-geo-note" role="note" aria-label="Availability" style="margin:12px 0 18px;padding:12px 16px;border:1px solid rgba(0,0,0,.12);border-radius:10px;font-size:.95rem;line-height:1.5">
    <strong><?php echo $h($casinoName); ?> doesn&rsquo;t accept players from <?php echo $h($countryname); ?>.</strong>
    The <?php echo (int)$cntActive; ?> active <?php echo ((int)$cntActive === 1) ? 'offer' : 'offers'; ?> below <?php echo ((int)$cntActive === 1) ? 'is' : 'are'; ?> listed for the record and can&rsquo;t be claimed from there.
    <a href="<?php echo $h($cbDirUrl); ?>">Casinos open in <?php echo $h($countryname); ?> &rarr;</a>
  </section>
  <?php endif; ?>
  
  <?php
  // ---------------------------------------------------------------------
  // Intro override per-cid (brand intro HTML)
  // File esterno: helpers/intro_overrides_cid.php
  // Ritorna array [cid => '<section class="fec-intro">...</section>']
  // Se il cid non è presente o il file manca, la sezione non viene stampata.
  // ---------------------------------------------------------------------
  $introFile = JPATH_COMPONENT . DS . 'helpers' . DS . 'intro_overrides_cid.php';
  if (is_file($introFile)) {
      $introOverrides = include $introFile;
      if (is_array($introOverrides) && !empty($introOverrides[$cidPage])) {
          echo $introOverrides[$cidPage];
      }
  }
  ?>

<?php
$hasFx = ($fx_summary['total'] > 0);
$badge = $fx_summary['badge'];
$uri = JUri::getInstance();
$q = $uri->getQuery(true);
unset($q['page']); // FX toggle must not carry legacy pagination
$q['fx_scope'] = 'global';
$fxGlobalUrl = $uri->toString(array('path')) . '?' . http_build_query($q) . '#fxcheck';
?>

<?php
// ----------------------------
// FXCheck widget dataset switch
// ----------------------------
$fxScope = strtolower(trim((string)$input->getCmd('fx_scope', 'country')));
if ($fxScope !== 'global') $fxScope = 'country';

// pick dataset
$useSummary = ($fxScope === 'global') ? $fx_summary_g : $fx_summary;
$useLatest  = ($fxScope === 'global') ? $fx_latest_g  : $fx_latest;

$hasFx = ((int)$useSummary['total'] > 0);
$badge = !empty($useSummary['badge']) ? (string)$useSummary['badge'] : 'lowdata';

// label
$scopeLabel = ($fxScope === 'global') ? 'Global' : $countryname;

// build urls
$uri = JUri::getInstance();
$q0  = $uri->getQuery(true);
unset($q0['page']); // FX toggle must not carry legacy pagination

$fxGlobalUrl = '';
if ($fxScope === 'country' && (int)$fx_summary_g['total'] > (int)$fx_summary['total']) {
  $q = $q0;
  $q['fx_scope'] = 'global';
  $fxGlobalUrl = $uri->toString(array('path')) . '?' . http_build_query($q) . '#fxcheck';
}

$fxCountryUrl = '';
if ($fxScope === 'global') {
  $q = $q0;
  unset($q['fx_scope']);
  $fxCountryUrl = $uri->toString(array('path')) . (count($q) ? ('?' . http_build_query($q)) : '') . '#fxcheck';
}
?>

<section class="cb-fxbox" id="fxcheck" aria-label="FXCheck widget">
  <div class="cb-fxbox-head">
    <div class="cb-fxbox-title">
      <h3>FXCheck&trade; &mdash; <?php echo $h($casinoName); ?></h3>
      <div class="sub">
        <?php if ($hasFx): ?>
          <span>Last <?php echo (int)$fx_days; ?> days &middot; <?php echo $h($scopeLabel); ?></span>
        <?php else: ?>
          <span>Not enough reports yet &middot; <?php echo $h($scopeLabel); ?></span>
        <?php endif; ?>
        <?php if ($fxGlobalUrl !== ''): ?>
          <a href="<?php echo $h($fxGlobalUrl); ?>">See Global (<?php echo (int)$fx_summary_g['total']; ?>)</a>
        <?php elseif ($fxCountryUrl !== ''): ?>
          <a href="<?php echo $h($fxCountryUrl); ?>">Back to <?php echo $h($countryname); ?></a>
        <?php endif; ?>
      </div>
    </div>

    <div class="cb-fxbox-stats">
      <?php if ($hasFx): ?>
        <span class="positive"><?php echo (int)$useSummary['pct_yes']; ?>% positive</span>
        <span class="reports"><?php echo (int)$useSummary['total']; ?> reports</span>
      <?php else: ?>
        <span class="reports">Low data</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($useLatest)): ?>
    <?php
    // Build bid -> [type, cid] lookup from this casino's listed bonuses ($sets)
    // so each FXCheck report row can produce the CANONICAL detail URL
    // (type+bid+cid). Without all three, Joomla's router emits a generic
    // /bonuses/<slug>-id<bid> path instead of the casino-scoped
    // /bonuses/<casino-slug>/<slug>-id<bid> path, which Google has been
    // indexing as duplicates. Rows whose bid isn't in $sets (rare: stale
    // global reports for delisted bonuses) intentionally emit no link
    // rather than a wrong one.
    $bidLookup = array();
    if (!empty($sets) && is_array($sets)) {
      foreach ($sets as $s) {
        if (!isset($s['bid'])) continue;
        $bidLookup[(int)$s['bid']] = array(
          'type' => isset($s['type']) ? (string)$s['type'] : '',
          'cid'  => isset($s['cid'])  ? (int)$s['cid']    : (int)$cidPage,
        );
      }
    }
    ?>
    <div class="cb-fxbox-list">
      <?php foreach ($useLatest as $r): ?>
        <?php
          $bid   = (int)$r['bid'];
          $isYes = ((string)$r['vote'] === '1');
          $when  = fec_rel_time($r['created_at']);

          $lk    = isset($bidLookup[$bid]) ? $bidLookup[$bid] : null;
          $bType = $lk ? $lk['type'] : '';
          $bCid  = $lk ? (int)$lk['cid'] : 0;

          $detailsUrl = ($bid > 0 && $bType !== '' && $bCid > 0)
            ? JRoute::_('index.php?option=com_bonuses&view=details&type='.$bType.'&bid='.$bid.'&cid='.$bCid)
            : '';

          $typelabel = function_exists('fec_bonus_type_label_for_bid')
            ? fec_bonus_type_label_for_bid($bid, $sets, $bonustypeMap, $countrycode)
            : '';
          $comment = isset($r['comment']) ? trim((string)$r['comment']) : '';
          if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($comment, 'UTF-8') > 110) $comment = mb_substr($comment, 0, 110, 'UTF-8') . '&hellip;';
          } else {
            if (strlen($comment) > 110) $comment = substr($comment, 0, 110) . '...';
          }
        ?>
        <div class="cb-fxbox-item">
          <div class="icon <?php echo $isYes ? 'yes' : 'no'; ?>" aria-hidden="true"><?php echo $isYes ? '&check;' : '&times;'; ?></div>
          <div class="body">
            <div class="row1">
              <span class="verdict <?php echo $isYes ? 'yes' : 'no'; ?>"><?php echo $isYes ? 'Worked' : 'Issue'; ?></span>
              <?php if ($typelabel !== '' && $detailsUrl !== ''): ?>
                <span class="btype"><a href="<?php echo $h($detailsUrl); ?>"><?php echo $h($typelabel); ?></a></span>
              <?php elseif ($typelabel !== ''): ?>
                <span class="btype"><?php echo $h($typelabel); ?></span>
              <?php endif; ?>
              <?php if ($when !== ''): ?>
                <?php $whenTs = (int)strtotime((string)$r['created_at']); ?>
                <span class="ago"<?php if ($whenTs > 0 && function_exists('fec_render_hub_cards_assets')): ?> data-fec-fresh="updated" data-ts="<?php echo $whenTs; ?>" data-fec-ladder="long" data-fec-bare="1"<?php endif; ?>><?php echo $h($when); ?></span>
              <?php endif; ?>
            </div>
            <?php if ($comment !== ''): ?>
              <div class="comment"><?php echo $h($comment); ?></div>
            <?php else: ?>
              <div class="comment">No details.</div>
            <?php endif; ?>
          </div>
          <?php if ($detailsUrl !== ''): ?>
            <a class="view" href="<?php echo $h($detailsUrl); ?>"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>View</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="cb-fxbox-empty">
      <?php echo ($fxScope === 'global')
        ? 'No recent FXCheck reports for this casino globally.'
        : 'No recent FXCheck reports for this casino in your country.'; ?>
    </div>
  <?php endif; ?>

  <div class="cb-fxbox-cta">
    <div class="cb-fxbox-cta-left">
      <span><b>Tried a bonus here?</b></span>
      <a href="#active">Report your experience</a>
    </div>
    <div class="cb-fxbox-cta-right">Reports are user-submitted. Results may vary.</div>
  </div>
</section>

<?php
// ---------------------------------------------------------------------
// FXRate (FEC Tools No. 05): the casino's welcome packages priced from the
// operator's own terms - one compact card per track with the four numbers
// and the ladder, each leg linking to its bonus page. The map that ties the
// loose first-deposit cards below into their ladders. Same standalone helper
// and guarded call as the casino review page; echoes nothing when the casino
// has no active package, and a missing helper never breaks the page.
// Rendered before the cards on purpose: their "Package" pills link to it.
// ---------------------------------------------------------------------
$fxrHelper = JPATH_SITE . '/components/com_bonuses/helpers/fec_fxrate_render.php';
if ($cidPage > 0 && is_readable($fxrHelper)) {
    require_once $fxrHelper;
    if (function_exists('fec_fxrate_render_casino_block')) {
        try {
            echo fec_fxrate_render_casino_block($db, $cidPage, array('brand' => $casinoName, 'wrap' => false));
        } catch (Exception $e) {
            // render nothing
        }
    }
}
?>

<div class="cb-tabs" role="group" aria-label="Offer filters">
  <span class="tab active">Active only <span class="count"><?php echo (int)$cntActive; ?></span></span>

  <?php if ($cntExpired > 0): ?>
    <?php if (!$showExpired): ?>
      <a class="tab" href="<?php echo $h($showUrl); ?>#expired">Show expired <span class="count">(<?php echo (int)$cntExpired; ?>)</span></a>
    <?php else: ?>
      <a class="tab" href="<?php echo $h($hideUrl); ?>">Hide expired</a>
    <?php endif; ?>
  <?php endif; ?>

  <span class="cb-tabs-spacer"></span>
  <span class="sort">Sorted by newest</span>
</div>


  <!-- ACTIVE LIST -->
  <section id="active">
    <div class="cb-section-title">
      <h2>Active offers</h2>
      <?php if ($cntActive > 0): ?>
        <span class="meta"><?php echo (int)$cntActive; ?> available</span>
      <?php endif; ?>
    </div>

    <?php if ($cntActive <= 0): ?>
      <div class="fec-zero">
        <p class="fec-zero__title">No active offers right now.</p>
        <?php if ($cntExpired > 0): ?>
          <p class="fec-zero__sub">You can still check expired offers below.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="hub-grid">
        <?php foreach ($active as $set): ?>
          <?php
            $bonustype = isset($set['type']) ? (string)$set['type'] : '';
            $currency  = isset($set['currency']) ? (string)$set['currency'] : '';
 echo fec_render_v2_list_card_html($set, $countrycode, $countryname, $bonustypeMap, $currencyMap);
          ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<!-- EXPIRED (hidden unless toggled) -->
<?php if ($cntExpired > 0 && $showExpired): ?>
  <section id="expired">
    <div class="cb-section-title">
      <h2>Expired offers</h2>
      <span class="meta"><?php echo (int)$cntExpired; ?> no longer claimable</span>
    </div>

    <div class="hub-grid fec-grid--expired">
      <?php foreach ($expired as $set): ?>
        <?php
          $bonustype = isset($set['type']) ? (string)$set['type'] : '';
          $currency  = isset($set['currency']) ? (string)$set['currency'] : '';
echo fec_render_v2_list_card_html($set, $countrycode, $countryname, $bonustypeMap, $currencyMap);
        ?>
      <?php endforeach; ?>
    </div>

  </section>
<?php endif; ?>

  <!-- PAGINATION (if any) -->
<?php
$hidePagi = !empty($GLOBALS['fec_hide_pagination']);
?>

<?php if (!$hidePagi && $pagination && is_object($pagination) && method_exists($pagination, 'render')): ?>
  <div class="fec-pagi">
    <?php if (method_exists($pagination, 'padding')) $pagination->padding(false); ?>
    <?php echo $pagination->render(); ?>
  </div>
<?php endif; ?>

  <!-- WATCHOUT (cb-watchout) -->
  <?php
  // Hardcoded defaults (used when no per-cid override is configured).
  // Items support inline <b>...</b> for emphasis (handled by .cb-watchout CSS).
  $watchoutDefaults = array(
    '<b>Expired codes usually don&apos;t reactivate</b> &mdash; check &ldquo;Active offers&rdquo; first.',
    '<b>High wagering</b> can make a bonus not worth it for casual play.',
    '&ldquo;<b>Cashable</b>&rdquo; doesn&apos;t always mean &ldquo;withdrawable&rdquo; &mdash; verify limits in Details.',
  );

  // Per-cid overrides (optional): helpers/watchout_overrides_cid.php returns
  // array(cid => array('<li html>', '<li html>', ...)). Falls back to defaults.
  $watchoutFile = JPATH_COMPONENT . DS . 'helpers' . DS . 'watchout_overrides_cid.php';
  $watchoutOverrides = is_file($watchoutFile) ? include $watchoutFile : array();
  $watchoutItems = (isset($watchoutOverrides[$cidPage]) && is_array($watchoutOverrides[$cidPage]) && !empty($watchoutOverrides[$cidPage]))
    ? $watchoutOverrides[$cidPage]
    : $watchoutDefaults;
  ?>
  <section class="cb-watchout" aria-label="What to watch out for">
    <h3>What to watch out for</h3>
    <ul class="cb-watchout-list">
      <?php foreach ($watchoutItems as $item): ?>
        <li><?php echo $item; ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- NEWSLETTER -->
  <?php
  echo fec_render_email_capture_block('casino_hub', array(
    'variant'  => 'inline',
    'heading'  => 'This week, verified.',
    'subhead'  => "The 5 best bonuses we'd actually claim, plus what got flagged.",
  ));
  ?>

</div>

<?php if ($showExpired): ?>
<script>
(function(){
  try {
    // If URL has #expired, ensure smooth scroll after layout is ready
    var hash = window.location.hash || '';
    if (hash.toLowerCase() !== '#expired') return;

    // Delay to allow cards/grid to render and heights to settle
    setTimeout(function(){
      var el = document.getElementById('expired');
      if (!el) return;

      // Respect reduced-motion
      var prefersReduced = false;
      try { prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

      el.scrollIntoView({ behavior: prefersReduced ? 'auto' : 'smooth', block: 'start' });
    }, 60);
  } catch(e){}
})();
</script>
<?php endif; ?>
<?php echo isset($GLOBALS['fec_breadcrumb_jsonld']) ? $GLOBALS['fec_breadcrumb_jsonld'] : '';
?>

<?php if (!empty($casinoJsonLd)): ?>
<script type="application/ld+json"><?php echo $casinoJsonLd; ?></script>
<?php endif; ?>