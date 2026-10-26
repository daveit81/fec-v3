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
 * Add / Edit bonus form view.
 *
 *   index.php?option=com_bonuses&view=add          -> add mode
 *   index.php?option=com_bonuses&view=add&bid=NNN  -> edit mode
 *
 * All the data-gathering that used to sit at the top of the template
 * file now lives here, so the add and edit flows share one code path.
 */
class BonusesViewAdd extends JView
{
	/**
	 * @param   string  $tpl
	 * @return  void
	 */
	public function display($tpl = null)
	{
		// ---- single-user gate --------------------------------------
		// The real write-side gate is in the controller; this just
		// stops the form from rendering for anyone who can't use it.
		$this->authorized = BonusesHelperBonus::authorized();

		if (!$this->authorized)
		{
			JError::raiseWarning(403, JText::_('JERROR_ALERTNOAUTHOR'));

			return;
		}

		$jinput = JFactory::getApplication()->input;
		$bid    = (int) $jinput->get('bid', 0, 'int');

		// ---- option lists ------------------------------------------
		$this->casinos   = BonusesHelperBonus::getCasinoOptions();
		$this->games     = BonusesHelperBonus::getGameOptions();
		$this->banks     = BonusesHelperBonus::getBankOptions();
		$this->authors   = BonusesHelperBonus::getAuthorOptions();
		$this->bonusType = BonusesHelperBonus::optionMap('bonustype');
		$this->countries = BonusesHelperBonus::optionMap('countries');
		$this->currency  = BonusesHelperBonus::optionMap('currency');
		$this->platform  = BonusesHelperBonus::optionMap('platform');
		$this->players   = BonusesHelperBonus::optionMap('players');
		$this->gametype  = BonusesHelperBonus::optionMap('gametype');

		// Default editorial author for new bonuses (David Romano if
		// active, else first active 'main', else any active, else null).
		// Used by the template to pre-select the dropdown in add mode.
		$this->defaultAuthorId = BonusesHelperBonus::getDefaultAuthorId();

		// ---- the item being edited (null in add mode) --------------
		$this->item   = $this->loadItem($bid);
		$this->isEdit = ($this->item !== null);

		// ---- welcome package (FXRate) -------------------------------
		// Server-side prefill of the Welcome package section: the
		// casino's packages (legs + FXRate line), its ladder-type rows
		// and the package this bonus belongs to. The same payload is
		// served by actions.packageoptions when the casino changes.
		// Degrades to an empty payload if the package tables are not
		// installed yet, so the form still renders.
		$this->pkgPayload = array('packages' => array(), 'rows' => array(), 'this_pkg_id' => 0);

		if ($this->isEdit && (int) $this->item->cid > 0)
		{
			try
			{
				$this->pkgPayload = BonusesHelperBonus::packagePayload((int) $this->item->cid, $bid);
			}
			catch (Exception $e)
			{
				error_log('com_bonuses add view packagePayload: ' . $e->getMessage());
			}
		}

		// in edit mode a real bid was requested but not found -> warn
		if ($bid > 0 && !$this->isEdit)
		{
			JError::raiseWarning(404, 'Bonus #' . $bid . ' was not found.');
		}

		// pre-split the starlist columns into arrays for option matching
		$this->selected = $this->buildSelected($this->item);

		// dates converted into the format the datetimepicker expects
		$this->startForm = $this->item ? BonusesHelperBonus::dbToFormDate($this->item->start) : '';
		$this->endForm   = $this->item ? BonusesHelperBonus::dbToFormDate($this->item->end)   : '';

		// ---- versioning: metadata of the latest restorable snapshot
		// In edit mode, looks up the most recent jos_bonuses_history
		// row for this bid with reason='edit' or 'delete'. Returns an
		// object with id / saved_at / reason, or null if no snapshot
		// exists. The template uses it to decide whether to render
		// the "Restore previous version" button and to label the
		// confirm dialog with the snapshot timestamp.
		//
		// Restore snapshots (reason='restore') are deliberately
		// excluded from this lookup - same rule as the controller,
		// so the button always targets an 'edit'/'delete' state and
		// pressing it twice would walk one step further back, not in
		// circles. (The controller still snapshots the pre-restore
		// state with reason='restore' for reversibility, but that
		// row is invisible to the button.)
		$this->lastHistory = $this->isEdit ? $this->loadLastHistory($bid) : null;

		// ---- page chrome -------------------------------------------
		$doc = JFactory::getDocument();
		$doc->setTitle($this->isEdit ? 'Edit bonus #' . $bid : 'Add a bonus');

		// ---- front-end libraries -----------------------------------
		// Loads jQuery + every plugin the form needs, in the correct
		// order. The old site loaded these from a site-wide global
		// script that the rebuilt site no longer has, so the view now
		// declares them itself - the dashboard is self-sufficient and
		// does not depend on the site template or any plugin.
		$this->loadAssets();

		parent::display($tpl);
	}

