<?php

namespace App\DeskLint\Rules;

/** Live bonuses nobody has touched for config fec.desk_lint.stale_days days. */
final class StaleActive extends BaseRule
{
    public function id(): string
    {
        return 'stale_active';
    }

    public function title(): string
    {
        return sprintf('Bonus attivi non toccati da oltre %d giorni', (int) config('fec.desk_lint.stale_days'));
    }

    public function run(): array
    {
        $days = (int) config('fec.desk_lint.stale_days');

        $rows = $this->db()->table('bonuses as b')
            ->select('b.bid', 'b.cid', 'b.label', 'b.modified', 'b.added')
            ->whereRaw($this->activeWhere())
            ->whereRaw("GREATEST(COALESCE(NULLIF(b.modified, '0000-00-00 00:00:00'), '1000-01-01'), COALESCE(NULLIF(b.added, '0000-00-00 00:00:00'), '1000-01-01')) < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days])
            ->orderBy('b.modified')
            ->limit(300)
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            sprintf('ultima modifica %s: %s', substr((string) ($r->modified ?: $r->added), 0, 10), trim((string) $r->label)),
            $this->editorUrl((int) $r->bid),
        ))->all();
    }
}
