<?php

namespace App\DeskLint\Rules;

/** Live bonuses whose end date is in the past (zero dates ignored). */
final class EndedButActive extends BaseRule
{
    public function id(): string
    {
        return 'ended_but_active';
    }

    public function title(): string
    {
        return 'Bonus attivi con data di fine passata';
    }

    public function run(): array
    {
        $rows = $this->select('
            SELECT b.bid, b.cid, b.label, b.end
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND b.end > "1000-01-01" AND b.end < NOW()
            ORDER BY b.end
            LIMIT 200');

        return array_map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            sprintf('scaduto il %s: %s', substr((string) $r->end, 0, 10), trim((string) $r->label)),
            $this->editorUrl((int) $r->bid),
        ), $rows);
    }
}
