<?php
defined('_JEXEC') or die;

/**
 * Helper utilities shared across com_authors views/templates.
 *
 * V2 adds methodologyUrl($topic) so other components (com_bonuses, modules,
 * the FXCheck status badge) can link to the methodology page without
 * hard-coding the route.
 */
class AuthorsHelper
{
  public static function baseUrl()
  {
    return rtrim(JUri::root(), '/');
  }

  // Find the Itemid of the menu item pointing at view=authors. Used so
  // SEF URLs keep their canonical menu context. Returns 0 if no menu
  // item is mapped to the team listing.
  public static function getAuthorsItemid()
  {
    $app  = JFactory::getApplication();
    $menu = $app->getMenu();
    $items = $menu->getItems('link', 'index.php?option=com_authors&view=authors');

    if (!empty($items) && !empty($items[0]->id)) {
      return (int)$items[0]->id;
    }
    return 0;
  }

  // Find the Itemid for the methodology menu item, if one was created
  // by the site admin. Optional — methodology pages route fine without it,
  // they just don't share an Itemid with the rest of the section.
  public static function getMethodologyItemid()
  {
    $app  = JFactory::getApplication();
    $menu = $app->getMenu();
    $items = $menu->getItems('link', 'index.php?option=com_authors&view=methodology');

    if (!empty($items) && !empty($items[0]->id)) {
      return (int)$items[0]->id;
    }
    return 0;
  }

  // Find the Itemid for the methodology hub menu item (e.g., /how-we-verify),
  // if one was created by the site admin. Optional — works without it.
  public static function getMethodologyHubItemid()
  {
    $app  = JFactory::getApplication();
    $menu = $app->getMenu();
    $items = $menu->getItems('link', 'index.php?option=com_authors&view=methodologyhub');

    if (!empty($items) && !empty($items[0]->id)) {
      return (int)$items[0]->id;
    }
    return 0;
  }

  public static function authorUrl($slug)
  {
    $itemid = self::getAuthorsItemid();

    $link = 'index.php?option=com_authors&view=author&slug=' . urlencode($slug);
    if ($itemid > 0) $link .= '&Itemid=' . $itemid;

    return JRoute::_($link);
  }

  public static function teamUrl()
  {
    $itemid = self::getAuthorsItemid();

    $link = 'index.php?option=com_authors&view=authors';
    if ($itemid > 0) $link .= '&Itemid=' . $itemid;

    return JRoute::_($link);
  }

  /**
   * Build a URL to a methodology page.
   *
   * @param string $topic  Topic slug; defaults to 'fxcheck'. Must match
   *                       a key in AuthorsModelMethodology::getTopics().
   */
  public static function methodologyUrl($topic = 'fxcheck')
  {
    $itemid = self::getMethodologyItemid();

    $link = 'index.php?option=com_authors&view=methodology&topic=' . urlencode($topic);
    if ($itemid > 0) $link .= '&Itemid=' . $itemid;

    return JRoute::_($link);
  }

  /**
   * Build a URL to the methodology hub (canonical entry point for trust).
   * Typically displayed under the menu alias /how-we-verify if the admin
   * has wired the menu item that way.
   */
  public static function methodologyHubUrl()
  {
    $itemid = self::getMethodologyHubItemid();

    $link = 'index.php?option=com_authors&view=methodologyhub';
    if ($itemid > 0) $link .= '&Itemid=' . $itemid;

    return JRoute::_($link);
  }

  /**
   * v2.7.9 — Map a volatility ENUM slug to its display label.
   * Unknown values fall back to a safe ucwords/replace formatting so future
   * additions don't render as raw slugs.
   */
  public static function volatilityLabel($slug)
  {
    $slug = strtolower(trim((string)$slug));
    if ($slug === '') return '';
    $map = array(
      'low'        => 'Low',
      'low-med'    => 'Low-Medium',
      'medium'     => 'Medium',
      'med-high'   => 'Medium-High',
      'high'       => 'High',
      'very-high'  => 'Very High',
      'extreme'    => 'Extreme',
    );
    if (isset($map[$slug])) return $map[$slug];
    return ucwords(str_replace(array('-', '_'), ' ', $slug));
  }

  /* ------------------------------------------------------------------------
   * V2.7.65 — Casino Bonus Blog (com_content category rendered by the fecv2
   * overrides + components/com_bonuses/helpers/fec_blog.php).
   *
   * Blog posts are #__content rows in one category, authored by the Joomla
   * user mapped on the profile (joomla_user_id). The profile page lists them
   * under a "Blog posts" tab and the team card shows the latest ones.
   * ---------------------------------------------------------------------- */

  /**
   * Category id of the blog. fec_blog.php owns the value (FEC_BLOG_CATID);
   * the literal is the fallback when that helper is not loaded.
   */
  public static function blogCatid()
  {
    return defined('FEC_BLOG_CATID') ? (int)FEC_BLOG_CATID : 154;
  }

