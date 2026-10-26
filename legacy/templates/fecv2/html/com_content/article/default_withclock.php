<?php
/**
 * @package     FreeExtraChips
 * @subpackage  com_content/article (template override)
 *
 * Custom Joomla 2.5 article view override for the fecv2 template.
 *
 *   - catid === 8 (casino reviews): custom v2 rendering pipeline driven by
 *     fec_common_lib helpers (full cutover, sprint 3 single review page).
 *   - catid === FEC_CLUSTERS_CATID (cluster pages): owner-group hub + grid.
 *   - any other catid: standard Joomla article markup preserved verbatim,
 *     so non-casino content (blog-like articles) is not affected.
 *
 * Sandbox reference: /fec_template_test.php (standalone preview).
 */
defined('_JEXEC') or die;

$catid = (int) $this->item->catid;

// Load the FEC lib early so FEC_CLUSTERS_CATID and the cluster helpers are
// available to the dispatch below. require_once = no-op for the casino branch.
$fecLib = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';
if (is_readable($fecLib)) {
    require_once $fecLib;
}


// ============================================================================
// CASINO REVIEW V2 (catid=8) — custom rendering pipeline
// ============================================================================
if ($catid === 8):

    $libPath = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';
    if (!is_readable($libPath)) {
        echo '<div class="alert alert-error">Library missing. Contact admin.</div>';
        return;
    }
    require_once $libPath;

    // Also load lookup arrays - the lib promotes $countries_array, $bonustype,
    // $currency to globals when present at include scope (used by the bonus
    // strip helper for label resolution). Joomla loads these when com_bonuses
    // is the active component, but in com_content article view it is not.
    $helpersDir = JPATH_SITE . '/components/com_bonuses/helpers';
    foreach (array('countriesarray.php', 'bonustypesarray.php', 'currenciesarray.php') as $fname) {
        $p = $helpersDir . '/' . $fname;
        if (is_readable($p)) require_once $p;
    }
    if (isset($countries_array) && is_array($countries_array)
        && (!isset($GLOBALS['countries_array']) || !is_array($GLOBALS['countries_array']))) {
        $GLOBALS['countries_array'] = $countries_array;
    }
    if (isset($bonustype) && is_array($bonustype)
        && (!isset($GLOBALS['bonustype']) || !is_array($GLOBALS['bonustype']))) {
        $GLOBALS['bonustype'] = $bonustype;
    }
    if (isset($currency) && is_array($currency)
        && (!isset($GLOBALS['currency']) || !is_array($GLOBALS['currency']))) {
        $GLOBALS['currency'] = $currency;
    }

    $cid = (int) $this->item->id;
    $db  = JFactory::getDbo();

    // Build view model
    $vm = fec_get_casino_view_model($db, $cid);
    if (!$vm) {
        echo '<div class="alert alert-error">Casino data not available.</div>';
        return;
    }

    // Country detection (no CF override needed - we're in real Joomla,
    // CF-IPCountry header is present)
    $countryDetect = function_exists('fec_detect_country')
        ? fec_detect_country('US')
        : array('cc_up' => 'US', 'src' => 'default');
    $countryName = function_exists('fec_country_name')
        ? fec_country_name($countryDetect['cc_up'],
            isset($GLOBALS['countries_array']) ? $GLOBALS['countries_array'] : array())
        : '';

    // Decide state once - same object passed to hero, banner, sidebar so they
    // all agree on closed/suspended/blacklisted/geo_blocked + cofm archive.
    $stateData = fec_decide_casino_state($vm, $countryDetect['cc_up']);

    // Render sections in canonical order
    $warningBanner = function_exists('fec_render_casino_warning_banner')
        ? fec_render_casino_warning_banner($vm) : '';
    $stateBanner   = fec_render_casino_state_banner($stateData);
    $hero          = fec_render_casino_hero_a($vm, array('stateData' => $stateData));
    $statsStrip    = fec_render_casino_stats_strip($vm);
    $sidebar       = fec_render_casino_sidebar($vm, array('stateData' => $stateData));
    $bonusStrip    = function_exists('fec_render_casino_bonus_strip')
        ? fec_render_casino_bonus_strip($db, $vm, 3, array(
            'countrycode' => $countryDetect['cc_up'],
            'countryname' => $countryName,
          )) : '';
    $withdrawalSection  = function_exists('fec_render_casino_withdrawal_section')
        ? fec_render_casino_withdrawal_section($vm) : '';
    $jackpotsSection    = function_exists('fec_render_casino_jackpots_section')
        ? fec_render_casino_jackpots_section($db, $vm, array('limit' => 12)) : '';
    $tournamentsSection = function_exists('fec_render_casino_tournaments_section')
        ? fec_render_casino_tournaments_section($db, $vm, array()) : '';
    $otherCasinos       = function_exists('fec_render_other_casinos_section')
        ? fec_render_other_casinos_section($db, $cid, 3) : '';
    $writeReviewSection = function_exists('fec_render_review_form')
        ? fec_render_review_form($vm, array()) : '';
    $reviews            = fec_render_player_reviews_section($vm);

    // Article body - already passed through onContentPrepare by Joomla
    $introClean = isset($this->item->text) ? $this->item->text : '';

    // Inject head meta + JSON-LD via JDocument. The site <head> is already
    // emitted by the parent template; Joomla expects setTitle/setDescription/
    // setMetaData calls (NOT addCustomTag for these, otherwise duplicate /
    // ignored tags). Canonical needs a <link> tag → addCustomTag is correct
    // there. JSON-LD is a <script> tag → addCustomTag.
    $doc = JFactory::getDocument();

    if (function_exists('fec_render_casino_head_meta')) {
        $meta = fec_render_casino_head_meta($vm);
        if (is_array($meta) && !empty($meta)) {
            // Title + description: use JDocument setters so Joomla owns them.
            if (!empty($meta['title']))       $doc->setTitle($meta['title']);
            if (!empty($meta['description'])) $doc->setDescription($meta['description']);

            // Canonical: emit as <link rel="canonical"> (no JDocument setter
            // for canonical in Joomla 2.5, addCustomTag is the standard way).
            if (!empty($meta['canonical'])) {
                $doc->addCustomTag('<link rel="canonical" href="'
                    . htmlspecialchars($meta['canonical'], ENT_QUOTES, 'UTF-8') . '" />');
            }

            // Open Graph: emit raw via addCustomTag with property=. Joomla
            // 2.5's setMetaData() does not properly support the property=
            // attribute - it forces http-equiv= and appends "; charset=utf-8"
            // to the content. Bypassing the setter avoids both bugs.
            if (!empty($meta['og']) && is_array($meta['og'])) {
                foreach ($meta['og'] as $k => $v) {
                    if ($v === '') continue;
                    $doc->addCustomTag(
                        '<meta property="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                      . '" content="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '" />'
                    );
                }
            }

            // Twitter: standard name="twitter:*" - setMetaData works fine
            // for this case (no property= involved).
            if (!empty($meta['twitter']) && is_array($meta['twitter'])) {
                foreach ($meta['twitter'] as $k => $v) {
                    if ($v !== '') $doc->setMetaData($k, $v);
                }
            }
        }
    }

    if (function_exists('fec_render_casino_jsonld')) {
        $jsonLd = fec_render_casino_jsonld($vm);
        if (is_string($jsonLd) && $jsonLd !== '') {
            $doc->addCustomTag($jsonLd);
        }
    }
    ?>

    <div class="fecx">

    <?php if ($warningBanner !== ''): ?>
    <?php echo $warningBanner; ?>
    <?php endif; ?>

    <?php if ($stateBanner !== ''): ?>
    <?php echo $stateBanner; ?>
    <?php endif; ?>

    <?php echo $hero; ?>

    <?php echo $statsStrip; ?>

    <?php if ($bonusStrip !== ''): ?>
    <?php echo $bonusStrip; ?>
    <?php endif; ?>

    <div class="wrap">
      <div class="cr-main">
        <?php echo fec_render_casino_intro($introClean, $vm['title']); ?>
        <?php echo $sidebar; ?>
      </div>
    </div>

    <?php if (!empty($vm['author']) && function_exists('fec_render_author_card')): ?>
    <?php echo fec_render_author_card($vm['author']); ?>
    <?php endif; ?>

    <?php
    // ------------------------------------------------------------------
    // Casino Comparator launcher (FEC Tools No. 02, F3).
    // Assets + version come from the com_bonuses helper (single source:
    // FEC_CMP_VERSION), the band renders client side from the /online-
    // casinos/<alias> path and self-hides when the casino is not in the
    // comparator dataset - hence no server-side section header here.
    // Guarded: a missing helper must never break the review page.
    // ------------------------------------------------------------------
    $cmpHelper = JPATH_SITE . '/components/com_bonuses/helpers/fec_cmp_widget.php';
    if (is_readable($cmpHelper)) {
        require_once $cmpHelper;
        if (function_exists('fec_cmp_add_assets')) {
            fec_cmp_add_assets();
            echo '<div class="wrap"><div id="fecCmpLaunch"></div></div>';
        }
    }
    ?>

    <?php if ($withdrawalSection !== ''): ?>
    <?php echo $withdrawalSection; ?>
    <?php endif; ?>

    <?php
    // ------------------------------------------------------------------
    // FXClock (FEC Tools No. 04): payouts timed by players. Sits right
    // after the desk withdrawal section so the two numbers - desk test
    // and player median - are side by side but never merged. Renders
    // nothing when the brand is not enabled in jos_fec_clock_brands or
    // the FXClock lib (outside the docroot) is missing.
    // Guarded: a missing helper must never break the review page.
    // ------------------------------------------------------------------
    $fxcHelper = JPATH_SITE . '/components/com_bonuses/helpers/fxclock_widget.php';
    if (is_readable($fxcHelper)) {
        require_once $fxcHelper;
        if (function_exists('fxc_widget_render')) {
            fxc_widget_render($cid, isset($vm['title']) ? $vm['title'] : '');
        }
    }
    ?>
    
    <?php if ($jackpotsSection !== ''): ?>
    <?php echo $jackpotsSection; ?>
    <?php endif; ?>

    <?php if ($tournamentsSection !== ''): ?>
    <?php echo $tournamentsSection; ?>
    <?php endif; ?>

    <?php if ($otherCasinos !== ''): ?>
    <?php echo $otherCasinos; ?>
    <?php endif; ?>
    
