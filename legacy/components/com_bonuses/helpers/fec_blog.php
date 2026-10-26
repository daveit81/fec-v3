<?php
/**
 * FreeExtraChips - Casino Bonus Blog renderer
 * Path: /components/com_bonuses/helpers/fec_blog.php
 * Version 1.0.11 (2026-10-14)
 *   1.0.11: fec_blog_render_posts_for_game(): the "From the blog" section
 *          for the bonus details page, keyed on the bonus gid (the same
 *          jos_games.gid that jos_fec_blog_meta.slot_gid points at). Posts
 *          about the game first, then the family, three cards in the blog
 *          card style; '' when the game has no post. fec_blog_fetch_posts()
 *          takes slot_gid / order=rel and returns slot_rel; the cards can
 *          show the relation (show_rel).
 *   1.0.10: breadcrumb says "Blog" (FEC_BLOG_CRUMB), the path it mirrors,
 *          on posts and on the index, HTML and BreadcrumbList alike; the
 *          blog name (title, H1, Blog node of the JSON-LD) is unchanged.
 *   1.0.9: RTP-verified slots. A post linked to a #__slot_rtp slot
 *          (jos_fec_blog_meta.slot_gid / slot_rel, com_authors 2.7.72)
 *          shows a "RTP verified" box in the facts panel with the
 *          verified figures and a link to /slots/{slug}; when the relation
 *          is "family" (sequel, variant, jackpot story) the box names the
 *          original and says so, never passing its RTP off as the post's
 *          game. JSON-LD: BlogPosting gets an @id (url#blogposting) and
 *          about (game) or mentions (family) pointing at the VideoGame
 *          node of the slot page (url#game).
 *   1.0.8: title and description re-applied after sh404SEF through the
 *          fec_cluster_meta_* app vars again (plg_system_fecclustermeta 1.1
 *          no longer eats "$20" out of "$205k"): sh404SEF replaced the
 *          listing title with the menu item title.
 *   1.0.7: the blog moves to /blog (FEC_BLOG_PATH): decision of 2026-10-13,
 *          /casino-bonus-blog/* answers 301 from .htaccess. Everything else
 *          is routed, so only the constant and the fallbacks change.
 *   1.0.6: the studio is a label, not a link: /online-casinos/software/* and
 *          the other jReviews tag/listing paths are 410 Gone in .htaccess
 *          (FEC_BLOG_GONE_RE mirrors those rules). fec_blog_prepare_content()
 *          unwraps any body link to a gone path at render time, so a post
 *          never links to a 410 page whatever was saved. The BlogPosting
 *          author node carries the @id of the com_authors Person
 *          (profile URL + #person) so both graphs name one entity.
 *
 * Renders the blog that lives in com_content category FEC_BLOG_CATID
 * (imported from EasyBlog by fec_blog_import.php, side table
 * jos_fec_blog_meta). Standalone helper in the fec_cmp_widget.php pattern:
 * the two fecv2 overrides include it guarded by is_readable() and call
 *
 *   fec_blog_render_listing($db, $category, $opts)   category/default.php
 *   fec_blog_render_article($db, $item, $opts)       article/default.php
 *
 * Both return HTML and push head meta (title, description, canonical,
 * robots, OG/Twitter, JSON-LD) through JDocument. The library
 * fec_common_lib.php must already be loaded (author card, breadcrumb,
 * pagination, canonical guard, ItemList JSON-LD are reused from there).
 *
 * Staging rule: while the blog menu item alias is not the final path
 * (FEC_BLOG_PATH), every page is noindex and canonicals point at the final
 * FEC_BLOG_PATH/{alias} URL, so a staging alias such as /blog-v2 can be
 * checked live without creating a duplicate of the EasyBlog pages.
 *
 * PHP 5.6 safe: no scalar type hints, no ??, no [] destructuring.
 */
defined('_JEXEC') or die;

if (!defined('FEC_BLOG_VERSION'))      define('FEC_BLOG_VERSION', '1.0.11');
if (!defined('FEC_BLOG_CATID'))        define('FEC_BLOG_CATID', 154);
if (!defined('FEC_BLOG_PATH'))         define('FEC_BLOG_PATH', '/blog');
if (!defined('FEC_BLOG_PER_PAGE'))     define('FEC_BLOG_PER_PAGE', 12);
if (!defined('FEC_BLOG_SITE_URL'))     define('FEC_BLOG_SITE_URL', 'https://www.freeextrachips.com');
if (!defined('FEC_BLOG_SITE_NAME'))    define('FEC_BLOG_SITE_NAME', 'FreeExtraChips');
if (!defined('FEC_BLOG_NAME'))         define('FEC_BLOG_NAME', 'Casino Bonus Blog');
if (!defined('FEC_BLOG_CRUMB'))        define('FEC_BLOG_CRUMB', 'Blog');             // breadcrumb label: the path, not the masthead
if (!defined('FEC_BLOG_ORG_SLUG'))     define('FEC_BLOG_ORG_SLUG', 'freeextrachips-editorial'); // casino_authors slug rendered as Organization
if (!defined('FEC_BLOG_CSS'))          define('FEC_BLOG_CSS', '/css/fec_blog.css');
if (!defined('FEC_BLOG_TOC_MIN_H2'))   define('FEC_BLOG_TOC_MIN_H2', 3);
// Site paths answered with 410 Gone by .htaccess (jReviews tag and listing
// pages). Mirror of the two RewriteRules; tested against the URL path only.
if (!defined('FEC_BLOG_GONE_RE'))      define('FEC_BLOG_GONE_RE', '#^/(?:online-casinos/(?:software|banking|countries|languages|currencies|type)|casino-reviews|reviews|countries)(?:/|$)|^/[^/]+/tag(?:/|$)#i');
if (!defined('FEC_BLOG_PROVIDER_LINKS')) define('FEC_BLOG_PROVIDER_LINKS', false); // software pages are gone: no studio links
if (!defined('FEC_BLOG_REAPPLY_META'))   define('FEC_BLOG_REAPPLY_META', true);    // needs plg_system_fecclustermeta >= 1.1
if (!defined('FEC_BLOG_SLOTS_PATH'))     define('FEC_BLOG_SLOTS_PATH', '/slots');   // com_authors slot pages, /slots/{slug}

/* =====================================================================
 * Small utilities
 * ===================================================================== */

if (!function_exists('fec_blog_esc')) {
  function fec_blog_esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('fec_blog_add_assets')) {
  /** Attach the blog stylesheet once (fec-edition.css is loaded by the template). */
  function fec_blog_add_assets() {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!class_exists('JFactory')) return;
    $doc = JFactory::getDocument();
    if (is_object($doc) && method_exists($doc, 'addStyleSheet')) {
      $base = class_exists('JURI') ? JURI::base(true) : '';
      $doc->addStyleSheet($base . FEC_BLOG_CSS . '?v=' . FEC_BLOG_VERSION);
    }
  }
}

if (!function_exists('fec_blog_kind_label')) {
  function fec_blog_kind_label($kind) {
    switch ((string) $kind) {
      case 'slot':    return 'Slot release';
      case 'live':    return 'Live casino';
      case 'jackpot': return 'Jackpots';
      case 'promo':   return 'Promotions';
      default:        return 'News';
    }
  }
}

if (!function_exists('fec_blog_date_long')) {
  /** "6 October 2026" from a SQL datetime; '' for null dates. */
  function fec_blog_date_long($sql) {
    $s = (string) $sql;
    if ($s === '' || $s === '0000-00-00 00:00:00' || $s === '0000-00-00') return '';
    $ts = strtotime($s);
    return $ts === false ? '' : date('j F Y', $ts);
  }
}
if (!function_exists('fec_blog_date_iso')) {
  function fec_blog_date_iso($sql) {
    $s = (string) $sql;
    if ($s === '' || $s === '0000-00-00 00:00:00' || $s === '0000-00-00') return '';
    $ts = strtotime($s);
    return $ts === false ? '' : date('c', $ts);
  }
}

