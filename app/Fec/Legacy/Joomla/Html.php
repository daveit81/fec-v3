<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JHtml::_() for the keys our components use: 'date' (Joomla's default
 * format "l, d F Y", UTC, as the site offset is UTC), 'form.token' (the
 * hidden CSRF input), 'behavior.*' and 'script'/'stylesheet' (no-ops or
 * document additions).
 */
class Html
{
    public static function _(string $key, mixed ...$args): mixed
    {
        return match (strtolower($key)) {
            'date' => self::date(...$args),
            'form.token' => '<input type="hidden" name="'.Session::getFormToken().'" value="1" />',
            'script' => self::script(...$args),
            'stylesheet' => self::stylesheet(...$args),
            'behavior.framework', 'behavior.modal', 'behavior.tooltip', 'behavior.formvalidation', 'behavior.keepalive', 'behavior.calendar' => null,
            'string.truncate' => self::truncate(...$args),
            default => '',
        };
    }

    public static function date(mixed $input = 'now', ?string $format = null, mixed $tz = true, bool $gregorian = false): string
    {
        if ($input === null || $input === '' || $input === '0000-00-00 00:00:00') {
            return '';
        }
        $date = new Date(is_numeric($input) ? (int) $input : (string) $input, is_string($tz) ? $tz : 'UTC');

        return $date->format($format ?: 'l, d F Y');
    }

    public static function script(string $file, bool $framework = false, bool $relative = false): void
    {
        Factory::getDocument()->addScript($file);
    }

    public static function stylesheet(string $file, array $attribs = [], bool $relative = false): void
    {
        Factory::getDocument()->addStyleSheet($file);
    }

    public static function truncate(string $text, int $length = 0, bool $noSplit = true, bool $allowHtml = true): string
    {
        $text = $allowHtml ? $text : strip_tags($text);
        if ($length <= 0 || mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        if ($noSplit && ($pos = mb_strrpos($cut, ' ')) !== false) {
            $cut = mb_substr($cut, 0, $pos);
        }

        return $cut.'...';
    }
}