  /**
   * SQL predicate selecting the blog posts signed by an author, for a
   * #__content alias ($c = 'c' or '' for no alias). Posts are linked to the
   * profile through #__content.author_id (the FEC byline column that reviews
   * use too) when that column exists, so a post bylined "FreeExtraChips
   * Editorial" never lands on a person's profile; without the column the
   * link falls back to the Joomla user (created_by = joomla_user_id).
   * Returns '0' (no rows) when the author has neither key.
   */
  public static function blogOwnerWhere($authorId, $joomlaUserId, $c = 'c')
  {
    $authorId = (int)$authorId; $joomlaUserId = (int)$joomlaUserId;
    $pfx = ($c !== '') ? $c . '.' : '';
    if (self::contentHasAuthorColumn()) {
      return ($authorId > 0) ? $pfx . 'author_id = ' . $authorId : '0';
    }
    return ($joomlaUserId > 0) ? $pfx . 'created_by = ' . $joomlaUserId : '0';
  }

  /**
   * Does #__content carry the FEC author_id column? One query per request.
   */
  public static function contentHasAuthorColumn()
  {
    static $has = null;
    if ($has !== null) return $has;
    $db = JFactory::getDbo();
    $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName('#__content') . ' LIKE ' . $db->quote('author_id'));
    $row = $db->loadAssoc();
    $has = (!$db->getErrorNum() && is_array($row) && !empty($row));
    return $has;
  }

  /**
   * Load fec_blog.php (routes, labels, image resizing) when it is installed.
   * Returns true when the blog helper functions are available.
   */
  public static function loadBlogHelper()
  {
    if (function_exists('fec_blog_route')) return true;
    $f = JPATH_SITE . '/components/com_bonuses/helpers/fec_blog.php';
    if (file_exists($f)) require_once $f;
    return function_exists('fec_blog_route');
  }

  /**
   * SEF URL of a blog post. Prefers fec_blog_route() (getArticleRoute with
   * the blog menu context); falls back to the core content route.
   */
  public static function blogPostUrl($id, $alias, $catid = 0)
  {
    $id = (int)$id; $catid = (int)$catid ?: self::blogCatid();
    if (self::loadBlogHelper()) return fec_blog_route($id, $alias, $catid);
    if (!class_exists('ContentHelperRoute')) {
      $f = JPATH_SITE . '/components/com_content/helpers/route.php';
      if (file_exists($f)) require_once $f;
    }
    if (class_exists('ContentHelperRoute')) {
      return JRoute::_(ContentHelperRoute::getArticleRoute($id . ':' . $alias, $catid));
    }
    return JRoute::_('index.php?option=com_content&view=article&id=' . $id . ':' . $alias . '&catid=' . $catid);
  }

  /**
   * SEF URL of the blog index (/casino-bonus-blog).
   */
  public static function blogIndexUrl()
  {
    if (self::loadBlogHelper() && function_exists('fec_blog_index_route')) return fec_blog_index_route();
    return JRoute::_('index.php?option=com_content&view=category&id=' . self::blogCatid());
  }

  /**
   * Human label for a post row: provider name when known, else the kind
   * ("Slot release", "Jackpot", "Live casino", "News").
   */
  public static function blogPostLabel($row)
  {
    $prov = isset($row['provider_name']) ? trim((string)$row['provider_name']) : '';
    if ($prov !== '') return $prov;
    $kind = isset($row['kind']) ? (string)$row['kind'] : 'news';
    if (self::loadBlogHelper() && function_exists('fec_blog_kind_label')) return fec_blog_kind_label($kind);
    $map = array('slot' => 'Slot release', 'jackpot' => 'Jackpots', 'live' => 'Live casino', 'promo' => 'Promotions', 'news' => 'News');
    return isset($map[$kind]) ? $map[$kind] : 'News';
  }

  /**
   * Thumbnail <img> for a post row (Cloudflare-resized when fec_blog.php is
   * loaded), or '' when the post has no image.
   */
  public static function blogPostThumb($row, $w = 112, $h = 72)
  {
    if (!self::loadBlogHelper() || !function_exists('fec_blog_image_path')) return '';
    $path = fec_blog_image_path(isset($row['images']) ? $row['images'] : '');
    if ($path === '') return '';
    $src = fec_blog_image_src($path, (int)$w, (int)$h);
    $alt = isset($row['title']) ? (string)$row['title'] : '';
    return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
      . '" width="' . (int)$w . '" height="' . (int)$h . '" loading="lazy">';
  }

  /**
   * "6 Oct 2026" from a MySQL datetime; '' for the null date.
   */
  public static function blogDate($sql)
  {
    $sql = (string)$sql;
    if ($sql === '' || strpos($sql, '0000-00-00') === 0) return '';
    $ts = strtotime($sql);
    return $ts ? date('j M Y', $ts) : '';
  }
}
