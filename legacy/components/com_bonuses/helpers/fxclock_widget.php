<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * FXClock widget (FEC Tools No. 04) - payouts timed by players.
 *
 * Path: components/com_bonuses/helpers/fxclock_widget.php
 *
 * Server renders the numbers (median, distribution, running clocks) so the
 * section is readable without JavaScript and by crawlers; fxclock.js then
 * hydrates it: ticking clocks, start/stop/backdated panels, receipt,
 * refresh from the cached /fxclock/api/brand/<slug> endpoint.
 *
 * Standalone helper: fec_common_lib.php is NOT touched and NOT required.
 * Reads through the FXClock lib at /home/freeextr/fxclock (outside the
 * docroot); if that lib or the brand row is missing, renders nothing.
 * PHP 5.6 compatible.
 */

// 1.4.1 (2026-10-22): desk notice. When the FXClock lib (1.0.2+) says the
// brand's casino is blacklisted, closed or suspended, the review-page section
// and the ribbon print the notice ("FEC has blacklisted this casino. You can
// still time a withdrawal here...") and keep the clocks: policy is findable,
// warned, allowed. Older lib: no desk_status in the payload, nothing printed.
// 1.4.0 (2026-10-20): homepage. Until a casino has reached its player median
// (ten stopped clocks, the site's own rule) the home shows a one-line strip
// in the review-page ribbon's clothes - counts, latest receipt, the unmet
// rule, two links - instead of the full band with a running clock as its
// headline number. The band returns by itself when brands_with_median > 0.
// Option compact_until_median (default true) on fxc_widget_render_home_band.
if (!defined('FXC_WIDGET_VERSION')) {
  define('FXC_WIDGET_VERSION', '1.4.1');
}

if (!function_exists('fxc_widget_notice_html')) {
  /**
   * 1.4.1: the desk notice from the brand payload as a block ('' when the
   * casino is open or the lib predates desk_status). Inline style is the
   * fallback until fxclock.css carries .fxc-notice.
   */
  function fxc_widget_notice_html($p, $class = 'fxc-notice')
  {
    if (!is_array($p) || empty($p['notice'])) return '';
    $st = isset($p['desk_status']) ? preg_replace('/[^a-z]/', '', strtolower((string) $p['desk_status'])) : '';
    $style = ($st === 'blacklisted')
      ? 'margin:0 0 12px;padding:10px 14px;border:1px solid #9a2828;border-radius:10px;background:rgba(154,40,40,.06);color:#6b1a1a;font-size:.95rem;line-height:1.5'
      : 'margin:0 0 12px;padding:10px 14px;border:1px solid rgba(0,0,0,.14);border-radius:10px;font-size:.95rem;line-height:1.5';
    return '<p class="' . htmlspecialchars($class . ($st !== '' ? ' ' . $class . '--' . $st : ''), ENT_QUOTES, 'UTF-8') . '" role="note" style="' . $style . '">'
         . '<span aria-hidden="true">&#9888;</span> ' . htmlspecialchars((string) $p['notice'], ENT_QUOTES, 'UTF-8') . '</p>';
  }
}

if (!defined('FXC_METHOD_PATH')) {
  define('FXC_METHOD_PATH', '/methodology/fxclock');
}

// 1.3.1: cross-casino register of measured payout times (com_authors page topic
// fast-payouts). Linked from the review-page answer and from the home band.
if (!defined('FXC_INDEX_PATH')) {
  define('FXC_INDEX_PATH', '/online-casinos/fast-payouts');
}

if (!function_exists('fxc_widget_add_assets')) {
  function fxc_widget_add_assets()
  {
    static $done = false;
    if ($done) return;
    $done = true;
    $doc  = JFactory::getDocument();
    $base = JUri::root(true) . '/components/com_bonuses/';
    $doc->addStyleSheet($base . 'css/fxclock.css?v=' . FXC_WIDGET_VERSION);
    $doc->addScript($base . 'js/fxclock.js?v=' . FXC_WIDGET_VERSION);
  }
}

