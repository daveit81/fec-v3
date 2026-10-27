<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import Joomla view library
jimport('joomla.application.component.view');

// shared helper
JLoader::register(
	'BonusesHelperBonus',
	JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bonus.php'
);

/**
 * Bonus list / dashboard index view.
 *
 *   index.php?option=com_bonuses&view=manage
 *
 * Shows every bonus with filters (casino / type / status / author /
 * free text), sortable Added column, per-status quick-filter chips,
 * and per-row actions: Edit, toggle Publish/Expire, Duplicate, Delete,
 * inline editorial-author and last-editor change.
 *
 * Filter values:
 *   filter_casino  int    0   = all
 *   filter_type    str    ''  = all
 *   filter_status  str    ''  = all
 *   filter_author  int    0   = all
 *                          -1 = "no author" (NULL or unknown id)
 *                         >0  = that specific author id
 *   filter_search  str    ''  = no text search
 *   filter_pkg     str    ''  = all
 *                         'in'         = legs of a welcome package
 *                         'incomplete' = legs of a package that still has
 *                                        virtual legs without terms (the
 *                                        FXRate data-entry work list)
 *                         'out'        = not a leg of any package
 *
 * Sort:
 *   list_order_dir  'asc' | 'desc'   default: 'desc' (column hard-wired
 *   to 'added' for now).
 */
class BonusesViewManage extends JView
{
	/**
	 * Sentinel value used in filter_author to mean "rows whose
	 * author_id is NULL or no longer matches a known author".
	 */
	const FILTER_AUTHOR_NONE = -1;

	/**
	 * @param   string  $tpl
	 * @return  void
	 */
	public function display($tpl = null)
	{
		// speculation rules opt-out
		JFactory::getDocument()->addCustomTag(
			'<script type="speculationrules">' .
			'{"prerender":[{"where":{"not":{"href_matches":"/*"}}}],' .
			'"prefetch":[{"where":{"not":{"href_matches":"/*"}}}]}' .
			'</script>'
		);

		$this->authorized = BonusesHelperBonus::authorized();

		if (!$this->authorized)
		{
			JError::raiseWarning(403, JText::_('JERROR_ALERTNOAUTHOR'));

			return;
		}

		// no-cache headers - see the long comment block in the previous
		// versions of this file for the full rationale (JResponse +
		// LiteSpeed-specific header + .htaccess override)
		JResponse::allowCache(false);
		JResponse::setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);
		JResponse::setHeader('Pragma', 'no-cache', true);
		JResponse::setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		JResponse::setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

		JFactory::getDocument()->setMetaData('robots', 'noindex,nofollow');

		$app    = JFactory::getApplication();
		$jinput = $app->input;

		// ---- filter state ------------------------------------------
		$filterCasino = $app->getUserStateFromRequest(
			'com_bonuses.manage.casino', 'filter_casino', 0, 'int'
		);
		$filterType = $app->getUserStateFromRequest(
			'com_bonuses.manage.type', 'filter_type', '', 'string'
		);
		$filterStatus = $app->getUserStateFromRequest(
			'com_bonuses.manage.status', 'filter_status', '', 'string'
		);
		$filterAuthor = $app->getUserStateFromRequest(
			'com_bonuses.manage.author', 'filter_author', 0, 'int'
		);
		$filterSearch = $app->getUserStateFromRequest(
			'com_bonuses.manage.search', 'filter_search', '', 'string'
		);
		$filterPkg = $app->getUserStateFromRequest(
			'com_bonuses.manage.pkg', 'filter_pkg', '', 'string'
		);
		$filterPkg = strtolower(trim((string) $filterPkg));

		if (!in_array($filterPkg, array('in', 'incomplete', 'out'), true))
		{
			$filterPkg = '';
		}

		$filterAuthor = (int) $filterAuthor;

		if ($filterAuthor < 0)
		{
			$filterAuthor = self::FILTER_AUTHOR_NONE;
		}
		elseif ($filterAuthor > 0)
		{
			// keep as-is
		}
		else
		{
			$filterAuthor = 0;
		}

		$this->filter = array(
			'casino' => (int) $filterCasino,
			'type'   => strtolower(trim($filterType)),
			'status' => strtolower(trim($filterStatus)),
			'author' => (int) $filterAuthor,
			'search' => trim($filterSearch),
			'pkg'    => $filterPkg,
		);

		// ---- sort order --------------------------------------------
		$orderDirRaw = (string) $app->getUserStateFromRequest(
			'com_bonuses.manage.order_dir', 'list_order_dir', 'desc', 'string'
		);
		$orderDir = (strtolower(trim($orderDirRaw)) === 'asc') ? 'asc' : 'desc';

		$this->orderDir = $orderDir;

		// ---- paging ------------------------------------------------
		$limit      = (int) $app->getUserStateFromRequest(
			'com_bonuses.manage.limit', 'limit', 25, 'int'
		);
		$limitstart = (int) $jinput->get('limitstart', 0, 'int');

		// ---- data --------------------------------------------------
		$this->items      = $this->loadItems($this->filter, $limit, $limitstart, $orderDir);
		$this->total      = $this->countItems($this->filter);
		$this->limitstart = $limitstart;

		$this->statusCounts = $this->countByStatus($this->filter);

		// welcome package badge per row (one query for the page)
		$bids = array();

		foreach ($this->items as $it)
		{
			$bids[] = (int) $it->bid;
		}

