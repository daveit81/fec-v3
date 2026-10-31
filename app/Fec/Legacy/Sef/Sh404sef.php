<?php

namespace App\Fec\Legacy\Sef;

use App\Fec\Url\UrlRegistry;

/**
 * sh404SEF's URL building, for the queries the registry does not hold yet.
 *
 * On the live site every SEF URL is made by sh404SEF (4.3): a component
 * plugin ("sef_ext/<option>.php") turns the Joomla query into a list of
 * title segments, sef_404::sefGetLocation() slugifies each one
 * (titleToLocation), joins them with "/", collapses repeated slashes and
 * records the pair oldurl/newurl in jos_sh404sef_urls the first time it is
 * seen (rank 0; a path already owned by another query gets the next rank,
 * a recorded 404 for that path is upgraded). JRoute resolves from the
 * registry first; this class is the fallback for a query nobody linked yet
 * (a new bonus, a new casino page), and registers it as sh404SEF did, so
 * that Joomla, still serving the public paths, resolves the link v3 wrote.
 *
 * The slugifier is titleToLocation() as configured on the live site
 * (replacement "-", lower case, no suffix, the default strip list and
 * accent map, "-" and "." trimmed at the ends); fec:sef:check rebuilds every
 * registry URL of a component with it and reports what differs.
 */
final class Sh404sef
{
    public const REPLACEMENT = '-';

    public const LOWER_CASE = true;

    /** sh404SEF "friendlytrim": characters trimmed at both ends of a segment ("|" separated). */
    public const FRIENDLY_TRIM = '-|.';

    /**
     * sh404SEF "stripthese": characters removed from a segment ("|" separated):
     * , ~ ! @ % ^ ( ) < > : ; { } [ ] & ` and the typographic quotes, bullets, guillemets, acute, degree.
     */
    public const STRIP_THESE = ",|~|!|@|%|^|(|)|<|>|:|;|{|}|[|]|&|`|\u{201E}|\u{2039}|\u{2019}|\u{2018}|\u{201C}|\u{201D}|\u{2022}|\u{203A}|\u{00AB}|\u{00B4}|\u{00BB}|\u{00B0}";

    /** sh404SEF_MAX_SEF_URL_LENGTH */
    public const MAX_LENGTH = 240;

    /**
     * sh404SEF "replacements": accented letters to their plain form, applied before
     * anything else (the default map of the component, in its order; code points
     * instead of raw UTF-8, as every PHP literal of this repository).
     */
    public const REPLACEMENTS = [
        "\u{0160}" => 'S', "\u{0152}" => 'O', "\u{017D}" => 'Z', "\u{0161}" => 's', "\u{0153}" => 'oe', "\u{017E}" => 'z',
        "\u{0178}" => 'Y', "\u{00A5}" => 'Y', "\u{00B5}" => 'u',
        "\u{00C0}" => 'A', "\u{00C1}" => 'A', "\u{00C2}" => 'A', "\u{00C3}" => 'A', "\u{00C4}" => 'A', "\u{00C5}" => 'A', "\u{00C6}" => 'A',
        "\u{00C7}" => 'C', "\u{00C8}" => 'E', "\u{00C9}" => 'E', "\u{00CA}" => 'E', "\u{00CB}" => 'E',
        "\u{00CC}" => 'I', "\u{00CD}" => 'I', "\u{00CE}" => 'I', "\u{00CF}" => 'I', "\u{00D0}" => 'D', "\u{00D1}" => 'N',
        "\u{00D2}" => 'O', "\u{00D3}" => 'O', "\u{00D4}" => 'O', "\u{00D5}" => 'O', "\u{00D6}" => 'O', "\u{00D8}" => 'O',
        "\u{00D9}" => 'U', "\u{00DA}" => 'U', "\u{00DB}" => 'U', "\u{00DC}" => 'U', "\u{00DD}" => 'Y', "\u{00DF}" => 'ss',
        "\u{00E0}" => 'a', "\u{00E1}" => 'a', "\u{00E2}" => 'a', "\u{00E3}" => 'a', "\u{00E4}" => 'a', "\u{00E5}" => 'a', "\u{00E6}" => 'a',
        "\u{00E7}" => 'c', "\u{00E8}" => 'e', "\u{00E9}" => 'e', "\u{00EA}" => 'e', "\u{00EB}" => 'e',
        "\u{00EC}" => 'i', "\u{00ED}" => 'i', "\u{00EE}" => 'i', "\u{00EF}" => 'i', "\u{00F0}" => 'o', "\u{00F1}" => 'n',
        "\u{00F2}" => 'o', "\u{00F3}" => 'o', "\u{00F4}" => 'o', "\u{00F5}" => 'o', "\u{00F6}" => 'o', "\u{00F8}" => 'o',
        "\u{00F9}" => 'u', "\u{00FA}" => 'u', "\u{00FB}" => 'u', "\u{00FC}" => 'u', "\u{00FD}" => 'y', "\u{00FF}" => 'y',
        "\u{0103}" => 'a', "\u{015F}" => 's', "\u{0163}" => 't', "\u{021B}" => 't', "\u{021A}" => 'T', "\u{0218}" => 'S', "\u{0219}" => 's', "\u{015E}" => 'S',
    ];

