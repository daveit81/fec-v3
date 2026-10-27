<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import Joomla controller library
jimport('joomla.application.component.controller');

// shared helper (auth gate, starlist (de)serialisation, lookups, ...)
JLoader::register(
	'BonusesHelperBonus',
	JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bonus.php'
);

/**
 * com_bonuses - Actions controller
 *
 * Front-end bonus dashboard write operations:
 *
 *   task=actions.save             insert / update                       -> JSON
 *   task=actions.toggle           flip active/pending <-> expired       -> redirect
 *   task=actions.remove           delete a bonus row                    -> redirect
 *   task=actions.duplicate        clone a bonus row as pending          -> redirect
 *   task=actions.restore          restore the latest history snapshot   -> redirect
 *   task=actions.setauthor        change author from list (single row)  -> JSON
 *   task=actions.setmodifiedby    change modified_by from list          -> JSON
 *   task=actions.relatedoptions   list a casino's bonuses (for the      -> JSON
 *                                 related-bonus select in the form)
 *   task=actions.packageoptions   a casino's welcome packages + ladder  -> JSON
 *                                 rows (for the Welcome package section)
 *   task=actions.packagedetect    what the article states about the     -> JSON
 *                                 package (fec_fxrate_text.php)
 *   task=actions.bulksetstatus    expire / publish many at once         -> JSON
 *   task=actions.bulkdelete       delete many at once                   -> JSON
 *   task=actions.bulksetauthor    change author on many at once         -> JSON
 *
 * Every task is gated by:
 *   - BonusesHelperBonus::assertAuthorized()  single-user ID gate
 *   - JSession::checkToken()                  CSRF protection
 *
 * Column semantics on jos_bonuses:
 *   - author_id        editorial author id, FK to jos_casino_authors.id
 *   - bonus_author_id  Joomla user id that CREATED the bonus (audit)
 *   - modified         DATETIME, auto-stamped on every write
 *   - modified_by      editorial last-editor id, FK to jos_casino_authors.id
 *                      (user-chosen, NOT auto-stamped from Joomla user)
 *
 * Modified-tracking policy:
 *   - INSERT          : modified=NULL, modified_by=NULL (a fresh bonus
 *                       has not been modified yet, only "added")
 *   - UPDATE via save : modified=NOW(), modified_by=chosen-in-form
 *                       (with default-author fallback if invalid)
 *   - toggle / set*   : modified=NOW(), modified_by=UNCHANGED
 *                       (status/author tweaks are operational changes,
 *                       not editorial edits - the editorial credit
 *                       must not silently flip on a status toggle)
 *   - setmodifiedby   : modified=NOW(), modified_by=chosen
 *   - bulk*           : modified=NOW(), modified_by=UNCHANGED (same
 *                       reasoning as the singular versions)
 *   - restore         : modified=NOW(), modified_by=as-in-snapshot
 *                       (the restore is overwriting every column
 *                       anyway, and the snapshot's modified_by is the
 *                       editorial state at that historical point)
 *   - remove          : the row disappears - no modified column is
 *                       set on the dead row; the deletion is journalled
 *                       in jos_bonuses_history with reason='delete'.
 *
 * Welcome packages (FXRate):
 *   save() ends with syncPackage(): the Welcome package section of the
 *   form decides (auto-detect from the article / none / an existing
 *   package / a new one). It never fails the save - a package problem
 *   becomes a note in the success message. remove() and bulkDelete()
 *   detach the row first, copying its terms into the leg so the
 *   package keeps its price.
 *   2026-10-15: in auto mode the deposit count also comes from the
 *   highest "Nth deposit" row of the article (parser 0.1.3 reads those
 *   rows with or without "up to"); a stated row's spins travel into
 *   the leg; a text-sourced package being refreshed fills the terms
 *   its text legs still lack (never overwriting a typed or stated one).
 *   2026-10-19 (schema 3): the spins' own wagering (parser 0.1.5
 *   spins_wr: 0 = stated as none, N = N x winnings) travels into the
 *   leg beside the spins count, real legs included; the form posts it
 *   as pkg_leg[pos][spins_wr]; a refresh fills it when the leg still
 *   lacks it; the headline's, when the row states none of its own.
 *
 * Versioning:
 *   jos_bonuses_history journals every write. snapshotBonus() is
 *   called before save() on update, before remove() and bulkDelete()
 *   on each bid, and before restore() overwrites the current row.
 *
 * On caching:
 *   every side-effect task calls preventCaching() so LiteSpeed never
 *   stores its response.
 */
class BonusesControllerActions extends JController
{
	/**
	 * The list view URL, used by the redirecting tasks.
	 *
	 * @var string
	 */
	private $listUrl = 'index.php?option=com_bonuses&view=manage';

	/**
	 * Columns owned by the social bot (fecxbot_cron.php), NOT by the
	 * editorial workflow. They track whether a bonus has already been
	 * announced on X / Telegram:
	 *   tweet_state, tg_state, dc_state - per-channel send state
	 *                            ('sent', 'dup', ...) for X / Telegram / Discord
	 *   x_reannounce           - manual re-announce flag
	 *   tweeted_at             - timestamp of the X post
	 *
	 * Neither duplicate() nor restore() may carry these values around:
	 *  - duplicate: a clone inheriting 'sent' would NEVER be announced
	 *    by the bot (it looks already posted). Excluding the columns
	 *    from the INSERT lets the schema defaults apply - exactly the
	 *    state a brand-new bonus gets from save(), so the cron picks
	 *    the duplicate up like any fresh bonus.
	 *  - restore: writing back the snapshot's historical state could
	 *    resurrect an already-announced bonus in the bot's queue and
	 *    double-post it (Telegram has no duplicate rejection). The
	 *    LIVE values must survive a restore untouched.
	 */
	private static $botStateColumns = array(
		'tweet_state',
		'tg_state',
		'dc_state',
		'x_reannounce',
		'tweeted_at',
	);

	/* =================================================================
	 * SAVE  (insert / update)  -  JSON response
	 * ================================================================= */

