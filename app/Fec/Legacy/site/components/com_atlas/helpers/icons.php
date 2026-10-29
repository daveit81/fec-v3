<?php
defined('_JEXEC') or die;

/**
 * Inline SVG icons for the public Atlas templates.
 * FEC pastello stroke style (house rule): fill none, currentColor stroke,
 * width 2, round caps and joins. Never emoji, never icon fonts.
 */
if (!function_exists('atlas_public_icon')) {
    function atlas_public_icon($name, $size = 16)
    {
        $paths = array(
            'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"/>',
            'shield'   => '<path d="M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-3z"/>',
            'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 1 0-5.7-5.7l-1.2 1.2"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 1 0 5.7 5.7l1.2-1.2"/>',
            'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 4 5.7 4 9s-1.5 6.4-4 9c-2.5-2.6-4-5.7-4-9s1.5-6.4 4-9z"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'doc'      => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/>',
            'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        );
        $d = isset($paths[$name]) ? $paths[$name] : $paths['building'];
        return '<svg viewBox="0 0 24 24" width="' . (int)$size . '" height="' . (int)$size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
    }
}
