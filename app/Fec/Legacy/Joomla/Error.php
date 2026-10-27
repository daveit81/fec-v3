<?php

namespace App\Fec\Legacy\Joomla;

use RuntimeException;

/**
 * JError as Joomla 2.5 behaved for our code: raiseWarning()/raiseNotice()
 * queue a message the page shows (the gated desk views do this and return
 * an empty page), raiseError() ends the request with Joomla's error page
 * (here an exception with the HTTP code, which the runner renders).
 */
class Error
{
    public static function raiseWarning(int|string $code, string $msg, mixed $info = null): Exception
    {
        Factory::getApplication()->enqueueMessage($msg, 'warning');

        return new Exception($msg, (int) $code);
    }

    public static function raiseNotice(int|string $code, string $msg, mixed $info = null): Exception
    {
        Factory::getApplication()->enqueueMessage($msg, 'notice');

        return new Exception($msg, (int) $code);
    }

    public static function raiseError(int|string $code, string $msg, mixed $info = null): never
    {
        $code = (int) $code;
        throw new RuntimeException($msg, $code >= 400 && $code < 600 ? $code : 500);
    }

    public static function isError(mixed $object): bool
    {
        return $object instanceof \Throwable;
    }

    public static function throwError(mixed $exception): never
    {
        throw $exception instanceof \Throwable ? $exception : new RuntimeException((string) $exception, 500);
    }
}
