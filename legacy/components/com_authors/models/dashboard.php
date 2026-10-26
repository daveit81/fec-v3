<?php
/**
 * AuthorsModelDashboard
 *
 * Data layer for the RTP database editorial dashboard.
 *
 * Auth: hard-gated to a whitelist of Joomla user IDs (DASHBOARD_ALLOWED_USERS).
 * V2.4 ships with just user 42 (Davide). To add an editor, append the new ID
 * to the constant — no other change needed.
 *
 * Tables:
 *   #__slot_rtp                — one row per game (theoretical RTP/volatility/source)
 *   #__slot_rtp_observations   — per-casino observed RTP (paytable screenshots etc)
 *
 * Bonus-count ranking: getSlotsList() LEFT JOINs #__bonuses (status='active') and
 * groups by gid, so the slots that appear in the most active bonuses surface first.
 * That's the priority queue for documentation work.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class AuthorsModelDashboard extends JModel
{
    /** User IDs allowed to access the dashboard. Keep this list short. */
    const DASHBOARD_ALLOWED_USERS = '42';

    /** Slot list cap — show this many on the priority queue. */
    const SLOT_LIST_LIMIT = 50;

    /** Considered "needs refresh" after this many days since last_verified_date. */
    const REFRESH_AFTER_DAYS = 90;

    /**
     * Check whether the current logged-in Joomla user is on the whitelist.
     * Used by view.html.php and the controller before any DB write.
     */
    public static function isAuthorized()
    {
        $user = JFactory::getUser();
        if (!$user || $user->guest) {
            return false;
        }
        $allowed = array_map('intval', explode(',', self::DASHBOARD_ALLOWED_USERS));
        return in_array((int)$user->id, $allowed, true);
    }

    /**
     * Top slots by active-bonus references, with RTP coverage status.
     *
     * Returns rows with: gid, game_name, provider_name, active_bonus_count,
     * has_rtp (0/1), rtp_theoretical_max, last_verified_date, obs_count,
     * status_code ('verified' | 'needs_refresh' | 'partial' | 'missing').
     *
     * The status_code is computed PHP-side so we can keep the SQL portable;
     * it drives the icon/badge in the UI.
     */
    public function getSlotsList($search = '', $filter = 'all', $limit = null, $start = 0)
    {
        if ($limit === null) {
            $limit = self::SLOT_LIST_LIMIT;
        }
        $limit = (int)$limit;
        $start = max(0, (int)$start);
        $db = JFactory::getDbo();

        $where = array("g.status = 1", "g.type = 'slot'");
        if ($search !== '') {
            $search = $db->getEscaped($search);
            $where[] = "(g.game_name LIKE '%" . $search . "%' OR g.provider_name LIKE '%" . $search . "%')";
        }
        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT
                g.gid,
                g.game_name,
                g.provider_name,
                g.provider_value,
                COUNT(DISTINCT b.bid) AS active_bonus_count,
                (rtp.id IS NOT NULL) AS has_rtp,
                rtp.slug,
                rtp.rtp_theoretical_max,
                rtp.rtp_theoretical_min,
                rtp.last_verified_date,
                COALESCE(rtp.published, 0) AS published,
                COALESCE(rtp.retired, 0) AS retired,
                (
                    SELECT COUNT(*) FROM #__slot_rtp_observations o
                    WHERE o.gid = g.gid
                ) AS obs_count
            FROM #__games g
            LEFT JOIN #__bonuses b ON b.gid = g.gid AND b.status = 'active'
            LEFT JOIN #__slot_rtp rtp ON rtp.gid = g.gid
            WHERE " . $whereSql . "
            GROUP BY g.gid
            ORDER BY active_bonus_count DESC, g.game_name ASC
            LIMIT " . $start . ", " . $limit;

        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        if (!is_array($rows)) {
            return array();
        }

        // Compute status_code per row + apply filter.
        $today = strtotime('today');
        $stale = self::REFRESH_AFTER_DAYS * 86400;
        $out = array();
        foreach ($rows as $r) {
            if (!$r->has_rtp || !$r->rtp_theoretical_max) {
                $r->status_code = 'missing';
            } else {
                $verified_ts = $r->last_verified_date ? strtotime($r->last_verified_date) : 0;
                if (!$verified_ts || ($today - $verified_ts) > $stale) {
                    $r->status_code = 'needs_refresh';
                } elseif ((int)$r->obs_count === 0) {
                    $r->status_code = 'partial';
                } else {
                    $r->status_code = 'verified';
                }
            }
            // filter
            if ($filter === 'missing' && $r->status_code !== 'missing') continue;
            if ($filter === 'needs_refresh' && $r->status_code !== 'needs_refresh') continue;
            if ($filter === 'verified' && $r->status_code !== 'verified') continue;
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Total count of slot records matching the search filter (NOT the status
     * filter, which is computed PHP-side). Used for pagination so the user
     * sees the full pageset, not just the filtered subset.
     */
    public function getSlotsCount($search = '')
    {
        $db = JFactory::getDbo();
        $where = array("g.status = 1", "g.type = 'slot'");
        if ($search !== '') {
            $search = $db->getEscaped($search);
            $where[] = "(g.game_name LIKE '%" . $search . "%' OR g.provider_name LIKE '%" . $search . "%')";
        }
        $whereSql = implode(' AND ', $where);
        $sql = "SELECT COUNT(DISTINCT g.gid)
                FROM #__games g
                WHERE " . $whereSql;
        $db->setQuery($sql);
        return (int)$db->loadResult();
    }

    /**
     * Coverage stats for the dashboard header.
     */
    public function getCoverageStats($top_n = 30)
    {
        $top_n = (int)$top_n;
        $db = JFactory::getDbo();
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN has_rtp = 1 THEN 1 ELSE 0 END) AS documented
            FROM (
                SELECT
                    g.gid,
                    (rtp.id IS NOT NULL) AS has_rtp,
                    COUNT(DISTINCT b.bid) AS bcount
                FROM #__games g
                LEFT JOIN #__bonuses b ON b.gid = g.gid AND b.status = 'active'
                LEFT JOIN #__slot_rtp rtp ON rtp.gid = g.gid
                WHERE g.status = 1 AND g.type = 'slot'
                GROUP BY g.gid
                ORDER BY bcount DESC
                LIMIT " . $top_n . "
            ) t
        ";
        $db->setQuery($sql);
        $row = $db->loadObject();
        return array(
            'total'      => $row ? (int)$row->total : 0,
            'documented' => $row ? (int)$row->documented : 0,
        );
    }

    /**
     * Fetch a single slot — game metadata + RTP row (may be null) + observations count.
     */
    public function getSlot($gid)
    {
        $gid = (int)$gid;
        if (!$gid) return null;
        $db = JFactory::getDbo();
        $sql = "
            SELECT
                g.gid, g.game_name, g.provider_name, g.provider_value,
                rtp.id AS rtp_id,
                rtp.slug,
                rtp.rtp_theoretical_max, rtp.rtp_theoretical_min,
                rtp.rtp_variants_json, rtp.max_win_x, rtp.volatility,
                rtp.paylines, rtp.reels, rtp.release_year,
                rtp.primary_source_url, rtp.primary_source_archived_url,
                rtp.certificate_url, rtp.last_verified_date,
                rtp.verified_by_author_id, rtp.notes,
                rtp.published, rtp.published_at,
                rtp.retired, rtp.retired_at, rtp.retired_reason,
                rtp.created_at, rtp.updated_at,
                COUNT(DISTINCT b.bid) AS active_bonus_count
            FROM #__games g
            LEFT JOIN #__slot_rtp rtp ON rtp.gid = g.gid
            LEFT JOIN #__bonuses b ON b.gid = g.gid AND b.status = 'active'
            WHERE g.gid = " . $gid . "
            GROUP BY g.gid
            LIMIT 1
        ";
        $db->setQuery($sql);
        return $db->loadObject();
    }

    /**
     * Observations for a given slot (joined with casino name from jreviews_content
     * via Joomla #__content title — jreviews stores the casino name there).
     */
    public function getObservations($gid)
    {
        $gid = (int)$gid;
        if (!$gid) return array();
        $db = JFactory::getDbo();
        // The casino "name" in this CMS lives in #__content.title, joined to
        // #__jreviews_content.contentid (which == #__content.id for casino entries).
        $sql = "
            SELECT
                o.id, o.gid, o.cid, o.rtp_observed, o.observed_date,
                o.evidence_type, o.screenshot_path,
                o.verified_by_author_id, o.notes, o.created_at,
                c.title AS casino_name
            FROM #__slot_rtp_observations o
            LEFT JOIN #__content c ON c.id = o.cid
            WHERE o.gid = " . $gid . "
            ORDER BY o.observed_date DESC, o.id DESC
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Casino selector — active casino entries from jreviews_content.
     * Used by the observation form casino picker.
     */
    public function getCasinos()
    {
        $db = JFactory::getDbo();
        // Casinos in jreviews_content: jr_casinotype is set (vs slot entries
        // which have jr_reels/jr_paylines set instead).
        // Closed casinos: jr_closed = 'yes' — exclude.
        $sql = "
            SELECT c.id AS cid, c.title AS name
            FROM #__content c
            INNER JOIN #__jreviews_content jr ON jr.contentid = c.id
            WHERE c.state = 1
              AND jr.jr_casinotype != ''
              AND (jr.jr_closed != 'yes' OR jr.jr_closed = '')
            ORDER BY c.title ASC
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Active editorial team — feeds the "Verified by" select in the slot RTP
     * form. Returns id + name only (no need for full author object here).
     */
    public function getActiveAuthors()
    {
        $db = JFactory::getDbo();
        $sql = "
            SELECT id, name
            FROM #__casino_authors
            WHERE is_active = 1
            ORDER BY name ASC
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Search #__games for slot-typed games matching a query that don't
     * yet have a #__slot_rtp record. Feeds the "+ Add slot" modal
     * autocomplete. Sorted by active bonus count (desc) so the highest-
     * impact games surface first.
     *
     * The type filter accepts both 'slot' and 'video-slot' since the
     * existing catalogue uses both labels.
     */
    public function searchGamesNotDocumented($q)
    {
        $q = trim((string)$q);
        if ($q === '' || strlen($q) < 2) return array();
        $db = JFactory::getDbo();
        // Build LIKE pattern, escaping % and _ in the user input
        $like = $db->quote('%' . addcslashes($q, '%_\\') . '%');
        $sql = "
            SELECT
                g.gid, g.game_name, g.provider_name,
                (SELECT COUNT(*) FROM #__bonuses b
                 WHERE b.gid = g.gid AND b.status = 'active') AS bonus_count
            FROM #__games g
            WHERE g.game_name LIKE " . $like . "
              AND (g.type LIKE '%slot%' OR g.type LIKE '%video%')
              AND g.gid NOT IN (SELECT gid FROM #__slot_rtp)
            ORDER BY bonus_count DESC, g.game_name ASC
            LIMIT 20
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        if ($db->getErrorNum()) {
            error_log('searchGamesNotDocumented: ' . $db->getErrorMsg());
            return array();
        }
        return is_array($rows) ? $rows : array();
    }

    /**
     * Create a stub row in #__slot_rtp for a given gid. Slug auto-derived
     * from #__games.game_name via the existing slugify helper. Idempotent
     * via INSERT IGNORE.
     *
     * Returns array(ok=>true, gid=>N) on success, array(ok=>false,error=>S) on fail.
     */
    public function createSlotStub($gid)
    {
        $gid = (int)$gid;
        if (!$gid) return array('ok' => false, 'error' => 'gid required');

        $db = JFactory::getDbo();

        // Verify game exists
        $db->setQuery("SELECT game_name FROM #__games WHERE gid = " . $gid . " LIMIT 1");
        $name = (string)$db->loadResult();
        if ($name === '') {
            return array('ok' => false, 'error' => 'game not found');
        }

        $slug = self::slugify($name);
        if ($slug === null || $slug === '') {
            return array('ok' => false, 'error' => 'could not derive slug');
        }

        // INSERT IGNORE so re-attempts are no-op
        $sql = "INSERT IGNORE INTO #__slot_rtp (gid, slug, created_at, updated_at, published) VALUES ("
             . $gid . ", "
             . $db->quote($slug) . ", "
             . "NOW(), NOW(), 0"
             . ")";
        $db->setQuery($sql);
        $db->execute();
        if ($db->getErrorNum()) {
            error_log('createSlotStub: ' . $db->getErrorMsg());
            return array('ok' => false, 'error' => $db->getErrorMsg());
        }

        return array('ok' => true, 'gid' => $gid, 'slug' => $slug, 'name' => $name);
    }

    /**
     * v2.7.10 — List the providers offered by the dashboard "create new game"
     * form, so editors pick from a canonical set instead of typing free-form
     * names (which has historically produced duplicates like
     * "BetSoft"/"BetSfot", "Saucify"/"Saucify Gaming", etc).
     *
     * v2.7.88 — The list is the union of the site's provider vocabulary (the
     * options of the jReviews software field, jr_soft: the same set the Blog
     * Desk offers) and the providers already present in #__games. Before, only
     * #__games was read, so a provider with no active game yet was missing
     * from the select. Merged on the normalised name: when both sides know a
     * provider, the provider_value of the existing games wins (the group with
     * most games when the same name carries several values), so a new game
     * keeps grouping with the old ones; a provider known only to jr_soft takes
     * the jr_soft option value, which createGameAndStub wraps in asterisks.
     * Sorted by name.
     *
     * Returns objects: provider_value, provider_name, game_count.
     */
    public function getProviders()
    {
        $db  = JFactory::getDbo();
        $out = array(); // normalised name => object

        // 1. Site vocabulary: jReviews software field options (game_count 0).
        $field = defined('FEC_BLOG_SOFT_FIELD') ? FEC_BLOG_SOFT_FIELD : 'jr_soft';
        $db->setQuery('SELECT fo.value, fo.text FROM ' . $db->quoteName('#__jreviews_fieldoptions') . ' fo'
            . ' JOIN ' . $db->quoteName('#__jreviews_fields') . ' f ON f.fieldid = fo.fieldid'
            . ' WHERE f.name = ' . $db->quote($field));
        $rows = $db->loadObjectList();
        if ($db->getErrorNum()) {
            error_log('getProviders (jr_soft): ' . $db->getErrorMsg());
            $rows = array();
        }
        foreach ((array) $rows as $r) {
            $key = self::providerKey($r->text);
            if ($key === '' || isset($out[$key])) continue;
            $o = new stdClass;
            $o->provider_value = trim((string) $r->value);
            $o->provider_name  = trim((string) $r->text);
            $o->game_count     = 0;
            if ($o->provider_value === '' || $o->provider_name === '') continue;
            $out[$key] = $o;
        }

        // 2. Providers already used by #__games (largest group first, so it
        //    sets the value when one name carries several values).
        $sql = "SELECT provider_value, provider_name, COUNT(*) AS game_count
                FROM #__games
                WHERE status = 1
                  AND provider_value <> ''
                  AND provider_name <> ''
                GROUP BY provider_value, provider_name
                ORDER BY game_count DESC, provider_name ASC";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        if ($db->getErrorNum()) {
            error_log('getProviders (games): ' . $db->getErrorMsg());
            $rows = array();
        }
        foreach ((array) $rows as $r) {
            $key = self::providerKey($r->provider_name);
            if ($key === '') continue;
            if (isset($out[$key])) {
                if ($out[$key]->game_count === 0) {
                    $out[$key]->provider_value = (string) $r->provider_value;
                }
                $out[$key]->game_count += (int) $r->game_count;
            } else {
                $o = new stdClass;
                $o->provider_value = (string) $r->provider_value;
                $o->provider_name  = (string) $r->provider_name;
                $o->game_count     = (int) $r->game_count;
                $out[$key] = $o;
            }
        }

        // 3. By name, natural order, case-insensitive.
        $out = array_values($out);
        usort($out, array(__CLASS__, 'compareProviderName'));
        return $out;
    }

    /**
     * v2.7.88 — Merge key for provider names: lowercase, entities decoded,
     * "&" and "+" spelled out, every other non-alphanumeric dropped
     * ("Play'n GO" -> "playngo", "BetSoft" / "Betsoft" -> "betsoft").
     */
    protected static function providerKey($name)
    {
        $s = html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8');
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = str_replace(array('&', '+'), array('and', 'plus'), $s);
        return (string) preg_replace('/[^a-z0-9]+/u', '', $s);
    }

    /** v2.7.88 — usort callback for getProviders(). */
    public static function compareProviderName($a, $b)
    {
        return strnatcasecmp($a->provider_name, $b->provider_name);
    }

    /**
     * v2.7.10 — Create a new row in #__games (if not already present) and
     * then create a slot_rtp stub for it. Idempotent on (game_name,
     * provider_value): if a matching game already exists, reuses its gid.
     *
     * #__games has no AUTO_INCREMENT on gid in legacy installs — we compute
     * MAX(gid)+1. Race condition window is tiny and the (game_name,provider)
     * dedup check upstream covers most user-error cases.
     *
     * Expected $data keys:
     *   game_name      (required)
     *   provider_value (required) — will be normalised to *slug* form
     *   provider_name  (required)
     *   type           (optional, defaults to 'slot')
     *
     * Returns array(ok, gid, slug, name) on success, array(ok=>false, error) on fail.
     */
    public function createGameAndStub($data)
    {
        $name          = isset($data['game_name'])      ? trim((string)$data['game_name'])      : '';
        $type          = isset($data['type'])           ? trim((string)$data['type'])           : 'slot';
        $providerValue = isset($data['provider_value']) ? trim((string)$data['provider_value']) : '';
        $providerName  = isset($data['provider_name'])  ? trim((string)$data['provider_name'])  : '';

        if ($name === '') {
            return array('ok' => false, 'error' => 'game_name required');
        }
        if ($providerValue === '' || $providerName === '') {
            return array('ok' => false, 'error' => 'provider required');
        }
        if ($type === '') {
            $type = 'slot';
        }

        // Normalise provider_value to *slug* form: lowercase, hyphenated,
        // wrapped in asterisks. Idempotent: applying twice produces same out.
        $providerValue = trim($providerValue, '*');
        $providerValue = strtolower($providerValue);
        $providerValue = preg_replace('/[^a-z0-9]+/', '-', $providerValue);
        $providerValue = trim($providerValue, '-');
        if ($providerValue === '') {
            return array('ok' => false, 'error' => 'provider_value resolved to empty after normalisation');
        }
        $providerValue = '*' . $providerValue . '*';

        $db = JFactory::getDbo();

        // Dedup: same name + same normalised provider -> reuse gid, just stub
        $db->setQuery(
            "SELECT gid FROM #__games WHERE game_name = " . $db->quote($name)
            . " AND provider_value = " . $db->quote($providerValue) . " LIMIT 1"
        );
        $existingGid = (int)$db->loadResult();
        if ($existingGid > 0) {
            return $this->createSlotStub($existingGid);
        }

        // Compute next gid (no AUTO_INCREMENT in legacy schema)
        $db->setQuery("SELECT COALESCE(MAX(gid), 0) + 1 FROM #__games");
        $nextGid = (int)$db->loadResult();
        if ($nextGid < 1) {
            return array('ok' => false, 'error' => 'could not compute next gid');
        }

        // Insert new game
        $sql = "INSERT INTO #__games (gid, game_name, type, provider_value, provider_name, status) VALUES ("
             . $nextGid . ", "
             . $db->quote($name) . ", "
             . $db->quote($type) . ", "
             . $db->quote($providerValue) . ", "
             . $db->quote($providerName) . ", "
             . "1)";
        $db->setQuery($sql);
        $db->execute();
        if ($db->getErrorNum()) {
            error_log('createGameAndStub games insert: ' . $db->getErrorMsg());
            return array('ok' => false, 'error' => $db->getErrorMsg());
        }

        // Then create the slot_rtp stub
        return $this->createSlotStub($nextGid);
    }

    /**
     * Upsert RTP row for a given gid.
     *
     * $data keys (all optional except gid):
     *   gid (required), rtp_theoretical_max, rtp_theoretical_min,
     *   rtp_variants_json, max_win_x, volatility, paylines, reels,
     *   release_year, primary_source_url, primary_source_archived_url,
     *   certificate_url, last_verified_date, verified_by_author_id, notes
     *
     * Returns the row ID on success, false on failure.
     */
    public function saveSlotRtp($data)
    {
        $gid = isset($data['gid']) ? (int)$data['gid'] : 0;
        if (!$gid) {
            return false;
        }
        $db = JFactory::getDbo();

        // Numeric coercion + null handling
        $cols = array(
            'gid'                          => $gid,
            'slug'                         => self::resolveSlug($data, $gid),
            'rtp_theoretical_max'          => self::toDecOrNull($data, 'rtp_theoretical_max'),
            'rtp_theoretical_min'          => self::toDecOrNull($data, 'rtp_theoretical_min'),
            'rtp_variants_json'            => self::toStrOrNull($data, 'rtp_variants_json'),
            'max_win_x'                    => self::toIntOrNull($data, 'max_win_x'),
            'volatility'                   => self::toStrOrNull($data, 'volatility'),
            'paylines'                     => self::toStrOrNull($data, 'paylines'),
            'reels'                        => self::toIntOrNull($data, 'reels'),
            'release_year'                 => self::toIntOrNull($data, 'release_year'),
            'primary_source_url'           => self::toStrOrNull($data, 'primary_source_url'),
            'primary_source_archived_url'  => self::toStrOrNull($data, 'primary_source_archived_url'),
            'certificate_url'              => self::toStrOrNull($data, 'certificate_url'),
            'last_verified_date'           => self::toStrOrNull($data, 'last_verified_date'),
            'verified_by_author_id'        => self::toIntOrNull($data, 'verified_by_author_id'),
            'notes'                        => self::toStrOrNull($data, 'notes'),
            'published'                    => isset($data['published']) && (int)$data['published'] === 1 ? 1 : 0,
            'retired'                      => isset($data['retired']) && (int)$data['retired'] === 1 ? 1 : 0,
            'retired_reason'               => self::toStrOrNull($data, 'retired_reason'),
        );
        // Set published_at on first publish only (don't overwrite if already set)
        if ($cols['published'] === 1) {
            $db->setQuery("SELECT published_at FROM #__slot_rtp WHERE gid = " . $gid . " LIMIT 1");
            $existing = $db->loadResult();
            if (!$existing || $existing === '0000-00-00 00:00:00') {
                $cols['published_at'] = date('Y-m-d H:i:s');
            }
        }
        // Set retired_at on first retire only (don't overwrite if already set)
        if ($cols['retired'] === 1) {
            $db->setQuery("SELECT retired_at FROM #__slot_rtp WHERE gid = " . $gid . " LIMIT 1");
            $existingR = $db->loadResult();
            if (!$existingR || $existingR === '0000-00-00 00:00:00') {
                $cols['retired_at'] = date('Y-m-d H:i:s');
            }
        }

        // Build column lists for INSERT ... ON DUPLICATE KEY UPDATE
        $colNames = array();
        $values = array();
        $updates = array();
        foreach ($cols as $name => $val) {
            $colNames[] = "`" . $name . "`";
            if ($val === null) {
                $values[] = "NULL";
                if ($name !== 'gid') {
                    $updates[] = "`" . $name . "` = NULL";
                }
            } else {
                if (is_numeric($val) && !is_string($val)) {
                    $values[] = (string)$val;
                    if ($name !== 'gid') {
                        $updates[] = "`" . $name . "` = " . $val;
                    }
                } else {
                    $escaped = $db->getEscaped($val);
                    $values[] = "'" . $escaped . "'";
                    if ($name !== 'gid') {
                        $updates[] = "`" . $name . "` = '" . $escaped . "'";
                    }
                }
            }
        }

        $sql = "INSERT INTO #__slot_rtp (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        $db->setQuery($sql);
        $ok = $db->query();
        if (!$ok) {
            error_log('[com_authors][dashboard] saveSlotRtp failed: ' . $db->getErrorMsg());
            return false;
        }
        // Return the id (existing or new)
        $db->setQuery("SELECT id FROM #__slot_rtp WHERE gid = " . $gid . " LIMIT 1");
        return (int)$db->loadResult();
    }

    /**
     * Insert a new observation row.
     */
    public function saveObservation($data)
    {
        $gid = isset($data['gid']) ? (int)$data['gid'] : 0;
        $cid = isset($data['cid']) ? (int)$data['cid'] : 0;
        if (!$gid || !$cid) {
            return false;
        }
        $db = JFactory::getDbo();

        $cols = array(
            'gid'                   => $gid,
            'cid'                   => $cid,
            'rtp_observed'          => self::toDecOrNull($data, 'rtp_observed'),
            'observed_date'         => self::toStrOrNull($data, 'observed_date'),
            'evidence_type'         => self::toStrOrNull($data, 'evidence_type'),
            'screenshot_path'       => self::toStrOrNull($data, 'screenshot_path'),
            'verified_by_author_id' => self::toIntOrNull($data, 'verified_by_author_id'),
            'notes'                 => self::toStrOrNull($data, 'notes'),
        );

        $colNames = array();
        $values   = array();
        foreach ($cols as $name => $val) {
            $colNames[] = "`" . $name . "`";
            if ($val === null) {
                $values[] = "NULL";
            } elseif (is_numeric($val) && !is_string($val)) {
                $values[] = (string)$val;
            } else {
                $values[] = "'" . $db->getEscaped($val) . "'";
            }
        }

        $sql = "INSERT INTO #__slot_rtp_observations (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $values) . ")";
        $db->setQuery($sql);
        if (!$db->query()) {
            error_log('[com_authors][dashboard] saveObservation failed: ' . $db->getErrorMsg());
            return false;
        }
        return (int)$db->insertid();
    }

    /**
     * Delete one observation (hard delete — observations are easy to re-add).
     */
    public function deleteObservation($id)
    {
        $id = (int)$id;
        if (!$id) return false;
        $db = JFactory::getDbo();
        // Fetch screenshot_path first so caller can unlink if desired.
        $db->setQuery("SELECT screenshot_path FROM #__slot_rtp_observations WHERE id = " . $id);
        $path = $db->loadResult();
        $db->setQuery("DELETE FROM #__slot_rtp_observations WHERE id = " . $id);
        $ok = $db->query();
        return $ok ? array('ok' => true, 'screenshot_path' => $path) : false;
    }

    /**
     * Archive a URL on Wayback Machine via Save Page Now (SPN1 endpoint).
     *
     * Strategy:
     *   1. Fire GET https://web.archive.org/save/<url> (Wayback queues + archives).
     *   2. Poll https://archive.org/wayback/available?url=<url> for a fresh snapshot.
     *      Try up to 5 times, 3s apart (Wayback usually archives within seconds).
     *   3. Return the snapshot URL, or null on timeout.
     *
     * Best-effort — Wayback can fail (rate limits, robots.txt, server errors).
     * The caller surfaces the snapshot URL into the form field; user can override.
     */
    public function archiveWayback($url)
    {
        $url = trim($url);
        if (!$url || !preg_match('~^https?://~i', $url)) {
            return array('ok' => false, 'error' => 'invalid_url');
        }

        // 1. Fire the save request (fire-and-forget — short timeout, no body parse).
        $saveUrl = 'https://web.archive.org/save/' . $url;
        $ch = curl_init($saveUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'FreeExtraChips/2.4 (+https://www.freeextrachips.com)',
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        curl_exec($ch);
        $saveHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 2. Poll availability API for a fresh snapshot.
        $availUrl = 'https://archive.org/wayback/available?url=' . urlencode($url);
        $maxAttempts = 5;
        $snapshot = null;
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Wait between attempts (skip on first iteration).
            if ($i > 0) sleep(3);
            $ch = curl_init($availUrl);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_USERAGENT      => 'FreeExtraChips/2.4',
                CURLOPT_SSL_VERIFYPEER => true,
            ));
            $body = curl_exec($ch);
            curl_close($ch);
            if (!$body) continue;
            $json = json_decode($body, true);
            if (!is_array($json)) continue;
            $closest = isset($json['archived_snapshots']['closest']) ? $json['archived_snapshots']['closest'] : null;
            if ($closest && !empty($closest['url']) && !empty($closest['available'])) {
                // Sanity: timestamp should be within the last day to count as "fresh".
                $ts = isset($closest['timestamp']) ? $closest['timestamp'] : '';
                if (strlen($ts) >= 8) {
                    $snapDate = strtotime(substr($ts, 0, 4) . '-' . substr($ts, 4, 2) . '-' . substr($ts, 6, 2));
                    if ($snapDate && (time() - $snapDate) < 86400) {
                        $snapshot = $closest['url'];
                        // Force https
                        $snapshot = preg_replace('~^http://~i', 'https://', $snapshot);
                        break;
                    }
                }
            }
        }

        if ($snapshot) {
            return array('ok' => true, 'snapshot_url' => $snapshot);
        }
        return array(
            'ok'           => false,
            'error'        => 'timeout',
            'save_status'  => $saveHttpCode,
            'note'         => 'Snapshot may still be processing — try again in 1–2 minutes.',
        );
    }

    // -------- helpers ------------------------------------------------------

    /**
     * Resolve the slug to save for a given gid.
     * - If $data['slug'] is provided and non-empty, normalize and use it.
     * - Otherwise, fetch existing slug from DB; if present, keep it.
     * - Otherwise, derive from #__games.game_name.
     */
    protected static function resolveSlug($data, $gid)
    {
        $candidate = isset($data['slug']) ? trim((string)$data['slug']) : '';
        if ($candidate !== '') {
            return self::slugify($candidate);
        }
        $db = JFactory::getDbo();
        $db->setQuery("SELECT slug FROM #__slot_rtp WHERE gid = " . (int)$gid . " LIMIT 1");
        $existing = trim((string)$db->loadResult());
        if ($existing !== '') {
            return $existing;
        }
        // Derive from game name
        $db->setQuery("SELECT game_name FROM #__games WHERE gid = " . (int)$gid . " LIMIT 1");
        $name = (string)$db->loadResult();
        if ($name === '') return null;
        return self::slugify($name);
    }

    /**
     * Generate a URL-safe slug from arbitrary text.
     */
    protected static function slugify($text)
    {
        $text = (string)$text;
        // Lowercase ascii fold
        $text = mb_strtolower($text, 'UTF-8');
        // Replace dollar sign with 's' before stripping (Fat Ca$h → fat-cash)
        $text = str_replace('$', 's', $text);
        // Replace whitespace and separators with hyphen
        $text = preg_replace('~[\s_/]+~u', '-', $text);
        // Strip everything that isn't ascii letter/digit/hyphen
        $text = preg_replace('~[^a-z0-9-]~u', '', $text);
        // Collapse consecutive hyphens
        $text = preg_replace('~-+~', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : null;
    }

    protected static function toIntOrNull($data, $key)
    {
        if (!isset($data[$key])) return null;
        $v = trim((string)$data[$key]);
        if ($v === '') return null;
        return (int)$v;
    }
    protected static function toDecOrNull($data, $key)
    {
        if (!isset($data[$key])) return null;
        $v = trim((string)$data[$key]);
        if ($v === '') return null;
        // accept comma decimal separator
        $v = str_replace(',', '.', $v);
        if (!is_numeric($v)) return null;
        return (float)$v;
    }
    protected static function toStrOrNull($data, $key)
    {
        if (!isset($data[$key])) return null;
        $v = trim((string)$data[$key]);
        if ($v === '') return null;
        return $v;
    }
}
