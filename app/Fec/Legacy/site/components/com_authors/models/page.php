<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Page model — drives editorial / trust / public-service pages owned by the
 * editorial team but distinct from methodology pages.
 *
 * Why a separate model from AuthorsModelMethodology:
 *   - methodology pages = HOW we evaluate things (FXCheck, RTP, FRank, ...).
 *     Schema.org type: Article.
 *   - editorial pages   = informational service / trust / transparency.
 *     Examples: Responsible Gambling (safety), Affiliate Disclosure (trust),
 *     About (org info). Schema.org type: WebPage (or specialized subtypes).
 *
 * Mixing the two on one model would confuse the registry semantics (status,
 * tmpl, authors) and the schema markup. Keeping them split makes intent
 * explicit and avoids fan-out as the editorial section grows.
 *
 * Topic config keys (same shape as methodology for ease of pattern transfer):
 *   - title         h1 string
 *   - tagline       one-line description for hero + meta
 *   - description   optional longer meta description
 *   - author_slug   primary author (must exist in #__casino_authors)
 *   - reviewer_slug optional reviewer slug
 *   - last_updated  YYYY-MM-DD
 *   - tmpl          Joomla layout suffix. Joomla resolves
 *                   parent::display('responsible-gambling') to
 *                   tmpl/default_responsible-gambling.php — NOT
 *                   responsible-gambling.php. Same trap as methodology.
 *   - schema_type   schema.org @type to emit (default 'WebPage')
 *   - status        'live' | 'coming_soon' | 'external'
 */
class AuthorsModelPage extends JModel
{
    /**
     * Static topic registry. Status field drives access control on the page
     * view: only 'live' topics render; everything else 404s. The hub view (if
     * we ever build one for editorial pages) calls getTopics() directly.
     *
     * Adding a new editorial page = add an entry here + create the
     * corresponding tmpl/default_<slug>.php template. Set status='live' once
     * the template exists.
     */
    public function getTopics()
    {
        // Prefer the unified registry (#__methodology_topics, kind='page') so
        // editors can manage page-topic metadata from the admin. Falls back to
        // the hardcoded array if the table/column is absent (pre-2.7.38) or the
        // query returns nothing — a live page must never disappear because of a
        // DB hiccup. Merge: DB rows override hardcoded by slug; a hardcoded
        // topic the DB doesn't have is still served.
        $hard = $this->getTopicsHardcoded();
        $db   = $this->getTopicsFromDb();
        if (empty($db)) {
            return $hard;
        }
        return array_merge($hard, $db);
    }

    /**
     * Read page topics from the unified registry. Same keyed-by-slug shape as
     * getTopicsHardcoded(). Returns empty on any error or when the kind column
     * doesn't exist yet (pre-2.7.38), so the caller falls back to hardcoded.
     */
    protected function getTopicsFromDb()
    {
        $out = array();
        try {
            $db = JFactory::getDbo();
            $db->setQuery(
                "SELECT slug, status, title, tagline, short_description, description,
                        author_slug, reviewer_slug, version, last_updated, tmpl, schema_type
                 FROM #__methodology_topics
                 WHERE kind = 'page'
                 ORDER BY sort_order ASC, slug ASC"
            );
            $rows = $db->loadAssocList();
            if (!is_array($rows) || empty($rows)) return array();
            foreach ($rows as $r) {
                $slug = isset($r['slug']) ? (string)$r['slug'] : '';
                if ($slug === '') continue;
                $lu = isset($r['last_updated']) ? (string)$r['last_updated'] : '';
                if ($lu === '0000-00-00' || $lu === null) $lu = '';
                $cfg = array(
                    'status'            => isset($r['status'])            ? (string)$r['status']            : 'coming_soon',
                    'title'             => isset($r['title'])             ? (string)$r['title']             : '',
                    'tagline'           => isset($r['tagline'])           ? (string)$r['tagline']           : '',
                    'short_description' => isset($r['short_description']) ? (string)$r['short_description'] : '',
                    'description'       => isset($r['description'])       ? (string)$r['description']       : '',
                    'author_slug'       => isset($r['author_slug'])       ? (string)$r['author_slug']       : '',
                    'reviewer_slug'     => isset($r['reviewer_slug'])     ? (string)$r['reviewer_slug']     : '',
                    'tmpl'              => isset($r['tmpl'])              ? (string)$r['tmpl']              : '',
                );
                if ($lu !== '') $cfg['last_updated'] = $lu;
                if (!empty($r['schema_type'])) $cfg['schema_type'] = (string)$r['schema_type'];
                $out[$slug] = $cfg;
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Hardcoded fallback for page topics (pre-2.7.38 behaviour). Kept in sync
     * with the seed in admin/sql/updates/mysql/2.7.38.sql. A live page is served
     * from here if the DB registry is unavailable, so pages never 404 on a DB
     * hiccup.
     */
    public function getTopicsHardcoded()
    {
        return array(
            'responsible-gambling' => array(
                'status'        => 'live',
                'title'         => 'Responsible Gambling',
                'tagline'       => 'Recognizing problem gambling, taking action, and accessing support — country-specific helplines and the tools casinos offer to stay in control.',
                'short_description' => 'Practical guidance on recognizing harm, accessing support, and using operator tools to stay in control. Country-specific helplines for US, UK, AU, CA, IT, DE, FR.',
                'description'   => 'Responsible gambling guidance from FreeExtraChips: warning signs, self-check questions, casino tools, and verified helplines for the US, UK, Australia, Canada, Italy, Germany, and France.',
                'author_slug'   => 'david-romano',
                'reviewer_slug' => 'ethan-walker',
                'last_updated'  => '2026-05-23',
                'tmpl'          => 'responsible-gambling',
                'schema_type'   => 'WebPage',
            ),
            'affiliate-disclosure' => array(
                'status'        => 'live',
                'title'         => 'Affiliate &amp; Advertiser Disclosure',
                'tagline'       => 'How FreeExtraChips makes money, what affiliate links mean for our content, and what does (and does not) affect bonus rankings.',
                'short_description' => 'How FreeExtraChips earns commission, why this does not control editorial decisions, what criteria decide bonus rankings, and which offers we refuse to promote.',
                'description'   => 'Affiliate and advertiser disclosure for FreeExtraChips: business model, editorial firewall, ranking criteria, and what we do not do.',
                'author_slug'   => 'david-romano',
                'reviewer_slug' => 'ethan-walker',
                'last_updated'  => '2026-05-23',
                'tmpl'          => 'affiliate-disclosure',
                'schema_type'   => 'AboutPage',
            ),
            'privacy-policy' => array(
                'status'        => 'live',
                'title'         => 'Privacy Policy',
                'tagline'       => 'What data FreeExtraChips actually collects, why, who processes it on our behalf, and the choices and rights you have.',
                'short_description' => 'The real data flows on FreeExtraChips: browsing logs and Cloudflare, GA4 analytics, newsletter subscriptions with click tracking, affiliate outbound links, and your rights.',
                'description'   => 'Privacy policy for FreeExtraChips: what we collect (logs, analytics, newsletter, reader submissions), cookies, affiliate-link reporting, processors, retention, and your rights.',
                'author_slug'   => 'david-romano',
                'reviewer_slug' => 'ethan-walker',
                'last_updated'  => '2026-07-29',
                'tmpl'          => 'privacy-policy',
                'schema_type'   => 'WebPage',
            ),
            'fast-payouts' => array(
                'status'        => 'live',
                'title'         => 'Fastest Paying Online Casinos, Measured',
                'tagline'       => 'Withdrawal times measured, not declared: desk tests timed by FreeExtraChips staff from request to receipt, and player clocks from FXClock, ranked fastest first.',
                'short_description' => 'A live register of measured casino withdrawal times. Desk tests requested and timed by FreeExtraChips staff in UTC against the operator declared window, plus FXClock medians timed by players. Two tables, never merged.',
                'description'   => 'Fastest paying online casinos ranked by measured withdrawal time: desk tests from request to receipt vs the declared window, plus FXClock player medians.',
                'author_slug'   => 'mark-sullivan',
                'reviewer_slug' => 'david-romano',
                'last_updated'  => '2026-10-06',
                'tmpl'          => 'fast-payouts',
                'schema_type'   => 'WebPage',
            ),
            'licence-watch' => array(
                'status'        => 'live',
                'title'         => 'Licence Watch: Where Online Casinos Say They Are Licensed',
                'tagline'       => 'A quarterly register of the licences behind every live casino on FreeExtraChips: what each brand declares, what we have verified against the regulators, and what moved since the last edition.',
                'short_description' => 'Quarterly licence register: live brands by declared regulator, the verified layer from the Operator Atlas, the Curacao transition, the new rails, brands with no regulator, and what moved in the quarter.',
                'description'   => 'Quarterly register of online casino licences on FreeExtraChips: every live brand by declared regulator, the operators and licences verified against the registers, and what changed since the previous edition.',
                'author_slug'   => 'ethan-walker',
                'reviewer_slug' => 'david-romano',
                'last_updated'  => '2026-10-09',
                'tmpl'          => 'licence-watch',
                'schema_type'   => 'WebPage',
            ),
            'about' => array(
                'status'        => 'coming_soon',
                'title'         => 'About FreeExtraChips',
                'tagline'       => 'Who we are, what we cover, how the editorial team is structured, and our position on the industry.',
            ),
        );
    }

    /**
     * Return a topic config only if status='live'. The page view uses this so
     * non-live topics 404 instead of trying to load a missing tmpl.
     */
    public function getTopicConfig($slug)
    {
        $slug   = trim((string)$slug);
        $topics = $this->getTopics();
        if (!isset($topics[$slug])) return null;
        $t = $topics[$slug];
        if (!isset($t['status']) || $t['status'] !== 'live') return null;
        return $t;
    }

    /**
     * Minimal author row by slug. Duplicated from AuthorsModelMethodology
     * intentionally — keeps the page model self-contained so a future change
     * to one schema's author shape can't accidentally break the other.
     * Returns null if not found.
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
            error_log('AuthorsModelPage::getAuthorBySlug(' . $slug . '): ' . $db->getErrorMsg());
            return null;
        }
        return is_array($row) ? $row : null;
    }
}
