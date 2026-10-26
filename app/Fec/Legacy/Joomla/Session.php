<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JSession: only the CSRF token and a get/set over the Laravel session.
 * getFormToken() is the value our desk forms post; checkToken() verifies it.
 */
class Session
{
    public static function getFormToken(bool $forceNew = false): string
    {
        return function_exists('csrf_token') && app()->bound('session') && app('session')->isStarted()
            ? (string) csrf_token()
            : hash('sha256', (string) config('app.key'));
    }

    public static function checkToken(string $method = 'post'): bool
    {
        $token = self::getFormToken();
        $request = request();
        if ($request->input($token) !== null || $request->input('_token') === $token) {
            return true;
        }

        return (string) $request->header('X-CSRF-TOKEN', '') === $token;
    }

    public function get(string $name, mixed $default = null, string $namespace = 'default'): mixed
    {
        return app()->bound('session') ? session()->get('legacy.'.$namespace.'.'.$name, $default) : $default;
    }

    public function set(string $name, mixed $value = null, string $namespace = 'default'): mixed
    {
        if (app()->bound('session')) {
            session()->put('legacy.'.$namespace.'.'.$name, $value);
        }

        return $value;
    }

    public function getId(): string
    {
        return app()->bound('session') ? (string) session()->getId() : '';
    }

    public function getToken(bool $forceNew = false): string
    {
        return self::getFormToken($forceNew);
    }
}
