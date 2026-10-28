<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JCategoryNode of Joomla 2.5: a row of jos_categories with its parent, its
 * children and the path of "id:alias" segments from the root (what
 * ContentHelperRoute matches against the menu). params and metadata are the
 * JSON strings until getParams()/getMetadata() turn them into registries,
 * as the original did (the category view clones and merges them).
 */
#[\AllowDynamicProperties]
class CategoryNode
{
    private ?CategoryNode $parent = null;

    /** @var array<int|string, CategoryNode> */
    private array $children = [];

    /** @var list<string> */
    private array $path = [];

    public function __construct(object $row)
    {
        foreach (get_object_vars($row) as $k => $v) {
            $this->{$k} = $v;
        }
        $this->id = $this->id === 'root' ? 'root' : (int) $this->id;
        $this->parent_id = $this->parent_id === 'root' ? 'root' : (int) $this->parent_id;
        $this->level = (int) ($this->level ?? 0);
    }

    public function setParent(CategoryNode $parent): void
    {
        $this->parent = $parent;
        $parent->children[$this->id] = $this;
        $this->path = $this->id === 'root' ? [] : array_merge($parent->getPath(), [$this->id.':'.$this->alias]);
    }

    public function getParent(): ?CategoryNode
    {
        return $this->parent;
    }

    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    /** @return array<int|string, CategoryNode> */
    public function getChildren(bool $recursive = false): array
    {
        if (! $recursive) {
            return $this->children;
        }
        $all = [];
        foreach ($this->children as $id => $child) {
            $all[$id] = $child;
            $all += $child->getChildren(true);
        }

        return $all;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /** @return list<string> "id:alias" from the first level down to this node */
    public function getPath(): array
    {
        return $this->path;
    }

    public function getParams(): Registry
    {
        if (! ($this->params instanceof Registry)) {
            $this->params = new Registry((string) $this->params);
        }

        return $this->params;
    }

    public function getMetadata(): Registry
    {
        if (! ($this->metadata instanceof Registry)) {
            $this->metadata = new Registry((string) $this->metadata);
        }

        return $this->metadata;
    }

    public function getNumItems(bool $recursive = false): int
    {
        return (int) ($this->numitems ?? 0);
    }
}
