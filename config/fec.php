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

    // The desk's clock: report timestamps, recap weekday, "today" in editorial
    // rules. Storage stays UTC (app.timezone), as the legacy tables are.
    'timezone' => env('FEC_TIMEZONE', 'Europe/Rome'),

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
        // Probe of 25 Oct 2026: su 834 (sign-up), fs 790, nd 237, 2d/3d/4d/5d
        // (package legs), hr, sl, cb, bj, db, fp, rl, vp, wb, fc, wkb, mon, tue.
        'welcome_types' => array_values(array_filter(explode(',', env('FEC_BONUS_WELCOME_TYPES', 'su')))),

        // Canonical currency codes as the desk writes them (probe of 25 Oct 2026:
        // euro 785, empty 749, usd 693, gbp 188, btc, aud, mbtc, cad, sek, nok, kr,
        // nzd, usdt, dkk). "eur" (22 rows) is a desk slip, hence not listed.
        'currencies' => array_values(array_filter(explode(',', env('FEC_BONUS_CURRENCIES', 'euro,usd,gbp,btc,mbtc,aud,cad,sek,nok,kr,nzd,usdt,dkk')))),

        // Dashboard editor for a bonus, used in Desk Lint links.
        'editor_url' => env('FEC_BONUS_EDITOR_URL', 'https://www.freeextrachips.com/index.php?option=com_bonuses&bid=%d&lang=en&view=add'),
    ],

    'goto' => [
        // Query parameters that carry the shortlink phrase (com_shortlink
        // "paramname", comma separated; legacy default "link").
        'params' => array_values(array_filter(explode(',', env('FEC_GOTO_PARAMS', 'link')))),

        // Where an unknown or missing phrase lands (com_shortlink fallback).
        'fallback' => env('FEC_GOTO_FALLBACK', '/'),

        // Legacy fec_detect_country() default when no country is known.
        'default_country' => 'US',
    ],

    'router' => [
        // sh404SEF duplicate URLs (rank > 0). Checked on the live site on 25 Oct
        // 2026: Joomla serves them as pages (200) with a self-referencing
        // canonical, so parity means false. true answers 301 to the rank-0
        // spelling instead: a phase 6 option, never during the convivenza.
        'alias_redirect' => filter_var(env('FEC_ROUTER_ALIAS_REDIRECT', false), FILTER_VALIDATE_BOOL),
    ],

    'desk_lint' => [
        // Days without a modification after which a live bonus is "stale", and
        // how many of the oldest ones the review queue shows each day (the
        // backlog is most of the catalogue: 2.145 of 2.529 on 26 Oct 2026).
        'stale_days' => (int) env('FEC_DESK_LINT_STALE_DAYS', 180),
        'stale_queue' => (int) env('FEC_DESK_LINT_STALE_QUEUE', 10),

        // active_on_closed_casino: which casino flags count. The site hides the
        // bonuses of closed and suspended casinos at render time, so by default
        // only the blacklist is an inconsistency. Options: blacklisted, closed, suspended.
        'closed_flags' => array_values(array_filter(explode(',', env('FEC_DESK_LINT_CLOSED_FLAGS', 'blacklisted')))),

        // currency_mismatch: compare currencies only among bonuses of the same
        // casino aimed at the same countries (true), or across the whole casino (false).
        'currency_by_market' => filter_var(env('FEC_DESK_LINT_CURRENCY_BY_MARKET', true), FILTER_VALIDATE_BOOL),

        // Report recipients. Mail uses the app mailer (MAIL_*); Telegram needs
        // the bot token and the chat id of the desk channel.
        'mail_to' => array_values(array_filter(explode(',', env('FEC_DESK_LINT_MAIL_TO', 'system@freeextrachips.com')))),
        'telegram_token' => env('FEC_TG_BOT_TOKEN', ''),
        'telegram_chat_id' => env('FEC_TG_DESK_CHAT_ID', ''),

        // Weekday (ISO: 1 = Monday) of the full recap; other days report news only.
        'recap_weekday' => (int) env('FEC_DESK_LINT_RECAP_WEEKDAY', 1),
    ],
];