if (!function_exists('fec_blog_text')) {
  /** Plain text from HTML: no images/headings, entities decoded, spaces collapsed. */
  function fec_blog_text($html) {
    $s = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', ' ', (string) $html);
    $s = preg_replace('#<img[^>]*>#i', ' ', $s);
    $s = preg_replace('#<h[1-6][^>]*>.*?</h[1-6]>#si', ' ', $s);
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    $s = str_replace("\xc2\xa0", ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim((string) $s);
  }
}

if (!function_exists('fec_blog_excerpt')) {
  function fec_blog_excerpt($html, $max = 150) {
    $s = fec_blog_text($html);
    if ($s === '') return '';
    $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    if ($len <= $max) return $s;
    $cut = function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
    $sp  = strrpos($cut, ' ');
    if ($sp !== false && $sp > 50) $cut = substr($cut, 0, $sp);
    return rtrim($cut, " .,;:-") . "\xe2\x80\xa6";
  }
}

if (!function_exists('fec_blog_image_path')) {
  /** image_intro (site-relative path or absolute URL) from jos_content.images JSON. */
  function fec_blog_image_path($imagesJson) {
    $j = json_decode((string) $imagesJson, true);
    if (!is_array($j)) return '';
    $p = '';
    if (!empty($j['image_intro'])) $p = (string) $j['image_intro'];
    elseif (!empty($j['image_fulltext'])) $p = (string) $j['image_fulltext'];
    return trim($p);
  }
}

if (!function_exists('fec_blog_image_src')) {
  /**
   * Resized src for an image path. Internal paths go through the Cloudflare
   * resizer (same pattern as the homepage cards); absolute external URLs are
   * returned untouched. $w/$h 0 = original.
   */
  function fec_blog_image_src($path, $w = 0, $h = 0) {
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) {
      $host = strtolower((string) parse_url($path, PHP_URL_HOST));
      if (!in_array($host, array('www.freeextrachips.com', 'freeextrachips.com', 'cdn.freeextrachips.com'), true)) return $path;
      $path = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    }
    $path = ltrim($path, '/');
    if ($w <= 0 && $h <= 0) return '/' . $path;
    $opts = 'fit=cover,quality=85,format=webp';
    if ($w > 0) $opts = 'width=' . (int) $w . ',' . $opts;
    if ($h > 0) $opts = 'height=' . (int) $h . ',' . $opts;
    return '/cdn-cgi/image/' . $opts . '/' . $path;
  }
}

if (!function_exists('fec_blog_image_abs')) {
  function fec_blog_image_abs($path) {
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return rtrim(FEC_BLOG_SITE_URL, '/') . '/' . ltrim($path, '/');
  }
}

if (!function_exists('fec_blog_provider_url')) {
  /** Studio page URL, or '' while FEC_BLOG_PROVIDER_LINKS is off (the pages are 410). */
  function fec_blog_provider_url($slug) {
    if (!FEC_BLOG_PROVIDER_LINKS) return '';
    $slug = trim((string) $slug);
    if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $slug)) return '';
    return '/online-casinos/software/' . rawurlencode($slug) . '/';
  }
}

if (!function_exists('fec_blog_is_gone_link')) {
  /** True when an href (relative, or absolute on the FEC host) points at a 410 path. */
  function fec_blog_is_gone_link($href) {
    $h = trim(html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8'));
    if ($h === '') return false;
    if (preg_match('#^(?:https?:)?//(?:www\.)?freeextrachips\.com(/.*)?$#i', $h, $m)) $h = isset($m[1]) ? $m[1] : '/';
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $h) || substr($h, 0, 2) === '//') return false; // other hosts, mailto:, etc.
    if ($h[0] !== '/') return false;
    $path = (string) parse_url($h, PHP_URL_PATH);
    return (bool) preg_match(FEC_BLOG_GONE_RE, $path);
  }
}

if (!function_exists('fec_blog_unwrap_gone_links')) {
  /** Replace every <a> pointing at a gone path with its inner HTML. $count gets the number unwrapped. */
  function fec_blog_unwrap_gone_links($html, &$count = 0) {
    $count = 0;
    return preg_replace_callback('#<a\b([^>]*)\bhref\s*=\s*(["\'])(.*?)\2([^>]*)>(.*?)</a>#is', function ($m) use (&$count) {
      if (!fec_blog_is_gone_link($m[3])) return $m[0];
      $count++;
      return $m[5];
    }, (string) $html);
  }
}

if (!function_exists('fec_blog_route')) {
  /** SEF URL of a blog article; falls back to the final path when routing is unavailable. */
  function fec_blog_route($id, $alias, $catid = 0) {
    $id = (int) $id; $alias = (string) $alias;
    if ($catid <= 0) $catid = (int) FEC_BLOG_CATID;
    if (class_exists('JRoute')) {
      $raw = 'index.php?option=com_content&view=article&id=' . $id . ':' . $alias . '&catid=' . $catid;
      // outside com_content (bonus pages, 1.0.11) the route helper is not loaded yet:
      // without it the URL would miss the blog Itemid and sh404SEF would mint another path
      if (!class_exists('ContentHelperRoute') && defined('JPATH_SITE')) {
        $rf = JPATH_SITE . '/components/com_content/helpers/route.php';
        if (is_readable($rf)) require_once $rf;
      }
      if (class_exists('ContentHelperRoute') && method_exists('ContentHelperRoute', 'getArticleRoute')) {
        $raw = ContentHelperRoute::getArticleRoute($id . ':' . $alias, $catid);
      }
      $url = JRoute::_($raw);
      if (is_string($url) && $url !== '' && stripos($url, 'index.php') === false) {
        return (substr($url, 0, 1) === '/') ? $url : '/' . $url;
      }
    }
    return FEC_BLOG_PATH . '/' . $alias;
  }
}

if (!function_exists('fec_blog_index_route')) {
  /** SEF URL of the blog index for the given menu item (staging or final). */
  function fec_blog_index_route($Itemid = 0) {
    if (class_exists('JRoute')) {
      $raw = 'index.php?option=com_content&view=category&id=' . (int) FEC_BLOG_CATID;
      if ($Itemid > 0) $raw .= '&Itemid=' . (int) $Itemid;
      $url = JRoute::_($raw);
      $p = is_string($url) ? (string) parse_url($url, PHP_URL_PATH) : '';
      if ($p !== '' && $p !== '/' && stripos($p, 'index.php') === false) return $p;
    }
    return FEC_BLOG_PATH;
  }
}

if (!function_exists('fec_blog_is_staging')) {
  /** True when the active menu item of the blog does not sit on the final path yet. */
  function fec_blog_is_staging() {
    static $v = null;
    if ($v !== null) return $v;
    $v = false;
    $path = fec_blog_index_route();
    if (rtrim($path, '/') !== rtrim(FEC_BLOG_PATH, '/')) $v = true;
    return $v;
  }
}

/* =====================================================================
 * Data
 * ===================================================================== */

if (!function_exists('fec_blog_fetch_meta')) {
  /** jos_fec_blog_meta row for one article (or null). */
  function fec_blog_fetch_meta($db, $contentId) {
    $contentId = (int) $contentId;
    if ($contentId <= 0) return null;
    try {
      $q = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__fec_blog_meta'))
        ->where('content_id = ' . $contentId);
      $db->setQuery($q, 0, 1);
      $row = $db->loadAssoc();
      return $row ? $row : null;
    } catch (Exception $e) { return null; }
  }
}

