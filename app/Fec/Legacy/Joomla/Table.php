<?php

namespace App\Fec\Legacy\Joomla;

use RuntimeException;

/**
 * JTable: only the Content table is asked for (the blog desk saves posts
 * through it, with Joomla's asset bookkeeping). Not ported yet: the call
 * fails loudly with a 501 instead of writing half a row.
 */
class Table
{
    public static function getInstance(string $type, string $prefix = 'JTable', array $config = []): never
    {
        throw new RuntimeException('JTable '.$type.' is not ported yet: this desk still saves on Joomla', 501);
    }

    public static function addIncludePath(mixed $path = null): array
    {
        return [];
    }
}
