<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JResponse: the headers a component sets on the response (the runner copies
 * them from the application), allowCache(), which v3 does not need (no page is
 * cached), and the body during onAfterRender: the runner stores the finished
 * page here, the system plugins rewrite it, the runner takes it back.
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

    /** @var string the rendered page, set by the runner before onAfterRender (the system plugins rewrite it) */
    private static string $body = '';

    public static function setBody(string $content): void
    {
        self::$body = $content;
    }

    public static function appendBody(string $content): void
    {
        self::$body .= $content;
    }

    public static function prependBody(string $content): void
    {
        self::$body = $content.self::$body;
    }

    public static function getBody(bool $toArray = false): string|array
    {
        return $toArray ? [self::$body] : self::$body;
    }
}
