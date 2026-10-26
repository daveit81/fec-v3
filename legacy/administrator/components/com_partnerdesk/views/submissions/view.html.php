<?php
/**
 * com_partnerdesk - submissions list view
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class PartnerdeskViewSubmissions extends JView
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

		if (count($errors = $this->get('Errors')))
		{
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		JToolBarHelper::title('Partner Desk - Brand Submissions', 'generic.png');

		JToolBarHelper::custom('submissions.approve', 'publish', 'publish', 'Approve', true);
		JToolBarHelper::custom('submissions.reject', 'unpublish', 'unpublish', 'Reject', true);
		JToolBarHelper::custom('submissions.pending', 'refresh', 'refresh', 'Mark pending', true);
		JToolBarHelper::divider();
		JToolBarHelper::deleteList('Delete the selected submissions permanently?', 'submissions.delete', 'Delete');
		JToolBarHelper::divider();
		JToolBarHelper::preferences('com_partnerdesk');
	}
}
