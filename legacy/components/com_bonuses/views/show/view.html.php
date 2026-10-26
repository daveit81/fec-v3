<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');


/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/


// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class BonusesViewShow extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
	
if (!function_exists('plural')) {
  function plural($amount, $singular = '', $plural = 'es') {
    return ($amount == 1) ? $singular : $plural;
  }
}

$jinput = JFactory::getApplication()->input;
$template = $jinput->get('template', '', 'CMD');
$input = JFactory::getApplication()->input;
$reqType    = $input->getCmd('type', '');
$reqSubtype = $input->getCmd('subtype', '');




require_once JPATH_COMPONENT.DS.'renders'.DS.'common.php';
require_once JPATH_COMPONENT.DS.'renders'.DS.'articles.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonuslabelsarray.php';
$mininav = '';
/**
 * common.php deve aver già settato almeno:
 * $GLOBALS['fec_pageUpdatedIso']
 * (e idealmente anche pill/date, ma li forziamo qui in modo sicuro)
 */
$iso = isset($GLOBALS['fec_pageUpdatedIso']) ? $GLOBALS['fec_pageUpdatedIso'] : '';

// Se hai la helper fec_format_updated() in common.php, usala qui
if ($iso && function_exists('fec_format_updated')) {
  $u = fec_format_updated($iso);

  $GLOBALS['fec_pageUpdatedIso']      = $u['iso'];      // "2026-01-25T21:53:56+00:00"
  $GLOBALS['fec_pageUpdatedDate']     = $u['date'];     // "25 Jan 2026"
  $GLOBALS['fec_pageUpdatedRelative'] = $u['relative']; // "Updated today" ecc.
}

// Se vuoi anche la pill già pronta come HTML (opzionale)
// lib v103 (2026-10-21): the pill comes from fec_render_updated_pill() when
// the lib has it - date on the server, "3 days ago" computed in the browser,
// so a cached page never carries a stale relative label. Older lib: as before.
$rel = isset($GLOBALS['fec_pageUpdatedRelative']) ? $GLOBALS['fec_pageUpdatedRelative'] : '';
$pillTs = ($iso !== '') ? (int)strtotime((string)$iso) : 0;
if ($pillTs > 0 && function_exists('fec_render_updated_pill')) {
  $GLOBALS['fec_pageUpdatedPill'] = fec_render_updated_pill($pillTs);
} else {
  $GLOBALS['fec_pageUpdatedPill'] = $rel
    ? '<span class="fec-pill fec-pill--updated">'.htmlspecialchars($rel, ENT_QUOTES, 'UTF-8').'</span>'
    : '';
}

  $pageUpdatedDate = isset($GLOBALS['fec_pageUpdatedDate']) ? $GLOBALS['fec_pageUpdatedDate'] : '';
  
/** -------------------------------------------------
 * Pagination context (robusto)
 * ------------------------------------------------- */
$page  = 1;
$pages = 1;

if (isset($pagination) && is_object($pagination) && method_exists($pagination, 'get_page')) {
    $page = (int) $pagination->get_page();
} else {
    $page = (int) JFactory::getApplication()->input->getInt('page', 1);
}
$page = max(1, $page);

if (isset($pagination) && is_object($pagination) && method_exists($pagination, 'get_pages')) {
    $pages = (int) $pagination->get_pages();
} else {
    $rpp   = max(1, (int)$records_per_page);
    $pages = (int) max(1, ceil((int)$total / $rpp));
}

$isFirstPage = ($page <= 1);

$rpp  = max(1, (int)$records_per_page);
$from = (($page - 1) * $rpp) + 1;
$to   = min((int)$total, $page * $rpp);

/** -------------------------------------------------
 * TOP content
 * ------------------------------------------------- */
echo isset($GLOBALS['fec_breadcrumb_html']) ? $GLOBALS['fec_breadcrumb_html'] : '';
echo isset($GLOBALS['fec_breadcrumb_jsonld']) ? $GLOBALS['fec_breadcrumb_jsonld'] : '';

echo isset($GLOBALS['fec_list_jsonld']) ? $GLOBALS['fec_list_jsonld'] : '';

if ($isFirstPage) {

    echo article_top();

} else {

    // titolo base (fallback sicuro)
    $titleBase = 'Casino Bonuses';
    if (!empty($reqType)) {
        // $labels arriva da bonuslabelsarray.php dentro articles.php
        $titleBase = isset($labels[$reqType]) ? $labels[$reqType] : $reqType;
    }

    echo '<div class="wrap">
        <h1 class="tcontainer">'
            . htmlspecialchars($titleBase, ENT_QUOTES, 'UTF-8')
            . ' - <span class="page">Page ' . (int)$page . ' of ' . (int)$pages . '</span></h1>
        <p>Viewing '
            . htmlspecialchars($titleBase, ENT_QUOTES, 'UTF-8')
            . ' from ' . (int)$from . ' to ' . (int)$to . '.</p>
    </div>';

    // SEO: titletag + (consigliato) noindex sulle pagine paginate
    $doc = JFactory::getDocument();
    $doc->setTitle($titleBase . ' - Page ' . (int)$page . ' of ' . (int)$pages . ' | FreeExtraChips.com');
    $doc->setMetaData('robots', 'noindex,follow');
}


