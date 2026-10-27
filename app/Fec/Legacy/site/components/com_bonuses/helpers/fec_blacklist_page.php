<?php
/**
 * @package     FreeExtraChips
 * @subpackage  com_bonuses/helpers
 *
 * FEC - Blacklist page renderer (casino reviews, catid 8)
 * File:    components/com_bonuses/helpers/fec_blacklist_page.php
 * Version: 1.0.0 (2026-10-15)
 *
 * A blacklisted casino is not reviewed, it is documented. When
 * fec_decide_casino_state() returns BLACKLISTED, the article override
 * (templates/fecv2/html/com_content/article/default.php) calls
 * fec_bl_render_page() instead of the standard review pipeline.
 *
 * The page KEEPS:
 *   - the state banner (title + jr_blreason + jr_blexplanation, lib helper)
 *   - an identity hero: badge, name, one-line reason, a 3-row facts panel
 *     (launched / operator / jurisdiction) in place of the Frank Score panel
 *   - a 4-cell record strip: status, player reports, clocks (or restricted
 *     countries), last update - no ranking, no stars, no sentiment
 *   - the article body (the editorial record), rendered through
 *     fec_render_casino_intro() so hub-longform TOCs keep working
 *   - a reduced sidebar: status, record, what to do with money on the site,
 *     review conditions - no outbound link to the casino anywhere
 *   - the author card
 *   - player reports as evidence (text only, no stars, no write-a-review)
 *   - the FXClock section (player-timed withdrawals are evidence)
 *   - the sister-casino map (risk travels across a group)
 *
 * The page DROPS: Frank Score, ranking, sentiment bar, review stars, bonus
 * strip, FXRate, comparator launcher, desk withdrawal section, jackpots,
 * tournaments, other casinos, the review form, the Play and Website links.
 *
 * Head: title "<Casino>: Blacklisted | FreeExtraChips", description from
 * jr_blreason, canonical/OG/Twitter via fec_render_casino_head_meta(),
 * JSON-LD as Article (no Review, no rating). Robots: index by default,
 * FEC_BL_NOINDEX = true switches to noindex,follow.
 *
 * Standalone: reads the view model built by fec_common_lib and never
 * patches it. PHP 5.6 safe. The caller guards the include (is_readable +
 * function_exists) and wraps the call in try/catch.
 */
defined('_JEXEC') or die;

if (!defined('FEC_BL_VERSION'))      define('FEC_BL_VERSION', '1.0.0');
if (!defined('FEC_BL_NOINDEX'))      define('FEC_BL_NOINDEX', false);
if (!defined('FEC_BL_SHOW_FXCLOCK')) define('FEC_BL_SHOW_FXCLOCK', true);
if (!defined('FEC_BL_SHOW_SISTERS')) define('FEC_BL_SHOW_SISTERS', true);
if (!defined('FEC_BL_SITE_NAME'))    define('FEC_BL_SITE_NAME', 'FreeExtraChips');
if (!defined('FEC_BL_SITE_URL'))     define('FEC_BL_SITE_URL', 'https://www.freeextrachips.com');


if (!function_exists('fec_bl_esc')) {
  function fec_bl_esc($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
  }
}


