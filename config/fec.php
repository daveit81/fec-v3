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

        // jos_redirection (3,840 rows, the URL table of sh404SEF 1.x) as a last resort for unknown
        // paths. Dead on the live site: no plugin of Joomla 2.5 reads it (shsef and jredirect are
        // Joomla 1.5 files the 2.5 loader never includes), and on 29 Oct 2026 the live site answered
        // 404 where these rules made v3 answer 301 (/strategies/, /strategies/blackjack/ and three
        // paths of the access log). Parity means false; the table stays for fec:urls:stats.
        'legacy_redirection' => filter_var(env('FEC_ROUTER_LEGACY_REDIRECTION', false), FILTER_VALIDATE_BOOL),
    ],

    'legacy' => [
        // Virtual docroot of the ported Joomla-era code (JPATH_SITE for the compatibility layer).
        'site_path' => env('FEC_LEGACY_SITE_PATH', app_path('Fec/Legacy/site')),

        // The real legacy docroot (JPATH_ROOT): where the desks store the files
        // they upload (images/rtp, images/blog), served by the live hostname.
        // Code is included from site_path, files are read and written here.
        'docroot' => env('FEC_LEGACY_DOCROOT', '/home/freeextr/public_html'),

        // Joomla configuration values the ported code reads through JFactory::getConfig().
        // "secret" signs the newsletter forms (HMAC) on the live site: same value = same signatures.
        'secret' => env('FEC_LEGACY_SECRET', ''),
        'sitename' => 'FreeExtraChips',

        // The Joomla user id the desk runs as once the redazione logs in on v3 (DESK_USER_ID on the live site).
        'desk_user_id' => (int) env('FEC_LEGACY_DESK_USER_ID', 42),

        // The editions config outside the docroot (FEC_PI_CRON_KEY, FEC_PI_NOTIFY_TO): the snapshot
        // tasks of com_authors read it, and so do fec:payout-index:snapshot / fec:licence-watch:snapshot.
        'editions_config' => env('FEC_LEGACY_EDITIONS_CONFIG', '/home/freeextr/conf/fec_payout_index.php'),

        // Run the two snapshot tasks from the v3 scheduler (routes/console.php). Off until the
        // crontab lines that call them on Joomla are removed: never both.
        'schedule_snapshots' => filter_var(env('FEC_LEGACY_SCHEDULE_SNAPSHOTS', false), FILTER_VALIDATE_BOOL),

        // Component options (JComponentHelper::getParams): com_authors and com_bonuses have none;
        // com_content's are the row of #__extensions on the live site (28 Oct 2026), read by the
        // port of the core component: show_intro=0 makes the article text the full text alone
        // when there is one, the intro text otherwise.
        'params' => [
            'com_content' => [
                'article_layout' => '_:default', 'show_title' => '0', 'link_titles' => '0', 'show_intro' => '0',
                'show_category' => '0', 'link_category' => '0', 'show_parent_category' => '0', 'link_parent_category' => '0',
                'show_author' => '0', 'link_author' => '0', 'show_create_date' => '0', 'show_modify_date' => '0',
                'show_publish_date' => '0', 'show_item_navigation' => '0', 'show_vote' => '0', 'show_readmore' => '0',
                'show_readmore_title' => '0', 'readmore_limit' => '100', 'show_icons' => '0', 'show_print_icon' => '0',
                'show_email_icon' => '0', 'show_hits' => '0', 'show_noauth' => '0', 'urls_position' => '0',
                'show_publishing_options' => '1', 'show_article_options' => '1', 'show_urls_images_frontend' => '0',
                'show_urls_images_backend' => '1', 'targeta' => 0, 'targetb' => 0, 'targetc' => 0,
                'float_intro' => 'left', 'float_fulltext' => 'left', 'category_layout' => '_:default',
                'show_category_title' => '0', 'show_description' => '0', 'show_description_image' => '0',
                'maxLevel' => '-1', 'show_empty_categories' => '0', 'show_no_articles' => '0', 'show_subcat_desc' => '0',
                'show_cat_num_articles' => '0', 'show_base_description' => '0', 'maxLevelcat' => '-1',
                'show_empty_categories_cat' => '0', 'show_subcat_desc_cat' => '0', 'show_cat_num_articles_cat' => '0',
                'num_leading_articles' => '1', 'num_intro_articles' => '4', 'num_columns' => '2', 'num_links' => '4',
                'multi_column_order' => '0', 'show_subcategory_content' => '0', 'show_pagination_limit' => '1',
                'filter_field' => 'hide', 'show_headings' => '1', 'list_show_date' => '0', 'date_format' => '',
                'list_show_hits' => '1', 'list_show_author' => '1', 'orderby_pri' => 'order', 'orderby_sec' => 'rdate',
                'order_date' => 'published', 'show_pagination' => '2', 'show_pagination_results' => '1',
                'show_feed_link' => '0', 'feed_summary' => '0', 'feed_show_readmore' => '0',
            ],
        ],

        // The global metadata options of configuration.php that com_content reads (the site's
        // MetaDesc is the description of a page without one of its own, sitename_pagetitles
        // prefixes or suffixes the site name to every title: 0 on the live site).
        'meta' => [
            'description' => env('FEC_LEGACY_META_DESCRIPTION', ''),
            'keywords' => env('FEC_LEGACY_META_KEYWORDS', ''),
            'rights' => env('FEC_LEGACY_META_RIGHTS', ''),
            'author' => env('FEC_LEGACY_META_AUTHOR', '0'),
            'robots' => env('FEC_LEGACY_META_ROBOTS', ''),
            'sitename_pagetitles' => (int) env('FEC_LEGACY_SITENAME_PAGETITLES', 0),
        ],

        // The review form of the casino pages (fec_render_review_form): the public Turnstile
        // site key it prints and the limits it announces, from the live fec_review_config.php
        // (not in the snapshot: it holds the secret key too, which only the submission endpoint
        // on Joomla needs). Without the site key the form is not rendered, as on the live site.
        'review' => [
            'turnstile_site_key' => env('FEC_TURNSTILE_SITE_KEY', ''),
            'min_length' => (int) env('FEC_REVIEW_MIN_LENGTH', 50),
            'max_length' => (int) env('FEC_REVIEW_MAX_LENGTH', 3000),
            'min_seconds_on_form' => (int) env('FEC_REVIEW_MIN_SECONDS_ON_FORM', 5),
        ],

        // The plugins ported verbatim (site/plugins/<type>/<name>/<name>.php), with the params of
        // their rows in #__extensions: the content plugins run on every article's text
        // (onContentPrepare), the system plugin after the page is rendered (onAfterRender).
        // The third party content plugins of the live site are not ported: fec:parity says which
        // ones change a page, and those are reproduced one by one.
        'plugins' => [
            'content' => ['feccounts', 'fechome'],
            // in Joomla's order: the core SEF plugin (76: href="index.php?..." routed, relative
            // src/href given the base), sh404SEF (79: the layer's "sh404sef", which puts back the
            // canonical Sh404sefPage/Sh404sefMetas wrote, past "sef": Sh404sefOutput),
            // feccanonicalfix (86: the bare-host canonical sh404SEF writes on menu item 26 gets
            // its slash) and fecclustermeta (87)
            'system' => ['sef', 'sh404sef', 'feccanonicalfix', 'fecclustermeta'],
        ],

        // sh404SEF's settings read by its page rewrite and its com_content meta plugin
        // (administrator/components/com_sh404sef/config/config.sef.php on the live site, 28 Oct
        // 2026; removeOtherCanonicals is not in the file: the class default). Used by
        // App\Fec\Legacy\Sh404sefPage; the categories are sh404SEF's codes (0 all nested,
        // 1 first, 2 last, 3 two first, 4 two last, 5 none).
        'sh404sef' => [
            'remove_other_canonicals' => true,
            'page_title_separator' => ' | ',
            'prepend_to_page_title' => '',
            'append_to_page_title' => '',
            'content_title_include_cat' => 5,        // contentTitleIncludeCat: an article's title alone
            'include_content_cat_categories' => 4,   // includeContentCatCategories: a category's last two
            'content_title_use_alias' => false,
            'content_title_use_cat_alias' => false,
            'insert_content_table_name' => false,
            'content_table_name' => '',
            'insert_content_blog_name' => false,
            'content_blog_name' => '',
            'slug_for_uncategorized_content' => 0,
            'page_text' => 'page-%s',                // pageTexts en-GB
            'pagerep' => '-',
            'always_append_items_per_page' => false,
            // the live copy of shDoTitleTags names the home menu item in its canonical branches
            'home_menu_item' => 26,
        ],

        // JRoute for a query the registry does not hold: the port of the component's sh404SEF
        // plugin builds the path (App\Fec\Legacy\Sef) and, when this is on, records the pair in
        // jos_sh404sef_urls as sh404SEF did, so that Joomla resolves the links v3 writes. Off
        // until `fec:sef:check` rebuilds the whole registry of the component without a difference:
        // a wrong row would be served by both halves of the site.
        'sef_register' => filter_var(env('FEC_LEGACY_SEF_REGISTER', false), FILTER_VALIDATE_BOOL),

        // The rules of the legacy public_html/.htaccess (mod_alias "Redirect 301", "RedirectMatch",
        // mod_rewrite "RewriteRule ... [R=301]", [G] 410, [F] 403 query filters) that fall under the paths
        // v3 serves: the web server answered them before Joomla; the v3 block at the top of that file
        // rewrites those paths to v3, whose own .htaccess is the only one the second pass reads, so the
        // router answers them first with each directive's semantics
        // (App\Fec\Url\HtaccessRedirects). File order, targets as written; the line numbers are those
        // of the file on 27 Oct 2026, the rules added on 29 Oct (for the root switch: every path will be
        // v3's) are named by what they match. Line 114 (allowed-countries/au|nz -> show&geo=) is an
        // internal rewrite: the registry holds both spellings of those two pages. Not reproduced: the
        // LiteSpeed cache rules (v3 pages are no-cache), the https redirect (Cloudflare), Joomla's own
        // rules on /index.php (that file stays Joomla's: the root block leaves it out). Listed in file
        // order; HtaccessRedirects answers the mod_alias lines (redirect, redirect-gone, match) before
        // the mod_rewrite rules, as LiteSpeed does on the live server (29 Oct:
        // /casino-bonus-blog/blogger/listings/peterg answers its Redirect to /sitemap, not the [G] above
        // it; 28 Oct: /bonuses/free-cash answered its Redirect under the v3 rewrite).
        'htaccess_redirects' => [
            // 29 Oct, "FEC dead sections purge" (Aug 2026): the jReviews sections are 410
            ['gone', '^(online-casinos/(software|banking|countries|languages|currencies|type)|casino-reviews|reviews|countries)(/.*)?$'],
            ['gone', '^[^/]+/tag(/.*)?$'], // 112: every "<section>/tag/..." path is 410
            ['rewrite', '^bonuses/no-deposit-casinos/allowed-countries/(?!(?:au|nz)/?$)', '/bonuses/no-deposit-casinos'], // 115
            // 29 Oct, the blog switch of 13 Oct (EasyBlog -> com_content, /casino-bonus-blog -> /blog)
            ['rewrite', '^blog-v2/?$', '/blog/'],
            ['rewrite', '^blog-v2/(.+)$', '/blog/$1'],
            ['rewrite', '^casino-bonus-blog/(latest(/page-[0-9]+)?|page-[0-9]+|categories(/.*)?)/?$', '/blog/'],
            ['gone', '^casino-bonus-blog/(tags|blogger|teamblog|archive|dashboard|login|register)(/.*)?$'],
            ['gone', '^casino-bonus-blog/(.+/)?feed(/.*)?$'],
            ['rewrite', '^casino-bonus-blog/nolimit-city-brings-more-chaos-and-bigger-wins-with-punk-rocker-2-slot/?$', '/blog/nolimit-city-unleashes-more-chaos-and-bigger-wins-with-punk-rocker-2-slot'],
            ['rewrite', '^casino-bonus-blog/?$', '/blog/'],
            ['rewrite', '^casino-bonus-blog/(.+)$', '/blog/$1'],
            // 29 Oct, Joomla section: "RewriteCond %{THE_REQUEST} \.html" + "^(.*)\.html$ /$1 [R=301]"
            ['rewrite', '^(.*)\.html$', '/$1'],
            // 301-305: Joomla's stock exploit filters (403), every path
            ['forbidden-query', 'base64_encode[^(]*\([^)]*\)'],
            ['forbidden-query', '(<|%3C)([^s]*s)+cript.*(>|%3E)', 'NC'],
            ['forbidden-query', 'GLOBALS(=|\[|\%[0-9A-Z]{0,2})'],
            ['forbidden-query', '_REQUEST(=|\[|\%[0-9A-Z]{0,2})'],
            ['forbidden-agent', 'AppEngine'], // 345-346
            // Not in the live file: Davey's decisions of 29 Oct for the root switch (phase 4 doc, §3):
            // login, registration and Community Builder, and the jackpot pages of com_jackpots, are 410
            // (on the live site Joomla serves them until the root switch; its data.update task stays)
            ['gone', '^(log-in|login|create-an-account|lost-password|lost-user-name|edit-user-details|view-user-profile|view-your-user-details|forgotten-password|community|register|reset|remind|user-profile|cb-[^/]+|jpots)(/.*)?$'],
            // Davey, 29 Oct (root switch): the jReviews pages still alive on the live site (200) go the
            // way of the August purge, and Joomla's front-end editing (303 to the home or 403 there)
            ['gone', '^(online-casinos/(jurisdictions?|rabobank)|jreviews)(/.*)?$'],
            ['gone', '^([0-9]+/|f/([0-9]+/)?)?create-new-article(/.*)?$'],
            // 462 (^bonuses/free-spins$ -> free-spins/ [R=301]) is dead on the live site: it sits below
            // Joomla's catch-all RewriteRule (.* index.php [L]); the 301 the live site answers comes from
            // the sh404SEF alias "bonuses/free-spins" (Sh404sefAliases), which v3 reads from the table.
            // 29 Oct, the mod_alias lines of the Joomla section
            ['match', '/about-casino-bonuses(.*)', '/bonuses/$1'],
            ['match', '^/casino-bonus-blog/entry/(.*)', '/blog/$1'],
            ['redirect', '/reviews...', 'https://www.freeextrachips.com/'],
            ['match', '/bonuses/ads/(.*)', '/bonuses/ctr/$1'], // 468
            // Davey, 27 Oct 2026: the exclusive filter page goes to the hub (the same RedirectMatch
            // goes into the live file next to line 468, so both halves answer alike)
            ['match', '^/bonuses/exclusive/yes/?$', 'https://www.freeextrachips.com/bonuses/'],
            ['redirect', '/bonuses/7bit-casino/75-free-spins-id7289', 'https://www.freeextrachips.com/bonuses/7bit-casino/75-free-spins-id8676'], // 533
            ['match', '^/bonuses/7bit-casino/75-free-spins-id8157/?$', '/bonuses/7bit-casino/75-free-spins-id8676'], // 534
            ['redirect', '/bonuses/slotland-casino/usd25-no-deposit-bonus-id4048', 'https://www.freeextrachips.com/bonuses/slotland-casino/usd25-no-deposit-bonus-id8252'], // 536
            ['redirect', '/bonuses/currency/btc/', 'https://www.freeextrachips.com/bonuses/crypto'], // 538
            ['redirect', '/bonuses/free-play', 'https://www.freeextrachips.com/bonuses/no-deposit-casinos'], // 540
            ['redirect', '/bonuses/slots-bonuses', 'https://www.freeextrachips.com/bonuses/'], // 542
            ['redirect', '/bonuses/no-deposit-casino/', 'https://www.freeextrachips.com/bonuses/no-deposit-casinos'], // 543
            ['redirect', '/bonuses/no-deposit-spins', 'https://www.freeextrachips.com/bonuses/free-spins/no-deposit/'], // 544
            ['redirect', '/bonuses/extra-spins', 'https://www.freeextrachips.com/bonuses/free-spins/no-deposit/'], // 545
            ['redirect', '/bonuses/sports-and-casino/', 'https://www.freeextrachips.com/bonuses/slots-and-casino/'], // 546
            ['redirect', '/bonuses/high-roller-bonus', 'https://www.freeextrachips.com/bonuses/high-roller-bonuses'], // 547
            ['redirect', '/bonuses/free-cash', 'https://www.freeextrachips.com/bonuses/no-deposit-casinos'], // 558
            // phase 4: the directory's old "newest" path (the search box once linked it)
            ['redirect', '/online-casinos/newest', 'https://www.freeextrachips.com/online-casinos'], // 565
            // 29 Oct, the rest of the mod_alias lines, file order ("Redirect gone /links_exchange", the
            // "redirect 301 ... /sitemap" list, /home, /new-casinos). Four lines of the list carry a query
            // string in their source (/jvcl/search?..., /microgaming-slots/categories/compare/?...,
            // /free-slots/categories/compare/?..., /content?nscode=...): mod_alias compares the path
            // only, they never match, and are left out.
            ['redirect-gone', '/links_exchange'],
            ['redirect', '/video-poker-bonuses', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/.well-known/apple-app-site-association', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/craps.com-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/apple-app-site-association', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/400.shtml', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/cherry-red-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/blackjack-bonuses', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/.well-known/assetlinks.json', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/win-a-day-casino.htmlWin', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/slots-bonuses', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/card-games/rules/pai-gow-rules', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/index/list-forums/casino-bonus-forum/', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/urllist.txt', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/table-games/rules/craps-rules', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/jvlinx/gambling_related_websites/index', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/categories/listings/no-deposit/feed/atom', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/super-slots-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/fortune-reel-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/images/easyblog_', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/whosonline', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/7-general-casino-discussion/page-2', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/table-games/rules/', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/card-games/rules/blackjack-switch-rules', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/categories/listings/casino-tournaments/feed/atom', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/del-rio-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/millionaire-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/blogger/listings/peterg', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/blogger/listings/giggles7p/Page-1', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/com_easyblog_sh404_view_search', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/f1x2games-casinos', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/prv/entry/coolcat-casino-new-100-free-chips', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/table/strategies/blackjack/', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/slots-oasis', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/categories/listings/coupon-codes/feed/atom', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/noiq-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/jvlinx/index', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/casino-bonus-blog/html/Page-2', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/card-games/rules/baccarat-rules', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/prv-v15-379', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/card-games/rules/blackjack-surrender-rules', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/7-general-casino-discussion/266-sunshine', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/Casino-Bonus-Blog/entry/no-deposit/rialto-casino-is-now-live-on-fec-with-a-10-free-play-bonus-code', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/slots-ville', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/club-vegas-usa', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/rushmore-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/banking/banking/paypal-casinos', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/index.php', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/home', 'https://www.freeextrachips.com/'],
            ['redirect', '/new-casinos', 'https://www.freeextrachips.com/online-casinos'],
            // 28 Oct 2026: the methodology hub linked its six page-kind topics under /methodology/<slug>,
            // which the methodology view refuses (404); sh404SEF registered those URLs (rank 0) and they
            // were crawled. The hub now routes them to the page view; these 301s send the registered
            // spellings to the pages (the same RedirectMatch lines are in the live file, next to the
            // exclusive/yes one, for the days /methodology is still Joomla's).
            ['match', '^/methodology/responsible-gambling/?$', 'https://www.freeextrachips.com/responsible-gambling'],
            ['match', '^/methodology/privacy-policy/?$', 'https://www.freeextrachips.com/privacy-policy'],
            ['match', '^/methodology/affiliate-disclosure/?$', 'https://www.freeextrachips.com/affiliate-advertiser-disclosure'],
            ['match', '^/methodology/fast-payouts/?$', 'https://www.freeextrachips.com/online-casinos/fast-payouts'],
            ['match', '^/methodology/licence-watch/?$', 'https://www.freeextrachips.com/online-casinos/licence-watch'],
            ['match', '^/methodology/welcome-packages/?$', 'https://www.freeextrachips.com/bonuses/welcome-packages'],
        ],

        // Public "option.view" keys that write (the tracked redirect of com_bonuses logs the click,
        // as goto.php does): served with the fec_rw connection without a login.
        'writing_views' => ['com_bonuses.ads'],

        // sh404SEF's custom 404 page: the jos_content article shown, in the shell, for unknown
        // paths (id 273, "__404__") under the fixed page title "404".
        'not_found_article_id' => (int) env('FEC_LEGACY_NOT_FOUND_ARTICLE', 273),
        'not_found_title' => env('FEC_LEGACY_NOT_FOUND_TITLE', '404'),

        // Desk (non-public) views: need a logged-in member of the redazione, run on the
        // writing connection, skipped by fec:parity --option.
        'desk_views' => [
            'com_authors.dashboard', 'com_authors.blogdesk', 'com_authors.deskadd', 'com_authors.fxshieldadmin', 'com_authors.methodologyadmin', 'com_authors.verification',
            // com_bonuses (phase 3): the bonus desk, gated to the dashboard user on the live
            // site too; "actions" is the controller of its tasks (task=actions.save...). The
            // old editor (view=edit) is not ported: dead on the live site, linked from nowhere.
            'com_bonuses.home', 'com_bonuses.add', 'com_bonuses.manage', 'com_bonuses.clicks', 'com_bonuses.actions',
        ],

        // The primary navigation from jos_menu (App\Fec\Legacy\MenuModule: mod_menu of the fec_nav
        // position through the fecv2 override); false serves the snapshot of 25 Oct 2026.
        'nav_from_menu' => filter_var(env('FEC_LEGACY_NAV_FROM_MENU', true), FILTER_VALIDATE_BOOL),
        'nav_position' => 'fec_nav',

        // Phase 4, the root switch (App\Fec\Url\RootSwitch, fec:urls:htaccess --root): what stays on
        // Joomla when every other path goes to v3. Besides files, directories, task=/option= requests
        // and the desk views above: these components and views (their registry URLs, as prefixes no
        // other URL shares) and these fixed paths (dashboards/: the React reviews desk, the Atlas
        // dashboard and the other menu items under it; component/: Joomla's non-SEF paths, which its
        // catch-all routes whatever their extension).
        'root_keep' => [
            // com_contact: /freeextrachips-team (Davey, 29 Oct)
            'options' => ['com_contactus', 'com_contact', 'com_partnerdesk', 'com_fecsearch', 'com_fecapi'],
            'views' => ['com_atlas.dashboard'],
            'paths' => ['administrator', 'component', 'index.php', 'dashboards'],
        ],

        // SEF paths of the desks (registry URLs): explicit routes in the web group, CSRF handled by the legacy token.
        'desk_paths' => ['methodology-admin', 'fxshield-admin', 'fxshield-verify'],

        // The desks' standalone endpoints: components/<option>/ajax/<name>.php on the live
        // site, scripts that bootstrapped Joomla themselves and that the desk pages call by
        // that path (the click feed, the FTD entry and import of the clicks desk). Served at
        // the same path on v3, to a logged-in member of the redazione, as whole scripts
        // (ComponentRunner::runScript, raw output).
        'desk_scripts' => [
            'com_bonuses' => ['clicks_live', 'ftd_save', 'ftd_import'],
        ],

        // The /desk home page: what the redazione can open on v3.
        'desks' => [
            ['title' => 'Methodology admin', 'url' => '/methodology-admin', 'note' => 'Topic, pagine e metadati di /methodology e delle pagine editoriali'],
            ['title' => 'FXShield cockpit', 'url' => '/fxshield-admin', 'note' => 'Stato FXShield dei casino, ricalcolo, export markdown'],
            ['title' => 'FXShield verification', 'url' => '/fxshield-verify', 'note' => 'Wizard di verifica first-party con evidenze'],
            ['title' => 'Desk add (baseline)', 'url' => '/index.php?option=com_authors&view=deskadd', 'note' => 'Inserimento baseline FXShield'],
            ['title' => 'RTP dashboard', 'url' => '/index.php?option=com_authors&view=dashboard', 'note' => 'Database slot e RTP (utente 42)'],
            ['title' => 'Blog desk', 'url' => '/index.php?option=com_authors&view=blogdesk', 'note' => 'Editor del Casino Bonus Blog (post in jos_content con il nodo asset, meta in jos_fec_blog_meta)'],
            ['title' => 'Bonus dashboard', 'url' => '/index.php?option=com_bonuses&view=home', 'note' => 'Overview del catalogo bonus: KPI, in scadenza, pending, modifiche recenti (utente 42)'],
            ['title' => 'Bonus list', 'url' => '/index.php?option=com_bonuses&view=manage', 'note' => 'Lista bonus con filtri, toggle, duplica, ripristina, azioni bulk'],
            ['title' => 'Add a bonus', 'url' => '/index.php?option=com_bonuses&view=add', 'note' => 'Form di inserimento/modifica bonus con welcome package FXRate'],
            ['title' => 'Click report', 'url' => '/index.php?option=com_bonuses&view=clicks', 'note' => 'Click per brand e paese, feed live, FTD StatsDrone (import CSV da /components/com_bonuses/ajax/ftd_import.php)'],
        ],
    ],

    'parity' => [
        // Origin IP used to fetch the live page past Cloudflare (curl --resolve); empty = public DNS.
        'origin_ip' => env('FEC_ORIGIN_IP', '170.249.222.58'),
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
