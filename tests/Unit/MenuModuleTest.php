<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Menu;
use App\Fec\Legacy\Joomla\Registry;
use App\Fec\Legacy\MenuModule;
use PHPUnit\Framework\TestCase;

/** ModMenuHelper::getList() of Joomla 2.5 over a menu shaped like the live mainmenu (snapshot of 25 Oct 2026). */
final class MenuModuleTest extends TestCase
{
    private static function item(int $id, int $parent, int $level, string $title, string $type, string $link, int $access = 1, array $params = [], string $menutype = 'mainmenu'): object
    {
        $i = new \stdClass;
        $i->id = $id;
        $i->parent_id = $parent;
        $i->level = $level;
        $i->title = $title;
        $i->type = $type;
        $i->link = $link;
        $i->access = $access;
        $i->menutype = $menutype;
        $i->home = 0;
        $i->browserNav = 0;
        $i->params = new Registry(json_encode($params));
        parse_str((string) parse_url($link, PHP_URL_QUERY), $q);
        $i->query = $q;

        return $i;
    }

    private function menu(): Menu
    {
        return Menu::fromItems([
            self::item(26, 1, 1, 'Home', 'component', 'index.php?option=com_content&view=article&id=1', 1, [], 'hidden'),
            self::item(372, 1, 1, 'Bonuses', 'component', 'index.php?option=com_bonuses&view=show'),
            self::item(1007, 372, 2, 'All Bonuses', 'component', 'index.php?option=com_bonuses&view=show'),
            self::item(911, 372, 2, 'No Deposit', 'component', 'index.php?option=com_bonuses&view=show&type=nd'),
            self::item(1147, 1, 1, 'Slots', 'component', 'index.php?option=com_authors&view=slots'),
            self::item(557, 1, 1, 'Casinos', 'component', 'index.php?option=com_content&view=category&id=8'),
            self::item(426, 557, 2, 'Casino Reviews', 'component', 'index.php?option=com_content&view=category&id=8', 1, ['menu-anchor_css' => 'mega_menu_image casinos all-casinos', 'menu-anchor_title' => 'All Casinos']),
            self::item(999, 557, 2, 'Members only', 'component', 'index.php?option=com_users&view=profile', 2),
            self::item(452, 1, 1, 'About', 'separator', ''),
            self::item(985, 452, 2, 'How We Verify', 'component', 'index.php?option=com_authors&view=methodologyhub'),
            self::item(1116, 985, 3, 'FXCheck™', 'url', 'https://www.freeextrachips.com/methodology/fxcheck'),
            self::item(1079, 452, 2, 'Authors', 'component', 'index.php?option=com_authors&view=authors'),
            self::item(1261, 1, 1, 'Blog', 'component', 'index.php?option=com_content&view=category&id=154'),
        ]);
    }

    /** @return array<int, object> */
    private function list(Menu $menu): array
    {
        $paths = [372 => '/bonuses/', 1007 => '/bonuses/', 911 => '/bonuses/no-deposit-casinos', 1147 => '/slots', 557 => '/online-casinos', 426 => '/online-casinos', 985 => '/methodology', 1079 => '/authors', 1261 => '/blog/'];
        $route = function (string $url, bool $xhtml = true, ?int $ssl = null) use ($paths): string {
            if (preg_match('/^index\.php\?Itemid=(\d+)$/', $url, $m)) {
                return $paths[(int) $m[1]] ?? '/x';
            }

            return $url;
        };

        return array_values(MenuModule::getList($menu, new Registry('{"menutype":"mainmenu","showAllChildren":"1"}'), [1], $route));
    }

    public function test_the_menu_items_in_tree_order_with_the_guest_levels(): void
    {
        $list = $this->list($this->menu());
        $this->assertSame([372, 1007, 911, 1147, 557, 426, 452, 985, 1116, 1079, 1261], array_map(fn ($i) => $i->id, $list));
    }

    public function test_deeper_shallower_and_parent_as_mod_menu_computed_them(): void
    {
        $by = [];
        foreach ($this->list($this->menu()) as $i) {
            $by[$i->id] = $i;
        }
        $this->assertTrue($by[372]->deeper);
        $this->assertTrue($by[372]->parent);
        $this->assertTrue($by[911]->shallower);
        $this->assertSame(1, $by[911]->level_diff);
        $this->assertFalse($by[1147]->parent);
        // the registered-only child does not count, but the menu still has it: parent stays true
        $this->assertTrue($by[557]->parent);
        $this->assertTrue($by[1116]->shallower);
        $this->assertSame(1, $by[1116]->level_diff);
        $this->assertFalse($by[1261]->deeper);
        $this->assertFalse($by[1261]->shallower);
    }

    public function test_links_titles_and_anchor_attributes(): void
    {
        $by = [];
        foreach ($this->list($this->menu()) as $i) {
            $by[$i->id] = $i;
        }
        $this->assertSame('/bonuses/no-deposit-casinos', $by[911]->flink);
        $this->assertSame('https://www.freeextrachips.com/methodology/fxcheck', $by[1116]->flink);
        $this->assertSame('mega_menu_image casinos all-casinos', $by[426]->anchor_css);
        $this->assertSame('All Casinos', $by[426]->anchor_title);
        // a separator still goes through the lines after the switch (Joomla's "continue" was a break)
        $this->assertSame('', $by[452]->anchor_title);
        $this->assertSame('', $by[452]->menu_image);
        $this->assertSame('FXCheck™', $by[1116]->title);
    }

    public function test_trees_from_the_parents(): void
    {
        $menu = $this->menu();
        $this->assertSame([452, 985, 1116], $menu->getItem(1116)->tree);
        $this->assertSame([372], $menu->getItem(372)->tree);
    }
}
