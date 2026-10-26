<?php

namespace App\Fec\Legacy\Joomla;

/** JCache: the component asked for a cache once; a no-op keeps the page uncached, as on the live site. */
class Cache
{
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

    public function clean(mixed $group = null, string $mode = 'group'): bool
    {
        return true;
    }
}
