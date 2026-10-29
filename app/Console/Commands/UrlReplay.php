<?php

namespace App\Console\Commands;

use App\Fec\Url\Decision;
use App\Fec\Url\RootSwitch;
use App\Fec\Url\Router;
use Illuminate\Console\Command;

/**
 * php artisan fec:urls:replay /home/freeextr/fec_replay.log [--top=60] [--max=20000] [--render=1000]
 *
 * The root switch checked against real traffic: every GET/HEAD of a slice of
 * the live access log (combined format) that the root block would send to
 * v3 is routed by v3's router, without rendering, and v3's status class is
 * compared with the statuses the live site answered for that path. Not sent
 * to v3, as with the block: other methods, files and directories on disk,
 * paths under a directory with rewrite rules of its own (its .htaccess
 * replaces the root one: /fxclock/, /v3/...), paths Joomla's catch-all never
 * took (RootSwitch::catchAll), what Joomla keeps (RootSwitch::keeps).
 *
 * A path agrees when v3's class is one the live site answered for it: the
 * log has no Host, and the bare domain answers 301 to www (sh404SEF, now
 * WwwRedirect) before the same path answers 200 or 404 on www. A path that
 * does not agree is routed again with a query string the live site saw
 * with its main status (the exploit filters, the aliases with a query), and
 * --render=N renders up to N of those still apart, one process each
 * (fec:parity:render), for the status v3 really answers (an expired bonus
 * is a 404 when it runs, on both sides). The views that answer a redirect
 * when they run (bonus T&C, ad clicks) count as redirects and are never
 * rendered (an ad click is logged).
 *
 * The differences answered by an .htaccess rule of v3 (the live file's
 * rules, the 410s decided for the root switch) are listed apart from the
 * rest, which is sorted by weight: the live site's 200s first.
 */
class UrlReplay extends Command
{
    /** Views that answer with a redirect when they run. */
    private const RUNTIME_REDIRECTS = ['com_bonuses.tc', 'com_bonuses.ads'];

    protected $signature = 'fec:urls:replay {log : An access log in combined format (a copy readable by this user)}
                            {--top=60 : Rows of the table of differences}
                            {--max=20000 : Unique paths routed at most, the most requested first}
                            {--render=0 : Render up to N of the paths that differ (one process each) and compare the real status}';

    protected $description = 'Replay a slice of the live access log against the root switch: where v3 would answer differently';

    public function handle(Router $router): int
    {
        $file = (string) $this->argument('log');
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            $this->error('Cannot read '.$file);

            return self::FAILURE;
        }

        $switch = RootSwitch::fromRegistry();
        $docroot = rtrim((string) config('fec.legacy.docroot'), '/');

        $n = ['lines' => 0, 'requests' => 0, 'method' => 0, 'file' => 0, 'subdir' => 0, 'server' => 0, 'joomla' => 0, 'v3' => 0];
        $first = $last = null;
        $paths = [];   // raw path => [hits, [status => n], [status => a query string seen with it]]
        $onDisk = [];  // memo: decoded path => file or directory
        $subdir = [];  // memo: directory => has rewrite rules of its own
        while (($line = fgets($fh)) !== false) {
            $n['lines']++;
            if (! preg_match('/\[([^\]]+)\] "([A-Z]+) (\S+) [^"]*" (\d{3}) /', $line, $m)) {
                continue;
            }
            $n['requests']++;
            $first ??= $m[1];
            $last = $m[1];
            [$method, $uri, $status] = [$m[2], $m[3], (int) $m[4]];
            if ($method !== 'GET' && $method !== 'HEAD') {
                $n['method']++;

                continue;
            }
            $uri = str_starts_with($uri, '/') ? $uri : '/'.$uri;
            $path = parse_url('http://x'.$uri, PHP_URL_PATH);
            $query = (string) (parse_url('http://x'.$uri, PHP_URL_QUERY) ?? '');
            if (! is_string($path) || $path === '') {
                continue;
            }
            $decoded = rawurldecode($path);
            if ($decoded !== '/' && ! str_contains($decoded, "\0")) {
                $onDisk[$decoded] ??= @is_file($docroot.$decoded) || @is_dir($docroot.$decoded);
                if ($onDisk[$decoded]) {
                    $n['file']++;

                    continue;
                }
                if (self::underRewritingDirectory($docroot, $decoded, $subdir)) {
                    $n['subdir']++;

                    continue;
                }
            }
            if (! RootSwitch::catchAll($decoded)) {
                $n['server']++;

                continue;
            }
            if ($switch->keeps($path, $query, $method)) {
                $n['joomla']++;

                continue;
            }
            $n['v3']++;
            $paths[$path][0] = ($paths[$path][0] ?? 0) + 1;
            $paths[$path][1][$status] = ($paths[$path][1][$status] ?? 0) + 1;
            if ($query !== '' && ! isset($paths[$path][2][$status])) {
                $paths[$path][2][$status] = $query;
            }
        }
        fclose($fh);

