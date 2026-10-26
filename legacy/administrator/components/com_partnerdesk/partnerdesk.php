<?php
/**
 * com_partnerdesk - admin entry point
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

if (!JFactory::getUser()->authorise('core.manage', 'com_partnerdesk'))
{
	return JError::raiseWarning(404, JText::_('JERROR_ALERTNOAUTHOR'));
}

require_once JPATH_COMPONENT . '/controller.php';

$controller = JController::getInstance('Partnerdesk');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();
