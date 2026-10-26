<?php
/**
 * AuthorsModelSlot — read-only model for public slot pages.
 *
 * Powers:
 *   /slots             → AuthorsViewSlots (index, published only)
 *   /slots/<slug>      → AuthorsViewSlot  (single detail)
 *
 * Writes happen via AuthorsModelDashboard. This model only reads, and only
 * returns rows where #__slot_rtp.published = 1.
 *
 * The "active bonuses on this slot" subquery JOINs #__bonuses on gid; the
 * casino name comes from #__content.title (which jReviews uses to store the
 * casino entry name — its contentid matches #__jreviews_content.contentid
 * and the bonus.cid).
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class AuthorsModelSlot extends JModel
{
    /**
     * Single slot by slug. Returns null if not found or not published.
     * Retired slots are still returned (visible with banner + noindex) as
     * long as published=1. retired=1 published=0 means hidden entirely.
     */
    public function getBySlug($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') return null;
        $db = JFactory::getDbo();
        $sql = "
            SELECT
                rtp.id, rtp.gid, rtp.slug,
                rtp.rtp_theoretical_max, rtp.rtp_theoretical_min,
                rtp.rtp_variants_json, rtp.max_win_x, rtp.volatility,
                rtp.paylines, rtp.reels, rtp.release_year,
                rtp.primary_source_url, rtp.primary_source_archived_url,
                rtp.certificate_url, rtp.last_verified_date,
                rtp.verified_by_author_id, rtp.notes,
                rtp.published, rtp.published_at,
                rtp.retired, rtp.retired_at, rtp.retired_reason,
                rtp.updated_at,
                g.game_name, g.provider_name, g.provider_value
            FROM #__slot_rtp rtp
            INNER JOIN #__games g ON g.gid = rtp.gid
            WHERE rtp.slug = " . $db->quote($slug) . "
              AND rtp.published = 1
            LIMIT 1
        ";
        $db->setQuery($sql);
        return $db->loadObject();
    }

    /**
     * Observations for a slot — joined with casino names.
     */
    public function getObservations($gid)
    {
        $gid = (int)$gid;
        if (!$gid) return array();
        $db = JFactory::getDbo();
        $sql = "
            SELECT
                o.id, o.cid, o.rtp_observed, o.observed_date,
                o.evidence_type, o.screenshot_path, o.notes,
                c.title AS casino_name, c.alias AS casino_alias
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
     * Active bonuses on this slot — for the "bonuses available" section.
     * Supports pagination via $limit and $start params (limitstart pattern).
     *
     * Filters applied:
     *   - b.status = 'active'                      → not pending/expired/draft
     *   - jr.jr_closed != 'yes'                    → exclude bonuses from
     *                                                closed casinos (joined
     *                                                via #__jreviews_content
     *                                                on the casino cid)
     *   - LEFT JOIN so bonuses whose cid lacks a
     *     jreviews_content row dont disappear; the
     *     IS NULL OR != 'yes' guard handles those
     */
    public function getActiveBonuses($gid, $limit = 10, $start = 0)
    {
        $gid = (int)$gid;
        $limit = (int)$limit;
        $start = (int)$start;
        if (!$gid) return array();
        $db = JFactory::getDbo();
        $sql = "
            SELECT
                b.bid, b.cid, b.type, b.label, b.bonus, b.currency,
                b.max_bonus, b.wr, b.wr_meth, b.code, b.fp_time,
                b.fp_time_den, b.brand, b.exclusive,
                c.title AS casino_name, c.alias AS casino_alias
            FROM #__bonuses b
            LEFT JOIN #__content c ON c.id = b.cid
            LEFT JOIN #__jreviews_content jr ON jr.contentid = b.cid
            WHERE b.gid = " . $gid . "
              AND b.status = 'active'
              AND (jr.jr_closed IS NULL OR jr.jr_closed NOT LIKE '%yes%')
            ORDER BY b.modified DESC, b.bid DESC
            LIMIT " . $start . ", " . $limit . "
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Total count of active bonuses for this slot — needed for pagination.
     * Same filters as getActiveBonuses (must match).
     */
    public function getActiveBonusCount($gid)
    {
        $gid = (int)$gid;
        if (!$gid) return 0;
        $db = JFactory::getDbo();
        $sql = "
            SELECT COUNT(*)
            FROM #__bonuses b
            LEFT JOIN #__jreviews_content jr ON jr.contentid = b.cid
            WHERE b.gid = " . $gid . "
              AND b.status = 'active'
              AND (jr.jr_closed IS NULL OR jr.jr_closed NOT LIKE '%yes%')
        ";
        $db->setQuery($sql);
        return (int)$db->loadResult();
    }

    /**
     * Blog posts linked to this slot (2.7.72): #__fec_blog_meta.slot_gid,
     * published posts only, the ones about the slot itself first, then the
     * family (sequels, variants, jackpot stories), newest first. Empty when
     * the blog side table lacks the columns (2.7.72.sql not applied).
     */
    public function getBlogPosts($gid, $limit = 4)
    {
        $gid = (int)$gid; $limit = max(1, (int)$limit);
        if (!$gid) return array();
        require_once JPATH_COMPONENT . '/helpers/authors.php';
        $db = JFactory::getDbo();
        $null = $db->quote($db->getNullDate());
        $sql = "
            SELECT c.id, c.title, c.alias, c.catid, c.created, c.publish_up, c.images, c.created_by_alias,
                   m.kind, m.provider_name, m.slot_rel
            FROM #__fec_blog_meta m
            INNER JOIN #__content c ON c.id = m.content_id
            WHERE m.slot_gid = " . $gid . "
              AND c.state = 1
              AND c.catid = " . (int)AuthorsHelper::blogCatid() . "
              AND (c.publish_up = " . $null . " OR c.publish_up <= UTC_TIMESTAMP())
              AND (c.publish_down = " . $null . " OR c.publish_down > UTC_TIMESTAMP())
            ORDER BY (m.slot_rel = 'game') DESC, c.publish_up DESC, c.id DESC
            LIMIT " . $limit . "
        ";
        try {
            $db->setQuery($sql);
            $rows = $db->loadAssocList();
            if ($db->getErrorNum()) return array();
        } catch (Exception $e) { return array(); }
        return is_array($rows) ? $rows : array();
    }

    /** How many published posts are linked to this slot (2.7.72). */
    public function getBlogPostCount($gid)
    {
        $gid = (int)$gid;
        if (!$gid) return 0;
        require_once JPATH_COMPONENT . '/helpers/authors.php';
        $db = JFactory::getDbo();
        $null = $db->quote($db->getNullDate());
        $sql = "
            SELECT COUNT(*)
            FROM #__fec_blog_meta m
            INNER JOIN #__content c ON c.id = m.content_id
            WHERE m.slot_gid = " . $gid . "
              AND c.state = 1
              AND c.catid = " . (int)AuthorsHelper::blogCatid() . "
              AND (c.publish_up = " . $null . " OR c.publish_up <= UTC_TIMESTAMP())
              AND (c.publish_down = " . $null . " OR c.publish_down > UTC_TIMESTAMP())
        ";
        try {
            $db->setQuery($sql);
            $n = (int)$db->loadResult();
            if ($db->getErrorNum()) return 0;
        } catch (Exception $e) { return 0; }
        return $n;
    }

    /**
     * Author row for byline.
     */
    public function getAuthor($id)
    {
        $id = (int)$id;
        if (!$id) return null;
        $db = JFactory::getDbo();
        $sql = "
            SELECT id, slug, name, role, short_bio, avatar_url
            FROM #__casino_authors
            WHERE id = " . $id . "
              AND is_active = 1
            LIMIT 1
        ";
        $db->setQuery($sql);
        return $db->loadAssoc();
    }

    /**
     * Index — all published slots with summary data for the listing.
     * Provider filter is case-insensitive (the URL segment is lowercased
     * by Joomla SEF; we compare LOWER(name) on both sides).
     */
    public function getPublishedSlots($filter_provider = '')
    {
        $db = JFactory::getDbo();
        $where = array(
            "rtp.published = 1",
            "rtp.retired = 0",
            "rtp.slug IS NOT NULL",
            "rtp.slug != ''",
        );
        if ($filter_provider !== '') {
            $where[] = "LOWER(g.provider_name) = " . $db->quote(strtolower($filter_provider));
        }
        $whereSql = implode(' AND ', $where);
        // 2.7.91: the index is a database page, so the server order is
        // freshness (last verification, then publication); the reader
        // re-sorts client side. Extra columns feed the card facts, the
        // sort keys and the verified-by line.
        $sql = "
            SELECT
                rtp.id, rtp.gid, rtp.slug,
                rtp.rtp_theoretical_max, rtp.rtp_theoretical_min,
                rtp.rtp_variants_json,
                rtp.max_win_x, rtp.volatility, rtp.release_year,
                rtp.last_verified_date, rtp.published_at,
                rtp.verified_by_author_id,
                g.game_name, g.provider_name, g.provider_value,
                a.name AS author_name, a.slug AS author_slug,
                (SELECT COUNT(*) FROM #__slot_rtp_observations o WHERE o.gid = rtp.gid) AS obs_count,
                (SELECT COUNT(*) FROM #__bonuses b WHERE b.gid = rtp.gid AND b.status = 'active') AS active_bonus_count,
                (rtp.rtp_theoretical_min IS NOT NULL AND rtp.rtp_theoretical_min < rtp.rtp_theoretical_max) AS is_multi_tier
            FROM #__slot_rtp rtp
            INNER JOIN #__games g ON g.gid = rtp.gid
            LEFT JOIN #__casino_authors a ON a.id = rtp.verified_by_author_id AND a.is_active = 1
            WHERE " . $whereSql . "
            ORDER BY rtp.last_verified_date DESC, rtp.published_at DESC, g.game_name ASC
            LIMIT 200
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Distinct providers among published slots — for index page filter.
     */
    public function getProvidersWithPublishedSlots()
    {
        $db = JFactory::getDbo();
        $sql = "
            SELECT g.provider_name AS name, COUNT(*) AS cnt
            FROM #__slot_rtp rtp
            INNER JOIN #__games g ON g.gid = rtp.gid
            WHERE rtp.published = 1
              AND rtp.retired = 0
              AND g.provider_name != ''
            GROUP BY g.provider_name
            ORDER BY cnt DESC, g.provider_name ASC
        ";
        $db->setQuery($sql);
        $rows = $db->loadObjectList();
        return is_array($rows) ? $rows : array();
    }
}
