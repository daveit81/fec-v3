<?php
/**
 * com_partnerdesk - submissions list controller
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class PartnerdeskControllerSubmissions extends JController
{
	public function approve()
	{
		$this->setStatus(1, 'approved');
	}

	public function reject()
	{
		$this->setStatus(2, 'rejected');
	}

	public function pending()
	{
		$this->setStatus(0, 'marked pending');
	}

	protected function setStatus($status, $label)
	{
		JRequest::checkToken() or jexit('Invalid token');

		$cid = JRequest::getVar('cid', array(), 'post', 'array');
		JArrayHelper::toInteger($cid);
		$cid = array_filter($cid);

		if (empty($cid))
		{
			$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', 'No submission selected.', 'warning');
			return;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__fec_brand_submissions'))
			->set($db->quoteName('status') . ' = ' . (int) $status)
			->set($db->quoteName('reviewed') . ' = ' . $db->quote(JFactory::getDate()->toSql()))
			->set($db->quoteName('reviewed_by') . ' = ' . (int) JFactory::getUser()->id)
			->where($db->quoteName('id') . ' IN (' . implode(',', $cid) . ')');

		$db->setQuery($query);
		$db->execute();

		$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', count($cid) . ' submission(s) ' . $label . '.');
	}

	public function delete()
	{
		JRequest::checkToken() or jexit('Invalid token');

		$cid = JRequest::getVar('cid', array(), 'post', 'array');
		JArrayHelper::toInteger($cid);
		$cid = array_filter($cid);

		if (empty($cid))
		{
			$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', 'No submission selected.', 'warning');
			return;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__fec_brand_submissions'))
			->where($db->quoteName('id') . ' IN (' . implode(',', $cid) . ')');

		$db->setQuery($query);
		$db->execute();

		$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', count($cid) . ' submission(s) deleted.');
	}
}
