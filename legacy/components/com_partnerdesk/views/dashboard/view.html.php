<?php
/**
 * com_partnerdesk - frontend dashboard view
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class PartnerdeskViewDashboard extends JView
{
	public $items;
	public $pagination;
	public $state;
	public $counts;

	public function display($tpl = null)
	{
		$this->items      = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state      = $this->get('State');
		$this->counts     = $this->get('StatusCounts');

		$doc = JFactory::getDocument();
		$doc->setTitle('Partner Desk - Submissions');
		$doc->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}
}
