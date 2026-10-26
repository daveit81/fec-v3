<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

// Blog catid + URL helpers (V2.7.65). Guarded: the views load it too.
require_once dirname(dirname(__FILE__)) . '/helpers/authors.php';

/**
 * Authors model — listing view.
 *
 * FEC V2.1 Editorial Team migration: added joined_year to base SELECT plus
 * three derivation methods used by the new tm-* template:
 *   - getHeroStats()              : 4 site-wide counts for the hero band
 *   - getStatsForAuthor($id)      : per-author bonuses/casinos/latest-update
 *   - getRecentForAuthor($id, $n) : per-author recent N bonuses with brand
 *
 * Brand source follows the established com_bonuses pattern: LEFT JOIN to
 * #__jreviews_content on cid = contentid; the JReviews "title" column is the
 * canonical casino brand (the b.brand column in #__bonuses, if present, is a
 * denormalised cache).
 */
class AuthorsModelAuthors extends JModel
{
    public function getItems()
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select('id, slug, name, role, bio, short_bio, avatar_url, author_type, joined_year, joomla_user_id')
            ->from($db->quoteName('#__casino_authors'))
            ->where('is_active = 1')
            ->order('FIELD(author_type, "main","reviewer","compliance","contributor") ASC, name ASC');

        $db->setQuery($query);
        $rows = $db->loadAssocList();

        if ($db->getErrorNum()) {
            error_log('com_authors authors model error: ' . $db->getErrorMsg());
            return array();
        }

        if (!is_array($rows)) $rows = array();

        foreach ($rows as &$r) {
            if (!isset($r['bio']))         $r['bio'] = '';
            if (!isset($r['short_bio']))   $r['short_bio'] = '';
            if (!isset($r['avatar_url']))  $r['avatar_url'] = '';
            if (!isset($r['role']))        $r['role'] = '';
            if (!isset($r['author_type'])) $r['author_type'] = 'contributor';
            if (!isset($r['joined_year']) || $r['joined_year'] === null) $r['joined_year'] = 0;
            if (!isset($r['joomla_user_id']) || $r['joomla_user_id'] === null) $r['joomla_user_id'] = 0;
        }

