<?php

namespace App\Fec\Url;

/**
 * The rules of the legacy public_html/.htaccess that fall under the paths v3
 * serves (fec.legacy.htaccess_redirects), answered by v3 itself.
 *
 * On the live site the web server answered them before Joomla saw the
 * request. The v3 block sits at the top of that file and rewrites to
 * /v3/index.php, whose own .htaccess (Laravel's) is the only one the second
 * pass reads: every rule below the block is skipped for the paths it routes.
 * So v3 answers them, in the file's order and with each directive's own
 * semantics, the targets exactly as written:
 *
 *   ['redirect', '/path', target]      mod_alias "Redirect 301": the source is a prefix of the
 *                                      request matched on whole segments ("/bonuses/free-cash"
 *                                      also matches "/bonuses/free-cash/x", never
 *                                      "/bonuses/free-cashier"); the rest of the path is
 *                                      appended to the target as is. 301.
 *   ['match', regex, target]           "RedirectMatch 301": the regex against the path with
 *                                      its leading slash, unanchored unless it anchors itself;
 *                                      $1..$9 in the target are the groups. 301.
 *   ['rewrite', regex, target]         "RewriteRule ... [R=301]" in .htaccess context: the
 *                                      regex against the path without the leading slash; $1..$9. 301.
 *   ['gone', regex]                    "RewriteRule regex - [G]": 410 Gone.
 *   ['forbidden-query', regex, 'NC']   "RewriteCond %{QUERY_STRING} regex [NC,OR]" before a
 *                                      "RewriteRule .* ... [F]": 403 when the raw query string
 *                                      matches; the third element is the flags ("NC" = no case).
 *   ['forbidden-agent', regex, 'NC']   "RewriteCond %{HTTP_USER_AGENT} regex" before "[F]": 403.
 *
 * The query string travels on with every redirect (FrontController keeps it).
 */
final class HtaccessRedirects
{
    /**
     * @param  list<array<int, string>>|null  $rules  default: fec.legacy.htaccess_redirects
     * @return ResolvedUrl|null kind "redirect" (301), "gone" (410) or "forbidden" (403), source "htaccess"
     */
    public static function match(string $path, ?array $rules = null, string $query = '', string $userAgent = ''): ?ResolvedUrl
    {
        $path = '/'.ltrim(UrlRegistry::normalize($path), '/');
        $rules ??= (array) config('fec.legacy.htaccess_redirects', []);

        foreach ($rules as $rule) {
            if (! is_array($rule) || count($rule) < 2) {
                continue;
            }
            $type = (string) $rule[0];
            $source = (string) $rule[1];
            $third = (string) ($rule[2] ?? '');

            if ($type === 'gone') {
                if (self::regexMatch(ltrim($path, '/'), $source, '') !== null) {
                    return new ResolvedUrl('gone', ltrim($path, '/'), [], null, null, null, null, null, 'htaccess');
                }

                continue;
            }
            if ($type === 'forbidden-query' || $type === 'forbidden-agent') {
                $subject = $type === 'forbidden-query' ? $query : $userAgent;
                if ($subject !== '' && self::regexMatch($subject, $source, '', str_contains(strtoupper($third), 'NC')) !== null) {
                    return new ResolvedUrl('forbidden', ltrim($path, '/'), [], null, null, null, null, null, 'htaccess');
                }

                continue;
            }

            if ($third === '') {
                continue;
            }
            $to = match ($type) {
                'redirect' => self::aliasMatch($path, $source, $third),
                'match' => self::regexMatch($path, $source, $third),
                'rewrite' => self::regexMatch(ltrim($path, '/'), $source, $third),
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

    /** RedirectMatch / RewriteRule / RewriteCond: a PCRE against the subject, $N of the target replaced. */
    private static function regexMatch(string $subject, string $regex, string $target, bool $noCase = false): ?string
    {
        if ($regex === '' || @preg_match('#'.str_replace('#', '\#', $regex).'#'.($noCase ? 'i' : ''), $subject, $m) !== 1) {
            return null;
        }

        return (string) preg_replace_callback('/\$(\d)/', fn ($g) => $m[(int) $g[1]] ?? '', $target);
    }
}
