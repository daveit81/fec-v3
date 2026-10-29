<?php

namespace App\Console\Commands;

use App\Fec\Url\RootSwitch;
use App\Fec\Url\UrlRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan fec:urls:htaccess --option=com_authors [--test]
 * php artisan fec:urls:htaccess --root [--test]
 *
 * Prints the block for the legacy public_html/.htaccess that sends a
 * section's public paths to the v3 app: the switch of the convivenza. Paths
 * come from the registry; a prefix is used only when every registry URL
 * under it belongs to the component (so "authors/" and "slots/" become
 * prefixes while "online-casinos/fast-payouts" stays exact, because
 * "online-casinos/<casino>" is com_content). Desk views are left on Joomla.
 * --test gates the rules on v3test=1 in the query string, to try the
 * section under the live hostname before the real switch. --served names
 * the components already switched to v3: their URLs no longer block a
 * prefix ("bonuses/welcome-packages" is com_authors, so "bonuses" can be a
 * prefix of com_bonuses once com_authors is on v3; v3 routes both).
 *
 * --root prints the block of the root switch instead (App\Fec\Url\RootSwitch):
 * every path to v3 but what Joomla keeps, with the registry's canonical URLs
 * counted by what v3 does with them (served, answered by an .htaccess rule,
 * kept on Joomla, not ported: those would be 404 on v3).
 */
class UrlHtaccess extends Command
{
    protected $signature = 'fec:urls:htaccess {--option= : Component (com_authors)}
                            {--root : The root switch block: every path to v3 but what Joomla keeps}
                            {--served= : Components already served by v3, comma separated (com_authors): no conflict with their URLs}
                            {--test : Gate the rules on ?v3test=1 (pre-switch trial)}';

    protected $description = 'Print the legacy .htaccess block that routes a section to v3';

    public function handle(): int
    {
        if ($this->option('root')) {
            return $this->root();
        }

        $option = (string) $this->option('option');
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            $this->error('Give --option=com_x');

            return self::INVALID;
        }

        $desk = (array) config('fec.legacy.desk_views', []);
        $served = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('served')))));
        $rows = DB::connection('fec')->table('sh404sef_urls')
            ->where('rank', 0)->where('newurl', '<>', '')
            ->get(['oldurl', 'newurl']);

        $mine = [];      // path => true (this component, public)
        $others = [];    // path => true (everything else still on Joomla)
        $onV3 = 0;       // public URLs of the components already served by v3: neither side of a conflict
        foreach ($rows as $r) {
            $path = trim((string) $r->oldurl, '/');
            $q = UrlRegistry::parseQuery((string) $r->newurl);
            $key = ($q['option'] ?? '').'.'.($q['view'] ?? '');
            if (($q['option'] ?? '') === $option && ! in_array($key, $desk, true)) {
                $mine[$path] = true;
            } elseif (in_array($q['option'] ?? '', $served, true) && ! in_array($key, $desk, true)) {
                $onV3++;
            } else {
                $others[$path] = true;
            }
        }
        if ($served !== []) {
            $this->comment(sprintf('%d public URL(s) of %s already on v3: not counted as conflicts', $onV3, implode(', ', $served)));
        }
        if ($mine === []) {
            $this->error('No public registry URL for '.$option);

            return self::FAILURE;
        }

        // Shortest exclusive prefix per path: no other component's URL equals it or lives under it.
        [$prefixes, $exact] = RootSwitch::prefixes($mine, $others);

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
        $lines[] = '';

        // A trailing newline: the block is prepended to the live .htaccess with cat.
        $this->line(implode("\n", $lines));
        $this->comment(sprintf('%d prefix rule(s): %s', count($prefixes), implode(', ', array_keys($prefixes))));
        $this->comment(sprintf('%d exact path(s): %s', count($exact), implode(', ', array_keys($exact))));

        return self::SUCCESS;
    }

    private function root(): int
    {
        $switch = RootSwitch::fromRegistry();

        // what v3 does with the registry's canonical URLs, by option.view
        $order = [RootSwitch::V3, RootSwitch::JOOMLA, RootSwitch::NOT_PORTED];
        $classes = array_keys($switch->stats);
        usort($classes, fn ($a, $b) => [array_search($a, $order, true) === false ? 9 : array_search($a, $order, true), $a]
            <=> [array_search($b, $order, true) === false ? 9 : array_search($b, $order, true), $b]);
        foreach ($classes as $class) {
            $keys = $switch->stats[$class];
            uasort($keys, fn ($a, $b) => $b[0] <=> $a[0]);
            $this->info(sprintf('%s: %d URL(s)', $class, array_sum(array_map(fn ($k) => $k[0], $keys))));
            if ($class === RootSwitch::NOT_PORTED) {
                // each one needs a decision before the switch: listed in full, one per line
                $rows = [];
                foreach ($keys as $key => $v) {
                    foreach ($v[1] as $path) {
                        $rows[] = [$key, $path];
                    }
                }
                $this->table(['option.view', 'path'], $rows);

                continue;
            }
            $this->table(['option.view', 'URLs', 'samples'], array_map(
                fn ($key, $v) => [$key, number_format($v[0]), implode('  ', $v[1])],
                array_keys($keys), $keys
            ));
        }

        $this->line(implode("\n", $switch->block((bool) $this->option('test'), date('Y-m-d'))));
        $this->comment(sprintf('Joomla keeps %d prefix(es): %s', count($switch->prefixes), implode(', ', $switch->prefixes)));
        $this->comment(sprintf('Joomla keeps %d exact path(s): %s', count($switch->exact), implode(', ', $switch->exact)));

        return self::SUCCESS;
    }
}
