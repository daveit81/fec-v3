<?php

namespace App\DeskLint\Rules;

/** Live bonuses without a tracked link: the "Visit casino" button goes nowhere. */
final class MissingLink extends BaseRule
{
    public function id(): string
    {
        return 'missing_link';
    }

    public function title(): string
    {
        return 'Bonus attivi senza link';
    }

    public function run(): array
    {
        $rows = $this->db()->table('bonuses as b')
            ->select('b.bid', 'b.cid', 'b.label')
            ->whereRaw($this->activeWhere())
            ->whereRaw("TRIM(COALESCE(b.link, '')) = ''")
            ->limit(200)
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            trim((string) $r->label),
            $this->editorUrl((int) $r->bid),
        ))->all();
    }
}
