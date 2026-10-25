<?php

namespace App\DeskLint\Rules;

use App\Models\Fec\Casino;

/** Live bonuses on a casino flagged closed, suspended or blacklisted. */
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
        $truthy = Casino::TRUTHY;

        $rows = $this->db()->table('bonuses as b')
            ->join('jreviews_content as j', 'j.contentid', '=', 'b.cid')
            ->selectRaw('b.cid, COUNT(*) AS n, MAX(j.jr_closed) AS closed, MAX(j.jr_suspended) AS suspended, MAX(j.jr_blacklisted) AS blacklisted')
            ->whereRaw($this->activeWhere())
            ->where(function ($q) use ($truthy) {
                $q->whereIn('j.jr_closed', $truthy)
                    ->orWhereIn('j.jr_suspended', $truthy)
                    ->orWhereIn('j.jr_blacklisted', $truthy);
            })
            ->groupBy('b.cid')
            ->get();

        return $rows->map(function ($r) use ($truthy) {
            $flags = array_keys(array_filter([
                'chiuso' => in_array((string) $r->closed, $truthy, true),
                'sospeso' => in_array((string) $r->suspended, $truthy, true),
                'blacklist' => in_array((string) $r->blacklisted, $truthy, true),
            ]));

            return $this->finding(
                (string) $r->cid,
                $this->casinoName((int) $r->cid),
                sprintf('%d bonus attivi su casino %s', $r->n, implode(', ', $flags)),
            );
        })->all();
    }
}
