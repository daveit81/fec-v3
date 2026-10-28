<?php
/**
 * Content Component Controller (port of Joomla 2.5.28, display only: no
 * edit, save, vote or form tasks on v3).
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class ContentController extends JController
{
	/**
	 * Method to display a view. The original set the default view to
	 * "categories" and decided the cache; neither exists here.
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$vName = JRequest::getCmd('view', 'categories');
		JRequest::setVar('view', $vName);

		if (!in_array($vName, array('article', 'category'), true)) {
			JError::raiseError(404, JText::_('JGLOBAL_RESOURCE_NOT_FOUND'));
		}

		parent::display($cachable, $urlparams);

		return $this;
	}
}
