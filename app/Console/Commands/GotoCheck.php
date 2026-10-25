<?php

namespace App\Console\Commands;

use App\Fec\Clicks\Subid;
use App\Fec\Shortlink\Shortlinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * php artisan fec:goto:check kingbilly            what v3 would do with ?link=kingbilly (no side effects)
 * php artisan fec:goto:check kingbilly --live     also follow the legacy goto.php on the live site (bot UA,
 *                                                 so the click it logs is flagged is_bot) and compare targets
 */
class GotoCheck extends Command
{
    protected $signature = 'fec:goto:check {phrase : shortlink phrase} {--live : compare with the live legacy goto.php}';

    protected $description = 'Parity check for the goto.php route against jos_shortlink and the live site';

    public function handle(Shortlinks $shortlinks): int
    {
        $phrase = (string) $this->argument('phrase');
        $row = $shortlinks->find($phrase);

        if (! $row) {
            $this->warn("v3: phrase '$phrase' not in jos_shortlink -> fallback ".config('fec.goto.fallback'));
        } else {
            $this->info("v3: $phrase -> ".$row->link);
            foreach (['', 'https://www.freeextrachips.com/bonuses/no-deposit-casinos', 'https://www.freeextrachips.com/bonuses/kingbilly/x-y-id1'] as $ref) {
                $subid = Subid::fromSrc($ref);
                $with = Subid::append((string) $row->link, $subid);
                $this->line(sprintf('   referer %-62s subid %-8s %s', $ref === '' ? '(none)' : $ref, $subid, $with === $row->link ? 'url unchanged' : 'url + subid: '.$with));
            }
        }

        if ($this->option('live')) {
            $url = rtrim(config('fec.site_url'), '/').'/goto.php?link='.rawurlencode($phrase);
            try {
                $response = Http::withHeaders(['User-Agent' => 'FEC-v3-parity-check (bot)'])
                    ->withOptions(['allow_redirects' => false])
                    ->timeout(20)
                    ->get($url);
                $this->line(sprintf('legacy: HTTP %d  Location: %s', $response->status(), $response->header('Location') ?: '(none)'));
                $site = rtrim(config('fec.site_url'), '/');
                // com_shortlink answers with a RELATIVE Location ("index.php?option=com_shortlink&...");
                // resolve it like a browser would, then follow while we stay on our own host.
                $absolute = function (?string $loc) use ($site): ?string {
                    if ($loc === null || $loc === '') {
                        return null;
                    }
                    if (preg_match('#^https?://#i', $loc)) {
                        return $loc;
                    }

                    return $site.'/'.ltrim($loc, '/');
                };
                $hops = 0;
                $next = $absolute($response->header('Location'));
                while ($next && $hops < 5 && preg_match('#^https?://(www\.)?freeextrachips\.com#i', $next) === 1) {
                    $r = Http::withHeaders(['User-Agent' => 'FEC-v3-parity-check (bot)'])->withOptions(['allow_redirects' => false])->timeout(20)->get($next);
                    $hops++;
                    $this->line(sprintf('        hop %d: HTTP %d  Location: %s', $hops, $r->status(), $r->header('Location') ?: '(none)'));
                    $next = $absolute($r->header('Location'));
                }
                if ($row && $next) {
                    $this->line(parse_url($next, PHP_URL_HOST) === parse_url((string) $row->link, PHP_URL_HOST) ? 'PARITY: same affiliate host' : 'DIFFERENT TARGET: '.$next);
                }
            } catch (\Throwable $e) {
                $this->error('legacy fetch failed: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
