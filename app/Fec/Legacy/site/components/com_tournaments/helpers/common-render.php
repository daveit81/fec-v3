<?php
defined('_JEXEC') or die('Restricted access');

require_once(JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'countriesarray.php');

$app      = JFactory::getApplication();
$jinput   = $app->input;
$document = JFactory::getDocument();
$db       = JFactory::getDbo();

/**
 * V2 ONLY
 * - No legacy CSS/JS
 * - No ?template=newdesign logic
 * - Output: $layout (table rows)
 */

// Country detection (Cloudflare / GeoIP env)
$countrycode   = strtolower((string) getenv('GEOIP_COUNTRY_CODE'));
$countrycodeup = (string) getenv('GEOIP_COUNTRY_CODE');
$countryname   = isset($countries_array[$countrycodeup]) ? $countries_array[$countrycodeup] : '';

/**
 * Inputs
 * Keep compatibility with old "filter=fr|wf".
 */
$filter = $jinput->get('filter', '', 'CMD'); // fr|wf|''
$q      = trim((string) $jinput->get('q', '', 'STRING'));

// Drawer filters
$status     = (array) $jinput->get('status', array(), 'ARRAY'); // running|starting_soon|upcoming
$type       = (array) $jinput->get('type', array(), 'ARRAY');   // freeroll|with_fee
$entryMax   = $jinput->getFloat('entry_max', null);
$prizeMin   = (int) $jinput->getInt('prize_min', 0);
$endsWithin = $jinput->get('ends_within', '', 'CMD');           // 1h|6h|24h|3d|''
$sort       = $jinput->get('sort', 'ending_soon', 'CMD');       // ending_soon default

// Pagination (Zebra)
require_once JPATH_COMPONENT . DS . 'helpers' . DS . 'paginationnew.php';

// FEC V2.1 — 30 records/page (down from 50). Tighter page for mobile-stack
// table rendering and the "Ending soon" default sort already surfaces the
// most relevant entries on page 1.
$records_per_page = 30;
$pagination = new Zebra_Pagination();
$getpage = ($pagination->get_page() - 1) * $records_per_page;

/**
 * Utility: human-ish time diff for "Starts in / Ends in"
 */
function fec_dateDiff($time1, $time2, $precision = 6) {
  if (!is_int($time1)) $time1 = strtotime($time1);
  if (!is_int($time2)) $time2 = strtotime($time2);

  if ($time1 > $time2) { $t = $time1; $time1 = $time2; $time2 = $t; }

  $intervals = array('year','month','day','hour','min');
  $diffs = array();

  foreach ($intervals as $interval) {
    $ttime = strtotime('+1 ' . $interval, $time1);
    $add = 1; $looped = 0;

    while ($time2 >= $ttime) {
      $add++;
      $ttime = strtotime("+" . $add . " " . $interval, $time1);
      $looped++;
    }

    $time1 = strtotime("+" . $looped . " " . $interval, $time1);
    $diffs[$interval] = $looped;
  }

  $count = 0;
  $times = array();
  foreach ($diffs as $interval => $value) {
    if ($count >= $precision) break;
    if ($value > 0) {
      if ($value != 1) $interval .= "s";
      $times[] = $value . " " . $interval;
      $count++;
    }
  }

  return implode(", ", $times);
}

/**
 * Query tournaments
 */
$query = $db->getQuery(true);
$query
  ->select(array(
    'SQL_CALC_FOUND_ROWS a.*',
    'b.contentid',
    'b.jr_restrictedcountries',
    'b.jr_gourl',
    'b.jr_logosmall',
    'b.jr_logowebp'
  ))
  ->from($db->quoteName('tournaments', 'a'))
  ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')')
  ->where('a.end >= NOW()');

/**
 * Legacy top filter (tabs/route)
 */
if ($filter === 'fr') {
  $query->where('a.fee = 0');
} elseif ($filter === 'wf') {
  $query->where('a.fee > 0');
}

/**
 * Search (q)
 * Matches tournament name, casino, game
 */
