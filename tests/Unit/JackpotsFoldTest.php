<?php

namespace Tests\Unit;

use App\Fec\Legacy\JackpotsFold;
use PHPUnit\Framework\TestCase;

/** fec:jackpots:update = com_jackpots data.update: the same SQL, transform and column values. */
final class JackpotsFoldTest extends TestCase
{
    public function test_the_union_part_quotes_the_table_and_selects_the_legacy_columns(): void
    {
        $sql = JackpotsFold::unionPart('113_mega`moolah');
        $this->assertStringContainsString('FROM `113_mega``moolah` ORDER BY update_datetime DESC LIMIT 1) AS gameId', $sql);
        foreach (['gameName', 'soft', 'currency', 'value', 'hits', 'avghit', 'avgfreq', 'lasthitdate', 'lasthitamount', 'lastupdate'] as $alias) {
            $this->assertStringContainsString(') AS '.$alias, $sql);
        }
        $this->assertStringContainsString('TIMESTAMPDIFF(HOUR, MIN(hit_date), MAX(hit_date)) / (COUNT(*)-1)', $sql);
    }

    public function test_transform_skips_rows_without_game_id_and_takes_cid_from_its_prefix(): void
    {
        $rows = [
            (object) ['gameId' => null, 'gameName' => 'x'],
            (object) ['gameId' => '', 'gameName' => 'y'],
            (object) [
                'gameId' => '817_mega_moolah', 'gameName' => '  Mega Moolah ', 'soft' => 'microgaming', 'currency' => 'eur',
                'value' => '1234567.891', 'hits' => '12', 'avghit' => null, 'avgfreq' => '700.5000',
                'lasthitdate' => '2026-10-01 10:00:00', 'lasthitamount' => '5000000.00', 'lastupdate' => '2026-10-30 08:00:00',
            ],
            (object) [
                'gameId' => 'abc', 'gameName' => null, 'soft' => null, 'currency' => null,
                'value' => null, 'hits' => null, 'avghit' => null, 'avgfreq' => null,
                'lasthitdate' => null, 'lasthitamount' => null, 'lastupdate' => null,
            ],
        ];
        $j = JackpotsFold::transform($rows);
        $this->assertCount(2, $j);
        $this->assertSame('817_mega_moolah', $j[0]['jid']);
        $this->assertSame(817, $j[0]['cid']);
        $this->assertSame('Mega Moolah', $j[0]['name']);
        $this->assertSame(1234567.891, $j[0]['amount']);
        $this->assertSame(12, $j[0]['hits']);
        $this->assertNull($j[0]['avgwin']);
        $this->assertSame(700.5, $j[0]['avgfreq']);
        $this->assertSame(0, $j[1]['cid']);
        $this->assertSame(0, $j[1]['hits']);
        $this->assertSame('', $j[1]['lastupdate']);
    }

    public function test_columns_write_null_for_empty_gid_and_dates(): void
    {
        [$j] = JackpotsFold::transform([(object) ['gameId' => '5_x', 'value' => '1', 'hits' => '0', 'lastupdate' => '', 'lasthitdate' => '']]);
        $cols = JackpotsFold::columns($j);
        $this->assertNull($cols['gid']);
        $this->assertNull($cols['last_update']);
        $this->assertNull($cols['last_hit_date']);
        $this->assertSame(5, $cols['cid']);
        $this->assertSame(['cid', 'gid', 'name', 'currency', 'amount', 'soft', 'avg_win', 'avg_freq', 'hits', 'last_update', 'last_hit_date', 'last_hit_value'], array_keys($cols));
    }

    public function test_differences_compare_as_the_table_stores(): void
    {
        $cols = ['cid' => 817, 'gid' => null, 'name' => 'Mega', 'currency' => 'eur', 'amount' => 1234567.891, 'soft' => 'mg',
            'avg_win' => null, 'avg_freq' => 700.5, 'hits' => 12, 'last_update' => '2026-10-30 08:00:00',
            'last_hit_date' => '2026-10-01 10:00:00', 'last_hit_value' => 5000000.0];
        $row = ['cid' => '817', 'gid' => null, 'name' => 'Mega', 'currency' => 'eur', 'amount' => '1234567.89', 'soft' => 'mg',
            'avg_win' => null, 'avg_freq' => '700.50', 'hits' => '12', 'last_update' => '2026-10-30 08:00:00',
            'last_hit_date' => '2026-10-01 10:00:00', 'last_hit_value' => '5000000.00'];
        $this->assertSame([], JackpotsFold::differences($cols, $row));

        $row['amount'] = '1234500.00';
        $row['last_update'] = '2026-10-30 07:00:00';
        $this->assertSame(['amount', 'last_update'], JackpotsFold::differences($cols, $row));

        $row = ['avg_win' => '10.00'] + $row;
        $this->assertContains('avg_win', JackpotsFold::differences($cols, $row));
    }
}