        return $rows;
    }

    /**
     * Site-wide stats for the team page hero band.
     *
     * Query semantics match the homepage hero (fec_render_home_hero_section
     * in fec_common_lib.php, ~L5557). Single canonical pattern used across
     * the editorial system so all "site-wide" counts agree:
     *
     *   - specialists : active editorial staff count
     *   - bonuses     : live & claimable bonuses (status=active, bonus>0,
     *                   JR-clean: not closed/suspended/blacklisted)
     *   - casinos     : active casino listings from #__jreviews_content
     *                   (JR-clean: not closed/suspended/blacklisted)
     *   - reports     : total player FXCheck reports (#__bonus_feedback)
     *
     * Each query is independent so a single failure doesn't blank the whole
     * band; failed counts return 0 and an error is logged.
     */
    public function getHeroStats()
    {
        $db = JFactory::getDbo();

        $stats = array(
            'specialists' => 0,
            'bonuses'     => 0,
            'casinos'     => 0,
            'reports'     => 0,
        );

        // 1) Specialists on staff
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__casino_authors'))
            ->where('is_active = 1');
        $db->setQuery($q);
        $stats['specialists'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getHeroStats specialists: ' . $db->getErrorMsg());
            $stats['specialists'] = 0;
        }

        // 2) Live bonuses — matches fec_render_home_hero_section query #1
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonuses', 'a'))
            ->join('INNER', $db->quoteName('#__jreviews_content', 'b')
                . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')')
            ->where($db->quoteName('a.status') . ' = ' . $db->quote('active'))
            ->where($db->quoteName('a.bonus') . ' > 0')
            ->where($db->quoteName('b.jr_closed') . ' = ' . $db->quote(''))
            ->where($db->quoteName('b.jr_suspended') . ' = ' . $db->quote(''))
            ->where($db->quoteName('b.jr_blacklisted') . ' = ' . $db->quote(''));
        $db->setQuery($q);
        $stats['bonuses'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getHeroStats bonuses: ' . $db->getErrorMsg());
            $stats['bonuses'] = 0;
        }

        // 3) Active casinos — matches fec_render_home_hero_section query #2
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jreviews_content', 'b'))
            ->where($db->quoteName('b.jr_closed') . ' = ' . $db->quote(''))
            ->where($db->quoteName('b.jr_suspended') . ' = ' . $db->quote(''))
            ->where($db->quoteName('b.jr_blacklisted') . ' = ' . $db->quote(''));
        $db->setQuery($q);
        $stats['casinos'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getHeroStats casinos: ' . $db->getErrorMsg());
            $stats['casinos'] = 0;
        }

        // 4) Total player reports — matches fec_render_home_hero_section query #3
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonus_feedback'));
        $db->setQuery($q);
        $stats['reports'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getHeroStats reports: ' . $db->getErrorMsg());
            $stats['reports'] = 0;
        }

        return $stats;
    }

    /**
     * Per-author rolled-up stats for the tm-card stats block.
     *
     * Returns associative array:
     *   - bonuses     : count of bonuses this author published (non-pending)
     *   - casinos     : distinct casinos this author covered (non-pending)
     *   - latest_ts   : MAX(added) as unix timestamp (0 if none)
     *   - latest_fmt  : "MMM YYYY" string or "" if no bonuses
     *
     * Single-query design: COUNT / COUNT-DISTINCT / MAX in one round-trip.
     */
    public function getStatsForAuthor($authorId)
    {
        $authorId = (int)$authorId;
        $out = array(
            'bonuses'    => 0,
            'casinos'    => 0,
            'latest_ts'  => 0,
            'latest_fmt' => '',
        );
        if ($authorId <= 0) return $out;

        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select(array(
                'COUNT(*) AS bonuses',
                'COUNT(DISTINCT cid) AS casinos',
                'UNIX_TIMESTAMP(MAX(added)) AS latest_ts',
            ))
            ->from($db->quoteName('#__bonuses'))
            ->where($db->quoteName('author_id') . ' = ' . $authorId)
            ->where($db->quoteName('status') . ' <> ' . $db->quote('pending'));

        $db->setQuery($q);
        $row = $db->loadAssoc();

        if ($db->getErrorNum()) {
            error_log('getStatsForAuthor(' . $authorId . '): ' . $db->getErrorMsg());
            return $out;
        }
        if (!is_array($row)) return $out;

        $out['bonuses']   = (int)$row['bonuses'];
        $out['casinos']   = (int)$row['casinos'];
        $out['latest_ts'] = (int)$row['latest_ts'];
        if ($out['latest_ts'] > 0) {
            // "Apr 2026" style — keep mono lockup consistent with hero stats.
            $out['latest_fmt'] = date('M Y', $out['latest_ts']);
        }
        return $out;
    }

    /**
     * Last N bonuses authored by $authorId, newest first, with brand from
     * the #__bonuses table itself (denormalised cache of the casino name).
     * Returns array of rows; each row has:
     *   - bid, cid, type, bonus, currency, added, brand (all from #__bonuses)
     *
     * Empty result on error or no matches.
     *
     * Brand source: #__bonuses.brand is a denormalised copy of the casino
     * brand string. Single-query approach (no JOIN to #__jreviews_content or
     * #__content). Faster, no JOIN-not-matching edge cases.
     */
    public function getRecentForAuthor($authorId, $limit = 3)
    {
        $authorId = (int)$authorId;
        $limit    = max(1, (int)$limit);
        if ($authorId <= 0) return array();

        $db = JFactory::getDbo();

        $sql = 'SELECT bid, cid, type, bonus, max_bonus, currency, game,'
             . ' fp_time, fp_time_den, added, brand'
             . ' FROM ' . $db->quoteName('#__bonuses')
             . ' WHERE author_id = ' . $authorId
             . '   AND status <> ' . $db->quote('pending')
             . ' ORDER BY added DESC'
             . ' LIMIT ' . $limit;

        $db->setQuery($sql);
        $rows = $db->loadAssocList();

        if ($db->getErrorNum()) {
            error_log('getRecentForAuthor SQL error: '
                . $db->getErrorMsg() . ' | SQL: ' . $sql);
            return array();
        }
        if (!is_array($rows)) {
            error_log('getRecentForAuthor non-array result | SQL: ' . $sql);
            return array();
        }

        // Normalise brand (NULL or empty → empty string)
        foreach ($rows as &$r) {
            if (!isset($r['brand']) || $r['brand'] === null) $r['brand'] = '';
            $r['brand'] = trim((string)$r['brand']);
        }
        unset($r);

        return $rows;
    }

    /**
     * V2.7.65 — Casino Bonus Blog footprint of an author, for the team card:
     * live post count and the newest post's timestamp (owner predicate: see
     * AuthorsHelper::blogOwnerWhere). Returns {posts, latest_ts}.
     */
    public function getBlogStatsForAuthor($authorId, $joomlaUserId)
    {
        $out = array('posts' => 0, 'latest_ts' => 0);
        $owner = AuthorsHelper::blogOwnerWhere($authorId, $joomlaUserId, '');
        if ($owner === '0') return $out;

        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select(array('COUNT(*) AS posts', 'UNIX_TIMESTAMP(MAX(created)) AS latest_ts'))
            ->from($db->quoteName('#__content'))
            ->where($owner)
            ->where($db->quoteName('catid') . ' = ' . AuthorsHelper::blogCatid())
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($q);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) {
            error_log('getBlogStatsForAuthor(' . (int)$authorId . '): ' . $db->getErrorMsg());
            return $out;
        }
        if (is_array($row)) {
            $out['posts']     = (int)$row['posts'];
            $out['latest_ts'] = (int)$row['latest_ts'];
        }
        return $out;
    }

    /**
     * V2.7.65 — last N live blog posts signed by the author, newest first,
     * with kind/provider from #__fec_blog_meta when that table exists.
     * Rows: id, title, alias, catid, created, images, kind, provider_name.
     */
    public function getRecentBlogForAuthor($authorId, $joomlaUserId, $limit = 3)
    {
        $limit = max(1, (int)$limit);
        $owner = AuthorsHelper::blogOwnerWhere($authorId, $joomlaUserId, 'c');
        if ($owner === '0') return array();

        $db = JFactory::getDbo();
        foreach (array(true, false) as $withMeta) {
            $q = $db->getQuery(true)
                ->select(array('c.id', 'c.title', 'c.alias', 'c.catid', 'c.created', 'c.images'))
                ->from($db->quoteName('#__content', 'c'))
                ->where($owner)
                ->where('c.catid = ' . AuthorsHelper::blogCatid())
                ->where('c.state = 1')
                ->order('c.created DESC, c.id DESC');
            if ($withMeta) {
                $q->select(array('m.kind', 'm.provider_name'))
                  ->join('LEFT', $db->quoteName('#__fec_blog_meta', 'm') . ' ON m.content_id = c.id');
            }
            $db->setQuery($q, 0, $limit);
            $rows = $db->loadAssocList();
            if (!$db->getErrorNum()) return is_array($rows) ? $rows : array();
            if (!$withMeta) error_log('getRecentBlogForAuthor(' . (int)$authorId . '): ' . $db->getErrorMsg());
        }
        return array();
    }
}