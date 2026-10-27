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
 *    row), absolute and without the query string; with no stored URL, the home
 *    page (slots/ -> com_content category 139, which has none);
 *  - when that SEF URL is the requested path itself and the request carries no
 *    query string (bonuses/dictionary/, bonuses/exclusive/yes/), there is no
 *    redirect: the page is served;
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
        $candidates = [$path];
        if ($query !== '') {
            $candidates[] = $path.'?'.$query;
        }

        try {
            $row = DB::connection('fec')->table('sh404sef_aliases')
                ->whereIn('alias', $candidates)
                ->where('type', 0)
                ->orderBy('id')
                ->first(['id', 'alias', 'newurl']);
        } catch (Throwable $e) {
            error_log('sh404sef aliases lookup failed: '.$e->getMessage());

            return null;
        }
        if ($row === null) {
            return null;
        }

        $target = self::target((string) $row->newurl, $path, $query);
        if ($target === null) {
            return null;
        }

        return new ResolvedUrl('redirect', $path, [], null, $target, (int) $row->id, null, (string) $row->newurl, 'sh404sef-alias', ['raw_location' => true]);
    }

    /** The Location of an alias, or null when the alias points at the requested page itself. */
    public static function target(string $newurl, string $path, string $query = '', ?callable $sefFor = null): ?string
    {
        $newurl = trim($newurl);
        if ($newurl === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $newurl)) {
            return $newurl;
        }

        if (! str_starts_with($newurl, 'index.php')) {
            // a SEF path: as written, relative, the request's query string kept
            return $newurl.($query !== '' ? '?'.$query : '');
        }

        $sef = ($sefFor ?? [self::class, 'sefFor'])($newurl);
        $sef = ltrim((string) $sef, '/');
        if ($sef === $path && $query === '') {
            return null;
        }

        return self::SITE.'/'.$sef;
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