if ($q !== '') {
  $qEsc  = $db->escape($q, true);
  $qLike = $db->quote('%' . $qEsc . '%', false);

  $query->where(
    '('
      . 'a.tournament LIKE ' . $qLike
      . ' OR a.casino LIKE ' . $qLike
      . ' OR a.game LIKE ' . $qLike
    . ')'
  );
}

/**
 * Drawer: status[]
 */
if (!empty($status)) {
  $conds = array();

  foreach ($status as $s) {
    switch ($s) {
      case 'running':
        $conds[] = '(NOW() BETWEEN a.start AND a.end)';
        break;

      case 'starting_soon':
        $conds[] = '(a.start > NOW() AND a.start <= DATE_ADD(NOW(), INTERVAL 24 HOUR))';
        break;

      case 'upcoming':
        $conds[] = '(a.start > DATE_ADD(NOW(), INTERVAL 24 HOUR))';
        break;
    }
  }

  if (!empty($conds)) {
    $query->where('(' . implode(' OR ', $conds) . ')');
  }
}

/**
 * Drawer: type[]
 * (works together with legacy filter; if both are set they’ll AND together)
 */
if (!empty($type)) {
  $conds = array();

  if (in_array('freeroll', $type, true)) {
    $conds[] = '(a.fee = 0)';
  }
  if (in_array('with_fee', $type, true)) {
    $conds[] = '(a.fee > 0)';
  }

  if (!empty($conds)) {
    $query->where('(' . implode(' OR ', $conds) . ')');
  }
}

/**
 * Drawer: entry_max
 */
if ($entryMax !== null && $entryMax !== '' && is_numeric($entryMax)) {
  $query->where('a.fee <= ' . (float) $entryMax);
}

/**
 * Drawer: prize_min
 */
if ($prizeMin > 0) {
  $query->where('a.prize >= ' . (int) $prizeMin);
}

/**
 * Drawer: ends_within
 */
if ($endsWithin !== '') {
  switch ($endsWithin) {
    case '1h':
      $query->where('a.end <= DATE_ADD(NOW(), INTERVAL 1 HOUR)');
      break;
    case '6h':
      $query->where('a.end <= DATE_ADD(NOW(), INTERVAL 6 HOUR)');
      break;
    case '24h':
      $query->where('a.end <= DATE_ADD(NOW(), INTERVAL 24 HOUR)');
      break;
    case '3d':
      $query->where('a.end <= DATE_ADD(NOW(), INTERVAL 3 DAY)');
      break;
  }
}

/**
 * Sorting (server-side)
 */
switch ($sort) {
  case 'starting_soon':
    $query->order('a.start ASC, a.end ASC');
    break;

  case 'prize_high':
    $query->order('a.prize DESC, a.end ASC');
    break;

  case 'entry_low':
    $query->order('a.fee ASC, a.end ASC');
    break;

  case 'newest':
    $query->order('a.start DESC, a.end ASC');
    break;

  case 'ending_soon':
  default:
    $query->order('a.end ASC, a.start ASC');
    break;
}

$db->setQuery($query, $getpage, $records_per_page);
$rows = (array) $db->loadObjectList();

$db->setQuery('SELECT FOUND_ROWS();');
$total = (int) $db->loadResult();

// Zebra pagination setup
$pagination->records($total);
$pagination->records_per_page($records_per_page);

/**
 * Build layout rows
 * Output matches the markup your v2 table expects.
 */
$layout = '';
$n = 0;

