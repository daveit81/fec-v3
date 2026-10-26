<?php

namespace App\Console\Commands;

use App\Fec\Url\UrlRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan fec:urls:htaccess --option=com_authors [--test]
 *
 * Prints the block for the legacy public_html/.htaccess that sends a
 * section's public paths to the v3 app: the switch of the convivenza. Paths
 * come from the registry; a prefix is used only when every registry URL
 * under it belongs to the component (so "authors/" and "slots/" become
 * prefixes while "online-casinos/fast-payouts" stays exact, because
 * "online-casinos/<casino>" is com_content). Desk views are left on Joomla.
 * --test gates the rules on v3test=1 in the query string, to try the
 * section under the live hostname before the real switch.
 */
class UrlHtaccess extends Command
{
    protected $signature = 'fec:urls:htaccess {--option= : Component (com_authors)}
                            {--test : Gate the rules on ?v3test=1 (pre-switch trial)}';

    protected $description = 'Print the legacy .htaccess block that routes a section to v3';

    public function handle(): int
    {
        $option = (string) $this->option('option');
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            $this->error('Give --option=com_x');

            return self::INVALID;
        }

        $desk = (array) config('fec.legacy.desk_views', []);
        $rows = DB::connection('fec')->table('sh404sef_urls')
            ->where('rank', 0)->where('newurl', '<>', '')
            ->get(['oldurl', 'newurl']);

        $mine = [];      // path => true (this component, public)
        $others = [];    // path => true (everything else)
        foreach ($rows as $r) {
            $path = trim((string) $r->oldurl, '/');
            $q = UrlRegistry::parseQuery((string) $r->newurl);
            $key = ($q['option'] ?? '').'.'.($q['view'] ?? '');
            if (($q['option'] ?? '') === $option && ! in_array($key, $desk, true)) {
                $mine[$path] = true;
            } else {
                $others[$path] = true;
            }
        }
        if ($mine === []) {
            $this->error('No public registry URL for '.$option);

            return self::FAILURE;
        }

        // Shortest exclusive prefix per path: no other component's URL equals it or lives under it.
        $prefixes = [];
        $exact = [];
        foreach (array_keys($mine) as $path) {
            $segments = explode('/', $path);
            $found = null;
            for ($n = 1; $n <= count($segments); $n++) {
                $prefix = implode('/', array_slice($segments, 0, $n));
                $clash = false;
                foreach (array_keys($others) as $other) {
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
                    if (str_starts_with($m, $path.'/')) {
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
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    unset($exact[$path]);
                }
            }
        }
        ksort($prefixes);
        ksort($exact);

        $quote = fn (string $p) => preg_quote($p, '#');
        // Requests carrying task= (snapshot crons, desk saves) stay on Joomla until those tasks are ported.
        $cond = "RewriteCond %{QUERY_STRING} !(^|&)task=\n";
        if ($this->option('test')) {
            $cond .= "RewriteCond %{QUERY_STRING} (^|&)v3test=1(&|$)\n";
        }

        $lines = [];
        $lines[] = '# FEC v3 switch - '.$option.' served by the v3 app ('.count($mine).' public URLs; generated '.date('Y-m-d').' by fec:urls:htaccess)';
        $lines[] = '# '.($this->option('test') ? 'TEST: only with v3test=1 in the query string; drop the v3test RewriteCond lines to switch for real' : 'Remove this block to roll back').'; task= requests (crons, desks) stay on Joomla';
        $lines[] = '<IfModule mod_rewrite.c>';
        $lines[] = 'RewriteEngine On';
        if ($prefixes !== []) {
            $lines[] = rtrim($cond)."\n".'RewriteRule ^('.implode('|', array_map($quote, array_keys($prefixes))).')(/.*)?$ /v3/index.php [L]';
        }
        if ($exact !== []) {
            $lines[] = rtrim($cond)."\n".'RewriteRule ^('.implode('|', array_map($quote, array_keys($exact))).')/?$ /v3/index.php [L]';
        }
        $lines[] = '</IfModule>';

        $this->line(implode("\n", $lines));
        $this->line('');
        $this->comment(sprintf('%d prefix rule(s): %s', count($prefixes), implode(', ', array_keys($prefixes))));
        $this->comment(sprintf('%d exact path(s): %s', count($exact), implode(', ', array_keys($exact))));

        return self::SUCCESS;
    }
}
