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
 * Overview / status dashboard view.
 *
 *   index.php?option=com_bonuses&view=home
 *
 * Read-only landing page summarising the state of the bonus catalog:
 *
 *   - KPI tiles: total / active / pending / expired counts, plus
 *     "no author" count. Each tile links to the manage list with the
 *     matching filter applied.
 *   - Expiring soon: ACTIVE bonuses whose end date falls within the
 *     next 7 days, soonest first. The most actionable list on the
 *     page - these need a decision (renew, replace, let lapse).
 *   - Pending to review: status='pending' bonuses, ordered by start
 *     ASC (next to go live first).
 *   - Expired in the last 30 days: status='expired' with an end date
 *     in the last 30 days. NOTE the proxy: there is no expired_at
 *     column, so the end date is the only honest timestamp for "when
 *     did this expire". Manually-expired bonuses without an end date
 *     are not counted here (they are still in the expired KPI tile).
 *   - Top casinos: the 5 casinos with the most ACTIVE bonuses.
 *   - Recently modified: latest rows by the modified column, with
 *     the editorial last-editor name resolved.
 *
 * No JS, no writes - plain queries and links. The view degrades
 * gracefully if the modified columns are missing (try/catch around
 * that query), so load order vs. the migration does not matter.
 */
class BonusesViewHome extends JView
{
	/**
	 * @param   string  $tpl
	 * @return  void
	 */
	public function display($tpl = null)
	{
		// ---- single-user gate --------------------------------------
		$this->authorized = BonusesHelperBonus::authorized();

		if (!$this->authorized)
		{
			JError::raiseWarning(403, JText::_('JERROR_ALERTNOAUTHOR'));

			return;
		}

		// ---- no-cache + noindex, same posture as the manage list ---
		JResponse::allowCache(false);
		JResponse::setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);
		JResponse::setHeader('Pragma', 'no-cache', true);
		JResponse::setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		JResponse::setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

		JFactory::getDocument()->setMetaData('robots', 'noindex,nofollow');

		// ---- data ---------------------------------------------------
		$this->counts           = $this->loadStatusCounts();
		$this->noAuthorCount    = $this->loadNoAuthorCount();
		$this->expiringSoon     = $this->loadExpiringSoon(7, 10);
		$this->expired30        = $this->loadRecentlyExpired(30, 10);
		$this->expired30Count   = $this->loadRecentlyExpiredCount(30);
		$this->pendingList      = $this->loadPending(10);
		$this->topCasinos       = $this->loadTopCasinos(5);
		$this->recentlyModified = $this->loadRecentlyModified(8);

		// id -> name map for resolving modified_by in the template
		$this->authorNames = array();

		foreach (BonusesHelperBonus::getAuthorOptions() as $a)
		{
			$this->authorNames[(int) $a['id']] = (string) $a['name'];
		}

		// ---- page chrome --------------------------------------------
		JFactory::getDocument()->setTitle('Bonus dashboard - Overview');

		// asset-light: the shared stylesheet only, no scripts (the
		// page is links and numbers, nothing interactive)
		JFactory::getDocument()->addStyleSheet('/components/com_bonuses/css/add-style.css');