<?php
if (function_exists('fec_render_casino_sister_map_by_id')) {
    $sisHtml = fec_render_casino_sister_map_by_id($cid);
    if ($sisHtml !== '') { ?>
<section class="section cr-sisters-section">
  <div class="wrap">
    <div class="sec-head">
      <div class="left">
        <span class="eyebrow">Same owner</span>
        <h2 class="sec-title">Sister casinos</h2>
        <p class="sec-sub">Brands run by the same operator &mdash; risk and reputation tend to travel across a group, so a problem at one is worth knowing about here.</p>
      </div>
    </div>
    <?php echo $sisHtml; ?>
  </div>
</section>
<?php }
}
?>

    <?php if ($writeReviewSection !== ''): ?>
    <?php echo $writeReviewSection; ?>
    <?php endif; ?>

    <?php echo $reviews; ?>
    
    </div><!-- /.fecx -->


<?php

// ============================================================================
// CLUSTER PAGE (catid = clusters category) — owner-group hub + member grid
// Article alias = cluster slug. Falls back to the article body if the slug is
// not a featured group in jos_fec_owner_groups.
// ============================================================================
elseif (defined('FEC_CLUSTERS_CATID') && FEC_CLUSTERS_CATID > 0 && $catid === FEC_CLUSTERS_CATID):

    $clusterHtml = function_exists('fec_render_cluster_page')
        ? fec_render_cluster_page(JFactory::getDbo(), $this->item->alias,
                                  isset($this->item->text) ? $this->item->text : '')
        : '';
    echo ($clusterHtml !== '')
        ? $clusterHtml
        : (isset($this->item->text) ? $this->item->text : '');


