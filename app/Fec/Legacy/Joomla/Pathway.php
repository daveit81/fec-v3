<?php

namespace App\Fec\Legacy\Joomla;

/** JPathway: the breadcrumb module is not rendered by v3's layout; items are collected and ignored. */
class Pathway
{
    /** @var list<array{name: string, link: string}> */
    private array $items = [];

    public function addItem(string $name, string $link = ''): bool
    {
        $this->items[] = ['name' => $name, 'link' => $link];

        return true;
    }

    /** @return list<array{name: string, link: string}> */
    public function getPathway(): array
    {
        return $this->items;
    }

    public function setItemName(int $id, string $name): bool
    {
        if (isset($this->items[$id])) {
            $this->items[$id]['name'] = $name;
        }

        return true;
    }
}
