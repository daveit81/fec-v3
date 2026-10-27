<?php

namespace App\Fec\Legacy\Joomla;

use App\Fec\Legacy\Joomla\Tables\Asset;
use RuntimeException;

/**
 * JTable as Joomla 2.5 had it, for the tables our desks save through it
 * (JTable::getInstance('Content')): the row is the object, one public
 * property per column (null until bound or loaded, reset() gives the column
 * defaults), bind() takes only known columns, load() by id or by an array of
 * conditions, store() inserts or updates and, when the table tracks assets,
 * keeps the row's node in #__assets (name "com_content.article.<id>" under
 * the category's asset). Errors never throw: setError()/getError(), as the
 * models expect.
 */
#[\AllowDynamicProperties]
class Table
{
    protected string $_tbl;

    protected string $_tbl_key;

    protected Database $_db;

    protected bool $_trackAssets = false;

    /** @var list<string> */
    protected array $_errors = [];

    /** JSON access rules bound with the row (none in our desks: the asset keeps "{}"). */
    protected ?string $_rules = null;

    /** @var array<string, array<string, object>> table => column => SHOW FULL COLUMNS row, per process */
    private static array $columns = [];

    /**
     * JTable::getInstance('Content', 'JTable'): a new table object over the
     * current database (the writing connection in a desk).
     */
    public static function getInstance(string $type, string $prefix = 'JTable', array $config = []): static
    {
        $class = __NAMESPACE__.'\\Tables\\'.ucfirst(strtolower($type));
        if (! class_exists($class)) {
            throw new RuntimeException('JTable '.$type.' is not ported yet', 501);
        }

        return new $class($config['dbo'] ?? Factory::getDbo());
    }

    public static function addIncludePath(mixed $path = null): array
    {
        return [];
    }

    public function __construct(string $table, string $key, Database $db)
    {
        $this->_tbl = $table;
        $this->_tbl_key = $key;
        $this->_db = $db;
        foreach (array_keys($this->getFields()) as $name) {
            if (! property_exists($this, $name)) {
                $this->$name = null;
            }
        }
    }

    /** @return array<string, object> column => SHOW FULL COLUMNS row (Field, Type, Null, Default, ...) */
    public function getFields(): array
    {
        $name = $this->_db->replacePrefix($this->_tbl);
        if (! isset(self::$columns[$name])) {
            $fields = $this->_db->getTableColumns($this->_tbl, false);
            if ($fields === []) {
                throw new RuntimeException('No columns found for table '.$name);
            }
            self::$columns[$name] = $fields;
        }

        return self::$columns[$name];
    }

    public function getTableName(): string
    {
        return $this->_tbl;
    }

    public function getKeyName(): string
    {
        return $this->_tbl_key;
    }

    public function getDbo(): Database
    {
        return $this->_db;
    }

