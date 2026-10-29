<?php

namespace App\Fec\Url;

use Illuminate\Support\Facades\DB;

/**
 * The root switch of phase 4: every public path goes to v3 except what
 * Joomla keeps. What Joomla keeps:
 *
 *   - files and directories on disk (the web server serves them, as now);
 *   - requests carrying task= or option= in the query string (crons, desks,
 *     non-SEF links: /index.php?option=com_jackpots&task=data.update...);
 *   - every method but GET and HEAD (Joomla's login module and forms posted
 *     to "/" or a SEF path with the task in the body; v3's public fallback
 *     answers GET and HEAD only);
 *   - the registry URLs of the components and views that stay on Joomla
 *     (fec.legacy.root_keep: options, views, plus the desk views), as the
 *     shortest prefixes that no other registry URL shares;
 *   - a few fixed paths (fec.legacy.root_keep.paths: /administrator,
 *     /index.php and its path-info, menu items that are no registry URL).
 *
 * Everything else goes to v3 only where Joomla's own catch-all took it
 * (line 347 of the live file: a last segment without a dot, or ending in
 * .php, .htm(l), .feed, .pdf, .vcf, .raw): a path like "/.env" or
 * "/x/y.jpg" that is no file keeps the web server's 404 as now. The block
 * goes right below the per-section blocks (com_bonuses, com_authors, goto),
 * which stay as they are: they send their whole prefixes, dots included
 * ("/bonuses/goldbet.io-casino").
 *
 * fec:urls:htaccess --root prints the .htaccess block from this,
 * fec:urls:replay checks a slice of the live access log against it.
 */
final class RootSwitch
{
    public const JOOMLA = 'joomla';

    public const V3 = 'v3';

    public const NOT_PORTED = 'not ported';

    /** Joomla's catch-all (live .htaccess, line 347), without its "/component/" branch: that prefix stays on Joomla. */
    public const CATCH_ALL = '(/[^.]*|\.(php|html?|feed|pdf|vcf|raw))$';

    /**
     * @param  list<string>  $prefixes  kept as "path" and "path/..."
     * @param  list<string>  $exact  kept as "path" and "path/"
     * @param  array<string, array<string, array{0: int, 1: list<string>}>>  $stats  class => option.view => [count, samples]
     */
    public function __construct(
        public readonly array $prefixes,
        public readonly array $exact,
        public readonly array $stats = [],
    ) {}

