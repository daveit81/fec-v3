<?php

namespace App\Console\Commands\Legacy;

use App\Fec\Legacy\JackpotsFold;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * php artisan fec:jackpots:update [--dry-run] [--tables=N] [--show=N]
 *
 * Port of com_jackpots' task data.update (controllers/data.php), which the
 * crontab reached through the web (/home/freeextr/bin/jackpots_update.sh):
 * for every tracker table of the jackpot database ("113_xxx", one per
 * jackpot, written by the parsers) the latest gameId/name/soft/currency/
 * amount, the hit count, average hit, average hours between hits, last hit
 * and last update, folded into jos_jackpots (jid unique). jos_jackpots feeds
 * the jackpots section of the casino reviews (fec_get_casino_jackpots).
 *
 * Same SQL per table, same chunks of 200, same transform, same values as the
 * legacy upsert. One difference, no effect on the data: rows that exist are
 * updated with UPDATE, only new jids go through INSERT ... ON DUPLICATE KEY
 * UPDATE, so a run no longer burns one AUTO_INCREMENT id per jackpot (the
 * column is int(11) and the counter was past 1.4 billion in the schema dump
 * of 25 Oct 2026; --dry-run prints where it stands).
 *
 * --dry-run reads and transforms, writes nothing, and compares the result
 * with jos_jackpots: same / newer data from the parsers / new / mismatch (a
 * row whose last_update is unchanged but whose values differ: a port bug).
 */
class JackpotsUpdate extends Command
{
    protected $signature = 'fec:jackpots:update
                            {--dry-run : Compute and compare with jos_jackpots, write nothing}
                            {--tables=0 : Only the first N tracker tables (a quick test)}
                            {--show=10 : With --dry-run, how many mismatching rows to print}';

    protected $description = 'Fold the jackpot tracker tables into jos_jackpots (legacy task com_jackpots data.update)';

    private const CHUNK = 200;

    private string $runId = '';

