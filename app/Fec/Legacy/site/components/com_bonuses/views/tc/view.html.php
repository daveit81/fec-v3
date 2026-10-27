<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');


/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/


// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class BonusesViewTc extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
	
$doc = JFactory::getDocument();
$doc->setMetaData('robots', 'noindex,nofollow');
$jinput = JFactory::getApplication()->input;

$bid = $jinput->get('bid','','int');
$cid = $jinput->get('cid','','int');

echo $bid;
echo $cid;

$db = JFactory::getDbo();
$query = $db->getQuery(true);
$query->select($db->quoteName(array('tc')));
$query->from($db->quoteName('#__bonuses'));
$query->where($db->quoteName('bid')." = ".$db->quote($bid));
 
$db->setQuery($query);
$link = $db->loadResult();

header("Location: " . $link);

parent::display($tpl);
	}
}

?>