if ($reqType === 'fs') {
$mininav = '<div class="fec-scope" role="region" aria-label="Free spins scope">
  <div class="fec-scope__inner">
    <nav class="fec-scope__tabs" aria-label="Free spins type">
      <a class="fec-tab '.($reqSubtype === '' ? 'is-active' : '').'" href="'.JRoute::_('index.php?option=com_bonuses&view=show&type=fs').'">All</a>
      <a class="fec-tab '.($reqSubtype === 'nd' ? 'is-active' : '').'" href="'.JRoute::_('index.php?option=com_bonuses&view=show&type=fs&subtype=nd').'">No Deposit</a>
      <a class="fec-tab '.($reqSubtype === 'dr' ? 'is-active' : '').'" href="'.JRoute::_('index.php?option=com_bonuses&view=show&type=fs&subtype=dr').'">Deposit Required</a>
    </nav>
  </div>
</div>';
}

// Results line (2026-10-20): where the reader is in the list and how it is
// ordered - "Showing 1-24 of 1,099 . Best match". It replaces "Updated: <date>
// . Showing: <total> bonuses", which repeated the two facts of the hub meta
// panel just above and called the page total "Showing" (the page shows 24).
// The updated date stays here on paginated pages only, which have no panel.
$sortLabels = array('best' => 'Best match', 'newest' => 'Newest', 'wr_low' => 'Lowest wager',
                    'cashout_high' => 'Highest cashout', 'cashout_low' => 'Lowest cashout');
$sortKey = isset($sort) ? (string)$sort : 'best';
$sortTxt = isset($sortLabels[$sortKey]) ? $sortLabels[$sortKey] : $sortLabels['best'];
if ((int)$total > 0) {
  $rangeTxt = ((int)$total > $rpp)
    ? number_format((int)$from).'&ndash;'.number_format((int)$to)
    : number_format((int)$total);
  $updSeg = (!$isFirstPage && $pageUpdatedDate !== '')
    ? '<span class="fec-minirow__dot" aria-hidden="true">&bull;</span>
      <span class="fec-minirow__item">Updated: <strong>'.htmlspecialchars($pageUpdatedDate, ENT_QUOTES, 'UTF-8').'</strong></span>'
    : '';
  echo '<div class="fec-minirow" role="region" aria-label="Bonus list status">
  <div class="fec-minirow__inner">
    <div class="fec-minirow__left">
      <span class="fec-minirow__item">Showing <strong>'.$rangeTxt.'</strong> of <strong>'.number_format((int)$total).'</strong></span>
      <span class="fec-minirow__dot" aria-hidden="true">&bull;</span>
      <span class="fec-minirow__item">'.htmlspecialchars($sortTxt, ENT_QUOTES, 'UTF-8').'</span>
      '.$updSeg.'
    </div>

    <div class="fec-minirow__right">
      <a class="fec-minirow__link" href="/methodology">How we verify</a>
    </div>
  </div>
</div>
';
}


/*echo '<div class="fec-scope" role="region" aria-label="Free spins scope">
  <div class="fec-scope__inner">
    <nav class="fec-scope__tabs" aria-label="Free spins type">
      <a class="fec-tab is-active" href="#">All</a>
      <a class="fec-tab" href="#">No Deposit</a>
      <a class="fec-tab" href="#">Dep. Required</a>
    </nav>
  </div>
</div>';*/
// Bridge to editorial body (TOC links)
echo isset($GLOBALS['fec_bridge_links']) ? $GLOBALS['fec_bridge_links'] : '';
echo '<div class="listing-results">';

/* Toolbar start */

echo $mininav;

$preset = isset($GLOBALS['fec_preset']) ? (string)$GLOBALS['fec_preset'] : '';

// Stati attuali dei filtri (da JInput, già parsati nel bootstrap)
$activeFx       = isset($fx) ? $fx : 'any';
$activeCashable = isset($cashable) ? $cashable : '';
$activeCode     = isset($codef) ? $codef : '';
$activeSort     = isset($sort) ? $sort : 'best';

