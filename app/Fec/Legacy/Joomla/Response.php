<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JResponse: the headers a component sets on the response (the runner copies
 * them from the application), and allowCache(), which v3 does not need: no
 * page is cached.
 */
class Response
{
    private static bool $cachable = false;

    public static function setHeader(string $name, string $value, bool $replace = false): void
    {
        Factory::getApplication()->setHeader($name, $value, $replace);
    }

    /** @return list<array{name: string, value: string}> */
    public static function getHeaders(): array
    {
        $out = [];
        foreach (Factory::getApplication()->getHeaders() as $name => $value) {
            $out[] = ['name' => $name, 'value' => $value];
        }

        return $out;
    }

    public static function allowCache(?bool $allow = null): bool
    {
        if ($allow !== null) {
            self::$cachable = $allow;
        }

        return self::$cachable;
    }

    public static function setBody(string $content): void
    {
        echo $content;
    }

    public static function appendBody(string $content): void
    {
        echo $content;
    }

    public static function getBody(bool $toArray = false): string|array
    {
        return $toArray ? [] : '';
    }
}
