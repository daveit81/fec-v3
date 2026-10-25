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
        $rows = $this->db()->table('bonuses as b')
            ->leftJoin('jreviews_content as j', 'j.contentid', '=', 'b.cid')
            ->leftJoin('content as c', 'c.id', '=', 'b.cid')
            ->selectRaw('b.cid, COUNT(*) AS n, MAX(j.contentid IS NULL) AS no_sheet, MAX(c.id IS NULL OR c.state <> 1) AS no_article')
            ->whereRaw($this->activeWhere())
            ->whereRaw('(j.contentid IS NULL OR c.id IS NULL OR c.state <> 1)')
            ->groupBy('b.cid')
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->cid,
            $this->casinoName((int) $r->cid),
            sprintf('%d bonus attivi; %s%s', $r->n, $r->no_sheet ? 'nessuna scheda JReviews; ' : '', $r->no_article ? 'recensione assente o non pubblicata' : ''),
        ))->all();
    }
}
