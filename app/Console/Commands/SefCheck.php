<?php

namespace App\Console\Commands;

use App\Fec\Legacy\Sef\Sh404sef;
use App\Fec\Url\UrlRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan fec:sef:check --option=com_bonuses              rebuild every canonical registry URL of the
 *                                                             component with the plugin port, report what differs
 * php artisan fec:sef:check --option=com_bonuses --view=casino --limit=200 --show=20
 * php artisan fec:sef:check --url="index.php?option=com_bonuses&view=details&type=fs&bid=5742&cid=811"
 *
 * The check of the port of a sh404SEF plugin (App\Fec\Legacy\Sef): the registry
 * holds what the live plugin produced for thousands of queries, so rebuilding
 * them is the exhaustive test of the slugifier and of every branch. Rows are
 * compared as stored (no leading slash, trailing slash kept). Nothing is written.
 */
class SefCheck extends Command
{
    protected $signature = 'fec:sef:check
                            {--option= : Component whose registry URLs are rebuilt (com_bonuses)}
                            {--view= : Only the rows of this view}
                            {--limit=0 : Stop after N rows (0 = all)}
                            {--show=30 : How many differences to print}
                            {--url= : Build one Joomla query and print the path and the stored non-SEF URL}';

    protected $description = 'Rebuild the registry URLs of a component with the ported sh404SEF plugin and report the differences';

    public function handle(): int
    {
        if ($url = (string) $this->option('url')) {
            $built = Sh404sef::build($url, UrlRegistry::parseQuery($url));
            if ($built === null) {
                $this->line('raw (no SEF URL for this query)');
            } else {
                $this->line('path:    /'.$built->path);
                $this->line('non-SEF: '.$built->nonSef);
                $this->line('registry: '.(app(UrlRegistry::class)->pathFor($url, false) ?? '(not stored)'));
            }

            return self::SUCCESS;
        }

        $option = (string) $this->option('option');
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            $this->error('--option=com_x is required');

            return self::INVALID;
        }
        if (! isset(Sh404sef::PLUGINS[$option])) {
            $this->error('No plugin port for '.$option);

            return self::FAILURE;
        }

        $q = DB::connection('fec')->table('sh404sef_urls')
            ->where('rank', 0)
            ->where('newurl', 'like', '%option='.$option.'%')
            ->orderBy('id');
        if ($view = (string) $this->option('view')) {
            $q->where('newurl', 'like', '%view='.$view.'%');
        }
        if ((int) $this->option('limit') > 0) {
            $q->limit((int) $this->option('limit'));
        }

        $show = (int) $this->option('show');
        $total = 0;
        $byView = [];
        $diffs = [];
        $raw = 0;
        foreach ($q->cursor() as $row) {
            $total++;
            $query = UrlRegistry::parseQuery((string) $row->newurl);
            if (($query['option'] ?? '') !== $option) {
                continue;
            }
            $key = $query['view'] ?? ($query['task'] ?? '(none)');
            $byView[$key] ??= ['rows' => 0, 'ok' => 0, 'diff' => 0, 'raw' => 0];
            $byView[$key]['rows']++;

            $built = Sh404sef::build((string) $row->newurl, $query);
            if ($built === null) {
                $byView[$key]['raw']++;
                $raw++;

                continue;
            }
            $stored = ltrim((string) $row->oldurl, '/');
            if ($built->path === $stored) {
                $byView[$key]['ok']++;

                continue;
            }
            $byView[$key]['diff']++;
            if (count($diffs) < $show) {
                $diffs[] = [$row->id, $stored, $built->path, mb_strimwidth((string) $row->newurl, 0, 90, '...')];
            }
        }

        $rows = [];
        $diffTotal = 0;
        foreach ($byView as $k => $c) {
            $rows[] = [$k, $c['rows'], $c['ok'], $c['diff'], $c['raw']];
            $diffTotal += $c['diff'];
        }
        $this->table(['view', 'rows', 'same', 'different', 'raw'], $rows);
        $this->line(sprintf('%d canonical rows of %s: %d rebuilt the same, %d different, %d raw (the plugin leaves them non-SEF)', $total, $option, $total - $diffTotal - $raw, $diffTotal, $raw));
        if ($diffs !== []) {
            $this->table(['id', 'stored', 'rebuilt', 'newurl'], $diffs);
        }

        return $diffTotal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
