<?php

namespace App\Fec\Legacy\Joomla;

use App\Fec\Legacy\Sef\Sh404sef;
use App\Fec\Legacy\Sh404sefMetas;
use App\Fec\Url\UrlRegistry;
use Throwable;

/**
 * JRoute::_() the way sh404SEF answered it on the live site: a Joomla query
 * ("index.php?option=...") becomes the SEF path stored in jos_sh404sef_urls
 * ("/authors/davey"), site-relative with a leading slash. Lookup order:
 * sh404SEF's own (the query with "lang=en", sorted: the stored row of that
 * newurl of any rank), exact newurl, tolerant (parameter order, lang, Itemid,
 * "id:alias"), then
 * the port of the component's sh404SEF plugin (App\Fec\Legacy\Sef, which also
 * records the new URL in the registry), then the ported component router
 * (<Com>BuildRoute), then the raw query as a last resort. Anything that does
 * not start with index.php is returned untouched, as JRoute does.
 */
class Route
{
    /** @var array<string, string> per-request memo: query => path */
    private static array $memo = [];

    public static function reset(): void
    {
        self::$memo = [];
    }

    public static function _(string|array|null $url, bool $xhtml = true, ?int $ssl = null): string
    {
        if (is_array($url)) {
            $url = 'index.php?'.http_build_query($url);
        }
        $url = (string) $url;
        if ($url === '' || (! str_starts_with($url, 'index.php') && ! str_starts_with($url, '&'))) {
            return $url;
        }
        if (str_starts_with($url, '&')) {
            $url = 'index.php?'.ltrim($url, '&');
        }

        $path = self::$memo[$url] ??= self::resolve($url);
        // JRoute::_() replaced the spaces a decoded query string left
        $path = preg_replace('/\s/u', '%20', $path) ?? $path;

        return $xhtml ? str_replace('&', '&amp;', $path) : $path;
    }

    private static function resolve(string $url): string
    {
        $query = UrlRegistry::parseQuery($url);
        $fragment = (string) (parse_url($url, PHP_URL_FRAGMENT) ?? '');
        $suffix = $fragment !== '' ? '#'.$fragment : '';

        // JRouterSite::_createURI(): an Itemid without an option stands for that menu
        // item's link (setQuery($item->query), then setVar('Itemid')), as mod_menu's
        // "index.php?Itemid=N" links are built in SEF mode
        if (! isset($query['option']) && isset($query['Itemid']) && is_numeric($query['Itemid'])) {
            $item = Factory::getApplication()->getMenu()->getItem((int) $query['Itemid']);
            if ($item !== null && ($item->query['option'] ?? '') !== '') {
                $query = array_merge($item->query, ['Itemid' => (string) (int) $query['Itemid']]);
                $url = 'index.php?'.http_build_query($query);
            }
        }

        // com_content links carry Joomla's "id:alias" slugs (ContentHelperRoute builds
        // them, the blog and the reviews link that way); sh404SEF stored the numeric
        // ids, and so does the registry.
        if (($query['option'] ?? '') === 'com_content') {
            $changed = false;
            foreach (['id', 'catid'] as $k) {
                if (isset($query[$k]) && preg_match('/^(\d+):/', (string) $query[$k], $m)) {
                    $query[$k] = $m[1];
                    $changed = true;
                }
            }
            if ($changed) {
                $url = 'index.php?'.http_build_query($query);
            }
        }

        try {
            $registry = app(UrlRegistry::class);
            // sh404SEF's own lookup first: the query with "lang=en", sorted by shSortUrl(),
            // the stored row of that newurl whatever its rank; then the tolerant match
            $path = $registry->storedPath(Sh404sefMetas::sortUrl(self::withLang($url)))
                ?? $registry->pathFor($url, true);
            if ($path !== null) {
                return $path.$suffix;
            }
        } catch (Throwable $e) {
            error_log('legacy JRoute registry lookup failed: '.$e->getMessage());
        }

        // a URL the table never stored: the sh404SEF plugin of the component, ported, builds
        // it and records it, as sh404SEF did the first time a page linked it
        $sef = Sh404sef::build($url, $query);
        if ($sef !== null) {
            if (config('fec.legacy.sef_register', false)) {
                try {
                    Sh404sef::register($sef);
                } catch (Throwable $e) {
                    error_log('legacy JRoute: registering '.$sef->path.' failed: '.$e->getMessage());
                }
            }

            return '/'.$sef->path.$suffix;
        }

        $built = self::buildWithComponentRouter($query);
        if ($built !== null) {
            return $built.$suffix;
        }

        return '/index.php?'.self::buildQuery($query).$suffix;
    }

    /** shSefRelToAbs(): "&lang=en" on a query without a language (before sorting it). */
    private static function withLang(string $url): string
    {
        $url = explode('#', str_replace('&amp;', '&', $url), 2)[0];
        if (preg_match('/(&|\?)lang=[^&]*/i', $url)) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'lang=en';
    }

    /**
     * JURI::buildQuery() of Joomla 2.5, through which every query string of a
     * routed URL went: http_build_query() decoded again ("filters=a,b", not
     * "a%2Cb"; a space stays a space and JRoute::_() then writes it "%20").
     *
     * @param  array<string, mixed>  $vars
     */
    public static function buildQuery(array $vars): string
    {
        return urldecode(http_build_query($vars, '', '&'));
    }

    /**
     * The component's own router.php (ported copy under JPATH_SITE), as
     * Joomla's non-sh404SEF routing would use it: segments from BuildRoute,
     * leftover variables as a query string.
     *
     * @param  array<string, string>  $query
     */
    private static function buildWithComponentRouter(array $query): ?string
    {
        $option = $query['option'] ?? '';
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            return null;
        }
        $file = Runtime::sitePath().'/components/'.$option.'/router.php';
        if (! is_file($file)) {
            return null;
        }
        $function = ucfirst(substr($option, 4)).'BuildRoute';
        if (! function_exists($function)) {
            require_once $file;
        }
        if (! function_exists($function)) {
            return null;
        }

        $vars = $query;
        unset($vars['option'], $vars['Itemid'], $vars['lang']);
        $segments = (array) $function($vars);
        if ($segments === []) {
            return self::defaultSef($option, $vars);
        }
        $path = '/'.implode('/', array_map('rawurlencode', array_map('strval', $segments)));
        if ($vars !== []) {
            $path .= '?'.self::buildQuery($vars);
        }

        return $path;
    }

    /**
     * What sh404SEF's default plugin made of a query no component plugin and no
     * router segment could name: the component's name as the path and the
     * variables it did not consume as the query string, in alphabetical order
     * (the FXShield report link of the reviews on the live site:
     * /authors?casino_id=910&tmpl=component&view=fxreport). Never stored.
     *
     * @param  array<string, string>  $vars
     */
    private static function defaultSef(string $option, array $vars): string
    {
        ksort($vars, SORT_STRING);
        $path = '/'.substr($option, 4);

        return $vars !== [] ? $path.'?'.self::buildQuery($vars) : $path;
    }
}
