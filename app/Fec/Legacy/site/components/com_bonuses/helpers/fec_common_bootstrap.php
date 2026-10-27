<?php
defined('_JEXEC') or die('Restricted access');

/**
 * FEC v2 — Bootstrap (runtime)
 * - Loads dependencies + lib
 * - Builds country context, filters, query, fallback, pagination
 * - Builds $output (list cards)
 * - Sets globals used by views
 */

require_once JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';

// Lib (functions)
require_once JPATH_COMPONENT.DS.'helpers'.DS.'fec_common_lib.php';

/* =================================================
 * CONTEXT
 * ================================================= */

$geo = fec_detect_country('US');

$countrycodeup = $geo['cc_up'];              // "IT"
$countrycode   = strtolower($countrycodeup); // "it"
$GLOBALS['fec_geo_src'] = $geo['src'];

$countryname = fec_country_name($countrycodeup, $countries_array); // returns "Italy" (not uppercase)

$app    = JFactory::getApplication();
$jinput = $app->input;

// FEC V2.1 — is this com_bonuses page on the .fecx editorial system (the hub
// or a migrated sibling)? Single source of truth: fec_is_fecx_bonus() in lib.
$fec_is_fecx = fec_is_fecx_bonus();

$type    = $jinput->getString('type', '');
$subtype = $jinput->getString('subtype', '');
$cid     = $jinput->getInt('cid', 0);
$view    = $jinput->getString('view', '');
$view_lc = strtolower(trim($view));

$fec_preset = $type;
$GLOBALS['fec_preset'] = $fec_preset;

if (!isset($output) || !is_string($output)) $output = '';

/* =================================================
 * FILTERS
 * ================================================= */

$fx        = strtolower(trim($jinput->getString('fx', 'any')));
$wr_max    = (int)$jinput->getInt('wr_max', 0);
$cashable  = strtolower(trim($jinput->getString('cashable', '')));
$cashout   = strtolower(trim($jinput->getString('cashout', '')));
$codef     = strtolower(trim($jinput->getString('code', '')));
$crypto    = strtolower(trim($jinput->getString('crypto', '')));
$hide_na   = (int)$jinput->getInt('hide_na', 0);
$exclusive = strtolower(trim($jinput->getString('exclusive', '')));
$sort      = $jinput->getString('sort', 'best');

$fx_allowed = array('any','verified','mixed','lowdata','issues');
if (!in_array($fx, $fx_allowed, true)) $fx = 'any';

$wr_allowed = array(0, 20, 30, 40, 50);
if (!in_array($wr_max, $wr_allowed, true)) $wr_max = 0;

// 'cashout' = soglia minima di max cashout.
//   'nocap'                    -> solo bonus senza cap (max_cashout = 0)
//   intero positivo (es. 500)  -> max cashout >= N
//   qualsiasi altro valore     -> nessun filtro
if ($cashout !== 'nocap') {
  $cashout = (ctype_digit($cashout) && (int)$cashout > 0) ? (string)(int)$cashout : '';
}

if ($cashable !== 'yes') $cashable = '';
if (!in_array($codef, array('', 'no', 'yes'), true)) $codef = '';
if ($crypto !== 'yes') $crypto = '';
if ($hide_na !== 1) $hide_na = 0;
if ($exclusive !== 'yes') $exclusive = '';

/* =================================================
 * GEO MARKET PAGE OVERRIDE (Phase 1: AU, NZ)
 * -------------------------------------------------
 * /bonuses/no-deposit-casinos/allowed-countries/{cc}/ arrives here as
 * ?geo={cc} via the .htaccess internal rewrite. When the code is in the
 * whitelist (fec_geo_config in fec_common_lib.php), the target market
 * replaces the detected visitor country for the ENTIRE list pipeline:
 * fec_build_query filters, FOUND_ROWS count, card states, editor picks.
 * hide_na is forced so only offers actually claimable from the target
 * market are listed. The canonical is pinned to the geo URL because
 * after the internal rewrite JUri sees the hub path.
 *
 * NOTE: placed AFTER filter normalization on purpose - $hide_na is read
 * from JInput above and would otherwise overwrite the override.
 * ================================================= */