// ============================================================================
// FALLBACK — Standard Joomla 2.5 article markup (catid != 8)
// Replica of /components/com_content/views/article/tmpl/default.php so any
// non-casino article keeps rendering exactly as it did before this override
// existed. Preserved verbatim from Joomla 2.5 core.
// ============================================================================
else:

    $params  = $this->item->params;
    $info    = $params->get('info_block_position', 0);
?>
<div class="item-page<?php echo $this->pageclass_sfx; ?>" itemscope itemtype="https://schema.org/Article">

    <?php if ($this->params->get('show_page_heading')) : ?>
    <h1>
        <?php echo $this->escape($this->params->get('page_heading')); ?>
    </h1>
    <?php endif; ?>

    <?php if ($params->get('show_title') || $params->get('show_author')) : ?>
    <div class="page-header">
        <?php if ($params->get('show_title')) : ?>
        <h2 itemprop="headline">
            <?php if ($params->get('link_titles') && !empty($this->item->readmore_link)) : ?>
                <a href="<?php echo $this->item->readmore_link; ?>" itemprop="url">
                <?php echo $this->escape($this->item->title); ?></a>
            <?php else : ?>
                <?php echo $this->escape($this->item->title); ?>
            <?php endif; ?>
        </h2>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($this->item->toc)) : ?>
        <?php echo $this->item->toc; ?>
    <?php endif; ?>

    <div itemprop="articleBody">
        <?php echo $this->item->text; ?>
    </div>

    <?php if ($params->get('show_modify_date')) : ?>
    <dl class="article-info muted">
        <dt class="article-info-term"><?php echo JText::_('COM_CONTENT_LAST_UPDATED'); ?></dt>
        <dd><?php echo JHtml::_('date', $this->item->modified, JText::_('DATE_FORMAT_LC2')); ?></dd>
    </dl>
    <?php endif; ?>

</div>
<?php endif; ?>