if (!function_exists('fxc_widget_bootstrap')) {
  /**
   * Load the FXClock lib from outside the docroot. Returns true on success.
   */
  function fxc_widget_bootstrap()
  {
    static $ok = null;
    if ($ok !== null) return $ok;
    $ok = false;
    $candidates = array(
      dirname(JPATH_SITE) . '/fxclock/bootstrap.php',
      '/home/freeextr/fxclock/bootstrap.php',
    );
    foreach ($candidates as $p) {
      if (is_readable($p)) {
        require_once $p;
        $ok = function_exists('fxc_brand_by_casino') && function_exists('fxc_brand_payload');
        break;
      }
    }
    return $ok;
  }
}

if (!function_exists('fxc_widget_fmt_min')) {
  /**
   * Minutes to the widget clock format: "42m", "19h 04m", "2d 06h".
   */
  function fxc_widget_fmt_min($min)
  {
    $min = (int) $min;
    if ($min < 60) return $min . 'm';
    $h = (int) floor($min / 60);
    if ($h < 24) return $h . 'h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . 'm';
    $d = (int) floor($h / 24);
    return $d . 'd ' . str_pad((string) ($h % 24), 2, '0', STR_PAD_LEFT) . 'h';
  }
}

if (!function_exists('fxc_widget_labels')) {
  function fxc_widget_labels($set)
  {
    switch ($set) {
      case 'method':
        return array('crypto' => 'Crypto', 'card' => 'Card', 'ewallet' => 'E-wallet', 'bank' => 'Bank transfer');
      case 'amount':
        return array('lt100' => 'Under 100', 'b100_500' => '100&ndash;500', 'b500_2000' => '500&ndash;2,000', 'gt2000' => 'Over 2,000');
      case 'bucket':
        return array('lt24h' => 'Under 24h', 'd1_3' => '1&ndash;3 days', 'd4_7' => '4&ndash;7 days', 'gt7d' => 'Over a week');
      case 'grade':
        return array('A' => 'Stop time locked on-chain', 'B' => 'Deposit verified', 'C' => 'Contact verified', 'D' => 'Anonymous');
    }
    return array();
  }
}

if (!function_exists('fxc_widget_summary')) {
  /**
   * Compact summary for the review page chrome (hero CTA, stats strip cell,
   * FXShield fourth cell). Returns null when the brand is not enabled or the
   * lib is unavailable; otherwise array(enabled, median_min, median_label,
   * stopped, running). Cached per request.
   */
  function fxc_widget_summary($cid)
  {
    static $cache = array();
    $cid = (int) $cid;
    if (array_key_exists($cid, $cache)) return $cache[$cid];
    $cache[$cid] = null;
    if (!fxc_widget_bootstrap()) return null;
    try {
      $brand = fxc_brand_by_casino($cid);
      if (!$brand || (int) $brand['enabled'] !== 1) return null;
      $p = fxc_brand_payload($brand);
    } catch (Exception $e) {
      if (function_exists('fxc_log')) fxc_log('summary error cid=' . $cid . ' :: ' . $e->getMessage());
      return null;
    }
    $cache[$cid] = array(
      'enabled' => true,
      'median_min' => $p['median_min'],
      'median_label' => $p['median_min'] !== null ? fxc_widget_fmt_min($p['median_min']) : '',
      'stopped' => (int) $p['stopped_count'],
      'running' => count($p['running']) + (int) $p['running_more'],
    );
    return $cache[$cid];
  }
}