if (!function_exists('fec_bl_add_assets')) {
  /**
   * Page-specific CSS, declared once in <head>. Everything else comes from
   * fec-edition.css (cr-hero, cr-stats-strip, cr-sidebar, cr-reviews).
   */
  function fec_bl_add_assets() {
    static $done = false;
    if ($done) return;
    $done = true;

    $css = '.fec-bl-card .cr-hero-left{align-self:center}'
         . '.fec-bl-lede{margin:12px 0 0;max-width:64ch;color:#3a3732;line-height:1.5}'
         . '.fec-bl-btn.btn-primary{background:#9a2828;border-color:#9a2828;color:#fff}'
         . '.fec-bl-btn.btn-primary:hover{background:#7e2020;border-color:#7e2020}'
         . '.fec-bl-facts{align-self:stretch;display:flex;flex-direction:column;justify-content:center;gap:2px;'
         .   'background:rgba(255,255,255,.55);border:1px solid rgba(154,40,40,.16);border-radius:14px;padding:18px 22px}'
         . '.fec-bl-facts .row{display:flex;justify-content:space-between;align-items:baseline;gap:16px;'
         .   'padding:9px 0;border-bottom:1px dashed rgba(0,0,0,.09)}'
         . '.fec-bl-facts .row:last-child{border-bottom:0}'
         . '.fec-bl-facts .k{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#6b6b66;white-space:nowrap}'
         . '.fec-bl-facts .v{font-weight:600;text-align:right;color:#14110d}'
         . '.fec-bl-facts .v.danger{color:#9a2828}'
         . '.fec-bl-facts .note{margin:10px 0 0;font-size:.8rem;color:#6b6b66;line-height:1.4}'
         . '.fec-bl-strip .cr-stats-row{grid-template-columns:repeat(4,minmax(0,1fr))}'
         . '.fec-bl-strip .v.danger{color:#9a2828}'
         . '.fec-bl-steps{margin:0;padding-left:18px}'
         . '.fec-bl-steps li{margin:6px 0;line-height:1.45}'
         . '.fec-bl-sidebar .cr-side-row .v.plain{word-break:break-all}'
         . '.fec-bl-reports .cr-review-item .cr-review-meta{display:flex;align-items:center;gap:8px}'
         . '.fec-bl-reports .sec-sub{max-width:70ch}'
         . '.fec-bl-empty{color:#6b6b66;margin:0}'
         . '@media (max-width:720px){.fec-bl-facts{margin-top:16px}.fec-bl-strip .cr-stats-row{grid-template-columns:repeat(2,minmax(0,1fr))}}';

    try {
      $doc = JFactory::getDocument();
      if ($doc && method_exists($doc, 'addStyleDeclaration')) {
        $doc->addStyleDeclaration('/* fec_blacklist_page v' . FEC_BL_VERSION . ' */' . $css);
        return;
      }
    } catch (Exception $e) {
      // fall through to inline
    }
    echo '<style>' . $css . '</style>';
  }
}


