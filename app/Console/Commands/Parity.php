<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
    protected $signature = 'fec:parity {path* : Public paths to compare}
                            {--save : Write the normalised documents to storage/app/parity/}
                            {--context=3 : Diff context lines}
                            {--main : Compare only the <main> element (default: whole <body>)}';

    protected $description = 'Compare the live Joomla page and the v3 rendering of the same path';

    public function handle(): int
    {
        $failed = 0;
        foreach ((array) $this->argument('path') as $path) {
            $path = '/'.ltrim((string) $path, '/');
            $this->line('');
            $this->info("== $path");
            try {
                $live = $this->fetchLive($path);
            } catch (Throwable $e) {
                $this->error('live fetch failed: '.$e->getMessage());
                $failed++;

                continue;
            }
            try {
                $v3 = $this->renderV3($path);
            } catch (Throwable $e) {
                $this->error('v3 render failed: '.$e->getMessage());
                $failed++;

                continue;
            }

            $ok = $this->compare($path, $live, $v3);
            if (! $ok) {
                $failed++;
            }
        }
        $this->line('');
        $this->line($failed === 0 ? '<info>PARITY: all green</info>' : "<error>PARITY: $failed path(s) differ</error>");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
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

        return ['status' => $response->status(), 'html' => $response->body()];
    }

    /** @return array{status: int, html: string} */
    private function renderV3(string $path): array
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

        return ['status' => $response->getStatusCode(), 'html' => $html];
    }

    /** @param  array{status: int, html: string}  $live @param  array{status: int, html: string}  $v3 */
    private function compare(string $path, array $live, array $v3): bool
    {
        $ok = true;
        $ok = $this->check('status', (string) $live['status'], (string) $v3['status']) && $ok;

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
        // CSRF tokens, cache busters, nonces: never equal between two requests.
        $html = preg_replace('/name="[a-f0-9]{32,}" value="1"/', 'name="CSRF" value="1"', $html) ?? $html;
        $html = preg_replace('/\?v=\d+/', '?v=N', $html) ?? $html;
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;
        // one tag per line so diffs stay readable
        $html = str_replace('><', ">\n<", $html);

        return trim($html);
    }

    /** @return list<string> a compact unified-ish diff of the two texts */
    private function diff(string $a, string $b, int $context): array
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
        return \Symfony\Component\Console\Formatter\OutputFormatter::escape(mb_strimwidth($s, 0, 160, '...'));
    }
}