$fec_geo_ctx = function_exists('fec_geo_market_context') ? fec_geo_market_context() : null;
if ($fec_geo_ctx) {
  $countrycode   = $fec_geo_ctx['cc'];
  $countrycodeup = strtoupper($fec_geo_ctx['cc']);
  $countryname   = $fec_geo_ctx['name'];
  $GLOBALS['fec_geo_src'] = 'market';
  $hide_na = 1;
  if (function_exists('fec_geo_apply_head')) fec_geo_apply_head($fec_geo_ctx);
}
$GLOBALS['fec_geo_market'] = $fec_geo_ctx ? $fec_geo_ctx['cc'] : '';

$has_filters =
  ($fx !== 'any') ||
  ($wr_max > 0) ||
  ($cashable === 'yes') ||
  ($cashout !== '') ||
  ($codef === 'no' || $codef === 'yes') ||
  ($crypto === 'yes') ||
  ($exclusive === 'yes');

// Preset page: crypto
if ($fec_preset === 'crypto') {
  $type = '';
  $crypto = 'yes';
  $has_filters = true;
}

/* =================================================
 * PAGINATION
 * ================================================= */
 
$records_per_page = 24;
$fallback_limit   = 5;
$hide_pagination  = false;

$pagination = new Zebra_Pagination();
$getpage = ($pagination->get_page() - 1) * $records_per_page;

/**
 * Casino summary: show all offers, no pagination.
 * Keeps counts + Active/Expired buckets coherent.
 */
if ($view_lc === 'casino') {
  $records_per_page = 250;     // enough to include all offers for one casino
  $hide_pagination  = true;    // tell views/templates to hide pagination UI
  $pagination->set_page(1);    // ignore any ?page=N
  $getpage = 0;
}

$db = JFactory::getDbo();

/* =================================================
 * QUERY + FALLBACK
 * ================================================= */

$filters = array(
  'fx'        => $fx,
  'wr_max'    => $wr_max,
  'cashable'  => $cashable,
  'cashout'   => $cashout,
  'code'      => $codef,
  'crypto'    => $crypto,
  'hide_na'   => $hide_na,
  'exclusive' => $exclusive,
  'sort'      => $sort,
  'type'      => $type,
  'subtype'   => $subtype,

  // PATCH: no $_GET here — use JInput/view
  'view'      => ($view_lc === 'casino') ? 'casino' : '',
  'cid'       => (int)$cid
);

$fallback_used = false;
$fallback_relaxed = '';
$fallback_message = '';

$w = array();
$query = fec_build_query($db, $filters, $countrycode, $w);

$db->setQuery($query, $getpage, $records_per_page);
$row = (array)$db->loadAssocList();



$db->setQuery('SELECT FOUND_ROWS();');
$total = (int)$db->loadResult();

if ($total === 0 && $has_filters) {
$relax_order = array('wr_max','cashout','fx','exclusive','code','cashable');

  foreach ($relax_order as $rk) {
    $try = $filters;

    if ($rk === 'wr_max') $try['wr_max'] = 0;
    elseif ($rk === 'cashout') $try['cashout'] = '';
    elseif ($rk === 'fx') $try['fx'] = 'any';
    elseif ($rk === 'exclusive') $try['exclusive'] = '';
    elseif ($rk === 'code') $try['code'] = '';
    elseif ($rk === 'cashable') $try['cashable'] = '';
    //elseif ($rk === 'crypto') $try['crypto'] = '';

    if ($rk === 'wr_max') $try['sort'] = 'wr_low';
    if ($rk === 'cashout' && $try['sort'] === 'wr_low') $try['sort'] = 'best';

    $w_try = array();
    $q_try = fec_build_query($db, $try, $countrycode, $w_try);

    $db->setQuery($q_try, 0, $fallback_limit);
    $row_try = (array)$db->loadAssocList();

    $db->setQuery('SELECT FOUND_ROWS();');
    $total_try = (int)$db->loadResult();

    if ($total_try > 0) {
      $fallback_used = true;
      $fallback_relaxed = $rk;
      $fallback_message = 'No exact matches for your filters. Here are the closest available offers for your country.';

      $row = $row_try;
      $total = $total_try;
      $filters = $try;
      $w = $w_try;

      $pagination->set_page(1);
      $getpage = 0;
      break;
    }
  }

  if (!$fallback_used) {
    $try = $filters;
    $try['fx'] = 'any';
    $try['wr_max'] = 0;
    $try['cashout'] = '';
    $try['code'] = '';
    $try['cashable'] = '';
    $try['crypto'] = '';
    $try['exclusive'] = '';
    $try['sort'] = 'best';

    $w_try = array();
    $q_try = fec_build_query($db, $try, $countrycode, $w_try);

    $db->setQuery($q_try, 0, $fallback_limit);
    $row_try = (array)$db->loadAssocList();

    $db->setQuery('SELECT FOUND_ROWS();');
    $total_try = (int)$db->loadResult();

    if ($total_try > 0) {
      $fallback_used = true;
      $fallback_relaxed = 'multiple';
      $fallback_message = 'No exact matches for your filters. Showing broadly relevant offers available in your country.';

      $row = $row_try;
      $total = min($total_try, $fallback_limit);

      $hide_pagination = true;
      $filters = $try;
      $w = $w_try;

      $pagination->set_page(1);
      $getpage = 0;
    }
  }
}

