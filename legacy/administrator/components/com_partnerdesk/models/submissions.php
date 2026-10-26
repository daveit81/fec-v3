<?php
/**
 * com_partnerdesk - submissions list model
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

class PartnerdeskModelSubmissions extends JModelList
{
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'brand_name', 'a.brand_name',
				'am_name', 'a.am_name',
				'am_email', 'a.am_email',
				'status', 'a.status',
				'created', 'a.created',
			);
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = null, $direction = null)
	{
		$search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
		$this->setState('filter.search', $search);

		$status = $this->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string');
		$this->setState('filter.status', $status);

		parent::populateState('a.created', 'DESC');
	}

	protected function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.status');

		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('a.*')
			->from($db->quoteName('#__fec_brand_submissions') . ' AS a');

		$status = $this->getState('filter.status');

		if ($status !== '' && $status !== null)
		{
			$query->where('a.status = ' . (int) $status);
		}

		$search = trim($this->getState('filter.search'));

		if ($search !== '')
		{
			$like = $db->quote('%' . $db->escape($search, true) . '%', false);
			$query->where(
				'(a.brand_name LIKE ' . $like
				. ' OR a.am_name LIKE ' . $like
				. ' OR a.am_email LIKE ' . $like
				. ' OR a.aff_program_name LIKE ' . $like
				. ' OR a.casino_url LIKE ' . $like . ')'
			);
		}

		$orderCol = $this->state->get('list.ordering', 'a.created');
		$orderDir = $this->state->get('list.direction', 'DESC');
		$query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

		return $query;
	}

	/**
	 * Counters for the dashboard header pills.
	 *
	 * @return array status => count
	 */
	public function getStatusCounts()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('status, COUNT(*) AS cnt')
			->from($db->quoteName('#__fec_brand_submissions'))
			->group('status');

		$db->setQuery($query);
		$rows = (array) $db->loadObjectList();

		$out = array(0 => 0, 1 => 0, 2 => 0);

		foreach ($rows as $row)
		{
			$out[(int) $row->status] = (int) $row->cnt;
		}

		return $out;
	}
}