	public function save()
	{
		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput = JFactory::getApplication()->input;
		$db     = JFactory::getDbo();

		$bid      = (int) $jinput->get('bid', 0, 'int');
		$isUpdate = ($bid > 0);

		// ---- collect + validate the essentials ----------------------
		$cid  = (int) $jinput->get('casino', 0, 'int');
		$type = strtolower(trim($jinput->get('type', '', 'string')));

		$errors = array();

		if ($cid <= 0)
		{
			$errors[] = 'Please select a casino.';
		}

		if ($type === '')
		{
			$errors[] = 'Please select a bonus type.';
		}
		elseif (!in_array($type, BonusesHelperBonus::validTypeKeys(), true))
		{
			$errors[] = 'Unknown bonus type.';
		}

		if ($errors)
		{
			$this->jsonOut(false, implode(' ', $errors));
		}

		$brand = BonusesHelperBonus::getBrand($cid);

		if ($brand === '')
		{
			$this->jsonOut(false, 'The selected casino could not be found.');
		}

		// ---- editorial author (the "who wrote it" credit) ---------
		$postedAuthorId = (int) $jinput->get('author_id', 0, 'int');

		if ($postedAuthorId > 0 && BonusesHelperBonus::isActiveAuthor($postedAuthorId))
		{
			$authorId = $postedAuthorId;
		}
		elseif (!$isUpdate)
		{
			$authorId = BonusesHelperBonus::getDefaultAuthorId();
		}
		else
		{
			$authorId = null;
		}

		// ---- editorial last-editor (modified_by) ------------------
		// Only meaningful on update. On insert this stays NULL.
		// Validation mirrors author_id: must be an ACTIVE row in
		// jos_casino_authors. Empty/invalid falls back to the
		// default author so the credit is never lost on save.
		$modifiedById = null;

		if ($isUpdate)
		{
			$postedModifiedBy = (int) $jinput->get('modified_by', 0, 'int');

			if ($postedModifiedBy > 0 && BonusesHelperBonus::isActiveAuthor($postedModifiedBy))
			{
				$modifiedById = $postedModifiedBy;
			}
			else
			{
				$modifiedById = BonusesHelperBonus::getDefaultAuthorId();
			}
		}

		// ---- build the row object -----------------------------------
		$row = new stdClass;

		if ($isUpdate)
		{
			$row->bid = $bid;
		}

		$row->cid   = $cid;
		$row->brand = $brand;
		$row->type  = $type;
		$row->label = BonusesHelperBonus::typeLabel($type);

		$row->players     = $jinput->get('players', '', 'string');
		$row->platform    = $jinput->get('platform', '', 'string');
		$row->exclusive   = $jinput->get('exclusive', '', 'string');
		$row->currency    = $jinput->get('currency', '', 'string');
		$row->fp_time_den = $jinput->get('fptimeden', '', 'string');
		$row->code        = $jinput->get('code', '', 'string');
		$row->wr_meth     = $jinput->get('wrmethod', '', 'string');
		$row->cashable    = $jinput->get('cashable', '', 'string');

		$row->bonus       = $this->numOrNull($jinput->get('bonus', '', 'string'));
		$row->val_spin    = $this->numOrNull($jinput->get('valxspin', '', 'string'));
		$row->fp_time     = $this->numOrNull($jinput->get('fptime', '', 'string'));
		$row->max_bonus   = $this->numOrNull($jinput->get('maxbonus', '', 'string'));
		$row->min_dep     = $this->numOrNull($jinput->get('mindep', '', 'string'));
		$row->max_dep     = $this->numOrNull($jinput->get('maxdep', '', 'string'));
		$row->min_cashout = $this->numOrNull($jinput->get('mincash', '', 'string'));
		$row->max_cashout = $this->numOrNull($jinput->get('maxcash', '', 'string'));
		// max_cashout_kind: unit of the cap (abs money / x_dep / x_bonus).
		// Enum-sanitized; anything else collapses to '' (unclassified).
		// Deliberately SOFT server-side: the client validator enforces the
		// editorial rule (positive cap requires a kind), but a server-side
		// hard fail here would redirect to the list and lose the whole
		// form - same hostility the related-field guard below avoids.
		$mcKind = trim((string) $jinput->get('maxcashkind', '', 'string'));
		$row->max_cashout_kind = in_array($mcKind, array('abs', 'x_dep', 'x_bonus'), true) ? $mcKind : '';
		$row->wr          = $this->numOrNull($jinput->get('wr', '', 'string'));
		$row->max_bet     = $this->numOrNull($jinput->get('maxbet', '', 'string'));
		// related bonus: must reference an EXISTING bonus of the SAME
		// casino, and cannot point at the bonus being edited. Anything
		// else is silently nulled - related is an optional side field,
		// failing the whole save over it would be hostile. (The data
		// audit found exactly one historical cross-casino value: this
		// guard keeps that an anomaly instead of a pattern.)
		$relatedBid   = (int) $jinput->get('related', 0, 'int');
		$row->related = null;

		if ($relatedBid > 0 && $relatedBid !== $bid)
		{
			$relatedCid = $this->fetchColumn($relatedBid, 'cid');

			if ($relatedCid !== false && (int) $relatedCid === $cid)
			{
				$row->related = $relatedBid;
			}
		}

		$gid       = (int) $jinput->get('game', 0, 'int');
		$row->gid  = ($gid > 0) ? $gid : null;
		$row->game = ($gid > 0) ? BonusesHelperBonus::getGameName($gid) : '';

		$row->gamesok    = BonusesHelperBonus::joinStarlist($jinput->get('gamesok', array(), 'array'));
		$row->gamesno    = BonusesHelperBonus::joinStarlist($jinput->get('gamesno', array(), 'array'));
		$row->bankok     = BonusesHelperBonus::joinStarlist($jinput->get('bankok', array(), 'array'));
		$row->bankno     = BonusesHelperBonus::joinStarlist($jinput->get('bankno', array(), 'array'));
		$row->country_ok = BonusesHelperBonus::joinStarlist($jinput->get('countriesok', array(), 'array'));
		$row->country_no = BonusesHelperBonus::joinStarlist($jinput->get('countriesno', array(), 'array'));

		$row->link = $jinput->get('link', '', 'string');
		$row->tc   = $jinput->get('tc', '', 'string');

		$row->additional_infos = $this->cleanInfos();

		$row->start = BonusesHelperBonus::formToDbDate($jinput->get('start', '', 'string'));
		$row->end   = BonusesHelperBonus::formToDbDate($jinput->get('end', '', 'string'));

		$row->author_id = $authorId;

		// ---- status -------------------------------------------------
		$autoStatus = $this->autoStatus($row->start);

		if ($isUpdate)
		{
			$current = $this->fetchColumn($bid, 'status');

			if ($current === false)
			{
				$this->jsonOut(false, 'Bonus not found.');
			}

			$row->status = ($current === 'expired') ? 'expired' : $autoStatus;

			// modified tracking: timestamp on every update + the
			// chosen editorial last-editor
			$this->stampModified($row);
			$row->modified_by = $modifiedById;
		}
		else
		{
			$row->status          = $autoStatus;
			$row->added           = date('Y-m-d H:i:s');
			$row->bonus_author_id = (int) JFactory::getUser()->id;
			// modified / modified_by stay NULL on insert - a fresh
			// bonus has not been "modified" yet, only "added".
		}

		// ---- versioning snapshot (before the write) ----------------
		if ($isUpdate)
		{
			$this->snapshotBonus($bid, 'edit');
		}

		// ---- persist ------------------------------------------------
		try
		{
			if ($isUpdate)
			{
				$ok = $db->updateObject('#__bonuses', $row, 'bid', true);
			}
			else
			{
				$ok  = $db->insertObject('#__bonuses', $row);
				$bid = (int) $db->insertid();
			}
		}
		catch (Exception $e)
		{
			$this->jsonOut(false, 'Database error: ' . $e->getMessage());
		}

		if (empty($ok))
		{
			$this->jsonOut(false, 'The bonus could not be saved.');
		}

		// ---- welcome package (soft: never fails the save) -----------
		$pkgNote = '';

		try
		{
			$pkgNote = $this->syncPackage(
				$bid, $cid, $type, $row->additional_infos,
				$row->bonus, $row->max_bonus, (string) $row->currency
			);
		}
		catch (Exception $e)
		{
			error_log('com_bonuses syncPackage: ' . $e->getMessage());
			$pkgNote = 'Package not updated (' . $e->getMessage() . ').';
		}

		$this->purgeSiteCache();
		$this->jsonOut(
			true,
			($isUpdate ? 'Bonus updated.' : 'Bonus created.') . ($pkgNote !== '' ? ' ' . $pkgNote : ''),
			array(
				'bid'      => $bid,
				'redirect' => JRoute::_($this->listUrl, false),
			)
		);
	}

	/* =================================================================
	 * TOGGLE status  -  redirect back to the list
	 * ================================================================= */

