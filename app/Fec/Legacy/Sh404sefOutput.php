<?php

namespace App\Fec\Legacy;

/**
 * What sh404SEF wrote into the finished page, kept away from the core "sef"
 * system plugin until sh404SEF's turn.
 *
 * On the live site the system plugins ran on the finished HTML in their
 * order: "sef" (relative src/href made absolute), then sh404SEF's page
 * rewrite (its canonical inserted or replaced, as written), then
 * feccanonicalfix and fecclustermeta. A canonical sh404SEF wrote was never
 * seen by "sef": the "Title and metas" row of casinouk, relative
 * ("reviews/online-casino/casinouk.html"), is served relative. v3 decides the
 * canonical on the document before the layout (Sh404sefPage, Sh404sefMetas);
 * the layout prints it under a placeholder that looks like a URL scheme,
 * which "sef" leaves alone, and the "sh404sef" system plugin of the layer
 * (site/plugins/system/sh404sef) puts it back in sh404SEF's place.
 */
final class Sh404sefOutput
{
    private const SCHEME = 'fecsh404sef:';

    /** @var list<string> */
    private static array $hrefs = [];

    public static function reset(): void
    {
        self::$hrefs = [];
    }

    /** The placeholder the layout prints in place of $href. */
    public static function protect(string $href): string
    {
        self::$hrefs[] = $href;

        return self::SCHEME.(count(self::$hrefs) - 1);
    }

    /** The placeholders replaced with their hrefs, escaped as the layout escapes (Blade's e()). */
    public static function restore(string $html): string
    {
        if (self::$hrefs === [] || ! str_contains($html, self::SCHEME)) {
            return $html;
        }

        return preg_replace_callback(
            '/'.preg_quote(self::SCHEME, '/').'(\d+)/',
            fn (array $m): string => isset(self::$hrefs[(int) $m[1]])
                ? htmlspecialchars(self::$hrefs[(int) $m[1]], ENT_QUOTES, 'UTF-8')
                : $m[0],
            $html
        ) ?? $html;
    }
}