if (!function_exists('fec_blog_fetch_slot')) {
  /**
   * The RTP-verified slot a post is linked to (1.0.9): one row of
   * #__slot_rtp joined with #__games and the verifying author, only when
   * published and not retired. null otherwise or when the tables are missing.
   */
  function fec_blog_fetch_slot($db, $gid) {
    $gid = (int) $gid;
    if ($gid <= 0) return null;
    try {
      $sql = 'SELECT r.gid, r.slug, r.rtp_theoretical_max, r.rtp_theoretical_min, r.volatility, r.max_win_x, r.release_year,'
           . ' r.last_verified_date, r.verified_by_author_id, g.game_name, g.provider_name, a.name AS verified_by, a.slug AS verified_by_slug'
           . ' FROM ' . $db->quoteName('#__slot_rtp') . ' r'
           . ' INNER JOIN ' . $db->quoteName('#__games') . ' g ON g.gid = r.gid'
           . ' LEFT JOIN ' . $db->quoteName('#__casino_authors') . ' a ON a.id = r.verified_by_author_id AND a.is_active = 1'
           . ' WHERE r.gid = ' . $gid . ' AND r.published = 1 AND r.retired = 0';
      $db->setQuery($sql, 0, 1);
      $row = $db->loadAssoc();
      if ($db->getErrorNum() || !$row || empty($row['slug'])) return null;
      return $row;
    } catch (Exception $e) { return null; }
  }
}

if (!function_exists('fec_blog_slot_url')) {
  /** Site-relative URL of a slot page: /slots/{slug}. */
  function fec_blog_slot_url($slug) {
    $slug = trim((string) $slug, '/');
    return $slug === '' ? '' : FEC_BLOG_SLOTS_PATH . '/' . $slug;
  }
}

if (!function_exists('fec_blog_render_verified_box')) {
  /**
   * The "RTP verified" box of the facts panel (1.0.9). $rel: game | family.
   */
  function fec_blog_render_verified_box($slot, $rel) {
    if (!$slot) return '';
    $esc = 'fec_blog_esc';
    $url = fec_blog_slot_url($slot['slug']);
    $fmtPct = function ($v) { return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . '%'; };
    $max = ($slot['rtp_theoretical_max'] !== null && $slot['rtp_theoretical_max'] !== '') ? (float) $slot['rtp_theoretical_max'] : null;
    $min = ($slot['rtp_theoretical_min'] !== null && $slot['rtp_theoretical_min'] !== '') ? (float) $slot['rtp_theoretical_min'] : null;
    $multi = ($max !== null && $min !== null && $min < $max);
    $bits = array();
    if (!empty($slot['volatility'])) {
      $volMap = array('low' => 'Low', 'low-med' => 'Low-medium', 'medium' => 'Medium', 'med-high' => 'Medium-high', 'high' => 'High', 'very-high' => 'Very high', 'extreme' => 'Extreme');
      $vol = (string) $slot['volatility'];
      $bits[] = $esc(isset($volMap[$vol]) ? $volMap[$vol] : ucfirst($vol)) . ' volatility';
    }
    if (!empty($slot['max_win_x'])) $bits[] = $esc(number_format((int) $slot['max_win_x'])) . 'x max win';
    $when = (!empty($slot['last_verified_date']) && strpos($slot['last_verified_date'], '0000-00-00') !== 0) ? fec_blog_date_long($slot['last_verified_date']) : '';
    $byWho = !empty($slot['verified_by']) ? $esc($slot['verified_by']) : '';
    $h  = '<div class="fec-blog-verified' . ($rel === 'game' ? ' is-game' : ' is-family') . '">';
    if ($rel === 'game') {
      $h .= '<span class="eyebrow">RTP verified</span>';
      $h .= '<div class="fec-blog-verified-name">' . $esc($slot['game_name']) . '<small>checked against the primary source</small></div>';
    } else {
      $h .= '<span class="eyebrow">Verified in this series</span>';
      $h .= '<div class="fec-blog-verified-name">' . $esc($slot['game_name']) . '<small>the original this post builds on; figures below are its own, not the game above</small></div>';
    }
    if ($max !== null) {
      $h .= '<div class="fec-blog-verified-rtp"><b>' . $esc($fmtPct($max)) . '</b><span>RTP' . ($multi ? ', top tier' : '') . '</span>';
      if ($multi) $h .= '<em>ships in several RTP versions, down to ' . $esc($fmtPct($min)) . '</em>';
      $h .= '</div>';
    }
    if ($bits || $when !== '') {
      $h .= '<div class="fec-blog-verified-line">' . implode(' &middot; ', $bits);
      if ($when !== '') $h .= ($bits ? ' &middot; ' : '') . 'verified ' . $esc($when) . ($byWho !== '' ? ' by ' . $byWho : '');
      $h .= '</div>';
    }
    if ($url !== '') $h .= '<a class="hub-pill fec-blog-verified-link" href="' . $esc($url) . '">Read the verification</a>';
    $h .= '</div>';
    return $h;
  }
}

if (!function_exists('fec_blog_fetch_posts')) {
  /**
   * Published blog posts, newest first, joined with the side table.
   * $opts: limit, offset, exclude (id), provider_slug, kind, ids (array)
   */
  function fec_blog_fetch_posts($db, $opts = array()) {
    $defaults = array('limit' => 12, 'offset' => 0, 'exclude' => 0, 'provider_slug' => '', 'kind' => '', 'ids' => array(),
                      'slot_gid' => 0, 'order' => 'date');
    $opts = array_merge($defaults, is_array($opts) ? $opts : array());
    $nowUtc = gmdate('Y-m-d H:i:s');
    $cols = array('c.id', 'c.title', 'c.alias', 'c.introtext', 'c.created', 'c.modified', 'c.publish_up',
                  'c.images', 'c.hits', 'c.created_by_alias', 'c.author_id', 'c.catid',
                  'm.kind', 'm.provider_slug', 'm.provider_name', 'm.release_date');
    // slot_gid / slot_rel exist from com_authors 2.7.72 on: select them only when asked for
    if ((int) $opts['slot_gid'] > 0 || $opts['order'] === 'rel') { $cols[] = 'm.slot_gid'; $cols[] = 'm.slot_rel'; }
    $q = $db->getQuery(true)
      ->select($cols)
      ->from($db->quoteName('#__content', 'c'))
      ->join('LEFT', $db->quoteName('#__fec_blog_meta', 'm') . ' ON m.content_id = c.id')
      ->where('c.catid = ' . (int) FEC_BLOG_CATID)
      ->where('c.state = 1')
      ->where('(c.publish_up = ' . $db->quote('0000-00-00 00:00:00') . ' OR c.publish_up <= ' . $db->quote($nowUtc) . ')')
      ->where('(c.publish_down = ' . $db->quote('0000-00-00 00:00:00') . ' OR c.publish_down >= ' . $db->quote($nowUtc) . ')')
      ->order($opts['order'] === 'rel' ? "(m.slot_rel = 'game') DESC, c.created DESC, c.id DESC" : 'c.created DESC, c.id DESC');
    if ((int) $opts['exclude'] > 0) $q->where('c.id <> ' . (int) $opts['exclude']);
    if ((int) $opts['slot_gid'] > 0) $q->where('m.slot_gid = ' . (int) $opts['slot_gid']);
    if ((string) $opts['provider_slug'] !== '') $q->where('m.provider_slug = ' . $db->quote((string) $opts['provider_slug']));
    if ((string) $opts['kind'] !== '') $q->where('m.kind = ' . $db->quote((string) $opts['kind']));
    if (!empty($opts['ids']) && is_array($opts['ids'])) {
      $ids = array(); foreach ($opts['ids'] as $i) { $i = (int) $i; if ($i > 0) $ids[] = $i; }
      if ($ids) $q->where('c.id IN (' . implode(',', $ids) . ')');
    }
    $db->setQuery($q, max(0, (int) $opts['offset']), max(1, (int) $opts['limit']));
    $rows = $db->loadAssocList();
    return is_array($rows) ? $rows : array();
  }
}

