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
        $rows = $this->select('
            SELECT b.bid, b.cid, b.label
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND TRIM(COALESCE(b.link, "")) = ""
            ORDER BY b.bid
            LIMIT 200');

        return array_map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            trim((string) $r->label),
            $this->editorUrl((int) $r->bid),
        ), $rows);
    }
}
