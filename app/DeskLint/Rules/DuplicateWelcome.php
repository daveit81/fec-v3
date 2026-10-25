<?php

namespace App\DeskLint\Rules;

/** More than one live welcome offer on the same casino (types from config fec.bonus.welcome_types). */
final class DuplicateWelcome extends BaseRule
{
    public function id(): string
    {
        return 'duplicate_welcome';
    }

    public function title(): string
    {
        return 'Due o piu welcome offer attive sullo stesso casino';
    }

    public function run(): array
    {
        $types = config('fec.bonus.welcome_types', []);
        if ($types === []) {
            return [];
        }

        $rows = $this->db()->table('bonuses as b')
            ->selectRaw('b.cid, COUNT(*) AS n, GROUP_CONCAT(b.bid ORDER BY b.bid SEPARATOR ", ") AS bids')
            ->whereRaw($this->activeWhere())
            ->whereIn($this->db()->raw('LOWER(TRIM(b.type))'), array_map('strtolower', $types))
            ->groupBy('b.cid')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->cid,
            $this->casinoName((int) $r->cid),
            sprintf('%d welcome attive (bid %s)', $r->n, $r->bids),
        ))->all();
    }
}
