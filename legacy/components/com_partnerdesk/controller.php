<?php
/**
 * com_partnerdesk - site controller
 *
 * Public side: form display and send task (validation, anti-spam,
 * rate limiting, persistence, admin notification).
 * Desk side (login + ACL required): dashboard list, review detail,
 * status decisions, internal notes, fact-sheet draft export.
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class PartnerdeskController extends JController
{
	/**
	 * Default display. Desk views are gated behind login + core.manage.
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$view = JRequest::getCmd('view', 'submit');
		JRequest::setVar('view', $view);

		if (in_array($view, array('dashboard', 'review')) && !$this->checkDeskAccess($view))
		{
			return;
		}

		parent::display(false);
	}

	/**
	 * Handle the public form POST.
	 */
	public function send()
	{
		JRequest::checkToken() or jexit('Invalid token');

		$app      = JFactory::getApplication();
		$params   = JComponentHelper::getParams('com_partnerdesk');
		$formUrl  = JRoute::_('index.php?option=com_partnerdesk&view=submit', false);

		if (!(int) $params->get('form_enabled', 1))
		{
			$app->redirect($formUrl, 'Submissions are temporarily closed.', 'warning');
			return;
		}

		// Honeypot: real users never see or fill this field.
		if (JRequest::getVar('website', '', 'post', 'string') !== '')
		{
			$app->redirect($formUrl, 'Your submission could not be processed.', 'warning');
			return;
		}

		// Time trap: reject forms filled faster than a human can.
		$ts         = (int) $app->getUserState('com_partnerdesk.form_ts', 0);
		$minSeconds = (int) $params->get('min_seconds', 8);

		if ($ts === 0 || (time() - $ts) < $minSeconds)
		{
			$app->redirect($formUrl, 'The form was submitted too quickly. Please review your data and try again.', 'warning');
			return;
		}

		require_once JPATH_COMPONENT . '/helpers/fields.php';

		$fields = PartnerdeskHelperFields::getAllFields();
		$data   = array();

		foreach ($fields as $f)
		{
			$data[$f['key']] = trim(JRequest::getVar($f['key'], '', 'post', 'string'));
		}

		// Validation.
		$errors = array();

		foreach ($fields as $f)
		{
			$key = $f['key'];
			$val = $data[$key];

			if (!empty($f['required']) && $val === '')
			{
				$errors[] = 'Required field missing: ' . html_entity_decode($f['label'], ENT_QUOTES, 'UTF-8');
				continue;
			}

			if ($val === '' || strtolower($val) === 'pending')
			{
				continue;
			}

			if ($f['type'] === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL))
			{
				$errors[] = 'Invalid email: ' . html_entity_decode($f['label'], ENT_QUOTES, 'UTF-8');
			}

			if ($f['type'] === 'url' && (!preg_match('#^https?://#i', $val) || !filter_var($val, FILTER_VALIDATE_URL)))
			{
				$errors[] = 'Invalid URL (must start with http:// or https://): ' . html_entity_decode($f['label'], ENT_QUOTES, 'UTF-8');
			}

			if ($f['type'] === 'select' && !empty($f['options']) && !in_array($val, $f['options'], true))
			{
				$errors[] = 'Invalid value: ' . html_entity_decode($f['label'], ENT_QUOTES, 'UTF-8');
			}
		}

		if (!empty($errors))
		{
			$app->setUserState('com_partnerdesk.data', $data);

			foreach ($errors as $e)
			{
				$app->enqueueMessage($e, 'warning');
			}

			$app->redirect($formUrl);
			return;
		}

		$db = JFactory::getDbo();
		$ip = $this->getClientIp();

		// Rate limit per IP per rolling 24h.
		$maxPerDay = (int) $params->get('max_per_day', 3);

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__fec_brand_submissions'))
			->where($db->quoteName('ip') . ' = ' . $db->quote($ip))
			->where($db->quoteName('created') . ' > ' . $db->quote(JFactory::getDate('-1 day')->toSql()));

		$db->setQuery($query);

		if ((int) $db->loadResult() >= $maxPerDay)
		{
			$app->setUserState('com_partnerdesk.data', $data);
			$app->redirect($formUrl, 'Submission limit reached for today. Please try again tomorrow or contact us directly.', 'warning');
			return;
		}

		// Duplicate guard: same brand + AM email already pending.
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__fec_brand_submissions'))
			->where($db->quoteName('brand_name') . ' = ' . $db->quote($data['brand_name']))
			->where($db->quoteName('am_email') . ' = ' . $db->quote($data['am_email']))
			->where($db->quoteName('status') . ' = 0');

		$db->setQuery($query);

		if ((int) $db->loadResult() > 0)
		{
			$app->setUserState('com_partnerdesk.data', $data);
			$app->redirect($formUrl, 'A submission for this brand from this email is already under review.', 'warning');
			return;
		}

		// Persist.
		$row = new stdClass;
		$row->brand_name       = $data['brand_name'];
		$row->casino_url       = $data['casino_url'];
		$row->aff_program_name = $data['aff_program_name'];
		$row->aff_program_url  = $data['aff_program_url'];
		$row->am_name          = $data['am_name'];
		$row->am_email         = $data['am_email'];
		$row->payload          = json_encode($data, JSON_UNESCAPED_UNICODE);
		$row->status           = 0;
		$row->admin_notes      = '';
		$row->ip               = $ip;
		$row->user_agent       = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
		$row->created          = JFactory::getDate()->toSql();
		$row->reviewed_by      = 0;

		try
		{
			$db->insertObject('#__fec_brand_submissions', $row, 'id');
		}
		catch (Exception $e)
		{
			$app->setUserState('com_partnerdesk.data', $data);
			$app->redirect($formUrl, 'A technical error occurred while saving. Please try again later.', 'error');
			return;
		}

		$this->notifyAdmin($params, $row);

		$app->setUserState('com_partnerdesk.data', null);
		$app->setUserState('com_partnerdesk.form_ts', null);

		$app->redirect(JRoute::_('index.php?option=com_partnerdesk&view=submit&layout=thanks', false));
	}

	// -----------------------------------------------------------------
	// Desk tasks (login + ACL)
	// -----------------------------------------------------------------

	public function approve()
	{
		$this->deskStatus(1, 'approved');
	}

	public function reject()
	{
		$this->deskStatus(2, 'rejected');
	}

	public function pending()
	{
		$this->deskStatus(0, 'marked pending');
	}

	/**
	 * Quick status change from the dashboard list or the review page.
	 *
	 * @param   integer  $status
	 * @param   string   $label
	 */
	protected function deskStatus($status, $label)
	{
		JRequest::checkToken() or jexit('Invalid token');

		if (!$this->checkDeskAccess())
		{
			return;
		}

		$app = JFactory::getApplication();
		$id  = JRequest::getInt('id', 0);

		if (!$id)
		{
			$app->redirect(JRoute::_('index.php?option=com_partnerdesk&view=dashboard', false), 'Missing id.', 'warning');
			return;
		}

		$this->applyStatus($id, $status);

		$ref = JRequest::getCmd('ref', 'dashboard');
		$url = ($ref === 'review')
			? 'index.php?option=com_partnerdesk&view=review&id=' . $id
			: 'index.php?option=com_partnerdesk&view=dashboard';

		$app->redirect(JRoute::_($url, false), 'Submission #' . $id . ' ' . $label . '.');
	}

	/**
	 * Save internal notes and, if a decision button was pressed, the status.
	 */
	public function savenotes()
	{
		JRequest::checkToken() or jexit('Invalid token');

		if (!$this->checkDeskAccess())
		{
			return;
		}

		$app = JFactory::getApplication();
		$id  = JRequest::getInt('id', 0);

		if (!$id)
		{
			$app->redirect(JRoute::_('index.php?option=com_partnerdesk&view=dashboard', false), 'Missing id.', 'warning');
			return;
		}

		$notes = JRequest::getVar('admin_notes', '', 'post', 'string');

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__fec_brand_submissions'))
			->set($db->quoteName('admin_notes') . ' = ' . $db->quote($notes))
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$db->setQuery($query);
		$db->execute();

		$setstatus = JRequest::getCmd('setstatus', '');
		$map       = array('approve' => 1, 'reject' => 2, 'pending' => 0);

		if ($setstatus !== '' && isset($map[$setstatus]))
		{
			$this->applyStatus($id, $map[$setstatus]);
		}

		$app->redirect(JRoute::_('index.php?option=com_partnerdesk&view=review&id=' . $id, false), 'Saved.');
	}

	/**
	 * Stream the fact-sheet draft as a .md download.
	 */
	public function exportmd()
	{
		JRequest::checkToken() or jexit('Invalid token');

		if (!$this->checkDeskAccess())
		{
			return;
		}

		$app = JFactory::getApplication();
		$id  = JRequest::getInt('id', 0);

		$model = $this->getModel('Review', 'PartnerdeskModel');
		$item  = $model ? $model->getItem($id) : null;

		if (!$item)
		{
			$app->redirect(JRoute::_('index.php?option=com_partnerdesk&view=dashboard', false), 'Submission not found.', 'warning');
			return;
		}

		require_once JPATH_COMPONENT . '/helpers/fields.php';

		$md   = PartnerdeskHelperFields::buildFactsheetDraft($item, $item->payload_data);
		$slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($item->brand_name));
		$slug = trim($slug, '_');

		if ($slug === '')
		{
			$slug = 'submission_' . (int) $item->id;
		}

		$filename = 'FACTSHEET_DRAFT_' . strtoupper($slug) . '_AMSUBMISSION.md';

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

	/**
	 * Common status write.
	 *
	 * @param   integer  $id
	 * @param   integer  $status
	 */
	protected function applyStatus($id, $status)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__fec_brand_submissions'))
			->set($db->quoteName('status') . ' = ' . (int) $status)
			->set($db->quoteName('reviewed') . ' = ' . $db->quote(JFactory::getDate()->toSql()))
			->set($db->quoteName('reviewed_by') . ' = ' . (int) JFactory::getUser()->id)
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Desk gate: logged in + core.manage on the component.
	 * Guests are sent to login with a return URL back to the desk.
	 *
	 * @param   string  $view  View to return to after login.
	 * @return  boolean
	 */
	protected function checkDeskAccess($view = 'dashboard')
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		if ($user->guest)
		{
			$return = 'index.php?option=com_partnerdesk&view=' . $view;

			if ($view === 'review' && ($id = JRequest::getInt('id', 0)))
			{
				$return .= '&id=' . $id;
			}

			$app->redirect(
				JRoute::_('index.php?option=com_users&view=login&return=' . base64_encode($return), false),
				'Please log in to access the desk.',
				'notice'
			);

			return false;
		}

		if (!$user->authorise('core.manage', 'com_partnerdesk'))
		{
			$app->redirect(JRoute::_('index.php', false), 'You are not authorised to access this area.', 'warning');

			return false;
		}

		return true;
	}

	/**
	 * Real client IP, Cloudflare aware.
	 *
	 * @return string
	 */
	protected function getClientIp()
	{
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])
			&& filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP))
		{
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	/**
	 * Notify the review desk about a new submission. Failures are silent.
	 *
	 * @param   JRegistry  $params
	 * @param   object     $row
	 */
	protected function notifyAdmin($params, $row)
	{
		$config = JFactory::getConfig();
		$to     = trim($params->get('notify_email', ''));

		if ($to === '')
		{
			$to = $config->get('mailfrom');
		}

		if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL))
		{
			return;
		}

		$body  = "New brand submission on FEC Partner Desk\n\n";
		$body .= 'Brand: ' . $row->brand_name . "\n";
		$body .= 'Casino URL: ' . $row->casino_url . "\n";
		$body .= 'Affiliate program: ' . $row->aff_program_name . ' - ' . $row->aff_program_url . "\n";
		$body .= 'AM: ' . $row->am_name . ' <' . $row->am_email . ">\n";
		$body .= 'IP: ' . $row->ip . "\n\n";
		$body .= 'Review it in the desk: index.php?option=com_partnerdesk&view=dashboard' . "\n";

		try
		{
			$mailer = JFactory::getMailer();
			$mailer->addRecipient($to);
			$mailer->setSubject('FEC Partner Desk - new submission: ' . $row->brand_name);
			$mailer->setBody($body);
			$mailer->Send();
		}
		catch (Exception $e)
		{
			// Notification failure must never break the submission flow.
		}
	}
}
