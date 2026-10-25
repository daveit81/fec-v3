<?php

namespace App\DeskLint\Rules;

use App\Models\Fec\Casino;

/** FXClock enabled on a casino that is blacklisted or closed. */
final class ClockBrandOnBlacklisted extends BaseRule
{
    public function id(): string
    {
        return 'clock_on_blacklisted';
    }

    public function title(): string
    {
        return 'Brand FXClock abilitato su casino in blacklist o chiuso';
    }

    public function run(): array
    {
        $truthy = Casino::TRUTHY;

        $rows = $this->db()->table('fec_clock_brands as cb')
            ->join('jreviews_content as j', 'j.contentid', '=', 'cb.casino_id')
            ->select('cb.brand_slug', 'cb.casino_id', 'j.jr_blacklisted', 'j.jr_closed')
            ->where('cb.enabled', 1)
            ->where(function ($q) use ($truthy) {
                $q->whereIn('j.jr_blacklisted', $truthy)->orWhereIn('j.jr_closed', $truthy);
            })
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->brand_slug,
            $r->brand_slug,
            sprintf('%s: %s', $this->casinoName((int) $r->casino_id), in_array((string) $r->jr_blacklisted, $truthy, true) ? 'blacklist' : 'chiuso'),
        ))->all();
    }
}