		parent::display($tpl);
	}

	/* =================================================================
	 * Queries (all read-only)
	 * ================================================================= */

	/**
	 * Counts per status in one GROUP BY pass.
	 *
	 * @return  array  active / pending / expired / total
	 */
	private function loadStatusCounts()
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
			->from($db->quoteName('#__bonuses'))
			->group($db->quoteName('status'));

		$db->setQuery($query);

		foreach ((array) $db->loadObjectList() as $r)
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

	/**
	 * Bonuses with no usable editorial author: author_id NULL, or
	 * pointing at an author that is not active any more. Same logic
	 * as the manage list's FILTER_AUTHOR_NONE filter, so the KPI tile
	 * and the filtered list it links to always agree.
	 *
	 * @return  integer
	 */
	private function loadNoAuthorCount()
	{
		$db = JFactory::getDbo();

		$sub = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('is_active') . ' = ' . $db->quote('1'));

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__bonuses'))
			->where(
				'('
				. $db->quoteName('author_id') . ' IS NULL'
				. ' OR ' . $db->quoteName('author_id') . ' NOT IN (' . (string) $sub . ')'
				. ')'
			);

		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	/**
	 * ACTIVE bonuses ending within the next $days days, soonest first.
	 *
	 * @param   integer  $days
	 * @param   integer  $limit
	 * @return  array
	 */
	private function loadExpiringSoon($days, $limit)
	{
		$db   = JFactory::getDbo();
		$now  = date('Y-m-d H:i:s');
		$till = date('Y-m-d H:i:s', time() + ((int) $days * 86400));

		$query = $db->getQuery(true)
			->select($db->quoteName(array('bid', 'brand', 'label', 'end')))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('status') . ' = ' . $db->quote('active'))
			->where($db->quoteName('end') . ' IS NOT NULL')
			->where($db->quoteName('end') . ' > ' . $db->quote($now))
			->where($db->quoteName('end') . ' <= ' . $db->quote($till))
			->order($db->quoteName('end') . ' ASC');

		$db->setQuery($query, 0, (int) $limit);

		return (array) $db->loadObjectList();
	}

	/**
	 * Expired bonuses whose end date fell in the last $days days,
	 * most recent first. See the class docblock for the end-date
	 * proxy caveat.
	 *
	 * @param   integer  $days
	 * @param   integer  $limit
	 * @return  array
	 */
	private function loadRecentlyExpired($days, $limit)
	{
		$db    = JFactory::getDbo();
		$now   = date('Y-m-d H:i:s');
		$since = date('Y-m-d H:i:s', time() - ((int) $days * 86400));

		$query = $db->getQuery(true)
			->select($db->quoteName(array('bid', 'brand', 'label', 'end')))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('status') . ' = ' . $db->quote('expired'))
			->where($db->quoteName('end') . ' IS NOT NULL')
			->where($db->quoteName('end') . ' >= ' . $db->quote($since))
			->where($db->quoteName('end') . ' <= ' . $db->quote($now))
			->order($db->quoteName('end') . ' DESC');

		$db->setQuery($query, 0, (int) $limit);

		return (array) $db->loadObjectList();
	}

	/**
	 * Count companion to loadRecentlyExpired().
	 *
	 * @param   integer  $days
	 * @return  integer
	 */
	private function loadRecentlyExpiredCount($days)
	{
		$db    = JFactory::getDbo();
		$now   = date('Y-m-d H:i:s');
		$since = date('Y-m-d H:i:s', time() - ((int) $days * 86400));

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('status') . ' = ' . $db->quote('expired'))
			->where($db->quoteName('end') . ' IS NOT NULL')
			->where($db->quoteName('end') . ' >= ' . $db->quote($since))
			->where($db->quoteName('end') . ' <= ' . $db->quote($now));

		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	/**
	 * Pending bonuses, next-to-go-live first. Rows with no start date
	 * sort last (they are pending for some other reason and have no
	 * scheduled launch).
	 *
	 * @param   integer  $limit
	 * @return  array
	 */
	private function loadPending($limit)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('bid', 'brand', 'label', 'start')))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
			->order($db->quoteName('start') . ' IS NULL')
			->order($db->quoteName('start') . ' ASC');

		$db->setQuery($query, 0, (int) $limit);

		return (array) $db->loadObjectList();
	}

	/**
	 * The $limit casinos with the most ACTIVE bonuses. brand is
	 * denormalised onto the bonus row, so no join is needed; grouping
	 * by cid AND brand keeps the query valid under ONLY_FULL_GROUP_BY.
	 *
	 * @param   integer  $limit
	 * @return  array
	 */
	private function loadTopCasinos($limit)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(array(
				$db->quoteName('cid'),
				$db->quoteName('brand'),
				'COUNT(*) AS cnt',
			))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('status') . ' = ' . $db->quote('active'))
			->group($db->quoteName('cid'))
			->group($db->quoteName('brand'))
			->order('cnt DESC');

		$db->setQuery($query, 0, (int) $limit);

		return (array) $db->loadObjectList();
	}

	/**
	 * Latest rows by the modified column (the new edit-tracking
	 * feature). Wrapped in try/catch so the page still renders if the
	 * modified migration has not run on this environment yet.
	 *
	 * @param   integer  $limit
	 * @return  array
	 */
	private function loadRecentlyModified($limit)
	{
		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName(array('bid', 'brand', 'label', 'status', 'modified', 'modified_by')))
				->from($db->quoteName('#__bonuses'))
				->where($db->quoteName('modified') . ' IS NOT NULL')
				->order($db->quoteName('modified') . ' DESC');

			$db->setQuery($query, 0, (int) $limit);

			return (array) $db->loadObjectList();
		}
		catch (Exception $e)
		{
			return array();
		}
	}
}