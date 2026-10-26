<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

if(!defined('DS')){
    define('DS',DIRECTORY_SEPARATOR);
}
 
// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class BonusesViewEdit extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
		// Assign data to the view
				
	
require_once JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'casinosarray.php';

include_once("geoip/geoip.inc");
$ip = $_SERVER["REMOTE_ADDR"];
$gi = geoip_open("geoip/GeoIP.dat",GEOIP_STANDARD);
$countrycode = strtolower(preg_replace('/\s+/','',geoip_country_code_by_addr($gi, "$ip")));
$countryname = geoip_country_name_by_addr($gi, "$ip");
$jinput = JFactory::getApplication()->input;
$cid = $jinput->get('cid');
$status = $jinput->get('status','','raw');


// how many records should be displayed on a page?
$records_per_page = 24;

// instantiate the pagination object
$pagination = new Zebra_Pagination();


$getpage = ($pagination->get_page() - 1) * $records_per_page;

// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query->select(array('SQL_CALC_FOUND_ROWS a.*'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->order($db->quoteName('a.added') . ' DESC');

if(!empty($cid)) {
$query->where($db->quoteName('a.cid') . ' = '.$cid);
}

if(!empty($status)) {
$query->where($db->quoteName('a.status') . ' LIKE '.$db->quote($status));
}


// Reset the query using our newly populated query object.
$db->setQuery($query,$getpage,$records_per_page);

$row = $db->loadAssocList();
$db->setQuery('SELECT FOUND_ROWS();');
$total = $db->loadResult();


// pass the total number of records to the pagination class
$pagination->records($total);

// records per page
$pagination->records_per_page($records_per_page);
$pagination->padding(false);

// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
foreach($row as $set) {

$this->outputedit .='
<tr>
        <td>'.$set['bid'].'</td>
        <td>'.$set['cid'].'</td>
        <td>'.$set['brand'].'</td>
        <td>'.$set['type'].'</td>
        <td>'.$set['label'].'</td>
        <td>'.$set['bonus'].'</td>
        <td>'.$set['status'].'</td>
        <td>Edit</td>
</tr>
';

}
$this->paginate = $pagination;
$this->option = $optedit;

$doc = JFactory::getDocument();
$doc->setMetaData('robots', 'noindex,nofollow');

parent::display($tpl);
	}
}