	public function toggle()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken('get'))
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), JText::_('JINVALID_TOKEN'), 'error');

			return;
		}

		$bid = (int) JFactory::getApplication()->input->get('bid', 0, 'int');

		if ($bid <= 0)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'No bonus specified.', 'error');

			return;
		}

		$current = $this->fetchColumn($bid, 'status');

		if ($current === false)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'Bonus not found.', 'error');

			return;
		}

		if ($current === 'expired')
		{
			$newStatus = $this->autoStatus($this->fetchColumn($bid, 'start'));
		}
		else
		{
			$newStatus = 'expired';
		}

		$row         = new stdClass;
		$row->bid    = $bid;
		$row->status = $newStatus;
		// stamp the timestamp but NOT modified_by - a toggle is an
		// operational change, the editorial credit must not flip.
		$this->stampModified($row);

		try
		{
			JFactory::getDbo()->updateObject('#__bonuses', $row, 'bid');
		}
		catch (Exception $e)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'Database error.', 'error');

			return;
		}

		$this->purgeSiteCache();
		$this->setRedirect(
			JRoute::_($this->listUrl . '#bonus-' . $bid, false),
			'Bonus #' . $bid . ' is now ' . $newStatus . '.'
		);
	}

	/* =================================================================
	 * REMOVE  -  redirect back to the list
	 * ================================================================= */

	public function remove()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken('get'))
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), JText::_('JINVALID_TOKEN'), 'error');

			return;
		}

		$bid = (int) JFactory::getApplication()->input->get('bid', 0, 'int');

		if ($bid <= 0)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'No bonus specified.', 'error');

			return;
		}

		// snapshot the row before destroying it
		$this->snapshotBonus($bid, 'delete');

		// the row may be a leg of a welcome package: turn that leg
		// virtual with the row's terms copied, so the package keeps
		// its FXRate price after the row is gone
		try
		{
			BonusesHelperBonus::detachBid($bid, true);
		}
		catch (Exception $e)
		{
			error_log('com_bonuses remove detachBid: ' . $e->getMessage());
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__bonuses'))
			->where($db->quoteName('bid') . ' = ' . (int) $bid);

		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (Exception $e)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'Database error.', 'error');

			return;
		}

		$this->purgeSiteCache();
		$this->setRedirect(JRoute::_($this->listUrl, false), 'Bonus #' . $bid . ' deleted.');
	}

	/* =================================================================
	 * DUPLICATE  -  redirect back to the list
	 * ================================================================= */

	public function duplicate()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken('get'))
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), JText::_('JINVALID_TOKEN'), 'error');

			return;
		}

		$bid = (int) JFactory::getApplication()->input->get('bid', 0, 'int');

		if ($bid <= 0)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'No bonus specified.', 'error');

			return;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('bid') . ' = ' . (int) $bid);

		$db->setQuery($query);
		$src = $db->loadObject();

		if (!$src)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'Bonus not found.', 'error');

			return;
		}

		$dup = (object) array_diff_key(
			(array) $src,
			// never copy the PK, and never copy the social bot's send
			// state (see $botStateColumns above): omitting the columns
			// from the INSERT gives the duplicate the schema defaults,
			// i.e. the same untouched state as a brand-new bonus, so
			// fecxbot announces it like any fresh listing.
			array('bid' => true) + array_fill_keys(self::$botStateColumns, true)
		);

		$dup->status          = 'pending';
		$dup->added           = date('Y-m-d H:i:s');
		$dup->bonus_author_id = (int) JFactory::getUser()->id;
		// the duplicate is a fresh row - it has not been "modified",
		// only "added" right now. modified/modified_by start NULL.
		$dup->modified    = null;
		$dup->modified_by = null;

		try
		{
			$ok      = $db->insertObject('#__bonuses', $dup);
			$newBid  = (int) $db->insertid();
		}
		catch (Exception $e)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'Database error: ' . $e->getMessage(), 'error');

			return;
		}

		if (empty($ok) || $newBid <= 0)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'The bonus could not be duplicated.', 'error');

			return;
		}

		$this->setRedirect(
			JRoute::_($this->listUrl . '#bonus-' . $newBid, false),
			'Bonus #' . $bid . ' duplicated as #' . $newBid . ' (pending).'
		);
	}

	/* =================================================================
	 * RESTORE  -  redirect back to the edit form
	 * ================================================================= */

	public function restore()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken('get'))
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), JText::_('JINVALID_TOKEN'), 'error');

			return;
		}

		$bid = (int) JFactory::getApplication()->input->get('bid', 0, 'int');

		if ($bid <= 0)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'No bonus specified.', 'error');

			return;
		}

		if ($this->fetchColumn($bid, 'status') === false)
		{
			$this->setRedirect(JRoute::_($this->listUrl, false), 'Bonus not found.', 'error');

			return;
		}

		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
			->select($db->quoteName(array('id', 'snapshot', 'saved_at')))
			->from($db->quoteName('#__bonuses_history'))
			->where($db->quoteName('bid') . ' = ' . (int) $bid)
			->where(
				$db->quoteName('reason') . ' IN ('
				. $db->quote('edit') . ', ' . $db->quote('delete')
				. ')'
			)
			->order($db->quoteName('saved_at') . ' DESC')
			->order($db->quoteName('id') . ' DESC');

		$db->setQuery($query, 0, 1);
		$histRow = $db->loadObject();

		if (!$histRow)
		{
			$this->setRedirect(
				JRoute::_('index.php?option=com_bonuses&view=add&bid=' . $bid, false),
				'No previous version found for this bonus.',
				'error'
			);

			return;
		}

		$snapshot = json_decode($histRow->snapshot, true);

		if (!is_array($snapshot) || empty($snapshot))
		{
			$this->setRedirect(
				JRoute::_('index.php?option=com_bonuses&view=add&bid=' . $bid, false),
				'The previous version could not be decoded.',
				'error'
			);

			return;
		}

		$this->snapshotBonus($bid, 'restore');

		$row = new stdClass;

		foreach ($snapshot as $col => $val)
		{
			// Skip the PK, and skip the social bot's send state: the
			// snapshot carries the HISTORICAL announce state, and
			// writing it back could re-queue an already-announced
			// bonus in fecxbot (double post on Telegram). The live
			// values stay untouched - old snapshots taken before the
			// columns existed simply don't contain them.
			if ($col === 'bid' || in_array($col, self::$botStateColumns, true))
			{
				continue;
			}

			$row->{$col} = $val;
		}

		$row->bid = $bid;
		// Restore happened RIGHT NOW - the snapshot's `modified`
		// timestamp is historical and would be misleading. Override
		// with the current time. modified_by stays as in the snapshot
		// (the historical editorial credit at that point).
		$this->stampModified($row);

		try
		{
			JFactory::getDbo()->updateObject('#__bonuses', $row, 'bid', true);
		}
		catch (Exception $e)
		{
			$this->setRedirect(
				JRoute::_('index.php?option=com_bonuses&view=add&bid=' . $bid, false),
				'Database error during restore: ' . $e->getMessage(),
				'error'
			);

			return;
		}

		$this->setRedirect(
			JRoute::_('index.php?option=com_bonuses&view=add&bid=' . $bid, false),
			'Bonus #' . $bid . ' restored to the version saved on '
			. $histRow->saved_at . '.'
		);
	}

	/* =================================================================
	 * SET AUTHOR (single row, inline list)  -  JSON response
	 * ================================================================= */

	public function setAuthor()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput = JFactory::getApplication()->input;

		$bid       = (int) $jinput->get('bid', 0, 'int');
		$authorId  = (int) $jinput->get('author_id', 0, 'int');

		if ($bid <= 0)
		{
			$this->jsonOut(false, 'No bonus specified.');
		}

		if ($this->fetchColumn($bid, 'status') === false)
		{
			$this->jsonOut(false, 'Bonus not found.');
		}

		if ($authorId === 0)
		{
			$newAuthorId = null;
			$authorName  = '';
		}
		elseif (BonusesHelperBonus::isActiveAuthor($authorId))
		{
			$newAuthorId = $authorId;
			$authorName  = BonusesHelperBonus::getAuthorName($authorId);
		}
		else
		{
			$this->jsonOut(false, 'Invalid author.');
		}

		$row            = new stdClass;
		$row->bid       = $bid;
		$row->author_id = $newAuthorId;
		// Stamp modified timestamp but leave modified_by alone -
		// changing the editorial author is an editorial event but
		// it is NOT a content edit, and the "last editor" credit
		// should not silently change because of it.
		$this->stampModified($row);

		try
		{
			JFactory::getDbo()->updateObject('#__bonuses', $row, 'bid', true);
		}
		catch (Exception $e)
		{
			$this->jsonOut(false, 'Database error: ' . $e->getMessage());
		}

		$this->jsonOut(
			true,
			'Author updated.',
			array(
				'bid'         => $bid,
				'author_id'   => $newAuthorId,
				'author_name' => $authorName,
			)
		);
	}

	/* =================================================================
	 * SET MODIFIED_BY (single row, inline list)  -  JSON response
	 * ================================================================= */

	/**
	 * Changes the editorial last-editor of a single bonus, called via
	 * AJAX from the inline dropdown on the manage list. Mirrors
	 * setAuthor() but writes modified_by and ALSO stamps the modified
	 * timestamp to now (the row has just been edited - this call is
	 * itself the edit).
	 *
	 * POST  index.php?option=com_bonuses&task=actions.setmodifiedby
	 * body  bid=NNN&modified_by=NN&<csrf_token>=1
	 *
	 * @return void
	 */
	public function setModifiedBy()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput = JFactory::getApplication()->input;

		$bid         = (int) $jinput->get('bid', 0, 'int');
		$modifiedBy  = (int) $jinput->get('modified_by', 0, 'int');

		if ($bid <= 0)
		{
			$this->jsonOut(false, 'No bonus specified.');
		}

		if ($this->fetchColumn($bid, 'status') === false)
		{
			$this->jsonOut(false, 'Bonus not found.');
		}

		if ($modifiedBy === 0)
		{
			$newModifiedById = null;
			$authorName      = '';
		}
		elseif (BonusesHelperBonus::isActiveAuthor($modifiedBy))
		{
			$newModifiedById = $modifiedBy;
			$authorName      = BonusesHelperBonus::getAuthorName($modifiedBy);
		}
		else
		{
			$this->jsonOut(false, 'Invalid author.');
		}

		$row              = new stdClass;
		$row->bid         = $bid;
		$row->modified_by = $newModifiedById;
		$this->stampModified($row);

		try
		{
			JFactory::getDbo()->updateObject('#__bonuses', $row, 'bid', true);
		}
		catch (Exception $e)
		{
			$this->jsonOut(false, 'Database error: ' . $e->getMessage());
		}

		$this->jsonOut(
			true,
			'Last editor updated.',
			array(
				'bid'         => $bid,
				'modified_by' => $newModifiedById,
				'author_name' => $authorName,
				'modified'    => $row->modified,
			)
		);
	}

	/* =================================================================
	 * RELATED OPTIONS  (read-only list for the form's select)  -  JSON
	 * ================================================================= */

	/**
	 * Returns the bonuses of one casino as options for the
	 * "Related bonus" select on the add/edit form. Replaces the
	 * legacy helpers/related.php endpoint for the dashboard (that
	 * file is left on disk untouched in case the public site still
	 * includes it).
	 *
	 * GET  index.php?option=com_bonuses&task=actions.relatedoptions
	 *      &cid=NN&exclude=NN&<csrf_token>=1
	 *
	 *   cid      casino id whose bonuses to list (required)
	 *   exclude  bid to leave out - the bonus being edited cannot
	 *            be related to itself (optional, 0 in add mode)
	 *
	 * Response: { ok, message, options: [ { bid, text } ] }
	 *
	 * Option text format: "#bid - label - value CUR (status)", e.g.
	 * "#675 - 1st Deposit Match - 100 EUR (active)". Ordering puts
	 * active first, then pending, then expired; newest first within
	 * each group - the bonus you want to link is almost always a
	 * live one. Capped at 200 rows per casino.
	 *
	 * Read-only: no DB writes. Still token-gated like every other
	 * task for uniformity (the form has the token at hand anyway).
	 *
	 * @return void
	 */
	public function relatedOptions()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken('get'))
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput  = JFactory::getApplication()->input;
		$cid     = (int) $jinput->get('cid', 0, 'int');
		$exclude = (int) $jinput->get('exclude', 0, 'int');

		if ($cid <= 0)
		{
			// no casino chosen yet -> empty list, not an error
			$this->jsonOut(true, '', array('options' => array()));
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('bid', 'label', 'type', 'bonus', 'currency', 'status')))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('cid') . ' = ' . $cid);

		if ($exclude > 0)
		{
			$query->where($db->quoteName('bid') . ' != ' . $exclude);
		}

		// active first, then pending, then expired; unknown statuses
		// (FIELD() returns 0 for them) sort before everything - they
		// should not exist, and if they do we want to SEE them.
		$query->order(
			'FIELD(' . $db->quoteName('status') . ', '
			. $db->quote('active') . ', ' . $db->quote('pending') . ', ' . $db->quote('expired')
			. ')'
		);
		$query->order($db->quoteName('added') . ' DESC');

		$db->setQuery($query, 0, 200);

		try
		{
			$rows = (array) $db->loadObjectList();
		}
		catch (Exception $e)
		{
			$this->jsonOut(false, 'Database error: ' . $e->getMessage());
		}

		$options = array();

		foreach ($rows as $r)
		{
			$label = trim((string) $r->label);

			if ($label === '')
			{
				$label = BonusesHelperBonus::typeLabel($r->type);
			}

			$bits = array('#' . (int) $r->bid, $label);

			if ($r->bonus !== null && $r->bonus !== '')
			{
				$value = (string) $r->bonus;

				if ((string) $r->currency !== '')
				{
					$value .= ' ' . strtoupper($r->currency);
				}

				$bits[] = $value;
			}

			$options[] = array(
				'bid'  => (int) $r->bid,
				'text' => implode(" \xC2\xB7 ", $bits) . ' (' . (string) $r->status . ')',
			);
		}

		$this->jsonOut(true, '', array('options' => $options));
	}

	/* =================================================================
	 * BULK SET STATUS  (expire / publish on many)  -  JSON response
	 * ================================================================= */

	public function bulkSetStatus()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput = JFactory::getApplication()->input;

		$action = strtolower(trim((string) $jinput->get('action', '', 'string')));
		$bids   = $this->collectBids($jinput);

		if (!in_array($action, array('expire', 'publish'), true))
		{
			$this->jsonOut(false, 'Unknown bulk action.');
		}

		if (empty($bids))
		{
			$this->jsonOut(false, 'No bonuses selected.');
		}

		$db        = JFactory::getDbo();
		$processed = 0;
		$errors    = array();

		foreach ($bids as $bid)
		{
			$bid = (int) $bid;

			if ($bid <= 0)
			{
				continue;
			}

			if ($action === 'expire')
			{
				$newStatus = 'expired';
			}
			else
			{
				$start     = $this->fetchColumn($bid, 'start');
				$newStatus = $this->autoStatus($start === false ? null : $start);
			}

			$row         = new stdClass;
			$row->bid    = $bid;
			$row->status = $newStatus;
			$this->stampModified($row);

			try
			{
				$ok = $db->updateObject('#__bonuses', $row, 'bid');

				if ($ok)
				{
					$processed++;
				}
				else
				{
					$errors[] = $bid;
				}
			}
			catch (Exception $e)
			{
				$errors[] = $bid;
			}
		}

		$verb = ($action === 'expire') ? 'expired' : 'published';
		$msg  = $processed . ' bonus' . ($processed === 1 ? '' : 'es') . ' ' . $verb . '.';

		if (!empty($errors))
		{
			$msg .= ' ' . count($errors) . ' failed.';
		}

		$this->jsonOut(
			(bool) $processed,
			$msg,
			array(
				'processed' => $processed,
				'errors'    => $errors,
			)
		);
	}

	/* =================================================================
	 * BULK DELETE  -  JSON response
	 * ================================================================= */

	public function bulkDelete()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput = JFactory::getApplication()->input;
		$bids   = $this->collectBids($jinput);

		if (empty($bids))
		{
			$this->jsonOut(false, 'No bonuses selected.');
		}

		$db        = JFactory::getDbo();
		$processed = 0;
		$errors    = array();

		foreach ($bids as $bid)
		{
			$bid = (int) $bid;

			if ($bid <= 0)
			{
				continue;
			}

			$this->snapshotBonus($bid, 'delete');

			try
			{
				BonusesHelperBonus::detachBid($bid, true);
			}
			catch (Exception $e)
			{
				error_log('com_bonuses bulkDelete detachBid: ' . $e->getMessage());
			}

			$query = $db->getQuery(true)
				->delete($db->quoteName('#__bonuses'))
				->where($db->quoteName('bid') . ' = ' . $bid);
			$db->setQuery($query);

			try
			{
				$db->execute();
				$processed++;
			}
			catch (Exception $e)
			{
				$errors[] = $bid;
			}
		}

		$msg = $processed . ' bonus' . ($processed === 1 ? '' : 'es') . ' deleted.';

		if (!empty($errors))
		{
			$msg .= ' ' . count($errors) . ' failed.';
		}

		$this->jsonOut(
			(bool) $processed,
			$msg,
			array(
				'processed' => $processed,
				'errors'    => $errors,
			)
		);
	}

	/* =================================================================
	 * BULK SET AUTHOR  -  JSON response
	 * ================================================================= */

	public function bulkSetAuthor()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput   = JFactory::getApplication()->input;
		$authorId = (int) $jinput->get('author_id', 0, 'int');
		$bids     = $this->collectBids($jinput);

		if (empty($bids))
		{
			$this->jsonOut(false, 'No bonuses selected.');
		}

		if ($authorId === 0)
		{
			$newAuthorId = null;
			$authorName  = '';
		}
		elseif (BonusesHelperBonus::isActiveAuthor($authorId))
		{
			$newAuthorId = $authorId;
			$authorName  = BonusesHelperBonus::getAuthorName($authorId);
		}
		else
		{
			$this->jsonOut(false, 'Invalid author.');
		}

		$db        = JFactory::getDbo();
		$processed = 0;
		$errors    = array();

		foreach ($bids as $bid)
		{
			$bid = (int) $bid;

			if ($bid <= 0)
			{
				continue;
			}

			$row            = new stdClass;
			$row->bid       = $bid;
			$row->author_id = $newAuthorId;
			$this->stampModified($row);

			try
			{
				$ok = $db->updateObject('#__bonuses', $row, 'bid', true);

				if ($ok)
				{
					$processed++;
				}
				else
				{
					$errors[] = $bid;
				}
			}
			catch (Exception $e)
			{
				$errors[] = $bid;
			}
		}

		$nameTag = ($authorName !== '') ? (' (' . $authorName . ')') : ' (no author)';
		$msg     = $processed . ' bonus' . ($processed === 1 ? '' : 'es') . ' reassigned' . $nameTag . '.';

		if (!empty($errors))
		{
			$msg .= ' ' . count($errors) . ' failed.';
		}

		$this->jsonOut(
			(bool) $processed,
			$msg,
			array(
				'processed'   => $processed,
				'errors'      => $errors,
				'author_id'   => $newAuthorId,
				'author_name' => $authorName,
			)
		);
	}

	/* =================================================================
	 * PACKAGE OPTIONS  (a casino's packages + ladder rows)  -  JSON
	 * ================================================================= */

	/**
	 * Everything the Welcome package section needs for one casino:
	 * its packages with legs and FXRate line, the ladder-type bonus
	 * rows that can be legs, and which package (if any) the bonus being
	 * edited belongs to. Read-only, token-gated like relatedoptions.
	 *
	 * GET  index.php?option=com_bonuses&task=actions.packageoptions
	 *      &cid=NN&exclude=NN&<csrf_token>=1
	 *
	 * @return void
	 */
	public function packageOptions()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken('get'))
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput  = JFactory::getApplication()->input;
		$cid     = (int) $jinput->get('cid', 0, 'int');
		$exclude = (int) $jinput->get('exclude', 0, 'int');

		if ($cid <= 0)
		{
			$this->jsonOut(true, '', array('packages' => array(), 'rows' => array(), 'this_pkg_id' => 0));
		}

		$this->jsonOut(true, '', BonusesHelperBonus::packagePayload($cid, $exclude));
	}

	/* =================================================================
	 * PACKAGE DETECT  (parse the article)  -  JSON
	 * ================================================================= */

	/**
	 * Runs fec_fxrate_text_parse() on the article the editor is typing
	 * (posted as 'infos', read raw from $_POST like cleanInfos) and
	 * returns what it states: package yes/no, deposits, leg number,
	 * headline, track, per-leg rows. Pure read, nothing is saved.
	 *
	 * POST index.php?option=com_bonuses&task=actions.packagedetect
	 *      infos=<html>&own_pct=N&own_cap=N&<csrf_token>=1
	 *
	 * @return void
	 */
	public function packageDetect()
	{
		$this->preventCaching();

		BonusesHelperBonus::assertAuthorized();

		if (!JSession::checkToken())
		{
			$this->jsonOut(false, JText::_('JINVALID_TOKEN'));
		}

		$jinput = JFactory::getApplication()->input;
		$infos  = isset($_POST['infos']) ? (string) $_POST['infos'] : '';
		$ownPct = $this->numOrNull($jinput->get('own_pct', '', 'string'));
		$ownCap = $this->numOrNull($jinput->get('own_cap', '', 'string'));

		$t = BonusesHelperBonus::parseArticleForPackage($infos, $ownPct, $ownCap);

		if ($t === null)
		{
			$this->jsonOut(false, 'The article parser (fec_fxrate_text.php) is not installed.');
		}

		$this->jsonOut(true, $t['summary'], array('detected' => $t));
	}

	/**
	 * Applies the Welcome package section of the form after the bonus
	 * row is saved. Returns a short note for the success message ('' =
	 * nothing to say). Never throws past save(): callers wrap it.
	 *
	 * pkg_choice:
	 *   auto     read the article; create a text-sourced package or
	 *            refresh the one this row is in (only if text-sourced),
	 *            or attach the row to the one package of the casino
	 *            that has an empty slot at this row's position
	 *   none     detach the row (its leg turns virtual, terms copied)
	 *   new      create a package from the posted fields + legs
	 *   pkg:N    update package N from the posted fields + legs
	 *
	 * The row being saved always occupies the position its type gives
	 * (su = 1, 2d = 2 ...), whatever the posted legs say.
	 *
	 * @return string
	 */
	private function syncPackage($bid, $cid, $type, $infos, $ownPct, $ownCap, $currency)
	{
		$bid      = (int) $bid;
		$cid      = (int) $cid;
		$position = BonusesHelperBonus::ladderPosition($type);
		$jinput   = JFactory::getApplication()->input;
		$choice   = strtolower(trim((string) $jinput->get('pkg_choice', 'auto', 'string')));

		if ($position === 0)
		{
			// not a ladder type (any more): it cannot be a leg
			$n = BonusesHelperBonus::detachBid($bid, true);

			return $n ? 'Detached from its welcome package (the type is not a deposit match).' : '';
		}

		if ($choice === 'none')
		{
			$n = BonusesHelperBonus::detachBid($bid, true);

			return $n ? 'Detached from its welcome package.' : '';
		}

		if ($choice === 'new' || strpos($choice, 'pkg:') === 0)
		{
			$pkgId = ($choice === 'new') ? 0 : (int) substr($choice, 4);

			if ($pkgId > 0)
			{
				$p = BonusesHelperBonus::getPackage($pkgId);

				if (!$p || (int) $p->pkg->cid !== $cid)
				{
					return 'Package #' . $pkgId . ' does not belong to this casino: not linked.';
				}
			}

			$data = array(
				'title'       => $jinput->get('pkg_title', 'Welcome Package', 'string'),
				'headline'    => $jinput->get('pkg_headline', '', 'string'),
				'currency'    => (string) $currency,
				'deposits'    => (int) $jinput->get('pkg_deposits', 0, 'int'),
				'spins_total' => (int) $jinput->get('pkg_spins_total', 0, 'int'),
				'window_days' => $jinput->get('pkg_window_days', '', 'string'),
				'sequential'  => (int) $jinput->get('pkg_sequential', 1, 'int'),
				'status'      => $jinput->get('pkg_status', 'active', 'string'),
				'notes'       => $jinput->get('pkg_notes', '', 'string'),
				'alt_of'      => (int) $jinput->get('pkg_alt_of', 0, 'int'),
			);

			if ($data['deposits'] < $position)
			{
				$data['deposits'] = $position;
			}

			$legs = $this->postedLegs($cid, $bid, $data['deposits']);

			// the row being saved owns its own position (its spins and their wagering are typed in the table)
			$legs[$position] = array(
				'bid'      => $bid,
				'spins'    => isset($legs[$position]['spins']) ? (int) $legs[$position]['spins'] : 0,
				'spins_wr' => isset($legs[$position]['spins_wr']) ? (string) $legs[$position]['spins_wr'] : '',
			);

			if ($position === 1)
			{
				$data['opener_bid'] = $bid;
			}

			$pkgId = BonusesHelperBonus::savePackage($pkgId, $cid, $data, $legs, 'editor', true);

			if ($pkgId <= 0)
			{
				return 'The welcome package could not be saved.';
			}

			// a row is a leg of one package at most
			BonusesHelperBonus::detachBid($bid, false, $pkgId);

			return 'Welcome package #' . $pkgId . ' saved.';
		}

		// ---- auto: what does the article say? ----------------------------
		$t = BonusesHelperBonus::parseArticleForPackage((string) $infos, $ownPct, $ownCap);

		if ($t === null)
		{
			return '';
		}

		// the stated count, else the highest "Nth deposit" row of the kvlist
		// (rows 2 and 3 alone mean a three-deposit package, not two legs)
		$topRow   = !empty($t['legs_stated']) ? (int) max(array_keys($t['legs_stated'])) : 0;
		$detected = ($t['deposits'] !== null) ? (int) $t['deposits'] : ($topRow > 1 ? $topRow : 0);
		$existing = BonusesHelperBonus::getPackageForBid($bid);

		if (!$t['package'] || $detected < 2)
		{
			return '';
		}

		if ($existing)
		{
			if ((string) $existing->pkg->source !== 'text')
			{
				return '';
			}

			$data = array('deposits' => max((int) $existing->pkg->deposits, $detected));

			if ($t['headline'])
			{
				$data['headline'] = $t['headline']['raw'];

				if ((int) $t['headline']['spins'] > 0)
				{
					$data['spins_total'] = (int) $t['headline']['spins'];
				}
			}

			if ($t['track'] !== null)
			{
				$data['title'] = $t['track'];
			}

			$legs = array();

			foreach ($existing->legs as $l)
			{
				$pos = (int) $l['position'];

				$spinsWr = isset($l['spins_wr']) ? $l['spins_wr'] : null;

				if ($l['bid'] !== null)
				{
					$legs[$pos] = array('bid' => (int) $l['bid'], 'spins' => (int) $l['spins'], 'spins_wr' => $spinsWr);

					if ($spinsWr === null && (int) $l['spins'] > 0)
					{
						$legs[$pos]['spins_wr'] = $this->textSpinsWr($t, $pos);
					}

					continue;
				}

				$legs[$pos] = array('bid' => 0, 'pct' => $l['pct'], 'cap' => $l['cap'], 'min_dep' => $l['min_dep'], 'max_dep' => $l['max_dep'],
					'wr' => $l['wr'], 'wr_meth' => $l['wr_meth'],
					'max_cashout' => isset($l['max_cashout']) ? $l['max_cashout'] : null, 'max_cashout_kind' => isset($l['max_cashout_kind']) ? $l['max_cashout_kind'] : '',
					'spins' => (int) $l['spins'], 'spins_wr' => $spinsWr, 'code' => $l['code'], 'source' => $l['source']);

				// a leg that came from the text and still lacks a term takes it
				// from the article as it reads now (the editor adds "up to" later,
				// re-saves, the leg fills in); a typed term is never overwritten
				if ((string) $l['source'] === 'text' && isset($t['legs_stated'][$pos]))
				{
					$ls = $t['legs_stated'][$pos];

					if ($legs[$pos]['pct'] === null && isset($ls['pct']) && $ls['pct'] !== null)
					{
						$legs[$pos]['pct'] = $ls['pct'];
					}

					if ($legs[$pos]['cap'] === null && isset($ls['cap']) && $ls['cap'] !== null)
					{
						$legs[$pos]['cap'] = $ls['cap'];
					}

					if ((int) $legs[$pos]['spins'] === 0 && !empty($ls['spins']))
					{
						$legs[$pos]['spins'] = (int) $ls['spins'];
					}

					if ($legs[$pos]['spins_wr'] === null && (int) $legs[$pos]['spins'] > 0)
					{
						$legs[$pos]['spins_wr'] = $this->textSpinsWr($t, $pos);
					}

					if ($legs[$pos]['max_cashout'] === null && isset($ls['max_cashout']) && $ls['max_cashout'] !== null)
					{
						$legs[$pos]['max_cashout']      = $ls['max_cashout'];
						$legs[$pos]['max_cashout_kind'] = isset($ls['max_cashout_kind']) ? $ls['max_cashout_kind'] : '';
					}
				}
			}

			for ($pos = 1; $pos <= $data['deposits']; $pos++)
			{
				if (!isset($legs[$pos]))
				{
					$legs[$pos] = $this->textLeg(isset($t['legs_stated'][$pos]) ? $t['legs_stated'][$pos] : null);
				}
			}

			$pkgId = BonusesHelperBonus::savePackage((int) $existing->pkg->pkg_id, $cid, $data, $legs, 'text', false);

			return $pkgId ? 'Welcome package #' . $pkgId . ' refreshed from the article.' : '';
		}

		// not in a package: is there one of this casino with an empty slot at this position?
		$candidates = array();

		foreach (BonusesHelperBonus::getPackagesForCasino($cid) as $p)
		{
			if ((string) $p->pkg->status !== 'active')
			{
				continue;
			}

			foreach ($p->legs as $l)
			{
				if ((int) $l['position'] === $position && $l['bid'] === null)
				{
					$candidates[] = (int) $p->pkg->pkg_id;
				}
			}
		}

		if (count($candidates) === 1)
		{
			BonusesHelperBonus::attachBid($candidates[0], $bid, $position);

			return 'Attached as deposit ' . $position . ' of welcome package #' . $candidates[0] . '.';
		}

		if (count($candidates) > 1)
		{
			return 'Several packages of this casino could take this leg: pick one in the Welcome package section.';
		}

		$deposits = max($detected, $position);
		$legs     = array($position => array('bid' => $bid));

		// the row's own spins (and their wagering) as the article states them for this deposit
		if (isset($t['legs_stated'][$position]) && !empty($t['legs_stated'][$position]['spins']))
		{
			$legs[$position]['spins']    = (int) $t['legs_stated'][$position]['spins'];
			$legs[$position]['spins_wr'] = $this->textSpinsWr($t, $position);
		}

		for ($pos = 1; $pos <= $deposits; $pos++)
		{
			if ($pos === $position)
			{
				continue;
			}

			$legs[$pos] = $this->textLeg(isset($t['legs_stated'][$pos]) ? $t['legs_stated'][$pos] : null);
		}

		$data = array(
			'title'       => ($t['track'] !== null) ? $t['track'] : 'Welcome Package',
			'headline'    => $t['headline'] ? $t['headline']['raw'] : '',
			'currency'    => (string) $currency,
			'deposits'    => $deposits,
			'spins_total' => $t['headline'] ? (int) $t['headline']['spins'] : 0,
			'opener_bid'  => ($position === 1) ? $bid : null,
			'notes'       => 'auto-detected from the article on save (' . date('Y-m-d') . ')',
		);

		$pkgId = BonusesHelperBonus::savePackage(0, $cid, $data, $legs, 'text', false);

		if ($pkgId <= 0)
		{
			return 'The article describes a ' . $deposits . '-deposit package, but it could not be saved.';
		}

		return 'Welcome package #' . $pkgId . ' created from the article (' . $deposits . ' deposits; enter the terms of the other legs).';
	}

	/**
	 * A virtual leg built from what the article states for that deposit
	 * (parser legs_stated row, or null when the row is absent): pct, cap
	 * and spins each only when stated, the rest left for the editor.
	 *
	 * @param   array|null  $ls
	 * @return  array
	 */
	private function textLeg($ls)
	{
		return array(
			'bid'    => 0,
			'pct'    => ($ls && isset($ls['pct']) && $ls['pct'] !== null) ? $ls['pct'] : null,
			'cap'    => ($ls && isset($ls['cap']) && $ls['cap'] !== null) ? $ls['cap'] : null,
			'spins'  => ($ls && !empty($ls['spins'])) ? (int) $ls['spins'] : 0,
			'spins_wr'         => ($ls && !empty($ls['spins']) && isset($ls['spins_wr']) && $ls['spins_wr'] !== null) ? $ls['spins_wr'] : null,
			'max_cashout'      => ($ls && isset($ls['max_cashout']) && $ls['max_cashout'] !== null) ? $ls['max_cashout'] : null,
			'max_cashout_kind' => ($ls && isset($ls['max_cashout_kind'])) ? (string) $ls['max_cashout_kind'] : '',
			'source' => 'text',
		);
	}

	/**
	 * The wagering the article states for the spins of one deposit: the
	 * leg row's own (parser 0.1.5 spins_wr), else the headline's when the
	 * package line states it for its spins ("+ 75 free spins, wager-free"
	 * speaks for every leg's spins); null when neither does.
	 *
	 * @param   array    $t    parser result
	 * @param   integer  $pos
	 * @return  mixed  float|null
	 */
	private function textSpinsWr(array $t, $pos)
	{
		if (isset($t['legs_stated'][$pos]['spins_wr']) && $t['legs_stated'][$pos]['spins_wr'] !== null)
		{
			return $t['legs_stated'][$pos]['spins_wr'];
		}

		if (!empty($t['headline']) && isset($t['headline']['spins_wr']) && $t['headline']['spins_wr'] !== null)
		{
			return $t['headline']['spins_wr'];
		}

		return null;
	}

	/**
	 * Reads the posted legs table (pkg_leg[pos][field]) straight from
	 * $_POST - JInput's array filter would not reach the nested keys -
	 * and sanitises every field. A posted bid is kept only when it is a
	 * ladder-type row of the same casino and not the row being saved
	 * (that one is pinned to its own position by the caller).
	 *
	 * @param   integer  $cid
	 * @param   integer  $selfBid
	 * @param   integer  $deposits
	 * @return  array  position => leg array
	 */
	private function postedLegs($cid, $selfBid, $deposits)
	{
		$raw = (isset($_POST['pkg_leg']) && is_array($_POST['pkg_leg'])) ? $_POST['pkg_leg'] : array();
		$out = array();

		$valid = array();

		foreach (BonusesHelperBonus::getLadderRows($cid) as $r)
		{
			$valid[(int) $r['bid']] = true;
		}

		for ($pos = 1; $pos <= max(1, min(10, (int) $deposits)); $pos++)
		{
			$leg = (isset($raw[$pos]) && is_array($raw[$pos])) ? $raw[$pos] : array();
			$bid = isset($leg['bid']) ? (int) $leg['bid'] : 0;

			if ($bid > 0 && (!isset($valid[$bid]) || $bid === (int) $selfBid))
			{
				$bid = 0;
			}

			$out[$pos] = array(
				'bid'     => $bid,
				'pct'     => isset($leg['pct']) ? (string) $leg['pct'] : '',
				'cap'     => isset($leg['cap']) ? (string) $leg['cap'] : '',
				'min_dep' => isset($leg['min_dep']) ? (string) $leg['min_dep'] : '',
				'max_dep' => isset($leg['max_dep']) ? (string) $leg['max_dep'] : '',
				'wr'      => isset($leg['wr']) ? (string) $leg['wr'] : '',
				'wr_meth' => isset($leg['wr_meth']) ? (string) $leg['wr_meth'] : '',
				'max_cashout'      => isset($leg['max_cashout']) ? (string) $leg['max_cashout'] : '',
				'max_cashout_kind' => isset($leg['max_cashout_kind']) ? (string) $leg['max_cashout_kind'] : '',
				'spins'   => isset($leg['spins']) ? (int) $leg['spins'] : 0,
				'spins_wr' => isset($leg['spins_wr']) ? (string) $leg['spins_wr'] : '',
				'code'    => isset($leg['code']) ? (string) $leg['code'] : '',
				'source'  => 'editor',
			);
		}

		return $out;
	}

	/* =================================================================
	 * Deprecated alias - the old form posted to this task name.
	 * ================================================================= */

	/**
	 * @return void
	 * @deprecated  use task=actions.save
	 */
	public function insert_indirect()
	{
		$this->save();
	}

	/* =================================================================
	 * Internal helpers
	 * ================================================================= */

	/**
	 * Sets ->modified = NOW() on a row object being passed to
	 * updateObject(). Every side-effect task that updates a bonus
	 * row calls this so the modified column always reflects "when
	 * was this row last written?". The caller is responsible for
	 * setting modified_by separately when appropriate - most tasks
	 * leave modified_by untouched.
	 *
	 * @param   stdClass  $row
	 * @return  void
	 */
	private function stampModified($row)
	{
		$row->modified = date('Y-m-d H:i:s');
	}

	/**
	 * Writes one row to jos_bonuses_history capturing the CURRENT
	 * state of the bonus identified by $bid. Used as a "before-write"
	 * hook by save() (update), remove(), bulkDelete() and restore().
	 *
	 * Failure is SWALLOWED on purpose: a broken journal must never
	 * abort a save or delete - the snapshot is a safety net, not a
	 * constraint.
	 *
	 * @param   integer  $bid     bid being modified / destroyed
	 * @param   string   $reason  'edit' | 'delete' | 'restore'
	 * @return  boolean
	 */
	private function snapshotBonus($bid, $reason)
	{
		$bid = (int) $bid;

		if ($bid <= 0)
		{
			return false;
		}

		if (!in_array($reason, array('edit', 'delete', 'restore'), true))
		{
			return false;
		}

		try
		{
			$db = JFactory::getDbo();

			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__bonuses'))
				->where($db->quoteName('bid') . ' = ' . $bid);

			$db->setQuery($query);
			$live = $db->loadAssoc();

			if (!$live)
			{
				return false;
			}

			$json = json_encode($live);

			if ($json === false)
			{
				error_log(
					'com_bonuses snapshotBonus: json_encode failed for bid=' . $bid
				);

				return false;
			}

			$histRow            = new stdClass;
			$histRow->bid       = $bid;
			$histRow->snapshot  = $json;
			$histRow->saved_at  = date('Y-m-d H:i:s');
			$histRow->saved_by  = (int) JFactory::getUser()->id;
			$histRow->reason    = $reason;

			return (bool) $db->insertObject('#__bonuses_history', $histRow);
		}
		catch (Exception $e)
		{
			error_log(
				'com_bonuses snapshotBonus: ' . $e->getMessage()
				. ' (bid=' . $bid . ', reason=' . $reason . ')'
			);

			return false;
		}
	}

	/**
	 * Reads the posted bids[] array, casts every entry to int, drops
	 * zero/negative values, deduplicates. Caps the batch at a sane
	 * upper bound.
	 *
	 * @param   JInput   $jinput
	 * @param   integer  $cap
	 * @return  array  list of distinct int bids
	 */
	private function collectBids($jinput, $cap = 500)
	{
		$raw = $jinput->get('bids', array(), 'array');

		if (!is_array($raw))
		{
			return array();
		}

		$out = array();

		foreach ($raw as $v)
		{
			$n = (int) $v;

			if ($n > 0)
			{
				$out[$n] = true;
			}
		}

		$out = array_keys($out);

		if (count($out) > $cap)
		{
			$out = array_slice($out, 0, $cap);
		}

		return $out;
	}

	/**
	 * Tells every cache layer between here and the browser NOT to keep
	 * a copy of this response. Used by every side-effect task.
	 *
	 * @return void
	 */
	private function preventCaching()
	{
		JResponse::allowCache(false);
		JResponse::setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);
		JResponse::setHeader('Pragma', 'no-cache', true);
		JResponse::setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		JResponse::setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);
	}

	/**
	 * Derives 'pending' or 'active' from a DB start datetime.
	 * No start date -> 'active'.
	 *
	 * @param   string|null  $startSql
	 * @return  string
	 */
	private function autoStatus($startSql)
	{
		if (empty($startSql))
		{
			return 'active';
		}

		try
		{
			$start = new DateTime($startSql);
			$now   = new DateTime;

			return ($now < $start) ? 'pending' : 'active';
		}
		catch (Exception $e)
		{
			return 'active';
		}
	}

	/**
	 * Reads a single column for a bid. Returns the value, or false if
	 * the row does not exist.
	 *
	 * @param   integer  $bid
	 * @param   string   $column  one of: status, start, cid
	 * @return  mixed|false
	 */
	private function fetchColumn($bid, $column)
	{
		$allowed = array('status', 'start', 'cid');

		if (!in_array($column, $allowed, true))
		{
			return false;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName($column))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('bid') . ' = ' . (int) $bid);

		$db->setQuery($query);
		$value = $db->loadResult();

		return ($value === null) ? false : $value;
	}

	/**
	 * Empty or non-numeric input -> null; otherwise the numeric value.
	 *
	 * @param   string  $value
	 * @return  float|int|null
	 */
	/**
	 * Ask LiteSpeed to drop the cached HTML for the whole vhost. Bonus
	 * edits must show up on the homepage and category grids immediately:
	 * those pages are LSCache HTML hits (query strings ignored in the
	 * cache key), and nothing else invalidates them on write. A site-wide
	 * stale-and-regenerate is cheap at this edit volume; per-URL purge
	 * lists would be fragile against sh404SEF rewrites. No credentials
	 * involved: LiteSpeed honours the response header natively.
	 */
	private function purgeSiteCache()
	{
		if (!headers_sent()) {
			fec_header('X-LiteSpeed-Purge: *');
		}
	}

	private function numOrNull($value)
	{
		$value = trim((string) $value);

		if ($value === '' || !is_numeric($value))
		{
			return null;
		}

		return $value + 0;
	}

	/**
	 * Pulls the 'infos' field raw and strips the dangerous parts,
	 * with a whitelist for FAQ JSON-LD <script> tags.
	 *
	 * Reads $_POST directly to bypass JFilterInput - Joomla 2.5's
	 * Text Filter mangles half of the HTML we want to keep before
	 * 'raw' is honoured.
	 *
	 * Sanitisation:
	 *   1. Whitelist <script type="application/ld+json"> blocks under
	 *      inert HTML-comment tokens, rebuilt with only the type
	 *      attribute (drops on* handlers / other attributes).
	 *   2. Strip every OTHER <script> block.
	 *   3. Strip inline event handlers (on*).
	 *   4. Strip the javascript: URI scheme.
	 *   5. Restore the stashed JSON-LD blocks.
	 *
	 * @return  string
	 */
	private function cleanInfos()
	{
		$infos = isset($_POST['infos']) ? (string) $_POST['infos'] : '';

		$stash = array();
		$infos = preg_replace_callback(
			'#<script\b[^>]*\btype\s*=\s*("|\')application/ld\+json\1[^>]*>([\s\S]*?)</script>#i',
			function ($m) use (&$stash)
			{
				$token = '<!--FEC_JSONLD_' . count($stash) . '-->';

				$stash[$token] = '<script type="application/ld+json">'
					. $m[2]
					. '</script>';

				return $token;
			},
			$infos
		);

		$infos = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $infos);
		$infos = preg_replace('#\son\w+\s*=\s*("|\').*?\1#is', '', $infos);
		$infos = preg_replace('#javascript:#i', '', $infos);

		if (!empty($stash))
		{
			$infos = strtr($infos, $stash);
		}

		return $infos;
	}

	/**
	 * Emits a JSON envelope and stops the request.
	 *
	 * @param   boolean  $ok
	 * @param   string   $message
	 * @param   array    $data
	 * @return  void
	 */
	private function jsonOut($ok, $message, $data = array())
	{
		while (ob_get_level())
		{
			ob_end_clean();
		}

		fec_header('Content-Type: application/json; charset=utf-8');

		echo json_encode(
			array_merge(
				array('ok' => (bool) $ok, 'message' => $message),
				$data
			)
		);

		jexit();
	}
}