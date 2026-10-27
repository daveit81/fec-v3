<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
fec_header('Content-Type: application/json');
// import Joomla controller library
jimport('joomla.application.component.controller');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);
*/




class BonusesControllerMobileapp extends JController
{

function soft()
  {

require_once JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';




include_once("geoip/geoip.inc");
$ip = $_SERVER["REMOTE_ADDR"];
$gi = geoip_open("geoip/GeoIP.dat",GEOIP_STANDARD);
$countrycode = strtolower(preg_replace('/\s+/','',geoip_country_code_by_addr($gi, "$ip")));
$countryname = geoip_country_name_by_addr($gi, "$ip");
$jinput = JFactory::getApplication()->input;
$type = $jinput->get('type');
$subtype = $jinput->get('subtype');
$cid = $jinput->get('cid');

// how many records should be displayed on a page?
$records_per_page = 24;

// instantiate the pagination object
$pagination = new Zebra_Pagination();


$getpage = ($pagination->get_page() - 1) * $records_per_page;

// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query->select(array('SQL_CALC_FOUND_ROWS a.*', 'b.*'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');

if(isset($_GET['view']) && $_GET['view'] == 'casino') {
$query->where($db->quoteName('a.cid') . ' = '.$cid);
$query->where($db->quoteName('a.status') . ' NOT LIKE "pending"');
$query->order('CASE WHEN '.$db->quoteName('a.status').' LIKE "expired" then 1 else 0 end');

} else {

$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');

}
$query->where($db->quoteName('a.bonus') . ' > 0');
$query->order('CASE WHEN '.$db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$countrycode.'*%" then 1 else 0 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_ok').' LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "" then 0 else 1 end');
$query->order('CASE WHEN '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$countrycode.'*%" OR '.$db->quoteName('a.country_no').' LIKE "" then 0 else 1 end');
$query->order($db->quoteName('a.added') . ' DESC');
// Reset the query using our newly populated query object.
$db->setQuery($query,$getpage,$records_per_page);

$row = $db->loadAssocList();

$db->setQuery('SELECT FOUND_ROWS();');
$total = $db->loadResult();


// pass the total number of records to the pagination class
$pagination->records($total);

// records per page
$pagination->records_per_page($records_per_page);

echo json_encode($row);

   
  }
  
  
}
?>