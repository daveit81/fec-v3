<?php

namespace App\DeskLint\Rules;

/**
 * Live bonuses on a casino carrying one of the flags listed in config
 * fec.desk_lint.closed_flags (default: blacklisted only). The site hides
 * bonuses of closed and suspended casinos at render time, so those flags
 * are opt-in: 450 of the 900 sheets would otherwise be reported.
 */
final class ActiveBonusOnClosedCasino extends BaseRule
{
    public function id(): string
    {
        return 'active_on_closed_casino';
    }

    public function title(): string
    {
        return 'Bonus attivi su casino '.implode('/', $this->flags());
    }

    public function run(): array
    {
        $columns = array_intersect_key([
            'blacklist' => 'j.jr_blacklisted',
            'chiuso' => 'j.jr_closed',
            'sospeso' => 'j.jr_suspended',
        ], array_flip($this->flags()));
        if ($columns === []) {
            return [];
        }

        $selects = [];
        $where = [];
        foreach ($columns as $label => $column) {
            $selects[] = 'MAX('.$this->flagSet($column).') AS flag_'.md5($label);
            $where[] = $this->flagSet($column);
        }

        $rows = $this->select('
            SELECT b.cid, COUNT(*) AS n, '.implode(', ', $selects).'
            FROM '.$this->t('bonuses').' b
            JOIN '.$this->t('jreviews_content').' j ON j.contentid = b.cid
            WHERE '.$this->activeWhere().' AND ('.implode(' OR ', $where).')
            GROUP BY b.cid
            ORDER BY n DESC');

        return array_map(function ($r) use ($columns) {
            $set = [];
            foreach (array_keys($columns) as $label) {
                if ($r->{'flag_'.md5($label)}) {
                    $set[] = $label;
                }
            }

            return $this->finding(
                (string) $r->cid,
                $this->casinoName((int) $r->cid),
                sprintf('%d bonus attivi su casino %s', $r->n, implode(', ', $set)),
            );
        }, $rows);
    }

    /** @return list<string> labels among blacklist, chiuso, sospeso */
    private function flags(): array
    {
        $map = ['blacklisted' => 'blacklist', 'closed' => 'chiuso', 'suspended' => 'sospeso'];
        $out = [];
        foreach (config('fec.desk_lint.closed_flags', ['blacklisted']) as $flag) {
            if (isset($map[$flag])) {
                $out[] = $map[$flag];
            }
        }

        return $out;
    }
}
