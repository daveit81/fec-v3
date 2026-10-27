<?php

use App\Fec\Legacy\Joomla\ExitException;
use App\Fec\Legacy\Php5Sort;

/*
| Global functions a Joomla 2.5 component expects to exist. Loaded by
| Runtime::boot(), never by Composer, so nothing here runs outside a
| legacy dispatch.
*/

// JRequest::getVar() masks (libraries/joomla/environment/request.php).
if (! defined('JREQUEST_NOTRIM')) {
    define('JREQUEST_NOTRIM', 1);
}
if (! defined('JREQUEST_ALLOWRAW')) {
    define('JREQUEST_ALLOWRAW', 2);
}
if (! defined('JREQUEST_ALLOWHTML')) {
    define('JREQUEST_ALLOWHTML', 4);
}

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
        throw new ExitException($message);
    }
}

/*
| The sorts of PHP 5.6 (App\Fec\Legacy\Php5Sort): the port scripts rewrite every
| usort/uasort/asort/arsort of the ported code to these, so that elements the
| comparator cannot tell apart come out in the order the live site shows them.
*/
if (! function_exists('fec_php5_usort')) {
    function fec_php5_usort(array &$array, callable $compare): bool
    {
        return Php5Sort::usort($array, $compare);
    }
}
if (! function_exists('fec_php5_uasort')) {
    function fec_php5_uasort(array &$array, callable $compare): bool
    {
        return Php5Sort::uasort($array, $compare);
    }
}
if (! function_exists('fec_php5_asort')) {
    function fec_php5_asort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return Php5Sort::asort($array, $flags);
    }
}
if (! function_exists('fec_php5_arsort')) {
    function fec_php5_arsort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return Php5Sort::arsort($array, $flags);
    }
}
