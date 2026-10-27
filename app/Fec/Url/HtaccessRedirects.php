<?php

namespace App\Fec\Url;

/**
 * The redirect lines of the legacy public_html/.htaccess that fall under the
 * paths v3 serves (fec.legacy.htaccess_redirects), answered by v3 itself.
 *
 * On the live site the web server answered them before Joomla saw the
 * request; with the v3 block at the top of the same .htaccess those paths
 * reach v3 instead. The rules are kept in the file's order and with each
 * directive's own semantics, the targets exactly as written:
 *
 *   ['redirect', '/path', target]      mod_alias "Redirect 301": the source is a prefix of the
 *                                      request matched on whole segments ("/bonuses/free-cash"
 *                                      also matches "/bonuses/free-cash/x", never
 *                                      "/bonuses/free-cashier"); the rest of the path is
 *                                      appended to the target as is.
 *   ['match', regex, target]           "RedirectMatch 301": the regex against the path with
 *                                      its leading slash, unanchored unless it anchors itself;
 *                                      $1..$9 in the target are the groups.
 *   ['rewrite', regex, target]         "RewriteRule ... [R=301]" in .htaccess context: the
 *                                      regex against the path without the leading slash; $1..$9.
 *
 * The query string travels on in every case (FrontController keeps it), the
 * status is 301.
 */
final class HtaccessRedirects
{
    /**
     * @param  list<array{0: string, 1: string, 2: string}>|null  $rules  default: fec.legacy.htaccess_redirects
     * @return ResolvedUrl|null kind "redirect", source "htaccess"
     */
    public static function match(string $path, ?array $rules = null): ?ResolvedUrl
    {
        $path = '/'.ltrim(UrlRegistry::normalize($path), '/');
        $rules ??= (array) config('fec.legacy.htaccess_redirects', []);

        foreach ($rules as $rule) {
            if (! is_array($rule) || count($rule) < 3) {
                continue;
            }
            [$type, $source, $target] = $rule;
            $target = (string) $target;
            if ($target === '') {
                continue;
            }
            $to = match ((string) $type) {
                'redirect' => self::aliasMatch($path, (string) $source, $target),
                'match' => self::regexMatch($path, (string) $source, $target),
                'rewrite' => self::regexMatch(ltrim($path, '/'), (string) $source, $target),
                default => null,
            };
            if ($to !== null) {
                return new ResolvedUrl('redirect', ltrim($path, '/'), [], null, $to, null, null, null, 'htaccess');
            }
        }

        return null;
    }

    /** mod_alias alias_matches(): the source as a whole-segment prefix, the rest appended. */
    private static function aliasMatch(string $path, string $source, string $target): ?string
    {
        if ($source === '' || ! str_starts_with($path, $source)) {
            return null;
        }
        $rest = substr($path, strlen($source));
        if (! str_ends_with($source, '/') && $rest !== '' && $rest[0] !== '/') {
            return null;
        }

        return $target.$rest;
    }

    /** RedirectMatch / RewriteRule: a PCRE against the subject, $N of the target replaced. */
    private static function regexMatch(string $subject, string $regex, string $target): ?string
    {
        if ($regex === '' || @preg_match('#'.str_replace('#', '\#', $regex).'#', $subject, $m) !== 1) {
            return null;
        }

        return (string) preg_replace_callback('/\$(\d)/', fn ($g) => $m[(int) $g[1]] ?? '', $target);
    }
}