        uasort($paths, fn ($a, $b) => $b[0] <=> $a[0]);
        $routed = array_slice($paths, 0, max(1, (int) $this->option('max')), true);

        $same = [0, 0];
        $diff = [];
        foreach ($routed as $path => $row) {
            [$hits, $statuses] = $row;
            $queries = $row[2] ?? [];
            arsort($statuses);
            $live = (int) array_key_first($statuses);
            $classes = array_unique(array_map(fn ($s) => RootSwitch::statusClass((int) $s), array_keys($statuses)));

            $decision = $router->route((string) $path, null, null);
            $v3 = $this->v3Status($decision);
            if (! in_array(RootSwitch::statusClass($v3), $classes, true) && isset($queries[$live])) {
                // the query string the live site saw with its main answer
                $decision = $router->route((string) $path, $queries[$live], null);
                $v3 = $this->v3Status($decision);
            }
            if (in_array(RootSwitch::statusClass($v3), $classes, true)) {
                $same[0]++;
                $same[1] += $hits;

                continue;
            }
            $key = $decision->url->routeKey();
            $diff[] = [
                'path' => (string) $path,
                'hits' => $hits,
                'live' => $live,
                'classes' => $classes,
                'statuses' => $statuses,
                'v3' => $v3,
                'action' => $decision->action,
                'rule' => $decision->url->source === 'htaccess',
                'key' => $key,
                'detail' => (string) ($decision->location ?? ($key ?? $decision->url->kind)),
            ];
        }

        // the paths still apart, rendered: the status v3 really answers
        $rendered = 0;
        $budget = max(0, (int) $this->option('render'));
        foreach ($diff as $i => $row) {
            if ($rendered >= $budget || $row['action'] !== 'render' || in_array($row['key'], self::RUNTIME_REDIRECTS, true)) {
                continue;
            }
            $rendered++;
            $status = $this->renderStatus($row['path']);
            if ($status === null) {
                continue;
            }
            $diff[$i]['v3'] = $status;
            $diff[$i]['action'] = 'rendered';
            if (in_array(RootSwitch::statusClass($status), $row['classes'], true)) {
                $same[0]++;
                $same[1] += $row['hits'];
                unset($diff[$i]);
            }
        }

        $rules = array_values(array_filter($diff, fn ($r) => $r['rule']));
        $rest = array_values(array_filter($diff, fn ($r) => ! $r['rule']));
        // the live site's pages first, then its redirects, then the rest; the most requested first
        $weight = fn (array $r): int => match (RootSwitch::statusClass($r['live'])) {
            '200' => 0,
            '30x' => 1,
            default => 2,
        };
        usort($rest, fn ($a, $b) => [$weight($a), -$a['hits']] <=> [$weight($b), -$b['hits']]);