    /** From the registry and fec.legacy (root_keep, desk_views). */
    public static function fromRegistry(): self
    {
        $rows = DB::connection('fec')->table('sh404sef_urls')
            ->where('rank', 0)->where('newurl', '<>', '')
            ->get(['oldurl', 'newurl']);
        $cfg = (array) config('fec.legacy.root_keep', []);

        return self::fromRows(
            $rows->map(fn ($r) => [(string) $r->oldurl, (string) $r->newurl])->all(),
            (array) ($cfg['options'] ?? []),
            array_merge((array) config('fec.legacy.desk_views', []), (array) ($cfg['views'] ?? [])),
            (array) ($cfg['paths'] ?? []),
        );
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows  [oldurl, newurl] of the canonical registry URLs
     * @param  list<string>  $keepOptions  components that stay on Joomla (com_contactus)
     * @param  list<string>  $keepViews  "option.view" keys that stay on Joomla (the desks)
     * @param  list<string>  $fixed  paths kept as prefixes whatever the registry says
     * @param  list<array<int, string>>|null  $htaccessRules  default: fec.legacy.htaccess_redirects
     */
    public static function fromRows(array $rows, array $keepOptions, array $keepViews, array $fixed = [], ?array $htaccessRules = null): self
    {
        $kept = [];
        $others = [];
        $stats = [];
        foreach ($rows as [$oldurl, $newurl]) {
            $path = trim($oldurl, '/');
            if ($path === '') {
                continue;
            }
            $q = UrlRegistry::parseQuery($newurl);
            $option = $q['option'] ?? '';
            $key = $option.'.'.($q['view'] ?? ($q['task'] ?? 'default'));
            if (in_array($option, $keepOptions, true) || in_array($key, $keepViews, true)) {
                $kept[$path] = true;
                $class = self::JOOMLA;
            } else {
                $others[$path] = true;
                $rule = HtaccessRedirects::match('/'.$path, $htaccessRules);
                $class = $rule !== null
                    ? 'htaccess '.$rule->kind
                    : (isset(Dispatcher::HANDLERS[$key]) ? self::V3 : self::NOT_PORTED);
            }
            $stats[$class][$key] ??= [0, []];
            $stats[$class][$key][0]++;
            // every not-ported URL is listed (each one needs a decision), three samples of the rest
            if (count($stats[$class][$key][1]) < ($class === self::NOT_PORTED ? 200 : 3)) {
                $stats[$class][$key][1][] = $oldurl; // as stored: the trailing slash matters
            }
        }

        [$prefixes, $exact] = self::prefixes($kept, $others);
        foreach ($fixed as $p) {
            $p = trim((string) $p, '/');
            if ($p !== '') {
                $prefixes[$p] = true;
            }
        }
        // an exact path or a longer prefix covered by a prefix is redundant
        foreach ([&$exact, &$prefixes] as &$set) {
            foreach (array_keys($set) as $path) {
                foreach (array_keys($prefixes) as $prefix) {
                    if ($path !== $prefix && str_starts_with($path, $prefix.'/')) {
                        unset($set[$path]);
                    }
                }
            }
        }
        unset($set);
        foreach (array_keys($exact) as $path) {
            if (isset($prefixes[$path])) {
                unset($exact[$path]);
            }
        }
        ksort($prefixes);
        ksort($exact);

        return new self(array_map('strval', array_keys($prefixes)), array_map('strval', array_keys($exact)), $stats);
    }

    /**
     * The shortest prefix of each of $mine that no path of $others equals or lives
     * under; the path itself, exact, when even that is shared (or a prefix when
     * other paths of $mine live under it).
     *
     * @param  array<string, true>  $mine
     * @param  array<string, true>  $others
     * @return array{0: array<string, true>, 1: array<string, true>} [prefixes, exact]
     */
    public static function prefixes(array $mine, array $others): array
    {
        $prefixes = [];
        $exact = [];
        foreach (array_keys($mine) as $path) {
            $path = (string) $path;
            $segments = explode('/', $path);
            $found = null;
            for ($n = 1; $n <= count($segments); $n++) {
                $prefix = implode('/', array_slice($segments, 0, $n));
                $clash = false;
                foreach (array_keys($others) as $other) {
                    $other = (string) $other;
                    if ($other === $prefix || str_starts_with($other, $prefix.'/')) {
                        $clash = true;
                        break;
                    }
                }
                if (! $clash) {
                    $found = $prefix;
                    break;
                }
            }
            if ($found !== null && $found !== $path) {
                $prefixes[$found] = true;
            } elseif ($found !== null) {
                // the path itself is exclusive: a prefix if other own URLs live under it, exact otherwise
                $deeper = false;
                foreach (array_keys($mine) as $m) {
                    if (str_starts_with((string) $m, $path.'/')) {
                        $deeper = true;
                        break;
                    }
                }
                if ($deeper) {
                    $prefixes[$path] = true;
                } else {
                    $exact[$path] = true;
                }
            } else {
                $exact[$path] = true;
            }
        }
        // an exact path covered by a prefix is redundant
        foreach (array_keys($exact) as $path) {
            foreach (array_keys($prefixes) as $prefix) {
                if ($path === $prefix || str_starts_with((string) $path, $prefix.'/')) {
                    unset($exact[$path]);
                }
            }
        }
        ksort($prefixes);
        ksort($exact);

        return [$prefixes, $exact];
    }

    /** Does Joomla keep this request? The path as requested (leading slash or not), the raw query string. */
    public function keeps(string $path, string $query = '', string $method = 'GET'): bool
    {
        if (! in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            return true;
        }
        if ($query !== '' && preg_match('/(^|&)(task|option)=/', $query)) {
            return true;
        }
        $path = trim((string) (parse_url($path, PHP_URL_PATH) ?? $path), '/');
        if ($path === '') {
            return false;
        }
        foreach ($this->prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return in_array($path, $this->exact, true);
    }

    /** Would Joomla's catch-all have taken this path (decoded, as REQUEST_URI)? The home always goes. */
    public static function catchAll(string $path): bool
    {
        $path = '/'.ltrim(rawurldecode($path), '/');

        return $path === '/' || preg_match('#'.self::CATCH_ALL.'#i', $path) === 1;
    }

    /**
     * The block for public_html/.htaccess, right below the per-section blocks. The home has its own
     * rule (the docroot is a directory, which the second rule leaves alone).
     * $test gates both rules on v3test=1 (the trial under the live hostname).
     *
     * @return list<string>
     */
    public function block(bool $test = false, string $date = ''): array
    {
        $quote = fn (string $p) => preg_quote($p, '#');
        $cond = ['RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$', 'RewriteCond %{QUERY_STRING} !(^|&)(task|option)='];
        if ($test) {
            $cond[] = 'RewriteCond %{QUERY_STRING} (^|&)v3test=1(&|$)';
        }

        $lines = [];
        $lines[] = '# FEC v3 root switch - every path to the v3 app but what Joomla keeps (generated '.$date.' by fec:urls:htaccess --root)';
        $lines[] = '# '.($test
            ? 'TEST: only with v3test=1 in the query string; drop the two v3test RewriteCond lines to switch for real'
            : 'Right below the per-section blocks (com_bonuses, com_authors, goto), which stay. Roll back: remove this block');
        $lines[] = '# Everything else goes to v3 where Joomla\'s catch-all took it (same REQUEST_URI condition)';
        $lines[] = '# Joomla keeps: files and directories on disk, POST and other non-GET requests, task= and option= requests, '.implode(', ', array_merge(
            array_map(fn ($p) => '/'.$p.'/...', $this->prefixes),
            array_map(fn ($p) => '/'.$p, $this->exact),
        ));
        $lines[] = '<IfModule mod_rewrite.c>';
        $lines[] = 'RewriteEngine On';
        array_push($lines, ...$cond);
        $lines[] = 'RewriteRule ^$ /v3/index.php [L]';
        array_push($lines, ...$cond);
        $lines[] = 'RewriteCond %{REQUEST_URI} '.self::CATCH_ALL.' [NC]';
        $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        if ($this->prefixes !== []) {
            $lines[] = 'RewriteCond %{REQUEST_URI} !^/('.implode('|', array_map($quote, $this->prefixes)).')(/|$)';
        }
        if ($this->exact !== []) {
            $lines[] = 'RewriteCond %{REQUEST_URI} !^/('.implode('|', array_map($quote, $this->exact)).')/?$';
        }
        $lines[] = 'RewriteRule ^ /v3/index.php [L]';
        $lines[] = '</IfModule>';
        $lines[] = '';

        return $lines;
    }

    /** Status classes for the log replay: 2xx and 304 are one class, every redirect another, the rest by code. */
    public static function statusClass(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 300, $status === 304 => '200',
            $status >= 300 && $status < 400 => '30x',
            $status >= 500 => '5xx',
            default => (string) $status,
        };
    }
}
