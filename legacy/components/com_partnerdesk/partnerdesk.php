<?php
/**
 * com_partnerdesk - site entry point
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

require_once JPATH_COMPONENT . '/controller.php';

$controller = JController::getInstance('Partnerdesk');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();
