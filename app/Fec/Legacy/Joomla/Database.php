<?php

namespace App\Fec\Legacy\Joomla;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * JDatabase as our components use it (JFactory::getDbo()), over the Laravel
 * connection "fec" (reads) or "fec_rw" (desk writes). Same semantics as
 * Joomla 2.5's MySQLi driver for the methods we call:
 *
 *   setQuery / query / execute / loadResult / loadAssoc / loadAssocList /
 *   loadObject / loadObjectList / loadColumn / loadRow / loadRowList /
 *   quote / quoteName / getEscaped / escape / getNullDate / insertid /
 *   getErrorNum / getErrorMsg / getQuery(true) / replacePrefix /
 *   transactionStart / transactionCommit / transactionRollback
 *
 * Joomla 2.5 never throws from a query: it sets the error number and
 * message and returns null/false, and our models check getErrorNum().
 * Kept exactly so, with the exception logged.
 */
class Database
{
    private string $sql = '';

    private int $errorNum = 0;

    private string $errorMsg = '';

    private int $insertId = 0;

    public function __construct(private readonly string $connection = 'fec')
    {
    }

    public function connection(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }

    public function getPrefix(): string
    {
        return (string) $this->connection()->getTablePrefix();
    }

    /** "#__" -> "jos_" */
    public function replacePrefix(string $sql, string $prefix = '#__'): string
    {
        return str_replace($prefix, $this->getPrefix(), $sql);
    }

    /** @param  string|Query|\Stringable  $query */
    public function setQuery($query, int $offset = 0, int $limit = 0): static
    {
        $sql = (string) $query;
        if ($limit > 0 && ! preg_match('/\bLIMIT\b/i', $sql)) {
            $sql .= ' LIMIT '.($offset > 0 ? (int) $offset.', ' : '').(int) $limit;
        }
        $this->sql = $sql;

        return $this;
    }

    public function getQuery(bool $new = false): Query|string
    {
        return $new ? new Query($this) : $this->sql;
    }

    /** Run the current statement: rows for SELECT-like statements, affected count otherwise. */
    public function query(): mixed
    {
        return $this->execute();
    }

    public function execute(): mixed
    {
        $this->errorNum = 0;
        $this->errorMsg = '';
        $sql = $this->replacePrefix($this->sql);

        try {
            if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $sql)) {
                return $this->connection()->select($sql);
            }
            $pdo = $this->connection()->getPdo();
            $affected = $this->connection()->affectingStatement($sql);
            if (preg_match('/^\s*INSERT\b/i', $sql)) {
                $this->insertId = (int) $pdo->lastInsertId();
            }

