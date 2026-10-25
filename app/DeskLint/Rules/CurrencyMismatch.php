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
        $rows = $this->db()->table('bonuses as b')
            ->selectRaw('b.cid, COUNT(DISTINCT TRIM(b.currency)) AS currencies, GROUP_CONCAT(DISTINCT TRIM(b.currency) ORDER BY TRIM(b.currency) SEPARATOR ", ") AS list, COUNT(*) AS bonuses')
            ->whereRaw($this->activeWhere())
            ->whereRaw("TRIM(COALESCE(b.currency, '')) <> ''")
            ->groupBy('b.cid')
            ->havingRaw('COUNT(DISTINCT TRIM(b.currency)) > 1')
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->cid,
            $this->casinoName((int) $r->cid),
            sprintf('%d bonus attivi in %d valute: %s', $r->bonuses, $r->currencies, $r->list),
        ))->all();
    }
}