    /**
     * option => the port of its sef_ext plugin (com_content: the articles of the
     * categories that still get new ones, ContentPlugin; off unless
     * fec.legacy.sef_content.enabled, see build()).
     *
     * @var array<string, class-string<SefPlugin>>
     */
    public const PLUGINS = [
        'com_bonuses' => BonusesPlugin::class,
        'com_content' => ContentPlugin::class,
    ];

    /**
     * The SEF path (no leading slash, as stored) for a Joomla query, built as the
     * live plugin builds it, with the non-SEF URL sh404SEF would store for it.
     * Null when no plugin covers the option or the plugin leaves the URL raw
     * (the desk views).
     *
     * The com_content port answers only with fec.legacy.sef_content.enabled on
     * ($force: fec:sef:check, which compares and never writes): until then the
     * links of articles the registry does not hold stay raw, as before it.
     *
     * @param  array<string, string>  $query  option/view/... of the link
     */
    public static function build(string $url, array $query, bool $force = false): ?BuiltUrl
    {
        $option = $query['option'] ?? '';
        $class = self::PLUGINS[$option] ?? null;
        if ($class === null) {
            return null;
        }
        if ($option === 'com_content' && ! $force && ! config('fec.legacy.sef_content.enabled', false)) {
            return null;
        }

        $vars = $query;
        $title = $class::title($vars);
        if ($title === null) {
            return null;
        }

        $path = self::location($title);
        if ($path === '') {
            return null;
        }

        // the non-SEF URL as stored: the link as written ("&amp;" normalised), "lang=en"
        // appended when missing; rebuilt from the variables when the plugin changed them
        // (/bonuses/crypto is "crypto=yes", not "type=crypto")
        $nonSef = str_replace('&amp;', '&', $url);
        if ($vars !== $query) {
            $nonSef = 'index.php?'.http_build_query($vars);
        }
        if (! str_contains($nonSef, 'lang=')) {
            $nonSef .= (str_ends_with($nonSef, 'index.php') ? '?' : '&').'lang=en';
        }

        return new BuiltUrl($path, $nonSef);
    }

    /**
     * sefGetLocation()'s assembly: every segment through titleToLocation, joined
     * with "/", repeated slashes collapsed, cut at the maximum length.
     *
     * @param  list<string>  $title
     */
    public static function location(array $title): string
    {
        $bits = [];
        foreach ($title as $segment) {
            $bits[] = self::titleToLocation(urldecode((string) $segment));
        }
        $path = implode('/', $bits);
        $path = (string) preg_replace('#/{2,}#u', '/', $path);

        return mb_substr($path, 0, self::MAX_LENGTH);
    }

    /** titleToLocation() of sh404SEF 4.3 with the live site's settings. */
    public static function titleToLocation(string $title): string
    {
        $title = trim($title);
        $title = str_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $title);
        $title = str_replace(explode('|', self::STRIP_THESE), '', $title);
        $title = (string) preg_replace('/[\s]+/iu', self::REPLACEMENT, $title);
        $title = str_replace(["'", '"', '#', '?', '\\'], self::REPLACEMENT, $title);
        $title = (string) preg_replace('/'.preg_quote(self::REPLACEMENT, '/').'{2,}/u', self::REPLACEMENT, $title);
        $title = trim($title, str_replace('|', '', self::FRIENDLY_TRIM));

        return self::LOWER_CASE ? mb_strtolower($title) : $title;
    }

    /** The registry row sefGetLocation() would write for a path it built. */
    public static function register(BuiltUrl $built): void
    {
        app(UrlRegistry::class)->register($built->path, $built->nonSef);
    }
}