if (!function_exists('fec_blog_count_posts')) {
  function fec_blog_count_posts($db) {
    $nowUtc = gmdate('Y-m-d H:i:s');
    $q = $db->getQuery(true)
      ->select('COUNT(*)')
      ->from($db->quoteName('#__content', 'c'))
      ->where('c.catid = ' . (int) FEC_BLOG_CATID)
      ->where('c.state = 1')
      ->where('(c.publish_up = ' . $db->quote('0000-00-00 00:00:00') . ' OR c.publish_up <= ' . $db->quote($nowUtc) . ')')
      ->where('(c.publish_down = ' . $db->quote('0000-00-00 00:00:00') . ' OR c.publish_down >= ' . $db->quote($nowUtc) . ')');
    $db->setQuery($q);
    return (int) $db->loadResult();
  }
}

if (!function_exists('fec_blog_author')) {
  /**
   * Author for an article: the casino_authors row behind jos_content.author_id
   * when set, else a fallback built from created_by_alias (so pages render
   * correctly before the profile exists). Returns array(name, slug, role,
   * short_bio, avatar_url, is_org) or null.
   */
  function fec_blog_author($db, $authorId, $createdByAlias) {
    $a = null;
    if ((int) $authorId > 0 && function_exists('fec_get_content_author')) {
      $a = fec_get_content_author($db, (int) $authorId);
    }
    if (!$a) {
      $alias = trim((string) $createdByAlias);
      if ($alias === '') return null;
      $a = array('id' => 0, 'slug' => '', 'name' => $alias, 'role' => '', 'short_bio' => '', 'avatar_url' => '');
      if (strcasecmp($alias, 'FreeExtraChips Editorial') === 0) {
        $a['slug'] = FEC_BLOG_ORG_SLUG;
        $a['role'] = 'Editorial desk';
      } elseif (strcasecmp($alias, 'Player101') === 0) {
        $a['role'] = 'Slot & game-release analyst';
      }
    }
    $a['is_org'] = (isset($a['slug']) && $a['slug'] === FEC_BLOG_ORG_SLUG) || strcasecmp((string) $a['name'], 'FreeExtraChips Editorial') === 0;
    $a['url'] = '';
    if (!$a['is_org'] && !empty($a['slug']) && (int) $a['id'] > 0 && class_exists('JRoute')) {
      $a['url'] = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($a['slug']));
    }
    return $a;
  }
}

/* =====================================================================
 * Shared card grid (listing, related posts, homepage section)
 * ===================================================================== */

if (!function_exists('fec_blog_render_cards')) {
  /**
   * .blog-row / .blog-card markup identical to the homepage section, with an
   * optional lead card. $rows from fec_blog_fetch_posts().
   * $opts: lead (bool), img_w, img_h, excerpt_len, grid_class
   */
  function fec_blog_render_cards($rows, $opts = array()) {
    $defaults = array('lead' => false, 'img_w' => 400, 'img_h' => 240, 'excerpt_len' => 140, 'grid_class' => 'blog-row', 'show_rel' => false);
    $opts = array_merge($defaults, is_array($opts) ? $opts : array());
    if (empty($rows)) return '';
    $esc = 'fec_blog_esc';
    $h = '<div class="' . $esc($opts['grid_class']) . '">';
    foreach ($rows as $i => $r) {
      $id = (int) $r['id'];
      $title = (string) $r['title'];
      $url = fec_blog_route($id, $r['alias'], isset($r['catid']) ? (int) $r['catid'] : 0);
      $kindLabel = fec_blog_kind_label(isset($r['kind']) ? $r['kind'] : 'news');
      $label = (!empty($r['provider_name'])) ? (string) $r['provider_name'] : $kindLabel;
      $date = fec_blog_date_long($r['created']);
      $img = fec_blog_image_path(isset($r['images']) ? $r['images'] : '');
      $isLead = ($opts['lead'] && $i === 0);
      if ($img !== '') {
        $src = fec_blog_image_src($img, $isLead ? 800 : (int) $opts['img_w'], $isLead ? 480 : (int) $opts['img_h']);
        $imgFrag = '<img src="' . $esc($src) . '" alt="' . $esc($title) . '" loading="lazy" width="' . ($isLead ? 800 : (int) $opts['img_w']) . '" height="' . ($isLead ? 480 : (int) $opts['img_h']) . '">';
      } else {
        $imgFrag = '<div class="stripes" aria-hidden="true"></div>';
      }
      $excerpt = fec_blog_excerpt(isset($r['introtext']) ? $r['introtext'] : '', (int) $opts['excerpt_len']);
      $h .= '<a class="blog-card' . ($isLead ? ' lead' : '') . '" href="' . $esc($url) . '">';
      $h .=   '<div class="blog-thumb" data-label="' . $esc($label) . '">' . $imgFrag . '</div>';
      $h .=   '<div class="blog-body">';
      $relFrag = '';
      if ($opts['show_rel'] && isset($r['slot_rel']) && $r['slot_rel'] !== null && $r['slot_rel'] !== '') {
        $relFrag = ' &middot; <span class="fec-blog-rel' . ($r['slot_rel'] === 'game' ? ' is-game' : '') . '">' . ($r['slot_rel'] === 'game' ? 'About this game' : 'Same family') . '</span>';
      }
      if ($date !== '' || $relFrag !== '') $h .= '<div class="blog-meta">' . $esc($date) . ($kindLabel !== $label ? ' &middot; ' . $esc($kindLabel) : '') . $relFrag . '</div>';
      $h .=     '<h3 class="blog-title">' . $esc($title) . '</h3>';
      if ($excerpt !== '') $h .= '<p class="blog-excerpt">' . $esc($excerpt) . '</p>';
      $h .=   '</div>';
      $h .= '</a>';
    }
    $h .= '</div>';
    return $h;
  }
}

if (!function_exists('fec_blog_render_posts_for_game')) {
  /**
   * "From the blog" section for a game (1.0.11): the published posts linked
   * to jos_games.gid through jos_fec_blog_meta.slot_gid, the ones about the
   * game first, then its family, as blog cards. Used by the bonus details
   * page (gid = the bonus game). Returns '' when there is nothing to show,
   * so the caller can decide whether to add a jump chip.
   * $opts: limit (3), game_name ('' = read from jos_games), id ('from-the-blog'),
   *        all_url ('' = blog index)
   */
  function fec_blog_render_posts_for_game($db, $gid, $opts = array()) {
    $gid = (int) $gid;
    if ($gid <= 0) return '';
    $defaults = array('limit' => 3, 'game_name' => '', 'id' => 'from-the-blog', 'all_url' => '');
    $opts = array_merge($defaults, is_array($opts) ? $opts : array());
    try {
      $rows = fec_blog_fetch_posts($db, array('limit' => max(1, (int) $opts['limit']), 'slot_gid' => $gid, 'order' => 'rel'));
    } catch (Exception $e) { $rows = array(); }
    if (!$rows) return '';
    $name = trim((string) $opts['game_name']);
    if ($name === '') {
      try {
        $q = $db->getQuery(true)->select('game_name')->from($db->quoteName('#__games'))->where('gid = ' . $gid);
        $db->setQuery($q, 0, 1);
        $name = trim((string) $db->loadResult());
      } catch (Exception $e) { $name = ''; }
    }
    $nGame = 0;
    foreach ($rows as $r) if (isset($r['slot_rel']) && $r['slot_rel'] === 'game') $nGame++;
    fec_blog_add_assets();
    $esc = 'fec_blog_esc';
    $allUrl = (string) $opts['all_url'];
    if ($allUrl === '') $allUrl = FEC_BLOG_PATH . '/';   // the final path, no router needed outside com_content
    $title = ($name !== '') ? ($nGame > 0 ? $esc($name) . ' on the blog' : 'The ' . $esc($name) . ' family on the blog') : 'From the blog';
    $sub = '';
    if ($name !== '' && $nGame === 0) $sub = 'Sequels, variants and jackpot stories built on ' . $esc($name) . '; the original has no post of its own yet.';
    $h  = '<div class="fecx fec-blog fec-blog-forgame" id="' . $esc($opts['id']) . '">';
    $h .= '<section class="section fec-blog-related" aria-label="From the blog"><div class="wrap">';
    $h .=   '<div class="sec-head"><div class="left"><span class="eyebrow">From the blog</span>'
         .  '<h2 class="sec-title">' . $title . '</h2>' . ($sub !== '' ? '<p class="fec-blog-forgame-sub">' . $sub . '</p>' : '') . '</div>'
         .  '<a class="sec-link" href="' . $esc($allUrl) . '">All posts <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a></div>';
    $h .=   fec_blog_render_cards($rows, array('grid_class' => 'fec-blog-grid', 'show_rel' => true));
    $h .= '</div></section></div>';
    return $h;
  }
}

