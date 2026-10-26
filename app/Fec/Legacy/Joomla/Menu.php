<?php

namespace App\Fec\Legacy\Joomla;

use App\Fec\Url\UrlRegistry;
use App\Models\Fec\MenuItem;

/**
 * JMenu (site) over jos_menu: the published site items, each as the object
 * Joomla built (id, menutype, title, alias, path, link, type, level,
 * parent_id, component_id, home, params as a Registry, query as the parsed
 * link). The active item is the one the runner sets from the resolved URL's
 * Itemid, or, failing that, the item whose link matches the resolved query;
 * menu params (hero copy, page titles) come from it, as on the live site.
 */
class Menu
{
    /** @var array<int, object>|null */
    private ?array $items = null;

    private ?int $activeId = null;

    /** @return array<int, object> */
    private function all(): array
    {
        if ($this->items === null) {
            $this->items = [];
            foreach (MenuItem::query()->site()->orderBy('lft')->get() as $row) {
                $this->items[(int) $row->id] = $this->toObject($row);
            }
        }

        return $this->items;
    }

    private function toObject(MenuItem $row): object
    {
        $item = new \stdClass;
        foreach (['id', 'menutype', 'title', 'alias', 'path', 'link', 'type', 'published', 'parent_id', 'level', 'component_id', 'ordering', 'access', 'home', 'language', 'note'] as $col) {
            $item->{$col} = $row->{$col};
        }
        $item->id = (int) $item->id;
        $item->parent_id = (int) $item->parent_id;
        $item->level = (int) $item->level;
        $item->home = (int) $item->home;
        $item->params = new Registry((string) $row->params);
        $item->query = UrlRegistry::parseQuery((string) $row->link);
        $item->route = (string) $row->path;
        $item->tree = [];

        return $item;
    }

    public function getItem(int|string $id): ?object
    {
        return $this->all()[(int) $id] ?? null;
    }

    /**
     * Joomla 2.5: getItems($attributes, $values, $firstonly = false), loose
     * comparison as JMenu did it (a link matches only when spelled exactly as
     * the desk saved it: AuthorsHelper relies on that to find its Itemid).
     *
     * @param  string|list<string>  $attributes
     * @param  mixed|list<mixed>  $values
     * @return list<object>|object|null
     */
    public function getItems(string|array $attributes, mixed $values, bool $firstonly = false): array|object|null
    {
        $attributes = (array) $attributes;
        $values = is_array($values) && is_array($attributes) && count($attributes) > 1 ? $values : [$values];
        $found = [];
        foreach ($this->all() as $item) {
            $ok = true;
            foreach ($attributes as $i => $attr) {
                $want = $values[$i] ?? null;
                $have = $item->{$attr} ?? null;
                if (is_array($want)) {
                    $ok = in_array($have, $want, false);
                } else {
                    $ok = $have == $want;
                }
                if (! $ok) {
                    break;
                }
            }
            if ($ok) {
                if ($firstonly) {
                    return $item;
                }
                $found[] = $item;
            }
        }

        return $firstonly ? null : $found;
    }

    public function getDefault(string $language = '*'): ?object
    {
        foreach ($this->all() as $item) {
            if ($item->home === 1) {
                return $item;
            }
        }

        return null;
    }

    public function setActive(int|string $id): ?object
    {
        $this->activeId = (int) $id;

        return $this->getActive();
    }

    public function getActive(): ?object
    {
        return $this->activeId !== null ? $this->getItem($this->activeId) : null;
    }

    /**
     * Pick the active item the way Joomla did after routing: the Itemid of
     * the resolved query when present, otherwise the first item whose link
     * carries the same option/view/identifiers.
     *
     * @param  array<string, string>  $query
     */
    public function activateFor(array $query): ?object
    {
        if (isset($query['Itemid']) && is_numeric($query['Itemid']) && $this->getItem((int) $query['Itemid'])) {
            return $this->setActive((int) $query['Itemid']);
        }
        $want = UrlRegistry::significant($query);
        $best = null;
        foreach ($this->all() as $item) {
            if ($item->type !== 'component' || empty($item->query['option']) || $item->query['option'] !== ($want['option'] ?? '')) {
                continue;
            }
            $have = UrlRegistry::significant($item->query);
            if ($have == $want) {
                return $this->setActive($item->id);
            }
            // Same option and view: a weaker match, kept if nothing exact turns up.
            if ($best === null && ($have['view'] ?? null) === ($want['view'] ?? null) && ! isset($want['slug'], $want['id'])) {
                $best = $item;
            }
        }

        return $best !== null ? $this->setActive($best->id) : null;
    }
}
