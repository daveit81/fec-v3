<?php

namespace App\DeskLint\Rules;

/** FXRate: active welcome packages with a match leg that has no cap. */
final class PackageLegWithoutCap extends BaseRule
{
    public function id(): string
    {
        return 'package_leg_without_cap';
    }

    public function title(): string
    {
        return 'Pacchetti FXRate attivi con leg di match senza cap';
    }

    public function run(): array
    {
        $rows = $this->db()->table('fec_bonus_package_legs as l')
            ->join('fec_bonus_packages as p', 'p.pkg_id', '=', 'l.pkg_id')
            ->selectRaw('p.pkg_id, p.cid, p.title, GROUP_CONCAT(l.position ORDER BY l.position) AS legs')
            ->where('p.status', 'active')
            ->where('l.pct', '>', 0)
            ->whereRaw('(l.cap IS NULL OR l.cap <= 0)')
            ->groupBy('p.pkg_id', 'p.cid', 'p.title')
            ->get();

        return $rows->map(fn ($r) => $this->finding(
            (string) $r->pkg_id,
            sprintf('%s (pacchetto #%d)', $this->casinoName((int) $r->cid), $r->pkg_id),
            sprintf('leg senza cap: %s; %s', $r->legs, trim((string) $r->title)),
        ))->all();
    }
}
