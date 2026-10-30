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

    /**
     * JDatabaseQuery::__call() of Joomla 2.5: q(), qn() and e() (ours are real methods
     * above), and null for any other method instead of an error. Joomla 2.5's query has no
     * setLimit() (JDatabaseQueryLimitable came with Platform 12.1, Joomla 3): the live
     * bonus_feedback.php's get_agg() ends its query with ->setLimit(1), gets null, runs an
     * empty statement and answers 0 votes for every bonus. Same here, with a legacy notice.
     *
     * @param  array<int, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        trigger_error('JDatabaseQuery::'.$method.'() does not exist in Joomla 2.5: null, as its __call() answered', E_USER_NOTICE);

        return null;
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

    /** JDatabase::q(): alias of quote(). */
    public function q(mixed $text, bool $escape = true): string
    {
        return $this->quote($text, $escape);
    }

    /** JDatabase::qn(): alias of quoteName(). */
    public function qn(string|array $name, string|array|null $as = null): string|array
    {
        return $this->quoteName($name, $as);
    }

    /** JDatabase::e(): alias of escape(). */
    public function e(string $text, bool $extra = false): string
    {
        return $this->escape($text, $extra);
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
