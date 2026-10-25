<?php

namespace App\Fec\Clicks;

/**
 * Landing-page token forwarding (port of fec_subid_* in fec_common_lib.php,
 * v1 Aug 2026). Low-cardinality page tokens only; stored on every click,
 * forwarded to an affiliate URL only for programs enabled in programMap(),
 * which ships EMPTY by design (gate procedure in SUBID_ACTIVATION_PLAN.md).
 */
final class Subid
{
    /** Landing-page path prefix => token. Longest matching prefix wins. */
    public static function pageRegistry(): array
    {
        return [
            '/bonuses/free-spins/deposit-required' => 'fs-dep',
            '/bonuses/free-spins/no-deposit' => 'fs-nd',
            '/bonuses/free-spins' => 'fs-hub',
            '/bonuses/1st-deposit-match' => 'fdm',
            '/bonuses/no-deposit-casinos' => 'nd-cas',
            '/bonuses/high-roller-bonuses' => 'hr',
            '/bonuses/crypto' => 'crypto',
            '/bonuses' => 'bon-hub',
        ];
    }

    /** "fec-{token}", [a-z0-9-], <= 24 chars, never empty. */
    public static function fromSrc(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return 'fec-dir';
        }

        $p = @parse_url($src);
        if (! is_array($p)) {
            return 'fec-dir';
        }

        $host = isset($p['host']) ? strtolower((string) $p['host']) : '';
        if ($host !== '' && ! str_contains($host, 'freeextrachips')) {
            return 'fec-ext';
        }

        $path = isset($p['path']) ? (string) $p['path'] : '';
        $path = preg_replace('/\.html?$/i', '', $path) ?? $path;
        $path = '/'.trim($path, '/');
        if ($path === '/') {
            return 'fec-home';
        }

        $tok = 'oth';
        $bestLen = 0;
        foreach (self::pageRegistry() as $prefix => $t) {
            $prefix = '/'.trim((string) $prefix, '/');
            if (($path === $prefix || str_starts_with($path, $prefix.'/')) && strlen($prefix) > $bestLen) {
                $bestLen = strlen($prefix);
                $tok = (string) $t;
            }
        }

        // Bonus detail pages (/bonuses/{brand}/{offer}) are their own landing class.
        if ($tok === 'bon-hub' && substr_count($path, '/') >= 3) {
            $tok = 'bd';
        }

        $subid = preg_replace('/[^a-z0-9\-]/', '', strtolower('fec-'.$tok)) ?? '';
        if (strlen($subid) > 24) {
            $subid = substr($subid, 0, 24);
        }

        return $subid === '' ? 'fec-oth' : $subid;
    }

    /**
     * Affiliate-target host substring (lowercase) => forwarding config.
     * Intentionally empty: entries are added by hand after verification.
     *
     * @return array<string, array{style: string, param: string, enabled: int}>
     */
    public static function programMap(): array
    {
        return [];
    }

    /** Append the subid only for enabled programs; otherwise the URL comes back byte-identical. */
    public static function append(string $url, string $subid): string
    {
        try {
            if ($url === '' || $subid === '' || ! preg_match('/^[a-z0-9\-]{1,24}$/', $subid)) {
                return $url;
            }
            $host = @parse_url($url, PHP_URL_HOST);
            if (! is_string($host) || $host === '') {
                return $url;
            }
            $host = strtolower($host);

            $cfg = null;
            foreach (self::programMap() as $needle => $c) {
                $needle = strtolower(trim((string) $needle));
                if ($needle !== '' && str_contains($host, $needle)) {
                    $cfg = $c;
                    break;
                }
            }
            if (! is_array($cfg) || empty($cfg['enabled'])) {
                return $url;
            }

            $style = (string) ($cfg['style'] ?? 'query');
            $param = (string) ($cfg['param'] ?? 'sub1');
            if (! preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $param)) {
                return $url;
            }

            if ($style === 'stag_suffix') {
                $re = '/([?&]'.preg_quote($param, '/').'=)([^&#]*)/';
                if (preg_match($re, $url, $m) && ! str_contains($m[2], '_'.$subid)) {
                    $url = preg_replace($re, '${1}${2}_'.$subid, $url, 1) ?? $url;
                }

                return $url;
            }

            if (preg_match('/[?&]'.preg_quote($param, '/').'=/', $url)) {
                return $url;
            }

            return $url.(str_contains($url, '?') ? '&' : '?').$param.'='.rawurlencode($subid);
        } catch (\Throwable) {
            return $url;
        }
    }
}
