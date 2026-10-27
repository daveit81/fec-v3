<?php

namespace App\Fec\Url;

use App\Fec\Url\Patterns\BonusDetails;

/**
 * The v3 router of the convivenza: one public path in, one Decision out.
 *
 *   0. .htaccess rules      the legacy redirects, 410s and 403s under our paths (the web server answered first)
 *   0b. jos_sh404sef_aliases sh404SEF's aliases, before its own URLs, Location as written
 *   1. jos_sh404sef_urls    canonical page, duplicate of a page, slash spelling, recorded 404
 *   2. run-time patterns    URL families sh404SEF never stored (bonus details)
 *   3. jos_redirection      the desk's 301s: Joomla's redirect plugin only fired on a 404
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

    public function route(string $path, ?string $query = null): Decision
    {
        $path = UrlRegistry::normalize($path);

        // The legacy .htaccess rules under our paths (redirects, 410, 403): the web server
        // answered them before Joomla saw the request, so they come before everything here.
        if ($rule = HtaccessRedirects::match($path, null, (string) $query)) {
            return match ($rule->kind) {
                'gone' => new Decision('gone', 410, $rule),
                'forbidden' => new Decision('forbidden', 403, $rule),
                default => new Decision('redirect', 301, $rule, $rule->target),
            };
        }

        // sh404SEF's aliases: checked before its own URLs (a path with an alias redirects
        // even when the registry holds it as a page), the Location as sh404SEF wrote it.
        if ($alias = Sh404sefAliases::match($path, $query)) {
            return new Decision('redirect', 301, $alias, $alias->target);
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

        if ($rule = $this->registry->redirectFor($path)) {
            return new Decision('redirect', 301, $rule, $rule->target);
        }

        return $this->decide($url);
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

    /** @return list<string> pattern names, for the probes. */
    public function patterns(): array
    {
        return array_map(fn (string $class) => app($class)->name(), self::PATTERNS);
    }
}
