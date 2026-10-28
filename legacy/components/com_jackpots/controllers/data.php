<?php
// No direct access
set_time_limit(0);
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.controller');

class JackpotsControllerData extends JController
{
    public function update()
    {
        // ---------- DEBUG (disattiva quando sei a posto) ----------
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        header('Content-Type: text/plain; charset=utf-8');

        // ---------- LOG ----------
        $logFile = '/tmp/jackpots_task.log';
        $runId   = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6);
        $start   = microtime(true);

        $log = function($msg) use ($logFile, $runId) {
            $line = "[".$runId."] ".$msg;
            echo $line . "\n";
            @file_put_contents($logFile, $line . "\n", FILE_APPEND);
            @flush();
        };

        // ---------- CONFIG: DB ESTERNO (tabelle jackpot per-game) ----------
        $option = array(
            'driver'   => 'mysqli',
            'host'     => 'localhost',
            'user'     => 'freeextr_dfiocch',
            'password' => 'semovente170381.',
            'database' => 'freeextr_jptest',
            'prefix'   => '',
        );

        $log("START data.update");
        $log("External DB target: ".$option['database']);

        // ---------- CONNESSIONE DB ESTERNO ----------
        $exdb = JDatabase::getInstance($option);
        if ($exdb->getErrorNum()) {
            $log("External DB ERROR ".$exdb->getErrorNum().": ".$exdb->getErrorMsg());
            return;
        }
        $log("External DB OK");

        // ---------- LISTA TABELLE ----------
        $qt = $exdb->getQuery(true);
        $qt->select('TABLE_NAME');
        $qt->from('information_schema.TABLES');
        $qt->where('TABLE_SCHEMA = "'.$option['database'].'"');

        $exdb->setQuery($qt);
        $trows = $exdb->loadObjectList();

        if ($exdb->getErrorNum()) {
            $log("TABLE LIST ERROR ".$exdb->getErrorNum().": ".$exdb->getErrorMsg());
            return;
        }

        $log("Tables found: ".count($trows));
        if (!$trows || count($trows) === 0) {
            $log("No tables found. END.");
            return;
        }

        // ---------- BUILD UNION PARTS (NO SELECT * - schema-safe) ----------
        $unionParts = array();
        $skippedBadName = 0;

