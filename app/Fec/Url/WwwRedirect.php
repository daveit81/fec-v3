<?php

namespace App\Fec\Url;

/**
 * sh404SEF's _checkDomain() with "enforce www" (shAutoRedirectWww), as the
 * live site answers it: a request on the bare domain (freeextrachips.com) is
 * redirected 301 to the same URL on www, path and query string as sent
 * (JURI::toString() of the request). It ran when sh404SEF started up, after
 * the web server's own rules and before any alias or URL lookup, only when
 * _canRedirectFrom() allowed it: no POST, no "tmpl=component", "no_html=1"
 * or "index2.php" in the URL, no XMLHttpRequest.
 *
 * Seen in the live access log on 29 Oct 2026 (PetalBot, scanners): every
 * Joomla path asked on the bare domain answers 301, the same path on www
 * 200 or 404. Only the bare domain of fec.site_url is redirected (sh404SEF
 * prefixed "www." to any host; v3's own hostname must not move).
 */
final class WwwRedirect
{
    public static function target(string $siteUrl, string $host, string $method, string $requestUri, string $xRequestedWith = ''): ?string
    {
        $live = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        if (! str_starts_with($live, 'www.') || strtolower($host) !== substr($live, 4)) {
            return null;
        }
        if (! in_array(strtoupper($method), ['GET', 'HEAD'], true) || strtolower($xRequestedWith) === 'xmlhttprequest') {
            return null;
        }
        foreach (['tmpl=component', 'no_html=1', 'index2.php'] as $never) {
            if (str_contains($requestUri, $never)) {
                return null;
            }
        }
        $scheme = (string) (parse_url($siteUrl, PHP_URL_SCHEME) ?: 'https');

        return $scheme.'://'.$live.'/'.ltrim($requestUri === '' ? '/' : $requestUri, '/');
    }
}