$qchipVerified = ($activeFx === 'verified') ? 'true' : 'false';
$qchipCashable = ($activeCashable === 'yes') ? 'true' : 'false';
$qchipCode     = ($activeCode === 'yes') ? 'true' : 'false';
$qchipWrLow    = ($activeSort === 'wr_low') ? 'true' : 'false';

// "Hide unavailable" hint (2026-10-20): says what the chip does. Closed,
// suspended and blacklisted casinos never reach this page (base scope of
// fec_build_query), so "closed/suspended" was a promise about nothing.
$naCountry = isset($GLOBALS['fec_countryname']) ? trim((string)$GLOBALS['fec_countryname']) : '';
$naHint = 'Hides offers not available in '.htmlspecialchars($naCountry !== '' ? $naCountry : 'your country', ENT_QUOTES, 'UTF-8').' and expired ones.';

echo '<div class="fec-toolbar" role="region" aria-label="Bonus toolbar" data-fec-preset="'.htmlspecialchars($preset, ENT_QUOTES, 'UTF-8').'">

  <div class="fec-toolbar__inner">

    <div class="fec-toolbar__left">
      <button class="fec-toolbtn" type="button"
              data-fec-toggle="filters"
              aria-expanded="false"
              aria-controls="fecFilters">
        <span class="fec-ico" aria-hidden="true">☰</span>
        Filters
        <span class="fec-badgecount" data-fec-activecount hidden>0</span>
      </button>
    </div>

    <div class="fec-toolbar__right">
      <div class="fec-quickchips" role="group" aria-label="Quick filters">

        <button class="fec-qchip" type="button"
                data-fec-qchip="fx" data-value="verified"
                aria-pressed="'.$qchipVerified.'">
          <span class="fec-qchip__icon" aria-hidden="true">★</span>
          Verified
        </button>

        <button class="fec-qchip" type="button"
                data-fec-qchip="cashable" data-value="yes"
                aria-pressed="'.$qchipCashable.'">
          Cashable
        </button>

        <button class="fec-qchip" type="button"
                data-fec-qchip="code" data-value="yes"
                aria-pressed="'.$qchipCode.'">
          With code
        </button>

        <button class="fec-qchip" type="button"
                data-fec-qchip="sort" data-value="wr_low"
                aria-pressed="'.$qchipWrLow.'">
          <span class="fec-qchip__icon" aria-hidden="true">↓</span>
          Lowest wager
        </button>

      </div>
    </div>
  </div>

  <!-- Filters panel (modal) -->
  <div id="fecFilters"
       class="fec-filters"
       data-fec-panel="filters"
       hidden>

    <div class="fec-filters__inner">

      <!-- Group: Availability -->
      <div class="fec-fgrp" role="group" aria-label="Availability">
        <div class="fec-fgrp__title">Availability</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="hide_na" data-value="1"
                  aria-pressed="false">
            Hide unavailable
          </button>
        </div>
        <div class="fec-fhint">
          '.$naHint.'
        </div>
      </div>

      <!-- Group: Deal -->
      <div class="fec-fgrp" role="group" aria-label="Deal">
        <div class="fec-fgrp__title">Deal</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="exclusive" data-value="yes"
                  aria-pressed="false">
            Exclusive
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="code" data-value="no"
                  aria-pressed="false">
            No code
          </button>
        </div>
        <div class="fec-fhint">
          Refine by exclusivity or code-free offers.
        </div>
      </div>

      <!-- Group: FXCheck -->
      <div class="fec-fgrp" role="group" aria-label="FXCheck">
        <div class="fec-fgrp__title">FXCheck</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="fx" data-value="mixed"
                  aria-pressed="false">
            Mixed
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="fx" data-value="issues"
                  aria-pressed="false">
            Issues
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="fx" data-value="lowdata"
                  aria-pressed="false">
            Low data
          </button>
        </div>
        <div class="fec-fhint">
          Verified is available as a quick filter above.
        </div>
      </div>

      <!-- Group: Wager -->
      <div class="fec-fgrp" role="group" aria-label="Wager">
        <div class="fec-fgrp__title">Wager max</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="wr_max" data-value="20"
                  aria-pressed="false">
            ≤ 20x
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="wr_max" data-value="30"
                  aria-pressed="false">
            ≤ 30x
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="wr_max" data-value="40"
                  aria-pressed="false">
            ≤ 40x
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="wr_max" data-value="50"
                  aria-pressed="false">
            ≤ 50x
          </button>
        </div>
        <div class="fec-fhint">
          Wager is shown as a multiplier (example: 35x).
        </div>
      </div>

      <!-- Group: Max cashout -->
      <div class="fec-fgrp" role="group" aria-label="Max cashout">
        <div class="fec-fgrp__title">Max cashout</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="cashout" data-value="50"
                  aria-pressed="false">
            ≥ 50
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="cashout" data-value="100"
                  aria-pressed="false">
            ≥ 100
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="cashout" data-value="200"
                  aria-pressed="false">
            ≥ 200
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="cashout" data-value="500"
                  aria-pressed="false">
            ≥ 500
          </button>
        </div>
        <div class="fec-fhint">
          Uses the bonus "max cashout" field when available.
        </div>
      </div>

      <!-- Group: Payments -->
      <div class="fec-fgrp" role="group" aria-label="Payments">
        <div class="fec-fgrp__title">Payments</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="crypto" data-value="yes"
                  aria-pressed="false">
            Supports crypto
          </button>
        </div>
        <div class="fec-fhint">
          Matches if the casino lists BTC/ETH/LTC/USDC/USDT.
        </div>
      </div>

      <!-- Group: Sort (moved here from toolbar) -->
      <div class="fec-fgrp" role="group" aria-label="Sort">
        <div class="fec-fgrp__title">Sort</div>
        <div class="fec-chips">
          <button class="fec-filterchip" type="button"
                  data-fec-chip="sort" data-value="newest"
                  aria-pressed="false">
            Newest
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="sort" data-value="cashout_high"
                  aria-pressed="false">
            Highest cashout
          </button>
          <button class="fec-filterchip" type="button"
                  data-fec-chip="sort" data-value="wr_low"
                  aria-pressed="false">
            Lowest wager
          </button>
        </div>
        <div class="fec-fhint">
          "Best match" is default: offers available to you, latest updated first.
        </div>
      </div>

    </div>

    <!-- Sticky bottom actions -->
    <div class="fec-factions" role="group" aria-label="Filter actions">
      <button class="fec-btn fec-btn--ghost" type="button"
              data-fec-action="reset">
        Reset
      </button>
      <button class="fec-btn fec-btn--primary" type="button"
              data-fec-action="apply">
        Apply filters
      </button>
    </div>

  </div>