            return $affected;
        } catch (Throwable $e) {
            $this->fail($e, $sql);

            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $this->errorNum = 0;
        $this->errorMsg = '';
        $sql = $this->replacePrefix($this->sql);

        try {
            $pdo = $this->connection()->getPdo();
            $statement = $pdo->prepare($sql);
            $statement->execute();

            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $this->fail($e, $sql);

            return [];
        }
    }

    public function loadResult(): mixed
    {
        $rows = $this->rows();
        if ($rows === []) {
            return null;
        }
        $first = reset($rows);

        return $first === [] ? null : reset($first);
    }

    /** @return array<string, mixed>|null */
    public function loadAssoc(): ?array
    {
        $rows = $this->rows();

        return $rows === [] ? null : reset($rows);
    }

    /** @return list<array<string, mixed>> */
    public function loadAssocList(?string $key = null, ?string $column = null): array
    {
        $rows = $this->rows();
        if ($key === null && $column === null) {
            return $rows;
        }
        $out = [];
        foreach ($rows as $row) {
            $value = $column !== null ? ($row[$column] ?? null) : $row;
            if ($key !== null) {
                $out[$row[$key] ?? count($out)] = $value;
            } else {
                $out[] = $value;
            }
        }

        return $out;
    }

    public function loadObject(): ?object
    {
        $row = $this->loadAssoc();

        return $row === null ? null : (object) $row;
    }

    /** @return list<object> */
    public function loadObjectList(?string $key = null): array
    {
        $out = [];
        foreach ($this->rows() as $row) {
            if ($key !== null) {
                $out[$row[$key] ?? count($out)] = (object) $row;
            } else {
                $out[] = (object) $row;
            }
        }

        return $out;
    }

    /** @return list<mixed> */
    public function loadColumn(int $offset = 0): array
    {
        $out = [];
        foreach ($this->rows() as $row) {
            $values = array_values($row);
            $out[] = $values[$offset] ?? null;
        }

        return $out;
    }

    /** Joomla 2.5 alias of loadColumn(). @return list<mixed> */
    public function loadResultArray(int $offset = 0): array
    {
        return $this->loadColumn($offset);
    }

    /** @return list<mixed>|null */
    public function loadRow(): ?array
    {
        $row = $this->loadAssoc();

        return $row === null ? null : array_values($row);
    }

    /** @return list<list<mixed>> */
    public function loadRowList(): array
    {
        return array_map('array_values', $this->rows());
    }

    public function quote(mixed $text, bool $escape = true): string
    {
        if (is_array($text)) {
            return implode(', ', array_map(fn ($t) => $this->quote($t, $escape), $text));
        }

        return "'".($escape ? $this->escape((string) $text) : (string) $text)."'";
    }

    /** "#__table" -> `jos_table`, "a.col" -> `a`.`col`, with an optional alias. */
    public function quoteName(string|array $name, string|array|null $as = null): string|array
    {
        if (is_array($name)) {
            $out = [];
            foreach ($name as $i => $n) {
                $out[] = $this->quoteName($n, is_array($as) ? ($as[$i] ?? null) : null);
            }

            return $out;
        }

        $quoted = implode('.', array_map(
            fn (string $part) => $part === '*' ? '*' : '`'.str_replace('`', '', $this->replacePrefix($part)).'`',
            explode('.', $name)
        ));

        return $as !== null && $as !== '' ? $quoted.' AS `'.str_replace('`', '', (string) $as).'`' : $quoted;
    }

    public function escape(string $text, bool $extra = false): string
    {
        $quoted = (string) $this->connection()->getPdo()->quote($text);
        $escaped = substr($quoted, 1, -1);

        return $extra ? addcslashes($escaped, '%_') : $escaped;
    }

    /** Joomla 2.5 name of escape(). */
    public function getEscaped(string $text, bool $extra = false): string
    {
        return $this->escape($text, $extra);
    }

    /**
     * JDatabase::insertObject: every public, scalar, non-null property of the
     * object whose name does not start with "_" becomes a column; the new id
     * is written back into $key.
     */
    public function insertObject(string $table, object $object, ?string $key = null): bool
    {
        $fields = [];
        $values = [];
        foreach (get_object_vars($object) as $k => $v) {
            if ($v === null || is_array($v) || is_object($v) || str_starts_with($k, '_')) {
                continue;
            }
            $fields[] = $this->quoteName($k);
            $values[] = $this->quote($v);
        }
        if ($fields === []) {
            return false;
        }
        $this->setQuery('INSERT INTO '.$this->quoteName($table).' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')');
        if ($this->execute() === false) {
            return false;
        }
        if ($key !== null && $this->insertId > 0) {
            $object->$key = $this->insertId;
        }

        return true;
    }

    /** JDatabase::updateObject: null properties are skipped unless $nulls; the key names the row. */
    public function updateObject(string $table, object $object, string $key, bool $nulls = false): bool
    {
        $fields = [];
        $where = '';
        foreach (get_object_vars($object) as $k => $v) {
            if (is_array($v) || is_object($v) || str_starts_with($k, '_')) {
                continue;
            }
            if ($k === $key) {
                $where = $this->quoteName($k).' = '.$this->quote($v);

                continue;
            }
            if ($v === null) {
                if (! $nulls) {
                    continue;
                }
                $fields[] = $this->quoteName($k).' = NULL';

                continue;
            }
            $fields[] = $this->quoteName($k).' = '.$this->quote($v);
        }
        if ($where === '') {
            $this->errorNum = 1;
            $this->errorMsg = 'updateObject: no key '.$key;

            return false;
        }
        if ($fields === []) {
            return true;
        }
        $this->setQuery('UPDATE '.$this->quoteName($table).' SET '.implode(', ', $fields).' WHERE '.$where);

        return $this->execute() !== false;
    }

    /**
     * JDatabase::getTableColumns: column => type (or the whole SHOW FULL COLUMNS
     * row when $typeOnly is false, as JTable reads the defaults from it).
     *
     * @return array<string, mixed>
     */
    public function getTableColumns(string $table, bool $typeOnly = true): array
    {
        $this->setQuery('SHOW FULL COLUMNS FROM '.$this->quoteName($table));
        $out = [];
        foreach ($this->loadObjectList() as $row) {
            $out[$row->Field] = $typeOnly ? preg_replace('/[(0-9)]/', '', (string) $row->Type) : $row;
        }

        return $out;
    }

    public function getNullDate(): string
    {
        return '0000-00-00 00:00:00';
    }

    public function insertid(): int
    {
        return $this->insertId;
    }

    public function getErrorNum(): int
    {
        return $this->errorNum;
    }

    public function getErrorMsg(bool $escaped = false): string
    {
        return $escaped ? addslashes($this->errorMsg) : $this->errorMsg;
    }

    public function getAffectedRows(): int
    {
        return 0;
    }

    public function transactionStart(): void
    {
        $this->connection()->beginTransaction();
    }

    public function transactionCommit(): void
    {
        $this->connection()->commit();
    }

    public function transactionRollback(): void
    {
        $this->connection()->rollBack();
    }

    private function fail(Throwable $e, string $sql): void
    {
        $this->errorNum = (int) ($e->getCode() ?: 1);
        $this->errorMsg = $e->getMessage();
        error_log('legacy db ['.$this->connection.']: '.$e->getMessage().' -- '.mb_strimwidth(preg_replace('/\s+/', ' ', $sql) ?? $sql, 0, 300, '...'));
    }
}
