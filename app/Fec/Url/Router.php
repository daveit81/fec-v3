<?php

namespace App\Fec\Url;

use App\Fec\Url\Patterns\BonusDetails;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The v3 router of the convivenza: one public path in, one Decision out.
 *
 *   0. .htaccess rules      the legacy redirects, 410s and 403s under our paths (the web server answered first)
 *   0b. jos_sh404sef_aliases sh404SEF's aliases, before its own URLs, Location as written
 *   0c. the home            "/" is the home menu item (jos_menu home = 1), never a row of the table
 *   1. jos_sh404sef_urls    canonical page, duplicate of a page, slash spelling, recorded 404
 *   2. run-time patterns    URL families sh404SEF never stored (bonus details)
 *   3. jos_redirection      off by default (fec.router.legacy_redirection): the table of sh404SEF 1.x,
 *                           read by no plugin of Joomla 2.5 (shsef and jredirect are J1.5 files the
 *                           2.5 loader never includes); on 29 Oct 2026 the live site answered 404
 *                           where v3 answered its 301s (/strategies/, /strategies/blackjack/)
 *
 * Aliases (rank > 0) are served as pages, as the live Joomla does (checked
 * 25 Oct 2026: 200 with a self-referencing canonical); fec.router.alias_redirect
 * = true answers 301 to the rank-0 spelling instead, a phase 6 option.
 */
final class Router
{
    /** @var list<class-string<UrlPattern>> in matching order */
    private const PATTERNS = [
        BonusDetails::class,
    ];

    public function __construct(
        private readonly UrlRegistry $registry,
        private readonly Dispatcher $dispatcher,
    ) {}

    public function route(string $path, ?string $query = null, ?string $userAgent = null): Decision
    {
        $path = UrlRegistry::normalize($path);

        // The legacy .htaccess rules under our paths (redirects, 410, 403): the web server
        // answered them before Joomla saw the request, so they come before everything here.
        if ($rule = HtaccessRedirects::match($path, null, (string) $query, (string) $userAgent)) {
            return match ($rule->kind) {
                'gone' => new Decision('gone', 410, $rule),
                'forbidden' => new Decision('forbidden', 403, $rule),
                default => new Decision('redirect', 301, $rule, $rule->target),
            };
        }

        // What sh404SEF looked up had no encoded "#": the path ends there.
        $path = self::withoutEncodedFragment($path);

        // sh404SEF's aliases: checked before its own URLs (a path with an alias redirects
        // even when the registry holds it as a page), the Location as sh404SEF wrote it.
        if ($alias = Sh404sefAliases::match($path, $query)) {
            return new Decision('redirect', 301, $alias, $alias->target);
        }

        // The home: sh404SEF answered an empty path with the home menu item's link.
        if ($path === '' && ($home = $this->home()) !== null) {
            return $this->decide($home);
        }

        // The SEF registry first: a URL sh404SEF knows is served (or redirected to its
        // stored spelling). Joomla's redirect plugin only ever fired on a 404, so the
        // jos_redirection rules apply to paths the registry and the patterns do not know.
        $url = $this->registry->lookup($path);
        if (in_array($url->kind, ['page', 'alias', 'redirect'], true)) {
            return $this->decide($url);
        }

        foreach (self::PATTERNS as $class) {
            /** @var UrlPattern $pattern */
            $pattern = app($class);
            if ($hit = $pattern->match($path, $this->registry)) {
                return $this->decide($hit);
            }
        }

        if (config('fec.router.legacy_redirection', false) && ($rule = $this->registry->redirectFor($path))) {
            return new Decision('redirect', 301, $rule, $rule->target);
        }

        return $this->decide($url);
    }

    /**
     * The home as Joomla and sh404SEF served it: the query of the home menu
     * item's link with its Itemid (and the language sh404SEF adds), flagged
     * for the runner, which prints the template's home instead of the
     * component's body (App\Fec\Legacy\HomeTemplate). Null when the menu
     * has no published home item.
     */
    public function home(): ?ResolvedUrl
    {
        try {
            $item = DB::connection('fec')->table('menu')
                ->where('home', 1)->where('published', 1)->where('client_id', 0)
                ->orderBy('id')->first(['id', 'link']);
        } catch (Throwable $e) {
            error_log('router: home menu item lookup failed: '.$e->getMessage());

            return null;
        }
        if ($item === null) {
            return null;
        }
        $query = UrlRegistry::parseQuery((string) $item->link);
        if (($query['option'] ?? '') === '') {
            return null;
        }
        $query['Itemid'] = (string) $item->id;
        $query['lang'] ??= 'en';
        // sh404SEF's spelling of a non-SEF URL: option first, the rest in byte order
        $rest = $query;
        unset($rest['option']);
        ksort($rest, SORT_STRING);
        $newurl = 'index.php?option='.$query['option'].'&'.http_build_query($rest);

        return new ResolvedUrl('page', '', $query, '/', null, null, 0, $newurl, 'home', ['home' => true]);
    }

    private function decide(ResolvedUrl $url): Decision
    {
        if ($url->kind === 'redirect' && $url->target !== null) {
            return new Decision('redirect', 301, $url, $url->target);
        }
        if ($url->kind === 'alias') {
            if ($url->canonical !== null && (bool) config('fec.router.alias_redirect', true)) {
                return new Decision('redirect', 301, $url, $url->canonical);
            }
            // No canonical spelling recorded (or redirects disabled): serve it as the page it is.
            $url = new ResolvedUrl('page', $url->path, $url->query, '/'.$url->path, null, $url->registryId, $url->rank, $url->newurl, $url->source, $url->meta);
        }

        if ($url->isPage()) {
            $handler = $this->dispatcher->handlerFor($url);

            return $handler !== null
                ? new Decision('render', 200, $url, null, $handler)
                : new Decision('not_ported', 404, $url);
        }

        return new Decision('not_found', 404, $url);
    }

    /**
     * A path with an encoded "#" (a bad link copied with its anchor: "%23cd-search")
     * was looked up by sh404SEF without it: in the live access log of 14-29 Oct 2026
     * /online-casinos/tipbet-casino%23cd-search answers 200 (the review) and
     * /bonuses/free-spins%23cd-primary-nav 301 (the alias of bonuses/free-spins).
     * The web server's own rules saw the whole path (they run before this).
     */
    public static function withoutEncodedFragment(string $path): string
    {
        $cut = stripos($path, '%23');

        return $cut === false ? $path : substr($path, 0, $cut);
    }

    /** @return list<string> pattern names, for the probes. */
    public function patterns(): array
    {
        return array_map(fn (string $class) => app($class)->name(), self::PATTERNS);
    }
}
