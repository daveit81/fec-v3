<?php
/**
 * Content Component Category Model (port of Joomla 2.5.28, reduced: the
 * state, the category node and its parent and children. The articles of the
 * category and their pagination are not loaded: the fecv2 override of the
 * category view queries the lib itself and reads only $this->category; see
 * PORT-NOTES.md).
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

class ContentModelCategory extends JModel
{
	/**
	 * Category item data
	 *
	 * @var		object
	 */
	protected $_item = null;

	protected $_articles = null;

	protected $_siblings = null;

	protected $_children = null;

	protected $_parent = null;

	/**
	 * The category that applies.
	 *
	 * @access	protected
	 * @var		object
	 */
	protected $_category = null;

	/**
	 * The list of other newfeed categories.
	 *
	 * @access	protected
	 * @var		array
	 */
	protected $_categories = null;

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initiliase variables.
		$app	= JFactory::getApplication('site');
		$pk		= JRequest::getInt('id');

		$this->setState('category.id', $pk);

		// Load the parameters. Merge Global and Menu Item params into new object
		$params = $app->getParams();
		$menuParams = new JRegistry;

		if ($menu = $app->getMenu()->getActive()) {
			$menuParams->loadString(is_string($menu->params) ? $menu->params : json_encode($menu->params->toArray()));
		}

		$mergedParams = clone $menuParams;
		$mergedParams->merge($params);

		$this->setState('params', $mergedParams);
		$user		= JFactory::getUser();

		if ((!$user->authorise('core.edit.state', 'com_content')) &&  (!$user->authorise('core.edit', 'com_content'))){
			// limit to published for people who can't edit or edit.state.
			$this->setState('filter.published', 1);
		}
		else {
			$this->setState('filter.published', array(0, 1, 2));
		}

		// process show_noauth parameter
		if (!$params->get('show_noauth')) {
			$this->setState('filter.access', true);
		}
		else {
			$this->setState('filter.access', false);
		}

		$this->setState('list.start', JRequest::getUInt('limitstart', 0));
		$this->setState('list.limit', $params->get('display_num', $app->getCfg('list_limit', 20)));
		$this->setState('filter.language', false);
	}

	/**
	 * The articles of the category: not loaded on v3 (the override renders
	 * the list from the lib). An empty list, so a layout that reads it prints
	 * nothing instead of failing.
	 */
	function getItems()
	{
		return array();
	}

	public function getPagination()
	{
		return null;
	}

	/**
	 * Method to get category data for the current category
	 *
	 * @param	int		An optional ID
	 *
	 * @return	object
	 * @since	1.5
	 */
	public function getCategory()
	{
		if (!is_object($this->_item)) {
			$categories = JCategories::getInstance('Content');
			$this->_item = $categories->get($this->getState('category.id', 'root'));

			if (is_object($this->_item)) {
				$this->_children = $this->_item->getChildren();
				$this->_parent = false;
				if ($this->_item->getParent()) {
					$this->_parent = $this->_item->getParent();
				}
				$this->_rightsibling = null;
				$this->_leftsibling = null;
			}
			else {
				$this->_children = false;
				$this->_parent = false;
			}
		}

		return $this->_item;
	}

	/**
	 * Get the parent category.
	 *
	 * @param	int		An optional category id. If not supplied, the model state 'category.id' will be used.
	 *
	 * @return	mixed	An array of categories or false if an error occurs.
	 */
	public function getParent()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_parent;
	}

	/**
	 * Get the child categories.
	 *
	 * @param	int		An optional category id. If not supplied, the model state 'category.id' will be used.
	 *
	 * @return	mixed	An array of categories or false if an error occurs.
	 */
	function &getChildren()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_children;
	}
}
