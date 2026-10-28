<?php
/**
 * @package     com_content
 * @subpackage  Layouts
 * @copyright   (C) FreeExtraChips.com
 * @license     GPL-2.0-or-later
 *
 * Custom article layout for the homepage v2.
 *
 * This override strips the standard com_content chrome (article title,
 * author/date/hits info, print/email icons, edit link, vote/rating, tags,
 * pagination) and renders ONLY the article body wrapped in a single
 * .fec-home wrapper.
 *
 * The article body for the home page is composed of {fec-*} shortcodes
 * which are replaced server-side by plg_content_fechome at model time
 * (onContentPrepare), so by the time this layout runs $this->item->text
 * already contains the rendered helper HTML.
 *
 * INSTALL:
 *   Copy this file to:
 *     templates/<YOUR_ACTIVE_TEMPLATE>/html/com_content/article/home.php
 *   Then in Menu Manager → Home menu item → Article Layout dropdown,
 *   select "Home".
 *
 * Target: Joomla 2.5, PHP 5.6+
 */

defined('_JEXEC') or die;
?>

<div class="fec-home" itemscope itemtype="https://schema.org/WebPage">
    <?php echo $this->item->text; ?> ciao
    
    {fec-home-hero}

{fec-trust-pulse}

{fec-browse-grid}

{fec-editor-picks}

{fec-latest-bonuses}

{fec-blog-grid}

{fec-latest-casinos}
</div>