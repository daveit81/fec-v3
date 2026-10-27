<?php

namespace App\Fec\Legacy\Sef;

/** A SEF path built by a plugin port, with the non-SEF URL sh404SEF stores for it. */
final class BuiltUrl
{
    public function __construct(
        /** the path as the registry stores it: no leading slash, trailing slash kept ("bonuses/yachting-casino/") */
        public readonly string $path,
        /** "index.php?option=...&lang=en" */
        public readonly string $nonSef,
    ) {}
}
