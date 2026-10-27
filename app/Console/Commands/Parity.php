<?php

namespace App\Console\Commands;

use App\Fec\Url\UrlRegistry;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

/**
 * php artisan fec:parity /authors [/authors/davey ...]
 *     Fetches each path from the live site (origin, --resolve, no TLS check,
 *     as every probe does) and renders it in-process with v3, then compares
 *     status, title, description, robots, canonical, stylesheets, every
 *     JSON-LD block (decoded) and the normalised <main> markup. Red at the
 *     first difference, with a readable diff. --save keeps both normalised
 *     documents under storage/app/parity/ for a side-by-side look.
 *
 * Every switch of a section is preceded by this being green on all of its
 * URLs; it is the "script di parita" of the roadmap, generalised.
 */
class Parity extends Command
{
    protected $signature = 'fec:parity {path?* : Public paths to compare}
                            {--option= : Compare every canonical registry URL of a component (com_authors)}
                            {--view= : With --option, only the URLs of this view (details, casino, show...)}
                            {--where= : With --option, only the URLs whose Joomla query contains this text (type=nd)}
                            {--sample=0 : With --option, N registry URLs drawn at random instead of the first N}
                            {--seed=0 : With --sample, the random seed (0 = a new draw every run; print it to repeat a run)}
                            {--limit=0 : With --option, stop after N paths (0 = all)}
                            {--headers-only : Compare status and Location only (tc/ads redirects), never the page}
                            {--save : Write the normalised documents to storage/app/parity/}
                            {--in-process : Render v3 in this process instead of one process per path (debugging)}
                            {--context=3 : Diff context lines}
                            {--main : Compare only the <main> element (default: whole <body>)}
                            {--bust : Add a unique query variable to both requests, past Joomla\'s page cache (the live page may be stale)}';

    protected $description = 'Compare the live Joomla page and the v3 rendering of the same path';

    public function handle(): int
    {
        $paths = (array) $this->argument('path');
        if ($option = (string) $this->option('option')) {
            $paths = array_merge($paths, $this->registryPaths($option, (int) $this->option('limit')));
            $this->info(count($paths).' path(s) for '.$option.($this->option('view') ? ' view='.$this->option('view') : '').($this->option('where') ? ' where '.$this->option('where') : ''));
        }
        if ($paths === []) {
            $this->error('Give at least one path or --option=com_x');

            return self::INVALID;
        }

        $failed = 0;
        foreach ($paths as $path) {
            if ($this->option('bust')) {
                $path .= (str_contains($path, '?') ? '&' : '?').'fecparity='.substr(md5((string) microtime(true)), 0, 8);
            }
            $path = '/'.ltrim((string) $path, '/');
            $this->line('');
            $this->info("== $path");
            try {
                $live = $this->fetchLive($path);
            } catch (Throwable $e) {
                $this->error('live fetch failed: '.$e->getMessage());
                $failed++;
                $failing[] = $path;

                continue;
            }
            try {
                $v3 = $this->renderV3($path);
            } catch (Throwable $e) {
                $this->error('v3 render failed: '.$e->getMessage());
                $failed++;
                $failing[] = $path;

                continue;
            }

            $ok = $this->compare($path, $live, $v3);
            if (! $ok) {
                $failed++;
                $failing[] = $path;
            }
        }
        $this->line('');
        $this->line($failed === 0 ? '<info>PARITY: all green</info>' : "<error>PARITY: $failed path(s) differ</error>");
        foreach ($failing ?? [] as $path) {
            $this->line('  '.$path);
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Canonical SEF paths of a component as the registry holds them, oldest first;
     * narrowed by view and by a text of the query (--where=type=nd), drawn at random
     * with --sample (the seed is printed so that a run can be repeated).
     *
     * @return list<string>
     */
    private function registryPaths(string $option, int $limit): array
    {
        $desk = (array) config('fec.legacy.desk_views', []);
        $view = (string) $this->option('view');
        $where = (string) $this->option('where');
        $q = DB::connection('fec')->table('sh404sef_urls')
            ->where('rank', 0)
            ->where('newurl', 'like', '%option='.$option.'%')
            ->orderBy('id');
        if ($view !== '') {
            $q->where('newurl', 'like', '%view='.$view.'%');
        }
        if ($where !== '') {
            $q->where('newurl', 'like', '%'.$where.'%');
        }
        $rows = $q->get(['oldurl', 'newurl'])
            ->reject(function ($r) use ($desk, $view) {
                $q = UrlRegistry::parseQuery((string) $r->newurl);

                return in_array(($q['option'] ?? '').'.'.($q['view'] ?? ''), $desk, true)
                    || ($view !== '' && ($q['view'] ?? '') !== $view);
            });

        $sample = (int) $this->option('sample');
        if ($sample > 0) {
            $seed = (int) $this->option('seed') ?: random_int(1, 999999);
            mt_srand($seed);
            $all = $rows->values()->all();
            $picked = [];
            while ($all !== [] && count($picked) < $sample) {
                $i = mt_rand(0, count($all) - 1);
                $picked[] = $all[$i];
                array_splice($all, $i, 1);
            }
            $rows = collect($picked);
            $this->comment("sample of $sample (--seed=$seed to repeat)");
        }

        $paths = $rows->when($limit > 0, fn ($c) => $c->take($limit))->pluck('oldurl');

        return array_values(array_unique(array_map(fn ($u) => '/'.ltrim((string) $u, '/'), $paths->all())));
    }

    /** @return array{status: int, html: string} */
    private function fetchLive(string $path): array
    {
        $host = (string) parse_url((string) config('fec.site_url'), PHP_URL_HOST);
        $origin = (string) config('fec.parity.origin_ip', '');
        $options = ['verify' => false, 'allow_redirects' => false, 'timeout' => 30];
        if ($origin !== '') {
            $options['curl'] = [CURLOPT_RESOLVE => [$host.':443:'.$origin]];
        }
        $response = Http::withOptions($options)
            ->withHeaders(['User-Agent' => 'FEC-parity/1.0', 'Accept' => 'text/html'])
            ->get(rtrim((string) config('fec.site_url'), '/').$path);

        return ['status' => $response->status(), 'html' => $response->body(), 'location' => (string) $response->header('Location')];
    }

    /**
     * One fresh PHP process per path (fec:parity:render): legacy code keeps
     * per-request state in statics that a web request never carries over.
     *
     * @return array{status: int, html: string}
     */
    private function renderV3(string $path): array
    {
        if ($this->option('in-process')) {
            return $this->renderInProcess($path);
        }
        $process = proc_open([PHP_BINARY, base_path('artisan'), 'fec:parity:render', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) {
            throw new \RuntimeException('cannot start the render process');
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if (! preg_match('/^STATUS:(\d+)\n(?:LOCATION:([^\n]*)\n)?/', $out, $m)) {
            throw new \RuntimeException('render process failed (exit '.$code.'): '.mb_strimwidth(trim($err.$out), 0, 400, '...'));
        }

        return ['status' => (int) $m[1], 'html' => substr($out, strlen($m[0])), 'location' => $m[2] ?? ''];
    }

    /** @return array{status: int, html: string} */
    private function renderInProcess(string $path): array
    {
        $host = (string) parse_url((string) config('fec.site_url'), PHP_URL_HOST);
        $request = Request::create('https://'.$host.$path, 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'FEC-parity/1.0',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_CF_IPCOUNTRY' => '',
        ]);
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $html = (string) $response->getContent();
        $kernel->terminate($request, $response);

        return ['status' => $response->getStatusCode(), 'html' => $html, 'location' => (string) $response->headers->get('Location', '')];
    }

    /** @param  array{status: int, html: string, location?: string}  $live @param  array{status: int, html: string, location?: string}  $v3 */
    private function compare(string $path, array $live, array $v3): bool
    {
        $ok = true;
        $ok = $this->check('status', (string) $live['status'], (string) $v3['status']) && $ok;

        // A redirect is compared by its target (path and query: the host differs by design
        // between the live site and v3.); its body is not a page.
        if (($live['status'] >= 300 && $live['status'] < 400) || ($v3['status'] >= 300 && $v3['status'] < 400)) {
            $strip = function (string $url): string {
                $parts = parse_url($url);
                if ($parts === false || ! isset($parts['path'])) {
                    return $url;
                }

                return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
            };

            return $this->check('location', $strip((string) ($live['location'] ?? '')), $strip((string) ($v3['location'] ?? ''))) && $ok;
        }

        // The web server's own error pages (410 of a [G] rule, 403 of an [F] rule) are not
        // pages of the site: the status is the parity.
        if ($this->option('headers-only') || ! in_array((int) $live['status'], [200, 404], true)) {
            return $ok;
        }

        $a = $this->parse($live['html']);
        $b = $this->parse($v3['html']);

        foreach (['title', 'description', 'robots', 'canonical', 'stylesheets', 'scripts'] as $field) {
            $ok = $this->check($field, $a[$field], $b[$field]) && $ok;
        }

        $ok = $this->check('json-ld blocks', (string) count($a['jsonld']), (string) count($b['jsonld'])) && $ok;
        foreach ($a['jsonld'] as $i => $block) {
            $ok = $this->check('json-ld #'.($i + 1), $block, $b['jsonld'][$i] ?? '(missing)') && $ok;
        }

        $ok = $this->check($this->option('main') ? 'main' : 'body', $a['body'], $b['body']) && $ok;

        if ($this->option('save')) {
            $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $path) ?? 'root', '-') ?: 'root';
            @mkdir(storage_path('app/parity'), 0755, true);
            file_put_contents(storage_path("app/parity/$slug.live.html"), $a['body']);
            file_put_contents(storage_path("app/parity/$slug.v3.html"), $b['body']);
            $this->comment("saved storage/app/parity/$slug.{live,v3}.html");
        }

        return $ok;
    }

    private function check(string $what, string $live, string $v3): bool
    {
        if ($live === $v3) {
            $this->line(sprintf('  <info>ok</info>   %-14s %s', $what, mb_strimwidth(preg_replace('/\s+/', ' ', $live) ?? '', 0, 90, '...')));

            return true;
        }
        $this->line(sprintf('  <error>DIFF</error> %s', $what));
        foreach ($this->diff($live, $v3, (int) $this->option('context')) as $line) {
            $this->line('       '.$line);
        }

        return false;
    }

    /**
     * @return array{title: string, description: string, robots: string, canonical: string, stylesheets: string, scripts: string, jsonld: list<string>, body: string}
     */
    private function parse(string $html): array
    {
        $site = rtrim((string) config('fec.site_url'), '/');
        $assets = rtrim((string) config('fec.asset_base'), '/');

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $x = new DOMXPath($dom);

        $text = fn (string $q) => trim((string) ($x->query($q)->item(0)?->textContent ?? ''));
        $attr = fn (string $q, string $a) => trim((string) ($x->query($q)->item(0)?->getAttribute($a) ?? ''));
        $list = function (string $q, string $a) use ($x, $site, $assets) {
            $out = [];
            foreach ($x->query($q) as $node) {
                $v = (string) $node->getAttribute($a);
                $v = str_replace([$assets, $site], '', $v);
                $out[] = preg_replace('/\?v=\d+/', '?v=N', $v);
            }

            return implode("\n", $out);
        };

        $jsonld = [];
        foreach ($x->query('//script[@type="application/ld+json"]') as $node) {
            $decoded = json_decode(trim((string) $node->textContent), true);
            $jsonld[] = $decoded === null
                ? '(invalid json) '.mb_strimwidth(trim((string) $node->textContent), 0, 200, '...')
                : (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $root = $this->option('main') ? $x->query('//main')->item(0) : $x->query('//body')->item(0);
        $body = $root ? (string) $dom->saveHTML($root) : '';

        return [
            'title' => $text('//title'),
            'description' => $attr('//meta[@name="description"]', 'content'),
            'robots' => $attr('//meta[@name="robots"]', 'content'),
            'canonical' => $attr('//link[@rel="canonical"]', 'href'),
            'stylesheets' => $list('//link[@rel="stylesheet"]', 'href'),
            'scripts' => $list('//head//script[@src]', 'src'),
            'jsonld' => $jsonld,
            'body' => $this->normalize($body, $site, $assets),
        ];
    }

    private function normalize(string $html, string $site, string $assets): string
    {
        $html = str_replace([$assets, $site], '', $html);
        // CSRF tokens, signed newsletter forms (fts/fsg), cache busters, nonces: never equal between two requests.
        $html = preg_replace('/name="[a-f0-9]{32,}" value="1"/', 'name="CSRF" value="1"', $html) ?? $html;
        $html = preg_replace('/name="fts" value="\d+"/', 'name="fts" value="FTS"', $html) ?? $html;
        $html = preg_replace('/name="fsg" value="[a-f0-9]{64}"/', 'name="fsg" value="FSG"', $html) ?? $html;
        $html = preg_replace('/\?v=\d+/', '?v=N', $html) ?? $html;
        // live counters that move between the two fetches (the trending tag "#1 . 476 opens")
        $html = preg_replace('/\b\d[\d.,]*\s+opens\b/', 'N opens', $html) ?? $html;
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;
        // one tag per line so diffs stay readable
        $html = str_replace('><', ">\n<", $html);

        return trim($html);
    }

    /** @return list<string> unified diff from the system diff when available, else a prefix/suffix sketch */
    private function diff(string $a, string $b, int $context): array
    {
        $unified = $this->unifiedDiff($a, $b, $context);
        if ($unified !== null) {
            return $unified;
        }

        return $this->sketchDiff($a, $b, $context);
    }

    /** @return list<string>|null */
    private function unifiedDiff(string $a, string $b, int $context): ?array
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        $dir = storage_path('app/parity');
        @mkdir($dir, 0755, true);
        $fa = tempnam($dir, 'live-');
        $fb = tempnam($dir, 'v3-');
        file_put_contents($fa, $a."\n");
        file_put_contents($fb, $b."\n");
        $out = [];
        try {
            $process = proc_open(['diff', '-U', (string) max(0, $context), '--label', 'live', '--label', 'v3', $fa, $fb], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($process)) {
                return null;
            }
            $text = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            if ($code > 1 || $text === '') {
                return null;
            }
            $lines = explode("\n", rtrim($text, "\n"));
            $shown = 0;
            foreach ($lines as $line) {
                if ($shown++ >= 400) {
                    $out[] = sprintf('  ... %d more diff lines (see --save files)', count($lines) - 400);
                    break;
                }
                $esc = $this->clip($line);
                $out[] = match (substr($line, 0, 1)) {
                    '-' => '<fg=red>'.$esc.'</>',
                    '+' => '<fg=green>'.$esc.'</>',
                    '@' => '<fg=cyan>'.$esc.'</>',
                    default => $esc,
                };
            }
        } finally {
            @unlink($fa);
            @unlink($fb);
        }

        return $out;
    }

    /** @return list<string> */
    private function sketchDiff(string $a, string $b, int $context): array
    {
        $al = explode("\n", $a);
        $bl = explode("\n", $b);
        $n = count($al);
        $m = count($bl);
        // first and last common prefix/suffix: cheap and enough to show where it diverges
        $start = 0;
        while ($start < $n && $start < $m && $al[$start] === $bl[$start]) {
            $start++;
        }
        $endA = $n - 1;
        $endB = $m - 1;
        while ($endA >= $start && $endB >= $start && $al[$endA] === $bl[$endB]) {
            $endA--;
            $endB--;
        }
        $out = [];
        $from = max(0, $start - $context);
        for ($i = $from; $i < $start; $i++) {
            $out[] = '  '.$this->clip($al[$i]);
        }
        $shownA = 0;
        for ($i = $start; $i <= $endA && $shownA < 12; $i++, $shownA++) {
            $out[] = '<fg=red>- '.$this->clip($al[$i]).'</>';
        }
        if ($endA - $start + 1 > 12) {
            $out[] = sprintf('<fg=red>  ... %d more live lines</>', $endA - $start + 1 - 12);
        }
        $shownB = 0;
        for ($i = $start; $i <= $endB && $shownB < 12; $i++, $shownB++) {
            $out[] = '<fg=green>+ '.$this->clip($bl[$i]).'</>';
        }
        if ($endB - $start + 1 > 12) {
            $out[] = sprintf('<fg=green>  ... %d more v3 lines</>', $endB - $start + 1 - 12);
        }
        $out[] = sprintf('  (live %d lines, v3 %d lines, diverge at line %d)', $n, $m, $start + 1);

        return $out;
    }

    private function clip(string $s): string
    {
        return OutputFormatter::escape(mb_strimwidth($s, 0, 160, '...'));
    }
}
