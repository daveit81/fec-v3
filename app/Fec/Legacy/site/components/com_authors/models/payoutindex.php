<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Payout Index model — read-only data provider for the editorial page
 * /online-casinos/fast-payouts (kind='page' topic 'fast-payouts', rendered by
 * views/page/tmpl/default_fast-payouts.php).
 *
 * The page is the first cross-casino consumer of the two withdrawal-timing
 * systems that already exist on the site. It writes nothing.
 *
 *   Lane 1 — desk tests     #__fec_payout_tests (written by the FXShield
 *                           verification wizard on commit). One row per
 *                           completed staff withdrawal: declared_hours is the
 *                           worst end of the operator's stated window at test
 *                           time, measured_hours the real elapsed time.
 *   Lane 2 — player clocks  #__fec_clock_stats / #__fec_clocks / #__fec_clock_brands
 *                           (FXClock, FEC Tools No. 04). A brand's median is
 *                           public only from FXC_THRESHOLD stopped live clocks.
 *
 * Editorial rules enforced here, not in the template:
 *   - The two lanes are never merged into one number or one ranking.
 *   - Only facts leave this model: elapsed times, declared windows, dates,
 *     counts. PRG, Safety Index, tiers and confidence grades are not read.
 *   - Below-threshold medians are never exposed (FXClock honesty contract).
 *   - Brands flagged closed, suspended or blacklisted in jReviews are dropped
 *     from both lanes regardless of measured speed.
 *   - Unpublished review articles are dropped.
 *
 * PHP 5.6 web runtime: no return types, no scalar hints, no ??, no <=>.
 */
class AuthorsModelPayoutindex extends JModel
{
    /** FXClock install root (outside webroot). config.php defines FXC_THRESHOLD. */
    const FXC_ROOT = '/home/freeextr/fxclock';

    /** Spec default: a player median is public from ten stopped live clocks. */
    const DEFAULT_THRESHOLD = 10;

    /** com_content category of casino reviews (online-casinos). */
    const CASINO_CATID = 8;

    /** Running-clock tiles and latest receipts shown when no brand has a median yet. */
    const FEED_RUNNING_MAX = 6;
    const FEED_LATEST_MAX  = 3;

    /** Public path of the live register (menu item under online-casinos). */
    const INDEX_PATH = '/online-casinos/fast-payouts';

    /** Monthly editions (2.7.55): config, card output, fonts. */
    const EDITIONS_CONFIG = '/home/freeextr/conf/fec_payout_index.php';
    const CARD_DIR        = '/images/payout-index';           // under JPATH_SITE, public
    const FONT_DIR        = '/home/freeextr/fec_private/fonts';
    const FONT_DIR_SYS    = '/usr/share/fonts/dejavu';
    const COMMON_LIB      = '/components/com_bonuses/helpers/fec_common_lib.php'; // under JPATH_SITE
    const CARD_TOP        = 5;

    /**
     * Ten-clock threshold, read from the FXClock config when readable so the
     * page and the widget can never disagree. Falls back to the spec default.
     */
    public static function threshold()
    {
        static $t = null;
        if ($t !== null) return $t;
        if (!defined('FXC_THRESHOLD')) {
            $cfg = self::FXC_ROOT . '/config.php';
            if (is_readable($cfg)) {
                try { @include_once $cfg; } catch (Exception $e) {}
            }
        }
        $t = defined('FXC_THRESHOLD') ? (int)FXC_THRESHOLD : self::DEFAULT_THRESHOLD;
        if ($t < 1) $t = self::DEFAULT_THRESHOLD;
        return $t;
    }

    /**
     * Everything the template needs, in one call. Every block is independently
     * guarded so a missing FXClock table (or a lane with no data yet) degrades
     * to an empty list, never to a broken page.
     */
    public static function data()
    {
        $threshold = self::threshold();

        $desk       = self::deskRows();
        $players    = self::playerRows($threshold);
        $collecting = self::collectingRows($threshold);
        $feed       = self::feed($threshold);

        $deskTests = 0;
        $latest    = '';
        foreach ($desk as $r) {
            $deskTests += (int)$r['tests_count'];
            if ($r['requested_date'] !== '' && strcmp($r['requested_date'], $latest) > 0) {
                $latest = $r['requested_date'];
            }
        }

        // 2.7.89: "Register data" is the latest DATA POINT - the newest desk
        // test request or the newest player receipt. The clock statistics
        // rebuild (stats_computed_at) used to count too, so the date moved
        // with the cron and read as today's date while the last fact was
        // days old. The rebuild stays in the feed for the record.
        $dataAsOf = $latest;
        $lastReceipt = '';
        foreach ($players as $r) {
            if (!empty($r['last_received_at'])) {
                $d = substr((string)$r['last_received_at'], 0, 10);
                if (strcmp($d, $lastReceipt) > 0) $lastReceipt = $d;
            }
        }
        foreach ($collecting as $r) {
            if (!empty($r['last']['stopped_at'])) {
                $d = substr((string)$r['last']['stopped_at'], 0, 10);
                if (strcmp($d, $lastReceipt) > 0) $lastReceipt = $d;
            }
        }
        if ($lastReceipt !== '' && strcmp($lastReceipt, $dataAsOf) > 0) $dataAsOf = $lastReceipt;

        return array(
            'threshold'        => $threshold,
            'desk'             => $desk,
            'desk_tests'       => $deskTests,
            'desk_casinos'     => count($desk),
            'desk_latest_date' => $latest,
            'players'          => $players,
            'collecting'       => $collecting,
            'feed'             => $feed,
            'data_as_of'       => $dataAsOf,
        );
    }

    // ---------------------------------------------------------------------
    // Lane 1 — desk-tested withdrawals
    // ---------------------------------------------------------------------

