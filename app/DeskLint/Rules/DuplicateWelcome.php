<?php

namespace App\DeskLint\Rules;

/** More than one live welcome offer on the same casino (types from config fec.bonus.welcome_types; "su" = sign-up). */
final class DuplicateWelcome extends BaseRule
{
    public function id(): string
    {
        return 'duplicate_welcome';
    }

    public function title(): string
    {
        return 'Due o piu welcome offer attive sullo stesso casino';
    }

    public function run(): array
    {
        $types = array_map('strtolower', config('fec.bonus.welcome_types', []));
        if ($types === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($types), '?'));

        $rows = $this->select('
            SELECT b.cid, COUNT(*) AS n, GROUP_CONCAT(b.bid ORDER BY b.bid SEPARATOR ", ") AS bids
            FROM '.$this->t('bonuses').' b
            WHERE '.$this->activeWhere().' AND LOWER(TRIM(b.type)) IN ('.$marks.')
            GROUP BY b.cid
            HAVING COUNT(*) > 1
            ORDER BY n DESC', $types);

        return array_map(fn ($r) => $this->finding(
            (string) $r->cid,
            $this->casinoName((int) $r->cid),
            sprintf('%d welcome attive (bid %s)', $r->n, $r->bids),
        ), $rows);
    }
}
