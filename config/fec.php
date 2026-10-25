<?php

/*
|--------------------------------------------------------------------------
| FreeExtraChips v3 - site settings
|--------------------------------------------------------------------------
|
| Everything that the legacy template read from Joomla params, PHP constants
| or hardcoded strings lives here, so that Blade and the console commands
| share one source. Values that change per environment come from .env.
|
*/

return [

    // Public hostname of the live site: used for canonical URLs and the brand link.
    'site_url' => env('FEC_SITE_URL', 'https://www.freeextrachips.com'),

    // Where /css, /js and /images are served from during the convivenza. On
    // v3.freeextrachips.com the static assets still live in the legacy docroot,
    // so they are loaded from the live hostname; once v3 serves the main
    // hostname, set FEC_ASSET_BASE to an empty string and the paths become
    // relative again. Same paths, same files, no duplication.
    'asset_base' => env('FEC_ASSET_BASE', 'https://www.freeextrachips.com'),

    // Template params of fecv2 as set in production (not the code defaults).
    'container_width' => (int) env('FEC_CONTAINER_WIDTH', 1200),

    // Google Tag Manager: emitted only when app.env is "production", so the
    // development hostname never records visits.
    'gtm_id' => env('FEC_GTM_ID', 'GTM-P4J6ZS'),

    'social' => [
        'x' => 'https://x.com/FreeExtraChips',
        'youtube' => 'https://www.youtube.com/@freeextrachips',
        'telegram' => env('FEC_TELEGRAM_URL', 'https://t.me/freeextrachips'),
        'discord' => env('FEC_DISCORD_URL', 'https://discord.gg/BgCyVu7VvQ'),
    ],

    // Countries treated as EU/EEA for the consent default (analytics denied).
    // Same list as templates/fecv2/index.php; unknown country counts as EU.
    'eu_countries' => [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'GB', 'UK', 'NO', 'IS', 'LI',
    ],

    'bonus' => [
        // SQL fragment that selects the bonuses the site treats as live.
        // Verify the real values with `php artisan fec:desk-lint --probe`
        // (distribution of jos_bonuses.status) before trusting the rules.
        'active_where' => env('FEC_BONUS_ACTIVE_WHERE', "status = 'active'"),

        // Type codes that mean "welcome offer" (one per casino at a time).
        // Calibrate with --probe (distribution of jos_bonuses.type).
        'welcome_types' => array_values(array_filter(explode(',', env('FEC_BONUS_WELCOME_TYPES', 'wp,welcome,fdm,match')))),

        // Dashboard editor for a bonus, used in Desk Lint links.
        'editor_url' => env('FEC_BONUS_EDITOR_URL', 'https://www.freeextrachips.com/index.php?option=com_bonuses&bid=%d&lang=en&view=add'),
    ],

    'desk_lint' => [
        // Days without a modification after which a live bonus is "stale".
        'stale_days' => (int) env('FEC_DESK_LINT_STALE_DAYS', 180),

        // Report recipients. Mail uses the app mailer (MAIL_*); Telegram needs
        // the bot token and the chat id of the desk channel.
        'mail_to' => array_values(array_filter(explode(',', env('FEC_DESK_LINT_MAIL_TO', 'system@freeextrachips.com')))),
        'telegram_token' => env('FEC_TG_BOT_TOKEN', ''),
        'telegram_chat_id' => env('FEC_TG_DESK_CHAT_ID', ''),

        // Weekday (ISO: 1 = Monday) of the full recap; other days report news only.
        'recap_weekday' => (int) env('FEC_DESK_LINT_RECAP_WEEKDAY', 1),
    ],
];
