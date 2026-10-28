<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JText: our components ship no language files, so a key is its own text,
 * except the few core Joomla keys they use (en-GB strings of Joomla 2.5).
 */
class Text
{
    private const CORE = [
        'JERROR_ALERTNOAUTHOR' => 'You are not authorised to view this resource.',
        'JINVALID_TOKEN' => 'The most recent request was denied because it had an invalid security token. Please refresh the page and try again.',
        'JGLOBAL_START_PUBLISH_AFTER_FINISH' => 'Start publishing must be before finish publishing.',
        'JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN' => 'Access forbidden',
        'COM_CONTENT_ERROR_ARTICLE_NOT_FOUND' => 'Article not found',
        'JGLOBAL_CATEGORY_NOT_FOUND' => 'Category not found',
        'JGLOBAL_RESOURCE_NOT_FOUND' => 'Resource Not Found',
        'JGLOBAL_ARTICLES' => 'Articles',
        'JPAGETITLE' => '%1$s - %2$s',
        'COM_CONTENT_READ_MORE_TITLE' => 'Read more: ',
        'COM_CONTENT_READ_MORE' => 'Read more...',
        'JGLOBAL_NO_MATCHING_RESULTS' => 'No matching results',
        'COM_CONTENT_LAST_UPDATED' => 'Last Updated',
        'DATE_FORMAT_LC2' => 'l, d F Y H:i',
    ];

    public static function _(string $string, bool $jsSafe = false): string
    {
        $string = self::CORE[$string] ?? $string;

        return $jsSafe ? addslashes($string) : $string;
    }

    public static function sprintf(string $string, mixed ...$args): string
    {
        return sprintf(self::_($string), ...$args);
    }

    public static function plural(string $string, int $n, mixed ...$args): string
    {
        return sprintf($string, $n, ...$args);
    }
}
