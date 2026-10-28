<?php
/**
 * sh404SEF - SEO extension for Joomla!
 *
 * @author      Yannick Gaultier
 * @copyright   (c) Yannick Gaultier 2014
 * @package     sh404SEF
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @version     4.3.0.1671
 * @date		2014-01-23
 */

defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );

/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/


$sefConfig = & Sh404sefFactory::getConfig();

if(isset($task)) {
if ($dosef == false) return;
$dosef = false;
}

$db = JFactory::getDbo();
$query = $db->getQuery(true);
$query->select($db->quoteName(array('jid', 'name', 'cid')));
$query->from($db->quoteName('#__jackpots'));
$query->where($db->quoteName('jid')." = ".$db->quote($jid));
 
$db->setQuery($query);
$result = $db->loadAssoc();

$item = JTable::getInstance("content");
$item->load($cid);
$itemurl = $item->get("title");

$shName = shGetComponentPrefix($option);
$title[] = 'jpots';
$title[] = '';

switch ($view) {

case 'show':
$title[] = '/';
break;

case 'details':
$title[] = '/';
$title[] = $result['name'];
break;

case 'dashboard':
$title[] = '/';
$title[] = '/dashboard/';
break;

case 'tools':
$title[] = '/';
$title[] = '/tools/';
break;

case 'edit':
$title[] = '/';
$title[] = '/edit/';
break;
}




if (count($title) > 0) $string = sef_404::sefGetLocation($string, $title,null);