if (!function_exists('fec_bl_reason_parts')) {
  /**
   * Split jr_blreason into a "since" label and the plain reason.
   *   "BLACKLISTED (Oct 2026): unpaid withdrawals ..." ->
   *   array('since' => 'Oct 2026', 'reason' => 'Unpaid withdrawals ...')
   * A reason without the prefix comes back unchanged with since = ''.
   */
  function fec_bl_reason_parts($reason) {
    $reason = trim((string) $reason);
    $since  = '';
    if ($reason !== '' && preg_match('/^\s*blacklisted\s*(?:\(([^)]*)\))?\s*:\s*(.*)$/is', $reason, $m)) {
      $since  = isset($m[1]) ? trim($m[1]) : '';
      $reason = isset($m[2]) ? trim($m[2]) : '';
    }
    if ($reason !== '') {
      if (function_exists('mb_substr')) {
        $reason = mb_strtoupper(mb_substr($reason, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($reason, 1, mb_strlen($reason, 'UTF-8'), 'UTF-8');
      } else {
        $reason = ucfirst($reason);
      }
    }
    return array('since' => $since, 'reason' => $reason);
  }
}


if (!function_exists('fec_bl_launch_label')) {
  /** "July 2026" from jr_established; falls back to the year; '' when unknown. */
  function fec_bl_launch_label($vm) {
    $raw = isset($vm['established_raw']) ? trim((string) $vm['established_raw']) : '';
    if ($raw !== '' && $raw !== '0000-00-00 00:00:00' && $raw !== '0000-00-00') {
      $ts = strtotime($raw);
      if ($ts !== false && $ts > 0) return date('F Y', $ts);
    }
    return isset($vm['established_year']) ? trim((string) $vm['established_year']) : '';
  }
}


if (!function_exists('fec_bl_head')) {
  /**
   * Title, description, canonical, OG, Twitter via the lib's head-meta
   * builder (caller templates win over its state branching), JSON-LD as
   * Article, optional noindex. Mirrors the emission code of the override.
   */
  function fec_bl_head($vm, $stateData) {
    $doc = JFactory::getDocument();
    if (!$doc) return;

    $parts  = fec_bl_reason_parts(isset($stateData['reason']) ? $stateData['reason'] : '');
    $reason = $parts['reason'];

    $descTpl = ($reason !== '')
      ? '%title% is blacklisted by %site%: ' . lcfirst(rtrim($reason, '.')) . '. Read the record before you deposit.'
      : '%title% is blacklisted by %site%: documented player harm and an operator that cannot be verified or reached. Read the record before you deposit.';

    $meta = array();
    if (function_exists('fec_render_casino_head_meta')) {
      $meta = fec_render_casino_head_meta($vm, array(
        'titleTemplate' => '%title%: Blacklisted | %site%',
        'descTemplate'  => $descTpl,
      ));
    }

    $title     = isset($vm['title']) ? (string) $vm['title'] : '';
    $canonical = '';
    if (is_array($meta) && !empty($meta)) {
      if (!empty($meta['title']))       $doc->setTitle($meta['title']);
      if (!empty($meta['description'])) $doc->setDescription($meta['description']);
      if (!empty($meta['canonical'])) {
        $canonical = (string) $meta['canonical'];
        $doc->addCustomTag('<link rel="canonical" href="' . fec_bl_esc($canonical) . '" />');
      }
      if (!empty($meta['og']) && is_array($meta['og'])) {
        foreach ($meta['og'] as $k => $v) {
          if ($v === '') continue;
          $doc->addCustomTag('<meta property="' . fec_bl_esc($k) . '" content="' . fec_bl_esc($v) . '" />');
        }
      }
      if (!empty($meta['twitter']) && is_array($meta['twitter'])) {
        foreach ($meta['twitter'] as $k => $v) {
          if ($v !== '') $doc->setMetaData($k, $v);
        }
      }
    } else {
      $doc->setTitle($title . ': Blacklisted | ' . FEC_BL_SITE_NAME);
    }

    if (FEC_BL_NOINDEX) {
      $doc->setMetaData('robots', 'noindex, follow');
    }

    // JSON-LD: an Article about the casino. No Review, no rating - a
    // blacklist entry with a star rating is a contradiction.
    if (!empty($vm['author']) && is_array($vm['author']) && !empty($vm['author']['name'])) {
      $authorNode = array('@type' => 'Person', 'name' => (string) $vm['author']['name']);
      if (!empty($vm['author']['slug']) && class_exists('JRoute')) {
        $authorNode['url'] = rtrim(FEC_BL_SITE_URL, '/')
          . JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode((string) $vm['author']['slug']));
      }
      if (!empty($vm['author']['role'])) $authorNode['jobTitle'] = (string) $vm['author']['role'];
    } else {
      $authorNode = array('@type' => 'Organization', 'name' => FEC_BL_SITE_NAME, 'url' => FEC_BL_SITE_URL);
    }

    $ld = array(
      '@context'  => 'https://schema.org',
      '@type'     => 'Article',
      'headline'  => $title . ': blacklisted by ' . FEC_BL_SITE_NAME,
      'about'     => array('@type' => 'Organization', 'name' => $title),
      'author'    => $authorNode,
      'publisher' => array('@type' => 'Organization', 'name' => FEC_BL_SITE_NAME, 'url' => FEC_BL_SITE_URL),
    );
    if ($canonical !== '') {
      $ld['mainEntityOfPage'] = $canonical;
      $ld['url'] = $canonical;
    }
    if (!empty($meta['description'])) $ld['description'] = (string) $meta['description'];
    if (!empty($vm['created']) && $vm['created'] !== '0000-00-00 00:00:00') {
      try { $ld['datePublished'] = (new DateTime($vm['created']))->format('Y-m-d'); } catch (Exception $e) {}
    }
    if (!empty($vm['modified']) && $vm['modified'] !== '0000-00-00 00:00:00') {
      try { $ld['dateModified'] = (new DateTime($vm['modified']))->format('Y-m-d'); } catch (Exception $e) {}
    }
    $json = json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (is_string($json) && $json !== '') {
      $doc->addCustomTag('<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>');
    }
  }
}


if (!function_exists('fec_bl_render_hero')) {
  /**
   * Breadcrumb + hero card. Left: badge, eyebrow, chip + name, one-line
   * reason, in-page actions. Right: facts panel (launched / operator /
   * jurisdiction) in the slot the Frank Score panel normally takes.
   */
  function fec_bl_render_hero($vm, $stateData, $opts = array()) {
    $title    = isset($vm['title'])     ? (string) $vm['title']     : '';
    $catTitle = isset($vm['cat_title']) ? (string) $vm['cat_title'] : 'Casinos';
    $parts    = fec_bl_reason_parts(isset($stateData['reason']) ? $stateData['reason'] : '');
    $whyHref  = isset($opts['whyHref']) ? (string) $opts['whyHref'] : '#bl-body';
    $hasClock = !empty($opts['hasClock']);

    $launch = fec_bl_launch_label($vm);
    $owner  = isset($vm['owner']) ? trim((string) $vm['owner']) : '';
    $juris  = isset($vm['jurisdiction']) ? trim((string) $vm['jurisdiction']) : '';
    $jurisIsNone = ($juris === '' || stripos($juris, 'not regulated') !== false || stripos($juris, 'none') !== false);

    $h  = '<nav class="cr-breadcrumb wrap" aria-label="Breadcrumb"><ol>';
    $h .=   '<li><a href="/">Home</a></li>';
    $h .=   '<li><a href="/online-casinos">' . fec_bl_esc($catTitle) . '</a></li>';
    $h .=   '<li aria-current="page">' . fec_bl_esc($title) . '</li>';
    $h .= '</ol></nav>';

    $h .= '<section class="cr-hero fec-bl-hero">';
    $h .=   '<div class="wrap">';
    $h .=     '<div class="cr-hero-card cr-hero-card--blacklisted fec-bl-card" data-state="BLACKLISTED">';

    // LEFT
    $h .=       '<div class="cr-hero-left">';
    $h .=         '<div class="cr-eyebrow-badges"><span class="cr-badge cr-badge--danger">Blacklisted'
              .   ($parts['since'] !== '' ? ' &middot; ' . fec_bl_esc($parts['since']) : '') . '</span></div>';
    $h .=         '<div class="cr-eyebrow">Blacklist record &middot; Documented, not reviewed</div>';
    $h .=         '<div class="cr-hero-name-row">';
    $h .=           (function_exists('fec_render_casino_chip') ? fec_render_casino_chip($title, 56) : '');
    $h .=           '<h1 class="cr-hero-title">' . fec_bl_esc($title) . '</h1>';
    $h .=         '</div>';
    if ($parts['reason'] !== '') {
      $h .=       '<p class="fec-bl-lede">' . fec_bl_esc($parts['reason']) . '</p>';
    }
    $h .=         '<div class="cr-hero-actions">';
    $h .=           '<a class="btn btn-primary fec-bl-btn" href="' . fec_bl_esc($whyHref) . '">Why it is blacklisted</a>';
    if ($hasClock) {
      $h .=         '<a class="btn btn-ghost" href="#fxclock">See the clocks</a>';
    }
    $h .=         '</div>';
    $h .=       '</div>';

    // RIGHT - facts panel
    $h .=       '<div class="fec-bl-facts" aria-label="Record facts">';
    $h .=         '<div class="row"><span class="k">Launched</span><span class="v">' . ($launch !== '' ? fec_bl_esc($launch) : '&mdash;') . '</span></div>';
    $h .=         '<div class="row"><span class="k">Operator</span><span class="v' . ($owner === '' ? ' danger' : '') . '">' . ($owner !== '' ? fec_bl_esc($owner) : 'Not disclosed') . '</span></div>';
    $h .=         '<div class="row"><span class="k">Jurisdiction</span><span class="v' . ($jurisIsNone ? ' danger' : '') . '">' . ($jurisIsNone ? 'Not regulated' : fec_bl_esc($juris)) . '</span></div>';
    $h .=         '<p class="note">No score, no ranking: a blacklisted casino is documented, not rated.</p>';
    $h .=       '</div>';

    $h .=     '</div>';
    $h .=   '</div>';
    $h .= '</section>';
    return $h;
  }
}


if (!function_exists('fec_bl_render_strip')) {
  /** 4-cell record strip in the stats-strip chrome: status, reports, clocks or restricted count, last update. */
  function fec_bl_render_strip($vm) {
    $reports  = isset($vm['reviews']) && is_array($vm['reviews']) ? count($vm['reviews']) : 0;
    $modified = (isset($vm['modified']) && function_exists('fec_format_date_short')) ? fec_format_date_short($vm['modified']) : '';
    $fxc      = (isset($vm['fxclock']) && is_array($vm['fxclock'])) ? $vm['fxclock'] : null;

    $h  = '<section class="cr-stats-strip fec-bl-strip"><div class="wrap"><div class="cr-stats-row">';
    $h .=   '<div class="stat"><div class="k">Status</div><div class="v danger">Blacklisted</div></div>';
    $h .=   '<div class="stat"><div class="k">Player reports</div><div class="v">' . (int) $reports . '</div></div>';
    if ($fxc !== null) {
      $running = isset($fxc['running']) ? (int) $fxc['running'] : 0;
      $stopped = isset($fxc['stopped']) ? (int) $fxc['stopped'] : 0;
      $h .= '<div class="stat stat-fxclock"><div class="k">Withdrawal clocks</div>'
          . '<div class="v"><a href="#fxclock">' . $running . '</a><sup> running &middot; ' . $stopped . ' stopped</sup></div></div>';
    } else {
      $n = isset($vm['restricted']) && is_array($vm['restricted']) ? count($vm['restricted']) : 0;
      $h .= '<div class="stat"><div class="k">Restricted countries</div><div class="v">' . ($n > 0 ? (int) $n : '&mdash;') . '</div></div>';
    }
    $h .=   '<div class="stat"><div class="k">Last update</div><div class="v mono">' . ($modified !== '' ? fec_bl_esc($modified) : '&mdash;') . '</div></div>';
    $h .= '</div></div></section>';
    return $h;
  }
}


if (!function_exists('fec_bl_render_sidebar')) {
  /** Status, record, what to do, review conditions. No outbound link to the casino. */
  function fec_bl_render_sidebar($vm, $stateData, $opts = array()) {
    $parts    = fec_bl_reason_parts(isset($stateData['reason']) ? $stateData['reason'] : '');
    $hasClock = !empty($opts['hasClock']);

    $launch = fec_bl_launch_label($vm);
    $owner  = isset($vm['owner']) ? trim((string) $vm['owner']) : '';
    $juris  = isset($vm['jurisdiction']) ? trim((string) $vm['jurisdiction']) : '';
    $jurisIsNone = ($juris === '' || stripos($juris, 'not regulated') !== false || stripos($juris, 'none') !== false);
    $email  = isset($vm['email']) ? trim((string) $vm['email']) : '';
    $site   = isset($vm['website']) ? trim((string) $vm['website']) : '';
    $siteTxt = $site !== '' ? preg_replace('#^https?://#', '', rtrim($site, '/')) : '';
    $nRestr = isset($vm['restricted']) && is_array($vm['restricted']) ? count($vm['restricted']) : 0;

    $h  = '<aside class="cr-sidebar fec-bl-sidebar">';

    $h .= '<div class="cr-side-card cr-side-card--danger">';
    $h .=   '<h3>Casino status</h3>';
    $h .=   '<div class="cr-side-status">Blacklisted' . ($parts['since'] !== '' ? ' &middot; ' . fec_bl_esc($parts['since']) : '') . '</div>';
    $h .=   '<p>Not promoted, not linked, not rated.</p>';
    $h .= '</div>';

    $rows = array();
    $rows[] = array('Launched', $launch !== '' ? fec_bl_esc($launch) : '&mdash;');
    $rows[] = array('Operator', $owner !== '' ? fec_bl_esc($owner) : 'Not disclosed');
    $rows[] = array('Jurisdiction', $jurisIsNone ? 'Not regulated' : fec_bl_esc($juris));
    if ($nRestr > 0) $rows[] = array('Restricted countries', (int) $nRestr . ' listed');
    if ($email !== '') $rows[] = array('Support e-mail', '<span class="plain">' . fec_bl_esc($email) . '</span>');
    if ($siteTxt !== '') $rows[] = array('Website', '<span class="plain">' . fec_bl_esc($siteTxt) . ' (not linked)</span>');

    $h .= '<div class="cr-side-card">';
    $h .=   '<h3>Record</h3>';
    foreach ($rows as $r) {
      $h .= '<div class="cr-side-row"><div class="k">' . fec_bl_esc($r[0]) . '</div><div class="v">' . $r[1] . '</div></div>';
    }
    $h .= '</div>';

    $h .= '<div class="cr-side-card">';
    $h .=   '<h3>If you have money on the site</h3>';
    $h .=   '<ul class="fec-bl-steps">';
    $h .=     '<li>Keep every screenshot: balance, withdrawal request, support transcript, document upload confirmations.</li>';
    $h .=     '<li>Card or Interac deposit: ask your payment provider about a chargeback or recall. It is their decision, not a right. Crypto deposits have no recall path.</li>';
    $h .=     '<li>' . ($jurisIsNone
                ? 'No licence is verifiable for this site, so there is no regulator to complain to and no dispute body.'
                : 'Check whether the licensing authority on record accepts player complaints; that is the only formal step.') . '</li>';
    $h .=     '<li>Do not deposit again to &ldquo;unlock&rdquo; a withdrawal.</li>';
    if ($hasClock) {
      $h .=   '<li>Pending withdrawal? <a href="#fxclock">Time it with FXClock</a>: the clocks are part of this record.</li>';
    }
    $h .=   '</ul>';
    $h .= '</div>';

    $h .= '<div class="cr-side-card">';
    $h .=   '<h3>When this record is reviewed</h3>';
    $h .=   '<p>Only when all three hold together: a licence number validated in a public register, a named and responsive operating company, and resolution of the reported cases.</p>';
    $h .= '</div>';

    $h .= '</aside>';
    return $h;
  }
}


if (!function_exists('fec_bl_render_reports')) {
  /** Player reports as evidence: user, date, text. No stars, no rating, no write-a-review. */
  function fec_bl_render_reports($vm) {
    $reviews = isset($vm['reviews']) && is_array($vm['reviews']) ? $vm['reviews'] : array();
    $count   = count($reviews);

    $h  = '<section class="cr-reviews fec-bl-reports" id="cr-reviews"><div class="wrap">';
    $h .=   '<div class="sec-head"><div class="left">';
    $h .=     '<span class="eyebrow">Evidence</span>';
    $h .=     '<h2 class="sec-title">Player reports</h2>';
    $h .=     '<p class="sec-sub">Moderated reports from readers, quoted as received. No scores: a blacklisted casino is not rated.</p>';
    $h .=   '</div></div>';

    if ($count === 0) {
      $h .= '<p class="fec-bl-empty">No reader reports on file. The record rests on the desk findings above.</p>';
      $h .= '</div></section>';
      return $h;
    }

    $h .= '<div class="cr-review-list" data-total="' . (int) $count . '">';
    $idx = 0;
    foreach ($reviews as $r) {
      $user = '';
      if (!empty($r['username']))  $user = (string) $r['username'];
      elseif (!empty($r['name']))  $user = (string) $r['name'];
      else                         $user = 'Anonymous';
      $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($user, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($user, 0, 1));
      $loc  = (!empty($r['show_location']) && !empty($r['location'])) ? (string) $r['location'] : '';
      $date = function_exists('fec_format_date_short') ? fec_format_date_short(isset($r['created']) ? $r['created'] : '') : '';
      $body = isset($r['comments']) ? trim(strip_tags((string) $r['comments'])) : '';

      $h .= '<article class="cr-review-item" data-idx="' . $idx . '">';
      $h .=   '<header class="cr-review-head">';
      $h .=     '<div class="cr-review-avatar">' . fec_bl_esc($initial) . '</div>';
      $h .=     '<div class="cr-review-who"><span class="user">' . fec_bl_esc($user) . '</span>'
            .   ($loc !== '' ? '<span class="badge">' . fec_bl_esc($loc) . '</span>' : '') . '</div>';
      $h .=     '<div class="cr-review-meta"><span class="badge">Report</span>'
            .   ($date !== '' ? '<span class="date">' . fec_bl_esc($date) . '</span>' : '') . '</div>';
      $h .=   '</header>';
      if ($body !== '') {
        $h .= '<p class="cr-review-text">' . nl2br(fec_bl_esc($body)) . '</p>';
      }
      $h .= '</article>';
      $idx++;
    }
    $h .= '</div>';
    $h .= '</div></section>';
    return $h;
  }
}


if (!function_exists('fec_bl_render_page')) {
  /**
   * Render the whole blacklist page. Echoes the markup and returns true.
   * Returns false (echoing nothing) when the inputs are not usable, so the
   * caller can fall back to the standard pipeline.
   *
   * @param object $db         JDatabase
   * @param array  $vm         view model from fec_get_casino_view_model()
   *                           (+ 'fxclock' summary when the override attached it)
   * @param array  $stateData  result of fec_decide_casino_state()
   * @param array  $opts       'item' => the Joomla article object (body in ->text),
   *                           'cid'  => content id
   * @return bool
   */
  function fec_bl_render_page($db, $vm, $stateData, $opts = array()) {
    if (!is_array($vm) || !is_array($stateData)) return false;
    if (!isset($stateData['state']) || $stateData['state'] !== 'BLACKLISTED') return false;

    $cid   = isset($opts['cid']) ? (int) $opts['cid'] : (isset($vm['cid']) ? (int) $vm['cid'] : 0);
    $item  = isset($opts['item']) ? $opts['item'] : null;
    $title = isset($vm['title']) ? (string) $vm['title'] : '';
    $body  = (is_object($item) && isset($item->text)) ? (string) $item->text : '';

    $hasClock = (isset($vm['fxclock']) && is_array($vm['fxclock']));

    // In-page target for the primary action: the body's own blacklist
    // section when the editor added one (id="blacklisted"), else the body.
    $whyHref = (strpos($body, 'id="blacklisted"') !== false) ? '#blacklisted' : '#bl-body';

    fec_bl_add_assets();
    fec_bl_head($vm, $stateData);

    $out  = '<div class="fecx fecx-blacklist">';

    // State banner (lib helper): title + explanation. The one-line reason is
    // printed once, in the hero, so it is blanked here to avoid saying the
    // same sentence twice in one viewport.
    if (function_exists('fec_render_casino_state_banner')) {
      $bannerState = $stateData;
      $bannerState['reason'] = '';
      $out .= fec_render_casino_state_banner($bannerState);
    }

    $out .= fec_bl_render_hero($vm, $stateData, array('whyHref' => $whyHref, 'hasClock' => $hasClock));
    $out .= fec_bl_render_strip($vm);

    // Body + sidebar in the review chrome.
    $out .= '<div class="wrap"><div class="cr-main" id="bl-body">';
    if (function_exists('fec_render_casino_intro') && trim($body) !== '') {
      $out .= fec_render_casino_intro($body, $title);
    } elseif (trim($body) !== '') {
      $out .= '<article class="cr-content">' . $body . '</article>';
    }
    $out .= fec_bl_render_sidebar($vm, $stateData, array('hasClock' => $hasClock));
    $out .= '</div></div>';

    if (!empty($vm['author']) && function_exists('fec_render_author_card')) {
      $out .= fec_render_author_card($vm['author']);
    }

    echo $out;

    // FXClock section (evidence). The helper echoes directly; same guard as
    // the override. Renders nothing when the brand is not enabled.
    if (FEC_BL_SHOW_FXCLOCK && $cid > 0) {
      $fxcHelper = JPATH_SITE . '/components/com_bonuses/helpers/fxclock_widget.php';
      if (is_readable($fxcHelper)) {
        require_once $fxcHelper;
        if (function_exists('fxc_widget_render')) {
          fxc_widget_render($cid, $title);
        }
      }
    }

    $tail = fec_bl_render_reports($vm);

    // Sister casinos: the same operator's other brands belong on a blacklist
    // record. Echoes nothing when there are none.
    if (FEC_BL_SHOW_SISTERS && $cid > 0 && function_exists('fec_render_casino_sister_map_by_id')) {
      $sisHtml = '';
      try { $sisHtml = fec_render_casino_sister_map_by_id($cid); } catch (Exception $e) { $sisHtml = ''; }
      if ($sisHtml !== '') {
        $tail .= '<section class="section cr-sisters-section"><div class="wrap">'
               . '<div class="sec-head"><div class="left">'
               . '<span class="eyebrow">Same owner</span>'
               . '<h2 class="sec-title">Sister casinos</h2>'
               . '<p class="sec-sub">Brands run by the same operator. A blacklisting here is worth knowing about there.</p>'
               . '</div></div>'
               . $sisHtml
               . '</div></section>';
      }
    }

    $tail .= '</div><!-- /.fecx -->';
    echo $tail;

    return true;
  }
}