if (!function_exists('fxc_widget_render_ribbon')) {
  /**
   * Echo a one-line ribbon for the top of the review page (placed right after
   * the stats strip in the override). Server-rendered numbers, two links:
   * "See the clocks" scrolls to #fxclock, "Start a clock" opens the start
   * panel via #fxc-start. Echoes nothing when the brand is not enabled.
   */
  function fxc_widget_render_ribbon($cid, $title = '')
  {
    if (!fxc_widget_bootstrap()) return;
    try {
      $brand = fxc_brand_by_casino((int) $cid);
      if (!$brand || (int) $brand['enabled'] !== 1) return;
      $p = fxc_brand_payload($brand);
    } catch (Exception $e) {
      if (function_exists('fxc_log')) fxc_log('ribbon error cid=' . (int) $cid . ' :: ' . $e->getMessage());
      return;
    }
    fxc_widget_add_assets();
    $esc = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $name = $title !== '' ? $title : $p['brand'];
    $running = count($p['running']) + (int) $p['running_more'];
    $stopped = (int) $p['stopped_count'];

    if ($p['median_min'] !== null) {
      $line = 'Players are timing payouts at ' . $esc($name) . ': median <b>' . $esc(fxc_widget_fmt_min($p['median_min']))
            . '</b> from ' . $stopped . ' stopped clocks'
            . ($running > 0 ? ', <b>' . $running . '</b> running now' : '') . '.';
    } elseif ($stopped > 0 || $running > 0) {
      $line = 'Players are timing payouts at ' . $esc($name) . ': '
            . ($running > 0 ? '<b>' . $running . '</b> clock' . ($running === 1 ? '' : 's') . ' running now, ' : '')
            . $stopped . ' of 10 stopped clocks needed before a median shows.';
    } else {
      $line = 'Withdrawing from ' . $esc($name) . '? Track it. One tap when you request, one tap when the money lands, reminders until it arrives: the clock runs in public.';
    }

    // 1.4.1: a desk notice leads the ribbon line.
    if (!empty($p['notice'])) {
      $line = '<b>' . $esc(preg_replace('/\..*$/s', '.', (string) $p['notice'])) . '</b> ' . $line;
    }
    echo '<div class="wrap"><div class="fxc-ribbon" id="fxcRibbon" data-brand="' . $esc($p['brand']) . '">'
       . '<svg class="fxc-ribbon-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>'
       . '<span class="fxc-ribbon-eyebrow">FXClock</span>'
       . '<span class="fxc-ribbon-text">' . $line . '</span>'
       . '<span class="fxc-ribbon-links">'
       . '<a class="fxc-ribbon-link" href="#fxclock">See the clocks</a>'
       . '<a class="fxc-ribbon-link fxc-ribbon-link-primary" href="#fxc-start">Start a clock</a>'
       . '</span>'
       . '</div></div>' . "\n";
  }
}

if (!function_exists('fxc_widget_country_name')) {
  /**
   * 1.4.0: ISO code to the display name the rest of the site prints
   * ("PL" -> "Poland"), from the com_bonuses countries array; the raw code
   * when the array or the entry is missing.
   */
  function fxc_widget_country_name($cc)
  {
    $cc = strtoupper(trim((string) $cc));
    if ($cc === '') return '';
    static $map = null;
    if ($map === null) {
      $map = (isset($GLOBALS['countries_array']) && is_array($GLOBALS['countries_array'])) ? $GLOBALS['countries_array'] : array();
      if (!$map && defined('JPATH_SITE')) {
        $cf = JPATH_SITE . '/components/com_bonuses/helpers/countriesarray.php';
        if (is_readable($cf)) {
          $countries_array = array();
          include $cf;
          if (isset($countries_array) && is_array($countries_array)) $map = $countries_array;
        }
      }
    }
    if (!isset($map[$cc]) || trim((string) $map[$cc]) === '') return $cc;
    $name = (string) $map[$cc];
    return function_exists('fec_country_display_name') ? fec_country_display_name($name) : $name;
  }
}