/* =================================================
 * EDITOR'S PICKS — fetch (page 1, no filters, no fallback, sort=best)
 * ================================================= */

$pickBids = array();
$GLOBALS['fec_editor_picks_rows'] = array();

$picksAllowed = (
  ((int)$pagination->get_page() <= 1) &&
  !$has_filters &&
  !$fallback_used &&
  ($view_lc !== 'casino') &&
  ($sort === 'best' || $sort === '')
);

if ($picksAllowed) {
  $picksFilters = array(
    'type'    => $type,
    'subtype' => $subtype
  );

  $pickRows = fec_fetch_editor_picks($db, $countrycode, 3, $picksFilters);

  if (!empty($pickRows)) {
    $GLOBALS['fec_editor_picks_rows'] = $pickRows;
    $pickBids = fec_editor_picks_extract_bids($pickRows);
  }
}

/* =================================================
 * PAGE UPDATED (after final $w)
 * ================================================= */

$pageUpdatedTs   = fec_page_last_updated_ts($db, $w, '#__bonus_feedback');
$pageUpdatedPill = fec_render_updated_pill($pageUpdatedTs);

$pageUpdatedIso  = $pageUpdatedTs ? gmdate('c', $pageUpdatedTs) : '';
$updated         = fec_format_updated($pageUpdatedIso);

$GLOBALS['fec_pageUpdatedPill']     = $pageUpdatedPill;
$GLOBALS['fec_pageUpdatedIso']      = $updated['iso'];
$GLOBALS['fec_pageUpdatedDate']     = $updated['date'];
$GLOBALS['fec_pageUpdatedRelative'] = $updated['relative'];
$GLOBALS['fec_total_offers'] = (int)$total;

// Hub meta (2026-10-20): approved player reports on the bonuses in this
// page's scope - the FXCheck counts the cards print, summed - for the meta
// panel's "Player reports" row, a number in place of the old "Verification:
// FXCheck signals" label. Page-scoped like "Bonuses tracked" beside it.
$GLOBALS['fec_total_reports'] = 0;
try {
  $rq = $db->getQuery(true)
    ->select('COALESCE(SUM(COALESCE(agg.yes_count, 0) + COALESCE(agg.no_count, 0)), 0)')
    ->from($db->quoteName('#__bonuses', 'a'))
    ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON a.cid = b.contentid')
    ->join('INNER', $db->quoteName('#__bonus_feedback_agg', 'agg') . ' ON agg.bid = a.bid');
  foreach ((array)$w as $cond) $rq->where($cond);
  $db->setQuery($rq);
  $GLOBALS['fec_total_reports'] = (int)$db->loadResult();
  if ($db->getErrorNum()) { $GLOBALS['fec_total_reports'] = 0; }
} catch (Exception $e) {
  $GLOBALS['fec_total_reports'] = 0;
}

/* =================================================
 * PAGINATION INIT
 * ================================================= */

$pagination->records($total);
$pagination->records_per_page($records_per_page);

/* =================================================
 * NOTICE
 * ================================================= */

$fec_notice = '';