    /** @return array<string, mixed> the public column properties */
    public function getProperties(bool $public = true): array
    {
        $out = [];
        foreach (get_object_vars($this) as $k => $v) {
            if (! str_starts_with($k, '_')) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /** Column defaults, the key left alone. */
    public function reset(): void
    {
        foreach ($this->getFields() as $k => $column) {
            if ($k !== $this->_tbl_key && ! str_starts_with($k, '_')) {
                $this->$k = $column->Default ?? null;
            }
        }
    }

    /**
     * @param  array<string, mixed>|object  $src
     * @param  array<int, string>|string  $ignore
     */
    public function bind(array|object $src, array|string $ignore = []): bool
    {
        if (is_object($src)) {
            $src = get_object_vars($src);
        }
        if (is_string($ignore)) {
            $ignore = explode(' ', $ignore);
        }
        foreach (array_keys($this->getProperties()) as $k) {
            if (array_key_exists($k, $src) && ! in_array($k, $ignore, true)) {
                $this->$k = $src[$k];
            }
        }

        return true;
    }

    /**
     * @param  int|string|array<string, mixed>|null  $keys  the id, or column => value conditions
     */
    public function load(mixed $keys = null, bool $reset = true): bool
    {
        if ($keys === null || $keys === [] || $keys === 0 || $keys === '') {
            $keyValue = $this->{$this->_tbl_key};
            if (empty($keyValue)) {
                return true;
            }
            $keys = [$this->_tbl_key => $keyValue];
        } elseif (! is_array($keys)) {
            $keys = [$this->_tbl_key => $keys];
        }
        if ($reset) {
            $this->reset();
        }

        $fields = array_keys($this->getProperties());
        $where = [];
        foreach ($keys as $field => $value) {
            if (! in_array($field, $fields, true)) {
                $this->setError('Missing field in database: '.$this->_tbl.' '.$field);

                return false;
            }
            $where[] = $this->_db->quoteName($field).' = '.$this->_db->quote($value);
        }
        $this->_db->setQuery('SELECT * FROM '.$this->_db->quoteName($this->_tbl).' WHERE '.implode(' AND ', $where));
        $row = $this->_db->loadAssoc();
        if ($this->_db->getErrorNum()) {
            $this->setError($this->_db->getErrorMsg());

            return false;
        }
        if (empty($row)) {
            $this->setError('Empty row returned');

            return false;
        }

        return $this->bind($row);
    }

    public function check(): bool
    {
        return true;
    }

    /**
     * Insert or update the row; then, for an asset-tracking table, create or
     * heal its node in #__assets and write asset_id back, as JTable did.
     */
    public function store(bool $updateNulls = false): bool
    {
        $k = $this->_tbl_key;
        $currentAssetId = property_exists($this, 'asset_id') && ! empty($this->asset_id) ? (int) $this->asset_id : 0;
        if (empty($this->$k)) {
            $this->$k = null;
        }

        $stored = $this->$k
            ? $this->_db->updateObject($this->_tbl, $this, $k, $updateNulls)
            : $this->_db->insertObject($this->_tbl, $this, $k);
        if (! $stored) {
            $this->setError('Store failed: '.$this->_db->getErrorMsg());

            return false;
        }
        if (! $this->_trackAssets) {
            return true;
        }

        $parentId = $this->_getAssetParentId();
        $name = $this->_getAssetName();
        $title = $this->_getAssetTitle();

        $asset = new Asset($this->_db);
        $asset->loadByName($name);
        $this->asset_id = $asset->id ? (int) $asset->id : null;

        if (empty($this->asset_id) || (int) $asset->parent_id !== $parentId) {
            $asset->setLocation($parentId, 'last-child');
        }
        $asset->parent_id = $parentId;
        $asset->name = $name;
        $asset->title = $title;
        if ($this->_rules !== null) {
            $asset->rules = $this->_rules;
        }
        if (! $asset->check() || ! $asset->store($updateNulls)) {
            $this->setError($asset->getError());

            return false;
        }

        if (empty($this->asset_id) || $currentAssetId !== (int) $asset->id) {
            $this->asset_id = (int) $asset->id;
            $this->_db->setQuery('UPDATE '.$this->_db->quoteName($this->_tbl).' SET asset_id = '.(int) $this->asset_id
                .' WHERE '.$this->_db->quoteName($k).' = '.(int) $this->$k);
            if ($this->_db->execute() === false) {
                $this->setError('Store failed: '.$this->_db->getErrorMsg());

                return false;
            }
        }

        return true;
    }

    public function setError(string $error): void
    {
        $this->_errors[] = $error;
    }

    public function getError(?int $i = null, bool $toString = true): string
    {
        if ($this->_errors === []) {
            return '';
        }

        return $i === null ? (string) end($this->_errors) : (string) ($this->_errors[$i] ?? '');
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->_errors;
    }

    /** "com_content.article.12", from the table name by default. */
    protected function _getAssetName(): string
    {
        return $this->_tbl.'.'.(int) $this->{$this->_tbl_key};
    }

    protected function _getAssetTitle(): ?string
    {
        return null;
    }

    /** The root asset (id 1 on every Joomla site) unless the table knows better. */
    protected function _getAssetParentId(?Table $table = null, ?int $id = null): int
    {
        return Asset::rootId($this->_db);
    }
}