    public function handle(): int
    {
        $start = microtime(true);
        $this->runId = date('Ymd_His').'_'.substr(md5(uniqid('', true)), 0, 6);
        $dry = (bool) $this->option('dry-run');

        $ex = DB::connection('jptest');
        $database = (string) $ex->getDatabaseName();
        $this->log('START data.update'.($dry ? ' (dry run)' : ''));
        $this->log('External DB target: '.$database);

        try {
            $ex->getPdo();
        } catch (Throwable $e) {
            $this->log('External DB ERROR: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->log('External DB OK');

        // Table list: the same query as the legacy task (no ORDER BY: the server's order, as before).
        try {
            $trows = $ex->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]);
        } catch (Throwable $e) {
            $this->log('TABLE LIST ERROR: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->log('Tables found: '.count($trows));
        if (count($trows) === 0) {
            $this->log('No tables found. END.');

            return self::SUCCESS;
        }

        $unionParts = [];
        $skippedBadName = 0;
        foreach ($trows as $table) {
            $t = (string) ($table->TABLE_NAME ?? $table->table_name ?? '');
            // Only sane jackpot tables like "113_xxx", "817_xxx" (excludes "0_" and the rest).
            if (! preg_match('/^\d+_[a-z0-9]/i', $t)) {
                $skippedBadName++;

                continue;
            }
            $unionParts[] = JackpotsFold::unionPart($t);
        }
        $this->log('Union parts: '.count($unionParts).' skipped(bad name): '.$skippedBadName);

        $limit = (int) $this->option('tables');
        if ($limit > 0 && $limit < count($unionParts)) {
            $unionParts = array_slice($unionParts, 0, $limit);
            $this->log('Limited to the first '.$limit.' tables (--tables)');
        }
        if (count($unionParts) === 0) {
            $this->log('No union parts after filtering. END.');

            return self::SUCCESS;
        }

        $chunks = (int) ceil(count($unionParts) / self::CHUNK);
        $this->log('Executing UNION in chunks. chunkSize='.self::CHUNK.', chunks='.$chunks);
        $rowsAll = [];
        for ($c = 0; $c < $chunks; $c++) {
            $chunk = array_slice($unionParts, $c * self::CHUNK, self::CHUNK);
            $unionSql = implode(' UNION ALL ', $chunk);
            $this->log('Chunk '.($c + 1).'/'.$chunks.'. parts='.count($chunk));
            try {
                foreach ($ex->select($unionSql) as $r) {
                    $rowsAll[] = $r;
                }
            } catch (Throwable $e) {
                $this->log('UNION ERROR (chunk '.($c + 1).') '.$e->getMessage());
                $this->log('SQL head: '.substr($unionSql, 0, 400));

                return self::FAILURE;
            }
        }
        $this->log('Union total rows: '.count($rowsAll));

        $jackpots = JackpotsFold::transform($rowsAll);
        $this->log('Jackpots prepared: '.count($jackpots));

        $result = $dry ? $this->compare($jackpots) : $this->store($jackpots);

        $this->log('END data.update elapsed='.number_format(microtime(true) - $start, 3).'s');

        return $result;
    }

    /** @param  list<array<string, mixed>>  $jackpots */
    private function store(array $jackpots): int
    {
        $db = DB::connection('fec_rw');
        $table = $db->getTablePrefix().'jackpots';

        $existing = [];
        try {
            foreach ($db->select('SELECT jid FROM `'.$table.'`') as $r) {
                $existing[(string) $r->jid] = true;
            }
        } catch (Throwable $e) {
            $this->log('READ '.$table.' ERROR: '.$e->getMessage());

            return self::FAILURE;
        }

        $updated = 0;
        $inserted = 0;
        $fail = 0;
        foreach ($jackpots as $j) {
            if ($j['jid'] === '') {
                continue;
            }
            $cols = JackpotsFold::columns($j);
            try {
                if (isset($existing[$j['jid']])) {
                    $this->updateRow($db, $table, $j['jid'], $cols);
                    $updated++;
                } else {
                    $this->insertRow($db, $table, $j['jid'], $cols);
                    $existing[$j['jid']] = true;
                    $inserted++;
                }
            } catch (Throwable $e) {
                $fail++;
                $this->log('UPSERT failed jid='.$j['jid'].' '.$e->getMessage());
            }
        }
        $this->log('UPSERT summary: ok='.($updated + $inserted).' fail='.$fail.' (updated='.$updated.' inserted='.$inserted.')');

        return $fail > 0 && $updated + $inserted === 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param  array<string, mixed>  $cols */
    private function updateRow(ConnectionInterface $db, string $table, string $jid, array $cols): void
    {
        $set = implode(', ', array_map(fn ($c) => '`'.$c.'` = ?', array_keys($cols)));
        $db->update('UPDATE `'.$table.'` SET '.$set.' WHERE `jid` = ?', [...array_values($cols), $jid]);
    }

    /**
     * The legacy statement, for a jid not in the table: size/type/parent/logo are NOT NULL
     * and get '', published 1; a jid that exists after all (another writer, a case variant
     * under the case-insensitive unique key) is updated as before.
     *
     * @param  array<string, mixed>  $cols
     */
    private function insertRow(ConnectionInterface $db, string $table, string $jid, array $cols): void
    {
        $db->insert(
            'INSERT INTO `'.$table.'` (
                jid, cid, gid, name, currency, amount,
                size, type, soft,
                avg_win, avg_freq, hits,
                last_update, last_hit_date, last_hit_value,
                parent, published, logo
            ) VALUES (?, ?, ?, ?, ?, ?, \'\', \'\', ?, ?, ?, ?, ?, ?, ?, \'\', 1, \'\')
            ON DUPLICATE KEY UPDATE
                cid = VALUES(cid),
                gid = VALUES(gid),
                name = VALUES(name),
                currency = VALUES(currency),
                amount = VALUES(amount),
                soft = VALUES(soft),
                avg_win = VALUES(avg_win),
                avg_freq = VALUES(avg_freq),
                hits = VALUES(hits),
                last_update = VALUES(last_update),
                last_hit_date = VALUES(last_hit_date),
                last_hit_value = VALUES(last_hit_value)',
            [
                $jid, $cols['cid'], $cols['gid'], $cols['name'], $cols['currency'], $cols['amount'],
                $cols['soft'],
                $cols['avg_win'], $cols['avg_freq'], $cols['hits'],
                $cols['last_update'], $cols['last_hit_date'], $cols['last_hit_value'],
            ]
        );
    }

    /** @param  list<array<string, mixed>>  $jackpots */
    private function compare(array $jackpots): int
    {
        $db = DB::connection('fec');
        $table = $db->getTablePrefix().'jackpots';
        $stored = [];
        foreach ($db->select('SELECT jid, cid, gid, name, currency, amount, soft, avg_win, avg_freq, hits, last_update, last_hit_date, last_hit_value FROM `'.$table.'`') as $r) {
            $stored[(string) $r->jid] = (array) $r;
        }

        $counts = ['same' => 0, 'newer' => 0, 'new' => 0, 'mismatch' => 0, 'repeated jid' => 0];
        $seen = [];
        $shown = 0;
        $show = max(0, (int) $this->option('show'));
        foreach ($jackpots as $j) {
            if (isset($seen[$j['jid']])) {
                $counts['repeated jid']++;
            }
            $seen[$j['jid']] = true;
            $row = $stored[$j['jid']] ?? null;
            if ($row === null) {
                $counts['new']++;

                continue;
            }
            $diff = JackpotsFold::differences(JackpotsFold::columns($j), $row);
            if ($diff === []) {
                $counts['same']++;
            } elseif (in_array('last_update', $diff, true)) {
                $counts['newer']++;
            } else {
                $counts['mismatch']++;
                if ($shown++ < $show) {
                    $cols = JackpotsFold::columns($j);
                    $this->line('  mismatch '.$j['jid'].': '.implode(', ', array_map(
                        fn ($c) => $c.' '.var_export($row[$c], true).' -> '.var_export($cols[$c], true),
                        $diff
                    )));
                }
            }
        }
        $this->log('Dry run against '.$table.' ('.count($stored).' rows): '.implode(', ', array_map(
            fn ($k, $v) => $k.'='.$v, array_keys($counts), $counts
        )));
        $this->log('Rows of '.$table.' no tracker table produced: '.count(array_diff_key($stored, $seen)));

        $auto = $db->selectOne('SELECT AUTO_INCREMENT AS a FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$db->getDatabaseName(), $table]);
        if ($auto !== null && $auto->a !== null) {
            $this->log('AUTO_INCREMENT of '.$table.': '.number_format((float) $auto->a, 0, '.', ',').' of 2,147,483,647');
        }

        return $counts['mismatch'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function log(string $msg): void
    {
        $this->line('['.$this->runId.'] '.$msg);
    }
}
