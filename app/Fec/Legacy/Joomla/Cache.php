<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JCache: v3 caches nothing (the live site serves these pages uncached, see
 * fec:parity). clean() still matters during the convivenza: a desk that saves
 * on v3 must drop Joomla's own file cache of the group (public_html/cache/
 * <group>/), or www keeps showing the old article until it expires.
 */
class Cache
{
    public function __construct(private readonly string $group = '')
    {
    }

    public function get(mixed $id, mixed $group = null): mixed
    {
        return false;
    }

    public function store(mixed $data, mixed $id, mixed $group = null): bool
    {
        return true;
    }

    public function call(mixed ...$args): mixed
    {
        $callback = array_shift($args);

        return is_callable($callback) ? $callback(...$args) : null;
    }

    public function setCaching(bool $enabled): void
    {
    }

    /** Remove Joomla's cached files of the group (the group given, or the one the cache was made for). */
    public function clean(mixed $group = null, string $mode = 'group'): bool
    {
        $group = trim((string) ($group ?? $this->group));
        if ($group === '' || ! preg_match('/^[a-z0-9_.-]+$/i', $group)) {
            return true;
        }
        $dir = Runtime::docroot().'/cache/'.$group;
        if (! is_dir($dir)) {
            return true;
        }
        foreach (glob($dir.'/*.php') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }
}
