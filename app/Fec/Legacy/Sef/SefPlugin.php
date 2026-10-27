<?php

namespace App\Fec\Legacy\Sef;

/**
 * The port of one sh404SEF component plugin (legacy/components/<option>/sef_ext/<option>.php).
 */
interface SefPlugin
{
    /**
     * The title segments the plugin hands to sefGetLocation() for these query
     * variables, or null when it leaves the URL non-SEF ($dosef = false). The
     * variables are passed by reference because a plugin may rewrite them
     * (what sh404SEF then stores as the non-SEF URL).
     *
     * @param  array<string, string>  $vars
     * @return list<string>|null
     */
    public static function title(array &$vars): ?array;
}
