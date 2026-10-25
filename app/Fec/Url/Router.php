<?php

namespace App\Fec\Url;

use App\Fec\Url\Patterns\BonusDetails;

/**
 * The v3 router of the convivenza: one public path in, one Decision out.
 *
 *   1. jos_redirection      the desk's deliberate 301s win over everything
 *   2. run-time patterns    URL families sh404SEF never stored (bonus details)
 *   3. jos_sh404sef_urls    canonical page, alias of a page, recorded 404
 *
 * Aliases (rank > 0) are answered with a 301 to the canonical spelling when
 * fec.router.alias_redirect is true (default); set it to false to serve them
 * as pages, exactly as a Joomla without "redirect duplicates" would. Verify
 * the legacy behaviour on an alias before switching a section that has them.
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
    ) {
    }

    public function route(string $path): Decision
    {
        $path = UrlRegistry::normalize($path);

        if ($rule = $this->registry->redirectFor($path)) {
            return new Decision('redirect', 301, $rule, $rule->target);
        }

        foreach (self::PATTERNS as $class) {
            /** @var UrlPattern $pattern */
            $pattern = app($class);
            if ($hit = $pattern->match($path, $this->registry)) {
                return $this->decide($hit);
            }
        }

        return $this->decide($this->registry->lookup($path));
    }

    private function decide(ResolvedUrl $url): Decision
    {
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
