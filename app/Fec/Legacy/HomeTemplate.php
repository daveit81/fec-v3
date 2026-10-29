<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\Document;
use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\Uri;
use Exception;

/**
 * The $isHome branch of templates/fecv2/index.php: the home is not the
 * output of a component. Joomla ran the home menu item's component (whose
 * head stays: robots, keywords, what its plugins added) and then the
 * template, which set the title and the description, printed the Open Graph
 * and Twitter tags after the head, and rendered the sections with the lib
 * in place of the component, with "is-home" on the body and
 * "fec-main--home" on <main>. Same code, same order, same values; sh404SEF's
 * page rewrite and the system plugins then run on the page as on any other.
 */
final class HomeTemplate
{
    public const TITLE = 'Casino Bonuses Verified by Real Players | FreeExtraChips';

    public const DESCRIPTION = "Compare verified casino bonuses, free spins, and no deposit offers. We focus on wagering, max cashout, and country rules \u{2014} with real player feedback via FXCheck\u{2122}.";

    public const URL = 'https://www.freeextrachips.com/';

    public const OG_IMAGE = 'https://www.freeextrachips.com/images/og/og-home-1200x630.png';

    public const OG_IMAGE_ALT = "FreeExtra \u{00b7} Chips \u{2014} Casino bonuses verified by real players";

    /**
     * Lines 16-26 and 72 of index.php, which every page runs before its own
     * sections: the template's stylesheets and scripts. The component's assets
     * came first on every other page (the component ran before the template)
     * and the layout prints them from the document before its own fixed lines;
     * on the home the sections run inside the template, after these, so the
     * document holds the template's assets first and the layout prints no fixed
     * line of its own (see layouts/fecx.blade.php).
     */
    public static function templateAssets(Document $doc): void
    {
        $doc->addStyleSheet(Uri::base(true).'/css/fecv2.css');
        $doc->addScript(Uri::base(true).'/js/fecv2.js', 'text/javascript', true);
        $doc->addScript(Uri::root(true).'/js/scrollspy.js?v=1', 'text/javascript', true);
        $doc->addStyleSheet(Uri::base(true).'/css/fec-edition.css');
    }

    /** Lines 74-99 of index.php: the homepage SEO overrides, set before the head is rendered. */
    public static function head(Document $doc): void
    {
        self::templateAssets($doc);
        $doc->setTitle(self::TITLE);
        $doc->setMetaData('description', self::DESCRIPTION);
    }