    /**
     * One row per casino with at least one completed staff test, ranked by the
     * measured time of its most recent test (fastest first). Every test the
     * casino has is kept under 'tests' so the template can show the count and
     * the median when there is more than one.
     *
     * Row shape:
     *   casino_id, title, url, measured_hours, declared_hours, delta_hours,
     *   requested_date (Y-m-d, request date when the linked event has it,
     *   else the commit date), test_date, tests_count, median_hours (null
     *   when tests_count = 1), report_url ('' when no first-party report
     *   exists), tests (array of the raw rows, newest first)
     */
    public static function deskRows()
    {
        $db   = JFactory::getDbo();
        $rows = array();
        try {
            $db->setQuery(
                "SELECT t.id, t.casino_id, t.test_date, t.declared_hours, t.measured_hours,
                        e.event_date AS requested_date,
                        c.title, c.state, c.catid
                   FROM #__fec_payout_tests t
                   LEFT JOIN #__fec_safety_events e ON e.id = t.event_id
                   LEFT JOIN #__content c ON c.id = t.casino_id
                  WHERE t.source = 'staff'
                    AND t.measured_hours IS NOT NULL
                  ORDER BY t.casino_id ASC, t.test_date DESC, t.id DESC"
            );
            $r = $db->loadAssocList();
            if (is_array($r)) $rows = $r;
        } catch (Exception $e) {
            return array();
        }
        if (empty($rows)) return array();

        // Group by casino, newest test first (query order guarantees it).
        $byCasino = array();
        foreach ($rows as $r) {
            $cid = (int)$r['casino_id'];
            if ($cid <= 0) continue;
            // Unpublished or missing review article: nothing to link to, skip.
            if (!isset($r['state']) || (int)$r['state'] !== 1) continue;
            if (!isset($byCasino[$cid])) $byCasino[$cid] = array();
            $byCasino[$cid][] = $r;
        }
        if (empty($byCasino)) return array();

        $ids      = array_keys($byCasino);
        $excluded = self::excludedCasinos($ids);
        $fpCounts = self::firstPartyCounts($ids);

        $out = array();
        foreach ($byCasino as $cid => $tests) {
            if (isset($excluded[$cid])) continue;

            $head     = $tests[0];
            $measured = (float)$head['measured_hours'];
            $declared = (float)$head['declared_hours'];

            $title = isset($head['title']) ? trim((string)$head['title']) : '';
            if ($title === '') $title = self::jreviewsTitle($cid);
            if ($title === '') $title = 'Casino #' . $cid;

            $reqDate = '';
            if (!empty($head['requested_date']) && $head['requested_date'] !== '0000-00-00') {
                $reqDate = (string)$head['requested_date'];
            } elseif (!empty($head['test_date']) && $head['test_date'] !== '0000-00-00') {
                $reqDate = (string)$head['test_date'];
            }

            $measuredAll = array();
            foreach ($tests as $t) $measuredAll[] = (float)$t['measured_hours'];
            sort($measuredAll);
            $median = (count($measuredAll) > 1) ? self::median($measuredAll) : null;

            $out[] = array(
                'casino_id'      => $cid,
                'title'          => $title,
                'url'            => self::casinoUrl($cid),
                'measured_hours' => $measured,
                'declared_hours' => $declared,
                'delta_hours'    => $measured - $declared,
                'requested_date' => $reqDate,
                'test_date'      => isset($head['test_date']) ? (string)$head['test_date'] : '',
                'tests_count'    => count($tests),
                'median_hours'   => $median,
                'report_url'     => (isset($fpCounts[$cid]) && $fpCounts[$cid] > 0) ? self::reportUrl($cid) : '',
                'tests'          => $tests,
            );
        }

        // Fastest measured first; ties broken by the smaller gap to declared.
        usort($out, array('AuthorsModelPayoutindex', 'cmpDesk'));
        return $out;
    }

    public static function cmpDesk($a, $b)
    {
        if ($a['measured_hours'] == $b['measured_hours']) {
            if ($a['delta_hours'] == $b['delta_hours']) return strcasecmp($a['title'], $b['title']);
            return ($a['delta_hours'] < $b['delta_hours']) ? -1 : 1;
        }
        return ($a['measured_hours'] < $b['measured_hours']) ? -1 : 1;
    }

