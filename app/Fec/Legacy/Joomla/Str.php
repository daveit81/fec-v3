<?php

namespace App\Fec\Legacy\Joomla;

/** JString: the multibyte string helpers of Joomla 2.5 we call. */
class Str
{
    public static function substr(string $str, int $offset, ?int $length = null): string
    {
        return mb_substr($str, $offset, $length, 'UTF-8');
    }

    public static function strlen(string $str): int
    {
        return mb_strlen($str, 'UTF-8');
    }

    public static function strtolower(string $str): string
    {
        return mb_strtolower($str, 'UTF-8');
    }

    public static function strtoupper(string $str): string
    {
        return mb_strtoupper($str, 'UTF-8');
    }

    public static function ucfirst(string $str): string
    {
        return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($str, 1, null, 'UTF-8');
    }

    public static function trim(string $str, ?string $charlist = null): string
    {
        return $charlist === null ? trim($str) : trim($str, $charlist);
    }

    public static function strpos(string $haystack, string $needle, int $offset = 0): int|false
    {
        return mb_strpos($haystack, $needle, $offset, 'UTF-8');
    }

    public static function str_ireplace(string|array $search, string|array $replace, string $subject): string
    {
        return str_ireplace($search, $replace, $subject);
    }
}
