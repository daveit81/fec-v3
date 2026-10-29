<?php

namespace App\Fec\Legacy\Joomla;

/** JInput (Joomla 2.5 $app->input): the same variables JRequest sees, same filters. */
class Input
{
    public function get(string $name, mixed $default = null, string $filter = 'cmd'): mixed
    {
        return Request::getVar($name, $default, 'default', $filter);
    }

    public function getInt(string $name, int $default = 0): int
    {
        return Request::getInt($name, $default);
    }

    public function getCmd(string $name, string $default = ''): string
    {
        return Request::getCmd($name, $default);
    }

    public function getString(string $name, string $default = ''): string
    {
        return Request::getString($name, $default);
    }

    public function getWord(string $name, string $default = ''): string
    {
        return Request::getWord($name, $default);
    }

    public function getBool(string $name, bool $default = false): bool
    {
        return Request::getBool($name, $default);
    }

    /**
     * JInput::getFloat(): the default as given when the variable is absent (the
     * tournaments feed asks for null), the float filter otherwise.
     */
    public function getFloat(string $name, mixed $default = 0.0): mixed
    {
        return Request::getVar($name, $default, 'default', 'float');
    }

    public function getRaw(string $name, mixed $default = null): mixed
    {
        return Request::getVar($name, $default, 'default', 'raw');
    }

    public function getHtml(string $name, mixed $default = null): mixed
    {
        return Request::getVar($name, $default, 'default', 'raw');
    }

    /** @return array<string, mixed> */
    public function getArray(array $vars = [], mixed $datasource = null): array
    {
        return Request::get();
    }

    public function set(string $name, mixed $value): void
    {
        Request::setVar($name, $value);
    }

    public function getMethod(): string
    {
        return Request::getMethod();
    }

    public function __get(string $name): Input
    {
        // $app->input->post->get(...), ->get->..., ->server->...: same bag, same filters.
        return $this;
    }
}
