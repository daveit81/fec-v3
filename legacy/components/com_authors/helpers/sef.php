<?php
/**
 * AuthorsHelperSef — registers edition URLs with sh404SEF (2.7.62).
 *
 * Why this exists. sh404SEF has no plugin for com_authors, so it builds our
 * URLs through the Joomla router and, with or without an Itemid, ignores the
 * menu item: an edition comes out as /page/<slug>/YYYY-MM. It also decodes
 * ONLY URLs present in #__sh404sef_urls, so the anchored form the templates,
 * the bot and the newsletter link to (/online-casinos/<slug>/YYYY-MM) is a
 * 404 until a record for it exists. Every freeze writes that record itself:
 *
 *   oldurl = online-casinos/<slug>/YYYY-MM            (SEF path, no leading slash)
 *   newurl = index.php?option=com_authors&view=page&slug=<slug>&edition=YYYY-MM&Itemid=N
 *
 * N is the published menu item of that page (view=page&slug=<slug>), so the
 * edition renders with the same active menu, modules and template as the live
 * page. Any 404 record sh404SEF logged for the same path, and the stray
 * /page/<slug>/YYYY-MM record, are removed. Without sh404SEF (no table) this
 * is a no-op: the component router already resolves both forms natively.
 *
 * PHP 5.6 safe.
 */

defined('_JEXEC') or die;

class AuthorsHelperSef
{
    /**
     * Register the anchored SEF path of an edition.
     *
     * @param string $sefPath  e.g. /online-casinos/licence-watch/2026-10
     * @param string $slug     page topic slug (licence-watch, fast-payouts)
     * @param string $ym       YYYY-MM
     * @return string status: registered:<itemid|none>, skipped: <why>, failed: <why>
     */
    public static function registerEdition($sefPath, $slug, $ym)
    {
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$slug));
        $ym   = preg_replace('/[^0-9-]/', '', (string)$ym);
        if ($slug === '' || !preg_match('/^\d{4}-\d{2}$/', $ym)) return 'skipped: bad args';

        $sef = self::cleanPath($sefPath);
        if ($sef === '') return 'skipped: empty path';

        $itemid = self::menuItemId($slug);
        $nonSef = 'index.php?option=com_authors&view=page&slug=' . $slug . '&edition=' . $ym
                . ($itemid > 0 ? '&Itemid=' . $itemid : '');

        $r = self::write($sef, $nonSef, array('page/' . $slug . '/' . $ym));
        if ($r !== true) return $r;
        return 'registered:' . ($itemid > 0 ? $itemid : 'none');
    }

    /** Remove the record of an edition path (draft deleted). */
    public static function unregister($sefPath)
    {
        $sef = self::cleanPath($sefPath);
        if ($sef === '') return 'skipped: empty path';
        $t = self::table();
        if ($t === null) return 'skipped: no sh404sef';
        try {
            $db = JFactory::getDbo();
            $db->setQuery("DELETE FROM " . $db->quoteName($t) . " WHERE oldurl = " . $db->quote($sef));
            $db->query();
        } catch (Exception $e) {
            return 'failed: ' . $e->getMessage();
        }
        return 'removed';
    }

    /**
     * Itemid of the published site menu item pointing at view=page&slug=$slug,
     * 0 when none. Uses the loaded site menu when available, the table otherwise
     * (the snapshot task runs on the front end, but keep the install context safe).
     */
    public static function menuItemId($slug)
    {
        try {
            $app = JFactory::getApplication();
            if ($app && method_exists($app, 'getMenu')) {
                $menu  = $app->getMenu('site');
                $items = $menu ? $menu->getItems('component', 'com_authors') : null;
                if (is_array($items)) {
                    foreach ($items as $it) {
                        if (!isset($it->query['view'], $it->query['slug'])) continue;
                        if ((string)$it->query['view'] === 'page' && (string)$it->query['slug'] === $slug) return (int)$it->id;
                    }
                }
            }
        } catch (Exception $e) {}
        try {
            $db = JFactory::getDbo();
            $db->setQuery("SELECT id FROM #__menu WHERE client_id = 0 AND published = 1"
                . " AND link LIKE " . $db->quote('index.php?option=com_authors&view=page%')
                . " AND link LIKE " . $db->quote('%slug=' . $slug . '%')
                . " ORDER BY id ASC LIMIT 1");
            return (int)$db->loadResult();
        } catch (Exception $e) {
            return 0;
        }
    }

    // ---- internals --------------------------------------------------------

    /** oldurl form: no leading slash, no query, no trailing slash. */
    protected static function cleanPath($p)
    {
        $p = (string)$p;
        $q = strpos($p, '?');
        if ($q !== false) $p = substr($p, 0, $q);
        return trim($p, '/');
    }

    /** Full name of the sh404SEF URL table, null when sh404SEF is not installed. */
    protected static function table()
    {
        static $cached = false;
        if ($cached !== false) return $cached;
        $cached = null;
        try {
            $db = JFactory::getDbo();
            $name = $db->getPrefix() . 'sh404sef_urls';
            $db->setQuery("SHOW TABLES LIKE " . $db->quote($name));
            if ((string)$db->loadResult() !== '') $cached = $name;
        } catch (Exception $e) {}
        return $cached;
    }

    /** Delete every record for $sef (and the $alsoDelete paths), insert the new one. */
    protected static function write($sef, $nonSef, $alsoDelete = array())
    {
        $t = self::table();
        if ($t === null) return 'skipped: no sh404sef';
        try {
            $db = JFactory::getDbo();
            $db->setQuery("SHOW COLUMNS FROM " . $db->quoteName($t));
            $cols = $db->loadColumn();
            if (!is_array($cols) || !in_array('oldurl', $cols, true) || !in_array('newurl', $cols, true)) {
                return 'skipped: unexpected sh404sef schema';
            }

            $paths = array_merge(array($sef), (array)$alsoDelete);
            $in = array();
            foreach ($paths as $p) { $p = self::cleanPath($p); if ($p !== '') $in[] = $db->quote($p); }
            $db->setQuery("DELETE FROM " . $db->quoteName($t) . " WHERE oldurl IN (" . implode(', ', $in) . ")");
            $db->query();

            $fields = array('oldurl' => $db->quote($sef), 'newurl' => $db->quote($nonSef));
            if (in_array('cpt', $cols, true))     $fields['cpt'] = 0;
            if (in_array('rank', $cols, true))    $fields['rank'] = 0;
            if (in_array('dateadd', $cols, true)) $fields['dateadd'] = $db->quote(gmdate('Y-m-d'));
            $names = array();
            foreach (array_keys($fields) as $n) $names[] = $db->quoteName($n);
            $db->setQuery("INSERT INTO " . $db->quoteName($t) . " (" . implode(', ', $names) . ") VALUES (" . implode(', ', array_values($fields)) . ")");
            $db->query();
        } catch (Exception $e) {
            return 'failed: ' . $e->getMessage();
        }
        return true;
    }
}
