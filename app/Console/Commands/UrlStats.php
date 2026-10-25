<?php

namespace App\Console\Commands;

use App\Fec\Url\UrlRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan fec:urls:stats           canonical URLs per component and view
 * php artisan fec:urls:stats --path=/x resolve one path and print the outcome
 */
class UrlStats extends Command
{
    protected $signature = 'fec:urls:stats {--path= : Resolve a single public path instead of printing the statistics}';

    protected $description = 'URL registry (jos_sh404sef_urls + jos_redirection): statistics or a single lookup';

    public function handle(UrlRegistry $registry): int
    {
        if ($path = $this->option('path')) {
            $this->line(json_encode($registry->resolve((string) $path)->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
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
}
