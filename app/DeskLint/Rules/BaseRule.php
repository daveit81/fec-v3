<?php

namespace App\DeskLint\Rules;

use App\DeskLint\Finding;
use App\DeskLint\Rule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Rules are written in explicit SQL: with the "jos_" table prefix Laravel's
 * query builder also prefixes table aliases (bonuses as b -> jos_bonuses as
 * jos_b), which breaks every raw expression. Here the prefix is applied by
 * hand through t() and the aliases are ours.
 */
abstract class BaseRule implements Rule
{
    protected function db(): ConnectionInterface
    {
        return DB::connection('fec');
    }

    /** Fully prefixed table name: t('bonuses') -> jos_bonuses. */
    protected function t(string $table): string
    {
        return $this->db()->getTablePrefix().$table;
    }

    /** @return list<object> */
    protected function select(string $sql, array $bindings = []): array
    {
        return $this->db()->select($sql, $bindings);
    }

    /** SQL fragment for live bonuses, with the table alias applied. */
    protected function activeWhere(string $alias = 'b'): string
    {
        $where = trim((string) config('fec.bonus.active_where'));

        // "status = 'active'" -> "b.status = 'active'" when the fragment starts with a bare column.
        return preg_replace('/^([a-z_]+)(\s*)(=|<>|!=|in\b|like\b)/i', $alias.'.$1$2$3', $where) ?? $where;
    }

    /** A JReviews checkbox flag (jr_closed, jr_suspended, jr_blacklisted) is set when not empty ("*yes*", "*susp*", "*bl*"). */
    protected function flagSet(string $column): string
    {
        return "TRIM(COALESCE($column, '')) <> ''";
    }

    /** Set by a rule in run() when it has a backlog figure to show; null for plain rules. */
    protected ?string $summary = null;

    public function summary(): ?string
    {
        return $this->summary;
    }

    protected function finding(string $key, string $subject, string $detail = '', ?string $url = null): Finding
    {
        return new Finding($this->id(), $key, $subject, $detail, $url);
    }

    protected function editorUrl(int $bid): string
    {
        return sprintf(config('fec.bonus.editor_url'), $bid);
    }

    /** "*us*,*ca*,*au*" -> "us, ca, au" (first six). */
    protected function shortList(string $raw): string
    {
        $codes = array_values(array_filter(array_map(fn ($c) => trim($c, " *"), preg_split('/[,\s]+/', $raw) ?: [])));
        $shown = array_slice($codes, 0, 6);

        return implode(', ', $shown).(count($codes) > 6 ? sprintf(' +%d', count($codes) - 6) : '');
    }

    /** @var array<int, string> */
    private array $names = [];

    /** Casino display name from the review article title (cached per run). */
    protected function casinoName(int $cid): string
    {
        if (! array_key_exists($cid, $this->names)) {
            $row = $this->select('SELECT title FROM '.$this->t('content').' WHERE id = ? LIMIT 1', [$cid]);
            $this->names[$cid] = $row ? (string) $row[0]->title : 'casino #'.$cid;
        }

        return $this->names[$cid];
    }
}
