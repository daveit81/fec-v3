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

        $rows = $this->select('
            SELECT b.bid, b.cid, b.label, b.modified, b.added
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().'
              AND GREATEST(COALESCE(NULLIF(b.modified, "0000-00-00 00:00:00"), "1000-01-01"),
                           COALESCE(NULLIF(b.added, "0000-00-00 00:00:00"), "1000-01-01")) < DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY b.modified
            LIMIT 300', [$days]);

        return array_map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            sprintf('ultima modifica %s: %s', substr((string) ($r->modified ?: $r->added), 0, 10), trim((string) $r->label)),
            $this->editorUrl((int) $r->bid),
        ), $rows);
    }
}