    /**
     * Lines 174-196: emitted as raw HTML right after <jdoc:include type="head" />
     * (Joomla 2.5's setMetaData() cannot write property="og:...").
     */
    public static function openGraph(): string
    {
        $e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            "  <!-- Open Graph (home) \u{2014} emitted as raw HTML because Joomla 2.5's",
            '       setMetaData() can\'t produce property="og:..." reliably. -->',
            '  <meta property="og:title" content="'.$e(self::TITLE).'">',
            '  <meta property="og:description" content="'.$e(self::DESCRIPTION).'">',
            '  <meta property="og:url" content="'.$e(self::URL).'">',
            '  <meta property="og:type" content="website">',
            '  <meta property="og:site_name" content="FreeExtraChips">',
            '  <meta property="og:locale" content="en_US">',
            '  <meta property="og:image" content="'.$e(self::OG_IMAGE).'">',
            '  <meta property="og:image:secure_url" content="'.$e(self::OG_IMAGE).'">',
            '  <meta property="og:image:type" content="image/png">',
            '  <meta property="og:image:width" content="1200">',
            '  <meta property="og:image:height" content="630">',
            '  <meta property="og:image:alt" content="'.$e(self::OG_IMAGE_ALT).'">',
            '',
            "  <!-- Twitter Card (home) \u{2014} large image, matches OG image 1200x630. -->",
            '  <meta name="twitter:card" content="summary_large_image">',
            '  <meta name="twitter:title" content="'.$e(self::TITLE).'">',
            '  <meta name="twitter:description" content="'.$e(self::DESCRIPTION).'">',
            '  <meta name="twitter:image" content="'.$e(self::OG_IMAGE).'">',
            '  <meta name="twitter:image:alt" content="'.$e(self::OG_IMAGE_ALT).'">',
        ]);
    }

    /**
     * Lines 287-386: the sections, in the template's order, with its country
     * (Cloudflare's header, "us" without one) and its fallback message when a
     * section throws. Echoed output and returned strings alike, as printed.
     */
    public static function sections(): string
    {
        $fecHelpersDir = JPATH_SITE.'/components/com_bonuses/helpers';
        require_once $fecHelpersDir.'/fec_common_lib.php';
        // the template required these into its own scope: the three arrays it passes on
        $countries_array = [];
        $bonustype = [];
        $currency = [];
        include $fecHelpersDir.'/countriesarray.php';
        include $fecHelpersDir.'/bonustypesarray.php';
        include $fecHelpersDir.'/currenciesarray.php';

        // Country detection (lines 110-134): Cloudflare's CF-IPCountry header, GEOIP last.
        $countryCode = '';
        if (! empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $countryCode = $_SERVER['HTTP_CF_IPCOUNTRY'];
        } elseif (! empty($_SERVER['CF_IPCOUNTRY'])) {
            $countryCode = $_SERVER['CF_IPCOUNTRY'];
        } elseif (getenv('GEOIP_COUNTRY_CODE')) {
            $countryCode = getenv('GEOIP_COUNTRY_CODE');
        }
        $countryCode = strtoupper(trim((string) $countryCode));
        if (! preg_match('/^[A-Z]{2}$/', $countryCode) || $countryCode === 'XX' || $countryCode === 'T1') {
            $countryCode = '';
        }

        $db = Factory::getDbo();
        $cc = strtolower(trim($countryCode));
        if ($cc === '' || strlen($cc) !== 2) {
            $cc = 'us';
        }
        $cn = isset($countries_array[strtoupper($cc)]) ? (string) $countries_array[strtoupper($cc)] : '';

        $level = ob_get_level();
        ob_start();
        LegacyErrors::begin('home');
        try {
            echo fec_render_home_jsonld([
                'logo' => [
                    'url' => '/images/logo-new-extended.png',
                    'width' => 512,
                    'height' => 512,
                ],
                'same_as' => [
                    'https://x.com/FreeExtraChips',
                    'https://www.youtube.com/@freeextrachips',
                    (function_exists('fec_telegram_url') ? fec_telegram_url() : 'https://t.me/freeextrachips'),
                    (function_exists('fec_discord_url') ? fec_discord_url() : 'https://discord.gg/BgCyVu7VvQ'),
                ],
                'search_url' => '/search?q={search_term_string}',
            ]);

            echo fec_render_home_hero_section($db, $cc, $cn, $bonustype, $currency);

            if (function_exists('fec_render_trending_section')) {
                echo fec_render_trending_section($db, $cc, $cn, $bonustype, $currency, 3, ['shell' => 'band']);
            }

            echo fec_render_browse_by_type_section($db, $cc);
            echo fec_render_latest_bonuses_section($db, $cc, $cn, $bonustype, $currency, 12);

            $fxrHomeHelper = JPATH_SITE.'/components/com_bonuses/helpers/fec_fxrate_render.php';
            if (is_readable($fxrHomeHelper)) {
                require_once $fxrHomeHelper;
                if (function_exists('fec_fxrate_render_home_section')) {
                    echo fec_fxrate_render_home_section($db, ['cc' => $cc, 'country' => $cn]);
                }
            }

            echo fec_render_latest_casinos_section($db, 3);
            if (function_exists('fec_render_fxcheck_activity_section')) {
                echo fec_render_fxcheck_activity_section($db, 7);
            } else {
                echo '<!-- DEBUG: fec_render_fxcheck_activity_section() is NOT loaded in fec_common_lib.php -->';
            }

            $fxcHomeHelper = JPATH_SITE.'/components/com_bonuses/helpers/fxclock_widget.php';
            if (is_readable($fxcHomeHelper)) {
                require_once $fxcHomeHelper;
                if (function_exists('fxc_widget_render_home_band')) {
                    fxc_widget_render_home_band(['hide_when_empty' => false]);
                }
            }
            echo fec_render_trust_pulse_strip($db);
            echo fec_render_blog_latest_section($db, 3);
            echo fec_render_home_faq_section();
            echo fec_render_email_capture_block('home_block');
        } catch (Exception $e) {
            // what index.php caught (a PHP 8 Error is not one: it fails the page, as a fatal did)
            error_log('Homepage render failed: '.$e->getMessage());
            echo '<div class="fec-container" style="padding:40px 16px">';
            echo '<p>The homepage is temporarily unavailable. Please try again in a moment.</p>';
            echo '</div>';
        } finally {
            LegacyErrors::end();
        }
        $out = '';
        while (ob_get_level() > $level) {
            $out = (string) ob_get_clean().$out;
        }

        return $out;
    }
}
