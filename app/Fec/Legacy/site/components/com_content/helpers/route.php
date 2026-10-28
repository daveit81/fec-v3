<?php
/**
 * Content Component Route Helper (port of Joomla 2.5.28, without the
 * multilingual lookup: the site has one language).
 *
 * The lib loads this file by path (JPATH_SITE/components/com_content/helpers/
 * route.php) before building a blog or review link, so that the link carries
 * the Itemid of the menu item that owns the category (the Blog item for a
 * post) and sh404SEF resolves it to the stored path.
 */
defined('_JEXEC') or die;

abstract class ContentHelperRoute
{
	protected static $lookup;

	/**
	 * @param	int	The route of the content item
	 */
	public static function getArticleRoute($id, $catid = 0, $language = 0)
	{
		$needles = array(
			'article'  => array((int) $id)
		);

		//Create the link
		$link = 'index.php?option=com_content&view=article&id='. $id;

		if ((int)$catid > 1)
		{
			$categories = JCategories::getInstance('Content');
			$category = $categories->get((int)$catid);
			if($category)
			{
				$needles['category'] = array_reverse($category->getPath());
				$needles['categories'] = $needles['category'];
				$link .= '&catid='.$catid;
			}
		}

		if ($item = self::_findItem($needles)) {
			$link .= '&Itemid='.$item;
		}

		return $link;
	}

	public static function getCategoryRoute($catid)
	{
		if ($catid instanceof JCategoryNode)
		{
			$id = $catid->id;
			$category = $catid;
		}
		else
		{
			$id = (int) $catid;
			$category = JCategories::getInstance('Content')->get($id);
		}

		if ($id < 1 || !($category instanceof JCategoryNode))
		{
			$link = '';
		}
		else
		{
			$needles = array();

			$link = 'index.php?option=com_content&view=category&id='.$id;

			$catids = array_reverse($category->getPath());
			$needles['category'] = $catids;
			$needles['categories'] = $catids;

			if ($item = self::_findItem($needles))
			{
				$link .= '&Itemid='.$item;
			}
		}

		return $link;
	}

	protected static function _findItem($needles = null)
	{
		$app		= JFactory::getApplication();
		$menus		= $app->getMenu('site');

		// Prepare the reverse lookup array.
		if (!isset(self::$lookup))
		{
			self::$lookup = array();

			// The original asked the items by component_id (com_content's row in
			// #__extensions): a "component" item whose link is com_content's, never
			// a "url" item that happens to spell a com_content link (component_id 0).
			$items		= $menus->getItems('type', 'component');

			foreach ((array) $items as $item)
			{
				if (isset($item->query) && isset($item->query['view']) && isset($item->query['option']) && $item->query['option'] == 'com_content')
				{
					$view = $item->query['view'];

					if (!isset(self::$lookup[$view])) {
						self::$lookup[$view] = array();
					}

					if (isset($item->query['id'])) {
						self::$lookup[$view][$item->query['id']] = $item->id;
					}
				}
			}
		}

		if ($needles)
		{
			foreach ($needles as $view => $ids)
			{
				if (isset(self::$lookup[$view]))
				{
					foreach($ids as $id)
					{
						if (isset(self::$lookup[$view][(int)$id])) {
							return self::$lookup[$view][(int)$id];
						}
					}
				}
			}
		}

		$active = $menus->getActive();
		if ($active && isset($active->query['option']) && $active->query['option'] == 'com_content')
		{
			return $active->id;
		}

		return null;
	}
}
