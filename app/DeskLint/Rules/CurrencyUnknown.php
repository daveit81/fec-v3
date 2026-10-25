<?php

namespace App\DeskLint\Rules;

/**
 * Live bonuses whose currency code is not in the canonical set (config
 * fec.bonus.currencies). Found by the first probe: "eur" next to "euro".
 */
final class CurrencyUnknown extends BaseRule
{
    public function id(): string
    {
        return 'currency_unknown';
    }

    public function title(): string
    {
        return 'Bonus attivi con codice valuta fuori dall\'elenco canonico';
    }

    public function run(): array
    {
        $known = array_map('strtolower', config('fec.bonus.currencies', []));
        if ($known === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($known), '?'));

        $rows = $this->select('
            SELECT b.bid, b.cid, b.label, LOWER(TRIM(b.currency)) AS currency
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND TRIM(COALESCE(b.currency, "")) <> ""
              AND LOWER(TRIM(b.currency)) NOT IN ('.$marks.')
            ORDER BY currency, b.bid
            LIMIT 200', $known);

        return array_map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            sprintf('valuta "%s": %s', $r->currency, trim((string) $r->label)),
            $this->editorUrl((int) $r->bid),
        ), $rows);
    }
}
