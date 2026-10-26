<?php

namespace App\Fec\Legacy;

/**
 * On the development hostname (v3.) the legacy docroot is not there: the
 * component's stylesheets, scripts and images are written with site-relative
 * paths (/components/com_authors/assets/bylines.css, /images/authors/x.webp)
 * that only exist under www. This rewrites those asset paths to the asset
 * base so the page looks on v3. as it will on www, and leaves page links
 * alone so navigation stays on the development host. Never applied on the
 * live hostname, where parity holds byte for byte.
 */
final class DevAssets
{
    private const PREFIXES = 'images|css|js|components|templates|media|favicon';

    public static function absolutize(string $html, string $assetBase): string
    {
        $base = rtrim($assetBase, '/');
        if ($base === '') {
            return $html;
        }
        $html = (string) preg_replace('#(\s(?:src|href|data-src|poster)=["\'])/('.self::PREFIXES.')/#i', '$1'.$base.'/$2/', $html);
        $html = (string) preg_replace('#(\ssrcset=["\'][^"\']*?)(?<=[\s,"\'])/('.self::PREFIXES.')/#i', '$1'.$base.'/$2/', $html);

        return (string) preg_replace('#url\((["\']?)/('.self::PREFIXES.')/#i', 'url($1'.$base.'/$2/', $html);
    }
}