if ($fallback_used) {
  $rk = htmlspecialchars($fallback_relaxed, ENT_QUOTES, 'UTF-8');

  $dropKey = '';
  if ($fallback_relaxed === 'wr_max') $dropKey = 'wr_max';
  if ($fallback_relaxed === 'cashout') $dropKey = 'cashout';
  if ($fallback_relaxed === 'fx') $dropKey = 'fx';
  if ($fallback_relaxed === 'exclusive') $dropKey = 'exclusive';
  if ($fallback_relaxed === 'code') $dropKey = 'code';
  if ($fallback_relaxed === 'cashable') $dropKey = 'cashable';
  if ($fallback_relaxed === 'crypto') $dropKey = 'crypto';

  $fec_notice = '
  <div class="fec-zero" role="note" aria-label="No exact results" data-fec-fallback="1" data-fec-relaxed="'.$rk.'">
    <h3 class="fec-zero__title">No exact matches</h3>
    <p class="fec-zero__text">'.htmlspecialchars($fallback_message, ENT_QUOTES, 'UTF-8').'</p>
    <div class="fec-zero__actions" role="group" aria-label="Fallback actions">
      <button class="fec-btn fec-btn--ghost" type="button" data-fec-action="reset">Reset filters</button>'
      .($dropKey ? '<button class="fec-btn fec-btn--primary" type="button" data-fec-drop="'.htmlspecialchars($dropKey, ENT_QUOTES, 'UTF-8').'">Remove this filter</button>' : '')
    .'</div>
  </div>

  <script>
  (function(){
    var box = document.querySelector(".fec-zero[data-fec-fallback=\\"1\\"]");
    if(!box) return;

    box.addEventListener("click", function(e){
      var reset = e.target.closest("[data-fec-action=\\"reset\\"]");
      if(reset){
        var url = new URL(window.location.href);
        var keep = ["option","view","type","subtype","filter","fq","template","Itemid","lang"];
        var next = new URL(window.location.origin + window.location.pathname);
        keep.forEach(function(k){
          if(url.searchParams.has(k)) next.searchParams.set(k, url.searchParams.get(k));
        });
        window.location.href = next.toString();
        return;
      }

      var drop = e.target.closest("[data-fec-drop]");
      if(drop){
        var key = drop.getAttribute("data-fec-drop");
        var url2 = new URL(window.location.href);
        url2.searchParams.delete(key);
        url2.searchParams.delete("page");
        window.location.href = url2.toString();
      }
    });
  })();
  </script>';
} elseif ((int)$total === 0) {
  $fec_notice = '
  <div class="fec-zero" role="note" aria-label="No results">
    <h3 class="fec-zero__title">No offers available</h3>
    <p class="fec-zero__text">We couldn’t find any bonuses for this page right now. Please try again later.</p>
    <div class="fec-zero__actions" role="group" aria-label="Empty actions">
      <button class="fec-btn fec-btn--ghost" type="button" data-fec-action="reset" onclick="window.location.href=window.location.pathname;">Reset</button>
    </div>
  </div>';
}

/* =================================================
 * RENDER LIST (builds $output)
 * ================================================= */
 
$fec_itemlist_urls = array();

// Same-casino grouping (2026-10-21, lib v101): on this page, one card per
// casino and the others folded behind "+N more offers at this casino"
// (the home board's rule). Counted here so the first card knows its N;
// the fold is assembled after the loop. Picks are skipped as before.
$fecCidCount = array();
foreach ((array)$row as $fecPre) {
  $fecPreBid = isset($fecPre['bid']) ? (int)$fecPre['bid'] : 0;
  if (!empty($pickBids) && $fecPreBid > 0 && in_array($fecPreBid, $pickBids, true)) continue;
  $fecPreCid = isset($fecPre['cid']) ? (int)$fecPre['cid'] : 0;
  if ($fecPreCid <= 0) continue;
  $fecCidCount[$fecPreCid] = isset($fecCidCount[$fecPreCid]) ? $fecCidCount[$fecPreCid] + 1 : 1;
}
$fecCidSeen = array();
$fecChunks  = array();

