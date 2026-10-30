<?php

namespace App\Fec\Legacy;

/**
 * The pure parts of com_jackpots' task data.update (controllers/data.php), used by
 * fec:jackpots:update: the per-table SELECT, the transform of its rows into jackpots,
 * the values the upsert writes per jos_jackpots column, and the comparison of those
 * values with a stored row as MariaDB keeps them (decimal(20,2), timestamps as strings).
 */
final class JackpotsFold
{
    /** Decimal columns of jos_jackpots (decimal(20,2)). */
    public const DECIMALS = ['amount', 'avg_win', 'avg_freq', 'last_hit_value'];

    /** The legacy SELECT for one tracker table (fixed columns, so every part of the UNION matches). */
    public static function unionPart(string $t): string
    {
        $tbl = '`'.str_replace('`', '``', $t).'`';

        return "
                SELECT
                    (SELECT gameId   FROM {$tbl} ORDER BY update_datetime DESC LIMIT 1) AS gameId,
                    (SELECT gameName FROM {$tbl} ORDER BY update_datetime DESC LIMIT 1) AS gameName,
                    (SELECT soft     FROM {$tbl} ORDER BY update_datetime DESC LIMIT 1) AS soft,
                    (SELECT currency FROM {$tbl} ORDER BY update_datetime DESC LIMIT 1) AS currency,

                    (SELECT amount FROM {$tbl} ORDER BY update_datetime DESC LIMIT 1) AS value,
                    (SELECT COUNT(hit) FROM {$tbl} WHERE hit IS NOT NULL) AS hits,
                    (SELECT AVG(hit_amount) FROM {$tbl} WHERE hit_amount IS NOT NULL) AS avghit,
                    (SELECT TIMESTAMPDIFF(HOUR, MIN(hit_date), MAX(hit_date)) / (COUNT(*)-1)
                       FROM {$tbl}
                      WHERE hit_date IS NOT NULL
                    ) AS avgfreq,
                    (SELECT hit_date FROM {$tbl} ORDER BY hit_date DESC LIMIT 1) AS lasthitdate,
                    (SELECT hit_amount FROM {$tbl} ORDER BY hit_date DESC LIMIT 1) AS lasthitamount,
                    (SELECT update_datetime FROM {$tbl} ORDER BY update_datetime DESC LIMIT 1) AS lastupdate
            ";
    }

    /**
     * The legacy transform: one jackpot per row with a gameId; cid = the number before the
     * first underscore of the gameId.
     *
     * @param  iterable<object>  $rows
     * @return list<array<string, mixed>>
     */
    public static function transform(iterable $rows): array
    {
        $jackpots = [];
        foreach ($rows as $row) {
            if (! isset($row->gameId) || $row->gameId === '') {
                continue; // table empty or malformed
            }
            $getpref = explode('_', (string) $row->gameId, 2);
            $jackpots[] = [
                'jid' => (string) $row->gameId,
                'cid' => (int) ($getpref[0] ?? ''),
                'gid' => '',
                'name' => trim((string) ($row->gameName ?? '')),
                'currency' => (string) ($row->currency ?? ''),
                'amount' => isset($row->value) ? (float) $row->value : null,
                'soft' => (string) ($row->soft ?? ''),
                'hits' => isset($row->hits) ? (int) $row->hits : 0,
                'avgwin' => isset($row->avghit) ? (float) $row->avghit : null,
                'avgfreq' => isset($row->avgfreq) ? (float) $row->avgfreq : null,
                'lastupdate' => (string) ($row->lastupdate ?? ''),
                'lasthitdate' => (string) ($row->lasthitdate ?? ''),
                'lasthitamount' => isset($row->lasthitamount) ? (float) $row->lasthitamount : null,
            ];
        }

        return $jackpots;
    }

    /**
     * The values the legacy upsert wrote for a jackpot, by jos_jackpots column
     * (gid NULL when empty, dates NULL when empty).
     *
     * @param  array<string, mixed>  $j
     * @return array<string, mixed>
     */
    public static function columns(array $j): array
    {
        return [
            'cid' => (int) $j['cid'],
            'gid' => $j['gid'] === '' ? null : $j['gid'],
            'name' => $j['name'],
            'currency' => $j['currency'],
            'amount' => $j['amount'],
            'soft' => $j['soft'],
            'avg_win' => $j['avgwin'],
            'avg_freq' => $j['avgfreq'],
            'hits' => (int) $j['hits'],
            'last_update' => $j['lastupdate'] === '' ? null : $j['lastupdate'],
            'last_hit_date' => $j['lasthitdate'] === '' ? null : $j['lasthitdate'],
            'last_hit_value' => $j['lasthitamount'],
        ];
    }

    /**
     * Columns where the stored row differs from what the upsert would write, compared as
     * MariaDB stores them (decimals rounded to 2 places, dates as strings, NULLs kept).
     *
     * @param  array<string, mixed>  $cols
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function differences(array $cols, array $row): array
    {
        $diff = [];
        foreach ($cols as $c => $v) {
            $s = $row[$c] ?? null;
            if ($v === null || $s === null) {
                $same = $v === null && $s === null;
            } elseif (in_array($c, self::DECIMALS, true)) {
                $same = abs(round((float) $v, 2) - round((float) $s, 2)) < 0.005;
            } elseif ($c === 'cid' || $c === 'hits') {
                $same = (int) $v === (int) $s;
            } else {
                $same = (string) $v === (string) $s;
            }
            if (! $same) {
                $diff[] = $c;
            }
        }

        return $diff;
    }
}
