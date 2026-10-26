<?php

namespace App\Fec\Url;

/**
 * Which v3 handler renders which legacy view. The key is "option.view" (view
 * falls back to task, then "default"); a missing key means "not ported yet",
 * and the front controller answers 404 for it (on the live hostname such a
 * path is never routed to v3 anyway: the legacy .htaccess sends only the
 * switched paths here).
 *
 * The map grows one line per switched view, with the parity script green:
 *   'com_authors.team' => \App\Http\Controllers\Authors\TeamController::class,
 */
final class Dispatcher
{
    /** @var array<string, class-string<PageHandler>> */
    public const HANDLERS = [
        // phase 2: com_authors, view by view as parity goes green (fec:parity)
        'com_authors.authors' => \App\Fec\Legacy\ComponentRunner::class,
        // phase 3: com_bonuses.*  phase 4: com_content.*, com_atlas.*
    ];

    /** @return class-string<PageHandler>|null */
    public function handlerFor(ResolvedUrl $url): ?string
    {
        if (! $url->isPage()) {
            return null;
        }

        $key = $url->routeKey();

        return $key !== null ? (self::HANDLERS[$key] ?? null) : null;
    }

    public function isPorted(ResolvedUrl $url): bool
    {
        return $this->handlerFor($url) !== null;
    }

    /** @return list<string> the "option.view" keys served by v3, for the probes. */
    public function portedKeys(): array
    {
        return array_keys(self::HANDLERS);
    }
}
