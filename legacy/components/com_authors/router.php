<?php
defined('_JEXEC') or die;

/**
 * Front-end SEF router for com_authors.
 *
 * URL map:
 *   /authors                       → view=authors
 *   /authors/<slug>                → view=author&slug=<slug>
 *   /methodology                   → view=methodologyhub (hub overview)
 *   /methodology/<topic>           → view=methodology&topic=<topic>
 *   /page/<slug>                   → view=page&slug=<slug>
 *   /editorial/dashboard           → view=dashboard (V2.4, gated to user 42)
 *   /methodology-admin             → view=methodologyadmin (V2.7.5, gated)
 *
 * Note: /how-we-verify and /responsible-gambling are NOT routed here — they
 * are Joomla menu aliases that the site admin points at view=methodologyhub
 * and view=page&slug=responsible-gambling respectively. Joomla's SEF layer
 * handles the alias resolution; this router only emits clean segments from
 * the component side when no menu alias matches.
 *
 * In practice, /page/<slug> exists as a fallback URL for editorial pages
 * BEFORE the menu item has been wired. Once the alias is created in Menu
 * Manager, Joomla emits the alias URL instead.
 *
 * The dashboard URL is intentionally non-SEF by default — the editorial
 * tool isn't meant to be linked. Access via /index.php?option=com_authors&view=dashboard
 * or wire a hidden menu item.
 *
 * Adding a new methodology topic or editorial page is config-only: an entry
 * in AuthorsModelMethodology::getTopics() / AuthorsModelPage::getTopics()
 * + a tmpl/default_<slug>.php template.
 */

function AuthorsBuildRoute(&$query)
{
  $segments = array();

  if (!isset($query['view'])) {
    return $segments;
  }

  // /authors
  if ($query['view'] === 'authors') {
    $segments[] = 'authors';
    unset($query['view']);
    return $segments;
  }

  // /authors/{slug}
  if ($query['view'] === 'author') {
    $segments[] = 'authors';
    unset($query['view']);

    if (isset($query['slug'])) {
      $segments[] = $query['slug'];
      unset($query['slug']);
    }

    return $segments;
  }

  // /methodology (hub)
  if ($query['view'] === 'methodologyhub') {
    $segments[] = 'methodology';
    unset($query['view']);
    return $segments;
  }

  // /methodology/{topic}
  if ($query['view'] === 'methodology') {
    $segments[] = 'methodology';
    unset($query['view']);

    if (isset($query['topic'])) {
      $segments[] = $query['topic'];
      unset($query['topic']);
    }

    return $segments;
  }

  // /page/{slug} — editorial / trust / public-service pages.
  // This is the fallback URL when no menu alias has been wired. Once the
  // admin creates a menu item with alias 'responsible-gambling' pointing at
  // view=page&slug=responsible-gambling, Joomla emits /responsible-gambling
  // and this branch isn't reached (menu alias resolution precedes router).
  if ($query['view'] === 'page') {
    $segments[] = 'page';
    unset($query['view']);

    if (isset($query['slug'])) {
      $segments[] = $query['slug'];
      unset($query['slug']);
    }

    // 2.7.55: Payout Index monthly editions ride on the fast-payouts anchor:
    // /online-casinos/fast-payouts/YYYY-MM. Templates link editions with the
    // hardcoded path (AuthorsModelPayoutindex::editionPath), this branch only
    // keeps JRoute from leaking ?edition= as a query string.
    if (isset($query['edition']) && preg_match('/^\d{4}-\d{2}$/', (string)$query['edition'])) {
      $segments[] = $query['edition'];
      unset($query['edition']);
    }

    return $segments;
  }

  // /slots — index of published slot pages (V2.6).
  // Optional provider filter as /slots/p/<provider-slug>.
  if ($query['view'] === 'slots') {
    $segments[] = 'slots';
    unset($query['view']);
    if (isset($query['provider']) && $query['provider'] !== '') {
      $segments[] = 'p';
      $segments[] = $query['provider'];
      unset($query['provider']);
    }
    return $segments;
  }

  // /slots/{slug} — single slot detail page
  if ($query['view'] === 'slot') {
    $segments[] = 'slots';
    unset($query['view']);
    if (isset($query['slug'])) {
      $segments[] = $query['slug'];
      unset($query['slug']);
    }
    return $segments;
  }

  // /methodology-admin — admin view for methodology topic metadata.
  // Gated to DASHBOARD_ALLOWED_USERS. Single-segment URL to avoid conflict
  // with the Joomla menu alias 'editorial' (which would otherwise capture
  // /editorial/* paths and force view=authors before this router runs).
  // Same pattern as the /slots/p/<provider> path-routing fix in V2.6.x.
  if ($query['view'] === 'methodologyadmin') {
    $segments[] = 'methodology-admin';
    unset($query['view']);
    return $segments;
  }

  // FXShield admin views (fxshieldadmin, verification) are intentionally
  // NON-SEF: internal backend, raw index.php?option=com_authors URLs. They are
  // not routed here, so sh404SEF/native routing leaves them untouched — the
  // same treatment com_bonuses gives its private dashboard tasks.

  return $segments;
}

