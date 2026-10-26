<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

// Blog catid + URL helpers (V2.7.65). Guarded: the views load it too.
require_once dirname(dirname(__FILE__)) . '/helpers/authors.php';

/**
 * Single Author model — drives /authors/<slug>.
 *
 * FEC V2.1 Author Profile migration: extended with the columns added in
 * author_schema_migration.sql (languages, expertise_json, credentials_json,
 * joomla_user_id) plus stat methods that power the activity tabs and the
 * sidebar Quick Facts box. V2.7.65 adds the Casino Bonus Blog: blog_posts
 * count, getBlogPosts()/getBlogTotal() for the "Blog posts" tab.
 *
 * Stat methods are intentionally split: each returns a single scalar so the
 * controller can decide which to fetch (e.g., skip Guides if joomla_user_id
 * is NULL). All filters mirror fec_render_home_hero_section's "live"
 * semantics where it makes sense (canonical alignment with site-wide stats).
 */
class AuthorsModelAuthor extends JModel
{
    /**
     * Fetch the author row by slug. Returns associative array with derived
     * fields:
     *   - expertise   : decoded array (from expertise_json TEXT column)
     *   - credentials : decoded array of {t, d} (from credentials_json)
     *   - languages   : exploded array from comma-separated "languages" column
     *
     * Returns null on no match or DB error.
     */
    public function getItemBySlug($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') return null;

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select(array(
                'id', 'slug', 'name', 'role', 'bio', 'short_bio',
                'avatar_url', 'author_type', 'joined_year',
                'languages', 'expertise_json', 'credentials_json',
                'joomla_user_id',
            ))
            ->from($db->quoteName('#__casino_authors'))
            ->where($db->quoteName('slug') . ' = ' . $db->quote($slug))
            ->where('is_active = 1');

        $db->setQuery($query);
        $row = $db->loadAssoc();

        if ($db->getErrorNum()) {
            error_log('AuthorsModelAuthor::getItemBySlug: ' . $db->getErrorMsg());
            return null;
        }
        if (!is_array($row)) return null;

        // Defensive defaults so the template never has to test isset() everywhere.
        if (!isset($row['bio']))         $row['bio'] = '';
        if (!isset($row['short_bio']))   $row['short_bio'] = '';
        if (!isset($row['avatar_url']))  $row['avatar_url'] = '';
        if (!isset($row['role']))        $row['role'] = '';
        if (!isset($row['author_type'])) $row['author_type'] = 'contributor';
        if (!isset($row['joined_year']) || $row['joined_year'] === null) $row['joined_year'] = 0;
        if (!isset($row['joomla_user_id']) || $row['joomla_user_id'] === null) $row['joomla_user_id'] = 0;

        // Languages: "English, Italian" → ["English","Italian"]
        $langStr = isset($row['languages']) ? trim((string)$row['languages']) : '';
        $row['languages'] = $langStr === ''
            ? array()
            : array_values(array_filter(array_map('trim', explode(',', $langStr)), 'strlen'));

        // Expertise: JSON-encoded array of strings → PHP array
        $row['expertise'] = $this->decodeJsonField(isset($row['expertise_json']) ? $row['expertise_json'] : '', 'expertise', $slug);

        // Credentials: JSON-encoded array of {t, d} → PHP array
        $row['credentials'] = $this->decodeJsonField(isset($row['credentials_json']) ? $row['credentials_json'] : '', 'credentials', $slug);

        return $row;
    }

