<?php
defined('_JEXEC') or die;

/**
 * atlas_route() — deterministic SEF builder for internal Atlas links.
 *
 * v2: sh404SEF normalises the Itemid away when building SEF URLs, so simply
 * appending Itemid (v1) is not enough — its cache keeps serving root-level
 * URLs like /changelog. This version builds the path DIRECTLY from the
 * com_atlas menu item route (e.g. online-casinos/operators), so the emitted
 * href is correct regardless of which SEF extension sits in the middle:
 *
 *   view=operators              -> /<menu-route>
 *   view=changelog              -> /<menu-route>/changelog
 *   view=operator&slug=<slug>   -> /<menu-route>/<slug>
 *
 * Inbound parsing stays with router.php via the Joomla router (menu match
 * plus trailing segment). Fallback: JRoute with pinned Itemid when no menu
 * item exists yet or SEF is off.
 */
if (!function_exists('atlas_route')) {
    function atlas_route($query)
    {
        static $item = null, $resolved = false;
        if (!$resolved) {
            $resolved = true;
            $app = JFactory::getApplication();
            if (method_exists($app, 'getMenu')) {
                $menu = $app->getMenu();
                if ($menu) {
                    $items = $menu->getItems('component', 'com_atlas');
                    if (is_array($items) && count($items)) {
                        // Prefer the PUBLIC menu item (view=operators): the
                        // component can legitimately have a second menu item
                        // for the dashboard, and picking that one would emit
                        // hrefs under the dashboard alias.
                        foreach ($items as $it) {
                            if (strpos((string)$it->link, 'view=operators') !== false) {
                                $item = $it;
                                break;
                            }
                        }
                        if ($item === null) $item = $items[0];
                    }
                }
            }
        }

        $vars = array();
        $pos = strpos($query, '?');
        parse_str($pos !== false ? substr($query, $pos + 1) : $query, $vars);
        $view = isset($vars['view']) ? $vars['view'] : '';
        $slug = isset($vars['slug']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($vars['slug'])) : '';

        $config = JFactory::getConfig();
        $sef = (int)$config->get('sef');

        if ($item && $sef && in_array($view, array('operators', 'changelog', 'operator'), true)) {
            $rewrite = (int)$config->get('sef_rewrite');
            $base = JURI::root(true) . '/' . ($rewrite ? '' : 'index.php/') . $item->route;
            if ($view === 'changelog') return $base . '/changelog';
            if ($view === 'operator' && $slug !== '') return $base . '/' . $slug;
            return $base;
        }

        if ($item && strpos($query, 'Itemid=') === false) {
            $query .= '&Itemid=' . (int)$item->id;
        }
        return JRoute::_($query);
    }
}
