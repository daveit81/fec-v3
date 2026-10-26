<?php

namespace App\Fec\Legacy\Joomla;

/** JFilterOutput: the slug and output filters our code calls, with Joomla 2.5 behaviour. */
class FilterOutput
{
    public static function stringURLSafe(string $string): string
    {
        $str = str_replace('-', ' ', $string);
        $str = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        $str = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', strtolower($str)) ?? '';

        return trim($str, '-');
    }

    public static function stringURLUnicodeSlug(string $string): string
    {
        $str = str_replace('-', ' ', $string);
        $str = preg_replace('/(\s|[^\pL\pN\-])+/u', '-', mb_strtolower($str)) ?? '';

        return trim($str, '-');
    }

    public static function ampReplace(string $text): string
    {
        return (string) preg_replace('/&(?!(amp;|#[0-9]+;|[a-z]+;))/i', '&amp;', $text);
    }

    public static function cleanText(string $text): string
    {
        return trim(strip_tags($text));
    }

    public static function stripImages(string $string): string
    {
        return (string) preg_replace('/<img[^>]*>/i', '', $string);
    }
}
