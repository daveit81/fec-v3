<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\Document;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The "Title and metas" of sh404SEF (jos_sh404sef_metas), as the live site
 * applies them.
 *
 * The redazione set them per page in sh404SEF's back end, years before the
 * meta_overrides_*.php files of com_bonuses: 810 rows, 49 for com_bonuses
 * URLs (27 Oct 2026), none for com_authors. sh404SEF applies them on the
 * finished page, after the component and the template: a non-empty title,
 * description, keywords or robots replaces the page's own, a non-empty
 * canonical replaces the canonical link. The row is the one whose newurl is
 * the page's current non-SEF URL as sh404SEF's router left it: the newurl of
 * the registry row the requested path resolved to (rank 0) with the request's
 * own query variables on top, Google's tracking variables stripped, sorted
 * (lookupKey()). A request with a query string of its own ("?sort=newest",
 * "?start=50") therefore finds no row and keeps the page's computed title, as
 * on the live site; a row keyed on another spelling of the same query never
 * applies (17 of the 49 on the live site). The Open Graph fields of the table
 * are left to the lib's own social metas (og_enable 2 = sh404SEF's global
 * setting, off).
 */
final class Sh404sefMetas
{
    /**
     * Sh404sefConfiguration::$_trackingVars: stripped from the current non-SEF
     * URL before the lookup ("would prevent us to find the correct meta tags").
     * fbclid is not among them.
     */
    public const TRACKING_VARS = ['utm_source', 'utm_medium', 'utm_term', 'utm_content', 'utm_id', 'utm_campaign', 'gclid', 'fb_xd_bust', 'fb_xd_fragment', 'hitcount'];

    /** @param  array<string, mixed>  $request  the request's query variables */
    public static function apply(Document $doc, ?string $newurl, array $request = []): void
    {
        $row = self::row(self::lookupKey($newurl, $request));
        if ($row === null) {
            return;
        }

        if (trim((string) $row->metatitle) !== '') {
            $doc->setTitle((string) $row->metatitle);
        }
        if (trim((string) $row->metadesc) !== '') {
            $doc->setDescription((string) $row->metadesc);
        }
        if (trim((string) $row->metakey) !== '') {
            $doc->setMetaData('keywords', (string) $row->metakey);
        }
        if (trim((string) $row->metarobots) !== '') {
            $doc->setMetaData('robots', (string) $row->metarobots);
        }
        if (trim((string) $row->canonical) !== '') {
            $doc->headLinks = array_values(array_filter($doc->headLinks, fn ($l) => $l['relation'] !== 'canonical'));
            $doc->addHeadLink(trim((string) $row->canonical), 'canonical');
        }
    }

    /**
     * The row of the page's own non-SEF URL, if the redazione set one
     * (metatitle, metadesc, metakey, metarobots, canonical).
     */
    public static function row(?string $newurl): ?object
    {
        $newurl = trim((string) $newurl);
        if ($newurl === '') {
            return null;
        }

        try {
            return DB::connection('fec')->table('sh404sef_metas')
                ->where('newurl', $newurl)
                ->orderBy('id')
                ->first(['metatitle', 'metadesc', 'metakey', 'metarobots', 'canonical']);
        } catch (Throwable $e) {
            error_log('sh404sef metas lookup failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Sh404sefFactory::getPageInfo()->currentNonSefUrl as the router stored it
     * (classes/router.php, parseRule): the variables decoded from the SEF URL
     * (the registry row's newurl) merged with the request's query string,
     * rebuilt by JURI ("index.php?" and J2.5's JURI::buildQuery, which decodes
     * the values). On the home with a query string the router passed the
     * request's variables through alone, without the home's (Joomla then ran
     * the default menu item): "/?utm_source=x" is "index.php?utm_source=x".
     * The registry's own spelling when the request adds nothing to it but
     * tracking variables, which every use strips.
     *
     * @param  array<string, mixed>  $request  the request's query variables
     */
    public static function currentNonSefUrl(?string $newurl, array $request, bool $homePath = false): ?string
    {
        $newurl = trim((string) $newurl);
        if ($newurl === '') {
            return null;
        }
        if ($homePath && $request !== []) {
            return 'index.php?'.urldecode(http_build_query($request, '', '&'));
        }

        parse_str((string) (parse_url($newurl, PHP_URL_QUERY) ?? ''), $stored);
        $tracking = array_flip(self::TRACKING_VARS);
        $own = array_filter($request, fn ($k) => ! isset($tracking[strtolower((string) $k)]), ARRAY_FILTER_USE_KEY);
        if (array_merge($stored, $own) === $stored) {
            return $newurl;
        }

        return 'index.php?'.urldecode(http_build_query(array_merge($stored, $request), '', '&'));
    }

    /**
     * The key of the row (shDoTitleTags + shGetCurrentNonSef): the current
     * non-SEF URL with "&lang=en" when it has no language, the tracking
     * variables stripped, sorted by shSortUrl().
     *
     * @param  array<string, mixed>  $request
     */
    public static function lookupKey(?string $newurl, array $request = [], bool $homePath = false): ?string
    {
        $current = self::currentNonSefUrl($newurl, $request, $homePath);
        if ($current === null || $current === trim((string) $newurl)) {
            return $current;
        }
        if (! preg_match('/(&|\?)lang=[a-zA-Z]{2,3}/iuU', $current)) {
            $current .= '&lang=en';
        }

        return self::sortUrl(self::stripTracking($current));
    }

    /** Sh404sefHelperGeneral::stripTrackingVarsFromNonSef(): shCleanUpVar() for each. */
    public static function stripTracking(string $url): string
    {
        foreach (self::TRACKING_VARS as $var) {
            $url = (string) (preg_replace('/(&|\?)'.preg_quote($var, '/').'=[^&]*/iu', '', $url) ?? $url);
        }

        return $url;
    }

    /**
     * shSortURL(): "option" first, the other variables by name, values
     * urlencoded; anything that does not start with "index.php?" untouched
     * (a URL whose first variable was stripped, "index.php&lang=en", stays so).
     */
    public static function sortUrl(string $string): string
    {
        if (substr($string, 0, 10) !== 'index.php?') {
            return $string;
        }
        $st = str_replace('&amp;', '&', $string);
        $st = str_replace('index.php', '', $st);
        $st = str_replace('?', '', $st);
        parse_str($st, $tmp);
        $vars = self::urlencodeDeep($tmp);
        if (count($vars) === 0) {
            return '';
        }
        ksort($vars);
        $ret = 'index.php?';
        $rest = '';
        foreach ($vars as $key => $value) {
            if (strtolower((string) $key) !== 'option') {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $rest .= '&'.$key.'['.$k.']='.(is_array($v) ? 'Array' : $v);
                    }
                } else {
                    $rest .= '&'.$key.'='.$value;
                }
            } else {
                $ret .= $key.'='.(is_array($value) ? 'Array' : $value);
            }
        }

        return $ret.($ret === 'index.php?' ? ltrim($rest, '&') : $rest);
    }

    private static function urlencodeDeep(mixed $data): mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => $element) {
                $data[$key] = self::urlencodeDeep($element);
            }

            return $data;
        }

        return urlencode((string) $data);
    }
}
