<?php

namespace App\DeskLint\Rules;

/**
 * Live bonuses nobody has touched for config fec.desk_lint.stale_days days.
 *
 * First real run (26 Oct 2026): 2.145 of 2.529 live bonuses were older than
 * 180 days, i.e. the shape of the catalogue, not a list anyone can act on.
 * So this is a review queue, not a blacklist: the findings are the
 * stale_queue oldest bonuses (fingerprint = bid, so each one stays in the
 * report until the desk touches it and the next oldest takes its place),
 * and summary() carries the size of the whole backlog.
 */
final class StaleActive extends BaseRule
{
    public function id(): string
    {
        return 'stale_active';
    }

    public function title(): string
    {
        return sprintf('Coda di revisione: i %d bonus attivi non toccati da piu tempo (oltre %d giorni)',
            $this->queueSize(), (int) config('fec.desk_lint.stale_days'));
    }

    public function run(): array
    {
        $days = (int) config('fec.desk_lint.stale_days');
        $touched = 'GREATEST(COALESCE(NULLIF(b.modified, "0000-00-00 00:00:00"), "1000-01-01"),
                             COALESCE(NULLIF(b.added, "0000-00-00 00:00:00"), "1000-01-01"))';
        $where = $this->activeWhere().' AND '.$touched.' < DATE_SUB(NOW(), INTERVAL ? DAY)';

        $total = (int) ($this->select('SELECT COUNT(*) AS n FROM '.$this->t('bonuses').' b WHERE '.$where, [$days])[0]->n ?? 0);
        $live = (int) ($this->select('SELECT COUNT(*) AS n FROM '.$this->t('bonuses').' b WHERE '.$this->activeWhere())[0]->n ?? 0);
        $this->summary = sprintf('%s bonus attivi su %s non toccati da oltre %d giorni; qui sotto i %d piu vecchi',
            number_format($total, 0, ',', '.'), number_format($live, 0, ',', '.'), $days, $this->queueSize());

        $rows = $this->select('
            SELECT b.bid, b.cid, b.label, b.modified, b.added, '.$touched.' AS touched
            FROM '.$this->t('bonuses').' b
            WHERE '.$where.'
            ORDER BY touched ASC, b.bid ASC
            LIMIT '.$this->queueSize(), [$days]);

        return array_map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            sprintf('ultima modifica %s: %s', str_starts_with((string) $r->touched, '1000-') ? 'mai datata' : substr((string) $r->touched, 0, 10), trim((string) $r->label)),
            $this->editorUrl((int) $r->bid),
        ), $rows);
    }

    private function queueSize(): int
    {
        return max(1, (int) config('fec.desk_lint.stale_queue', 10));
    }
}
