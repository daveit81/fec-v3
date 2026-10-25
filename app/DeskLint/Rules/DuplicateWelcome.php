<?php

namespace App\DeskLint\Rules;

/**
 * More than one live welcome offer on the same casino for the same market
 * (identical country_ok / country_no). Types from config fec.bonus.welcome_types
 * ("su" = sign-up). Per-market sign-up offers are expected; two for the same
 * countries are not.
 */
final class DuplicateWelcome extends BaseRule
{
    public function id(): string
    {
        return 'duplicate_welcome';
    }

    public function title(): string
    {
        return 'Due o piu welcome offer attive sullo stesso casino per gli stessi paesi';
    }

    public function run(): array
    {
        $types = array_map('strtolower', config('fec.bonus.welcome_types', []));
        if ($types === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($types), '?'));

        $rows = $this->select('
            SELECT b.cid, TRIM(COALESCE(b.country_ok, "")) AS country_ok, TRIM(COALESCE(b.country_no, "")) AS country_no,
                   COUNT(*) AS n, GROUP_CONCAT(b.bid ORDER BY b.bid SEPARATOR ", ") AS bids,
                   GROUP_CONCAT(DISTINCT LOWER(TRIM(b.currency)) ORDER BY LOWER(TRIM(b.currency)) SEPARATOR ", ") AS currencies
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND LOWER(TRIM(b.type)) IN ('.$marks.')
            GROUP BY b.cid, TRIM(COALESCE(b.country_ok, "")), TRIM(COALESCE(b.country_no, ""))
            HAVING COUNT(*) > 1
            ORDER BY n DESC', $types);

        return array_map(function ($r) {
            $ok = $r->country_ok !== '' ? 'solo '.$this->shortList($r->country_ok) : 'tutti i paesi';
            $no = $r->country_no !== '' ? ', esclusi '.$this->shortList($r->country_no) : '';

            return $this->finding(
                $r->cid.':'.md5($r->country_ok.'|'.$r->country_no),
                $this->casinoName((int) $r->cid).' ['.$ok.$no.']',
                sprintf('%d welcome attive (bid %s; valute %s)', $r->n, $r->bids, $r->currencies ?: '-'),
            );
        }, $rows);
    }
}
