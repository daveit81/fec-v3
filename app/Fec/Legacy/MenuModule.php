<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\Menu;
use App\Fec\Legacy\Joomla\ModuleHelper;
use App\Fec\Legacy\Joomla\Registry;
use App\Fec\Legacy\Joomla\Route;
use App\Fec\Legacy\Joomla\Runtime;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The primary navigation as the live site renders it: the mod_menu module of
 * the fec_nav position ("Fec Nav", menutype mainmenu), its parameters from
 * jos_modules, its items from jos_menu, its markup from the fecv2 template's
 * override (templates/fecv2/html/mod_menu, verbatim). Replaces the snapshot
 * of 25 Oct 2026 (partials/nav-static): an item edited in Joomla's menu
 * manager shows on v3 as on the live site.
 *
 * getList() is Joomla 2.5's ModMenuHelper::getList(), render() its
 * mod_menu.php: the module's cache is not reproduced (it only saved the
 * work). Items are filtered by the guest's view levels, as JMenuSite's
 * getItems() did on the site.
 */
final class MenuModule
{
    public static function render(): ?string
    {
        try {
            Runtime::environment();
            $menu = Factory::getApplication()->getMenu();
            $params = new Registry(self::moduleParams());

            return self::renderWith($menu, $params);
        } catch (Throwable $e) {
            error_log('legacy mod_menu failed, the snapshot nav is served: '.$e->getMessage());

            return null;
        }
    }

    /** mod_menu.php, with the menu and the module's params given. */
    public static function renderWith(Menu $menu, Registry $params): string
    {
        Runtime::environment();
        $list = self::getList(
            $menu,
            $params,
            Factory::getUser()->getAuthorisedViewLevels(),
            fn (string $url, bool $xhtml = true, ?int $ssl = null): string => Route::_($url, $xhtml, $ssl),
        );
        $active = $menu->getActive();
        $active_id = isset($active) ? $active->id : ($menu->getDefault()?->id ?? 0);
        $path = isset($active) ? $active->tree : [];
        $showAll = $params->get('showAllChildren');
        $class_sfx = htmlspecialchars((string) $params->get('class_sfx'));

        if (! count($list)) {
            return '';
        }
        ob_start();
        try {
            require ModuleHelper::getLayoutPath('mod_menu', (string) $params->get('layout', 'default'));
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    /**
     * ModMenuHelper::getList() of Joomla 2.5. $levels are the user's view levels
     * (JMenuSite::getItems() added them on the site), $route is JRoute::_().
     *
     * @param  list<int>  $levels
     * @param  callable(string, bool=, ?int=): string  $route
     * @return array<int, object>
     */
    public static function getList(Menu $menu, Registry $params, array $levels, callable $route): array
    {
        // If no active menu, use default
        $active = $menu->getActive() ?? $menu->getDefault();
        $path = $active->tree ?? [];
        $start = (int) $params->get('startLevel');
        $end = (int) $params->get('endLevel');
        $showAll = $params->get('showAllChildren');
        $items = $menu->getItems(['menutype', 'access'], [$params->get('menutype'), $levels]);
        $lastitem = 0;

        if ($items) {
            foreach ($items as $i => $item) {
                $item = clone $item; // the menu's own objects stay as JMenu loaded them
                $items[$i] = $item;
                if (($start && $start > $item->level)
                    || ($end && $item->level > $end)
                    || (! $showAll && $item->level > 1 && ! in_array($item->parent_id, $path))
                    || ($start > 1 && ! in_array($item->tree[$start - 2] ?? null, $path))
                ) {
                    unset($items[$i]);

                    continue;
                }

                $item->deeper = false;
                $item->shallower = false;
                $item->level_diff = 0;

                if (isset($items[$lastitem])) {
                    $items[$lastitem]->deeper = ($item->level > $items[$lastitem]->level);
                    $items[$lastitem]->shallower = ($item->level < $items[$lastitem]->level);
                    $items[$lastitem]->level_diff = ($items[$lastitem]->level - $item->level);
                }

                $item->parent = (bool) $menu->getItems(['parent_id', 'access'], [(int) $item->id, $levels], true);

                $lastitem = $i;
                $item->active = false;
                $item->flink = $item->link;

                switch ($item->type) {
                    case 'separator':
                        // No further action needed. (Joomla wrote "continue": inside a switch PHP
                        // reads it as "break", so a separator goes on to the lines after the switch.)
                        break;

                    case 'url':
                        if ((str_starts_with($item->link, 'index.php?')) && (! str_contains($item->link, 'Itemid='))) {
                            // If this is an internal Joomla link, ensure the Itemid is set.
                            $item->flink = $item->link.'&Itemid='.$item->id;
                        }
                        break;

                    case 'alias':
                        // If this is an alias use the item id stored in the parameters to make the link.
                        $item->flink = 'index.php?Itemid='.$item->params->get('aliasoptions');
                        break;

                    default:
                        // the router is in SEF mode on the live site
                        $item->flink = 'index.php?Itemid='.$item->id;
                        break;
                }

                if (strcasecmp(substr($item->flink, 0, 4), 'http') && str_contains($item->flink, 'index.php?')) {
                    $secure = $item->params->get('secure');
                    $item->flink = $route($item->flink, true, is_numeric($secure) ? (int) $secure : null);
                } else {
                    $item->flink = $route($item->flink);
                }

                $item->title = htmlspecialchars((string) $item->title);
                $item->anchor_css = htmlspecialchars((string) $item->params->get('menu-anchor_css', ''));
                $item->anchor_title = htmlspecialchars((string) $item->params->get('menu-anchor_title', ''));
                $item->menu_image = $item->params->get('menu_image', '') ? htmlspecialchars((string) $item->params->get('menu_image', '')) : '';
            }

            if (isset($items[$lastitem])) {
                $items[$lastitem]->deeper = (($start ?: 1) > $items[$lastitem]->level);
                $items[$lastitem]->shallower = (($start ?: 1) < $items[$lastitem]->level);
                $items[$lastitem]->level_diff = ($items[$lastitem]->level - ($start ?: 1));
            }
        }

        return $items;
    }

    /** The params of the published mod_menu module in the fec_nav position (first by ordering). */
    private static function moduleParams(): string
    {
        $row = DB::connection('fec')->table('modules')
            ->where('module', 'mod_menu')->where('position', (string) config('fec.legacy.nav_position', 'fec_nav'))
            ->where('published', 1)->where('client_id', 0)
            ->orderBy('ordering')->orderBy('id')
            ->first(['params']);

        return $row !== null ? (string) $row->params : '{"menutype":"mainmenu","showAllChildren":"1"}';
    }
}
