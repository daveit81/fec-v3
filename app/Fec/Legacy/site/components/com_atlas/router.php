<?php
defined('_JEXEC') or die;

/**
 * Front-end SEF router for com_atlas.
 *
 * URL map (design doc D1, locked 2026-08-03):
 *   /operators                → view=operators   (index of published entities)
 *   /operators/changelog      → view=changelog   (published events, all entities)
 *   /operators/<slug>         → view=operator&slug=<slug>
 *
 * The dashboard URL is intentionally non-SEF — the editorial tool is not
 * meant to be linked. Access via /index.php?option=com_atlas&view=dashboard.
 *
 * Wiring: create a Joomla menu item with alias "operators" pointing at
 * view=operators. Joomla emits /operators as the base; this router appends
 * the remaining segments. sh404SEF then picks the URLs up as usual.
 */

function AtlasBuildRoute(&$query)
{
    $segments = array();

    if (!isset($query['view'])) {
        return $segments;
    }

    if ($query['view'] === 'operators') {
        unset($query['view']);
        return $segments;
    }

    if ($query['view'] === 'changelog') {
        $segments[] = 'changelog';
        unset($query['view']);
        return $segments;
    }

    if ($query['view'] === 'operator') {
        unset($query['view']);
        if (isset($query['slug'])) {
            $segments[] = $query['slug'];
            unset($query['slug']);
        }
        return $segments;
    }

    return $segments;
}

function AtlasParseRoute($segments)
{
    $vars = array();

    if (empty($segments)) {
        $vars['view'] = 'operators';
        return $vars;
    }

    $first = str_replace(':', '-', $segments[0]);

    if ($first === 'changelog') {
        $vars['view'] = 'changelog';
        return $vars;
    }

    $vars['view'] = 'operator';
    $vars['slug'] = $first;
    return $vars;
}
