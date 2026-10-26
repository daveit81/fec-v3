<?php

namespace App\Fec\Legacy\Joomla;

/** JText: our components ship no language files, so every key is its own text. */
class Text
{
    public static function _(string $string, bool $jsSafe = false): string
    {
        return $jsSafe ? addslashes($string) : $string;
    }

    public static function sprintf(string $string, mixed ...$args): string
    {
        return sprintf($string, ...$args);
    }

    public static function plural(string $string, int $n, mixed ...$args): string
    {
        return sprintf($string, $n, ...$args);
    }
}
