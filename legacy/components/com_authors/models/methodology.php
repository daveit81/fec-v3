<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Methodology model — drives /methodology/<topic>.
 *
 * V1 ships the "fxcheck" topic. The model is structured to make future topics
 * (rtp-verification, editorial-standards, ...) a config-only addition.
 *
 * Topic config keys:
 *   - title        h1 string
 *   - tagline      one-line description for hero + meta
 *   - author_slug  primary author (must exist in #__casino_authors)
 *   - reviewer_slug optional reviewer slug
 *   - version      methodology version (e.g. "v3")
 *   - last_updated YYYY-MM-DD
 *   - tmpl         Joomla layout suffix. The actual file is tmpl/default_<tmpl>.php
 *                  (Joomla convention: parent::display('fxcheck') resolves to
 *                  default_fxcheck.php, NOT fxcheck.php).
 *
 * FXCheck status thresholds (used in getFxcheckStats() and surfaced in the
 * template's status table). Tune here if internal logic changes.
 */
class AuthorsModelMethodology extends JModel
{
    /** Minimum reports before a bonus can be classified beyond "Active". */
    const FX_MIN_REPORTS  = 5;

    /** Positive-ratio threshold for "Verified" status. */
    const FX_POS_THRESHOLD = 0.80;

    /** Negative-ratio threshold for "Likely expired" status. */
    const FX_NEG_THRESHOLD = 0.80;

    /** Recent-window in days used in "reports in last N days" stat. */
    const FX_RECENT_WINDOW = 30;

    /** Decay-window in days after which reports drop out of active scoring. */
    const FX_DECAY_WINDOW  = 90;

    /**
     * Topic registry. Reads from #__methodology_topics when available
     * (V2.7.3+); falls back to the hardcoded array otherwise (zero-downtime
     * upgrade — if the migration hasn't run or the table is missing, the
     * site continues to work with the values shipped in this file).
     *
     * Each entry has a 'status' field that drives hub display and the
     * methodology view access control:
     *   - 'live'        : topic page exists; methodology view will render it
     *   - 'coming_soon' : placeholder card on the hub; methodology view 404s
     *   - 'external'    : card on the hub linking to an existing URL elsewhere
     *                     (e.g. /who-is-frank). methodology view 404s.
     *   - 'draft'       : invisible on hub and view 404s. For staging.
     */
    public function getTopics()
    {
        $rows = $this->fetchTopicsFromDb();
        if (!empty($rows)) {
            return $rows;
        }
        return $this->getTopicsHardcoded();
    }

    /**
     * DB read. Returns keyed by slug for compatibility with the legacy
     * hardcoded array shape. Returns empty array if the table is missing
     * (caller falls back to hardcoded).
     */
    protected function fetchTopicsFromDb()
    {
        $out = array();
        try {
            $db = JFactory::getDbo();
            // `kind` and `schema_type` exist from 2.7.38. If the table predates
            // the migration, this query errors and we fall back to hardcoded —
            // which is correct (no page topics to manage pre-migration anyway).
            $db->setQuery(
                "SELECT slug, kind, status, title, tagline, short_description, description,
                        author_slug, reviewer_slug, version, last_updated, tmpl,
                        schema_type, external_url, sort_order
                 FROM #__methodology_topics
                 ORDER BY sort_order ASC, slug ASC"
            );
            $rows = $db->loadAssocList();
            if (!is_array($rows) || empty($rows)) return array();
            foreach ($rows as $r) {
                $slug = (string)$r['slug'];
                if ($slug === '') continue;
                // Format last_updated as YYYY-MM-DD (drop the 0000-00-00 placeholder)
                $lu = isset($r['last_updated']) ? (string)$r['last_updated'] : '';
                if ($lu === '0000-00-00' || $lu === null) $lu = '';
                $out[$slug] = array(
                    'kind'              => (isset($r['kind']) && $r['kind'] !== '') ? (string)$r['kind'] : 'methodology',
                    'status'            => isset($r['status'])            ? (string)$r['status']            : 'draft',
                    'title'             => isset($r['title'])             ? (string)$r['title']             : '',
                    'tagline'           => isset($r['tagline'])           ? (string)$r['tagline']           : '',
                    'short_description' => isset($r['short_description']) ? (string)$r['short_description'] : '',
                    'description'       => isset($r['description'])       ? (string)$r['description']       : '',
                    'author_slug'       => isset($r['author_slug'])       ? (string)$r['author_slug']       : '',
                    'reviewer_slug'     => isset($r['reviewer_slug'])     ? (string)$r['reviewer_slug']     : '',
                    'version'           => isset($r['version'])           ? (string)$r['version']           : '',
                    'last_updated'      => $lu,
                    'tmpl'              => isset($r['tmpl'])              ? (string)$r['tmpl']              : '',
                    'schema_type'       => isset($r['schema_type'])       ? (string)$r['schema_type']       : '',
                    'external_url'      => isset($r['external_url'])      ? (string)$r['external_url']      : '',
                    'sort_order'        => isset($r['sort_order'])        ? (int)$r['sort_order']           : 0,
                );
            }
            return $out;
        } catch (Exception $e) {
            // Table missing or query error — fall through to hardcoded
            return array();
        }
    }

    /**
     * Hardcoded fallback (preserves pre-V2.7.3 behaviour). Kept in sync with
     * the seed values in admin/sql/updates/mysql/2.7.3.sql for the first run.
     */
    protected function getTopicsHardcoded()
    {
        return array(
            'fxcheck' => array(
                'status'        => 'live',
                'title'         => 'How FXCheck™ Works',
                'tagline'       => 'Player-verified bonus status: how we calculate it, what each status means, and how to read it.',
                'short_description' => 'Player-verified bonus status from real Yes/No reports. Live thresholds, status definitions, recency weighting, and editorial overrides.',
                'author_slug'   => 'david-romano',
                'reviewer_slug' => 'sabrina-oconnell',
                'version'       => 'v3',
                'last_updated'  => '2026-05-23',
                'tmpl'          => 'fxcheck',
                'description'   => 'Methodology behind the FXCheck™ system that powers bonus status signals on FreeExtraChips.',
            ),
            'fxshield' => array(
                'status'        => 'draft',
                'title'         => 'How FXShield™ Works',
                'tagline'       => 'Every weight, threshold and half-life behind our 0–100 casino safety index — published in full, with a public event log and a confidence grade on every score.',
                'short_description' => 'The complete FXShield™ formula: verified baseline, dated event log with half-lives, live FXCheck signal, group-risk transfer rates, and A–D confidence grades.',
                'author_slug'   => 'mark-sullivan',
                'reviewer_slug' => 'ethan-walker',
                'version'       => 'SI v1.0',
                'last_updated'  => '',
                'tmpl'          => 'fxshield',
                'description'   => 'Methodology behind FXShield, the FreeExtraChips casino safety index: baseline checklist and weights, event taxonomy with decay half-lives, hard caps, Payout Reality Gap, group-risk coefficients, confidence grades, dispute process and version history.',
            ),
            'frank' => array(
                'status'        => 'external',
                'title'         => 'How FRank Ranks Casinos',
                'tagline'       => 'Our proprietary casino ranking algorithm, in development since 2007.',
                'short_description' => 'Proprietary ranking algorithm combining license validity, software providers, banking options, and weighted player reviews.',
                'external_url'  => '/who-is-frank',
                'last_updated'  => '',
            ),
            'rtp-verification' => array(
                'status'        => 'live',
                'title'         => 'How We Verify In-Game RTP',
                'tagline'       => 'We check the RTP each slot actually serves vs. the value its provider publishes — and flag bonuses where the configured RTP is lower than expected.',
                'short_description' => 'How we identify slot configurations where the in-game RTP is set below the provider\'s published value.',
                'author_slug'   => 'david-romano',
                'reviewer_slug' => 'sabrina-oconnell',
                'version'       => 'v1',
                'last_updated'  => '2026-05-24',
                'tmpl'          => 'rtp-verification',
                'description'   => 'Methodology behind FreeExtraChips\' RTP verification system: provider primary sources, Wayback archiving, multi-tier RTP disclosure (Pragmatic Play, Play\'n Go), and per-casino paytable observations for opaque providers (RTG/SpinLogic/Rival).',
            ),
            'editorial-standards' => array(
                'status'        => 'live',
                'title'         => 'Editorial Standards',
                'tagline'       => 'How reviews are written, sourced, fact-checked, and corrected — plus our affiliate disclosure and conflict-of-interest policy.',
                'short_description' => 'How our reviews are written, reviewed, and corrected. Source hierarchy, affiliate disclosure, and corrections policy.',
                'author_slug'   => 'sabrina-oconnell',
                'reviewer_slug' => 'ethan-walker',
                'version'       => 'v1.3',
                'last_updated'  => '2026-10-13',
                'tmpl'          => 'editorial-standards',
                'description'   => 'Editorial standards at FreeExtraChips: source hierarchy (provider primary, regulator certificate, in-game observation, operator T&C), affiliate disclosure model, fact-checking workflow, update cadence per data type, correction policy, and conflict-of-interest rules.',
            ),
        );
    }

    /**
     * Save a topic (create or update). Auto-bumps updated_at; last_updated
     * is set by the caller (controller passes either the user-edited value
     * or NOW() when the "bump to today" button is clicked).
     *
     * Returns (int) topic id on success, or false on failure.
     */
    public function saveTopic($data)
    {
        $db = JFactory::getDbo();
        $slug = isset($data['slug']) ? trim((string)$data['slug']) : '';
        if ($slug === '') return false;

        // Validate status enum
        $allowedStatus = array('live', 'coming_soon', 'external', 'draft');
        $status = isset($data['status']) ? (string)$data['status'] : 'coming_soon';
        if (!in_array($status, $allowedStatus, true)) $status = 'coming_soon';

        // Validate last_updated as YYYY-MM-DD
        $lastUpdated = isset($data['last_updated']) ? trim((string)$data['last_updated']) : '';
        if ($lastUpdated !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastUpdated)) {
            $lastUpdated = '';
        }

        // kind: 'methodology' (default) or 'page'. Validated against a small
        // allow-list so a malformed post can't invent a type. When absent we
        // leave it out of the column set entirely (see below) so an UPDATE
        // never disturbs an existing row's kind.
        $kind = isset($data['kind']) ? (string)$data['kind'] : '';
        if (!in_array($kind, array('methodology', 'page'), true)) $kind = '';

        $cols = array(
            'slug'              => $slug,
            'status'            => $status,
            'title'             => isset($data['title'])             ? trim((string)$data['title'])             : '',
            'tagline'           => isset($data['tagline'])           ? trim((string)$data['tagline'])           : '',
            'short_description' => isset($data['short_description']) ? trim((string)$data['short_description']) : '',
            'description'       => isset($data['description'])       ? trim((string)$data['description'])       : '',
            'author_slug'       => isset($data['author_slug'])       ? trim((string)$data['author_slug'])       : '',
            'reviewer_slug'     => isset($data['reviewer_slug'])     ? trim((string)$data['reviewer_slug'])     : '',
            'version'           => isset($data['version'])           ? trim((string)$data['version'])           : '',
            'last_updated'      => $lastUpdated,
            'tmpl'              => isset($data['tmpl'])              ? trim((string)$data['tmpl'])              : '',
            'external_url'      => isset($data['external_url'])      ? trim((string)$data['external_url'])      : '',
            'sort_order'        => isset($data['sort_order'])        ? (int)$data['sort_order']                 : 0,
        );

        // Only carry kind into the write when explicitly provided & valid. For
        // an UPDATE this means an edit that omits kind leaves the stored kind
        // intact (a page topic stays a page). For an INSERT of a page topic the
        // admin passes kind='page' so it's classified correctly instead of
        // silently defaulting to 'methodology'.
        if ($kind !== '') {
            $cols['kind'] = $kind;
        }

        // Check if row exists
        $db->setQuery(
            "SELECT id FROM #__methodology_topics WHERE slug = " . $db->quote($slug) . " LIMIT 1"
        );
        $existingId = (int)$db->loadResult();

        if ($existingId > 0) {
            // UPDATE
            $sets = array();
            foreach ($cols as $col => $val) {
                if ($col === 'slug') continue; // immutable
                if ($col === 'last_updated' && $val === '') {
                    $sets[] = $db->quoteName($col) . ' = NULL';
                } elseif (is_int($val)) {
                    $sets[] = $db->quoteName($col) . ' = ' . $val;
                } else {
                    $sets[] = $db->quoteName($col) . ' = ' . $db->quote($val);
                }
            }
            $sql = "UPDATE #__methodology_topics SET " . implode(', ', $sets)
                 . " WHERE id = " . $existingId;
            $db->setQuery($sql);
            $db->query();
            if ($db->getErrorNum()) return false;
            return $existingId;
        }

        // INSERT
        $colNames = array();
        $values   = array();
        foreach ($cols as $col => $val) {
            $colNames[] = $db->quoteName($col);
            if ($col === 'last_updated' && $val === '') {
                $values[] = 'NULL';
            } elseif (is_int($val)) {
                $values[] = $val;
            } else {
                $values[] = $db->quote($val);
            }
        }
        $sql = "INSERT INTO #__methodology_topics (" . implode(', ', $colNames) . ") "
             . "VALUES (" . implode(', ', $values) . ")";
        $db->setQuery($sql);
        $db->query();
        if ($db->getErrorNum()) return false;
        return (int)$db->insertid();
    }

    /**
     * Return a topic config only if status='live'. The methodology view uses
     * this so non-live topics 404 instead of trying to load a missing tmpl.
     * The hub view calls getTopics() directly to show all topics.
     */
    public function getTopicConfig($slug)
    {
        $slug   = trim((string)$slug);
        $topics = $this->getTopics();
        if (!isset($topics[$slug])) return null;
        $t = $topics[$slug];
        // Routing safety: the methodology view must never serve a 'page' topic
        // (those belong to the page view with their own template/URL). Without
        // this, /methodology/<page-slug> would resolve post-2.7.38 and render
        // with the wrong layout. A topic with no kind is treated as methodology
        // (pre-2.7.38 rows have no kind column).
        $kind = isset($t['kind']) && $t['kind'] !== '' ? $t['kind'] : 'methodology';
        if ($kind !== 'methodology') return null;
        if (!isset($t['status']) || $t['status'] !== 'live') return null;
        return $t;
    }

    /**
     * Hub aggregate stats. Reused across the hub page and the homepage trust
     * band (when/if that mounts a similar widget).
     *
     * Returns:
     *   - total_reports      : COUNT(*) #__bonus_feedback
     *   - reports_recent     : last FX_RECENT_WINDOW days
     *   - casinos_covered    : DISTINCT cid with any FXCheck activity
     *   - methodologies_live : count of topics with status='live'
     *   - methodologies_total: total topics in the registry (live + planned)
     *   - last_methodology_update : YYYY-MM-DD from the most recent live topic
     */
    public function getHubStats()
    {
        $out = array(
            'total_reports'           => 0,
            'reports_recent'          => 0,
            'casinos_covered'         => 0,
            'methodologies_live'      => 0,
            'methodologies_total'     => 0,
            'last_methodology_update' => '',
            'recent_window_days'      => self::FX_RECENT_WINDOW,
        );

        // Reuse the FXCheck stats query for the trust numbers (single source
        // of truth — if those queries change shape, both pages update).
        $fx = $this->getFxcheckStats();
        $out['total_reports']    = (int)$fx['total_reports'];
        $out['reports_recent']   = (int)$fx['reports_recent'];
        $out['casinos_covered']  = (int)$fx['casinos_covered'];

        // Methodology counts from the registry (page-kind rows are the editorial
        // pages' registry, not methodologies: the hub does not count them)
        $topics = $this->getTopics();
        $latest = '';
        foreach ($topics as $t) {
            $kind = (isset($t['kind']) && $t['kind'] !== '') ? $t['kind'] : 'methodology';
            if ($kind === 'page') continue;
            $out['methodologies_total']++;
            if (!isset($t['status']) || $t['status'] !== 'live') continue;
            $out['methodologies_live']++;
            if (!empty($t['last_updated']) && $t['last_updated'] > $latest) {
                $latest = $t['last_updated'];
            }
        }
        $out['last_methodology_update'] = $latest;

        return $out;
    }

    /**
     * Live FXCheck stats for the methodology page hero strip + transparency
     * section. All queries are independent — one failure returns 0 for that
     * stat, not the whole array.
     *
     * Returns:
     *   - total_reports        : COUNT(*) #__bonus_feedback
     *   - reports_recent       : COUNT(*) #__bonus_feedback in last FX_RECENT_WINDOW days
     *   - casinos_covered      : COUNT(DISTINCT cid) on bonuses with any FXCheck activity
     *   - bonuses_tracked      : COUNT(DISTINCT bid) in #__bonus_feedback_agg with yes+no > 0
     *   - status_verified      : bonuses meeting FX_MIN_REPORTS + >= FX_POS_THRESHOLD positive
     *   - status_mixed         : bonuses meeting FX_MIN_REPORTS in the middle band
     *   - status_likely_expired: bonuses meeting FX_MIN_REPORTS + >= FX_NEG_THRESHOLD negative
     *   - launch_year          : MIN(YEAR(created_at)) #__bonus_feedback (0 if no data)
     *   - last_report_ts       : MAX(UNIX_TIMESTAMP(created_at)) #__bonus_feedback
     */
    public function getFxcheckStats()
    {
        $db = JFactory::getDbo();

        $out = array(
            'total_reports'         => 0,
            'reports_recent'        => 0,
            'casinos_covered'       => 0,
            'bonuses_tracked'       => 0,
            'status_verified'       => 0,
            'status_mixed'          => 0,
            'status_likely_expired' => 0,
            'launch_year'           => 0,
            'last_report_ts'        => 0,
            // expose thresholds so the template doesn't re-import the consts
            'min_reports'           => self::FX_MIN_REPORTS,
            'pos_threshold_pct'     => (int)round(self::FX_POS_THRESHOLD * 100),
            'neg_threshold_pct'     => (int)round(self::FX_NEG_THRESHOLD * 100),
            'recent_window_days'    => self::FX_RECENT_WINDOW,
            'decay_window_days'     => self::FX_DECAY_WINDOW,
        );

        // 1) total reports
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonus_feedback'));
        $db->setQuery($q);
        $out['total_reports'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology total_reports: ' . $db->getErrorMsg());
            $out['total_reports'] = 0;
        }

        // 2) reports in last FX_RECENT_WINDOW days
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonus_feedback'))
            ->where($db->quoteName('created_at') . ' >= DATE_SUB(NOW(), INTERVAL ' . (int)self::FX_RECENT_WINDOW . ' DAY)');
        $db->setQuery($q);
        $out['reports_recent'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology reports_recent: ' . $db->getErrorMsg());
            $out['reports_recent'] = 0;
        }

        // 3) distinct casinos covered — JOIN feedback → bonuses → distinct cid
        $sql = 'SELECT COUNT(DISTINCT b.cid)'
             . ' FROM ' . $db->quoteName('#__bonus_feedback') . ' AS bf'
             . ' INNER JOIN ' . $db->quoteName('#__bonuses') . ' AS b ON b.bid = bf.bid';
        $db->setQuery($sql);
        $out['casinos_covered'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology casinos_covered: ' . $db->getErrorMsg());
            $out['casinos_covered'] = 0;
        }

        // 4) bonuses with at least one report (from the agg table — faster)
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonus_feedback_agg'))
            ->where('(yes_count + no_count) > 0');
        $db->setQuery($q);
        $out['bonuses_tracked'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology bonuses_tracked: ' . $db->getErrorMsg());
            $out['bonuses_tracked'] = 0;
        }

        // 5-7) status distribution — single query, three counters
        // Casts to DECIMAL to avoid integer-division pitfalls when computing the ratio.
        $minReports = (int)self::FX_MIN_REPORTS;
        $posThresh  = (float)self::FX_POS_THRESHOLD;
        $negThresh  = (float)self::FX_NEG_THRESHOLD;

        $sql = 'SELECT'
             . '  SUM(CASE WHEN (yes_count + no_count) >= ' . $minReports
             .         ' AND (yes_count / (yes_count + no_count)) >= ' . $posThresh
             .       ' THEN 1 ELSE 0 END) AS verified,'
             . '  SUM(CASE WHEN (yes_count + no_count) >= ' . $minReports
             .         ' AND (no_count / (yes_count + no_count)) >= ' . $negThresh
             .       ' THEN 1 ELSE 0 END) AS likely_expired,'
             . '  SUM(CASE WHEN (yes_count + no_count) >= ' . $minReports
             .         ' AND (yes_count / (yes_count + no_count)) < ' . $posThresh
             .         ' AND (no_count / (yes_count + no_count)) < ' . $negThresh
             .       ' THEN 1 ELSE 0 END) AS mixed'
             . ' FROM ' . $db->quoteName('#__bonus_feedback_agg')
             . ' WHERE (yes_count + no_count) > 0';
        $db->setQuery($sql);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology status counts: ' . $db->getErrorMsg());
        } elseif (is_array($row)) {
            $out['status_verified']       = (int)$row['verified'];
            $out['status_mixed']          = (int)$row['mixed'];
            $out['status_likely_expired'] = (int)$row['likely_expired'];
        }

        // 8) launch year — MIN(YEAR(created_at))
        $q = $db->getQuery(true)
            ->select('MIN(YEAR(created_at))')
            ->from($db->quoteName('#__bonus_feedback'));
        $db->setQuery($q);
        $out['launch_year'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology launch_year: ' . $db->getErrorMsg());
            $out['launch_year'] = 0;
        }

        // 9) last report timestamp
        $q = $db->getQuery(true)
            ->select('UNIX_TIMESTAMP(MAX(created_at))')
            ->from($db->quoteName('#__bonus_feedback'));
        $db->setQuery($q);
        $out['last_report_ts'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology last_report_ts: ' . $db->getErrorMsg());
            $out['last_report_ts'] = 0;
        }

        return $out;
    }

    /**
     * Live RTP-database stats — used by /methodology/rtp-verification.
     *
     * Returns:
     *   - slots_documented        : COUNT(*) #__slot_rtp
     *   - slots_with_rtp          : with rtp_theoretical_max NOT NULL
     *   - slots_with_source       : with primary_source_url populated
     *   - slots_with_archive      : with primary_source_archived_url populated
     *   - slots_multi_tier        : with rtp_theoretical_min NOT NULL AND < max
     *   - slots_with_observations : DISTINCT gid in #__slot_rtp_observations
     *   - observations_total      : COUNT(*) #__slot_rtp_observations
     *   - providers_top           : top 5 providers by documented slot count,
     *                               as array of {name, count}
     *   - last_verified_ts        : MAX(UNIX_TIMESTAMP(last_verified_date))
     *
     * All counters degrade to 0 silently on query error (tables may not exist
     * yet on a partial install). Errors logged.
     */
    public function getRtpStats()
    {
        $db = JFactory::getDbo();
        $out = array(
            'slots_documented'        => 0,
            'slots_with_rtp'          => 0,
            'slots_with_source'       => 0,
            'slots_with_archive'      => 0,
            'slots_multi_tier'        => 0,
            'slots_with_observations' => 0,
            'observations_total'      => 0,
            'providers_top'           => array(),
            'last_verified_ts'        => 0,
        );

        // 1) headline aggregates — single roundtrip
        $sql = 'SELECT'
             . ' COUNT(*) AS slots_documented,'
             . ' SUM(CASE WHEN rtp_theoretical_max IS NOT NULL THEN 1 ELSE 0 END) AS slots_with_rtp,'
             . ' SUM(CASE WHEN primary_source_url IS NOT NULL AND primary_source_url != \'\' THEN 1 ELSE 0 END) AS slots_with_source,'
             . ' SUM(CASE WHEN primary_source_archived_url IS NOT NULL AND primary_source_archived_url != \'\' THEN 1 ELSE 0 END) AS slots_with_archive,'
             . ' SUM(CASE WHEN rtp_theoretical_min IS NOT NULL AND rtp_theoretical_max IS NOT NULL AND rtp_theoretical_min < rtp_theoretical_max THEN 1 ELSE 0 END) AS slots_multi_tier,'
             . ' UNIX_TIMESTAMP(MAX(last_verified_date)) AS last_verified_ts'
             . ' FROM ' . $db->quoteName('#__slot_rtp');
        $db->setQuery($sql);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology getRtpStats headline: ' . $db->getErrorMsg());
        } elseif (is_array($row)) {
            $out['slots_documented']   = (int)$row['slots_documented'];
            $out['slots_with_rtp']     = (int)$row['slots_with_rtp'];
            $out['slots_with_source']  = (int)$row['slots_with_source'];
            $out['slots_with_archive'] = (int)$row['slots_with_archive'];
            $out['slots_multi_tier']   = (int)$row['slots_multi_tier'];
            $out['last_verified_ts']   = (int)$row['last_verified_ts'];
        }

        // 2) observations — distinct slots covered + total count
        $sql = 'SELECT'
             . ' COUNT(*) AS observations_total,'
             . ' COUNT(DISTINCT gid) AS slots_with_observations'
             . ' FROM ' . $db->quoteName('#__slot_rtp_observations');
        $db->setQuery($sql);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology getRtpStats obs: ' . $db->getErrorMsg());
        } elseif (is_array($row)) {
            $out['observations_total']      = (int)$row['observations_total'];
            $out['slots_with_observations'] = (int)$row['slots_with_observations'];
        }

        // 3) top providers by documented slot count
        $sql = 'SELECT g.provider_name AS name, COUNT(*) AS cnt'
             . ' FROM ' . $db->quoteName('#__slot_rtp') . ' AS rtp'
             . ' INNER JOIN ' . $db->quoteName('#__games') . ' AS g ON g.gid = rtp.gid'
             . ' WHERE g.provider_name != \'\''
             . ' GROUP BY g.provider_name'
             . ' ORDER BY cnt DESC, g.provider_name ASC'
             . ' LIMIT 5';
        $db->setQuery($sql);
        $rows = $db->loadAssocList();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology getRtpStats providers: ' . $db->getErrorMsg());
        } elseif (is_array($rows)) {
            foreach ($rows as $r) {
                $out['providers_top'][] = array(
                    'name'  => $r['name'],
                    'count' => (int)$r['cnt'],
                );
            }
        }

        return $out;
    }

    /**
     * Per-casino FXShield status for the admin cockpit (read-only).
     *
     * One row per casino that has any FXShield footprint (a score snapshot,
     * baseline rows, or ledger events), joined to the jReviews listing title.
     * Drives /fxshield-admin: the at-a-glance view of which casinos are
     * scored, which sit at low confidence and need first-party verification,
     * and which have incomplete baselines.
     *
     * Degrades to an empty array if the fec_* tables aren't deployed yet
     * (same silent-degradation contract as getFxshieldStats).
     *
     * Each row:
     *   casino_id, title, si_score, si_tier, si_confidence, si_caps_applied,
     *   prg_ratio, si_delta_30d, computed_at,
     *   baseline_fields   (count of baseline rows present, 0-9)
     *   baseline_verified (count with verified_first_party = 1, 0-9)
     *   events_active     (active ledger events)
     *   last_first_party  (most recent staff_test event date, or NULL)
     */
    public function getFxshieldDashboard()
    {
        $rows = array();
        try {
            $db = JFactory::getDbo();

            // Universe + snapshot in one pass; LEFT JOINs so a casino with a
            // baseline but no score yet still shows up (as "not scored").
            $sql = "SELECT u.casino_id,
                           c.title AS title,
                           s.si_score, s.si_tier, s.si_confidence,
                           s.si_caps_applied, s.prg_ratio, s.si_delta_30d,
                           s.computed_at,
                           (SELECT COUNT(*) FROM #__fec_safety_baseline b
                             WHERE b.casino_id = u.casino_id) AS baseline_fields,
                           (SELECT COUNT(*) FROM #__fec_safety_baseline b
                             WHERE b.casino_id = u.casino_id
                               AND b.verified_first_party = 1) AS baseline_verified,
                           (SELECT COUNT(*) FROM #__fec_safety_events e
                             WHERE e.casino_id = u.casino_id
                               AND e.status = 'active') AS events_active,
                           (SELECT MAX(e2.event_date) FROM #__fec_safety_events e2
                             WHERE e2.casino_id = u.casino_id
                               AND e2.source_type = 'staff_test'
                               AND e2.status IN ('active','expired')) AS last_first_party
                      FROM (
                            SELECT casino_id FROM #__fec_safety_scores
                            UNION SELECT casino_id FROM #__fec_safety_baseline
                            UNION SELECT casino_id FROM #__fec_safety_events
                           ) u
                      LEFT JOIN #__fec_safety_scores s ON s.casino_id = u.casino_id
                      LEFT JOIN #__content c           ON c.id = u.casino_id
                     ORDER BY (s.si_score IS NULL) ASC, s.si_score DESC";
            $db->setQuery($sql);
            $res = $db->loadAssocList();
            if ($db->getErrorNum()) {
                return array();
            }
            if (is_array($res)) {
                $rows = $res;
            }
        } catch (Exception $e) {
            return array();
        }
        return $rows;
    }

    /**
     * Live FXShield stats — used by /methodology/fxshield.
     *
     * Reads the safety-engine tables (#__fec_safety_scores and
     * #__fec_safety_events). Those tables ship with safety-engine.php, NOT
     * with this component, so every counter degrades to 0 silently while the
     * engine isn't deployed (same pattern as getRtpStats on partial
     * installs). The fxshield template checks 'engine_live' and renders
     * static formula facts instead of a row of zeros until then.
     *
     * Returns:
     *   - engine_live       : true once at least one casino holds a score
     *   - casinos_scored    : COUNT si_score IS NOT NULL in #__fec_safety_scores
     *   - events_public     : public ledger entries (active + expired; excludes
     *                         superseded/revoked and internal-only rows)
     *   - staff_tests       : public ledger entries with source_type='staff_test'
     *   - last_computed_ts  : UNIX_TIMESTAMP(MAX(computed_at)) of the snapshot
     */
    public function getFxshieldStats()
    {
        $out = array(
            'engine_live'      => false,
            'casinos_scored'   => 0,
            'events_public'    => 0,
            'staff_tests'      => 0,
            'last_computed_ts' => 0,
        );

        try {
            $db = JFactory::getDbo();

            // 1) score snapshot — single roundtrip
            $sql = 'SELECT'
                 . ' SUM(CASE WHEN si_score IS NOT NULL THEN 1 ELSE 0 END) AS casinos_scored,'
                 . ' UNIX_TIMESTAMP(MAX(computed_at)) AS last_computed_ts'
                 . ' FROM ' . $db->quoteName('#__fec_safety_scores');
            $db->setQuery($sql);
            $row = $db->loadAssoc();
            if ($db->getErrorNum()) {
                // Table missing (engine not deployed yet) — stay degraded
                return $out;
            }
            if (is_array($row)) {
                $out['casinos_scored']   = (int)$row['casinos_scored'];
                $out['last_computed_ts'] = (int)$row['last_computed_ts'];
            }

            // 2) public ledger counters — single roundtrip
            $sql = 'SELECT'
                 . ' COUNT(*) AS events_public,'
                 . ' SUM(CASE WHEN source_type = ' . $db->quote('staff_test') . ' THEN 1 ELSE 0 END) AS staff_tests'
                 . ' FROM ' . $db->quoteName('#__fec_safety_events')
                 . ' WHERE visibility = ' . $db->quote('public')
                 . ' AND status IN (' . $db->quote('active') . ', ' . $db->quote('expired') . ')';
            $db->setQuery($sql);
            $row = $db->loadAssoc();
            if ($db->getErrorNum()) {
                error_log('AuthorsModelMethodology getFxshieldStats events: ' . $db->getErrorMsg());
            } elseif (is_array($row)) {
                $out['events_public'] = (int)$row['events_public'];
                $out['staff_tests']   = (int)$row['staff_tests'];
            }

            $out['engine_live'] = ($out['casinos_scored'] > 0);
        } catch (Exception $e) {
            // Newer DB drivers throw on missing tables — degrade silently,
            // identical behaviour to the getErrorNum path above.
            return $out;
        }

        return $out;
    }

    /**
     * Live FXRate stats for the methodology page hero strip and the
     * "numbers today" table (V2.7.73). One query over the active welcome
     * packages and their legs (real rows read through #__bonuses, virtual
     * legs from the side table), then the same arithmetic the public band
     * uses: the calculation core components/com_bonuses/helpers/fec_fxrate.php
     * is loaded guarded. Missing core or tables -> 'live' false and zeros;
     * the template then shows dashes, never an invented number.
     *
     * Only packages the public render would show are counted: status
     * active, opener row not expired, at least two deposits, casino not
     * closed / blacklisted / suspended (jReviews flags, listing rule). "Priced" means
     * every cap and match is stated, so an effective rate exists.
     *
     * Returns:
     *   - live               : true when the core loaded and the query ran
     *   - packages_total     : packages counted
     *   - packages_priced    : packages with an effective rate
     *   - casinos            : DISTINCT cid among packages_total
     *   - legs_total         : legs across packages_total
     *   - median_advertised  : median advertised match % (priced packages), null if none
     *   - median_effective   : median effective match % (priced packages), null if none
     *   - median_ratio       : median advertised / effective (priced packages), null if none
     *   - below_100          : priced packages with effective < 100%
     *   - below_50           : priced packages with effective <= 50%
     *   - max_ratio          : the largest advertised / effective seen
     */
    public function getFxrateStats()
    {
        $out = array(
            'live'              => false,
            'packages_total'    => 0,
            'packages_priced'   => 0,
            'casinos'           => 0,
            'legs_total'        => 0,
            'median_advertised' => null,
            'median_effective'  => null,
            'median_ratio'      => null,
            'below_100'         => 0,
            'below_50'          => 0,
            'max_ratio'         => null,
        );

        if (!function_exists('fec_fxrate_package')) {
            $core = JPATH_SITE . '/components/com_bonuses/helpers/fec_fxrate.php';
            if (is_readable($core)) {
                require_once $core;
            }
        }
        if (!function_exists('fec_fxrate_package') || !function_exists('fec_fxrate_leg')) {
            return $out;
        }

        try {
            $db  = JFactory::getDbo();
            $sql = 'SELECT p.pkg_id, p.cid, p.currency AS pkg_currency, p.deposits, p.spins_total,'
                 . ' ob.status AS opener_status,'
                 . ' jc.jr_closed, jc.jr_blacklisted, jc.jr_suspended,'
                 . ' l.position, l.bid, l.spins,'
                 . ' CASE WHEN l.bid IS NULL THEN l.pct     ELSE b.bonus     END AS pct,'
                 . ' CASE WHEN l.bid IS NULL THEN l.cap     ELSE b.max_bonus END AS cap,'
                 . ' CASE WHEN l.bid IS NULL THEN l.min_dep ELSE b.min_dep   END AS min_dep,'
                 . ' CASE WHEN l.bid IS NULL THEN l.max_dep ELSE b.max_dep   END AS max_dep,'
                 . ' CASE WHEN l.bid IS NULL THEN l.wr      ELSE b.wr        END AS wr,'
                 . ' CASE WHEN l.bid IS NULL THEN l.wr_meth ELSE b.wr_meth   END AS wr_meth,'
                 . ' CASE WHEN l.bid IS NULL THEN l.max_cashout      ELSE b.max_cashout      END AS max_cashout,'
                 . ' CASE WHEN l.bid IS NULL THEN l.max_cashout_kind ELSE b.max_cashout_kind END AS max_cashout_kind,'
                 . ' b.currency AS row_currency, b.type AS row_type'
                 . ' FROM ' . $db->quoteName('#__fec_bonus_packages') . ' p'
                 . ' INNER JOIN ' . $db->quoteName('#__fec_bonus_package_legs') . ' l ON l.pkg_id = p.pkg_id'
                 . ' LEFT JOIN ' . $db->quoteName('#__bonuses') . ' b ON b.bid = l.bid'
                 . ' LEFT JOIN ' . $db->quoteName('#__bonuses') . ' ob ON ob.bid = p.opener_bid'
                 . ' LEFT JOIN ' . $db->quoteName('#__jreviews_content') . ' jc ON jc.contentid = p.cid'
                 . ' WHERE p.status = ' . $db->quote('active')
                 . ' ORDER BY p.pkg_id ASC, l.position ASC';
            $db->setQuery($sql);
            $rows = $db->loadAssocList();
            if ($db->getErrorNum()) {
                return $out;
            }
        } catch (Exception $e) {
            return $out;
        }

        $groups = array();
        foreach ((array)$rows as $r) {
            $groups[(int)$r['pkg_id']][] = $r;
        }

        $adv = array(); $eff = array(); $ratio = array();
        $cids = array();
        foreach ($groups as $pkgId => $legsRaw) {
            $first = $legsRaw[0];
            $openerStatus = isset($first['opener_status']) ? strtolower(trim((string)$first['opener_status'])) : '';
            if ($openerStatus !== '' && $openerStatus !== 'active') continue;
            // casino exclusion: the core's rule (0.5.1, the listings' one); the index model carries the same fallback
            if (function_exists('fec_fxrate_casino_excluded')) {
                if (fec_fxrate_casino_excluded($first['jr_closed'], $first['jr_blacklisted'], $first['jr_suspended'])) continue;
            } else {
                $jcClosed = trim((string)$first['jr_closed']); $jcBl = (string)$first['jr_blacklisted']; $jcSusp = (string)$first['jr_suspended'];
                if ($jcClosed !== '' || ($jcBl !== '' && (fnmatch('*bl*', $jcBl) || fnmatch('*yes*', $jcBl))) || ($jcSusp !== '' && fnmatch('*susp*', $jcSusp))) continue;
            }

            $legs = array();
            $spins = 0;
            foreach ($legsRaw as $r) {
                $isReal = ($r['bid'] !== null && (int)$r['bid'] > 0 && $r['row_type'] !== null);
                $pos    = (int)$r['position'];
                $type   = $isReal ? (string)$r['row_type'] : ($pos === 1 ? 'su' : $pos . 'd');
                $cur    = ($isReal && $r['row_currency'] !== null) ? (string)$r['row_currency'] : (string)$r['pkg_currency'];
                $leg = fec_fxrate_leg(array(
                    'bid' => $isReal ? (int)$r['bid'] : 0, 'cid' => (int)$r['cid'], 'type' => $type,
                    'bonus' => $r['pct'], 'max_bonus' => $r['cap'], 'min_dep' => $r['min_dep'], 'max_dep' => $r['max_dep'],
                    'wr' => $r['wr'], 'wr_meth' => $r['wr_meth'], 'currency' => $cur, 'code' => '',
                    'spins' => (int)$r['spins'],
                    'max_cashout' => isset($r['max_cashout']) ? $r['max_cashout'] : null,
                    'max_cashout_kind' => isset($r['max_cashout_kind']) ? (string)$r['max_cashout_kind'] : '',
                ));
                if ($leg['position'] === 0) $leg['position'] = $pos;
                $legs[] = $leg;
                $spins += (int)$r['spins'];
            }
            $fx = fec_fxrate_package($legs, array('spins' => max((int)$first['spins_total'], $spins)));
            $deposits = max((int)$first['deposits'], $fx['n_legs']);
            if ($fx['n_legs'] > 0) $deposits = max($deposits, (int)$fx['legs'][$fx['n_legs'] - 1]['position']);
            if ($deposits < 2) continue;

            $out['packages_total']++;
            $out['legs_total'] += $fx['n_legs'];
            $cids[(int)$first['cid']] = true;

            if ($fx['effective_pct'] !== null && $fx['advertised_pct'] !== null && $fx['effective_pct'] > 0) {
                $out['packages_priced']++;
                $adv[]   = (float)$fx['advertised_pct'];
                $eff[]   = (float)$fx['effective_pct'];
                $ratio[] = (float)$fx['advertised_pct'] / (float)$fx['effective_pct'];
                if ($fx['effective_pct'] < 100) $out['below_100']++;
                if ($fx['effective_pct'] <= 50) $out['below_50']++;
            }
        }

        $out['casinos'] = count($cids);
        $out['live']    = true;
        if ($adv) {
            $out['median_advertised'] = $this->median($adv);
            $out['median_effective']  = $this->median($eff);
            $out['median_ratio']      = $this->median($ratio);
            $out['max_ratio']         = max($ratio);
        }

        return $out;
    }

    /** Median of a non-empty float array (average of the two middle values). */
    protected function median(array $v)
    {
        sort($v);
        $n = count($v);
        if ($n === 0) return null;
        $m = (int)floor($n / 2);
        return ($n % 2) ? $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }

    /**
     * Minimal author row by slug — used to plumb bylines into the methodology
     * page without dragging the full AuthorsModelAuthor load (which fetches
     * stat counts we don't need here). Returns null if not found.
     */
    public function getAuthorBySlug($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') return null;

        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select(array('id', 'slug', 'name', 'role', 'short_bio', 'avatar_url'))
            ->from($db->quoteName('#__casino_authors'))
            ->where($db->quoteName('slug') . ' = ' . $db->quote($slug))
            ->where('is_active = 1');
        $db->setQuery($q);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelMethodology::getAuthorBySlug(' . $slug . '): ' . $db->getErrorMsg());
            return null;
        }
        return is_array($row) ? $row : null;
    }
}
