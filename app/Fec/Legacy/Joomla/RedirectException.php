<?php

namespace App\Fec\Legacy\Joomla;

use Error;

/**
 * Raised by JApplication::redirect(); the runner answers with the HTTP redirect.
 *
 * An Error, not an Exception: on the live site redirect() sent the header and
 * closed the application, so a legacy "catch (Exception $e)" around it never
 * saw anything (the blog listing's try/catch swallowed the guard's 301 of
 * /blog/feed/rss and printed "Blog temporarily unavailable"). PHP 5.6 code
 * cannot catch an Error; only the runner's class-specific catch does.
 */
final class RedirectException extends Error
{
    public function __construct(public readonly string $url, public readonly int $status = 303)
    {
        parent::__construct('redirect to '.$url);
    }
}
