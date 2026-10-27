<?php

namespace App\Fec\Legacy;

use Illuminate\Support\Facades\Log;

/**
 * PHP 5.6 code under PHP 8: notices and warnings the legacy code always
 * produced (an undefined index, a missing variable, a deprecated call) must
 * not become exceptions, or every such page is a 500 that Joomla never
 * showed. While a component runs, they are collected instead: the first
 * occurrence of each (file:line) goes to storage/logs/legacy-notices.log
 * with the page key, the page renders as on the live site. Fatal errors
 * and exceptions still fail the request.
 */
final class LegacyErrors
{
    private const SOFT = E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED | E_STRICT;

    private static ?string $page = null;

    /** @var array<string, int> "file:line" => count, per request */
    private static array $seen = [];

    private static int $logged = 0;

    public static function begin(string $page): void
    {
        self::$page = $page;
        self::$seen = [];
        self::$logged = 0;
        set_error_handler([self::class, 'handle']);
    }

    public static function end(): void
    {
        if (self::$page !== null) {
            restore_error_handler();
            self::$page = null;
        }
    }

    public static function handle(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (! ($errno & self::SOFT) || ! (error_reporting() & $errno)) {
            return false; // not ours (or silenced with @): PHP's default, Laravel's handler
        }
        $key = $errfile.':'.$errline;
        self::$seen[$key] = (self::$seen[$key] ?? 0) + 1;
        if (self::$seen[$key] === 1 && self::$logged < 50) {
            self::$logged++;
            try {
                Log::build(['driver' => 'single', 'path' => storage_path('logs/legacy-notices.log'), 'level' => 'debug'])
                    ->debug(sprintf('[%s] %s: %s (%s:%d)', self::$page, self::label($errno), $errstr, self::short($errfile), $errline));
            } catch (\Throwable) {
                // logging must never fail the page
            }
        }

        return true;
    }

    /** @return array<string, int> */
    public static function seen(): array
    {
        return self::$seen;
    }

    private static function label(int $errno): string
    {
        return match ($errno) {
            E_WARNING, E_USER_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'deprecated',
            default => 'strict',
        };
    }

    private static function short(string $file): string
    {
        $base = base_path().'/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