		$this->pkgBadges = BonusesHelperBonus::getPackageBadges($bids);

		jimport('joomla.html.pagination');
		$this->pagination = new JPagination($this->total, $limitstart, $limit);

		$this->casinos   = BonusesHelperBonus::getCasinoOptions();
		$this->bonusType = BonusesHelperBonus::optionMap('bonustype');
		$this->authors   = BonusesHelperBonus::getAuthorOptions();

		$this->filterAuthorNone = self::FILTER_AUTHOR_NONE;

		JFactory::getDocument()->setTitle('Bonus dashboard');

		$this->loadAssets();

		parent::display($tpl);
	}

	private function loadAssets()
	{
		$doc = JFactory::getDocument();

		$doc->addStyleSheet('/components/com_bonuses/css/add-style.css');
		$doc->addScript('https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js');
		$doc->addScriptDeclaration('jQuery.noConflict();');
	}

	/**
	 * @param   JDatabaseDriver  $db
	 * @param   array            $filter
	 * @param   boolean          $includeStatus
	 * @return  array
	 */
	private function whereClause($db, $filter, $includeStatus = true)
	{
		$where = array();

		if ($filter['casino'] > 0)
		{
			$where[] = $db->quoteName('cid') . ' = ' . (int) $filter['casino'];
		}

		if ($filter['type'] !== '')
		{
			$where[] = $db->quoteName('type') . ' = ' . $db->quote($filter['type']);
		}

		if ($includeStatus && $filter['status'] !== '')
		{
			$where[] = $db->quoteName('status') . ' = ' . $db->quote($filter['status']);
		}

		if ($filter['author'] === self::FILTER_AUTHOR_NONE)
		{
			$sub = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__casino_authors'))
				->where($db->quoteName('is_active') . ' = ' . $db->quote('1'));

			$where[] = '('
				. $db->quoteName('author_id') . ' IS NULL'
				. ' OR ' . $db->quoteName('author_id') . ' NOT IN (' . (string) $sub . ')'
				. ')';
		}
		elseif ($filter['author'] > 0)
		{
			$where[] = $db->quoteName('author_id') . ' = ' . (int) $filter['author'];
		}

		if (isset($filter['pkg']) && $filter['pkg'] !== '')
		{
			$legs = $db->quoteName('#__fec_bonus_package_legs');
			$in   = 'SELECT l.bid FROM ' . $legs . ' AS l WHERE l.bid IS NOT NULL';

			if ($filter['pkg'] === 'incomplete')
			{
				// rows whose package still has a virtual leg without terms
				$in .= ' AND EXISTS (SELECT 1 FROM ' . $legs . ' AS v WHERE v.pkg_id = l.pkg_id'
					. ' AND v.bid IS NULL AND (v.pct IS NULL OR v.cap IS NULL))';
			}

			$where[] = $db->quoteName('bid') . (($filter['pkg'] === 'out') ? ' NOT IN (' : ' IN (') . $in . ')';
		}

		if ($filter['search'] !== '')
		{
			$like = $db->quote('%' . $db->escape($filter['search'], true) . '%', false);

			$where[] = '('
				. $db->quoteName('brand') . ' LIKE ' . $like
				. ' OR ' . $db->quoteName('label') . ' LIKE ' . $like
				. ' OR ' . $db->quoteName('code') . ' LIKE ' . $like
				. ')';
		}

		return $where;
	}

	/**
	 * Loads one page of bonuses for the current filter set.
	 *
	 * @param   array    $filter
	 * @param   integer  $limit
	 * @param   integer  $limitstart
	 * @param   string   $orderDir
	 * @return  array
	 */
	private function loadItems($filter, $limit, $limitstart, $orderDir = 'desc')
	{
		$orderDir = (strtolower(trim($orderDir)) === 'asc') ? 'ASC' : 'DESC';

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(
				array(
					'bid', 'cid', 'brand', 'type', 'label', 'code',
					'exclusive', 'bonus', 'currency', 'status',
					'start', 'end', 'added', 'author_id',
					'modified', 'modified_by',
				)
			)
			->from($db->quoteName('#__bonuses'));

		foreach ($this->whereClause($db, $filter) as $cond)
		{
			$query->where($cond);
		}

		$query->order($db->quoteName('added') . ' ' . $orderDir);

		$db->setQuery($query, (int) $limitstart, (int) $limit);

		return (array) $db->loadObjectList();
	}

	private function countItems($filter)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__bonuses'));

		foreach ($this->whereClause($db, $filter) as $cond)
		{
			$query->where($cond);
		}

		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	private function countByStatus($filter)
	{
		$out = array(
			'active'  => 0,
			'pending' => 0,
			'expired' => 0,
			'total'   => 0,
		);

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(array($db->quoteName('status'), 'COUNT(*) AS cnt'))
			->from($db->quoteName('#__bonuses'));

		foreach ($this->whereClause($db, $filter, false) as $cond)
		{
			$query->where($cond);
		}

		$query->group($db->quoteName('status'));

		$db->setQuery($query);
		$rows = (array) $db->loadObjectList();

		foreach ($rows as $r)
		{
			$key = strtolower((string) $r->status);
			$n   = (int) $r->cnt;

			if (isset($out[$key]))
			{
				$out[$key] = $n;
			}
			$out['total'] += $n;
		}

		return $out;
	}
}