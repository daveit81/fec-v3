<?php

namespace App\Fec\Url;

use App\Fec\Legacy\Sef\Sh404sef;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * sh404SEF's aliases (jos_sh404sef_aliases), answered as the live site answers them.
 *
 * 721 rows on 27 Oct 2026, most of them old .html paths at the root (com_content,
 * jReviews); 27 under the paths v3 serves or will serve. The behaviour, read off
 * the live site path by path:
 *
 *  - an alias is checked before sh404SEF's own URLs (bonuses/video-poker-bonuses
 *    has a canonical row and still redirects), after the .htaccess rules;
 *  - a target that is a path ("bonuses/", "bonuses/mandarin-palace/") is sent as
 *    the Location exactly as written, relative, with the request's query string
 *    appended ("bonuses/?utm_source=test"): browsers resolve it against the
 *    requested path, which is why the table also holds "bonuses/bonuses/";
 *  - a target "index.php?..." becomes the SEF URL stored for that exact newurl,
 *    whatever its rank (bonuses/bonuses/ -> bonuses/slots-bonuses, a rank 2
 *    row), absolute; with no stored URL, the home page (slots/ -> com_content
 *    category 139, which has none). The request's query string went into the
 *    non-SEF URL (classes/router.php, _checkAliases) and sh404SEF routed it
 *    (shCheckRedirect): com_bonuses' plugin (sefGetLocation) drops the
 *    variables it does not encode, so "bonuses/dictionary/?utm_source=test"
 *    goes to the bare page; sh404SEF's own com_content plugin appends them,
 *    sorted and decoded ("online-casinos?b=2&a=1" -> "online-casinos?a=1&b=2");
 *  - when that target is the requested URL itself, query string as sent
 *    included (bonuses/dictionary/, bonuses/exclusive/yes/,
 *    online-casinos?sort=newest), there is no redirect: the page is served;
 *  - the lookup (29 Oct 2026): the request as sent ("online-casinos?b=2&a=1")
 *    and, when it carries a query string, its path with and without the
 *    trailing slash ("online-casinos", "online-casinos/"), the alias sorting
 *    last first;
 *  - always 301; type 0 only (every row of the table).
 */
final class Sh404sefAliases
{
    public const SITE = 'https://www.freeextrachips.com';

    /** A redirect for the request, or null when no alias applies. */
    public static function match(string $path, ?string $query = null): ?ResolvedUrl
    {
        $path = UrlRegistry::normalize($path);
        $query = (string) $query;

        try {
            $row = DB::connection('fec')->table('sh404sef_aliases')
                ->whereIn('alias', self::candidates($path, $query))
                ->where('type', 0)
                ->orderBy('alias', 'desc')
                ->orderBy('id')
                ->first(['id', 'alias', 'newurl']);
        } catch (Throwable $e) {
            error_log('sh404sef aliases lookup failed: '.$e->getMessage());

            return null;
        }
        if ($row === null) {
            return null;
        }

        $target = self::target((string) $row->newurl, $path, $query, null, (string) $row->alias);
        if ($target === null) {
            return null;
        }

        return new ResolvedUrl('redirect', $path, [], null, $target, (int) $row->id, null, (string) $row->newurl, 'sh404sef-alias', ['raw_location' => true]);
    }

    /**
     * The aliases sh404SEF looked for (classes/router.php, _checkAliases): the
     * request as sent; with a query string, also its path, and the path with a
     * trailing slash when it has none.
     *
     * @return list<string>
     */
    public static function candidates(string $path, string $query = ''): array
    {
        if ($query === '') {
            return [$path];
        }
        $candidates = [$path.'?'.$query];
        if ($path !== '') {
            $candidates[] = $path;
            if (! str_ends_with($path, '/')) {
                $candidates[] = $path.'/';
            }
        }

        return $candidates;
    }

    /** The Location of an alias, or null when the alias points at the requested page itself. */
    public static function target(string $newurl, string $path, string $query = '', ?callable $sefFor = null, ?string $alias = null): ?string
    {
        $newurl = trim($newurl);
        if ($newurl === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $newurl)) {
            return $newurl;
        }

        if (! str_starts_with($newurl, 'index.php')) {
            // a SEF path: as written, relative, the request's query string kept (unless the
            // alias was spelled with it)
            return $newurl.($query !== '' && $alias !== $path.'?'.$query ? '?'.$query : '');
        }

        $sef = ($sefFor ?? [self::class, 'sefFor'])($newurl);
        $sef = ltrim((string) $sef, '/');

        // the request's variables the target's plugin keeps (an alias spelled with the query
        // string itself got none: sh404SEF appended the query only to an alias of the path)
        $leftover = [];
        if ($query !== '' && $alias !== $path.'?'.$query && str_contains($newurl, 'option=com_content')) {
            parse_str($query, $requested);
            parse_str((string) (parse_url($newurl, PHP_URL_QUERY) ?? ''), $own);
            $leftover = array_diff_key($requested, $own, array_flip(['option', 'Itemid', 'lang', 'limit', 'limitstart']));
            ksort($leftover);
        }
        $target = self::SITE.'/'.$sef.($leftover !== [] ? '?'.urldecode(http_build_query($leftover, '', '&')) : '');

        // the requested URL itself (shCheckRedirect compared it with the original URI)
        $incoming = self::SITE.'/'.$path.($query !== '' ? '?'.$query : '');

        return $target === $incoming ? null : $target;
    }

    /** The SEF URL stored for a non-SEF URL, any rank (as sh404SEF looked it up), else the plugin port's. */
    public static function sefFor(string $newurl): string
    {
        $old = DB::connection('fec')->table('sh404sef_urls')
            ->where('newurl', $newurl)
            ->orderBy('rank')
            ->orderBy('id')
            ->value('oldurl');
        if ($old !== null) {
            return (string) $old;
        }

        $built = Sh404sef::build($newurl, UrlRegistry::parseQuery($newurl));

        return $built?->path ?? '';
    }
}
