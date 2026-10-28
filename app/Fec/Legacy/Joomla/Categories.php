<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JCategories::getInstance('Content') of Joomla 2.5 over jos_categories: the
 * published categories of com_content a visitor can see, as nodes
 * (JCategoryNode) linked parent to child, each with the columns of the row,
 * getParams(), getParent(), getPath() (the "id:alias" segments from the root)
 * and getChildren(). A category under an unpublished ancestor is not served
 * (Joomla's "badcats" join). Loaded once per request, the whole tree: 43 rows.
 */
final class Categories
{
    /** @var array<string, Categories> */
    private static array $instances = [];

    /** @var array<int, CategoryNode>|null */
    private ?array $nodes = null;

    private function __construct(private readonly string $extension) {}

    public static function getInstance(string $extension, array $options = []): Categories
    {
        $extension = strtolower($extension);
        $key = $extension === 'content' ? 'com_content' : 'com_'.$extension;

        return self::$instances[$key] ??= new Categories($key);
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    public function get(int|string $id = 'root', bool $forceload = false): ?CategoryNode
    {
        $this->load();
        if ($id === 'root' || (int) $id === 0) {
            return $this->nodes['root'] ?? null;
        }

        return $this->nodes[(int) $id] ?? null;
    }

    private function load(): void
    {
        if ($this->nodes !== null) {
            return;
        }
        $this->nodes = [];
        $db = Factory::getDbo();
        $sql = 'SELECT c.* FROM #__categories AS c'
            .' LEFT JOIN (SELECT cat.id AS id FROM #__categories AS cat JOIN #__categories AS parent'
            .' ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.extension = '.$db->quote($this->extension)
            .' AND parent.published != 1 GROUP BY cat.id) AS badcats ON badcats.id = c.id'
            .' WHERE (c.extension = '.$db->quote($this->extension).' OR c.extension = '.$db->quote('system').')'
            .' AND c.published = 1 AND c.access = 1 AND badcats.id IS NULL'
            .' ORDER BY c.lft';
        $db->setQuery($sql);
        $rows = (array) $db->loadObjectList();

        $root = new CategoryNode((object) ['id' => 'root', 'parent_id' => 'root', 'level' => 0, 'alias' => 'root', 'title' => 'ROOT', 'path' => '', 'lft' => 0, 'rgt' => PHP_INT_MAX, 'published' => 1, 'access' => 1, 'params' => '', 'metadata' => '', 'metadesc' => '', 'metakey' => '', 'description' => '', 'extension' => 'system']);
        $this->nodes['root'] = $root;
        foreach ($rows as $row) {
            if ((int) $row->id === 1) {
                continue; // Joomla's own ROOT row (id 1) is the root node above
            }
            $node = new CategoryNode($row);
            $this->nodes[(int) $row->id] = $node;
        }
        foreach ($this->nodes as $id => $node) {
            if ($id === 'root') {
                continue;
            }
            $parent = (int) $node->parent_id <= 1 ? $root : ($this->nodes[(int) $node->parent_id] ?? $root);
            $node->setParent($parent);
        }
    }
}