foreach ((array)$row as $set) {
    
      // Skip Editor's Picks (rendered above the grid)
  $bidCheck = isset($set['bid']) ? (int)$set['bid'] : 0;
  if (!empty($pickBids) && $bidCheck > 0 && in_array($bidCheck, $pickBids, true)) continue;

  // Optional: keep "related" query only where it mattered before (GB label tweaks)
  if ($countrycode === 'gb') {
    $queryrel = $db->getQuery(true)
      ->select($db->quoteName(array('bonus', 'label','type', 'game','bid','related','currency','min_dep')))
      ->from($db->quoteName('#__bonuses'))
      ->where($db->quoteName('related') . ' = ' . $db->quote($set['bid']))
      ->where($db->quoteName('status') . ' LIKE "active"');

    $db->setQuery($queryrel);
    $related = $db->loadAssoc();

    if ($countrycode === 'gb' && isset($related['type']) && $related['type'] == 'fs' && !empty($related['min_dep'])) {
      $related['label'] = 'Extra Spins';
    }
    if ($countrycode === 'gb' && isset($related['type']) && $related['type'] == 'fc') {
      $related['label'] = 'Extra Cash';
    }
  }

  $decision = fec_decide_state($set, $countrycode);
  $state    = $decision['state'];
  if ($state === 'SKIP') continue;

  $cidRow = isset($set['cid']) ? (int)$set['cid'] : 0;
  $bidRow = isset($set['bid']) ? (int)$set['bid'] : 0;

  // Play URL
  $linkRaw = isset($set['link']) ? trim((string)$set['link']) : '';
  if ($linkRaw === '' || $linkRaw === '0') {
    $playUrl = fec_pick_play_url($set);
  } else {
    $playUrl = JRoute::_('index.php?option=com_bonuses&view=ads&cid='.$cidRow.'&bid='.$bidRow.'&tmpl=component');
  }

  $ctaLabel = 'PLAY NOW';
  $ctaHref  = $playUrl;

  $cardStateClass = 'fec-card--active';
  $showFxcheck    = true;

  $stateNote = '';
  if ($state === 'UNAVAILABLE') {
    $cardStateClass = 'fec-card--unavailable';
    $ctaLabel = 'Unavailable';
    $ctaHref  = '';
    $showFxcheck = false;
    $stateNote = (isset($set['brand']) ? (string)$set['brand'] : 'This casino') . ' is currently unavailable';
  } elseif ($state === 'EXPIRED') {
    $cardStateClass = 'fec-card--expired';
    $ctaLabel = 'Expired';
    $ctaHref  = '';
    $stateNote = 'This bonus has expired';
  } elseif ($state === 'GEO_BLOCK') {
    $cardStateClass = 'fec-card--geo';
    $ctaLabel = 'Not available in '.$countryname;
    $ctaHref  = '';
    $stateNote = 'Not available in your country';
  }

  // Expiry pill
  $expiryPill = '';
  if ($state === 'ACTIVE') {
    $expiryLabel = fec_expiry_label(isset($set['end']) ? $set['end'] : '');
    $expiryPill  = fec_render_expiry_pill($expiryLabel, isset($set['end']) ? $set['end'] : '');
  }

  // FX counts
  $fec_yes = isset($set['fec_yes']) ? (int)$set['fec_yes'] : 0;
  $fec_no  = isset($set['fec_no'])  ? (int)$set['fec_no']  : 0;

  $fxBadge = fec_fxcheck_classify($fec_yes, $fec_no);
  $fxLine  = fec_fxcheck_line($fxBadge['key']);

  $fec_total = $fec_yes + $fec_no;
  $fec_tip = ($fec_total > 0)
    ? ($fec_yes.' Yes / '.$fec_no.' No ('.$fec_total.' reports)')
    : 'No reports yet';

  // Related bonus (optional)
  $relSet = array(
    'bid'         => isset($set['rel_bid']) ? $set['rel_bid'] : '',
    'type'        => isset($set['rel_type']) ? $set['rel_type'] : '',
    'bonus'       => isset($set['rel_bonus']) ? $set['rel_bonus'] : '',
    'max_bonus'   => isset($set['rel_max_bonus']) ? $set['rel_max_bonus'] : '',
    'currency'    => isset($set['rel_currency']) ? $set['rel_currency'] : '',
    'game'        => isset($set['rel_game']) ? $set['rel_game'] : '',
    'fp_time'     => isset($set['rel_fp_time']) ? $set['rel_fp_time'] : '',
    'fp_time_den' => isset($set['rel_fp_time_den']) ? $set['rel_fp_time_den'] : ''
  );

  // Type text (card shorthand, no game names, + related if any)
  $hasRel = (!empty($relSet['bid']) && !empty($relSet['type']) && !empty($relSet['bonus']));

  // Card rule:
  // - If the main bonus is a standalone Free Spins offer, keep the game name ("Free Spins on X").
  // - If there's a related/extra bonus, keep the related part compact (no game name) to avoid clutter.
  if (!$hasRel && isset($set['type']) && (string)$set['type'] === 'fs') {
    $typetxt = fec_typetxt($set, $countrycode, $bonustype);
  } else {
    $typetxt = fec_card_typeline($set, $relSet, $countrycode, $currency, $bonustype);
  }

  // Amount pieces (same rules as before)
  $curr = '';
  $perc = '';
  $upto = '';

  if ((string)$set['type'] === 'fs') {
    $curr = '';
    $perc = '';
  } elseif (!isset($set['currency']) || $set['currency'] == '') {
    $curr = '<sup class="curr">$</sup>';
  } else {
    $sym = isset($currency[$set['currency']]) ? $currency[$set['currency']] : '$';
    $curr = '<sup class="curr">'.$sym.'</sup>';
  }

  if (!empty($set['max_bonus']) && (float)$set['max_bonus'] > 0) {
    $sym2 = isset($currency[$set['currency']]) ? $currency[$set['currency']] : '$';
    $upto = 'up to '.$sym2.htmlspecialchars($set['max_bonus'], ENT_QUOTES, 'UTF-8').'';
  }

  if ((string)$set['type'] === 'nd' || (string)$set['type'] === 'fs' || (string)$set['type'] === 'fp' || (string)$set['type'] === 'fc') {
    $perc = '';
    $upto = '';
  } else {
    $perc = '<sup class="perc">%</sup>';
    $curr = '';
  }

  $wrLabel = fec_vm_wager_label($set, isset($set['type']) ? $set['type'] : '', isset($set['wr_meth']) ? $set['wr_meth'] : '');
  if ($wrLabel === '') $wrLabel = '0';

  $cashTxt = isset($set['cashable']) ? strtolower(trim((string)$set['cashable'])) : '';
  $cashTxt = ($cashTxt === 'yes' || $cashTxt === 'no') ? ucfirst($cashTxt) : 'Not stated';

  $url = JRoute::_('index.php?option=com_bonuses&view=details&type='.$set['type'].'&bid='.$bidRow.'&cid='.$cidRow);
  
  // JSON-LD ItemList (LIST pages): keep it light -> only details URLs
  $fec_itemlist_urls[] = $url;

  $logo = isset($set['jr_logosmall']) ? (string)$set['jr_logosmall'] : '';

  $exclusivePill = '';
  if (isset($set['exclusive']) && fnmatch('yes', (string)$set['exclusive'])) {
    $exclusivePill = '<span class="fec-pill fec-pill--excl">Exclusive</span>';
  }

  $fec_fact = $showFxcheck ? fec_render_fxcheck_fact($fec_yes, $fec_no) : '';

  $noteHtml = '';
  if ($stateNote !== '') {
    // State note (UNAVAILABLE / EXPIRED / GEO_BLOCK) → critical, full box
    $noteHtml = '<div class="fec-card__note">'.htmlspecialchars($stateNote, ENT_QUOTES, 'UTF-8').'</div>';
  } elseif ($fxBadge['key'] === 'issues') {
    // FXCheck issues → important signal, full box
    $noteHtml = '<div class="fec-card__note">'.htmlspecialchars((string)$fxLine, ENT_QUOTES, 'UTF-8').'</div>';
  } else {
    // Verified / Mixed / Lowdata → soft freshness label (or nothing if no date)
    // lib v82: the modified date too - "Updated 2 days ago" when the edit is
    // what put the card at the top of Best match.
    // lib v101: with the new lib the freshness travels as data ($fecFresh)
    // and the hub card prints it beside the note - an Issues card keeps its
    // date, and the relative wording is computed in the browser, so a
    // cached page never says "today" about yesterday.
    if (!function_exists('fec_card_freshness_parts')) {
      $freshness = fec_card_freshness_label(isset($set['added']) ? $set['added'] : '', isset($set['modified']) ? $set['modified'] : '');
      if ($freshness !== '') {
        $noteHtml = '<div class="fec-card__note fec-card__note--soft">'.htmlspecialchars($freshness, ENT_QUOTES, 'UTF-8').'</div>';
      }
    }
  }
  $fecFresh = function_exists('fec_card_freshness_parts')
    ? fec_card_freshness_parts(isset($set['added']) ? $set['added'] : '', isset($set['modified']) ? $set['modified'] : '')
    : null;

 $code = (isset($set['code']) && trim((string)$set['code']) !== '') ? trim((string)$set['code']) : '';
 
  // ---- Max cashout inline row (sub-bonus) ----
  // Display rules:
  //   NULL/empty/non-numeric → no row at all (silent)
  //   0 (or 0.00)            → "Max cashout: No cap" (explicit positive signal)
  //   any positive amount    → "Max cashout: $X" using bonus currency
  // ---- Max cashout inline row (sub-bonus) ----
  // Display rules:
  //   NULL/empty/non-numeric → placeholder (invisible, but reserves vertical space)
  //   0 (or 0.00)            → "Max cashout: No cap" (explicit positive signal)
  //   any positive amount    → "Max cashout: $X" using bonus currency
  //
  // The placeholder is critical for visual alignment across cards in the same row:
  // without it, cards with cashout data would be taller than cards without it,
  // and the sections below (meta, facts) would not align horizontally.
  $mxRaw = '';
  if (isset($set['max_cashout']) && trim((string)$set['max_cashout']) !== '') {
    $mxRaw = trim((string)$set['max_cashout']);
  }
  if ($mxRaw !== '' && is_numeric($mxRaw)) {
    if ((float)$mxRaw <= 0) {
      $cashoutHtml = '<div class="fec-card__cashout">Max cashout: <strong>No cap</strong></div>';
    } else {
      $cashoutSym = (isset($set['currency']) && isset($currency[$set['currency']])) ? $currency[$set['currency']] : '$';
      // kind-aware label (abs = denaro, multipli = Nx, non classificato = numero
      // nudo). NB: questo blocco e il gemello nella lib (inline row sub-bonus)
      // sono duplicati storici: tenerli allineati finche non si unificano.
      $cashoutVal = function_exists('fec_vm_cashout_label')
        ? fec_vm_cashout_label($set, $cashoutSym)
        : $cashoutSym . $mxRaw;
      $cashoutHtml = '<div class="fec-card__cashout">Max cashout: <strong>'.htmlspecialchars($cashoutVal, ENT_QUOTES, 'UTF-8').'</strong></div>';
    }
  } else {
    // Invisible placeholder to keep card sections aligned
    $cashoutHtml = '<div class="fec-card__cashout fec-card__cashout--placeholder" aria-hidden="true">&nbsp;</div>';
  }
 
  if ($fec_is_fecx) {

    /* ---- FEC V2.1 — Bonuses Hub card: one shared renderer (see lib) ---- */
    $fecIsExtra   = ($cidRow > 0 && !empty($fecCidSeen[$cidRow]));
    $fecMoreCount = (!$fecIsExtra && $cidRow > 0 && isset($fecCidCount[$cidRow])) ? $fecCidCount[$cidRow] - 1 : 0;
    if ($cidRow > 0) $fecCidSeen[$cidRow] = true;
    $fecChunks[] = array(
      'cid'   => $cidRow,
      'extra' => $fecIsExtra,
      'html'  => fec_render_hub_bonus_card(array(
        'set'=>$set, 'state'=>$state, 'fxBadge'=>$fxBadge,
        'curr'=>$curr, 'perc'=>$perc, 'upto'=>$upto, 'code'=>$code,
        'cashoutHtml'=>$cashoutHtml, 'exclusivePill'=>$exclusivePill,
        'showFxcheck'=>$showFxcheck, 'expiryLabel'=>($state === 'ACTIVE' ? $expiryLabel : ''),
        'noteHtml'=>$noteHtml, 'fec_yes'=>$fec_yes, 'fec_no'=>$fec_no,
        'wrLabel'=>$wrLabel, 'cashTxt'=>$cashTxt, 'typetxt'=>$typetxt,
        'logo'=>$logo, 'url'=>$url, 'ctaLabel'=>$ctaLabel, 'ctaHref'=>$ctaHref,
        'fresh'=>$fecFresh, 'moreCount'=>$fecMoreCount, 'moreOf'=>($fecIsExtra ? $cidRow : 0)
      )),
    );

  } else {

  $output .= '
  <div class="fec-card '.$cardStateClass.' fx--'.htmlspecialchars($fxBadge['key'], ENT_QUOTES, 'UTF-8').'">
 
    <div class="fec-card__head">
      <img class="fec-card__logo"
           src="/cdn-cgi/image/width=60,height=60,quality=90,format=webp/images/casino-logos/'.htmlspecialchars($logo, ENT_QUOTES, 'UTF-8').'"
           alt="'.htmlspecialchars((string)$set['brand'], ENT_QUOTES, 'UTF-8').' logo">
 
      <div class="fec-card__brand">
        <div class="fec-card__brandname">'.htmlspecialchars((string)$set['brand'], ENT_QUOTES, 'UTF-8').'</div>
        <div class="fec-card__type">'.htmlspecialchars((string)$typetxt, ENT_QUOTES, 'UTF-8').'</div>
      </div>
    </div>
 
    <div class="fec-card__bonus">
      <div class="fec-card__amount">'.$curr.htmlspecialchars((string)$set['bonus'], ENT_QUOTES, 'UTF-8').$perc.$upto.'</div>
      '.($code !== ''
        ? '<div class="fec-card__game">Code: <strong>'.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'</strong></div>'
        : '<div class="fec-card__game fec-card__game--empty">No code required</div>'
      ).'
      '.$cashoutHtml.'
    </div>

    <div class="fec-card__meta">
      '.$exclusivePill.'
      '.($showFxcheck
        ? '<span class="fec-card__check fec-check--'.htmlspecialchars($fxBadge['key'], ENT_QUOTES, 'UTF-8').'"
                title="'.htmlspecialchars($fec_tip, ENT_QUOTES, 'UTF-8').'">
            <span class="fec-check__dot" aria-hidden="true"></span>
            <span class="fec-check__txt">'.htmlspecialchars($fxBadge['text'], ENT_QUOTES, 'UTF-8').'</span>
          </span>'
        : ''
      ).'
      '.$expiryPill.'
    </div>

    <div class="fec-card__facts">
      <div class="fec-card__fact">
        <strong>'.htmlspecialchars($wrLabel, ENT_QUOTES, 'UTF-8').'</strong>
        <span>Wager</span>
      </div>

      <div class="fec-card__fact">
        <strong>'.htmlspecialchars($cashTxt, ENT_QUOTES, 'UTF-8').'</strong>
        <span>Cashable</span>
      </div>

      '.$fec_fact.'
    </div>

    '.$noteHtml.'

    <div class="fec-card__cta">
      <a href="'.$url.'" class="fec-btn fec-btn--ghost" aria-label="Details for '.htmlspecialchars((string)$set['brand'], ENT_QUOTES, 'UTF-8').' bonus">Details</a>
      '.fec_render_cta($state, $ctaLabel, $ctaHref, '').'
    </div>

  </div>';

  }
}

