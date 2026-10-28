<?php
/**
 * @package     plg_content_fechome
 * @subpackage  Content
 * @copyright   (C) FreeExtraChips.com
 * @license     GPL-2.0-or-later
 *
 * Replaces {fec-*} shortcodes on the homepage Joomla article with the
 * rendered output of helper functions defined in:
 *   components/com_bonuses/helpers/fec_common_lib.php
 *
 * Shortcodes (place inside the home article body):
 *   {fec-home-hero}      → fec_render_home_hero_section()
 *   {fec-trust-pulse}    → fec_render_trust_pulse_strip()
 *   {fec-browse-grid}    → fec_render_browse_by_type_section()
 *   {fec-editor-picks}   → fec_render_editor_picks_section()
 *   {fec-latest-bonuses} → fec_render_latest_bonuses_section()
 *   {fec-blog-grid}      → fec_render_blog_latest_section()
 *   {fec-latest-casinos} → fec_render_latest_casinos_section()
 *
 * Target: Joomla 2.5, PHP 5.6+. No closures-in-class-property weirdness;
 * uses standard JPlugin patterns.
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgContentFechome extends JPlugin
{
    /**
     * Loaded once per request (static survives across multiple article
     * onContentPrepare invocations on a single page render).
     */
    private static $helpersLoaded = false;

    /** Cached country code (per-request) */
    private static $cachedCC = null;
    private static $cachedCN = null;

    /**
     * Joomla content plugin entry point.
     *
     * @param string  $context  e.g. 'com_content.article'
     * @param object  &$article The content item (has ->text, ->alias, ->id, etc)
     * @param object  &$params  Joomla content params (unused here)
     * @param int     $page     Page number for multi-page articles
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        // Only process com_content articles (article view, category list,
        // featured, archive). Skip everything else (modules, search, etc.)
        if (strpos((string)$context, 'com_content') !== 0) {
            return true;
        }

        // Only process the home article (configurable). To process every
        // article, set "Process all articles" = Yes in the plugin params.
        $homeAlias     = (string)$this->params->get('home_alias', 'home');
        $enabledForAll = (int)$this->params->get('enabled_for_all', 0);

        if (!$enabledForAll) {
            $alias = isset($article->alias) ? (string)$article->alias : '';
            if ($alias === '' || $alias !== $homeAlias) {
                return true;
            }
        }

        // Cheap early-out: if no {fec-* shortcode in the text, do nothing.
        if (!isset($article->text) || strpos((string)$article->text, '{fec-') === false) {
            return true;
        }

        // Load helpers (idempotent — safe to call repeatedly)
        if (!$this->loadHelpers()) {
            error_log('plg_content_fechome: failed to load fec_common_lib.php — check components/com_bonuses/helpers/');
            return true;
        }

        // Process the body
        $article->text = $this->processShortcodes((string)$article->text);

        return true;
    }

    /**
     * Loads fec_common_lib.php + the 3 helper arrays from
     * components/com_bonuses/helpers/. Returns true on success.
     */
    private function loadHelpers()
    {
        if (self::$helpersLoaded) {
            return true;
        }

        $dir = JPATH_SITE.'/components/com_bonuses/helpers';
        $needed = array(
            'fec_common_lib.php',
            'countriesarray.php',
            'bonustypesarray.php',
            'currenciesarray.php',
        );

        foreach ($needed as $f) {
            $path = $dir.'/'.$f;
            if (!is_file($path)) {
                return false;
            }
            require_once $path;
        }

        self::$helpersLoaded = true;
        return true;
    }

    /**
     * Walks the configured shortcode → callable map, replacing each present
     * shortcode with its rendered HTML. Missing shortcodes are skipped (no
     * unnecessary helper calls). Helper exceptions are caught and logged so
     * one broken section doesn't break the page.
     */
    private function processShortcodes($text)
    {
        $db = JFactory::getDbo();

        // Resolve country once per request
        if (self::$cachedCC === null) {
            self::$cachedCC = function_exists('fec_detect_country')
                ? (string)fec_detect_country()
                : 'us';
            global $countries_array;
            $upperCC = strtoupper(self::$cachedCC);
            self::$cachedCN = (is_array($countries_array) && isset($countries_array[$upperCC]))
                ? (string)$countries_array[$upperCC]
                : '';
        }
        $cc = self::$cachedCC;
        $cn = self::$cachedCN;

        // Arrays from the included helper files (set as globals when included)
        global $bonustype, $currency;

        // Shortcode → closure map. Each closure wraps a helper with its
        // specific signature so the loop below can call them uniformly.
        $handlers = array(

            'fec-home-hero' => function() use ($db) {
                return fec_render_home_hero_section($db);
            },

            'fec-trust-pulse' => function() use ($db) {
                return fec_render_trust_pulse_strip($db);
            },

            'fec-browse-grid' => function() use ($db, $cc) {
                return fec_render_browse_by_type_section($db, $cc);
            },

            'fec-editor-picks' => function() use ($db, $cc, $cn, $bonustype, $currency) {
                $picks = fec_fetch_editor_picks($db, $cc, 3);
                if (empty($picks)) return '';
                return fec_render_editor_picks_section($picks, $cc, $cn, $bonustype, $currency);
            },

            'fec-latest-bonuses' => function() use ($db, $cc, $cn, $bonustype, $currency) {
                return fec_render_latest_bonuses_section($db, $cc, $cn, $bonustype, $currency, 6);
            },

            'fec-blog-grid' => function() use ($db) {
                return fec_render_blog_latest_section($db, 3);
            },

            'fec-latest-casinos' => function() use ($db) {
                return fec_render_latest_casinos_section($db, 4);
            },
        );

        foreach ($handlers as $code => $cb) {
            $token = '{'.$code.'}';
            if (strpos($text, $token) === false) {
                continue; // shortcode not used on this page — skip helper call
            }

            $html = '';
            try {
                $html = $cb();
                if (!is_string($html)) $html = '';
            } catch (Exception $e) {
                error_log('plg_content_fechome shortcode '.$code.': '.$e->getMessage());
                $html = '';
            }

            $text = str_replace($token, $html, $text);
        }

        return $text;
    }
}
