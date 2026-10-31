<?php

namespace App\Fec\Desk;

/**
 * The affiliate links desk of v3 (phase 5): the phrases of /goto.php?link=... in
 * jos_shortlink, which com_shortlink managed from the Joomla backend. The public side
 * is v3's since 25 Oct 2026 (GotoController, App\Fec\Shortlink\Shortlinks); this is the
 * management that was left behind, written for v3 rather than ported.
 *
 * What the old backend did (administrator/components/com_shortlink): list with search,
 * a "last call" filter and sorting; add and edit phrase, destination (a URL, or a
 * com_content article id picked from a list) and description; delete. Two of its
 * quirks are not kept: every save rewrote create_date with the time of the save (the
 * table's constructor set it and the edit form never sent it), and a phrase could be
 * renamed or deleted while a casino or bonus still pointed at it (goto.php then lands
 * on the home page, silently). Data of 31 Oct 2026: 1,010 phrases, 1,008 absolute
 * URLs, no article ids; 806 casinos (jr_gourl), 1 bonus and 39 articles use them.
 *
 * Pure rules here; the controller reads and writes the database.
 */
final class LinksDesk
{
    /** Rows per page of the list. */
    public const PER_PAGE = 100;

    /** Longest phrase and destination the table holds. */
    public const PHRASE_MAX = 100;

    public const LINK_MAX = 255;

    public const DESCRIPTION_MAX = 255;

    /** Sort key => [column, direction]. */
    public const SORTS = [
        'newest' => ['id', 'desc'],
        'phrase' => ['phrase', 'asc'],
        'hits' => ['counter', 'desc'],
        'last_call' => ['last_call', 'desc'],
    ];

    /** List filters: all, used by no page, no call in 30 days, never called. */
    public const SHOWS = ['all', 'unused', 'idle', 'never'];

    /** A new phrase as typed (letters, digits, dot, dash, underscore), or null. */
    public static function phrase(mixed $value): ?string
    {
        $s = is_string($value) ? trim($value) : '';

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,'.(self::PHRASE_MAX - 1).'}$/', $s) ? $s : null;
    }

    /**
     * A destination: an absolute http(s) URL, or a path of this site starting with "/"
     * (com_shortlink routed anything relative through the site). Null when neither.
     */
    public static function link(mixed $value): ?string
    {
        $s = is_string($value) ? trim($value) : '';
        if ($s === '' || strlen($s) > self::LINK_MAX || preg_match('/[\x00-\x20\x7F]/', $s)) {
            return null;
        }
        if (preg_match('#^https?://#i', $s)) {
            $host = parse_url($s, PHP_URL_HOST);

            return filter_var($s, FILTER_VALIDATE_URL) && is_string($host) && $host !== '' ? $s : null;
        }

        return str_starts_with($s, '/') && ! str_starts_with($s, '//') ? $s : null;
    }

    /** A description: one line, at most 255 characters. */
    public static function description(mixed $value): string
    {
        $s = is_string($value) ? $value : '';
        $s = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s)));

        return mb_substr($s, 0, self::DESCRIPTION_MAX);
    }

    /**
     * The phrases of every goto.php link in a text (a jr_gourl value, a bonus link, an
     * article body), read from the parameters goto.php reads, in order.
     *
     * @param  list<string>  $params
     * @return list<string>
     */
    public static function phrasesIn(string $text, array $params = ['link']): array
    {
        if (stripos($text, 'goto.php') === false
            || ! preg_match_all('~goto\.php\?([^"\'\s<>#]+)~i', $text, $m)) {
            return [];
        }
        $out = [];
        foreach ($m[1] as $qs) {
            parse_str(html_entity_decode($qs, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $vars);
            foreach ($params as $param) {
                $v = $vars[$param] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    $out[] = trim($v);
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** The key two phrases match on: the table's collation ignores case. */
    public static function key(string $phrase): string
    {
        return mb_strtolower($phrase);
    }

    /** The address to paste in a casino or a bonus. */
    public static function goUrl(string $siteUrl, string $phrase, string $param = 'link'): string
    {
        return rtrim($siteUrl, '/').'/goto.php?'.$param.'='.rawurlencode($phrase);
    }

    /** The host a destination goes to ("this site" for a path), for the list. */
    public static function host(string $link): string
    {
        $host = preg_match('#^[a-z][a-z0-9+.-]*://#i', $link) ? parse_url($link, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? strtolower($host) : 'this site';
    }

    /** A datetime of the table as shown (minutes, UTC), "" for the zero date of "never". */
    public static function when(?string $datetime): string
    {
        $s = (string) $datetime;

        return $s === '' || str_starts_with($s, '0000-00-00') ? '' : substr($s, 0, 16);
    }
}
