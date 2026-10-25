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
        $rows = $this->db()->table('bonuses as b')
            ->select('b.bid', 'b.cid', 'b.label', 'b.end')
            ->whereRaw($this->activeWhere())
            ->whereRaw("b.end > '1000-01-01' AND b.end < NOW()")
            ->orderBy('b.end')
            ->limit(200)
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            sprintf('scaduto il %s: %s', substr((string) $r->end, 0, 10), trim((string) $r->label)),
            $this->editorUrl((int) $r->bid),
        ))->all();
    }
}