/* =====================================================================
 * Head meta helpers
 * ===================================================================== */

if (!function_exists('fec_blog_push_head')) {
  /**
   * Push title/description/canonical/robots/OG/Twitter/JSON-LD into JDocument.
   * Same technique as the casino branch: setTitle/setDescription for the
   * Joomla-owned tags, addCustomTag for canonical, property= metas and
   * JSON-LD.
   */
  function fec_blog_push_head($meta) {
    if (!class_exists('JFactory')) return;
    $doc = JFactory::getDocument();
    if (!is_object($doc)) return;
    $esc = 'fec_blog_esc';
    if (!empty($meta['title']))       $doc->setTitle($meta['title']);
    if (!empty($meta['description'])) $doc->setDescription($meta['description']);
    if (!empty($meta['robots']) && method_exists($doc, 'setMetaData')) $doc->setMetaData('robots', $meta['robots']);
    if (!empty($meta['canonical'])) {
      // drop any canonical the site-wide emitter already queued
      if (isset($doc->_links) && is_array($doc->_links)) {
        foreach (array_keys($doc->_links) as $href) {
          $rel = isset($doc->_links[$href]['relation']) ? strtolower((string) $doc->_links[$href]['relation']) : '';
          if ($rel === 'canonical') unset($doc->_links[$href]);
        }
      }
      // addHeadLink, not addCustomTag: the template emitter only yields to a
      // canonical it finds in $doc->_links (same technique as the listing guard).
      if (method_exists($doc, 'addHeadLink')) $doc->addHeadLink($meta['canonical'], 'canonical');
      else $doc->addCustomTag('<link rel="canonical" href="' . $esc($meta['canonical']) . '" />');
    }
    if (!empty($meta['og']) && is_array($meta['og'])) {
      foreach ($meta['og'] as $k => $v) {
        if ((string) $v === '') continue;
        $doc->addCustomTag('<meta property="' . $esc($k) . '" content="' . $esc($v) . '" />');
      }
    }
    if (!empty($meta['twitter']) && is_array($meta['twitter']) && method_exists($doc, 'setMetaData')) {
      foreach ($meta['twitter'] as $k => $v) { if ((string) $v !== '') $doc->setMetaData($k, $v); }
    }
    if (!empty($meta['jsonld'])) {
      foreach ((array) $meta['jsonld'] as $ld) {
        $enc = json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($enc) $doc->addCustomTag('<script type="application/ld+json">' . $enc . '</script>');
      }
    }
    // sh404SEF overwrites the document title of the category listing with the
    // menu item title ("Blog") after the component ran. plg_system_fecclustermeta
    // 1.1 re-applies these two app vars on top of sh404SEF (1.0.8; with the
    // plugin at 1.0 a title such as "$205k" lost its "$20" to a preg_replace
    // backreference, hence the detour of 1.0.5). Set only when the plugin fix is
    // in place: FEC_BLOG_REAPPLY_META is true by default, define it false
    // before including this file to go back to JDocument only.
    if (FEC_BLOG_REAPPLY_META && class_exists('JFactory')) {
      $app = JFactory::getApplication();
      if (is_object($app) && method_exists($app, 'set')) {
        if (!empty($meta['title']))       $app->set('fec_cluster_meta_title', (string) $meta['title']);
        if (!empty($meta['description'])) $app->set('fec_cluster_meta_desc', (string) $meta['description']);
      }
    }
  }
}

/* =====================================================================
 * Article page
 * ===================================================================== */

if (!function_exists('fec_blog_prepare_content')) {
  /**
   * Prepare the assembled body: demote any <h1> inside it to <h2> (the page
   * already has its H1 in the hero). Content plugins are deliberately NOT
   * run here: triggering onContentPrepare on a hand-built article object made
   * one of the installed plugins send anonymous visitors to the login page
   * (2026-10-13). The posts are plain HTML and need none of them.
   */
  function fec_blog_prepare_content($html, $item) {
    $html = (string) $html;
    if ($html === '') return '';
    $html = preg_replace('#<h1\b([^>]*)>(.*?)</h1>#si', '<h2$1>$2</h2>', $html);
    $html = fec_blog_unwrap_gone_links($html);   // never link a 410 page, whatever was saved
    return $html;
  }
}

if (!function_exists('fec_blog_add_heading_ids')) {
  /**
   * Give every <h2> an id (slug of its text, de-duplicated) and return the
   * TOC entries. Headings that already carry an id are kept as they are.
   */
  function fec_blog_add_heading_ids($html, &$toc) {
    $toc = array();
    $used = array();
    $cb = function ($m) use (&$toc, &$used) {
      $attrs = $m[1]; $inner = $m[2];
      $label = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));
      if ($label === '') return $m[0];
      if (preg_match('/\bid=["\']([^"\']+)["\']/i', $attrs, $im)) {
        $id = $im[1];
      } else {
        $id = strtolower($label);
        $id = preg_replace('/[^a-z0-9]+/', '-', $id);
        $id = trim($id, '-');
        if ($id === '' || preg_match('/^\d/', $id)) $id = 'section-' . $id;
        $base = $id; $n = 2;
        while (isset($used[$id])) { $id = $base . '-' . $n; $n++; }
        $attrs = $attrs . ' id="' . fec_blog_esc($id) . '"';
      }
      $used[$id] = true;
      $toc[] = array('id' => $id, 'label' => $label);
      return '<h2' . $attrs . '>' . $inner . '</h2>';
    };
    return preg_replace_callback('#<h2([^>]*)>(.*?)</h2>#si', $cb, (string) $html);
  }
}

