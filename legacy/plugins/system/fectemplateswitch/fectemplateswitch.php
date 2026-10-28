<?php
/**
 * System - FEC Template Switch
 *
 * Forces selected components (default: com_easyblog) to render with a
 * legacy template (default: rt_lumiere) while the section is not yet
 * ported to the new design.
 *
 * Joomla 2.5 / PHP 5.3 compatible.
 *
 * HOW IT WORKS
 * onAfterRoute fires after the router (incl. sh404SEF) has resolved
 * option/view/Itemid, but BEFORE JSite::render() determines the template.
 * Preferred mechanism: set the 'templateStyle' request var to the style id
 * of the legacy template — JSite::getTemplate() honours it and loads the
 * style WITH its params (critical for Gantry templates like rt_lumiere,
 * which need their style params to boot correctly).
 * Fallback: $app->setTemplate() (loads the template with EMPTY params —
 * fine for plain templates, degraded for Gantry; used only if no style
 * row is found in #__template_styles).
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSystemFectemplateswitch extends JPlugin
{
	public function onAfterRoute()
	{
		$app = JFactory::getApplication();

		// Frontend only — never touch the admin.
		if ($app->isAdmin()) {
			return;
		}

		$option = strtolower(JRequest::getCmd('option'));
		if ($option === '') {
			return;
		}

		// Components to force onto the legacy template (comma separated).
		$raw        = (string) $this->params->get('components', 'com_easyblog');
		$components = array();
		foreach (explode(',', $raw) as $c) {
			$c = strtolower(trim($c));
			if ($c !== '') {
				$components[] = $c;
			}
		}

		if (!in_array($option, $components, true)) {
			return;
		}

		$template = trim((string) $this->params->get('template', 'rt_lumiere'));
		if ($template === '' || !is_dir(JPATH_THEMES . '/' . $template)) {
			// Template dir missing — do nothing rather than white-screen.
			return;
		}

		// Preferred path: template STYLE id (params included — Gantry-safe).
		$styleId = (int) $this->params->get('style_id', 0);
		if ($styleId <= 0) {
			$styleId = $this->findStyleId($template);
		}

		if ($styleId > 0) {
			JRequest::setVar('templateStyle', $styleId);
			return;
		}

		// Fallback: hard switch with empty params.
		if (method_exists($app, 'setTemplate')) {
			$app->setTemplate($template);
		}
	}

	/**
	 * Find the default (home-first) frontend style id for a template.
	 *
	 * @param  string $template Template directory name.
	 * @return int    Style id, 0 if not found.
	 */
	protected function findStyleId($template)
	{
		static $cache = array();

		if (isset($cache[$template])) {
			return $cache[$template];
		}

		$id = 0;

		try {
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__template_styles'))
				->where('client_id = 0')
				->where('template = ' . $db->quote($template))
				->order('home DESC, id ASC');
			$db->setQuery($query, 0, 1);
			$id = (int) $db->loadResult();
		} catch (Exception $e) {
			$id = 0;
		}

		$cache[$template] = $id;

		return $id;
	}
}
