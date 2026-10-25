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
        $rows = $this->select('
            SELECT p.pkg_id, p.cid, p.title, GROUP_CONCAT(l.position ORDER BY l.position) AS legs
            FROM '.$this->t('fec_bonus_package_legs').' l
            JOIN '.$this->t('fec_bonus_packages').' p ON p.pkg_id = l.pkg_id
            WHERE p.status = "active" AND l.pct > 0 AND (l.cap IS NULL OR l.cap <= 0)
            GROUP BY p.pkg_id, p.cid, p.title
            ORDER BY p.pkg_id');

        return array_map(fn ($r) => $this->finding(
            (string) $r->pkg_id,
            sprintf('%s (pacchetto #%d)', $this->casinoName((int) $r->cid), $r->pkg_id),
            sprintf('leg senza cap: %s; %s', $r->legs, trim((string) $r->title)),
        ), $rows);
    }
}
