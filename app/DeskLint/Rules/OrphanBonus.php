<?php

namespace App\DeskLint\Rules;

/** Live bonuses whose casino has no sheet or no published review article. */
final class OrphanBonus extends BaseRule
{
    public function id(): string
    {
        return 'orphan_bonus';
    }

    public function title(): string
    {
        return 'Bonus attivi su casino senza scheda o senza recensione pubblicata';
    }

    public function run(): array
    {
        $rows = $this->select('
            SELECT b.cid, COUNT(*) AS n,
                   MAX(j.contentid IS NULL) AS no_sheet,
                   MAX(c.id IS NULL OR c.state <> 1) AS no_article
            FROM '.$this->t('bonuses').' b
            LEFT JOIN '.$this->t('jreviews_content').' j ON j.contentid = b.cid
            LEFT JOIN '.$this->t('content').' c ON c.id = b.cid
            WHERE '.$this->activeWhere().' AND (j.contentid IS NULL OR c.id IS NULL OR c.state <> 1)
            GROUP BY b.cid
            ORDER BY n DESC');

        return array_map(fn ($r) => $this->finding(
            (string) $r->cid,
            $this->casinoName((int) $r->cid),
            sprintf('%d bonus attivi; %s%s', $r->n, $r->no_sheet ? 'nessuna scheda JReviews; ' : '', $r->no_article ? 'recensione assente o non pubblicata' : ''),
        ), $rows);
    }
}
