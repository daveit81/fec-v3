<?php

namespace App\Fec\Url;

/**
 * A URL family that the legacy site builds at run time and sh404SEF never
 * stored (so the registry cannot answer). Each pattern inspects the path and
 * either returns a ResolvedUrl (page or miss, source "pattern:<name>") or null
 * to let the next pattern, then the registry, try.
 */
interface UrlPattern
{
    public function name(): string;

    public function match(string $path, UrlRegistry $registry): ?ResolvedUrl;
}
