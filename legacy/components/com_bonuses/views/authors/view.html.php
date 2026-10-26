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
	
parent::display($tpl);
	}
}

?>