// Assemble the hub cards: each casino's first card, then its folded extras
// (hidden until the "+N more" toggle), in page order. Nothing is dropped and
// the "Showing 1-24 of N" line still counts offers.
if (!empty($fecChunks)) {
  $fecExtrasByCid = array();
  foreach ($fecChunks as $ch) {
    if ($ch['extra'] && $ch['cid'] > 0) $fecExtrasByCid[$ch['cid']][] = $ch['html'];
  }
  foreach ($fecChunks as $ch) {
    if ($ch['extra']) continue;
    $output .= $ch['html'];
    if ($ch['cid'] > 0 && !empty($fecExtrasByCid[$ch['cid']])) {
      $output .= implode('', $fecExtrasByCid[$ch['cid']]);
      $fecExtrasByCid[$ch['cid']] = array();
    }
  }
}

// JSON-LD for LIST page (ItemList pointing to DETAILS)
$ctx = fec_list_context_from_request();
$GLOBALS['fec_list_jsonld'] = fec_render_list_itemlist_jsonld(
  $fec_itemlist_urls,
  array(
    'title' => $ctx['title'],
    'per_page' => (int)$limit
  )
);

// Breadcrumbs (landing /bonuses/* only)
$baseHost = rtrim(JUri::getInstance()->toString(array('scheme','host')), '/'); // https://www.freeextrachips.com
$path     = JUri::getInstance()->toString(array('path'));                      // /v2/bonuses/...
$items    = fec_build_breadcrumb_items_from_path($path, $baseHost);


$GLOBALS['fec_breadcrumb_html']  = fec_render_breadcrumb_html($items);

// Hub (FEC V2.1) — the editorial layout neutralises the template's
// .fec-container, so the breadcrumb, like every other hub block, must
// carry its own .wrap to align on the 1280/28 content column. The
// .fecx-scoped CSS owns the look; this only adds the wrapper element.
// (When the sibling com_bonuses pages migrate, widen this condition.)
if ($fec_is_fecx) {
    $GLOBALS['fec_breadcrumb_html'] = '<div class="wrap">' . $GLOBALS['fec_breadcrumb_html'] . '</div>';
}
$GLOBALS['fec_breadcrumb_jsonld'] = fec_render_breadcrumb_jsonld($items);


/* =================================================
 * Globals used by views
 * ================================================= */

$GLOBALS['fec_notice'] = $fec_notice;
$GLOBALS['fec_hide_pagination'] = $hide_pagination;
$GLOBALS['fec_countrycode'] = $countrycode;
$GLOBALS['fec_countryname'] = $countryname;