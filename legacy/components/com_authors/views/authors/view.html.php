<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/helpers/authors.php';

/**
 * Authors view — listing (Editorial Team page).
 *
 * FEC V2.1 Editorial Team migration: this view now drives the new tm-*
 * template. Three additions vs. the legacy version:
 *   1. Augments each author row with derived fields used by the template:
 *      initials, avatar_hue, stats {bonuses,casinos,latest_fmt}, recent[].
 *   2. Fetches site-wide hero stats from the model (one DB round-trip per
 *      metric, 4 total — fine for a once-per-pageview hero band).
 *   3. Loads the active menu item's params (JRegistry) so admin-editable
 *      text fields (hero copy, principles, contact CTA) come from the menu
 *      manager, not the template file.
 *
 * Helpers from fec_common_lib.php (initials, avatar hue) are available
 * globally because index.php require_once's the lib at the top.
 */
class AuthorsViewAuthors extends JView
{
    public $items        = array();
    public $heroStats    = array();
    public $params       = null;
    public $bonustypeMap = array();
    public $currencyMap  = array();

    public function display($tpl = null)
    {
        // FEC V2.1 Editorial Team — load the lib + helper maps NOW (not at
        // file scope above). Joomla 2.5 runs the component view before the
        // template index.php, so the lib load at index.php L38-39 is too
        // late for our tmpl/default.php to call fec_casino_chip() etc. We
        // require_once defensively here; PHP's once-guard makes the second
        // load (by index.php later in the request) a no-op.
        $fecBonusHelpers = JPATH_SITE . '/components/com_bonuses/helpers';
        if (file_exists($fecBonusHelpers . '/fec_common_lib.php')) {
            require_once $fecBonusHelpers . '/fec_common_lib.php';
        }
        if (file_exists($fecBonusHelpers . '/bonustypesarray.php')) {
            require_once $fecBonusHelpers . '/bonustypesarray.php';
        }
        if (file_exists($fecBonusHelpers . '/currenciesarray.php')) {
            require_once $fecBonusHelpers . '/currenciesarray.php';
        }
        // The helper files define $bonustypeMap / $currencyMap at their own
        // file scope, which here = THIS method's local scope (require_once
        // from inside a method puts the included file's top-level vars into
        // that method's scope). Capture into class properties so the
        // template can read them via $this->*.
        //
        // FALLBACK: if the helper files weren't found or use different
        // variable names, populate minimal maps inline so fec_bonus_full_title
        // can render "Free Spins" instead of bare type code "fs", "$25" instead
        // of "25", etc. The canonical helper files will override these when
        // present (assignment below only fills if loaded-result is empty).
        $bonustypeMapFallback = array(
            'nd'  => 'No Deposit',
            'fs'  => 'Free Spins',
            'su'  => '1st Deposit Match',
            '1st' => '1st Deposit',
            'rl'  => 'Reload',
            'cb'  => 'Cashback',
            'fp'  => 'Free Play',
            'fc'  => 'Free Cash',
            'hr'  => 'High Roller',
        );
        $currencyMapFallback = array(
            'USD' => '$',
            'EUR' => '&euro;',
            'GBP' => '&pound;',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'NZD' => 'NZ$',
        );
        $loadedBonustype = (isset($bonustypeMap) && is_array($bonustypeMap) && !empty($bonustypeMap))
            ? $bonustypeMap : array();
        $loadedCurrency  = (isset($currencyMap)  && is_array($currencyMap)  && !empty($currencyMap))
            ? $currencyMap  : array();
        // Merge: loaded map wins on key collision (helper files are canonical)
        $this->bonustypeMap = $loadedBonustype + $bonustypeMapFallback;
        $this->currencyMap  = $loadedCurrency  + $currencyMapFallback;

        // Casino Bonus Blog helper (V2.7.65): post routes and thumbnails for
        // the team card. Optional — AuthorsHelper falls back without it.
        AuthorsHelper::loadBlogHelper();

        $model = $this->getModel('authors');
        $items = $model->getItems();

        // Augment each author with template-ready derived fields.
        foreach ($items as &$a) {
            $authorId = isset($a['id']) ? (int)$a['id'] : 0;

            // Initials and avatar hue — from fec_common_lib (component-agnostic).
            $a['initials'] = function_exists('fec_initials_from_name')
                ? fec_initials_from_name(isset($a['name']) ? $a['name'] : '')
                : '';
            $a['avatar_hue'] = function_exists('fec_avatar_hue_from_type')
                ? fec_avatar_hue_from_type(isset($a['author_type']) ? $a['author_type'] : '')
                : 220;

            // Per-author stats (single combined query in the model).
            $a['stats'] = $model->getStatsForAuthor($authorId);

            // Per-author last N bonuses with brand from JReviews.
            $a['recent'] = $model->getRecentForAuthor($authorId, 3);

            // Casino Bonus Blog footprint (V2.7.65): post count for the stats
            // row and, when the author has no recent bonuses, the last 3
            // posts for the "Recent work" list. Posts are linked through
            // #__content.author_id (fallback created_by = joomla_user_id).
            $juid = isset($a['joomla_user_id']) ? (int)$a['joomla_user_id'] : 0;
            $blog = $model->getBlogStatsForAuthor($authorId, $juid);
            $a['stats']['blog_posts'] = (int)$blog['posts'];
            if ((int)$blog['latest_ts'] > (int)$a['stats']['latest_ts']) {
                $a['stats']['latest_ts']  = (int)$blog['latest_ts'];
                $a['stats']['latest_fmt'] = date('M Y', (int)$blog['latest_ts']);
            }
            $a['recent_blog'] = ((int)$blog['posts'] > 0 && empty($a['recent']))
                ? $model->getRecentBlogForAuthor($authorId, $juid, 3)
                : array();

            // "Years at FreeExtraChips" derived from joined_year column.
            // Show only when joined_year is set; otherwise the template hides
            // the tile gracefully.
            $jy = isset($a['joined_year']) ? (int)$a['joined_year'] : 0;
            $a['years_at_fec'] = ($jy > 0 && $jy <= (int)date('Y'))
                ? ((int)date('Y') - $jy)
                : 0;
        }
        unset($a);

        $this->items     = $items;
        $this->heroStats = $model->getHeroStats();

        // Load active menu item params (Joomla 2.5 — JRegistry).
        $app    = JFactory::getApplication();
        $menu   = $app->getMenu();
        $active = $menu ? $menu->getActive() : null;
        if ($active && isset($active->params) && is_object($active->params)) {
            $this->params = $active->params;
        } else {
            // Fallback: empty JRegistry so $this->params->get(...) calls in
            // the template don't fatal when the page is opened outside a
            // configured menu item.
            jimport('joomla.registry.registry');
            $this->params = new JRegistry();
        }

        $doc = JFactory::getDocument();
        // Title: prefer menu-configured page title via params, fallback to default.
        $pageTitle = $this->params->get('page_title', '');
        if (trim($pageTitle) === '') $pageTitle = 'Editorial Team — FreeExtraChips';
        $doc->setTitle($pageTitle);

        $metaDesc = $this->params->get('meta_description', '');
        if (trim($metaDesc) !== '') $doc->setMetaData('description', $metaDesc);

        // bylines.css (V2.7.59): the byline policy box under the Bylines
        // section head. Tiny file, same cache-buster pattern as methodology.css.
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/bylines.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/bylines.css') ?: 1)
        );

        parent::display($tpl);

        // Legacy CSS asset removed: the new template is .fecx-scoped and
        // styled by fec-edition.css (loaded centrally by index.php when
        // fec_is_fecx_team() returns true). The old per-component CSS file
        // (assets/authors.css) stays on disk for now but is no longer linked.
    }
}