<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JLoader::register('BonusesHelperBonus', JPATH_COMPONENT . '/helpers/bonus.php'):
 * the file is required when the class is first used, as Joomla's autoloader did.
 */
class Loader
{
    /** @var array<string, string> class => file */
    private static array $classes = [];

    private static bool $registered = false;

    public static function register(string $class, string $path, bool $force = false): void
    {
        $class = ltrim($class, '\\');
        if ($class === '' || ! is_file($path)) {
            return;
        }
        if ($force || ! isset(self::$classes[$class])) {
            self::$classes[$class] = $path;
        }
        if (! self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    public static function load(string $class): bool
    {
        $class = ltrim($class, '\\');
        if (isset(self::$classes[$class]) && ! class_exists($class, false)) {
            require_once self::$classes[$class];

            return class_exists($class, false);
        }

        return false;
    }

    /** jimport()-style library imports: every library class is provided by the layer. */
    public static function import(string $key, ?string $base = null): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public static function getClassList(): array
    {
        return self::$classes;
    }
}