</div>

';
  
/* Toolbar end */

// Editor's Picks (sopra il grid, solo se popolato dal bootstrap)
if (!empty($GLOBALS['fec_editor_picks_rows'])) {
  echo fec_render_editor_picks_section(
    $GLOBALS['fec_editor_picks_rows'],
    isset($GLOBALS['fec_countrycode']) ? $GLOBALS['fec_countrycode'] : 'us',
    isset($GLOBALS['fec_countryname']) ? $GLOBALS['fec_countryname'] : 'your country',
    isset($GLOBALS['bonustype']) && is_array($GLOBALS['bonustype']) ? $GLOBALS['bonustype'] : array(),
    isset($GLOBALS['currency']) && is_array($GLOBALS['currency']) ? $GLOBALS['currency'] : array()
  );
}

echo '<div class="fec-grid">';
echo isset($GLOBALS['fec_notice']) ? $GLOBALS['fec_notice'] : '';
echo $output;
echo '</div>';
echo '</div>';


$pagination->padding(false);

$hidePg = !empty($GLOBALS['fec_hide_pagination']) || !empty($GLOBALS['fec_notice']);

if (!$hidePg && $total > $records_per_page) {
  echo $pagination->render();
}

if ($isFirstPage) {

    // Trending this week — global band ranked by real outbound clicks
    // (lib v57). Sits between the list block and the editorial body so
    // it never pushes the grid or its pagination down. Renders '' until
    // enough bonuses clear the 30-click threshold after geo filtering.
    if (function_exists('fec_render_trending_section')) {
      // Scope the band to the page category when the pool allows it; the
      // renderer falls back to type-only, then site-wide, on its own.
      // $labels comes from helpers/bonuslabelsarray.php (required above) and
      // stays the single source of truth for category names.
      echo fec_render_trending_section(
        JFactory::getDbo(),
        isset($GLOBALS['fec_countrycode']) ? $GLOBALS['fec_countrycode'] : 'us',
        isset($GLOBALS['fec_countryname']) ? $GLOBALS['fec_countryname'] : 'your country',
        isset($GLOBALS['bonustype']) && is_array($GLOBALS['bonustype']) ? $GLOBALS['bonustype'] : array(),
        isset($GLOBALS['currency']) && is_array($GLOBALS['currency']) ? $GLOBALS['currency'] : array(),
        3,
        array(
          'type'       => $reqType,
          'subtype'    => $reqSubtype,
          'type_label' => ($reqType !== '' && isset($labels[$reqType])) ? $labels[$reqType] : '',
        )
      );
    }

    echo article_bottom();
}




return;
	}
}
$filter = isset($_GET['filter']) ? $_GET['filter'] : null;
$fq = isset($_GET['fq']) ? $_GET['fq'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
?>