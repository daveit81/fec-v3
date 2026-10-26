<?php

namespace App\Fec\Legacy\Joomla;

use RuntimeException;

/** Raised by JApplication::redirect(); the runner answers with the HTTP redirect. */
final class RedirectException extends RuntimeException
{
    public function __construct(public readonly string $url, public readonly int $status = 303)
    {
        parent::__construct('redirect to '.$url);
    }
}
