<?php

namespace App\DeskLint\Rules;

/** Live bonuses on a casino flagged closed, suspended or blacklisted (JReviews flags: non-empty = set). */
final class ActiveBonusOnClosedCasino extends BaseRule
{
    public function id(): string
    {
        return 'active_on_closed_casino';
    }

    public function title(): string
    {
        return 'Bonus attivi su casino chiusi, sospesi o in blacklist';
    }

    public function run(): array
    {
        $rows = $this->select('
            SELECT b.cid, COUNT(*) AS n,
                   MAX('.$this->flagSet('j.jr_closed').') AS closed,
                   MAX('.$this->flagSet('j.jr_suspended').') AS suspended,
                   MAX('.$this->flagSet('j.jr_blacklisted').') AS blacklisted
            FROM '.$this->t('bonuses').' b
            JOIN '.$this->t('jreviews_content').' j ON j.contentid = b.cid
            WHERE '.$this->activeWhere().'
              AND ('.$this->flagSet('j.jr_closed').' OR '.$this->flagSet('j.jr_suspended').' OR '.$this->flagSet('j.jr_blacklisted').')
            GROUP BY b.cid
            ORDER BY n DESC');

        return array_map(function ($r) {
            $flags = array_keys(array_filter([
                'chiuso' => (bool) $r->closed,
                'sospeso' => (bool) $r->suspended,
                'blacklist' => (bool) $r->blacklisted,
            ]));

            return $this->finding(
                (string) $r->cid,
                $this->casinoName((int) $r->cid),
                sprintf('%d bonus attivi su casino %s', $r->n, implode(', ', $flags)),
            );
        }, $rows);
    }
}
