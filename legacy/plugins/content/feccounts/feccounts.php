<?php
/**
 * Joomla content plugin: feccounts
 *
 * Resolves {FEC:NAME} placeholders in any article body with live counts
 * from the FreeExtraChips database. Runs on onContentPrepare.
 *
 * Supported placeholders:
 *   {FEC:active_casinos}    Operators with at least one active bonus
 *   {FEC:total_casinos}     Total operators in directory
 *   {FEC:active_bonuses}    Bonuses with status='active' (non-expired)
 *   {FEC:total_bonuses}     All bonuses in DB (excluding pending)
 *   {FEC:fxcheck_reports}   Player Yes/No reports aggregated to date
 *   {FEC:slots_verified}    Published, non-retired slots
 *   {FEC:active_editors}    Active editorial team members
 *
 * Caches results in static $counts for the duration of the request.
 *
 * @version 1.0
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgContentFeccounts extends JPlugin
{
    /**
     * Cached counts for the request lifetime. Avoids hitting DB twice
     * even if the same article renders multiple placeholders.
     */
    protected static $counts = null;

    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        // Cheap check: no placeholder, no work
        if (empty($article->text) || strpos($article->text, '{FEC:') === false) {
            return true;
        }

        $counts = $this->getCounts();

        // Build replacement map
        $replace = array();
        foreach ($counts as $key => $val) {
            $replace['{FEC:' . $key . '}'] = number_format((int)$val);
        }

        $article->text = strtr($article->text, $replace);

        return true;
    }

    /**
     * Single-pass DB read for all counts. Each query is wrapped in
     * try/catch so a missing table (e.g. before com_authors install)
     * doesn't break content rendering — falls back to 0.
     */
    protected function getCounts()
    {
        if (self::$counts !== null) {
            return self::$counts;
        }

        $db = JFactory::getDbo();
        $out = array(
            'active_casinos'   => 0,
            'total_casinos'    => 0,
            'active_bonuses'   => 0,
            'total_bonuses'    => 0,
            'fxcheck_reports'  => 0,
            'slots_verified'   => 0,
            'active_editors'   => 0,
        );

        $queries = array(
            'active_casinos'  => "SELECT COUNT(DISTINCT cid) FROM #__bonuses WHERE status = 'active'",
            'total_casinos'   => "SELECT COUNT(*) FROM #__jreviews_content WHERE jr_closed NOT LIKE '%yes%'",
            'active_bonuses'  => "SELECT COUNT(*) FROM #__bonuses WHERE status = 'active'",
            'total_bonuses'   => "SELECT COUNT(*) FROM #__bonuses WHERE status <> 'pending'",
            'fxcheck_reports' => "SELECT COUNT(*) FROM #__bonus_feedback",
            'slots_verified'  => "SELECT COUNT(*) FROM #__slot_rtp WHERE published = 1 AND retired = 0",
            'active_editors'  => "SELECT COUNT(*) FROM #__casino_authors WHERE is_active = 1",
        );

        foreach ($queries as $key => $sql) {
            try {
                $db->setQuery($sql);
                $val = $db->loadResult();
                if ($db->getErrorNum()) continue;
                $out[$key] = (int)$val;
            } catch (Exception $e) {
                // Silent fallback to 0
            }
        }

        self::$counts = $out;
        return $out;
    }
}
