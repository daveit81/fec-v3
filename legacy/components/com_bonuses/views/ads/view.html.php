<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import Joomla view library
jimport('joomla.application.component.view');

/**
 * HTML View class for the HelloWorld Component
 */
class BonusesViewAds extends JView
{
	// Overwriting JView display method
	function display($tpl = null)
	{
		$doc = JFactory::getDocument();
		$doc->setMetaData('robots', 'noindex,nofollow');
		$jinput = JFactory::getApplication()->input;

		$bid = $jinput->get('bid', '', 'int');
		$cid = $jinput->get('cid', '', 'int');

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName(array('link')));
		$query->from($db->quoteName('#__bonuses'));
		$query->where($db->quoteName('bid') . " = " . $db->quote($bid));
		$db->setQuery($query);
		$link = $db->loadResult();

		// Guard: never emit a blank Location header (would break the redirect).
		$link = trim((string) $link);

		// --- FEC click tracker + subid (v2, Aug 2026 batch) ----------------
		// Ensure the helper is loaded (VERIFY this path matches your install).
		if (!function_exists('fec_log_bonus_click')) {
			$libPath = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';
			if (is_file($libPath)) {
				require_once $libPath;
			}
		}

		// Landing-page token (low-cardinality, see fec_subid_from_src docblock).
		$fecSubid = function_exists('fec_subid_from_src')
			? fec_subid_from_src(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '')
			: '';

		// Anti double-count: if this bonus's link is itself a goto.php URL,
		// the click will be logged by goto.php (slug path) — skip here so we
		// don't record it twice. Only log when we are the terminal redirect.
		if (function_exists('fec_log_bonus_click') && stripos($link, 'goto.php') === false) {
			fec_log_bonus_click($db, $cid, $bid, array(
				'src_page' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
				'subid'    => $fecSubid,
			));

			// Terminal redirect: this is where the URL leaves the site, so
			// append the subid here — no-op unless the target host matches an
			// ENABLED entry in fec_subid_program_map() (URL comes back
			// byte-identical otherwise).
			if (function_exists('fec_subid_append') && $fecSubid !== ''
				&& preg_match('#^https?://#i', $link)) {
				$link = fec_subid_append($link, $fecSubid);
			}
		}
		// -------------------------------------------------------------------

		if ($link === '') {
			$link = JUri::root();
		}

		header("Location: " . $link);
		JFactory::getApplication()->close();
	}
}