    /**
     * Counts for the 4 activity tabs + sidebar facts. Returns:
     *   - bonuses      : count of non-pending bonuses by this author
     *   - bonuses_active: subset of above with status='active' (for the
     *                    "Active only" sub-filter under the Bonuses tab)
     *   - casinos      : distinct casinos this author has covered
     *   - fxchecks     : count of #__bonus_feedback rows on this author's bonuses
     *   - guides       : count of #__content rows authored by joomla_user_id
     *                    (0 if joomla_user_id is unset or 0)
     *   - latest_ts    : MAX(added) UNIX timestamp across all non-pending bonuses
     *                    (used for the "Latest activity" sidebar row)
     *
     * Each query is independent; one failure doesn't blank the rest.
     */
    public function getCountsForAuthor($authorId, $joomlaUserId = 0)
    {
        $authorId     = (int)$authorId;
        $joomlaUserId = (int)$joomlaUserId;

        $out = array(
            'bonuses'           => 0,
            'bonuses_active'    => 0,
            'casinos'           => 0,
            'fxchecks'          => 0,
            'guides'            => 0,
            'blog_posts'        => 0,
            'rtp_verifications' => 0,
            'latest_ts'         => 0,
        );
        if ($authorId <= 0) return $out;

        $db = JFactory::getDbo();

        // Bonuses total + active + casinos + latest_ts — one round-trip
        $q = $db->getQuery(true)
            ->select(array(
                'COUNT(*) AS bonuses',
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = ' . $db->quote('active') . ' THEN 1 ELSE 0 END) AS bonuses_active',
                'COUNT(DISTINCT cid) AS casinos',
                'UNIX_TIMESTAMP(MAX(added)) AS latest_ts',
            ))
            ->from($db->quoteName('#__bonuses'))
            ->where($db->quoteName('author_id') . ' = ' . $authorId)
            ->where($db->quoteName('status') . ' <> ' . $db->quote('pending'));
        $db->setQuery($q);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) {
            error_log('getCountsForAuthor bonuses: ' . $db->getErrorMsg());
        } elseif (is_array($row)) {
            $out['bonuses']        = (int)$row['bonuses'];
            $out['bonuses_active'] = (int)$row['bonuses_active'];
            $out['casinos']        = (int)$row['casinos'];
            $out['latest_ts']      = (int)$row['latest_ts'];
        }

        // RTP verifications — slots verified by this author, published and not retired
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__slot_rtp'))
            ->where($db->quoteName('verified_by_author_id') . ' = ' . $authorId)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('retired') . ' = 0');
        $db->setQuery($q);
        $out['rtp_verifications'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getCountsForAuthor rtp_verifications: ' . $db->getErrorMsg());
            $out['rtp_verifications'] = 0;
        }

        // FXCheck reports — feedback rows on this author's bonuses
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonus_feedback', 'bf'))
            ->join('INNER', $db->quoteName('#__bonuses', 'b')
                . ' ON ' . $db->quoteName('b.bid') . ' = ' . $db->quoteName('bf.bid'))
            ->where($db->quoteName('b.author_id') . ' = ' . $authorId);
        $db->setQuery($q);
        $out['fxchecks'] = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getCountsForAuthor fxchecks: ' . $db->getErrorMsg());
            $out['fxchecks'] = 0;
        }

        $blogCatid = AuthorsHelper::blogCatid();

        // Guides — Joomla content rows by this joomla user (published only).
        // Only runs if the admin set joomla_user_id on the author. Until set,
        // the tab reads 0. V2.7.65: the Casino Bonus Blog category is left
        // out (it has its own counter below), so a contributor's 500 posts
        // don't read as 500 guides.
        if ($joomlaUserId > 0) {
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('created_by') . ' = ' . $joomlaUserId)
                ->where($db->quoteName('catid') . ' <> ' . $blogCatid)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($q);
            $out['guides'] = (int)$db->loadResult();
            if ($db->getErrorNum()) {
                error_log('getCountsForAuthor guides: ' . $db->getErrorMsg());
                $out['guides'] = 0;
            }
        }

        // Blog posts (V2.7.65) — posts signed by this profile (see
        // AuthorsHelper::blogOwnerWhere). latest_ts also considers the
        // newest post, so "Latest activity" is right for a writer.
        $owner = AuthorsHelper::blogOwnerWhere($authorId, $joomlaUserId, '');
        if ($owner !== '0') {
            $q = $db->getQuery(true)
                ->select(array('COUNT(*) AS blog_posts', 'UNIX_TIMESTAMP(MAX(' . $db->quoteName('created') . ')) AS latest_ts'))
                ->from($db->quoteName('#__content'))
                ->where($owner)
                ->where($db->quoteName('catid') . ' = ' . $blogCatid)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($q);
            $row = $db->loadAssoc();
            if ($db->getErrorNum()) {
                error_log('getCountsForAuthor blog: ' . $db->getErrorMsg());
            } elseif (is_array($row)) {
                $out['blog_posts'] = (int)$row['blog_posts'];
                $out['latest_ts']  = max($out['latest_ts'], (int)$row['latest_ts']);
            }
        }

        return $out;
    }

    /**
     * V2.7.65 — Casino Bonus Blog posts signed by this profile, newest
     * first, for the "Blog posts" tab (owner predicate: see
     * AuthorsHelper::blogOwnerWhere). Joins #__fec_blog_meta for the kind
     * and provider labels; when that table is missing (blog not migrated
     * yet) the query is retried without it. Returns rows with id, title,
     * alias, catid, created, hits, images, introtext, kind, provider_name,
     * provider_slug.
     */
    public function getBlogPosts($authorId, $joomlaUserId, $limit = 20, $start = 0)
    {
        $owner = AuthorsHelper::blogOwnerWhere($authorId, $joomlaUserId, 'c');
        if ($owner === '0') return array();

        $db     = JFactory::getDbo();
        $nowUtc = JFactory::getDate()->toSql();
        $cols   = array('c.id', 'c.title', 'c.alias', 'c.catid', 'c.created', 'c.hits', 'c.images', 'c.introtext');

        foreach (array(true, false) as $withMeta) {
            $q = $db->getQuery(true)
                ->select($cols)
                ->from($db->quoteName('#__content', 'c'))
                ->where($owner)
                ->where('c.catid = ' . AuthorsHelper::blogCatid())
                ->where('c.state = 1')
                ->where('(c.publish_up = ' . $db->quote($db->getNullDate()) . ' OR c.publish_up <= ' . $db->quote($nowUtc) . ')')
                ->where('(c.publish_down = ' . $db->quote($db->getNullDate()) . ' OR c.publish_down >= ' . $db->quote($nowUtc) . ')')
                ->order('c.created DESC, c.id DESC');
            if ($withMeta) {
                $q->select(array('m.kind', 'm.provider_name', 'm.provider_slug'))
                  ->join('LEFT', $db->quoteName('#__fec_blog_meta', 'm') . ' ON m.content_id = c.id');
            }
            $db->setQuery($q, max(0, (int)$start), max(1, (int)$limit));
            $rows = $db->loadAssocList();
            if (!$db->getErrorNum()) return is_array($rows) ? $rows : array();
            if (!$withMeta) error_log('AuthorsModelAuthor::getBlogPosts: ' . $db->getErrorMsg());
        }
        return array();
    }

    /**
     * V2.7.65 — total of live blog posts signed by this profile (pagination).
     */
    public function getBlogTotal($authorId, $joomlaUserId)
    {
        $owner = AuthorsHelper::blogOwnerWhere($authorId, $joomlaUserId, '');
        if ($owner === '0') return 0;

        $db     = JFactory::getDbo();
        $nowUtc = JFactory::getDate()->toSql();
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($owner)
            ->where($db->quoteName('catid') . ' = ' . AuthorsHelper::blogCatid())
            ->where($db->quoteName('state') . ' = 1')
            ->where('(publish_up = ' . $db->quote($db->getNullDate()) . ' OR publish_up <= ' . $db->quote($nowUtc) . ')')
            ->where('(publish_down = ' . $db->quote($db->getNullDate()) . ' OR publish_down >= ' . $db->quote($nowUtc) . ')');
        $db->setQuery($q);
        $n = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelAuthor::getBlogTotal: ' . $db->getErrorMsg());
            return 0;
        }
        return $n;
    }

    /**
     * Activity average — "X.X / day" string for the sidebar Quick Facts box.
     *
     * Numerator: bonuses created in the last 30 days + feedback created in the
     * last 30 days (the author's recent contributions to the corpus).
     * Denominator: 30 days fixed.
     *
     * Returns '0 / day' if no activity, or e.g. '3.2 / day'.
     */
    public function getActivityAverage($authorId, $joomlaUserId = 0, $windowDays = 30)
    {
        $authorId     = (int)$authorId;
        $windowDays   = max(1, (int)$windowDays);
        $joomlaUserId = (int)$joomlaUserId;
        if ($authorId <= 0) return '0 / day';

        $db    = JFactory::getDbo();
        $total = 0;

        // Bonuses in the last N days
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonuses'))
            ->where($db->quoteName('author_id') . ' = ' . $authorId)
            ->where($db->quoteName('status') . ' <> ' . $db->quote('pending'))
            ->where($db->quoteName('added') . ' >= DATE_SUB(NOW(), INTERVAL ' . $windowDays . ' DAY)');
        $db->setQuery($q);
        $total += (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getActivityAverage bonuses: ' . $db->getErrorMsg());
        }

        // Feedback on this author's bonuses in the last N days
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonus_feedback', 'bf'))
            ->join('INNER', $db->quoteName('#__bonuses', 'b')
                . ' ON ' . $db->quoteName('b.bid') . ' = ' . $db->quoteName('bf.bid'))
            ->where($db->quoteName('b.author_id') . ' = ' . $authorId)
            ->where($db->quoteName('bf.created_at') . ' >= DATE_SUB(NOW(), INTERVAL ' . $windowDays . ' DAY)');
        $db->setQuery($q);
        $total += (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('getActivityAverage feedback: ' . $db->getErrorMsg());
        }

        // Joomla content rows in the last N days (Guides) — only if mapped
        if ($joomlaUserId > 0) {
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('created_by') . ' = ' . $joomlaUserId)
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('created') . ' >= DATE_SUB(NOW(), INTERVAL ' . $windowDays . ' DAY)');
            $db->setQuery($q);
            $total += (int)$db->loadResult();
            if ($db->getErrorNum()) {
                error_log('getActivityAverage guides: ' . $db->getErrorMsg());
            }
        }

        if ($total === 0) return '0 / day';

        $avg = $total / $windowDays;
        // One decimal place if < 10, else whole number
        $fmt = ($avg < 10) ? number_format($avg, 1) : (string)(int)round($avg);
        return $fmt . ' / day';
    }

    /**
     * Internal: defensively decode a JSON string column to a PHP array.
     * Returns empty array on null/empty/parse failure; logs on parse failure.
     */
    private function decodeJsonField($raw, $fieldName, $authorSlug)
    {
        $raw = trim((string)$raw);
        if ($raw === '') return array();
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('AuthorsModelAuthor: ' . $fieldName . '_json decode failed for slug=' . $authorSlug . ' (raw start: ' . substr($raw, 0, 50) . ')');
            return array();
        }
        return $decoded;
    }

    /**
     * Slots verified by this author. Returns published, non-retired records
     * with slug present, joined to #__games for name + provider. Sorted by
     * last_verified_date desc so the freshest work surfaces first.
     */
    public function getVerifiedSlots($authorId, $limit = 50)
    {
        $authorId = (int)$authorId;
        $limit    = max(1, (int)$limit);
        if ($authorId <= 0) return array();

        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select(array(
                'rtp.slug',
                'rtp.rtp_theoretical_max',
                'rtp.rtp_theoretical_min',
                'rtp.last_verified_date',
                'g.game_name',
                'g.provider_name',
            ))
            ->from($db->quoteName('#__slot_rtp', 'rtp'))
            ->innerJoin(
                $db->quoteName('#__games', 'g')
                . ' ON g.' . $db->quoteName('gid') . ' = rtp.' . $db->quoteName('gid')
            )
            ->where('rtp.' . $db->quoteName('verified_by_author_id') . ' = ' . $authorId)
            ->where('rtp.' . $db->quoteName('published') . ' = 1')
            ->where('rtp.' . $db->quoteName('retired') . ' = 0')
            ->where('rtp.' . $db->quoteName('slug') . ' IS NOT NULL')
            ->where('rtp.' . $db->quoteName('slug') . " <> ''")
            ->order('rtp.last_verified_date DESC, g.game_name ASC');
        $db->setQuery($q, 0, $limit);
        $rows = $db->loadAssocList();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelAuthor::getVerifiedSlots: ' . $db->getErrorMsg());
            return array();
        }
        return is_array($rows) ? $rows : array();
    }
}