	/**
	 * Registers every front-end library and stylesheet the add/edit
	 * form needs, in one place and in the correct order.
	 *
	 * Why this lives here and not in the template:
	 * Joomla 2.5 ships MooTools, not jQuery, and formValidation /
	 * icheck / bootstrap-multiselect / the datetimepicker were never
	 * part of the component - the old form relied on a site-wide
	 * global script that the rebuilt site dropped.
	 *
	 * Why ORDER matters (this is the subtle part):
	 * Joomla's addScript() / addStyleSheet() keep insertion order and
	 * emit everything into <head>. addScriptDeclaration() output also
	 * lands in <head>, after the file includes. So by registering the
	 * libraries first and the jQuery.noConflict() call last, every
	 * plugin is guaranteed to run while window.$ is still jQuery.
	 *
	 * This is what fixes bootstrap-multiselect: that file binds to the
	 * global $, and if noConflict() runs before it (the previous
	 * setup, where multiselect.js sat in the template body AFTER the
	 * head-level noConflict()), it silently fails to register and
	 * .multiselect() does nothing. icheck happened to work only
	 * because it was already in <head>; loading the whole chain here
	 * removes the ordering accident entirely.
	 *
	 * Final load order:
	 *   CSS : formValidation, icheck skin, multiselect, datetimepicker
	 *   JS  : jQuery
	 *         -> formValidation core + bootstrap framework bridge
	 *         -> icheck
	 *         -> moment (datetimepicker depends on it)
	 *         -> bootstrap-multiselect
	 *         -> datetimepicker
	 *         -> CKEditor + its jQuery adapter
	 *         -> CKEditor DTD patch (needs window.CKEDITOR to exist)
	 *         -> jQuery.noConflict()  <-- LAST, hands $ back to MooTools
	 *
	 * @return  void
	 */
	private function loadAssets()
	{
		$doc  = JFactory::getDocument();
		$comp = '/components/com_bonuses';

		// ---- stylesheets -------------------------------------------
		// Font Awesome 4.7 - the form uses FA4 icon classes
		// (fa fa-calendar, fa fa-clock-o, fa fa-arrow-up / -down):
		// they go into the input-group addons and, via the
		// datetimepicker's icons:{} option, into its up/down spinner
		// buttons. The old site had FA in the global script the
		// rebuild dropped; without it the picker's arrow buttons
		// render as empty boxes and the addon icons are blank. FA4
		// (not 5/6) is required - the classes use the bare 'fa'
		// prefix and the '-o' suffix (fa-clock-o) which only exist
		// in the 4.x line.
		$doc->addStyleSheet('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
		$doc->addStyleSheet('https://cdnjs.cloudflare.com/ajax/libs/formvalidation/0.6.2-dev/css/formValidation.min.css');
		$doc->addStyleSheet('https://cdnjs.cloudflare.com/ajax/libs/iCheck/1.0.2/skins/square/red.css');
		$doc->addStyleSheet($comp . '/css/multiselect.css');
		$doc->addStyleSheet($comp . '/css/datetimepicker.css');
		$doc->addStyleSheet($comp . '/css/add-style.css');

		// ---- scripts, in dependency order --------------------------

		// 1. jQuery 1.12.4 - last of the 1.x line, the safest match
		//    for this era of plugins.
		$doc->addScript('https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js');

		// 2. formValidation 0.6.2 - core + the Bootstrap framework
		//    bridge (the form calls it with framework: 'bootstrap').
		$doc->addScript('https://cdnjs.cloudflare.com/ajax/libs/formvalidation/0.6.2-dev/js/formValidation.min.js');
		$doc->addScript('https://cdnjs.cloudflare.com/ajax/libs/formvalidation/0.6.2-dev/js/framework/bootstrap.min.js');

		// 3. icheck 1.0.2 (skin stylesheet already queued above).
		$doc->addScript('https://cdnjs.cloudflare.com/ajax/libs/iCheck/1.0.2/icheck.min.js');

		// 4. moment - must load BEFORE the datetimepicker, which
		//    depends on it.
		$doc->addScript($comp . '/js/moment-w-locales.js');

		// 5. bootstrap-multiselect. This is the file that was failing:
		//    it binds to the global $, so it must run while $ is still
		//    jQuery - i.e. before the noConflict() call below.
		$doc->addScript($comp . '/js/multiselect.js');

		// 6. eonasdan datetimepicker.
		$doc->addScript($comp . '/js/datetimepicker.js');

		// 7. CKEditor 4.10.1 + its jQuery adapter (the form uses
		//    $(textarea).ckeditor()).
		$doc->addScript('https://cdn.ckeditor.com/4.10.1/standard-all/ckeditor.js');
		$doc->addScript('https://cdn.ckeditor.com/4.10.1/standard-all/adapters/jquery.js');

		// 8. CKEditor DTD patch - keeps <details>/<summary> from being
		//    stripped. Must run after ckeditor.js has defined
		//    window.CKEDITOR, which the ordering above guarantees.
		$doc->addScriptDeclaration(
			"if (window.CKEDITOR && CKEDITOR.dtd) {\n"
			. "\tCKEDITOR.dtd.\$block.details = 1;\n"
			. "\tCKEDITOR.dtd.\$block.summary = 1;\n"
			. "\tCKEDITOR.dtd.\$removeEmpty.details = 0;\n"
			. "\tCKEDITOR.dtd.\$removeEmpty.summary = 0;\n"
			. "}"
		);

		// 9. LAST: release '$' back to MooTools. Joomla 2.5 loads
		//    MooTools on most pages; leaving the CDN jQuery on '$'
		//    would break MooTools-based core behaviour. Every plugin
		//    above has already registered against window.jQuery by
		//    now, and the form's own inline script is noConflict-safe
		//    (jQuery(document).ready(function ($) { ... })).
		$doc->addScriptDeclaration('jQuery.noConflict();');
	}

	/**
	 * Loads a single bonus row by id, or null when none / not found.
	 *
	 * @param   integer  $bid
	 * @return  object|null
	 */
	private function loadItem($bid)
	{
		if ($bid <= 0)
		{
			return null;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('bid') . ' = ' . (int) $bid);

		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Pre-splits the starlist columns of the loaded item into flat
	 * arrays, so the template can mark <option> tags as selected with
	 * a simple in_array() check. In add mode every list is empty.
	 *
	 * @param   object|null  $item
	 * @return  array
	 */
	private function buildSelected($item)
	{
		$fields = array('gamesok', 'gamesno', 'bankok', 'bankno', 'country_ok', 'country_no');
		$out    = array();

		foreach ($fields as $f)
		{
			$out[$f] = $item
				? BonusesHelperBonus::splitStarlist($item->$f)
				: array();
		}

		return $out;
	}

	/**
	 * Looks up the metadata of the most recent restorable history
	 * snapshot for a bid - i.e. the row the "Restore previous
	 * version" button would target.
	 *
	 * Only 'edit' and 'delete' snapshots are restorable: 'restore'
	 * snapshots are taken BY the restore task itself (capturing the
	 * state it is about to overwrite) and must never be the target
	 * of a subsequent restore, otherwise pressing the button twice
	 * would walk in circles instead of stepping further back. The
	 * controller enforces the same rule on the write side.
	 *
	 * Returns null when no restorable snapshot exists yet, or when
	 * the lookup fails (the history table might not be installed on
	 * an in-progress migration; in that case the button just doesn't
	 * render and everything else keeps working).
	 *
	 * Only the metadata is pulled here - the snapshot JSON itself is
	 * read by the restore controller task at the moment of restore.
	 *
	 * @param   integer  $bid
	 * @return  object|null  with ->id, ->saved_at, ->reason
	 */
	private function loadLastHistory($bid)
	{
		if ((int) $bid <= 0)
		{
			return null;
		}

		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName(array('id', 'saved_at', 'reason')))
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

			$row = $db->loadObject();

			return $row ? $row : null;
		}
		catch (Exception $e)
		{
			// Table missing or any other DB error: degrade gracefully
			// by behaving like "no history". The form still renders.
			return null;
		}
	}
}