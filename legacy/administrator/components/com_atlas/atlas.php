<?php
/**
 * com_atlas — admin entry point (stub).
 *
 * The Atlas is edited from the FRONTEND dashboard (gated, whitelist),
 * same philosophy as the com_authors editorial cockpit. This backend
 * page exists so the component installs cleanly on Joomla 2.5, shows
 * up under the Components menu, and gives editors a one-click jump.
 */

defined('_JEXEC') or die;

if (!JFactory::getUser()->authorise('core.manage', 'com_atlas')) {
    return JError::raiseWarning(404, JText::_('JERROR_ALERTNOAUTHOR'));
}

JToolBarHelper::title('FEC Operator Atlas', 'generic.png');

$dashUrl = JURI::root() . 'index.php?option=com_atlas&view=dashboard';

$db = JFactory::getDbo();
$counts = array('entities' => 0, 'published' => 0, 'licences' => 0, 'links' => 0);
$db->setQuery("SELECT COUNT(*) FROM #__atlas_entities");
$counts['entities'] = (int)$db->loadResult();
$db->setQuery("SELECT COUNT(*) FROM #__atlas_entities WHERE published = 1");
$counts['published'] = (int)$db->loadResult();
$db->setQuery("SELECT COUNT(*) FROM #__atlas_licences");
$counts['licences'] = (int)$db->loadResult();
$db->setQuery("SELECT COUNT(*) FROM #__atlas_brand_links WHERE date_to IS NULL");
$counts['links'] = (int)$db->loadResult();
?>
<div style="max-width:640px;margin:24px auto;font-size:14px;line-height:1.6">
  <p>
    The Operator Atlas is a structured public register of the legal entities
    behind casino brands: operators, holdings, licences, temporal brand
    ownership and a sourced changelog.
  </p>
  <ul>
    <li><strong><?php echo $counts['entities']; ?></strong> entities (<strong><?php echo $counts['published']; ?></strong> published)</li>
    <li><strong><?php echo $counts['licences']; ?></strong> licence records</li>
    <li><strong><?php echo $counts['links']; ?></strong> current brand links</li>
  </ul>
  <p>
    All editing happens in the gated frontend cockpit (whitelisted editors only):
  </p>
  <p>
    <a class="btn btn-primary" href="<?php echo htmlspecialchars($dashUrl, ENT_QUOTES, 'UTF-8'); ?>">
      Open the Atlas dashboard
    </a>
  </p>
</div>
