<?php
/**
 * com_partnerdesk - single submission model
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class PartnerdeskModelSubmission extends JModel
{
	/**
	 * Load one submission and decode its payload.
	 *
	 * @param   integer  $pk
	 * @return  object|null
	 */
	public function getItem($pk = null)
	{
		$pk = (int) ($pk !== null ? $pk : JRequest::getInt('id', 0));

		if (!$pk)
		{
			return null;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('a.*')
			->from($db->quoteName('#__fec_brand_submissions') . ' AS a')
			->where('a.id = ' . $pk);

		$db->setQuery($query);
		$item = $db->loadObject();

		if (!$item)
		{
			return null;
		}

		$payload = json_decode($item->payload, true);
		$item->payload_data = is_array($payload) ? $payload : array();

		return $item;
	}
}
