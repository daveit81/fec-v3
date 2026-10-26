<?php
defined('_JEXEC') or die;

/**
 * Page view — default fallback template.
 *
 * Renders when a topic config has no `tmpl` field, or when parent::display()
 * is called without a layout suffix. Status='live' topics should always ship
 * their own tmpl/default_<slug>.php, so reaching this template usually means
 * a configuration mistake. Treat as a defensive 404.
 */
?>
<div class="fecx rg-page">
<section class="au-hero rg-hero">
    <div class="wrap">
        <div class="eyebrow">Page not found</div>
        <h1 class="au-hero-title">This page isn't available</h1>
        <p class="au-hero-lede">The editorial page you requested doesn't exist or hasn't been published yet. If you reached this URL from a link on our site, please <a href="mailto:editorial@freeextrachips.com">let us know</a> so we can fix it.</p>
        <p style="margin-top: 1.5rem;">
            <a href="<?php echo JRoute::_('index.php?option=com_authors&view=authors'); ?>" class="btn btn-primary">See our editorial team</a>
            <a href="<?php echo JRoute::_('index.php?option=com_authors&view=methodologyhub'); ?>" class="btn btn-ghost">How we verify bonuses</a>
        </p>
    </div>
</section>
</div>
