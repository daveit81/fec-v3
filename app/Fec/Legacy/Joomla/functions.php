<?php

/*
| Global functions a Joomla 2.5 component expects to exist. Loaded by
| Runtime::boot(), never by Composer, so nothing here runs outside a
| legacy dispatch.
*/

if (! function_exists('jimport')) {
    /** Joomla's library loader: every class it would load is provided by the compatibility layer. */
    function jimport(string $path): bool
    {
        return true;
    }
}

if (! function_exists('jexit')) {
    /** Joomla's exit wrapper: in v3 a component must never terminate the request. */
    function jexit(string $message = ''): never
    {
        throw new \App\Fec\Legacy\Joomla\ExitException($message);
    }
}