if (!function_exists('fec_blog_render_article')) {
  /**
   * Full article page. $item is the com_content article object from the view
   * ($this->item): id, title, alias, catid, introtext, fulltext, text (plugin
   * processed), created, modified, publish_up, images, metadesc, metadata,
   * author_id, created_by_alias, hits.
   * $opts: Itemid
   */
  function fec_blog_render_article($db, $item, $opts = array()) {
    if (!is_object($item) || empty($item->id)) return '';
    fec_blog_add_assets();
    $esc = 'fec_blog_esc';
    $id    = (int) $item->id;
    $title = (string) $item->title;
    $alias = (string) $item->alias;
    $catid = isset($item->catid) ? (int) $item->catid : (int) FEC_BLOG_CATID;
    $Itemid = isset($opts['Itemid']) ? (int) $opts['Itemid'] : 0;

    $meta   = fec_blog_fetch_meta($db, $id);
    $kind   = ($meta && !empty($meta['kind'])) ? (string) $meta['kind'] : 'news';
    $provName = ($meta && !empty($meta['provider_name'])) ? (string) $meta['provider_name'] : '';
    $provSlug = ($meta && !empty($meta['provider_slug'])) ? (string) $meta['provider_slug'] : '';
    $provUrl  = fec_blog_provider_url($provSlug);
    // RTP-verified slot the post is linked to (1.0.9; columns from com_authors 2.7.72)
    $vSlot = ($meta && !empty($meta['slot_gid'])) ? fec_blog_fetch_slot($db, (int) $meta['slot_gid']) : null;
    $vRel  = ($meta && isset($meta['slot_rel']) && $meta['slot_rel'] === 'game') ? 'game' : 'family';

    // author_id is a custom FEC column that the core article model does not
    // select, so read it directly (the view object only has it in previews).
    $authorId = isset($item->author_id) ? (int) $item->author_id : 0;
    if ($authorId <= 0) {
      try {
        $qa = $db->getQuery(true)->select('author_id')->from($db->quoteName('#__content'))->where('id = ' . $id);
        $db->setQuery($qa, 0, 1);
        $authorId = (int) $db->loadResult();
      } catch (Exception $e) { $authorId = 0; }
    }
    $author = fec_blog_author($db, $authorId, isset($item->created_by_alias) ? $item->created_by_alias : '');

    $created  = isset($item->created) ? (string) $item->created : '';
    $modified = isset($item->modified) ? (string) $item->modified : '';
    $createdLabel  = fec_blog_date_long($created);
    $modifiedLabel = ($modified !== '' && strtotime($modified) > strtotime($created) + 86400) ? fec_blog_date_long($modified) : '';

    // Body: always intro + full text. The view's $item->text depends on the
    // "show intro text" option, which this site keeps off for reviews, so the
    // lede would vanish.
    $body = (isset($item->introtext) ? (string) $item->introtext : '') . (isset($item->fulltext) ? (string) $item->fulltext : '');
    if (trim($body) === '' && isset($item->text)) $body = (string) $item->text;
    $body = fec_blog_prepare_content($body, $item);
    $toc = array();
    $body = fec_blog_add_heading_ids($body, $toc);
    $hasToc = count($toc) >= (int) FEC_BLOG_TOC_MIN_H2;

    $imgPath = fec_blog_image_path(isset($item->images) ? $item->images : '');
    $imgAbs  = fec_blog_image_abs($imgPath);

    $indexUrl = fec_blog_index_route($Itemid);
    $canonical = rtrim(FEC_BLOG_SITE_URL, '/') . FEC_BLOG_PATH . '/' . $alias;
    $staging = fec_blog_is_staging();

    // ---------------- head meta ----------------
    $pageTitle = $title;
    $pageDesc  = isset($item->metadesc) ? trim((string) $item->metadesc) : '';
    if (!empty($item->metadata)) {
      $mdj = json_decode((string) $item->metadata, true);
      if (is_array($mdj) && !empty($mdj['page_title'])) $pageTitle = trim((string) $mdj['page_title']);
    }
    if ($pageDesc === '') $pageDesc = fec_blog_excerpt($body, 155);
    $robots = '';
    if ($staging) $robots = 'noindex, follow';
    elseif (!empty($item->metadata)) {
      $mdj = isset($mdj) ? $mdj : json_decode((string) $item->metadata, true);
      if (is_array($mdj) && !empty($mdj['robots'])) $robots = (string) $mdj['robots'];
    }
    $og = array(
      'og:type' => 'article', 'og:title' => $pageTitle, 'og:description' => $pageDesc,
      'og:url' => $canonical, 'og:site_name' => FEC_BLOG_SITE_NAME,
      'article:published_time' => fec_blog_date_iso($created),
      'article:modified_time'  => fec_blog_date_iso($modified !== '' ? $modified : $created),
      'article:section' => fec_blog_kind_label($kind),
    );
    if ($provName !== '') $og['article:tag'] = $provName;
    if ($imgAbs !== '') $og['og:image'] = $imgAbs;
    $tw = array('twitter:card' => $imgAbs !== '' ? 'summary_large_image' : 'summary', 'twitter:title' => $pageTitle, 'twitter:description' => $pageDesc);
    if ($imgAbs !== '') $tw['twitter:image'] = $imgAbs;

    // JSON-LD: BlogPosting + breadcrumb (breadcrumb emitted by the lib helper below)
    if ($author && !$author['is_org']) {
      $authorNode = array('@type' => 'Person', 'name' => $author['name']);
      if (!empty($author['url'])) {
        $authorAbs = rtrim(FEC_BLOG_SITE_URL, '/') . $author['url'];
        $authorNode['@id'] = $authorAbs . '#person';   // the com_authors Person entity
        $authorNode['url'] = $authorAbs;
      }
      if (!empty($author['role'])) $authorNode['jobTitle'] = $author['role'];
    } else {
      $authorNode = array('@type' => 'Organization', 'name' => FEC_BLOG_SITE_NAME, 'url' => FEC_BLOG_SITE_URL . '/');
    }
    $ld = array(
      '@context' => 'https://schema.org',
      '@type' => 'BlogPosting',
      '@id' => $canonical . '#blogposting',
      'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => $canonical),
      'headline' => $title,
      'description' => $pageDesc,
      'url' => $canonical,
      'datePublished' => fec_blog_date_iso($created),
      'dateModified' => fec_blog_date_iso($modified !== '' ? $modified : $created),
      'author' => $authorNode,
      'publisher' => array('@type' => 'Organization', '@id' => FEC_BLOG_SITE_URL . '/#organization', 'name' => FEC_BLOG_SITE_NAME, 'url' => FEC_BLOG_SITE_URL . '/'),
      'isPartOf' => array('@type' => 'Blog', '@id' => FEC_BLOG_SITE_URL . FEC_BLOG_PATH . '#blog', 'name' => FEC_BLOG_NAME, 'url' => FEC_BLOG_SITE_URL . FEC_BLOG_PATH),
      'articleSection' => fec_blog_kind_label($kind),
      'inLanguage' => 'en',
    );
    if ($imgAbs !== '') $ld['image'] = $imgAbs;
    if ($provName !== '') $ld['keywords'] = $provName;
    $wordCount = str_word_count(fec_blog_text($body));
    if ($wordCount > 0) $ld['wordCount'] = $wordCount;
    if ($vSlot) {
      $slotAbs = rtrim(FEC_BLOG_SITE_URL, '/') . fec_blog_slot_url($vSlot['slug']);
      $gameNode = array('@type' => 'VideoGame', '@id' => $slotAbs . '#game', 'name' => $vSlot['game_name'], 'url' => $slotAbs);
      if (!empty($vSlot['provider_name'])) $gameNode['publisher'] = array('@type' => 'Organization', 'name' => $vSlot['provider_name']);
      $ld[$vRel === 'game' ? 'about' : 'mentions'] = $gameNode;
    }

    fec_blog_push_head(array(
      'title' => $pageTitle, 'description' => $pageDesc, 'canonical' => $canonical, 'robots' => $robots,
      'og' => $og, 'twitter' => $tw, 'jsonld' => array($ld),
    ));

    // ---------------- breadcrumb ----------------
    $crumbs = array(
      array('name' => 'Home', 'url' => '/'),
      array('name' => FEC_BLOG_CRUMB, 'url' => $indexUrl),
      array('name' => $title, 'url' => fec_blog_route($id, $alias, $catid)),
    );
    $crumbHtml = function_exists('fec_render_breadcrumb_html') ? fec_render_breadcrumb_html($crumbs) : '';
    $crumbLd   = function_exists('fec_render_breadcrumb_jsonld') ? fec_render_breadcrumb_jsonld($crumbs) : '';

    // ---------------- hero ----------------
    $kindLabel = fec_blog_kind_label($kind);
    $h  = '<div class="fecx fec-blog">';
    $h .= $crumbHtml . $crumbLd;
    $h .= '<section class="hub-hero fec-blog-hero"><div class="wrap"><div class="hub-hero-grid">';
    $h .=   '<div class="hub-card">';
    $h .=     '<span class="eyebrow">' . $esc($kindLabel) . ($provName !== '' ? ' &middot; ' . $esc($provName) : '') . '</span>';
    $h .=     '<h1 class="hub-title">' . $esc($title) . '</h1>';
    $h .=     '<div class="hub-byline fec-blog-byline">';
    if ($author) {
      $nameHtml = ($author['url'] !== '') ? '<a href="' . $esc($author['url']) . '" rel="author">' . $esc($author['name']) . '</a>' : $esc($author['name']);
      $h .=       '<div class="person"><span class="who">Written by</span><span class="name">' . $nameHtml . '</span>'
            . (!empty($author['role']) ? '<span class="role">' . $esc($author['role']) . '</span>' : '') . '</div>';
    }
    $h .=       '<div class="updated">';
    if ($createdLabel !== '')  $h .= 'Published <time datetime="' . $esc(fec_blog_date_iso($created)) . '">' . $esc($createdLabel) . '</time>';
    if ($modifiedLabel !== '') $h .= ' &middot; Updated <time datetime="' . $esc(fec_blog_date_iso($modified)) . '">' . $esc($modifiedLabel) . '</time>';
    $h .=       '</div>';
    $h .=     '</div>';
    $pills = array();
    if ($provName !== '') $pills[] = ($provUrl !== '') ? '<a class="hub-pill" href="' . $esc($provUrl) . '">' . $esc($provName) . ' casinos</a>' : '<span class="hub-pill">' . $esc($provName) . '</span>';
    $pills[] = '<a class="hub-pill" href="' . $esc($indexUrl) . '">All blog posts</a>';
    $h .=     '<div class="hub-pills">' . implode('', $pills) . '</div>';
    $h .=   '</div>';
    // right: facts panel
    $facts = array();
    if ($kind === 'slot' && $meta) {
      if ($provName !== '')               $facts[] = array('Studio', $provUrl !== '' ? '<a href="' . $esc($provUrl) . '">' . $esc($provName) . '</a>' : $esc($provName));
      if (!empty($meta['release_date']))  $facts[] = array('Release', $esc(fec_blog_date_long($meta['release_date'])));
      if ($meta['rtp'] !== null && $meta['rtp'] !== '')           $facts[] = array('RTP', $esc(rtrim(rtrim(number_format((float) $meta['rtp'], 2, '.', ''), '0'), '.')) . '%');
      if (!empty($meta['volatility']))    $facts[] = array('Volatility', $esc(ucfirst($meta['volatility'])));
      if (!empty($meta['max_win_x']))     $facts[] = array('Max win', $esc(number_format((int) $meta['max_win_x'])) . 'x');
      if (!empty($meta['grid']))          $facts[] = array('Grid', $esc($meta['grid']));
    }
    $h .=   '<aside class="hub-meta fec-blog-meta">';
    if ($imgPath !== '') {
      $h .= '<div class="fec-blog-figure"><img src="' . $esc(fec_blog_image_src($imgPath, 800, 450)) . '" alt="' . $esc($title) . '" width="800" height="450" fetchpriority="high"></div>';
    }
    if ($facts) {
      $h .= '<div><span class="eyebrow">Game facts</span><ul class="hub-meta-list">';
      foreach ($facts as $f) $h .= '<li><span class="k">' . $esc($f[0]) . '</span><span class="v">' . $f[1] . '</span></li>';
      $h .= '</ul></div>';
      if ($vSlot) $h .= fec_blog_render_verified_box($vSlot, $vRel);
      $h .= '<div class="hub-tip"><b>Source</b>' . (($vSlot && $vRel === 'game')
            ? 'Game facts as stated by the studio at release; the verified box is our own reading of the primary source. Casino operators can run lower RTP settings: check the paytable in the lobby before you play.'
            : 'Figures as stated by the studio at release. Casino operators can run lower RTP settings: check the paytable in the lobby before you play.') . '</div>';
    } elseif ($vSlot) {
      $h .= fec_blog_render_verified_box($vSlot, $vRel);
    } elseif ($imgPath === '') {
      $h .= '<ul class="hub-meta-list">';
      if ($createdLabel !== '') $h .= '<li><span class="k">Published</span><span class="v">' . $esc($createdLabel) . '</span></li>';
      $h .= '<li><span class="k">Section</span><span class="v">' . $esc($kindLabel) . '</span></li>';
      if ($provName !== '') $h .= '<li><span class="k">Studio</span><span class="v">' . $esc($provName) . '</span></li>';
      $h .= '</ul>';
    }
    $h .=   '</aside>';
    $h .= '</div></div></section>';

    // ---------------- body ----------------
    $h .= '<section class="section fec-blog-body"><div class="wrap">';
    $h .=   '<div class="hub-longform' . ($hasToc ? '' : ' fec-blog-notoc') . '">';
    if ($hasToc) {
      $h .= '<aside class="hub-toc"><div class="lbl">On this page</div><ul>';
      foreach ($toc as $t) $h .= '<li><a href="#' . $esc($t['id']) . '">' . $esc($t['label']) . '</a></li>';
      $h .= '</ul></aside>';
    }
    $h .=     '<article class="hub-content fec-blog-content">' . $body . '</article>';
    $h .=   '</div>';
    if ($hasToc && function_exists('fec_hub_longform_iife')) $h .= fec_hub_longform_iife();

    // author card (lib renderer, same as reviews and clusters)
    if ($author && function_exists('fec_render_author_card')) {
      $cardData = array('id' => $author['id'], 'slug' => $author['is_org'] ? '' : $author['slug'], 'name' => $author['name'],
                        'role' => $author['role'], 'short_bio' => $author['short_bio'], 'avatar_url' => $author['avatar_url']);
      $card = fec_render_author_card($cardData);
      $prefix = '<div class="wrap">';
      if (strpos($card, $prefix) === 0 && substr($card, -6) === '</div>') $card = substr($card, strlen($prefix), -6);
      if ($card !== '') $h .= '<div class="fec-blog-author">' . $card . '</div>';
    }
    $h .= '</div></section>';

    // ---------------- read next ----------------
    $related = array();
    try {
      if ($provSlug !== '') $related = fec_blog_fetch_posts($db, array('limit' => 3, 'exclude' => $id, 'provider_slug' => $provSlug));
      if (count($related) < 3) {
        $more = fec_blog_fetch_posts($db, array('limit' => 6, 'exclude' => $id, 'kind' => $kind));
        foreach ($more as $m) {
          $dup = false; foreach ($related as $r) if ((int) $r['id'] === (int) $m['id']) { $dup = true; break; }
          if (!$dup) $related[] = $m;
          if (count($related) >= 3) break;
        }
      }
      if (count($related) < 3) {
        $more = fec_blog_fetch_posts($db, array('limit' => 6, 'exclude' => $id));
        foreach ($more as $m) {
          $dup = false; foreach ($related as $r) if ((int) $r['id'] === (int) $m['id']) { $dup = true; break; }
          if (!$dup) $related[] = $m;
          if (count($related) >= 3) break;
        }
      }
    } catch (Exception $e) { $related = array(); }
    if ($related) {
      $h .= '<section class="section fec-blog-related" aria-label="Read next"><div class="wrap">';
      $h .=   '<div class="sec-head"><div class="left"><span class="eyebrow">Read next</span>'
           .  '<h2 class="sec-title">' . ($provName !== '' ? 'More on ' . $esc($provName) : 'More from the blog') . '</h2></div>'
           .  '<a class="sec-link" href="' . $esc($indexUrl) . '">All posts <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a></div>';
      $h .=   fec_blog_render_cards(array_slice($related, 0, 3), array('grid_class' => 'fec-blog-grid'));
      $h .= '</div></section>';
    }

    $h .= '</div><!-- /.fecx.fec-blog -->';
    return $h;
  }
}

