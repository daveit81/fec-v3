<?php

namespace App\DeskLint\Rules;

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
        $rows = $this->select('
            SELECT cb.brand_slug, cb.casino_id,
                   '.$this->flagSet('j.jr_blacklisted').' AS blacklisted,
                   '.$this->flagSet('j.jr_closed').' AS closed
            FROM '.$this->t('fec_clock_brands').' cb
            JOIN '.$this->t('jreviews_content').' j ON j.contentid = cb.casino_id
            WHERE cb.enabled = 1 AND ('.$this->flagSet('j.jr_blacklisted').' OR '.$this->flagSet('j.jr_closed').')
            ORDER BY cb.brand_slug');

        return array_map(fn ($r) => $this->finding(
            (string) $r->brand_slug,
            $r->brand_slug,
            sprintf('%s: %s', $this->casinoName((int) $r->casino_id), $r->blacklisted ? 'blacklist' : 'chiuso'),
        ), $rows);
    }
}
