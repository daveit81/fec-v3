<?php

namespace App\Fec\Legacy\Joomla\Tables;

use App\Fec\Legacy\Joomla\Database;
use App\Fec\Legacy\Joomla\Table;

/**
 * JTableAsset: the ACL node of a row in #__assets, a nested set (lft/rgt,
 * level, parent_id). What JTable::store() needs of JTableNested: load a node
 * by name, insert a new node as the last child of its parent (every lft/rgt
 * after the parent's rgt shifts by two), move a leaf under another parent,
 * update a node in place. Articles are leaves; a node with children is never
 * moved from here.
 */
class Asset extends Table
{
    /** Where a new or moved node goes: the reference node id, -1 = stay. */
    protected int $_location_id = -1;

    protected string $_location = 'last-child';

    public function __construct(Database $db)
    {
        parent::__construct('#__assets', 'id', $db);
    }

    /** The root node (parent_id 0): id 1 on every Joomla site, read once per process. */
    public static function rootId(Database $db): int
    {
        static $root = null;
        if ($root === null) {
            $db->setQuery('SELECT id FROM '.$db->quoteName('#__assets').' WHERE parent_id = 0 ORDER BY lft LIMIT 1');
            $root = (int) $db->loadResult();
            if ($root <= 0) {
                $root = 1;
            }
        }

        return $root;
    }

    /** Load the node named $name; false (no error set) when there is none. */
    public function loadByName(string $name): bool
    {
        $this->_db->setQuery('SELECT id FROM '.$this->_db->quoteName('#__assets').' WHERE name = '.$this->_db->quote($name));
        $id = (int) $this->_db->loadResult();
        if ($id <= 0) {
            return false;
        }

        return $this->load($id);
    }

    public function setLocation(int $referenceId, string $position = 'after'): void
    {
        $this->_location_id = $referenceId;
        $this->_location = $position;
    }

    public function check(): bool
    {
        $this->parent_id = (int) $this->parent_id;
        if (empty($this->rules)) {
            $this->rules = '{}';
        }
        if (trim((string) $this->name) === '') {
            $this->setError('Asset: a name is required');

            return false;
        }
        if (trim((string) $this->title) === '') {
            $this->setError('Asset: a title is required');

            return false;
        }

        return true;
    }

    public function store(bool $updateNulls = false): bool
    {
        $k = $this->_tbl_key;
        $isNew = empty($this->$k);
        if ($isNew && $this->_location_id < 0) {
            $this->_location_id = self::rootId($this->_db);
        }
        if (! $isNew && $this->_location_id < 0) {
            // an update in place (title, rules): the tree does not move
            if (! $this->_db->updateObject($this->_tbl, $this, $k, $updateNulls)) {
                $this->setError('Asset store failed: '.$this->_db->getErrorMsg());

                return false;
            }

            return true;
        }

        $t = $this->_db->quoteName('#__assets');
        $this->_db->transactionStart();
        try {
            if (! $isNew) {
                // moving a leaf: take it out of the sequence first
                $node = $this->node((int) $this->$k);
                if ($node === null) {
                    throw new \RuntimeException('Asset '.$this->$k.' not found');
                }
                if ((int) $node->rgt - (int) $node->lft !== 1) {
                    throw new \RuntimeException('Asset '.$this->$k.' has children: move not ported');
                }
                $this->run('UPDATE '.$t.' SET lft = lft - 2 WHERE lft > '.(int) $node->rgt);
                $this->run('UPDATE '.$t.' SET rgt = rgt - 2 WHERE rgt > '.(int) $node->rgt);
            }

            $reference = $this->node($this->_location_id);
            if ($reference === null) {
                throw new \RuntimeException('Asset parent '.$this->_location_id.' not found');
            }
            // last-child: the new node takes the parent's rgt, everything from there on moves right by two
            $this->run('UPDATE '.$t.' SET lft = lft + 2 WHERE lft > '.(int) $reference->rgt);
            $this->run('UPDATE '.$t.' SET rgt = rgt + 2 WHERE rgt >= '.(int) $reference->rgt);
            $this->lft = (int) $reference->rgt;
            $this->rgt = (int) $reference->rgt + 1;
            $this->level = (int) $reference->level + 1;
            $this->parent_id = (int) $reference->id;

            $ok = $isNew
                ? $this->_db->insertObject($this->_tbl, $this, $k)
                : $this->_db->updateObject($this->_tbl, $this, $k, $updateNulls);
            if (! $ok) {
                throw new \RuntimeException($this->_db->getErrorMsg());
            }
            $this->_db->transactionCommit();
        } catch (\Throwable $e) {
            $this->_db->transactionRollback();
            $this->setError('Asset store failed: '.$e->getMessage());

            return false;
        }
        $this->_location_id = -1;

        return true;
    }

    private function node(int $id): ?object
    {
        $this->_db->setQuery('SELECT id, parent_id, lft, rgt, level FROM '.$this->_db->quoteName('#__assets').' WHERE id = '.$id);
        $row = $this->_db->loadObject();

        return $row ?: null;
    }

    private function run(string $sql): void
    {
        $this->_db->setQuery($sql);
        if ($this->_db->execute() === false) {
            throw new \RuntimeException($this->_db->getErrorMsg());
        }
    }
}
