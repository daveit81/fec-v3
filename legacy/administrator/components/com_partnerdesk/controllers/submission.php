<?php
/**
 * com_partnerdesk - single submission controller
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class PartnerdeskControllerSubmission extends JController
{
	/**
	 * Save internal notes and, if a decision button was pressed, the status.
	 */
	public function save()
	{
		JRequest::checkToken() or jexit('Invalid token');

		$id = JRequest::getInt('id', 0);

		if (!$id)
		{
			$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', 'Missing id.', 'warning');
			return;
		}

		$this->persist($id);

		$this->setRedirect('index.php?option=com_partnerdesk&view=submission&id=' . $id, 'Saved.');
	}

	/**
	 * Save current notes/status, then stream the fact-sheet draft as .md download.
	 */
	public function export()
	{
		JRequest::checkToken() or jexit('Invalid token');

		$id = JRequest::getInt('id', 0);

		if (!$id)
		{
			$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', 'Missing id.', 'warning');
			return;
		}

		$this->persist($id);

		$model = $this->getModel('Submission', 'PartnerdeskModel');
		$item  = $model->getItem($id);

		if (!$item)
		{
			$this->setRedirect('index.php?option=com_partnerdesk&view=submissions', 'Submission not found.', 'warning');
			return;
		}

		require_once JPATH_SITE . '/components/com_partnerdesk/helpers/fields.php';

		$md   = PartnerdeskHelperFields::buildFactsheetDraft($item, $item->payload_data);
		$slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($item->brand_name));
		$slug = trim($slug, '_');

		if ($slug === '')
		{
			$slug = 'submission_' . (int) $item->id;
		}

		$filename = 'FACTSHEET_DRAFT_' . strtoupper($slug) . '_AMSUBMISSION.md';

		$app = JFactory::getApplication();

		while (ob_get_level())
		{
			ob_end_clean();
		}

		header('Content-Type: text/markdown; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($md));
		header('Cache-Control: private, no-cache');

		echo $md;

		$app->close();
	}

	public function cancel()
	{
		$this->setRedirect('index.php?option=com_partnerdesk&view=submissions');
	}

	/**
	 * Shared persistence for save and export.
	 *
	 * @param   integer  $id
	 */
	protected function persist($id)
	{
		$notes     = JRequest::getVar('admin_notes', '', 'post', 'string');
		$setstatus = JRequest::getCmd('setstatus', '');

		$map = array('approve' => 1, 'reject' => 2, 'pending' => 0);

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__fec_brand_submissions'))
			->set($db->quoteName('admin_notes') . ' = ' . $db->quote($notes))
			->where($db->quoteName('id') . ' = ' . (int) $id);

		if ($setstatus !== '' && isset($map[$setstatus]))
		{
			$query->set($db->quoteName('status') . ' = ' . (int) $map[$setstatus])
				->set($db->quoteName('reviewed') . ' = ' . $db->quote(JFactory::getDate()->toSql()))
				->set($db->quoteName('reviewed_by') . ' = ' . (int) JFactory::getUser()->id);
		}

		$db->setQuery($query);
		$db->execute();
	}
}