foreach ($rows as $row) {
  $n++;

  $dnow   = new DateTime();
  $dstart = new DateTime($row->start);
  $dend   = new DateTime($row->end);

  // Format dates (keep your existing style)
  $sdate = date("d M y h:i a", strtotime($row->start));
  $edate = date("d M y h:i a", strtotime($row->end));

  // Start column
  $start = $sdate;
  $startclass = '';
  $startAttrs = '';

  // Running
  if ($dnow >= $dstart) {
    $start = 'Running';
    $startclass = 'run';
  } else {
    // "Starting soon" if within 24h
    $soon = new DateTime();
    $soon->add(new DateInterval('P1D'));

    if ($soon >= $dstart) {
      $start = fec_dateDiff("now", $row->start);
      $startclass = 'imm';
      $startAttrs = ' data-time="' . htmlspecialchars($row->start, ENT_QUOTES, 'UTF-8') . '"';
    }
  }

  // End column
  $end = $edate;
  $endclass = '';
  $endAttrs = '';

  // If running and ends within 2 days -> show countdown
  if ($start === 'Running') {
    $dendMinus = new DateTime($row->end);
    $dendMinus->sub(new DateInterval('P2D'));

    if ($dendMinus <= $dnow) {
      $end = fec_dateDiff("now", $row->end);
      $endclass = 'imme';
      $endAttrs = ' data-time="' . htmlspecialchars($row->end, ENT_QUOTES, 'UTF-8') . '"';
    }
  }

  // Prize formatting
  $prize = is_numeric($row->prize) ? ($row->currency . $row->prize) : $row->prize;

  // Fee formatting (keep your special cid==1000 rule)
  $fee = $row->currency . $row->fee;
  if ((int) $row->cid === 1000) {
    $fee = '&euro;' . $row->fee;
  }

  // Casino review url
  $reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id=' . (int) $row->cid . '&lang=en&view=article');

  // Geo restriction check
  $restricted = false;
  if (!empty($countrycode) && !empty($row->jr_restrictedcountries)) {
    $restricted = fnmatch('*' . $countrycode . '*', $row->jr_restrictedcountries);
  }

  // -----------------------------------------------------------------
  // FEC V2.1 — derived flags for the .tn-* row template
  // -----------------------------------------------------------------
  // Status: 'unavailable' (geoblocked), 'upcoming' (not started yet),
  // or 'join' (running and in-country). Drives both the <tr> class and
  // the CTA cell variant.
  $tnIsRunning = ($start === 'Running');
  $tnStatus    = $restricted ? 'unavailable' : ($tnIsRunning ? 'join' : 'upcoming');

  // Entry "freeroll" detection: drives the green pill in the Entry cell.
  // Cast covers '0', '0.00', '', and numeric 0.
  $tnEntryFree = ((float) $row->fee == 0);

  // Prize "zero" detection: drives the '—' dash + muted style. Handles
  // formatted strings like '€0.00' / '$0' as well as raw numeric 0.
  $prizeRaw     = trim((string) $row->prize);
  $tnPrizeZero  = ($prizeRaw === '' || $prizeRaw === '0'
                  || (is_numeric($prizeRaw) && (float) $prizeRaw == 0));
  // Heuristic for pre-formatted '$0' / '€0.00' strings (currency prefix +
  // a zero amount): strip non-digits except '.' and re-check.
  if (!$tnPrizeZero && !is_numeric($prizeRaw)) {
    $prizeNum = preg_replace('/[^0-9.]/', '', $prizeRaw);
    if ($prizeNum !== '' && (float) $prizeNum == 0) $tnPrizeZero = true;
  }

  // End-cell colour: 'critical' (1-5 mins left), 'soon' (any N mins, > 5).
  // Matches the JSX regex /^[0-9]+\s*mins?$/  with 1-5 -> critical.
  // Strings like "1 hour, 4 mins" do NOT match — the cell stays neutral.
  $tnEndCritical = false;
  $tnEndSoon     = false;
  if (preg_match('/^([0-9]+)\s*min/i', trim($end), $em)) {
    $emins = (int) $em[1];
    if ($emins >= 1 && $emins <= 5) $tnEndCritical = true;
    else                            $tnEndSoon     = true;
  }

  // -----------------------------------------------------------------
  // Casino column inner (link + optional game)
  // -----------------------------------------------------------------
  $game = trim((string) $row->game);

  $casinoHtml = '<a href="' . htmlspecialchars($reviewurl, ENT_QUOTES, 'UTF-8') . '">'
              . htmlspecialchars($row->casino, ENT_QUOTES, 'UTF-8')
              . '</a>';
  if ($game !== '') {
    $casinoHtml .= '<span class="arrow" aria-hidden="true">&rsaquo;</span>'
                .  '<span>' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . '</span>';
  }

  // -----------------------------------------------------------------
  // CTA cell: Join button (running/upcoming, in-country) or Unavailable
  // -----------------------------------------------------------------
  // Inline arrow-up-right SVG (matches mockup Icon name="arrow-up-right").
  $joinArrowSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" '
               . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" '
               . 'stroke-linejoin="round" aria-hidden="true">'
               . '<path d="M7 17 17 7M8 7h9v9"></path></svg>';

  if ($tnStatus === 'unavailable') {
    $ctaHtml = '<span class="tn-unavail">'
             .   '<span class="btn-disabled" aria-disabled="true">Unavailable</span>'
             .   '<span class="why">&#9888; Country restriction</span>'
             . '</span>';
  } else {
    $gaexturl    = addslashes($row->casino . ' Tournaments Play');
    $onclickplay = "ga('send', 'event', 'Tournaments', 'Play Button', '{$gaexturl}')";
    $ctaHtml = '<a href="' . htmlspecialchars($row->jr_gourl, ENT_QUOTES, 'UTF-8') . '" '
             . 'class="tn-join-btn" target="_blank" rel="nofollow" '
             . 'onclick="' . htmlspecialchars($onclickplay, ENT_QUOTES, 'UTF-8') . '">'
             . 'Join ' . $joinArrowSvg
             . '</a>';
  }

  // -----------------------------------------------------------------
  // Start cell: "Running" with live-dot, or upcoming countdown/datetime
  // -----------------------------------------------------------------
  if ($tnIsRunning) {
    $startCellHtml = '<span class="tn-state" data-countdown="start">'
                   .   '<span class="live-dot" aria-hidden="true"></span> Running'
                   . '</span>';
  } else {
    // Upcoming: $start is either a "X hours, Y mins" countdown (with
    // $startAttrs holding data-time for the live JS ticker) or a plain
    // dd Mmm yy h:i a string (no data-time, no ticker).
    $startCellHtml = '<span class="tn-state upcoming"' . $startAttrs . ' data-countdown="start">'
                   .   htmlspecialchars($start, ENT_QUOTES, 'UTF-8')
                   . '</span>';
  }

  // -----------------------------------------------------------------
  // End cell: countdown with critical/soon colouring, or static date
  // -----------------------------------------------------------------
  $endStateClass = '';
  if ($tnEndCritical) $endStateClass = ' critical';
  elseif ($tnEndSoon) $endStateClass = ' soon';
  $endCellHtml = '<span class="tn-end' . $endStateClass . '"' . $endAttrs . ' data-countdown="end">'
               .   htmlspecialchars($end, ENT_QUOTES, 'UTF-8')
               . '</span>';

  // -----------------------------------------------------------------
  // Row assembly
  // -----------------------------------------------------------------
  $trClass = ($tnStatus === 'unavailable') ? ' class="unavailable"' : '';

  $entryDisplay = $tnEntryFree ? 'Freeroll' : $fee;
  $prizeDisplay = $tnPrizeZero ? '&mdash;' : htmlspecialchars($prize, ENT_QUOTES, 'UTF-8');

  $layout .= '<tr' . $trClass . '>
    <td>
      <div class="tn-tname">
        <div class="n">' . htmlspecialchars($row->tournament, ENT_QUOTES, 'UTF-8') . '</div>
        <div class="sub">' . $casinoHtml . '</div>
      </div>
    </td>
    <td><span class="tn-amount' . ($tnEntryFree ? ' free' : '') . '">' . $entryDisplay . '</span></td>
    <td><span class="tn-prize' . ($tnPrizeZero ? ' zero' : '') . '">' . $prizeDisplay . '</span></td>
    <td>' . $startCellHtml . '</td>
    <td>' . $endCellHtml . '</td>
    <td class="tn-cta">' . $ctaHtml . '</td>
  </tr>';
}

// Expose vars used by the view
// ($layout, $total, $pagination, $records_per_page, $getpage)
?>