/* =====================================================================
 * Listing page
 * ===================================================================== */

if (!function_exists('fec_blog_render_listing')) {
  /**
   * Category index: newest first, FEC_BLOG_PER_PAGE per page, ?start=N
   * pagination (same pattern and guard as /online-casinos).
   * $category: the Joomla category object ($this->category). $opts: Itemid
   */
  function fec_blog_render_listing($db, $category, $opts = array()) {
    fec_blog_add_assets();
    $esc = 'fec_blog_esc';
    $app = class_exists('JFactory') ? JFactory::getApplication() : null;
    $input = is_object($app) ? $app->input : null;
    $Itemid = isset($opts['Itemid']) ? (int) $opts['Itemid'] : 0;
    // Display name is the constant, not the category title: sh404SEF builds the
    // SEF path from the category title, so during staging the category is
    // renamed (e.g. "Blog v2") without that leaking into H1, <title> or crumbs.
    $catTitle = FEC_BLOG_NAME;
    $catDesc  = (is_object($category) && !empty($category->description)) ? trim((string) $category->description) : '';

    $perPage = (int) FEC_BLOG_PER_PAGE;
    $start = is_object($input) ? max(0, (int) $input->getInt('start', 0)) : 0;
    $start = (int) (floor($start / $perPage) * $perPage);
    $page  = (int) floor($start / $perPage) + 1;

    $total = fec_blog_count_posts($db);
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    if ($page > $totalPages) { $page = $totalPages; $start = ($page - 1) * $perPage; }
    $isFirst = ($page === 1);

    $indexUrl = fec_blog_index_route($Itemid);
    $staging = fec_blog_is_staging();

    // canonical / redirect guard (runs before any output)
    if (function_exists('fec_canonical_page_guard')) {
      fec_canonical_page_guard(array(
        'base_path'   => rtrim($indexUrl, '/') !== '' ? rtrim($indexUrl, '/') : $indexUrl,
        'allow_page'  => false,
        'allow_start' => true,
        'per_page'    => $perPage,
        'total_items' => $total,
        'noindex'     => $staging,
      ));
    }

    $rows = fec_blog_fetch_posts($db, array('limit' => $perPage, 'offset' => $start));

    // head
    $pageTitle = $isFirst ? $catTitle . ' | ' . FEC_BLOG_SITE_NAME : $catTitle . ' - Page ' . $page . ' | ' . FEC_BLOG_SITE_NAME;
    $pageDesc  = $catDesc !== '' ? fec_blog_excerpt($catDesc, 155)
      : 'New slot releases, live casino launches and jackpot news from the ' . FEC_BLOG_SITE_NAME . ' desk, with the numbers behind each game.';
    $head = array('title' => $pageTitle, 'description' => $pageDesc, 'og' => array(
      'og:type' => 'website', 'og:title' => $pageTitle, 'og:description' => $pageDesc,
      'og:url' => rtrim(FEC_BLOG_SITE_URL, '/') . FEC_BLOG_PATH . ($isFirst ? '' : '?start=' . $start), 'og:site_name' => FEC_BLOG_SITE_NAME,
    ), 'twitter' => array('twitter:card' => 'summary', 'twitter:title' => $pageTitle, 'twitter:description' => $pageDesc));
    if ($isFirst) {
      $head['jsonld'] = array(array(
        '@context' => 'https://schema.org', '@type' => 'Blog',
        '@id' => FEC_BLOG_SITE_URL . FEC_BLOG_PATH . '#blog',
        'name' => $catTitle, 'url' => FEC_BLOG_SITE_URL . FEC_BLOG_PATH, 'description' => $pageDesc,
        'publisher' => array('@type' => 'Organization', '@id' => FEC_BLOG_SITE_URL . '/#organization', 'name' => FEC_BLOG_SITE_NAME, 'url' => FEC_BLOG_SITE_URL . '/'),
        'inLanguage' => 'en',
      ));
    }
    // the guard already set canonical (self for deep ?start=N) and robots on staging
    fec_blog_push_head($head);

    // breadcrumb + ItemList
    $crumbs = array(array('name' => 'Home', 'url' => '/'), array('name' => FEC_BLOG_CRUMB, 'url' => $indexUrl));
    $crumbHtml = function_exists('fec_render_breadcrumb_html') ? fec_render_breadcrumb_html($crumbs) : '';
    $crumbLd   = function_exists('fec_render_breadcrumb_jsonld') ? fec_render_breadcrumb_jsonld($crumbs) : '';
    $itemListLd = '';
    if ($isFirst && function_exists('fec_render_list_itemlist_jsonld') && $rows) {
      $urls = array();
      foreach ($rows as $r) $urls[] = rtrim(FEC_BLOG_SITE_URL, '/') . fec_blog_route((int) $r['id'], $r['alias'], (int) $r['catid']);
      $itemListLd = fec_render_list_itemlist_jsonld($urls, array('title' => $catTitle, 'per_page' => $perPage, 'offset' => 0));
    }

    // pagination (?start=N through the router, like the casino listing)
    $urlFor = function ($p) use ($Itemid, $perPage) {
      $q = 'index.php?option=com_content&view=category&id=' . (int) FEC_BLOG_CATID;
      if ($Itemid > 0) $q .= '&Itemid=' . (int) $Itemid;
      if ($p > 1) $q .= '&start=' . (($p - 1) * $perPage);
      $u = class_exists('JRoute') ? JRoute::_($q) : $q;
      if (!is_string($u) || $u === '' || stripos($u, 'index.php') !== false) {
        $u = rtrim(fec_blog_index_route($Itemid), '/') . ($p > 1 ? '?start=' . (($p - 1) * $perPage) : '');
      }
      return $u;
    };
    $pagHtml = function_exists('fec_render_listing_pagination')
      ? fec_render_listing_pagination($total, $page, $perPage, $indexUrl, array('url_builder' => $urlFor)) : '';

    // ---------------- markup ----------------
    $h  = '<div class="fecx fec-blog">';
    $h .= $crumbHtml . $crumbLd . $itemListLd;
    $h .= '<section class="section section-tight fec-blog-head"><div class="wrap">';
    $h .=   '<div class="sec-head"><div class="left">';
    $h .=     '<span class="eyebrow">' . FEC_BLOG_SITE_NAME . ' editorial</span>';
    $h .=     '<h1 class="sec-title">' . $esc($catTitle) . ($isFirst ? '' : ' <span class="fec-blog-pageno">&middot; page ' . (int) $page . '</span>') . '</h1>';
    if ($isFirst) {
      $h .=   ($catDesc !== '') ? '<div class="sec-sub fec-blog-desc">' . $catDesc . '</div>' : '<p class="sec-sub">' . $esc($pageDesc) . '</p>';
    }
    $h .=   '</div></div>';
    $h .=   '<div class="fec-minirow" role="region" aria-label="Blog status"><div class="fec-minirow__inner">'
         .    '<div class="fec-minirow__left"><span class="fec-minirow__item">Posts: <strong>' . (int) $total . '</strong></span>'
         .    ($rows ? '<span class="fec-minirow__dot" aria-hidden="true">&bull;</span><span class="fec-minirow__item">Latest: <strong>' . $esc(fec_blog_date_long($rows[0]['created'])) . '</strong></span>' : '')
         .    '</div><div class="fec-minirow__right"><a class="fec-minirow__link" href="/authors">Who writes here</a></div>'
         .  '</div></div>';
    $h .= '</div></section>';
    $h .= '<section class="section fec-blog-list"><div class="wrap">';
    $h .=   $rows ? fec_blog_render_cards($rows, array('lead' => $isFirst, 'grid_class' => 'fec-blog-grid')) : '<p class="sec-sub">No posts yet.</p>';
    if ($pagHtml !== '') $h .= '<div class="fec-blog-pager">' . $pagHtml . '</div>';
    $h .= '</div></section>';
    $h .= '</div><!-- /.fecx.fec-blog -->';
    return $h;
  }
}