function AuthorsParseRoute($segments)
{
  $vars = array();
  $count = count($segments);

  // /authors
  if ($count === 1 && $segments[0] === 'authors') {
    $vars['view'] = 'authors';
    return $vars;
  }

  // /authors/{slug}
  if ($count >= 2 && $segments[0] === 'authors') {
    $vars['view'] = 'author';
    $vars['slug'] = $segments[1];
    return $vars;
  }

  // /methodology → hub
  if ($count === 1 && $segments[0] === 'methodology') {
    $vars['view'] = 'methodologyhub';
    return $vars;
  }

  // /methodology/{topic}
  if ($count >= 2 && $segments[0] === 'methodology') {
    $vars['view']  = 'methodology';
    $vars['topic'] = $segments[1];
    return $vars;
  }

  // Page topics that publish dated editions (2.7.55 Payout Index, 2.7.61
  // Licence Watch). Both use the YYYY-MM stamp as the trailing segment.
  $editionTopics = array('fast-payouts', 'licence-watch');

  // /page/{slug} — editorial pages fallback URL
  // /page/{slug}/{YYYY-MM} — an edition of a serial page topic
  if ($count >= 2 && $segments[0] === 'page') {
    $vars['view'] = 'page';
    $vars['slug'] = $segments[1];
    if ($count >= 3 && in_array($segments[1], $editionTopics, true) && preg_match('/^\d{4}-\d{2}$/', (string)$segments[2])) {
      $vars['edition'] = $segments[2];
    }
    return $vars;
  }

  // /online-casinos/{topic}/{YYYY-MM} — the menu item for the live page is
  // the anchor, Joomla hands us only the trailing segment (same mechanism as
  // /slots/{slug} under the slots menu item). The topic comes from the active
  // menu item's own slug (Joomla sets the active item before calling us);
  // fast-payouts stays the fallback for menu items without a slug.
  if ($count === 1 && preg_match('/^\d{4}-\d{2}$/', (string)$segments[0])) {
    $slug = 'fast-payouts';
    try {
      $active = JFactory::getApplication()->getMenu()->getActive();
      if ($active && isset($active->query['slug']) && in_array((string)$active->query['slug'], $editionTopics, true)) {
        $slug = (string)$active->query['slug'];
      }
    } catch (Exception $e) {}
    $vars['view']    = 'page';
    $vars['slug']    = $slug;
    $vars['edition'] = $segments[0];
    return $vars;
  }

  // /slots → index page (V2.6)
  if ($count === 1 && $segments[0] === 'slots') {
    $vars['view'] = 'slots';
    return $vars;
  }

  // /slots/{slug} → single slot detail page
  // (must be BEFORE the /slots/p/<provider> branch because /slots/p alone
  // would otherwise be misread; here we check exact 2 segments)
  if ($count >= 2 && $segments[0] === 'slots' && $segments[1] !== 'p') {
    $vars['view'] = 'slot';
    $vars['slug'] = $segments[1];
    return $vars;
  }

  // /slots/p/<provider-slug> → filtered index
  if ($count >= 3 && $segments[0] === 'slots' && $segments[1] === 'p') {
    $vars['view'] = 'slots';
    $vars['provider'] = $segments[2];
    return $vars;
  }

  // /methodology-admin → methodology topic admin (V2.7.5)
  // Must come BEFORE the count===1 author fallback below, otherwise the
  // segment would be misread as an author slug.
  if ($count === 1 && $segments[0] === 'methodology-admin') {
    $vars['view'] = 'methodologyadmin';
    return $vars;
  }


  // Fallback for menu items that hit a bare slug
  if ($count === 1) {
    $vars['view'] = 'author';
    $vars['slug'] = $segments[0];
    return $vars;
  }

  $vars['view'] = 'authors';
  return $vars;
}
