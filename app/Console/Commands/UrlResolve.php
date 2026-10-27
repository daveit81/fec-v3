<?php

namespace App\Console\Commands;

use App\Fec\Url\Dispatcher;
use App\Fec\Url\Router;
use Illuminate\Console\Command;

/**
 * php artisan fec:urls:resolve /online-casinos/king-billy-casino /bonuses/king-billy-casino/200-free-spins-id9800
 *     one line per path: what v3 would answer (status, action, handler or redirect target)
 * php artisan fec:urls:resolve --json /some/path
 *     the full decision, for diffing against a legacy curl
 */
class UrlResolve extends Command
{
    protected $signature = 'fec:urls:resolve {path* : Public paths, with or without leading slash}
                            {--json : Print the full decision as JSON instead of the table}';

    protected $description = 'Ask the v3 router what it would do with one or more public paths';

    public function handle(Router $router): int
    {
        $paths = (array) $this->argument('path');

        if ($this->option('json')) {
            $out = [];
            foreach ($paths as $path) {
                $out['/'.ltrim($path, '/')] = $router->route($path, parse_url($path, PHP_URL_QUERY) ?: null)->toArray();
            }
            $this->line(json_encode(count($out) === 1 ? reset($out) : $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($paths as $path) {
            $d = $router->route($path, parse_url($path, PHP_URL_QUERY) ?: null);
            $u = $d->url;
            $ids = array_filter([
                'id' => $u->id(),
                'bid' => $u->int('bid'),
                'cid' => $u->int('cid'),
                'catid' => $u->int('catid'),
            ], fn ($v) => $v !== null);

            $rows[] = [
                '/'.ltrim($path, '/'),
                $d->status,
                $d->action,
                $u->kind.' ('.$u->source.')',
                $u->routeKey() ?? '',
                $ids ? implode(' ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($ids), $ids)) : '',
                $d->location ?? ($d->handler ? class_basename($d->handler) : ''),
                isset($u->meta['casino_match']) ? ($u->meta['casino_match'] ? 'casino ok' : 'casino MISMATCH') : '',
            ];
        }

        $this->table(['path', 'status', 'action', 'kind (source)', 'option.view', 'ids', 'location / handler', 'notes'], $rows);
        $this->line('Patterns: '.implode(', ', $router->patterns()).'. Ported views: '.(count(app(Dispatcher::class)->portedKeys()) ?: 'none yet').'.');

        return self::SUCCESS;
    }
}
