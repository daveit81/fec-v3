<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JRequest over the Laravel request. The runner seeds the variables the
 * SEF layer would have produced (option, view, slug, Itemid...) with
 * setVar(); getVar() then reads them before the query string and the body,
 * exactly as Joomla's merged $_REQUEST did. Filters follow JFilterInput's
 * types for the ones our code uses: int, string, cmd, word, bool, float,
 * array, raw/none.
 */
class Request
{
    /** @var array<string, mixed> variables set by the router/runner, highest priority */
    private static array $vars = [];

    public static function reset(): void
    {
        self::$vars = [];
    }

    /** @param  array<string, mixed>  $vars */
    public static function seed(array $vars): void
    {
        foreach ($vars as $k => $v) {
            self::$vars[$k] = $v;
        }
    }

    /**
     * The mask is JRequest's: JREQUEST_NOTRIM keeps surrounding whitespace,
     * JREQUEST_ALLOWRAW skips the string filter, JREQUEST_ALLOWHTML keeps the
     * markup. Without them a "string" loses its tags (JFilterInput's default
     * instance allows none) and is trimmed, as on the live site.
     */
    public static function getVar(string $name, mixed $default = null, string $hash = 'default', string $type = 'none', int $mask = 0): mixed
    {
        $value = self::raw($name, strtolower($hash));
        if ($value === null) {
            return $default;
        }
        if (is_string($value) && ! ($mask & 1)) {
            $value = trim($value);
        }
        if (strtoupper($type) === 'STRING' && ! ($mask & 6) && is_string($value)) {
            $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML401, 'UTF-8'));
        }

        return self::clean($value, $type);
    }

    public static function getInt(string $name, int $default = 0, string $hash = 'default'): int
    {
        return (int) self::getVar($name, $default, $hash, 'int');
    }

    public static function getUInt(string $name, int $default = 0, string $hash = 'default'): int
    {
        return abs((int) self::getVar($name, $default, $hash, 'int'));
    }

    public static function getFloat(string $name, float $default = 0.0, string $hash = 'default'): float
    {
        return (float) self::getVar($name, $default, $hash, 'float');
    }

    public static function getBool(string $name, bool $default = false, string $hash = 'default'): bool
    {
        return (bool) self::getVar($name, $default, $hash, 'bool');
    }

    public static function getWord(string $name, string $default = '', string $hash = 'default'): string
    {
        return (string) self::getVar($name, $default, $hash, 'word');
    }

    public static function getCmd(string $name, string $default = '', string $hash = 'default'): string
    {
        return (string) self::getVar($name, $default, $hash, 'cmd');
    }

    public static function getString(string $name, string $default = '', string $hash = 'default', int $mask = 0): string
    {
        return (string) self::getVar($name, $default, $hash, 'string', $mask);
    }

    public static function setVar(string $name, mixed $value = null, string $hash = 'method', bool $overwrite = true): mixed
    {
        if (! $overwrite && array_key_exists($name, self::$vars)) {
            return self::$vars[$name];
        }
        self::$vars[$name] = $value;

        return $value;
    }

    /** @return array<string, mixed> */
    public static function get(string $hash = 'default', int $mask = 0): array
    {
        $request = request();
        $base = match (strtolower($hash)) {
            'get' => $request->query(),
            'post' => $request->post(),
            'files' => $_FILES,
            'cookie' => $request->cookie(),
            'env', 'server' => $request->server(),
            default => $request->all(),
        };

        return array_merge(is_array($base) ? $base : [], self::$vars);
    }

    public static function getMethod(): string
    {
        return strtoupper(request()->getMethod());
    }

    public static function checkToken(string $method = 'post'): bool
    {
        return Session::checkToken($method);
    }

    private static function raw(string $name, string $hash): mixed
    {
        if (array_key_exists($name, self::$vars) && in_array($hash, ['default', 'request', 'get', 'method'], true)) {
            return self::$vars[$name];
        }
        $request = request();

        return match ($hash) {
            'get' => $request->query($name),
            'post' => $request->post($name),
            // the $_FILES entry (name, type, tmp_name, error, size), as the legacy upload code reads it
            'files' => $_FILES[$name] ?? null,
            'cookie' => $request->cookie($name),
            'env', 'server' => $request->server($name),
            'method' => self::getMethod() === 'POST' ? $request->post($name) : $request->query($name),
            default => $request->input($name),
        };
    }

    private static function clean(mixed $value, string $type): mixed
    {
        switch (strtoupper($type)) {
            case 'INT':
            case 'INTEGER':
                preg_match('/-?[0-9]+/', is_scalar($value) ? (string) $value : '', $m);

                return isset($m[0]) ? (int) $m[0] : 0;
            case 'FLOAT':
            case 'DOUBLE':
                preg_match('/-?[0-9]+(\.[0-9]+)?/', is_scalar($value) ? (string) $value : '', $m);

                return isset($m[0]) ? (float) $m[0] : 0.0;
            case 'BOOL':
            case 'BOOLEAN':
                return (bool) $value;
            case 'WORD':
                return (string) preg_replace('/[^A-Z_]/i', '', is_scalar($value) ? (string) $value : '');
            case 'CMD':
                return ltrim((string) preg_replace('/[^A-Z0-9_\.-]/i', '', is_scalar($value) ? (string) $value : ''), '.');
            case 'STRING':
                return is_array($value) ? '' : (string) $value;
            case 'ARRAY':
                return (array) $value;
            default:
                return $value;
        }
    }
}
