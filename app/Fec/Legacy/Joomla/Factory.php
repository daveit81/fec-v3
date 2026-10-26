<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JFactory: the per-request singletons a component reaches for. reset()
 * runs at every legacy dispatch so one PHP process never leaks a document
 * head or a request var into the next request.
 */
class Factory
{
    private static ?Database $db = null;

    private static ?Database $dbRw = null;

    private static ?Application $application = null;

    private static ?Document $document = null;

    private static ?User $user = null;

    private static ?Registry $config = null;

    private static ?Session $session = null;

    public static function reset(): void
    {
        self::$application = null;
        self::$document = null;
        self::$user = null;
        self::$session = null;
    }

    public static function getDbo(): Database
    {
        return self::$db ??= new Database('fec');
    }

    /** The writing connection, for the desk views only (never called by legacy code: the runner swaps it in). */
    public static function getDboRw(): Database
    {
        return self::$dbRw ??= new Database('fec_rw');
    }

    public static function useRwConnection(bool $rw): void
    {
        self::$db = $rw ? self::getDboRw() : new Database('fec');
    }

    public static function getApplication(mixed $id = null, array $config = [], string $prefix = 'J'): Application
    {
        return self::$application ??= new Application;
    }

    public static function getDocument(): Document
    {
        return self::$document ??= new Document;
    }

    public static function getUser(mixed $id = null): User
    {
        if ($id !== null && $id !== 0 && $id !== '') {
            return User::load((int) $id);
        }

        return self::$user ??= User::current();
    }

    public static function setUser(User $user): void
    {
        self::$user = $user;
    }

    /** Joomla's configuration.php as a registry: what our code reads is secret, sitename, offset, live_site. */
    public static function getConfig(): Registry
    {
        return self::$config ??= new Registry([
            'secret' => (string) config('fec.legacy.secret', config('app.key')),
            'sitename' => (string) config('fec.legacy.sitename', 'FreeExtraChips'),
            'offset' => 'UTC',
            'live_site' => '',
            'tmp_path' => storage_path('app/legacy-tmp'),
            'log_path' => storage_path('logs'),
            'mailfrom' => (string) config('mail.from.address', ''),
            'fromname' => (string) config('mail.from.name', ''),
        ]);
    }

    public static function getDate(mixed $time = 'now', mixed $tzOffset = null): Date
    {
        return new Date($time, $tzOffset);
    }

    public static function getSession(array $options = []): Session
    {
        return self::$session ??= new Session;
    }

    public static function getCache(string $group = '', string $handler = 'callback', ?string $storage = null): Cache
    {
        return new Cache;
    }

    public static function getLanguage(): Language
    {
        return new Language;
    }
}
