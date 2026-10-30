<?php
/**
 * com_partnerdesk - frontend review view
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class PartnerdeskViewReview extends JView
{
	public $item;
	public $sections;

	public function display($tpl = null)
	{
		$this->item = $this->get('Item');

		if (!$this->item)
		{
			JFactory::getApplication()->redirect(
				JRoute::_('index.php?option=com_partnerdesk&view=dashboard', false),
				'Submission not found.',
				'warning'
			);
			return;
		}

		require_once JPATH_COMPONENT . '/helpers/fields.php';
		$this->sections = PartnerdeskHelperFields::getSections();

		$doc = JFactory::getDocument();
		$doc->setTitle('Partner Desk - #' . (int) $this->item->id . ' ' . $this->item->brand_name);
		$doc->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}
}
