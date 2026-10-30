<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// FEC 2026-10-30 gate: the desk (dashboard/edit/tools views, actions.* tasks) is for the redazione only.
// Public: the show/details views with their getjson/hits/values tasks, and data.update (the cron).
$fecInput = JFactory::getApplication()->input;
$fecTask = (string) $fecInput->getCmd('task');
$fecView = (string) $fecInput->getCmd('view');
if (strpos($fecTask, 'actions.') === 0 || in_array($fecView, array('dashboard', 'edit', 'tools'), true)) {
    $fecUser = JFactory::getUser();
    if ($fecUser->guest || !$fecUser->authorise('core.manage', 'com_jackpots')) {
        JError::raiseError(403, JText::_('JERROR_ALERTNOAUTHOR'));
    }
}
 
// import joomla controller library
jimport('joomla.application.component.controller');
 
// Get an instance of the controller prefixed by HelloWorld
$controller = JController::getInstance('Jackpots');
 
// Perform the Request task
$input = JFactory::getApplication()->input;
$controller->execute($input->getCmd('task'));
 
// Redirect if set by the controller
$controller->redirect();