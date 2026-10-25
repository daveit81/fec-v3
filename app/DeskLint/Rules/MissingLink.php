<?php

namespace App\DeskLint\Rules;

/**
 * Live bonuses with no tracked link of their own AND no casino default link
 * (jreviews_content.jr_gourl): the "Visit casino" button has nowhere to go.
 * An empty bonus link alone is normal (the casino link is used).
 */
final class MissingLink extends BaseRule
{
    public function id(): string
    {
        return 'missing_link';
    }

    public function title(): string
    {
        return 'Bonus attivi senza link, su casino senza link di default';
    }

    public function run(): array
    {
        $rows = $this->select('
            SELECT b.bid, b.cid, b.label
            FROM '.$this->t('bonuses').' b
            LEFT JOIN '.$this->t('jreviews_content').' j ON j.contentid = b.cid
            WHERE '.$this->activeWhere().'
              AND TRIM(COALESCE(b.link, "")) = ""
              AND TRIM(COALESCE(j.jr_gourl, "")) = ""
            ORDER BY b.cid, b.bid');

        return array_map(fn ($r) => $this->finding(
            (string) $r->bid,
            sprintf('#%d %s', $r->bid, $this->casinoName((int) $r->cid)),
            trim((string) $r->label),
            $this->editorUrl((int) $r->bid),
        ), $rows);
    }
}
