<?php

namespace App\Console\Commands;

use App\Fec\Url\UrlRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan fec:urls:stats              canonical URLs per component and view
 * php artisan fec:urls:stats --redirects  the jos_redirection rules: what their targets resolve to
 * php artisan fec:urls:stats --path=/x    resolve one path and print the registry outcome
 */
class UrlStats extends Command
{
    protected $signature = 'fec:urls:stats
                            {--path= : Resolve a single public path instead of printing the statistics}
                            {--redirects : Audit the redirect rules: targets by kind and how many resolve to a live SEF URL}';

    protected $description = 'URL registry (jos_sh404sef_urls + jos_redirection): statistics, redirect audit or a single lookup';

    public function handle(UrlRegistry $registry): int
    {
        if ($path = $this->option('path')) {
            $this->line(json_encode($registry->resolve((string) $path)->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($this->option('redirects')) {
            return $this->redirects($registry);
        }

        $db = DB::connection('fec');
        $total = $db->table('sh404sef_urls')->count();
        $canonical = $db->table('sh404sef_urls')->where('rank', 0)->where('newurl', '<>', '')->count();
        $aliases = $db->table('sh404sef_urls')->where('rank', '>', 0)->count();
        $gone = $db->table('sh404sef_urls')->where('newurl', '')->count();
        $redirects = $db->table('redirection')->count();

        $this->info(sprintf('sh404sef_urls: %d rows = %d canonical + %d aliases + %d recorded 404s; redirection rules: %d', $total, $canonical, $aliases, $gone, $redirects));
        $this->table(['option', 'view / task', 'canonical URLs'], array_map(fn ($r) => [$r->option, $r->view, number_format($r->urls)], $registry->stats()));

        return self::SUCCESS;
    }

    /**
     * Every rule whose target is a Joomla query is resolved the way the router
     * does it (exact newurl, then tolerant); the rest are grouped by what the
     * legacy would have done with them. Dead components (no SEF URL at all)
     * are listed by option so the desk can decide whether to drop the rules.
     */
    private function redirects(UrlRegistry $registry): int
    {
        $rows = DB::connection('fec')->table('redirection')->orderBy('id')->get(['id', 'oldurl', 'newurl']);

        $kinds = ['empty' => 0, 'absolute' => 0, 'path' => 0, 'sef exact' => 0, 'sef tolerant' => 0, 'raw (no SEF URL)' => 0];
        $raw = [];
        $samples = [];

        foreach ($rows as $r) {
            $target = trim((string) $r->newurl);
            if ($target === '') {
                $kinds['empty']++;

                continue;
            }
            if (preg_match('#^https?://#i', $target)) {
                $kinds['absolute']++;

                continue;
            }
            if (! str_starts_with($target, 'index.php')) {
                $kinds['path']++;

                continue;
            }

            if ($registry->pathFor($target, false) !== null) {
                $kinds['sef exact']++;
            } elseif (($sef = $registry->pathFor($target, true)) !== null) {
                $kinds['sef tolerant']++;
                if (count($samples) < 5) {
                    $samples[] = ['/'.ltrim((string) $r->oldurl, '/'), $target, $sef];
                }
            } else {
                $kinds['raw (no SEF URL)']++;
                $q = UrlRegistry::parseQuery($target);
                $key = ($q['option'] ?? '(none)').' / '.($q['view'] ?? ($q['task'] ?? ($q['func'] ?? '-')));
                $raw[$key] = ($raw[$key] ?? 0) + 1;
            }
        }

        $this->info(sprintf('jos_redirection: %d rules', $rows->count()));
        $this->table(['target kind', 'rules'], array_map(fn ($k, $n) => [$k, number_format($n)], array_keys($kinds), $kinds));

        if ($samples !== []) {
            $this->line('Resolved only by the tolerant lookup (first 5):');
            $this->table(['rule path', 'target query', 'SEF URL found'], $samples);
        }

        if ($raw !== []) {
            arsort($raw);
            $this->line('Targets with no SEF URL in the registry (the legacy 301s to a URL that then 404s), by option / view:');
            $this->table(['option / view', 'rules'], array_map(fn ($k, $n) => [$k, number_format($n)], array_keys($raw), $raw));
        }

        return self::SUCCESS;
    }
}
