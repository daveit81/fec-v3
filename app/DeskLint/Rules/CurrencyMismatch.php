<?php

namespace App\DeskLint\Rules;

/**
 * Live bonuses of the same casino, aimed at the same market, listed in
 * different currencies. "Same market" = identical country_ok / country_no
 * restrictions: a casino with an AUD bonus for Australia and a USD one for
 * the US is not inconsistent, two all-countries bonuses in euro and usd are.
 * Spellings are normalised (eur = euro, mbtc = btc) so that the slips land
 * in the currency_unknown rule instead of here. FEC_DESK_LINT_CURRENCY_BY_MARKET=false
 * restores the broad version (any mix of currencies on a casino).
 */
final class CurrencyMismatch extends BaseRule
{
    public function id(): string
    {
        return 'currency_mismatch';
    }

    public function title(): string
    {
        return config('fec.desk_lint.currency_by_market', true)
            ? 'Valuta diversa fra bonus attivi dello stesso casino rivolti agli stessi paesi'
            : 'Valuta diversa fra bonus attivi dello stesso casino';
    }

    public function run(): array
    {
        $byMarket = (bool) config('fec.desk_lint.currency_by_market', true);
        $cur = 'REPLACE(REPLACE(LOWER(TRIM(b.currency)), "eur", "euro"), "euroo", "euro")';
        $cur = 'REPLACE('.$cur.', "mbtc", "btc")';
        $market = $byMarket ? ', TRIM(COALESCE(b.country_ok, "")) AS country_ok, TRIM(COALESCE(b.country_no, "")) AS country_no' : '';
        $groupBy = $byMarket ? 'b.cid, TRIM(COALESCE(b.country_ok, "")), TRIM(COALESCE(b.country_no, ""))' : 'b.cid';

        $rows = $this->select('
            SELECT b.cid'.$market.',
                   COUNT(DISTINCT '.$cur.') AS currencies,
                   GROUP_CONCAT(DISTINCT '.$cur.' ORDER BY '.$cur.' SEPARATOR ", ") AS list,
                   GROUP_CONCAT(b.bid ORDER BY b.bid SEPARATOR ", ") AS bids,
                   COUNT(*) AS bonuses
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND TRIM(COALESCE(b.currency, "")) <> ""
            GROUP BY '.$groupBy.'
            HAVING COUNT(DISTINCT '.$cur.') > 1
            ORDER BY bonuses DESC');

        return array_map(function ($r) use ($byMarket) {
            $market = '';
            if ($byMarket) {
                $ok = $r->country_ok !== '' ? 'solo '.$this->shortList($r->country_ok) : 'tutti i paesi';
                $no = $r->country_no !== '' ? ', esclusi '.$this->shortList($r->country_no) : '';
                $market = ' ['.$ok.$no.']';
            }

            return $this->finding(
                $byMarket ? $r->cid.':'.md5($r->country_ok.'|'.$r->country_no) : (string) $r->cid,
                $this->casinoName((int) $r->cid).$market,
                sprintf('%d bonus attivi in %d valute: %s (bid %s)', $r->bonuses, $r->currencies, $r->list, $r->bids),
            );
        }, $rows);
    }
}
