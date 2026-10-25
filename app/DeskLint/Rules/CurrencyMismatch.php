<?php

namespace App\DeskLint\Rules;

/** Live bonuses of the same casino listed in different currencies. */
final class CurrencyMismatch extends BaseRule
{
    public function id(): string
    {
        return 'currency_mismatch';
    }

    public function title(): string
    {
        return 'Valuta diversa fra bonus attivi dello stesso casino';
    }

    public function run(): array
    {
        $rows = $this->select('
            SELECT b.cid, COUNT(DISTINCT LOWER(TRIM(b.currency))) AS currencies,
                   GROUP_CONCAT(DISTINCT LOWER(TRIM(b.currency)) ORDER BY LOWER(TRIM(b.currency)) SEPARATOR ", ") AS list,
                   COUNT(*) AS bonuses
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND TRIM(COALESCE(b.currency, "")) <> ""
            GROUP BY b.cid
            HAVING COUNT(DISTINCT LOWER(TRIM(b.currency))) > 1
            ORDER BY bonuses DESC');

        return array_map(fn ($r) => $this->finding(
            (string) $r->cid,
            $this->casinoName((int) $r->cid),
            sprintf('%d bonus attivi in %d valute: %s', $r->bonuses, $r->currencies, $r->list),
        ), $rows);
    }
}