        foreach ($trows as $table) {
            $t = $table->TABLE_NAME;

            // Include only sane jackpot tables like "113_xxx", "817_xxx" etc.
            // This excludes broken names like "0_" and non-jackpot tables.
            if (!preg_match('/^\d+_[a-z0-9]/i', $t)) {
                $skippedBadName++;
                continue;
            }

            $tbl = '`' . str_replace('`', '``', $t) . '`';

            // Fixed output columns so all UNION SELECTs match in column count.
            $unionParts[] = "
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

        $log("Union parts: ".count($unionParts)." skipped(bad name): ".$skippedBadName);

        if (count($unionParts) === 0) {
            $log("No union parts after filtering. END.");
            return;
        }

        // ---------- EXECUTE UNION IN CHUNKS ----------
        $chunkSize = 200;
        $rowsAll = array();
        $chunks = ceil(count($unionParts) / $chunkSize);

        $log("Executing UNION in chunks. chunkSize={$chunkSize}, chunks={$chunks}");

        for ($c = 0; $c < $chunks; $c++) {
            $chunk = array_slice($unionParts, $c * $chunkSize, $chunkSize);
            $unionSql = implode(" UNION ALL ", $chunk);

            $log("Chunk ".($c+1)."/{$chunks}. parts=".count($chunk));

            $exdb->setQuery($unionSql);
            $rows = $exdb->loadObjectList();

            if ($exdb->getErrorNum()) {
                $log("UNION ERROR (chunk ".($c+1).") ".$exdb->getErrorNum().": ".$exdb->getErrorMsg());
                $log("SQL head: ".substr($unionSql, 0, 400));
                return;
            }

            if ($rows && count($rows)) {
                foreach ($rows as $r) $rowsAll[] = $r;
            }
        }

        $log("Union total rows: ".count($rowsAll));

        // ---------- TRANSFORM -> JACKPOTS ----------
        $jackpots = array();

        foreach ($rowsAll as $row) {
            if (!isset($row->gameId) || $row->gameId === null || $row->gameId === '') {
                continue; // table empty or malformed
            }

            $getpref = explode("_", $row->gameId, 2);
            $pref = isset($getpref[0]) ? $getpref[0] : '';

            $jackpots[] = array(
                'jid' => (string)$row->gameId,
                'cid' => (int)$pref,
                'gid' => '',
                'name' => trim((string)$row->gameName),
                'currency' => (string)$row->currency,
                'amount' => ($row->value === null ? null : (float)$row->value),
                'soft' => (string)$row->soft,
                'hits' => ($row->hits === null ? 0 : (int)$row->hits),
                'avgwin' => ($row->avghit === null ? null : (float)$row->avghit),
                'avgfreq' => ($row->avgfreq === null ? null : (float)$row->avgfreq),
                'lastupdate' => (string)$row->lastupdate,
                'lasthitdate' => (string)$row->lasthitdate,
                'lasthitamount' => ($row->lasthitamount === null ? null : (float)$row->lasthitamount),
            );
        }

        $log("Jackpots prepared: ".count($jackpots));

        // ---------- UPSERT INTO JOOMLA DB (#__jackpots, jid UNIQUE) ----------
        $dbo = JFactory::getDbo();

        $ok = 0;
        $fail = 0;

        foreach ($jackpots as $j) {
            if (empty($j['jid'])) continue;

            // Fields size/type/parent/published/logo are NOT NULL in your table, so we pass safe defaults.
            $sql = "
                INSERT INTO ".$dbo->quoteName('#__jackpots')." (
                    jid, cid, gid, name, currency, amount,
                    size, type, soft,
                    avg_win, avg_freq, hits,
                    last_update, last_hit_date, last_hit_value,
                    parent, published, logo
                ) VALUES (
                    ".$dbo->quote($j['jid']).",
                    ".(int)$j['cid'].",
                    ".($j['gid'] === '' ? 'NULL' : $dbo->quote($j['gid'])).",
                    ".$dbo->quote($j['name']).",
                    ".$dbo->quote($j['currency']).",
                    ".(is_null($j['amount']) ? 'NULL' : (float)$j['amount']).",
                    ".$dbo->quote('').",
                    ".$dbo->quote('').",
                    ".$dbo->quote($j['soft']).",
                    ".(is_null($j['avgwin']) ? 'NULL' : (float)$j['avgwin']).",
                    ".(is_null($j['avgfreq']) ? 'NULL' : (float)$j['avgfreq']).",
                    ".(int)$j['hits'].",
                    ".($j['lastupdate'] === '' ? 'NULL' : $dbo->quote($j['lastupdate'])).",
                    ".($j['lasthitdate'] === '' ? 'NULL' : $dbo->quote($j['lasthitdate'])).",
                    ".(is_null($j['lasthitamount']) ? 'NULL' : (float)$j['lasthitamount']).",
                    ".$dbo->quote('').",
                    1,
                    ".$dbo->quote('')."
                )
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
                    last_hit_value = VALUES(last_hit_value)
            ";

            try {
                $dbo->setQuery($sql);
                if (method_exists($dbo, 'execute')) $dbo->execute();
                else $dbo->query();
                $ok++;
            } catch (Exception $e) {
                $fail++;
                $log("UPSERT failed jid={$j['jid']} ".$e->getMessage());
            }
        }

        $log("UPSERT summary: ok={$ok} fail={$fail}");

        $elapsed = microtime(true) - $start;
        $log("END data.update elapsed=".number_format($elapsed, 3)."s");
    }
}
