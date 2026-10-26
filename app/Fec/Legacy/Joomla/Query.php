<?php

namespace App\Fec\Legacy\Joomla;

use Stringable;

/**
 * JDatabaseQuery as our models build it: select/from/join/where/group/
 * having/order plus the DML forms (insert/update/delete, set, values,
 * columns). Renders the same SQL text Joomla 2.5 would; the database
 * object replaces "#__" when it runs it.
 */
class Query implements Stringable
{
    private string $type = '';

    /** @var array<string, list<string>> */
    private array $parts = [];

    private string $whereGlue = 'AND';

    private ?string $table = null;

    public function __construct(private readonly Database $db)
    {
    }

    private function add(string $part, string|array $value): static
    {
        foreach ((array) $value as $v) {
            $this->parts[$part][] = (string) $v;
        }

        return $this;
    }

    public function select(string|array $columns): static
    {
        $this->type = 'select';

        return $this->add('select', $columns);
    }

    public function from(string|array $tables): static
    {
        return $this->add('from', $tables);
    }

    public function join(string $type, string $condition): static
    {
        return $this->add('join', strtoupper(trim($type)).' JOIN '.$condition);
    }

    public function innerJoin(string $condition): static
    {
        return $this->join('INNER', $condition);
    }

    public function leftJoin(string $condition): static
    {
        return $this->join('LEFT', $condition);
    }

    public function where(string|array $conditions, string $glue = 'AND'): static
    {
        $this->whereGlue = strtoupper($glue);

        return $this->add('where', $conditions);
    }

    public function group(string|array $columns): static
    {
        return $this->add('group', $columns);
    }

    public function having(string|array $conditions): static
    {
        return $this->add('having', $conditions);
    }

    public function order(string|array $columns): static
    {
        return $this->add('order', $columns);
    }

    public function insert(string $table): static
    {
        $this->type = 'insert';
        $this->table = $table;

        return $this;
    }

    public function update(string $table): static
    {
        $this->type = 'update';
        $this->table = $table;

        return $this;
    }

    public function delete(?string $table = null): static
    {
        $this->type = 'delete';
        $this->table = $table;

        return $this;
    }

    public function columns(string|array $columns): static
    {
        return $this->add('columns', $columns);
    }

    public function values(string|array $values): static
    {
        return $this->add('values', $values);
    }

    public function set(string|array $assignments): static
    {
        return $this->add('set', $assignments);
    }

    public function clear(?string $clause = null): static
    {
        if ($clause === null) {
            $this->parts = [];
            $this->type = '';
            $this->table = null;
        } else {
            unset($this->parts[$clause]);
        }

        return $this;
    }

    public function quote(mixed $text, bool $escape = true): string
    {
        return $this->db->quote($text, $escape);
    }

    public function quoteName(string|array $name, string|array|null $as = null): string|array
    {
        return $this->db->quoteName($name, $as);
    }

    public function escape(string $text, bool $extra = false): string
    {
        return $this->db->escape($text, $extra);
    }

    public function __toString(): string
    {
        $p = $this->parts;
        $sql = '';

        switch ($this->type) {
            case 'select':
                $sql = 'SELECT '.implode(',', $p['select'] ?? ['*']);
                if (! empty($p['from'])) {
                    $sql .= "\nFROM ".implode(',', $p['from']);
                }
                foreach ($p['join'] ?? [] as $join) {
                    $sql .= "\n".$join;
                }
                break;
            case 'insert':
                $sql = 'INSERT INTO '.$this->table;
                if (! empty($p['columns'])) {
                    $sql .= "\n(".implode(',', $p['columns']).')';
                }
                if (! empty($p['values'])) {
                    $sql .= "\nVALUES\n(".implode('),(', $p['values']).')';
                }
                if (! empty($p['set'])) {
                    $sql .= "\nSET ".implode(', ', $p['set']);
                }
                break;
            case 'update':
                $sql = 'UPDATE '.$this->table;
                foreach ($p['join'] ?? [] as $join) {
                    $sql .= "\n".$join;
                }
                $sql .= "\nSET ".implode(', ', $p['set'] ?? []);
                break;
            case 'delete':
                $sql = 'DELETE'.($this->table ? ' FROM '.$this->table : '');
                if (! empty($p['from'])) {
                    $sql .= ' FROM '.implode(',', $p['from']);
                }
                break;
        }

        if (! empty($p['where'])) {
            $sql .= "\nWHERE ".implode(' '.$this->whereGlue.' ', $p['where']);
        }
        if (! empty($p['group'])) {
            $sql .= "\nGROUP BY ".implode(',', $p['group']);
        }
        if (! empty($p['having'])) {
            $sql .= "\nHAVING ".implode(' AND ', $p['having']);
        }
        if (! empty($p['order'])) {
            $sql .= "\nORDER BY ".implode(',', $p['order']);
        }

        return $sql;
    }
}
