<?php

namespace App\Fec\Clicks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Port of fec_log_bonus_click() (fec_common_lib.php, click tracking sprint).
 * One row per affiliate click in jos_bonus_clicks: no raw IP, country from
 * CF-IPCountry (legacy default "US" when unknown), coarse device and bot
 * sniff from the user agent, src_page = referer path, referer = host+path,
 * subid token, UTC timestamp. Non-blocking by contract: every failure is
 * swallowed and logged, the caller's redirect always fires.
 */
final class ClickLogger
{
    /** @param array{slug?: string, src_page?: string, referer?: string, subid?: string} $opts */
    public function log(Request $request, int $cid, int $bid, array $opts = []): int
    {
        try {
            $slug = substr((string) ($opts['slug'] ?? ''), 0, 64);
            if ($cid <= 0 && $bid <= 0 && $slug === '') {
                return 0;
            }

            $referer = (string) $request->header('Referer', '');

            $srcRaw = (string) ($opts['src_page'] ?? '');
            if ($srcRaw === '') {
                $srcRaw = $referer;
            }
            $srcPath = parse_url($srcRaw, PHP_URL_PATH);
            $srcPage = substr(is_string($srcPath) ? $srcPath : '', 0, 255);

            $refRaw = (string) ($opts['referer'] ?? '');
            if ($refRaw === '') {
                $refRaw = $referer;
            }
            $rp = @parse_url($refRaw);
            $refClean = is_array($rp) ? substr(($rp['host'] ?? '').($rp['path'] ?? ''), 0, 255) : '';

            $country = self::legacyCountry($request);

            $ua = (string) $request->userAgent();
            $isBot = preg_match('~bot|crawl|spider|slurp|bing|prerender|headless|monitor|preview~i', $ua) ? 1 : 0;
            $device = 'd';
            if (preg_match('~Mobile|iPhone|iPod~i', $ua)) {
                $device = 'm';
            } elseif (preg_match('~iPad|Tablet|Android(?!.*Mobile)~i', $ua)) {
                $device = 't';
            }
            if ($isBot) {
                $device = 'b';
            }

            $subid = substr((string) ($opts['subid'] ?? ''), 0, 64);

            return (int) DB::connection('fec_rw')->table('bonus_clicks')->insertGetId([
                'cid' => $cid,
                'bid' => $bid,
                'slug' => $slug,
                'subid' => $subid,
                'src_page' => $srcPage,
                'referer' => $refClean,
                'country' => $country,
                'device' => $device,
                'is_bot' => $isBot,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            Log::warning('ClickLogger: insert failed', ['error' => $e->getMessage(), 'slug' => $opts['slug'] ?? null]);

            return 0;
        }
    }

    /**
     * fec_detect_country(''): CF-IPCountry (2 letters, not XX), then the
     * GEOIP_COUNTRY_CODE environment, then "US". Kept as is for parity of
     * the stored rows, even though "US" for unknown is a legacy choice.
     */
    public static function legacyCountry(Request $request): string
    {
        $cf = strtoupper(trim((string) $request->header('CF-IPCountry', '')));
        if ($cf !== '' && preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX') {
            return $cf;
        }

        $geo = getenv('GEOIP_COUNTRY_CODE');
        $geo = $geo ? strtoupper(trim((string) $geo)) : '';
        if ($geo !== '' && preg_match('/^[A-Z]{2}$/', $geo) && $geo !== 'XX') {
            return $geo;
        }

        return (string) config('fec.goto.default_country', 'US');
    }
}
