<?php

namespace App\Fec\Legacy\Joomla\Mvc;

use App\Fec\Legacy\Joomla\Database;
use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\Registry;

/**
 * JModel of Joomla 2.5: the database handle (_db / getDbo), the name
 * derived from the class, a state registry and the list helpers
 * (_getList, _getListCount). Our models mostly take JFactory::getDbo()
 * themselves and extend this for the name and the state only.
 */
#[\AllowDynamicProperties]
class Model
{
    protected Database $_db;

    protected string $name;

    protected Registry $state;

    protected bool $__state_set = false;

    public function __construct(array $config = [])
    {
        $this->_db = $config['dbo'] ?? Factory::getDbo();
        if (isset($config['name'])) {
            $this->name = strtolower((string) $config['name']);
        } elseif (preg_match('/Model(.*)$/i', static::class, $m)) {
            $this->name = strtolower($m[1]);
        } else {
            $this->name = strtolower(static::class);
        }
        $this->state = new Registry($config['state'] ?? []);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDbo(): Database
    {
        return $this->_db;
    }

    public function setDbo(Database $db): void
    {
        $this->_db = $db;
    }

    public function getState(?string $property = null, mixed $default = null): mixed
    {
        if (! $this->__state_set) {
            $this->populateState();
            $this->__state_set = true;
        }

        return $property === null ? $this->state : $this->state->get($property, $default);
    }

    public function setState(string $property, mixed $value = null): mixed
    {
        return $this->state->set($property, $value);
    }

    protected function populateState(): void
    {
    }

    /** @return list<object> */
    protected function _getList(string|\Stringable $query, int $limitstart = 0, int $limit = 0): array
    {
        $this->_db->setQuery($query, $limitstart, $limit);

        return $this->_db->loadObjectList();
    }

    protected function _getListCount(string|\Stringable $query): int
    {
        $this->_db->setQuery('SELECT COUNT(*) FROM ('.(string) $query.') AS fec_count');

        return (int) $this->_db->loadResult();
    }
}
