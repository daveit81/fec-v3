<?php

use App\Fec\Legacy\Joomla\ExitException;
use App\Fec\Legacy\Joomla\Factory;
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

/*
| header() of the ported code (rewritten to this by the port scripts): the header
| goes on the Joomla application object, the one place the runner builds the
| response from, whatever the SAPI (the CLI keeps no header list, so fec:parity
| never saw a header() the tc and ads views set). A status line ("HTTP/1.1 404
| Not Found") or an explicit response code sets the status; a Location header
| makes the response a 302 unless a 3xx status is set, as PHP does.
*/
if (! function_exists('fec_header')) {
    function fec_header(string $header, bool $replace = true, int $response_code = 0): void
    {
        $header = trim($header);
        if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#i', $header, $m)) {
            http_response_code((int) $m[1]);
        } elseif (str_contains($header, ':')) {
            [$name, $value] = explode(':', $header, 2);
            $app = Factory::getApplication();
            $name = trim($name);
            $value = trim($value);
            if (! $replace) {
                foreach ($app->getHeaders() as $k => $v) {
                    if (strcasecmp($k, $name) === 0 && $v !== '') {
                        $value = $v.', '.$value;
                        $name = $k;
                        break;
                    }
                }
            }
            $app->setHeader($name, $value);
        }
        if ($response_code > 0) {
            http_response_code($response_code);
        }
    }
}
