<?php
/**
 * com_partnerdesk - admin base controller
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class PartnerdeskController extends JController
{
	public function display($cachable = false, $urlparams = false)
	{
		JRequest::setVar('view', JRequest::getCmd('view', 'submissions'));
		parent::display(false);
	}
}
