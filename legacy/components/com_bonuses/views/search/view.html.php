<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');


// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class BonusesViewSearch extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
		// Assign data to the view
	

require JPATH_COMPONENT.DS.'renders'.DS.'filters.php';
require JPATH_COMPONENT.DS.'helpers'.DS.'filtertrigger.php';
require JPATH_COMPONENT.DS.'renders'.DS.'filtersheaders.php'; //this goes here!!!
echo '<section class="grid-wrap" id="container">';
echo '<div class="filters-wrapper">'.(isset($refine) ? '' : $filtertrigger).'<div class="filters-results">Showing <span>'.$total.'</span> '.' Bonus' . plural($total).'</div></div>';
echo '<div class="affiliate-disclosure">
  <i class="fa fa-info-circle"></i> Some offers on this page contain affiliate links. We may earn a commission at no extra cost to you.
  <a href="/affiliate-advertiser-disclosure">Learn more</a>.
</div>';
echo '<div class="listing-results fec-grid">';
echo $output;
echo '</div>';

echo '</section>';



$pagination->padding(false);

echo $pagination->render();

echo $articlebottom;

parent::display($tpl);
	}
}

?>


