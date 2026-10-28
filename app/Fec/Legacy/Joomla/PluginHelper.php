<?php

namespace App\Fec\Legacy\Joomla;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * JPluginHelper of Joomla 2.5 for the plugins we ported: importPlugin($type)
 * includes plugins/<type>/<name>/<name>.php under the virtual docroot for
 * every name listed in fec.legacy.plugins.<type> and attaches an instance to
 * the dispatcher, with the params the live site keeps in #__extensions (the
 * same row Joomla read). isEnabled() answers for those names only: the third
 * party plugins of the live site are not ported (fec:parity shows which ones
 * change a page; those are reproduced one by one).
 */
final class PluginHelper
{
    /** @var array<string, bool> "type/name" already imported in this request */
    private static array $imported = [];

    /** @var array<string, string>|null "type/name" => params JSON, from #__extensions */
    private static ?array $params = null;

    public static function reset(): void
    {
        self::$imported = [];
    }

    public static function isEnabled(string $type, ?string $plugin = null): bool
    {
        $names = (array) config('fec.legacy.plugins.'.$type, []);

        return $plugin === null ? $names !== [] : in_array($plugin, $names, true);
    }

    /** @return list<object> the rows (name, type, params) of the ported plugins of a type */
    public static function getPlugin(string $type, ?string $plugin = null): array|object|null
    {
        $rows = [];
        foreach ((array) config('fec.legacy.plugins.'.$type, []) as $name) {
            $rows[] = (object) ['name' => $name, 'type' => $type, 'params' => self::paramsOf($type, $name)];
        }
        if ($plugin !== null) {
            foreach ($rows as $row) {
                if ($row->name === $plugin) {
                    return $row;
                }
            }

            return null;
        }

        return $rows;
    }

    public static function importPlugin(string $type, ?string $plugin = null, bool $autocreate = true, mixed $dispatcher = null): bool
    {
        $dispatcher = $dispatcher instanceof Dispatcher ? $dispatcher : Dispatcher::getInstance();
        $loaded = false;
        foreach ((array) config('fec.legacy.plugins.'.$type, []) as $name) {
            if ($plugin !== null && $plugin !== $name) {
                continue;
            }
            $key = $type.'/'.$name;
            if (isset(self::$imported[$key])) {
                $loaded = true;

                continue;
            }
            $file = Runtime::sitePath().'/plugins/'.$type.'/'.$name.'/'.$name.'.php';
            if (! is_file($file)) {
                continue;
            }
            $class = 'plg'.ucfirst($type).ucfirst($name);
            if (! class_exists($class, false)) {
                require_once $file;
            }
            if (! class_exists($class, false)) {
                continue;
            }
            self::$imported[$key] = true;
            $loaded = true;
            if ($autocreate) {
                $config = ['name' => $name, 'type' => $type, 'params' => self::paramsOf($type, $name)];
                $dispatcher->attach(new $class($dispatcher, $config));
            }
        }

        return $loaded;
    }

    /** The params JSON of #__extensions for a plugin (empty when the row is missing). */
    private static function paramsOf(string $type, string $name): string
    {
        if (self::$params === null) {
            self::$params = [];
            try {
                $rows = DB::connection('fec')->table('extensions')
                    ->where('type', 'plugin')
                    ->get(['folder', 'element', 'params']);
                foreach ($rows as $row) {
                    self::$params[$row->folder.'/'.$row->element] = (string) $row->params;
                }
            } catch (Throwable $e) {
                error_log('legacy plugins: reading #__extensions failed: '.$e->getMessage());
            }
        }

        return self::$params[$type.'/'.$name] ?? '';
    }
}
