<?php

namespace App\Fec\Legacy\Joomla;

use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * JLog (Joomla 2.5): add() writes an entry only to the loggers that
 * addLogger() registered for its priority and category. The CMS registers
 * none for the categories our code uses ("newsletter"), so on the live site
 * those entries went nowhere: the same here unless addLogger() was called,
 * in which case they go to Laravel's log. (subscribe.php logs recipients'
 * addresses on a failed send: never written, as before.)
 */
class Log
{
    public const EMERGENCY = 1;

    public const ALERT = 2;

    public const CRITICAL = 4;

    public const ERROR = 8;

    public const WARNING = 16;

    public const NOTICE = 32;

    public const INFO = 64;

    public const DEBUG = 128;

    public const ALL = 30719;

    /** @var list<array{priorities: int, categories: list<string>}> */
    private static array $loggers = [];

    /** @param  array<string, mixed>  $options  @param  string|list<string>  $categories */
    public static function addLogger(array $options, int $priorities = self::ALL, string|array $categories = []): void
    {
        self::$loggers[] = ['priorities' => $priorities, 'categories' => array_map('strtolower', (array) $categories)];
    }

    /** @param  string|list<string>  $category */
    public static function add(mixed $entry, int $priority = self::INFO, string|array $category = '', ?string $date = null): void
    {
        $message = is_object($entry) && isset($entry->message) ? (string) $entry->message : (string) $entry;
        $categories = array_map('strtolower', (array) $category);
        foreach (self::$loggers as $logger) {
            if (! ($logger['priorities'] & $priority)) {
                continue;
            }
            if ($logger['categories'] !== [] && array_intersect($logger['categories'], $categories) === []) {
                continue;
            }
            $level = match (true) {
                $priority <= self::CRITICAL => 'critical',
                $priority === self::ERROR => 'error',
                $priority === self::WARNING => 'warning',
                $priority === self::NOTICE => 'notice',
                $priority === self::DEBUG => 'debug',
                default => 'info',
            };
            LaravelLog::log($level, 'legacy JLog ['.implode(',', $categories).'] '.$message);

            return;
        }
    }
}
