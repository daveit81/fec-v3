<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import Joomla view library
jimport('joomla.application.component.view');

// shared helper (same registration as the manage dashboard)
JLoader::register(
	'BonusesHelperBonus',
	JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bonus.php'
);

/**
 * Click report + FTD reconciliation dashboard.
 *
 *   index.php?option=com_bonuses&view=clicks
 *
 * Clicks per brand (slug + cid merged via jos_fec_brand_map) for a
 * window, alongside ad-hoc StatsDrone snapshots (FTD / regs / NGR /
 * commission) from jos_fec_ftd_log. Click->FTD ratio computed inline.
 * Bots excluded.
 */
class BonusesViewClicks extends JView
{
	/** @var array allowed look-back windows (days) */
	private $allowedDays = array(7, 14, 30, 90);

	public function display($tpl = null)
	{
		$this->authorized = BonusesHelperBonus::authorized();

		if (!$this->authorized)
		{
			JError::raiseWarning(403, JText::_('JERROR_ALERTNOAUTHOR'));

			return;
		}

		JResponse::allowCache(false);
		JResponse::setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);
		JResponse::setHeader('Pragma', 'no-cache', true);
		JResponse::setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		JResponse::setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

		JFactory::getDocument()->setMetaData('robots', 'noindex,nofollow');

		$app = JFactory::getApplication();

		$days = (int) $app->getUserStateFromRequest(
			'com_bonuses.clicks.days', 'filter_days', 14, 'int'
		);

		if (!in_array($days, $this->allowedDays, true))
		{
			$days = 14;
		}

		$this->days        = $days;
		$this->allowedDays = $this->allowedDays;

		// window the snapshot is recorded against (frozen at save time client-side)
		$this->periodEnd   = gmdate('Y-m-d');
		$this->periodStart = gmdate('Y-m-d', time() - ((int) $days * 86400));

		$this->token = JSession::getFormToken();

		$rows    = $this->loadRows($days);
		$latest  = $this->loadLatestFtd();

		// attach the most recent snapshot (if any) to each brand row
		foreach ($rows as $r)
		{
			$cid     = (int) $r->canon_cid;
			$r->ftd  = ($cid > 0 && isset($latest[$cid])) ? $latest[$cid] : null;
		}

		$this->rows   = $rows;
		$this->totals = $this->loadTotals($days);

		JFactory::getDocument()->setTitle('Click report');

		$this->loadAssets();

		parent::display($tpl);
	}

	private function loadAssets()
	{
		JFactory::getDocument()->addStyleSheet('/components/com_bonuses/css/add-style.css');
	}

	/**
	 * Canonical brand key: prefer the bridge cid, then the click's own
	 * cid, then the raw slug (fallback for an unmapped slug).
	 *
	 * @return  string  SQL CASE expression
	 */
	private function canonKeyExpr()
	{
		return "CASE "
			. "WHEN m.cid IS NOT NULL THEN CONCAT('cid:', m.cid) "
			. "WHEN c.cid > 0 THEN CONCAT('cid:', c.cid) "
			. "WHEN c.slug <> '' THEN CONCAT('slug:', c.slug) "
			. "ELSE 'unknown' END";
	}

	/**
	 * One row per brand for the window, slug and cid paths merged.
	 *
	 * @param   integer  $days
	 * @return  array
	 */
	private function loadRows($days)
	{
		$db   = JFactory::getDbo();
		$days = (int) $days;

		$canonKey  = $this->canonKeyExpr();
		$canonCid  = "COALESCE(m.cid, NULLIF(c.cid, 0))";
		$brandName = "COALESCE(NULLIF(m.brand, ''), NULLIF(b.brand, ''), NULLIF(c.slug, ''), CONCAT('cid:', c.cid))";

		$brandSub = '(SELECT DISTINCT ' . $db->quoteName('cid') . ', ' . $db->quoteName('brand')
			. ' FROM ' . $db->quoteName('#__bonuses')
			. ' WHERE ' . $db->quoteName('brand') . " <> '')";

		$query = $db->getQuery(true)
			->select(array(
				$canonKey . ' AS canon_key',
				'MAX(' . $canonCid . ') AS canon_cid',
				'MAX(' . $brandName . ') AS brand_name',
				'COUNT(*) AS clicks',
				"SUM(CASE WHEN c.device = 'm' THEN 1 ELSE 0 END) AS mobile",
				"COUNT(DISTINCT NULLIF(c.country, '')) AS geos",
				'MAX(c.created_at) AS last_click',
			))
			->from($db->quoteName('#__bonus_clicks', 'c'))
			->join('LEFT', $db->quoteName('#__fec_brand_map', 'm') . " ON (c.slug <> '' AND m.slug = c.slug)")
			->join('LEFT', $brandSub . ' AS b ON b.cid = ' . $canonCid)
			->where('c.' . $db->quoteName('is_bot') . ' = 0')
			->where('c.' . $db->quoteName('created_at') . ' >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)')
			->group($canonKey)
			->order('clicks DESC');

		$db->setQuery($query);

		return (array) $db->loadObjectList();
	}

	/**
	 * Most recent FTD snapshot per cid, keyed by cid. Tolerant of a
	 * missing table (returns empty) so the dashboard never hard-fails
	 * before 04_create_ftd_table.sql has run.
	 *
	 * @return  array
	 */
	private function loadLatestFtd()
	{
		$db  = JFactory::getDbo();
		$out = array();

		try
		{
			$sub = '(SELECT ' . $db->qn('cid') . ', MAX(' . $db->qn('id') . ') AS mid FROM '
				. $db->qn('#__fec_ftd_log') . ' GROUP BY ' . $db->qn('cid') . ')';

			$query = $db->getQuery(true)
				->select(array('f.cid', 'f.ftd', 'f.regs', 'f.ngr', 'f.commission', 'f.period_start', 'f.period_end'))
				->from($db->qn('#__fec_ftd_log', 'f'))
				->join('INNER', $sub . ' AS t ON t.cid = f.cid AND t.mid = f.id');

			$db->setQuery($query);

			foreach ((array) $db->loadObjectList() as $r)
			{
				$out[(int) $r->cid] = $r;
			}
		}
		catch (Exception $e)
		{
			// table not created yet: degrade gracefully
			$out = array();
		}

		return $out;
	}

	private function loadTotals($days)
	{
		$db   = JFactory::getDbo();
		$days = (int) $days;

		$canonKey = $this->canonKeyExpr();

		$query = $db->getQuery(true)
			->select(array(
				'COUNT(*) AS clicks',
				"SUM(CASE WHEN c.device = 'm' THEN 1 ELSE 0 END) AS mobile",
				"COUNT(DISTINCT NULLIF(c.country, '')) AS geos",
				'COUNT(DISTINCT ' . $canonKey . ') AS brands',
			))
			->from($db->quoteName('#__bonus_clicks', 'c'))
			->join('LEFT', $db->quoteName('#__fec_brand_map', 'm') . " ON (c.slug <> '' AND m.slug = c.slug)")
			->where('c.' . $db->quoteName('is_bot') . ' = 0')
			->where('c.' . $db->quoteName('created_at') . ' >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)');

		$db->setQuery($query);
		$r = $db->loadObject();

		return $r ? $r : (object) array('clicks' => 0, 'mobile' => 0, 'geos' => 0, 'brands' => 0);
	}
}