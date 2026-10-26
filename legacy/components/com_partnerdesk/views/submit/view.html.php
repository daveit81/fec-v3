<?php
/**
 * com_partnerdesk - submit view
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class PartnerdeskViewSubmit extends JView
{
	public $sections;
	public $data;
	public $params;

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		require_once JPATH_COMPONENT . '/helpers/fields.php';

		$this->sections = PartnerdeskHelperFields::getSections();
		$this->data     = (array) $app->getUserState('com_partnerdesk.data', array());
		$this->params   = JComponentHelper::getParams('com_partnerdesk');

		// Timestamp for the time-trap check in the controller.
		if ($this->getLayout() !== 'thanks')
		{
			$app->setUserState('com_partnerdesk.form_ts', time());
		}

		$doc = JFactory::getDocument();
		$doc->setTitle('List Your Brand - FreeExtraChips Partner Desk');

		parent::display($tpl);
	}
}
