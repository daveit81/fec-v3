<?php

namespace App\Fec\Url;

/**
 * The mod_alias "Redirect 301 /path URL" lines of the legacy public_html/.htaccess
 * that fall under the paths v3 serves (fec.legacy.htaccess_redirects).
 *
 * On the live site Apache answered them before Joomla saw the request; with the
 * v3 block at the top of the same .htaccess those paths reach v3 instead, so v3
 * answers them itself, with mod_alias' semantics: the source is matched as a
 * whole path segment prefix ("/bonuses/free-cash" also matches
 * "/bonuses/free-cash/x", the rest is appended to the target), the query string
 * travels on (FrontController keeps it), status 301. The targets are kept
 * exactly as written in the file, absolute URL or not.
 */
final class HtaccessRedirects
{
    /**
     * @param  array<string, string>|null  $rules  source => target (default: fec.legacy.htaccess_redirects)
     * @return ResolvedUrl|null kind "redirect", source "htaccess"
     */
    public static function match(string $path, ?array $rules = null): ?ResolvedUrl
    {
        $path = '/'.ltrim(UrlRegistry::normalize($path), '/');
        $rules ??= (array) config('fec.legacy.htaccess_redirects', []);
        if ($rules === []) {
            return null;
        }

        foreach ($rules as $source => $target) {
            $source = '/'.trim((string) $source, '/');
            if ($source === '/' || $target === '' || $target === null) {
                continue;
            }
            if ($path === $source) {
                return self::redirect($path, (string) $target);
            }
            // mod_alias matches whole segments: /bonuses/free-cash/x, never /bonuses/free-cashier
            if (str_starts_with($path, $source.'/')) {
                return self::redirect($path, (string) $target.substr($path, strlen($source)));
            }
        }

        return null;
    }

    private static function redirect(string $path, string $target): ResolvedUrl
    {
        return new ResolvedUrl('redirect', ltrim($path, '/'), [], null, $target, null, null, null, 'htaccess');
    }
}