        $this->info(sprintf('%s line(s) from %s to %s, %s request(s):', number_format($n['lines']), $first ?? '-', $last ?? '-', number_format($n['requests'])));
        $this->line(sprintf(
            '  %s not GET/HEAD (Joomla), %s file or directory on disk, %s under a directory with its own rewrite rules, %s never taken by Joomla\'s catch-all, %s kept by Joomla, %s to v3',
            number_format($n['method']), number_format($n['file']), number_format($n['subdir']), number_format($n['server']), number_format($n['joomla']), number_format($n['v3'])
        ));
        $this->line(sprintf('  %s unique path(s) to v3, %s routed, %s rendered', number_format(count($paths)), number_format(count($routed)), number_format($rendered)));
        $this->info(sprintf('agree: %s path(s), %s hit(s); answered by a rule of v3: %s path(s), %s hit(s); differ: %s path(s), %s hit(s)',
            number_format($same[0]), number_format($same[1]),
            number_format(count($rules)), number_format(array_sum(array_column($rules, 'hits'))),
            number_format(count($rest)), number_format(array_sum(array_column($rest, 'hits')))));

        $top = max(1, (int) $this->option('top'));
        if ($rest !== []) {
            $pairs = [];
            foreach ($rest as $row) {
                $pair = RootSwitch::statusClass($row['live']).' -> '.RootSwitch::statusClass($row['v3']);
                $pairs[$pair] ??= [0, 0];
                $pairs[$pair][0]++;
                $pairs[$pair][1] += $row['hits'];
            }
            uasort($pairs, fn ($a, $b) => $b[1] <=> $a[1]);
            $this->table(['live -> v3', 'paths', 'hits'], array_map(fn ($k, $v) => [$k, $v[0], $v[1]], array_keys($pairs), $pairs));
            $this->table(['path', 'hits', 'live', 'v3', 'v3 detail'], array_map(fn ($r) => self::row($r), array_slice($rest, 0, $top)));
        }
        if ($rules !== []) {
            $this->comment('Answered by a rule of v3 (the live .htaccess, the 410s decided for the root switch):');
            usort($rules, fn ($a, $b) => $b['hits'] <=> $a['hits']);
            $this->table(['path', 'hits', 'live', 'v3', 'v3 detail'], array_map(fn ($r) => self::row($r), array_slice($rules, 0, 20)));
        }

        return self::SUCCESS;
    }

    /** v3's status from the routing alone: the views that redirect when they run count as redirects. */
    private function v3Status(Decision $decision): int
    {
        return $decision->action === 'render' && in_array($decision->url->routeKey(), self::RUNTIME_REDIRECTS, true) ? 302 : $decision->status;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<string|int>
     */
    private static function row(array $r): array
    {
        return [
            mb_strimwidth((string) $r['path'], 0, 90, '...'),
            (int) $r['hits'],
            implode(' ', array_map(fn ($s, $c) => $s.'x'.$c, array_keys($r['statuses']), $r['statuses'])),
            $r['v3'].' '.$r['action'],
            mb_strimwidth((string) $r['detail'], 0, 60, '...'),
        ];
    }

    /**
     * Is the path under a directory (below the docroot) whose .htaccess has
     * rewrite rules? Then that file's rules apply, not the root one's.
     *
     * @param  array<string, bool>  $memo
     */
    private static function underRewritingDirectory(string $docroot, string $path, array &$memo): bool
    {
        $dir = dirname($path);
        while ($dir !== '/' && $dir !== '.' && $dir !== '') {
            if (! array_key_exists($dir, $memo)) {
                $htaccess = $docroot.$dir.'/.htaccess';
                $memo[$dir] = @is_file($htaccess) && preg_match('/^\s*Rewrite(Engine|Rule)\b/mi', (string) @file_get_contents($htaccess)) === 1;
            }
            if ($memo[$dir]) {
                return true;
            }
            $dir = dirname($dir);
        }

        return false;
    }

    /** The status v3 answers for a path, rendered in a fresh process (fec:parity:render). */
    private function renderStatus(string $path): ?int
    {
        $process = @proc_open([PHP_BINARY, base_path('artisan'), 'fec:parity:render', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) {
            return null;
        }
        $out = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return preg_match('/^STATUS:(\d{3})/', $out, $m) ? (int) $m[1] : null;
    }
}