if (!function_exists('fxc_widget_render_home_strip')) {
  /**
   * 1.4.0: the homepage FXClock until a casino has a player median - one
   * line in the review-page ribbon's clothes: the catalogue counts, the
   * latest receipt as a link to its casino page, the rule still unmet, two
   * links. No running clock as a headline number: a fifteen-day clock with
   * no median beside it reads as a verdict the site has not issued.
   * Static (no id="fxcHome", no data-api): fxclock.js has nothing to
   * hydrate here, so a feed refresh cannot bring the tiles back.
   */
  function fxc_widget_render_home_strip($f, $empty)
  {
    fxc_widget_add_assets();
    $esc = function ($x) { return htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8'); };
    $base = JUri::root(true);
    $methods = fxc_widget_labels('method');

    if ($empty) {
      $text = 'Players time their own withdrawals on FreeExtraChips: one tap when you request, one when the money lands, '
            . 'and the clock runs in public on the casino review. No clock has been started yet.';
    } else {
      $bits = array();
      $stopped = (int) $f['stopped_total'];
      $running = (int) $f['running_count'];
      if ($stopped > 0) $bits[] = '<b>' . $stopped . '</b> withdrawal' . ($stopped === 1 ? '' : 's') . ' timed to the minute so far';
      if ($running > 0) $bits[] = '<b>' . $running . '</b> clock' . ($running === 1 ? '' : 's') . ' running now';
      $text = 'Players are timing their withdrawals: ' . implode(', ', $bits) . '.';
      if (!empty($f['latest']) && is_array($f['latest'])) {
        $t = reset($f['latest']);
        $href = !empty($t['page_path']) ? $base . '/' . ltrim($t['page_path'], '/') . '#fxclock' : $base . '/online-casinos';
        $m = isset($methods[$t['method']]) ? $methods[$t['method']] : (string) $t['method'];
        $m = html_entity_decode($m, ENT_QUOTES, 'UTF-8');
        $cc = (isset($t['cc']) && $t['cc'] !== null) ? (string) $t['cc'] : '';
        $from = ($cc !== '') ? fxc_widget_country_name($cc) : '';
        $text .= ' Latest: <a class="fxc-strip-latest" href="' . $esc($href) . '"><b>' . $esc(fxc_widget_fmt_min($t['duration_min'])) . '</b> at ' . $esc($t['title']) . '</a>'
               . ($m !== '' ? ', ' . $esc(strtolower($m)) : '')
               . ($from !== '' ? ', from ' . $esc($from) : '') . '.';
      }
      $text .= ' A casino gets its median from ten stopped clocks; none has ten yet.';
    }

    echo '<section class="section fxc-home-strip" id="fxcHomeStrip" aria-label="FXClock">' . "\n";
    echo '<style>.fxc-home-strip .fxc-ribbon{margin:0}.fxc-home-strip .fxc-strip-latest{color:inherit;text-decoration:underline;text-underline-offset:2px}</style>' . "\n";
    echo '<div class="wrap"><div class="fxc-ribbon">'
       . '<svg class="fxc-ribbon-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>'
       . '<span class="fxc-ribbon-eyebrow">FXClock</span>'
       . '<span class="fxc-ribbon-text">' . $text . '</span>'
       . '<span class="fxc-ribbon-links">'
       . '<a class="fxc-ribbon-link fxc-ribbon-link-primary" href="' . $esc($base . FXC_INDEX_PATH) . '">FEC Payout Index: fastest payouts, measured</a>'
       . '<a class="fxc-ribbon-link" href="' . $esc($base . FXC_METHOD_PATH) . '">How FXClock works</a>'
       . '</span>'
       . '</div></div>' . "\n";
    echo '</section>' . "\n";
  }
}

if (!function_exists('fxc_widget_render_home_band')) {
  /**
   * Homepage band: catalogue-wide live facts (clocks running now, brands,
   * latest three receipts as tiles) with links to the methodology page and
   * the casino directory. Never a cross-brand median. Server-rendered from
   * fxc_home_feed(); fxclock.js refreshes it from /fxclock/api/feed.
   * Options: hide_when_empty (default false: an invitation band is shown),
   * compact_until_median (1.4.0, default true: while no casino has a
   * player median the one-line strip renders instead of this band).
   */
  function fxc_widget_render_home_band($opts = array())
  {
    if (!fxc_widget_bootstrap() || !function_exists('fxc_home_feed')) return;
    $opts = array_merge(array('hide_when_empty' => false, 'limit' => 3, 'compact_until_median' => true), is_array($opts) ? $opts : array());
    try {
      $f = fxc_home_feed((int) $opts['limit']);
    } catch (Exception $e) {
      if (function_exists('fxc_log')) fxc_log('home band error :: ' . $e->getMessage());
      return;
    }
    $empty = ($f['running_count'] === 0 && $f['stopped_total'] === 0);
    if ($empty && !empty($opts['hide_when_empty'])) return;

    // 1.4.0: the full band is for the day a casino has a median to show.
    if (!empty($opts['compact_until_median']) && (int) $f['brands_with_median'] === 0) {
      fxc_widget_render_home_strip($f, $empty);
      return;
    }

    fxc_widget_add_assets();
    $esc = function ($x) { return htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8'); };
    $base = JUri::root(true);
    $methods = fxc_widget_labels('method');
    $grades  = fxc_widget_labels('grade');

    if ($empty) {
      $lead = 'Players time their own withdrawals on FreeExtraChips. One tap when you request, one tap when the money lands: the clock runs in public, on the casino review.';
    } else {
      $parts = array();
      if ($f['running_count'] > 0) {
        $parts[] = '<b>' . (int) $f['running_count'] . '</b> clock' . ($f['running_count'] === 1 ? '' : 's') . ' running now across <b>'
                 . (int) $f['running_brands'] . '</b> casino' . ($f['running_brands'] === 1 ? '' : 's');
      }
      if ($f['stopped_total'] > 0) {
        $parts[] = '<b>' . (int) $f['stopped_total'] . '</b> withdrawal' . ($f['stopped_total'] === 1 ? '' : 's') . ' timed to the minute';
      }
      if ($f['brands_with_median'] > 0) {
        $parts[] = '<b>' . (int) $f['brands_with_median'] . '</b> casino' . ($f['brands_with_median'] === 1 ? '' : 's') . ' with a player-timed median';
      }
      $lead = 'Players are timing their withdrawals: ' . implode(', ', $parts) . '.';
    }

    echo '<section class="section fxc-home" id="fxcHome" data-api="' . $esc($base) . '/fxclock/api">' . "\n";
    echo '<div class="wrap">' . "\n";
    echo '<div class="sec-head"><div class="left">'
       . '<span class="eyebrow">FXClock</span>'
       . '<h2 class="sec-title">Payouts timed by players, live</h2>'
       . '<p class="sec-sub" id="fxcHomeLead">' . $lead . '</p>'
       . '</div></div>' . "\n";

    echo '<div class="fxc-home-group" id="fxcHomeRunningWrap"' . (count($f['running']) ? '' : ' hidden') . '>';
    echo '<div class="fxc-home-group-l">Running now</div>';
    echo '<div class="fxc-home-tiles fxc-home-tiles--running" id="fxcHomeRunning">';
    $now = time();
    foreach ($f['running'] as $r) {
      $href = !empty($r['page_path']) ? $base . '/' . ltrim($r['page_path'], '/') . '#fxclock' : $base . '/online-casinos';
      $elapsedMin = max(0, (int) floor(($now - (int) $r['oldest_started_at']) / 60));
      echo '<a class="fxc-home-tile fxc-home-tile--running" href="' . $esc($href) . '" data-start="' . (int) $r['oldest_started_at'] . '">'
         . '<span class="fxc-home-tile-time"><span class="fxc-dot" aria-hidden="true"></span><span class="fxc-time">' . $esc(fxc_widget_fmt_min($elapsedMin)) . '</span></span>'
         . '<span class="fxc-home-tile-brand">' . $esc($r['title']) . '</span>'
         . '<span class="fxc-home-tile-sub">' . (int) $r['count'] . ' clock' . ((int) $r['count'] === 1 ? '' : 's') . ' running' . ((int) $r['count'] > 1 ? ', longest shown' : '') . '</span>'
         . '</a>';
    }
    echo '</div></div>' . "\n";

    echo '<div class="fxc-home-group" id="fxcHomeLatestWrap"' . (count($f['latest']) ? '' : ' hidden') . '>';
    echo '<div class="fxc-home-group-l">Latest receipts</div>';
    echo '<div class="fxc-home-tiles" id="fxcHomeTiles">';
    foreach ($f['latest'] as $t) {
      $href = !empty($t['page_path']) ? $base . '/' . ltrim($t['page_path'], '/') . '#fxclock' : $base . '/online-casinos';
      $m = isset($methods[$t['method']]) ? $methods[$t['method']] : $t['method'];
      $g = isset($grades[$t['grade']]) ? $grades[$t['grade']] : '';
      echo '<a class="fxc-home-tile" href="' . $esc($href) . '">'
         . '<span class="fxc-home-tile-time">' . $esc(fxc_widget_fmt_min($t['duration_min'])) . '</span>'
         . '<span class="fxc-home-tile-brand">' . $esc($t['title']) . '</span>'
         . '<span class="fxc-home-tile-sub">' . $m . ($t['cc'] ? ', from <span class="fxc-cc" data-cc="' . $esc($t['cc']) . '">' . $esc($t['cc']) . '</span>' : '') . '</span>'
         . '<span class="fxc-grade" title="' . $esc($g) . '">' . $esc($t['grade']) . '</span>'
         . '</a>';
    }
    echo '</div></div>' . "\n";

    echo '<div class="fxc-home-links">'
       . '<a class="fxc-ribbon-link fxc-ribbon-link-primary" href="' . $esc($base . FXC_INDEX_PATH) . '">FEC Payout Index: fastest payouts, measured</a>'
       . '<a class="fxc-ribbon-link" href="' . $esc($base) . '/online-casinos">Casino reviews</a>'
       . '<a class="fxc-ribbon-link" href="' . $esc($base . FXC_METHOD_PATH) . '">How FXClock works</a>'
       . '</div>' . "\n";
    echo '<p class="fxc-foot">Clocks are started and stopped by players only. Medians are per casino and shown only from ten stopped clocks; nothing here is a rating.</p>' . "\n";
    echo '</div></section>' . "\n";
  }
}

if (!function_exists('fxc_widget_desk_test')) {
  /**
   * Latest desk payout test for the casino (jos_fec_payout_tests), read only.
   * Returns array(measured_hours, declared_hours, test_date) or null.
   */
  function fxc_widget_desk_test($cid)
  {
    try {
      $r = fxc_row('SELECT measured_hours, declared_hours, test_date FROM ' . fxc_t('fec_payout_tests')
        . ' WHERE casino_id = ? ORDER BY test_date DESC, id DESC LIMIT 1', 'i', array((int) $cid));
    } catch (Exception $e) {
      return null;
    }
    if (!$r || $r['measured_hours'] === null) return null;
    return array(
      'measured_hours' => (float) $r['measured_hours'],
      'declared_hours' => $r['declared_hours'] === null ? null : (float) $r['declared_hours'],
      'test_date' => $r['test_date'],
    );
  }
}

if (!function_exists('fxc_widget_fmt_hours')) {
  function fxc_widget_fmt_hours($h)
  {
    return rtrim(rtrim(number_format((float) $h, 1), '0'), '.') . 'h';
  }
}

if (!function_exists('fxc_widget_render')) {
  /**
   * Echo the FXClock section for a review page. $cid is the jos_content id,
   * $title the casino display name. Echoes nothing when the brand is not
   * enabled or the FXClock lib is unavailable.
   */
  function fxc_widget_render($cid, $title = '')
  {
    if (!fxc_widget_bootstrap()) return;

    try {
      $brand = fxc_brand_by_casino((int) $cid);
      if (!$brand || (int) $brand['enabled'] !== 1) return;
      $p = fxc_brand_payload($brand);
    } catch (Exception $e) {
      if (function_exists('fxc_log')) fxc_log('widget error cid=' . (int) $cid . ' :: ' . $e->getMessage());
      return;
    }

    fxc_widget_add_assets();

    $esc = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $methods = fxc_widget_labels('method');
    $amounts = fxc_widget_labels('amount');
    $buckets = fxc_widget_labels('bucket');
    $grades  = fxc_widget_labels('grade');
    $slug    = $p['brand'];
    $name    = $title !== '' ? $title : $slug;
    $json    = json_encode($p);

    echo '<section class="section fxc-section" id="fxclock" data-brand="' . $esc($slug) . '"'
       . ' data-name="' . $esc($name) . '" data-api="' . $esc(JUri::root(true)) . '/fxclock/api"'
       . ' data-threshold="' . (int) $p['threshold'] . '" data-payload="' . $esc($json) . '">' . "\n";
    echo '<div class="wrap">' . "\n";

    // Search-facing head: the question people type while they wait for a payout,
    // answered with the player median when it exists, the desk test otherwise.
    $desk = fxc_widget_desk_test((int) $cid);
    $stoppedN = (int) $p['stopped_count'];
    if ($p['median_min'] !== null) {
      $answer = 'Players who timed their own withdrawals at ' . $esc($name) . ' report a median of <b>'
              . $esc(fxc_widget_fmt_min($p['median_min'])) . '</b> from request to money received, from ' . $stoppedN . ' stopped clocks.';
      if ($desk !== null) $answer .= ' Our desk&rsquo;s last payout test took ' . $esc(fxc_widget_fmt_hours($desk['measured_hours'])) . '.';
    } elseif ($desk !== null) {
      $answer = 'Our desk&rsquo;s last payout test at ' . $esc($name) . ' took <b>' . $esc(fxc_widget_fmt_hours($desk['measured_hours'])) . '</b>'
              . ($desk['declared_hours'] !== null ? ' against a declared ' . $esc(fxc_widget_fmt_hours($desk['declared_hours'])) : '') . '.'
              . ' Players have not timed enough withdrawals yet for a median: ' . $stoppedN . ' of 10 stopped clocks.';
    } else {
      $answer = 'No player has timed a withdrawal at ' . $esc($name) . ' yet, and there is no desk test on file. The first ten clocks set the number.';
    }
    // 1.3.1: hand-off to the cross-casino register. A casino with a desk test or
    // a player median is in it, so "how it compares" is literal; otherwise the
    // register is offered as the place where every timed withdrawal lives.
    $indexHref = $esc(JUri::root(true) . FXC_INDEX_PATH);
    if ($p['median_min'] !== null || $desk !== null) {
      $answer .= ' See how ' . $esc($name) . ' compares in the <a class="fxc-answer-link" href="' . $indexHref . '">FEC Payout Index</a>, every withdrawal we have timed, fastest first.';
    } else {
      $answer .= ' Every withdrawal we have timed so far, fastest first: the <a class="fxc-answer-link" href="' . $indexHref . '">FEC Payout Index</a>.';
    }
    echo '<div class="sec-head"><div class="left">'
       . '<span class="eyebrow">FXClock</span>'
       . '<h2 class="sec-title">How long does ' . $esc($name) . ' take to pay out?</h2>'
       . fxc_widget_notice_html($p)
       . '<p class="fxc-answer">' . $answer . '</p>'
       . '<p class="sec-sub">Waiting for a withdrawal right now? Start a clock and track it: one tap when you request, one when the money lands, '
       . 'reminders on day 3, 7 and 15. Already paid? Log it. Every clock runs in public on this page.</p>'
       . '</div></div>' . "\n";

    echo '<div class="fxc-grid">' . "\n";

    // --- Median block -------------------------------------------------
    echo '<div class="fxc-stat" id="fxcStat">';
    echo '<div class="fxc-label">Median payout, timed by players</div>';
    if ($p['median_min'] !== null) {
      echo '<div class="fxc-big" data-min="' . (int) $p['median_min'] . '">' . $esc(fxc_widget_fmt_min($p['median_min'])) . '</div>';
      echo '<div class="fxc-muted">from ' . (int) $p['stopped_count'] . ' stopped clocks</div>';
      $max = 1;
      foreach ($p['buckets'] as $v) { if ((int) $v > $max) $max = (int) $v; }
      echo '<div class="fxc-bars">';
      foreach ($buckets as $k => $label) {
        $v = isset($p['buckets'][$k]) ? (int) $p['buckets'][$k] : 0;
        $w = (int) round($v / $max * 100);
        echo '<div class="fxc-bar"><span class="fxc-bar-l">' . $label . '</span>'
           . '<span class="fxc-bar-t"><span class="fxc-bar-f" style="width:' . $w . '%"></span></span>'
           . '<span class="fxc-bar-n">' . $v . '</span></div>';
      }
      echo '</div>';
    } else {
      echo '<div class="fxc-big fxc-big-empty">&mdash;</div>';
      echo '<div class="fxc-muted">Not enough stopped clocks yet to show a median. Needs '
         . (int) $p['threshold'] . ', has ' . (int) $p['stopped_count'] . '.</div>';
    }
    echo '<div class="fxc-counts">';
    echo '<div>' . (int) $p['unresolved_count'] . ' clocks never stopped, closed as unresolved after 30 days.</div>';
    if ((int) $p['refused_count'] > 0)  echo '<div class="fxc-brick">' . (int) $p['refused_count'] . ' closed as refused.</div>';
    if ((int) $p['backdated_count'] > 0) echo '<div>' . (int) $p['backdated_count'] . ' past payouts logged at grade D, kept out of the median.</div>';
    if ((int) $p['hidden_count'] > 0)    echo '<div>' . (int) $p['hidden_count'] . ' excluded by automatic checks.</div>';
    echo '</div>';
    echo '</div>' . "\n";

    // --- Running list -------------------------------------------------
    echo '<div class="fxc-running" id="fxcRunning">';
    echo '<div class="fxc-label">Running now</div>';
    if (!count($p['running'])) {
      echo '<p class="fxc-muted fxc-empty">No clocks running. Start one the moment you request a withdrawal.</p>';
    } else {
      $now = time();
      echo '<ul class="fxc-list">';
      foreach ($p['running'] as $c) {
        $elapsed = max(0, (int) floor(($now - (int) $c['started_at']) / 60));
        $m = isset($methods[$c['method']]) ? $methods[$c['method']] : $c['method'];
        $a = ($c['amount_band'] !== null && isset($amounts[$c['amount_band']])) ? $amounts[$c['amount_band']] : 'amount not given';
        $g = isset($grades[$c['grade']]) ? $grades[$c['grade']] : '';
        $cc = (isset($c['cc']) && $c['cc'] !== null && $c['cc'] !== '') ? $c['cc'] : '';
        echo '<li class="fxc-row" data-id="' . $esc($c['public_id']) . '" data-start="' . (int) $c['started_at'] . '">'
           . '<span class="fxc-dot" aria-hidden="true"></span>'
           . '<span class="fxc-row-main"><span class="fxc-time">' . $esc(fxc_widget_fmt_min($elapsed)) . '</span>'
           . '<span class="fxc-row-sub">' . $m . ', ' . $a
           . ($cc !== '' ? ', from <span class="fxc-cc" data-cc="' . $esc($cc) . '">' . $esc($cc) . '</span>' : '')
           . '</span></span>'
           . '<span class="fxc-grade" title="' . $esc($g) . '">' . $esc($c['grade']) . '</span>'
           . '</li>';
      }
      echo '</ul>';
      if ((int) $p['running_more'] > 0) {
        echo '<p class="fxc-muted">+' . (int) $p['running_more'] . ' more running.</p>';
      }
    }
    echo '</div>' . "\n";

    echo '</div>' . "\n"; // .fxc-grid

    // --- Actions + panel host (panels are built by fxclock.js) ---------
    echo '<div class="fxc-actions" id="fxcActions">'
       . '<button type="button" class="btn btn-primary fxc-btn" data-fxc="start">Start a clock</button>'
       . '<button type="button" class="btn fxc-btn fxc-btn-ghost" data-fxc="past">Log a past payout</button>'
       . '</div>' . "\n";
    echo '<div class="fxc-panel" id="fxcPanel" hidden></div>' . "\n";

    echo '<p class="fxc-foot">Clocks are started and stopped by players only. The FEC desk cannot start, stop or edit one. '
       . 'Verification grades: A stop time locked on-chain, B deposit verified, C contact verified, D anonymous. '
       . '<a class="fxc-foot-link" href="' . $esc(JUri::root(true) . FXC_METHOD_PATH) . '">How FXClock works</a></p>' . "\n";

    echo '</div>' . "\n"; // .wrap
    echo '</section>' . "\n";
  }
}