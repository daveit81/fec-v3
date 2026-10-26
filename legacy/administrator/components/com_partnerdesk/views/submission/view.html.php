<?php
/**
 * com_partnerdesk - single submission view
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class PartnerdeskViewSubmission extends JView
{
	public $item;
	public $sections;

	public function display($tpl = null)
	{
		$this->item = $this->get('Item');

		if (!$this->item)
		{
			JFactory::getApplication()->redirect(
				JRoute::_('index.php?option=com_partnerdesk&view=submissions', false),
				'Submission not found.',
				'warning'
			);
			return;
		}

		require_once JPATH_SITE . '/components/com_partnerdesk/helpers/fields.php';
		$this->sections = PartnerdeskHelperFields::getSections();

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		JToolBarHelper::title('Partner Desk - Submission #' . (int) $this->item->id . ': ' . $this->item->brand_name, 'generic.png');

		JToolBarHelper::custom('submission.save', 'save', 'save', 'Save notes', false);
		JToolBarHelper::custom('submission.export', 'download', 'download', 'Export .md draft', false);
		JToolBarHelper::cancel('submission.cancel', 'Back to list');
	}
}
