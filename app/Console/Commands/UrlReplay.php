<?php

namespace App\Console\Commands;

use App\Fec\Url\RootSwitch;
use App\Fec\Url\Router;
use Illuminate\Console\Command;

/**
 * php artisan fec:urls:replay /home/freeextr/fec_replay.log [--top=40] [--max=20000]
 *
 * The root switch checked against real traffic: every GET/HEAD of a slice of
 * the live access log (combined format) that the root block would send to
 * v3 (not a file or a directory on disk, not kept by Joomla: RootSwitch) is
 * routed by v3's router, without rendering, and v3's status class is
 * compared with the status the live site answered. The table lists the
 * paths whose class differs, the most requested first; the POSTs that would
 * reach v3 are listed apart (v3's public fallback answers GET and HEAD only).
 *
 * Limits: a "render" is counted as 200 (a page can still answer 404 when it
 * runs, as /disclaimer does on both sides); the query string is ignored
 * (sh404SEF's aliases with a query, the exploit filters); the Location of a
 * redirect is not in the log, only its status. fec:parity checks the rest.
 */
class UrlReplay extends Command
{
    protected $signature = 'fec:urls:replay {log : An access log in combined format (a copy readable by this user)}
                            {--top=40 : Rows of the mismatch table}
                            {--max=20000 : Unique paths routed at most, the most requested first}';

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

        $n = ['lines' => 0, 'requests' => 0, 'file' => 0, 'joomla' => 0, 'v3' => 0];
        $paths = [];   // raw path => [hits, [status => n]]
        $posts = [];   // raw path => [hits, [status => n]]
        $isFile = [];  // memo
        while (($line = fgets($fh)) !== false) {
            $n['lines']++;
            if (! preg_match('/"([A-Z]+) (\S+) [^"]*" (\d{3}) /', $line, $m)) {
                continue;
            }
            $n['requests']++;
            [$method, $uri, $status] = [$m[1], $m[2], (int) $m[3]];
            $path = parse_url('http://x'.(str_starts_with($uri, '/') ? $uri : '/'.$uri), PHP_URL_PATH);
            $query = (string) (parse_url('http://x'.(str_starts_with($uri, '/') ? $uri : '/'.$uri), PHP_URL_QUERY) ?? '');
            if (! is_string($path) || $path === '') {
                continue;
            }
            if ($path !== '/') {
                $decoded = rawurldecode($path);
                $isFile[$decoded] ??= (str_contains($decoded, "\0") ? false : (@is_file($docroot.$decoded) || @is_dir($docroot.$decoded)));
                if ($isFile[$decoded]) {
                    $n['file']++;

                    continue;
                }
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
        $max = max(1, (int) $this->option('max'));
        $routed = array_slice($paths, 0, $max, true);

        $same = 0;
        $sameHits = 0;
        $diff = [];
        $pairs = [];
        foreach ($routed as $path => [$hits, $statuses]) {
            arsort($statuses);
            $live = (int) array_key_first($statuses);
            $decision = $router->route((string) $path, null, null);
            $v3 = $decision->status;
            if (RootSwitch::statusClass($live) === RootSwitch::statusClass($v3)) {
                $same++;
                $sameHits += $hits;

                continue;
            }
            $pair = RootSwitch::statusClass($live).' -> '.RootSwitch::statusClass($v3);
            $pairs[$pair] = ($pairs[$pair] ?? [0, 0]);
            $pairs[$pair][0]++;
            $pairs[$pair][1] += $hits;
            $detail = $decision->location ?? ($decision->url->routeKey() ?? $decision->url->kind);
            $diff[] = [
                (string) $path,
                $hits,
                implode(' ', array_map(fn ($s, $c) => $s.'x'.$c, array_keys($statuses), $statuses)),
                $v3.' '.$decision->action,
                (string) $detail,
            ];
        }

        $this->info(sprintf(
            '%s line(s), %s request(s): %s files or directories on disk, %s kept by Joomla, %s to v3 (%s unique GET/HEAD path(s), %s routed)',
            number_format($n['lines']), number_format($n['requests']), number_format($n['file']),
            number_format($n['joomla']), number_format($n['v3']), number_format(count($paths)), number_format(count($routed))
        ));
        $this->info(sprintf('same status class: %s path(s), %s hit(s); different: %s path(s), %s hit(s)',
            number_format($same), number_format($sameHits), number_format(count($diff)), number_format(array_sum(array_column($diff, 1)))));
        if ($pairs !== []) {
            uasort($pairs, fn ($a, $b) => $b[1] <=> $a[1]);
            $this->table(['live -> v3', 'paths', 'hits'], array_map(fn ($k, $v) => [$k, $v[0], $v[1]], array_keys($pairs), $pairs));
        }
        if ($diff !== []) {
            $top = max(1, (int) $this->option('top'));
            $this->table(['path', 'hits', 'live', 'v3', 'v3 detail'], array_map(
                fn ($r) => [mb_strimwidth($r[0], 0, 90, '...'), $r[1], $r[2], $r[3], mb_strimwidth($r[4], 0, 70, '...')],
                array_slice($diff, 0, $top)
            ));
        }
        if ($posts !== []) {
            uasort($posts, fn ($a, $b) => $b[0] <=> $a[0]);
            $this->comment(sprintf('%d other-method path(s) would reach v3 (GET and HEAD only on the public fallback):', count($posts)));
            $this->table(['request', 'hits', 'live'], array_map(
                fn ($k, $v) => [mb_strimwidth($k, 0, 100, '...'), $v[0], implode(' ', array_map(fn ($s, $c) => $s.'x'.$c, array_keys($v[1]), $v[1]))],
                array_keys(array_slice($posts, 0, 20, true)), array_slice($posts, 0, 20, true)
            ));
        }

        return self::SUCCESS;
    }
}