    /**
     * Casinos flagged closed, suspended or blacklisted in jReviews. Same tests
     * the FXClock bulk-enable used (jr_closed non-empty; jr_blacklisted
     * containing "bl" or "yes"; jr_suspended containing "susp"). Returns a
     * set keyed by contentid. Empty on any error, so a schema change never
     * blanks the page — it only stops filtering.
     */
    protected static function excludedCasinos($ids)
    {
        $out = array();
        if (empty($ids)) return $out;
        $db = JFactory::getDbo();
        try {
            $db->setQuery(
                "SELECT contentid, jr_closed, jr_blacklisted, jr_suspended
                   FROM #__jreviews_content
                  WHERE contentid IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
            $rows = $db->loadAssocList();
            if (!is_array($rows)) return $out;
            foreach ($rows as $r) {
                $closed = isset($r['jr_closed']) ? trim((string)$r['jr_closed']) : '';
                $bl     = isset($r['jr_blacklisted']) ? strtolower((string)$r['jr_blacklisted']) : '';
                $susp   = isset($r['jr_suspended']) ? strtolower((string)$r['jr_suspended']) : '';
                $flag = false;
                if ($closed !== '' && $closed !== '0') $flag = true;
                if (strpos($bl, 'bl') !== false || strpos($bl, 'yes') !== false) $flag = true;
                if (strpos($susp, 'susp') !== false) $flag = true;
                if ($flag) $out[(int)$r['contentid']] = true;
            }
        } catch (Exception $e) {}
        return $out;
    }

    /**
     * 2.7.89: FXShield tier and index per casino, for the badge beside the
     * name in the desk table - speed is one fact, the tier is the safety
     * assessment, and the row should carry both. Map casino_id => array
     * ('score' => int, 'tier' => string); casinos without a score are absent.
     */
    public static function safetyBadges($ids)
    {
        $out = array();
        if (empty($ids)) return $out;
        $db = JFactory::getDbo();
        try {
            $db->setQuery(
                "SELECT casino_id, si_score, si_tier
                   FROM #__fec_safety_scores
                  WHERE casino_id IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
            $rows = $db->loadAssocList();
            if (!is_array($rows)) return $out;
            foreach ($rows as $r) {
                $tier = isset($r['si_tier']) ? trim((string)$r['si_tier']) : '';
                if ($tier === '' || $r['si_score'] === null || $r['si_score'] === '') continue;
                $out[(int)$r['casino_id']] = array('score' => (int)$r['si_score'], 'tier' => $tier);
            }
        } catch (Exception $e) {}
        return $out;
    }

    /**
     * Number of first-party verified baseline fields per casino — the same
     * gate views/fxreport uses to decide whether a verification report exists.
     */
    protected static function firstPartyCounts($ids)
    {
        $out = array();
        if (empty($ids)) return $out;
        $db = JFactory::getDbo();
        try {
            $db->setQuery(
                "SELECT casino_id, SUM(verified_first_party = 1) AS fp
                   FROM #__fec_safety_baseline
                  WHERE casino_id IN (" . implode(',', array_map('intval', $ids)) . ")
                  GROUP BY casino_id"
            );
            $rows = $db->loadAssocList();
            if (is_array($rows)) {
                foreach ($rows as $r) $out[(int)$r['casino_id']] = (int)$r['fp'];
            }
        } catch (Exception $e) {}
        return $out;
    }

    protected static function jreviewsTitle($cid)
    {
        $db = JFactory::getDbo();
        try {
            $db->setQuery("SELECT title FROM #__jreviews_content WHERE contentid = " . (int)$cid, 0, 1);
            $t = (string)$db->loadResult();
            return trim($t);
        } catch (Exception $e) {
            return '';
        }
    }

    // ---------------------------------------------------------------------
    // Lane 2 — player-timed withdrawals (FXClock)
    // ---------------------------------------------------------------------

    /**
     * Enabled FXClock brands whose stopped-clock count has reached the public
     * threshold, fastest median first. Empty until the first brand gets there.
     *
     * Row shape:
     *   brand_slug, casino_id, title, url, median_min, p25_min, p75_min,
     *   stopped_count, running_count, last_received_at (Y-m-d H:i:s UTC or '')
     */
    public static function playerRows($threshold)
    {
        $db   = JFactory::getDbo();
        $rows = array();
        try {
            $db->setQuery(
                "SELECT s.brand_slug, s.stopped_count, s.median_min, s.p25_min, s.p75_min,
                        s.running_count, s.computed_at,
                        b.casino_id, b.page_path,
                        c.title, c.state,
                        (SELECT MAX(k.stopped_at) FROM #__fec_clocks k
                          WHERE k.brand_slug = s.brand_slug AND k.kind = 'live'
                            AND k.status = 'received' AND k.is_hidden = 0) AS last_received_at
                   FROM #__fec_clock_stats s
                   JOIN #__fec_clock_brands b ON b.brand_slug = s.brand_slug AND b.enabled = 1
                   LEFT JOIN #__content c ON c.id = b.casino_id
                  WHERE s.stopped_count >= " . (int)$threshold . "
                    AND s.median_min IS NOT NULL
                  ORDER BY s.median_min ASC, s.stopped_count DESC"
            );
            $r = $db->loadAssocList();
            if (is_array($r)) $rows = $r;
        } catch (Exception $e) {
            return array();
        }
        if (empty($rows)) return array();

        $ids = array();
        foreach ($rows as $r) if ((int)$r['casino_id'] > 0) $ids[] = (int)$r['casino_id'];
        $excluded = self::excludedCasinos($ids);

        $out = array();
        foreach ($rows as $r) {
            $cid = (int)$r['casino_id'];
            if ($cid > 0 && isset($excluded[$cid])) continue;
            if ($cid > 0 && isset($r['state']) && (int)$r['state'] !== 1) continue;
            $title = isset($r['title']) ? trim((string)$r['title']) : '';
            if ($title === '') $title = ucwords(str_replace('-', ' ', (string)$r['brand_slug']));
            $out[] = array(
                'brand_slug'       => (string)$r['brand_slug'],
                'casino_id'        => $cid,
                'title'            => $title,
                'url'              => ($cid > 0) ? self::casinoUrl($cid) : self::pagePathUrl($r['page_path']),
                'median_min'       => (int)$r['median_min'],
                'p25_min'          => ($r['p25_min'] !== null) ? (int)$r['p25_min'] : null,
                'p75_min'          => ($r['p75_min'] !== null) ? (int)$r['p75_min'] : null,
                'stopped_count'    => (int)$r['stopped_count'],
                'running_count'    => (int)$r['running_count'],
                'last_received_at' => !empty($r['last_received_at']) ? (string)$r['last_received_at'] : '',
            );
        }
        return $out;
    }

    /**
     * Brands still collecting clocks: at least one stopped live clock, fewer
     * than the threshold. Individual receipts are public facts (they already
     * show on the casino page and the home band); only the aggregate is
     * withheld until the threshold, so this table shows counts and the last
     * receipt and never a median, a mean or a bar. Ordered by clocks
     * collected, then recency — never by time, which would be a ranking in
     * disguise at n = 1.
     *
     * Row shape:
     *   brand_slug, casino_id, title, url, stopped_count, running_count,
     *   last (array: duration_min, grade, stopped_at) or null
     */
    public static function collectingRows($threshold)
    {
        $db   = JFactory::getDbo();
        $rows = array();
        try {
            $db->setQuery(
                "SELECT k.brand_slug, b.casino_id, b.page_path, c.title, c.state,
                        SUM(k.kind = 'live' AND k.status = 'received' AND k.is_hidden = 0 AND k.duration_min IS NOT NULL) AS stopped_n,
                        SUM(k.kind = 'live' AND k.status = 'running'  AND k.is_hidden = 0) AS running_n,
                        MAX(CASE WHEN k.kind = 'live' AND k.status = 'received' AND k.is_hidden = 0 THEN k.stopped_at END) AS last_stopped_at
                   FROM #__fec_clocks k
                   JOIN #__fec_clock_brands b ON b.brand_slug = k.brand_slug AND b.enabled = 1
                   LEFT JOIN #__content c ON c.id = b.casino_id
                  GROUP BY k.brand_slug, b.casino_id, b.page_path, c.title, c.state
                 HAVING (stopped_n >= 1 OR running_n >= 1) AND stopped_n < " . (int)$threshold . "
                  ORDER BY stopped_n DESC, running_n DESC, last_stopped_at DESC"
            );
            // 2.7.89: a brand whose first clock is still running sits in this
            // table too (0 of N, 1 running) instead of in a band of its own.
            $r = $db->loadAssocList();
            if (is_array($r)) $rows = $r;
        } catch (Exception $e) {
            return array();
        }
        if (empty($rows)) return array();

        $ids = array(); $slugs = array();
        foreach ($rows as $r) {
            if ((int)$r['casino_id'] > 0) $ids[] = (int)$r['casino_id'];
            $slugs[] = $db->quote((string)$r['brand_slug']);
        }
        $excluded = self::excludedCasinos($ids);

        // Latest receipt per brand (duration + grade): one query, first row per brand wins.
        $last = array();
        try {
            $db->setQuery(
                "SELECT brand_slug, duration_min, grade, stopped_at
                   FROM #__fec_clocks
                  WHERE kind = 'live' AND status = 'received' AND is_hidden = 0 AND duration_min IS NOT NULL
                    AND brand_slug IN (" . implode(',', $slugs) . ")
                  ORDER BY stopped_at DESC, id DESC"
            );
            $lr = $db->loadAssocList();
            if (is_array($lr)) {
                foreach ($lr as $x) {
                    $bs = (string)$x['brand_slug'];
                    if (isset($last[$bs])) continue;
                    $last[$bs] = array(
                        'duration_min' => (int)$x['duration_min'],
                        'grade'        => strtoupper((string)$x['grade']),
                        'stopped_at'   => (string)$x['stopped_at'],
                    );
                }
            }
        } catch (Exception $e) {}

        $out = array();
        foreach ($rows as $r) {
            $cid = (int)$r['casino_id'];
            if ($cid > 0 && isset($excluded[$cid])) continue;
            if ($cid > 0 && isset($r['state']) && $r['state'] !== null && (int)$r['state'] !== 1) continue;
            $slug  = (string)$r['brand_slug'];
            $title = isset($r['title']) ? trim((string)$r['title']) : '';
            if ($title === '') $title = ucwords(str_replace('-', ' ', $slug));
            $out[] = array(
                'brand_slug'    => $slug,
                'casino_id'     => $cid,
                'title'         => $title,
                'url'           => ($cid > 0) ? self::casinoUrl($cid) : self::pagePathUrl($r['page_path']),
                'stopped_count' => (int)$r['stopped_n'],
                'running_count' => (int)$r['running_n'],
                'last'          => isset($last[$slug]) ? $last[$slug] : null,
            );
        }
        return $out;
    }

    /**
     * Live FXClock facts for the stats strip and the running-now band:
     * mirrors fxc_home_feed() in the FXClock lib (same tables, same filters)
     * so the page and the homepage band never tell different stories.
     */
    public static function feed($threshold)
    {
        $db  = JFactory::getDbo();
        $out = array(
            'running_count'      => 0,
            'running_brands'     => 0,
            'stopped_total'      => 0,
            'brands_enabled'     => 0,
            'brands_with_median' => 0,
            'stats_computed_at'  => '',
            'running'            => array(),
            'latest'             => array(),
        );
        try {
            $db->setQuery(
                "SELECT SUM(k.kind = 'live' AND k.status = 'running'  AND k.is_hidden = 0) AS running_count,
                        COUNT(DISTINCT CASE WHEN k.kind = 'live' AND k.status = 'running' AND k.is_hidden = 0 THEN k.brand_slug END) AS running_brands,
                        SUM(k.kind = 'live' AND k.status = 'received' AND k.is_hidden = 0) AS stopped_total
                   FROM #__fec_clocks k
                   JOIN #__fec_clock_brands b ON b.brand_slug = k.brand_slug AND b.enabled = 1"
            );
            $c = $db->loadAssoc();
            if (is_array($c)) {
                $out['running_count']  = (int)$c['running_count'];
                $out['running_brands'] = (int)$c['running_brands'];
                $out['stopped_total']  = (int)$c['stopped_total'];
            }
            $db->setQuery("SELECT COUNT(*) FROM #__fec_clock_brands WHERE enabled = 1");
            $out['brands_enabled'] = (int)$db->loadResult();

            $db->setQuery(
                "SELECT COUNT(*) AS n, MAX(s.computed_at) AS computed_at
                   FROM #__fec_clock_stats s
                   JOIN #__fec_clock_brands b ON b.brand_slug = s.brand_slug AND b.enabled = 1
                  WHERE s.stopped_count >= " . (int)$threshold
            );
            $m = $db->loadAssoc();
            if (is_array($m)) {
                $out['brands_with_median'] = (int)$m['n'];
            }
            $db->setQuery(
                "SELECT MAX(s.computed_at) FROM #__fec_clock_stats s
                   JOIN #__fec_clock_brands b ON b.brand_slug = s.brand_slug AND b.enabled = 1"
            );
            $ca = (string)$db->loadResult();
            if ($ca !== '' && $ca !== '0000-00-00 00:00:00') $out['stats_computed_at'] = $ca;

            // Running now, grouped by brand (oldest clock first inside the group).
            $db->setQuery(
                "SELECT k.brand_slug, COUNT(*) AS n, MIN(k.started_at) AS oldest, b.casino_id, b.page_path, c.title
                   FROM #__fec_clocks k
                   JOIN #__fec_clock_brands b ON b.brand_slug = k.brand_slug AND b.enabled = 1
                   LEFT JOIN #__content c ON c.id = b.casino_id
                  WHERE k.kind = 'live' AND k.status = 'running' AND k.is_hidden = 0
                  GROUP BY k.brand_slug, b.casino_id, b.page_path, c.title
                  ORDER BY n DESC, oldest ASC", 0, self::FEED_RUNNING_MAX
            );
            $run = $db->loadAssocList();
            if (is_array($run)) {
                // 2.7.89: the same exclusion the two tables apply - a brand the
                // desk has closed, suspended or blacklisted does not appear as
                // "running now", and its clocks leave the count.
                $runIds = array();
                foreach ($run as $r) if ((int)$r['casino_id'] > 0) $runIds[] = (int)$r['casino_id'];
                $runExcluded = self::excludedCasinos($runIds);
                foreach ($run as $r) {
                    $cid = (int)$r['casino_id'];
                    if ($cid > 0 && isset($runExcluded[$cid])) {
                        $out['running_count'] = max(0, (int)$out['running_count'] - (int)$r['n']);
                        continue;
                    }
                    $out['running'][] = array(
                        'brand_slug' => (string)$r['brand_slug'],
                        'title'      => (!empty($r['title'])) ? (string)$r['title'] : ucwords(str_replace('-', ' ', (string)$r['brand_slug'])),
                        'url'        => ($cid > 0) ? self::casinoUrl($cid) : self::pagePathUrl($r['page_path']),
                        'count'      => (int)$r['n'],
                        'oldest'     => (string)$r['oldest'],
                    );
                }
            }

            // Latest receipts (live clocks stopped with the money received).
            $db->setQuery(
                "SELECT k.public_id, k.brand_slug, k.duration_min, k.grade, k.method, k.stopped_at, b.casino_id, b.page_path, c.title
                   FROM #__fec_clocks k
                   JOIN #__fec_clock_brands b ON b.brand_slug = k.brand_slug AND b.enabled = 1
                   LEFT JOIN #__content c ON c.id = b.casino_id
                  WHERE k.kind = 'live' AND k.status = 'received' AND k.is_hidden = 0 AND k.duration_min IS NOT NULL
                  ORDER BY k.stopped_at DESC", 0, self::FEED_LATEST_MAX
            );
            $lat = $db->loadAssocList();
            if (is_array($lat)) {
                foreach ($lat as $r) {
                    $cid = (int)$r['casino_id'];
                    $out['latest'][] = array(
                        'public_id'    => (string)$r['public_id'],
                        'brand_slug'   => (string)$r['brand_slug'],
                        'title'        => (!empty($r['title'])) ? (string)$r['title'] : ucwords(str_replace('-', ' ', (string)$r['brand_slug'])),
                        'url'          => ($cid > 0) ? self::casinoUrl($cid) : self::pagePathUrl($r['page_path']),
                        'duration_min' => (int)$r['duration_min'],
                        'grade'        => (string)$r['grade'],
                        'method'       => (string)$r['method'],
                        'stopped_at'   => (string)$r['stopped_at'],
                    );
                }
            }
        } catch (Exception $e) {
            // FXClock tables absent or unreadable: lane 2 simply stays empty.
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // URLs
    // ---------------------------------------------------------------------

    /** Casino review URL — same com_content route the slot view already uses. */
    public static function casinoUrl($cid)
    {
        return JRoute::_('index.php?option=com_content&view=article&id=' . (int)$cid
            . '&catid=' . self::CASINO_CATID . '&lang=en');
    }

    /** FXShield verification report. Deliberately non-SEF (see views/fxreport). */
    public static function reportUrl($cid)
    {
        return JUri::base(true) . '/index.php?option=com_authors&view=fxreport&casino_id=' . (int)$cid;
    }

    protected static function pagePathUrl($path)
    {
        $path = trim((string)$path);
        if ($path === '') return JUri::base(true) . '/';
        return JUri::base(true) . '/' . ltrim($path, '/');
    }

    // ---------------------------------------------------------------------
    // Formatting (shared by both lanes so the two tables read the same way)
    // ---------------------------------------------------------------------

    /**
     * Minutes → compact duration: "42m", "8h 06m", "2d 3h".
     *
     * $mode decides the unit so two numbers on the same row never mix units
     * around a boundary (47h 54m next to 2d 0h reads like different things):
     *   'auto'  — days from 72h up (single values: medians, receipts)
     *   'hours' — never days
     *   'days'  — days from 24h up
     * Use rowMode() to pick one mode for all values of a row.
     */
    public static function fmtMinutes($min, $mode = 'auto')
    {
        $min = (int)round((float)$min);
        if ($min < 0) $min = 0;
        if ($min < 60) return $min . 'm';
        $h = (int)floor($min / 60);
        $m = $min % 60;
        $dayFrom = ($mode === 'days') ? 24 : (($mode === 'hours') ? PHP_INT_MAX : 72);
        if ($h < $dayFrom) return $h . 'h ' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . 'm';
        $d  = (int)floor($h / 24);
        $hr = $h % 24;
        return $d . 'd ' . $hr . 'h';
    }

    public static function fmtHours($hours, $mode = 'auto')
    {
        return self::fmtMinutes((float)$hours * 60.0, $mode);
    }

    /**
     * One unit for a whole row: days only when every value on the row is at
     * least 48h, otherwise hours. Pass the row's values in hours.
     */
    public static function rowMode($hoursList)
    {
        $min = null;
        foreach ((array)$hoursList as $v) {
            if ($v === null) continue;
            $v = (float)$v;
            if ($min === null || $v < $min) $min = $v;
        }
        if ($min === null) return 'auto';
        return ($min >= 48.0) ? 'days' : 'hours';
    }

    /**
     * Signed gap to declared: "+6h 06m" (slower than declared), "-1h 30m"
     * (faster), "on time" when measured equals declared to the minute.
     * 2.7.89: the old +/-6 minute tolerance printed "on time" for a 54m
     * result against 1h and "-12m" for 48m against the same 1h - a rule
     * the reader could not see. The register logs to the minute; so does
     * this column.
     */
    public static function fmtDelta($hours)
    {
        $min = (int)round((float)$hours * 60.0);
        if ($min === 0) return 'on time';
        $sign = ($min > 0) ? '+' : '−';
        return $sign . self::fmtMinutes(abs($min));
    }

    public static function fmtDate($ymd, $long = false)
    {
        $ymd = trim((string)$ymd);
        if ($ymd === '' || $ymd === '0000-00-00') return '';
        $ts = strtotime(substr($ymd, 0, 10));
        if (!$ts) return $ymd;
        return date($long ? 'j F Y' : 'j M Y', $ts);
    }

    protected static function median($sorted)
    {
        $n = count($sorted);
        if ($n === 0) return null;
        $mid = (int)floor($n / 2);
        if ($n % 2 === 1) return (float)$sorted[$mid];
        return ((float)$sorted[$mid - 1] + (float)$sorted[$mid]) / 2.0;
    }

    // =====================================================================
    // Monthly editions (2.7.55)
    // =====================================================================

    /** Edition page path. Hardcoded on purpose: the menu anchor is fixed. */
    public static function editionPath($ym)
    {
        return self::INDEX_PATH . '/' . preg_replace('/[^0-9-]/', '', (string)$ym);
    }

    /** "October 2026" */
    public static function editionMonthLabel($ym)
    {
        $ts = strtotime(substr((string)$ym, 0, 7) . '-01');
        return $ts ? date('F Y', $ts) : (string)$ym;
    }

    /** "Edition 01" */
    public static function editionNumberLabel($number)
    {
        return 'Edition ' . str_pad((string)(int)$number, 2, '0', STR_PAD_LEFT);
    }

    /** All editions, newest first (light columns, no payload). */
    public static function editions()
    {
        $db = JFactory::getDbo();
        try {
            $db->setQuery(
                "SELECT id, edition, number, published_at, data_as_of, desk_tests, desk_casinos,
                        stopped_total, brands_with_median, card_path
                   FROM #__fec_payout_index_editions
                  ORDER BY edition DESC"
            );
            $rows = $db->loadAssocList();
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /** One edition with payload and diff decoded, or null. */
    public static function edition($ym)
    {
        $ym = substr(preg_replace('/[^0-9-]/', '', (string)$ym), 0, 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return null;
        $db = JFactory::getDbo();
        try {
            $db->setQuery("SELECT * FROM #__fec_payout_index_editions WHERE edition = " . $db->quote($ym), 0, 1);
            $row = $db->loadAssoc();
        } catch (Exception $e) {
            return null;
        }
        if (!$row) return null;
        $row['payload'] = json_decode((string)$row['payload'], true);
        $row['diff']    = ($row['diff'] !== null && $row['diff'] !== '') ? json_decode((string)$row['diff'], true) : null;
        if (!is_array($row['payload'])) return null;
        return $row;
    }

    /** Latest edition (light row) or null. */
    public static function latestEdition()
    {
        $all = self::editions();
        return !empty($all) ? $all[0] : null;
    }

    /**
     * Freeze the live register into an edition.
     *
     * @param string|null $ym     'YYYY-MM' (default: last month — the cron runs on the 1st)
     * @param bool        $force  replace an existing row for that edition
     * @param bool        $dry    compute, render the card to a temp name, insert nothing
     * @return array summary (ok, edition, number, created, card, diff, newsletter, notified, error)
     */
    public static function snapshot($ym = null, $force = false, $dry = false)
    {
        $db = JFactory::getDbo();
        $ym = ($ym !== null && $ym !== '') ? substr(preg_replace('/[^0-9-]/', '', (string)$ym), 0, 7)
                                           : date('Y-m', strtotime('first day of last month'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return array('ok' => false, 'error' => 'bad edition ' . $ym);
        }

        $existing = self::edition($ym);
        if ($existing && !$force && !$dry) {
            return array('ok' => true, 'edition' => $ym, 'number' => (int)$existing['number'], 'created' => false,
                         'note' => 'edition already exists; pass force=1 to rebuild');
        }

        // Previous edition = newest edition strictly before this one.
        $prev = null;
        foreach (self::editions() as $e) {
            if (strcmp($e['edition'], $ym) < 0) { $prev = self::edition($e['edition']); break; }
        }
        $number = $existing ? (int)$existing['number'] : ($prev ? (int)$prev['number'] + 1 : 1);

        $payload = self::buildPayload();
        $diff    = $prev ? self::diffPayloads($prev['payload'], $payload) : null;
        $now     = gmdate('Y-m-d H:i:s');

        $row = array(
            'edition'            => $ym,
            'number'             => $number,
            'published_at'       => $existing ? $existing['published_at'] : $now,
            'data_as_of'         => ($payload['data_as_of'] !== '') ? $payload['data_as_of'] : null,
            'desk_tests'         => (int)$payload['desk_tests'],
            'desk_casinos'       => (int)$payload['desk_casinos'],
            'stopped_total'      => (int)$payload['feed']['stopped_total'],
            'brands_with_median' => (int)$payload['feed']['brands_with_median'],
            'payload'            => $payload,
            'diff'               => $diff,
        );

        $card = self::renderCard($row, $dry);
        $row['card_path']       = ($card['ok'] && !$dry) ? $card['path'] : ($existing ? $existing['card_path'] : null);
        $row['newsletter_text'] = self::newsletterText($row);

        $out = array(
            'ok'         => true,
            'edition'    => $ym,
            'number'     => $number,
            'created'    => false,
            'dry'        => (bool)$dry,
            'card'       => $card,
            'diff'       => $diff,
            'newsletter' => $row['newsletter_text'],
            'page'       => self::editionPath($ym),
            'desk_tests' => $row['desk_tests'],
            'desk_casinos' => $row['desk_casinos'],
        );
        if ($dry) return $out;

        try {
            if ($existing) {
                $db->setQuery("DELETE FROM #__fec_payout_index_editions WHERE id = " . (int)$existing['id']);
                $db->query();
            }
            $cols = array('edition', 'number', 'published_at', 'data_as_of', 'desk_tests', 'desk_casinos',
                          'stopped_total', 'brands_with_median', 'payload', 'diff', 'card_path',
                          'newsletter_text', 'created_at');
            $vals = array(
                $db->quote($row['edition']), (int)$row['number'], $db->quote($row['published_at']),
                ($row['data_as_of'] !== null) ? $db->quote($row['data_as_of']) : 'NULL',
                (int)$row['desk_tests'], (int)$row['desk_casinos'], (int)$row['stopped_total'], (int)$row['brands_with_median'],
                $db->quote(json_encode($payload)),
                ($diff !== null) ? $db->quote(json_encode($diff)) : 'NULL',
                ($row['card_path'] !== null) ? $db->quote($row['card_path']) : 'NULL',
                $db->quote($row['newsletter_text']),
                $db->quote($now),
            );
            $db->setQuery("INSERT INTO #__fec_payout_index_editions (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")");
            $db->query();
            $out['created'] = true;
        } catch (Exception $e) {
            $out['ok'] = false; $out['error'] = 'insert failed: ' . $e->getMessage();
            return $out;
        }

        // 2.7.62: sh404SEF decodes only URLs it knows and never anchors ours to
        // the menu item, so the edition path is registered here, before the bot
        // and the newsletter link to it. No-op without sh404SEF.
        $out['sef'] = self::registerSef($ym);

        $out['notified'] = self::notifyDesk($row, $out);
        return $out;
    }

    public static function registerSef($ym)
    {
        $h = JPATH_SITE . '/components/com_authors/helpers/sef.php';
        if (!class_exists('AuthorsHelperSef') && is_readable($h)) require_once $h;
        if (!class_exists('AuthorsHelperSef')) return 'skipped: helper missing';
        return AuthorsHelperSef::registerEdition(self::editionPath($ym), 'fast-payouts', $ym);
    }

    /** The live register, trimmed to what an edition keeps (no raw test rows). */
    public static function buildPayload()
    {
        $d = self::data();
        $desk = array();
        foreach ($d['desk'] as $r) {
            unset($r['tests']);
            $desk[] = $r;
        }
        $feed = $d['feed'];
        unset($feed['running'], $feed['latest']);
        return array(
            'threshold'    => (int)$d['threshold'],
            'desk'         => $desk,
            'desk_tests'   => (int)$d['desk_tests'],
            'desk_casinos' => (int)$d['desk_casinos'],
            'players'      => $d['players'],
            'collecting'   => $d['collecting'],
            'feed'         => $feed,
            'data_as_of'   => (string)$d['data_as_of'],
            'frozen_at'    => gmdate('Y-m-d H:i:s'),
        );
    }

    /**
     * What changed between two payloads. Desk lane: new entries, position
     * moves, retests (tests_count grew), departures. Player lane: brands
     * newly at the median. Totals as deltas.
     */
    public static function diffPayloads($prev, $cur)
    {
        $pIdx = array(); $i = 0;
        foreach ((array)$prev['desk'] as $r) { $i++; $pIdx[(int)$r['casino_id']] = array('rank' => $i, 'row' => $r); }
        $cIdx = array(); $i = 0;
        foreach ((array)$cur['desk'] as $r)  { $i++; $cIdx[(int)$r['casino_id']] = array('rank' => $i, 'row' => $r); }

        $new = array(); $moved = array(); $retested = array(); $left = array();
        foreach ($cIdx as $cid => $c) {
            if (!isset($pIdx[$cid])) {
                $new[] = array('title' => $c['row']['title'], 'rank' => $c['rank'], 'measured_hours' => $c['row']['measured_hours'], 'declared_hours' => $c['row']['declared_hours']);
                continue;
            }
            $p = $pIdx[$cid];
            if ((int)$c['row']['tests_count'] > (int)$p['row']['tests_count']) {
                $retested[] = array('title' => $c['row']['title'], 'from_hours' => $p['row']['measured_hours'], 'to_hours' => $c['row']['measured_hours']);
            }
            if ($c['rank'] !== $p['rank']) {
                $moved[] = array('title' => $c['row']['title'], 'from' => $p['rank'], 'to' => $c['rank']);
            }
        }
        foreach ($pIdx as $cid => $p) {
            if (!isset($cIdx[$cid])) $left[] = array('title' => $p['row']['title'], 'rank' => $p['rank']);
        }

        $pMed = array(); foreach ((array)$prev['players'] as $r) $pMed[$r['brand_slug']] = true;
        $playersNew = array();
        foreach ((array)$cur['players'] as $r) {
            if (!isset($pMed[$r['brand_slug']])) $playersNew[] = array('title' => $r['title'], 'median_min' => $r['median_min'], 'stopped_count' => $r['stopped_count']);
        }

        return array(
            'prev_edition'      => isset($prev['frozen_at']) ? substr($prev['frozen_at'], 0, 10) : '',
            'new_entries'       => $new,
            'moved'             => $moved,
            'retested'          => $retested,
            'left'              => $left,
            'players_new'       => $playersNew,
            'desk_tests_delta'  => (int)$cur['desk_tests'] - (int)$prev['desk_tests'],
            'stopped_delta'     => (int)$cur['feed']['stopped_total'] - (int)$prev['feed']['stopped_total'],
            'median_brands_delta' => (int)$cur['feed']['brands_with_median'] - (int)$prev['feed']['brands_with_median'],
        );
    }

    /** Plain-text paragraph for the newsletter, built from the frozen data. */
    public static function newsletterText($row)
    {
        $p = $row['payload']; $d = $row['diff'];
        $n = (int)$row['desk_tests']; $c = (int)$row['desk_casinos'];
        $s = 'FEC Payout Index, ' . self::editionNumberLabel($row['number']) . ' (' . self::editionMonthLabel($row['edition']) . '). ';
        $s .= $n . ' withdrawal' . ($n === 1 ? '' : 's') . ' timed by the desk across ' . $c . ' casino' . ($c === 1 ? '' : 's') . ', from request to money received, next to the hours each operator declared.';
        if (!empty($p['desk'])) {
            $f = $p['desk'][0];
            $s .= ' Fastest: ' . $f['title'] . ', ' . self::fmtHours($f['measured_hours']) . ' against a declared ' . self::fmtHours($f['declared_hours']) . '.';
        }
        if ($d === null) {
            $s .= ' This is the first edition; the register grows one completed withdrawal at a time.';
        } else {
            $bits = array();
            if (!empty($d['new_entries'])) {
                $names = array(); foreach ($d['new_entries'] as $x) $names[] = $x['title'] . ' (' . self::fmtHours($x['measured_hours']) . ')';
                $bits[] = 'new this month: ' . implode(', ', $names);
            }
            if (!empty($d['retested'])) {
                $names = array(); foreach ($d['retested'] as $x) $names[] = $x['title'] . ' (' . self::fmtHours($x['from_hours']) . ' to ' . self::fmtHours($x['to_hours']) . ')';
                $bits[] = 'retested: ' . implode(', ', $names);
            }
            if (!empty($d['moved'])) $bits[] = count($d['moved']) . ' position change' . (count($d['moved']) === 1 ? '' : 's');
            if (!empty($d['left']))  $bits[] = count($d['left']) . ' brand' . (count($d['left']) === 1 ? '' : 's') . ' removed';
            $s .= empty($bits) ? ' No change in the desk table since the last edition.' : ' Since the last edition, ' . implode('; ', $bits) . '.';
        }
        $st = (int)$row['stopped_total']; $bm = (int)$row['brands_with_median']; $th = (int)$p['threshold'];
        if ($bm > 0) {
            $s .= ' Player clocks: ' . $bm . ' brand' . ($bm === 1 ? ' has' : 's have') . ' a published FXClock median from ' . $st . ' stopped clocks.';
        } elseif ($st > 0) {
            $s .= ' Player clocks: ' . $st . ' stopped so far; no brand has reached the ' . $th . '-clock median yet.';
        }
        $s .= ' Full table: https://www.freeextrachips.com' . self::editionPath($row['edition']);
        return $s;
    }

    // ---- Card (1200 x 630 PNG, GD) ------------------------------------------

    /**
     * Font resolver with a fallback chain: site fonts in FONT_DIR first (name
     * heuristics), then DejaVu from the usual system dirs, then any bold /
     * any regular TTF found, so the card renders with whatever the host has.
     */
    protected static function fontFile($kind)
    {
        static $cache = array();
        if (isset($cache[$kind])) return $cache[$kind];

        $pref = array(
            'serif-bold' => array('/serif.*bold/i', '/playfair.*bold/i', '/fraunces.*bold/i', '/lora.*bold/i', '/merriweather.*bold/i', '/georgia.*bold/i', '/sans.*bold/i', '/bold/i'),
            'sans-bold'  => array('/inter.*bold/i', '/sans.*bold/i', '/helvet.*bold/i', '/arial.*bold/i', '/bold/i'),
            'sans'       => array('/inter.*(regular|medium)/i', '/sans.*(regular|book)/i', '/inter\.ttf/i', '/sans\.ttf/i', '/^(?!.*(bold|italic|oblique)).*$/i'),
            'mono-bold'  => array('/mono.*bold/i', '/jetbrains.*bold/i', '/code.*bold/i', '/sans.*bold/i', '/bold/i'),
            'mono'       => array('/mono.*(regular|medium)/i', '/mono\.ttf/i', '/^(?!.*(bold|italic|oblique)).*$/i'),
        );
        $sys = array(
            'serif-bold' => array('DejaVuSerif-Bold.ttf', 'DejaVuSans-Bold.ttf', 'LiberationSerif-Bold.ttf', 'LiberationSans-Bold.ttf'),
            'sans-bold'  => array('DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf'),
            'sans'       => array('DejaVuSans.ttf', 'LiberationSans-Regular.ttf'),
            'mono-bold'  => array('DejaVuSansMono-Bold.ttf', 'LiberationMono-Bold.ttf', 'DejaVuSans-Bold.ttf'),
            'mono'       => array('DejaVuSansMono.ttf', 'LiberationMono-Regular.ttf', 'DejaVuSans.ttf'),
        );
        $sysDirs = array(self::FONT_DIR_SYS, '/usr/share/fonts/truetype/dejavu', '/usr/share/fonts/dejavu-sans-fonts',
                         '/usr/share/fonts/dejavu-serif-fonts', '/usr/share/fonts/liberation', '/usr/share/fonts/TTF');

        $found = '';
        // 1. Site fonts, by name preference.
        if (is_dir(self::FONT_DIR)) {
            $files = array_merge((array)glob(self::FONT_DIR . '/*.ttf'), (array)glob(self::FONT_DIR . '/*.TTF'), (array)glob(self::FONT_DIR . '/*.otf'));
            foreach ((array)$pref[$kind] as $re) {
                foreach ($files as $f) {
                    if ($f && preg_match($re, basename($f))) { $found = $f; break 2; }
                }
            }
        }
        // 2. System fonts, by file name.
        if ($found === '') {
            foreach ((array)$sys[$kind] as $name) {
                foreach ($sysDirs as $dir) {
                    if (is_readable($dir . '/' . $name)) { $found = $dir . '/' . $name; break 2; }
                }
            }
        }
        // 3. Anything TrueType at all, bold-ish for bold kinds.
        if ($found === '') {
            $any = array();
            foreach (array_merge(array(self::FONT_DIR), $sysDirs) as $dir) {
                if (is_dir($dir)) $any = array_merge($any, (array)glob($dir . '/*.ttf'));
            }
            $wantBold = (strpos($kind, 'bold') !== false);
            foreach ($any as $f) {
                if (!$f) continue;
                if ($wantBold && !preg_match('/bold/i', basename($f))) continue;
                if (!$wantBold && preg_match('/bold|italic|oblique/i', basename($f))) continue;
                $found = $f; break;
            }
            if ($found === '' && !empty($any) && $any[0]) $found = $any[0];
        }
        $cache[$kind] = $found;
        return $found;
    }

    /**
     * Social card for an edition. Returns array(ok, path (public, relative), file, error).
     * Design: paper background, teal eyebrow, serif headline, top-5 rows with
     * measured vs declared, footer with the URL. Facts only.
     */
    public static function renderCard($row, $dry = false)
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
            return array('ok' => false, 'error' => 'GD/FreeType not available');
        }
        $fSerifB = self::fontFile('serif-bold');
        $fSansB  = self::fontFile('sans-bold');
        $fSans   = self::fontFile('sans');
        $fMonoB  = self::fontFile('mono-bold');
        if ($fSans === '') {
            return array('ok' => false, 'error' => 'no TTF fonts found in ' . self::FONT_DIR . ' or the system font dirs');
        }
        if ($fSansB === '')  $fSansB  = $fSans;
        if ($fSerifB === '') $fSerifB = $fSansB;
        if ($fMonoB === '')  $fMonoB  = $fSansB;

        $W = 1200; $H = 630;
        $im = imagecreatetruecolor($W, $H);
        $paper = imagecolorallocate($im, 253, 251, 246);
        $ink   = imagecolorallocate($im, 28, 26, 23);
        $ink2  = imagecolorallocate($im, 74, 68, 60);
        $ink3  = imagecolorallocate($im, 122, 115, 104);
        $rule  = imagecolorallocate($im, 226, 221, 210);
        $teal  = imagecolorallocate($im, 15, 111, 92);
        $tealS = imagecolorallocate($im, 227, 241, 238);
        $warn  = imagecolorallocate($im, 154, 91, 0);
        imagefilledrectangle($im, 0, 0, $W, $H, $paper);
        imagefilledrectangle($im, 0, 0, $W, 10, $teal);

        $p = $row['payload'];
        $x0 = 64;

        // Eyebrow
        $eyebrow = strtoupper('FEC Payout Index  ·  ' . self::editionNumberLabel($row['number']) . '  ·  ' . self::editionMonthLabel($row['edition']));
        imagettftext($im, 17, 0, $x0, 72, $teal, $fSansB, $eyebrow);

        // Headline — size steps down until it fits the width (fonts vary by host)
        $head = 'Fastest paying casinos, measured.';
        $hs = 44;
        while ($hs > 28) {
            $bb = imagettfbbox($hs, 0, $fSerifB, $head);
            if (($bb[2] - $bb[0]) <= ($W - 2 * $x0)) break;
            $hs -= 2;
        }
        imagettftext($im, $hs, 0, $x0 - 2, 136, $ink, $fSerifB, $head);
        $n = (int)$row['desk_tests']; $c = (int)$row['desk_casinos'];
        $sub = $n . ' withdrawal' . ($n === 1 ? '' : 's') . ' timed by FreeExtraChips staff across ' . $c . ' casino' . ($c === 1 ? '' : 's') . ', request to receipt, in UTC.';
        imagettftext($im, 18, 0, $x0, 172, $ink2, $fSans, self::fitText($fSans, 18, $sub, $W - 2 * $x0));

        // Column heads
        $yTop = 216;
        imageline($im, $x0, $yTop, $W - $x0, $yTop, $rule);
        imagettftext($im, 13, 0, $x0 + 60, $yTop + 26, $ink3, $fSansB, 'CASINO');
        $cxM = 690; $cxD = 850; $cxG = 1000;
        imagettftext($im, 13, 0, $cxM, $yTop + 26, $ink3, $fSansB, 'MEASURED');
        imagettftext($im, 13, 0, $cxD, $yTop + 26, $ink3, $fSansB, 'DECLARED');
        imagettftext($im, 13, 0, $cxG, $yTop + 26, $ink3, $fSansB, 'GAP');

        // Rows
        $rows = array_slice((array)$p['desk'], 0, self::CARD_TOP);
        $y = $yTop + 72; $i = 0;
        foreach ($rows as $r) {
            $i++;
            $mode = self::rowMode(array($r['measured_hours'], $r['declared_hours']));
            imagefilledellipse($im, $x0 + 20, $y - 10, 36, 36, $tealS);
            $bb = imagettfbbox(15, 0, $fMonoB, (string)$i);
            imagettftext($im, 15, 0, $x0 + 20 - (int)(($bb[2] - $bb[0]) / 2), $y - 3, $teal, $fMonoB, (string)$i);
            $title = self::fitText($fSansB, 22, (string)$r['title'], $cxM - ($x0 + 60) - 24);
            imagettftext($im, 22, 0, $x0 + 60, $y, $ink, $fSansB, $title);
            imagettftext($im, 23, 0, $cxM, $y, $ink, $fMonoB, self::fmtHours($r['measured_hours'], $mode));
            imagettftext($im, 19, 0, $cxD, $y, $ink2, $fMonoB, self::fmtHours($r['declared_hours'], $mode));
            $dmin = (int)round((float)$r['delta_hours'] * 60);
            $gap  = self::fmtDelta($r['delta_hours']);
            $gap  = str_replace("\xE2\x88\x92", '-', $gap); // ascii minus for fonts without U+2212
            imagettftext($im, 17, 0, $cxG, $y, ($dmin > 6) ? $warn : $teal, $fMonoB, $gap);
            $y += 62;
            if ($i < count($rows)) imageline($im, $x0 + 60, $y - 40, $W - $x0, $y - 40, $rule);
        }
        if (empty($rows)) {
            imagettftext($im, 20, 0, $x0, $yTop + 80, $ink2, $fSans, 'No completed desk tests in this edition.');
        }

        // Footer — two lines, both left-aligned, no overlap whatever the font width
        imageline($im, $x0, $H - 86, $W - $x0, $H - 86, $rule);
        imagettftext($im, 15, 0, $x0, $H - 54, $ink3, $fSans, self::fitText($fSans, 15, 'Measured, not declared. Desk tests and player clocks are never merged.', $W - 2 * $x0));
        $url = 'freeextrachips.com' . self::editionPath($row['edition']);
        imagettftext($im, 16, 0, $x0, $H - 26, $teal, $fSansB, $url);

        // Output
        $dir = JPATH_ROOT . self::CARD_DIR; // PORT: files live in the real docroot (JPATH_SITE is the code)
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            imagedestroy($im);
            return array('ok' => false, 'error' => 'card dir not writable: ' . $dir);
        }
        $name = 'edition-' . $row['edition'] . ($dry ? '-preview' : '') . '.png';
        $file = $dir . '/' . $name;
        $ok = imagepng($im, $file, 6);
        imagedestroy($im);
        if (!$ok) return array('ok' => false, 'error' => 'imagepng failed: ' . $file);
        return array('ok' => true, 'path' => self::CARD_DIR . '/' . $name, 'file' => $file);
    }

    /** Trim text with an ellipsis until it fits the pixel width. */
    protected static function fitText($font, $size, $text, $maxW)
    {
        $t = $text;
        for ($k = 0; $k < 60; $k++) {
            $bb = imagettfbbox($size, 0, $font, $t);
            if (($bb[2] - $bb[0]) <= $maxW || strlen($t) <= 3) return $t;
            $t = rtrim(substr($t, 0, -4)) . '...';
        }
        return $t;
    }

    // ---- Desk notification (Resend, via fec_common_lib when present) ----------

    protected static function notifyDesk($row, $summary)
    {
        $to = '';
        if (is_readable(self::EDITIONS_CONFIG)) {
            try { @include_once self::EDITIONS_CONFIG; } catch (Exception $e) {}
        }
        if (defined('FEC_PI_NOTIFY_TO')) $to = trim((string)FEC_PI_NOTIFY_TO);
        if ($to === '') return 'skipped: FEC_PI_NOTIFY_TO not set';
        $lib = JPATH_SITE . self::COMMON_LIB;
        if (!function_exists('fec_resend_send') && is_readable($lib)) {
            try { @include_once $lib; } catch (Exception $e) {}
        }
        if (!function_exists('fec_resend_send')) return 'skipped: fec_resend_send unavailable';

        $site = 'https://www.freeextrachips.com';
        $label = self::editionNumberLabel($row['number']) . ' · ' . self::editionMonthLabel($row['edition']);
        $lines = array(
            'FEC Payout Index — ' . $label . ' is frozen.',
            '',
            'Edition page: ' . $site . self::editionPath($row['edition']),
            'Card: ' . ($row['card_path'] ? $site . $row['card_path'] : '(not rendered: ' . (isset($summary['card']['error']) ? $summary['card']['error'] : 'unknown') . ')'),
            'Desk tests: ' . (int)$row['desk_tests'] . ' across ' . (int)$row['desk_casinos'] . ' casinos. Player clocks stopped: ' . (int)$row['stopped_total'] . '. Brands with a median: ' . (int)$row['brands_with_median'] . '.',
            '',
            'Newsletter paragraph (paste as is):',
            $row['newsletter_text'],
            '',
            'The bot announces this edition on Telegram, X and Discord at its next run.',
        );
        $plain = implode("\n", $lines);
        $html  = '<pre style="font: 14px/1.5 -apple-system, Segoe UI, sans-serif; white-space: pre-wrap;">' . htmlspecialchars($plain, ENT_QUOTES, 'UTF-8') . '</pre>';
        try {
            $r = fec_resend_send($to, 'FEC Payout Index — ' . $label . ' ready', $html, $plain,
                                 array('tags' => array(array('name' => 'kind', 'value' => 'payout-index-edition'))));
            return (is_array($r) && !empty($r['ok'])) ? 'sent to ' . $to : 'failed: ' . (is_array($r) && isset($r['error']) ? $r['error'] : 'unknown');
        } catch (Exception $e) {
            return 'failed: ' . $e->getMessage();
        }
    }
}
