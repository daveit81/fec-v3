<?php

namespace App\Console\Commands;

use App\Fec\Url\RootSwitch;
use App\Fec\Url\Router;
use Illuminate\Console\Command;

/**
 * php artisan fec:urls:replay /home/freeextr/fec_replay.log [--top=40] [--max=20000] [--render=100]
 *
 * The root switch checked against real traffic: every GET/HEAD of a slice of
 * the live access log (combined format) that the root block would send to
 * v3 is routed by v3's router, without rendering, and v3's status class is
 * compared with the status the live site answered. Not sent to v3, as with
 * the block: files and directories on disk, paths under a directory with
 * rewrite rules of its own (its .htaccess replaces the root one: /fxclock/,
 * /v3/...), paths Joomla's catch-all never took (RootSwitch::catchAll), what
 * Joomla keeps (RootSwitch::keeps).
 *
 * The views that answer with a redirect when they run (bonus T&C and ad
 * clicks) count as redirects. --render=N renders up to N of the paths that
 * still differ, one fresh process each (fec:parity:render), and compares
 * the status v3 really answers (a page can answer 404 when it runs, as an
 * expired bonus does on both sides). The table lists what still differs,
 * the most requested first; the POSTs that would reach v3 are listed apart
 * (v3's public fallback answers GET and HEAD only).
 *
 * Limits: the query string is ignored (sh404SEF's aliases with a query, the
 * exploit filters); the log has neither the Host nor the Location, so a
 * redirect is a status class, nothing more. fec:parity checks the rest.
 */
class UrlReplay extends Command
{
    /** Views that answer with a redirect when they run. */
    private const RUNTIME_REDIRECTS = ['com_bonuses.tc', 'com_bonuses.ads'];

    protected $signature = 'fec:urls:replay {log : An access log in combined format (a copy readable by this user)}
                            {--top=40 : Rows of the mismatch table}
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

        $n = ['lines' => 0, 'requests' => 0, 'file' => 0, 'subdir' => 0, 'server' => 0, 'joomla' => 0, 'v3' => 0];
        $first = $last = null;
        $paths = [];   // raw path => [hits, [status => n]]
        $posts = [];   // "METHOD path" => [hits, [status => n]]
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
            if ($switch->keeps($path, $query)) {
                $n['joomla']++;

                continue;
            }
            $n['v3']++;
            if ($method === 'GET' || $method === 'HEAD') {
                $paths[$path][0] = ($paths[$path][0] ?? 0) + 1;
                $paths[$path][1][$status] = ($paths[$path][1][$status] ?? 0) + 1;
            } else {
                $key = $method.' '.$path;
                $posts[$key][0] = ($posts[$key][0] ?? 0) + 1;
                $posts[$key][1][$status] = ($posts[$key][1][$status] ?? 0) + 1;
            }
        }
        fclose($fh);

        uasort($paths, fn ($a, $b) => $b[0] <=> $a[0]);
        $routed = array_slice($paths, 0, max(1, (int) $this->option('max')), true);

        $same = [0, 0];
        $diff = [];
        foreach ($routed as $path => [$hits, $statuses]) {
            arsort($statuses);
            $live = (int) array_key_first($statuses);
            $decision = $router->route((string) $path, null, null);
            $key = $decision->url->routeKey();
            $v3 = $decision->action === 'render' && in_array($key, self::RUNTIME_REDIRECTS, true) ? 302 : $decision->status;
            if (RootSwitch::statusClass($live) === RootSwitch::statusClass($v3)) {
                $same[0]++;
                $same[1] += $hits;

                continue;
            }
            $diff[] = [
                'path' => (string) $path,
                'hits' => $hits,
                'live' => $live,
                'statuses' => $statuses,
                'v3' => $v3,
                'action' => $decision->action,
                'detail' => (string) ($decision->location ?? ($key ?? $decision->url->kind)),
            ];
        }

        // the paths that still differ, rendered: the status v3 really answers
        $rendered = 0;
        $budget = max(0, (int) $this->option('render'));
        foreach ($diff as $i => $row) {
            if ($rendered >= $budget || $row['action'] !== 'render' || in_array($row['detail'], self::RUNTIME_REDIRECTS, true)) {
                continue;
            }
            $rendered++;
            $status = $this->renderStatus($row['path']);
            if ($status === null) {
                continue;
            }
            $diff[$i]['v3'] = $status;
            $diff[$i]['action'] = 'rendered';
            if (RootSwitch::statusClass($row['live']) === RootSwitch::statusClass($status)) {
                $same[0]++;
                $same[1] += $row['hits'];
                unset($diff[$i]);
            }
        }
        $diff = array_values($diff);

        $this->info(sprintf('%s line(s) from %s to %s, %s request(s):', number_format($n['lines']), $first ?? '-', $last ?? '-', number_format($n['requests'])));
        $this->line(sprintf(
            '  %s file or directory on disk, %s under a directory with its own rewrite rules, %s never taken by Joomla\'s catch-all, %s kept by Joomla, %s to v3',
            number_format($n['file']), number_format($n['subdir']), number_format($n['server']), number_format($n['joomla']), number_format($n['v3'])
        ));
        $this->line(sprintf('  %s unique GET/HEAD path(s) to v3, %s routed, %s rendered', number_format(count($paths)), number_format(count($routed)), number_format($rendered)));
        $this->info(sprintf('same status class: %s path(s), %s hit(s); different: %s path(s), %s hit(s)',
            number_format($same[0]), number_format($same[1]), number_format(count($diff)), number_format(array_sum(array_column($diff, 'hits')))));

        $pairs = [];
        foreach ($diff as $row) {
            $pair = RootSwitch::statusClass($row['live']).' -> '.RootSwitch::statusClass($row['v3']);
            $pairs[$pair] ??= [0, 0];
            $pairs[$pair][0]++;
            $pairs[$pair][1] += $row['hits'];
        }
        if ($pairs !== []) {
            uasort($pairs, fn ($a, $b) => $b[1] <=> $a[1]);
            $this->table(['live -> v3', 'paths', 'hits'], array_map(fn ($k, $v) => [$k, $v[0], $v[1]], array_keys($pairs), $pairs));
        }
        if ($diff !== []) {
            $this->table(['path', 'hits', 'live', 'v3', 'v3 detail'], array_map(
                fn ($r) => [
                    mb_strimwidth($r['path'], 0, 90, '...'),
                    $r['hits'],
                    self::statuses($r['statuses']),
                    $r['v3'].' '.$r['action'],
                    mb_strimwidth($r['detail'], 0, 60, '...'),
                ],
                array_slice($diff, 0, max(1, (int) $this->option('top')))
            ));
        }
        if ($posts !== []) {
            uasort($posts, fn ($a, $b) => $b[0] <=> $a[0]);
            $top = array_slice($posts, 0, 20, true);
            $this->comment(sprintf('%d other-method path(s) would reach v3 (GET and HEAD only on the public fallback):', count($posts)));
            $this->table(['request', 'hits', 'live'], array_map(
                fn ($k, $v) => [mb_strimwidth($k, 0, 100, '...'), $v[0], self::statuses($v[1])],
                array_keys($top), $top
            ));
        }

        return self::SUCCESS;
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

    /** @param  array<int, int>  $statuses */
    private static function statuses(array $statuses): string
    {
        return implode(' ', array_map(fn ($s, $c) => $s.'x'.$c, array_keys($statuses), $statuses));
    }
}
