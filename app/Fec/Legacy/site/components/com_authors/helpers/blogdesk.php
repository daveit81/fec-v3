<?php
defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/authors.php';
require_once dirname(__FILE__) . '/blogtext.php';
require_once dirname(__FILE__) . '/blogslot.php';

/**
 * Blog desk — access context and shared lookups (V2.7.66).
 *
 * Who may use the desk:
 *   - a desk user: Joomla user id in AuthorsModelDashboard::DASHBOARD_ALLOWED_USERS
 *     (the editorial cockpit whitelist); sees and edits every blog post, may
 *     sign a post with any active byline or "FreeExtraChips Editorial";
 *   - a contributor: logged-in Joomla user whose id is the joomla_user_id of
 *     an active #__casino_authors row; sees and edits the posts signed by that
 *     profile (#__content.author_id = profile id, or created_by = user id for
 *     posts imported before the profile existed).
 */
class AuthorsHelperBlogdesk
{
    const BLOG_PATH_FALLBACK = '/casino-bonus-blog';
    const EDITORIAL_ALIAS    = 'FreeExtraChips Editorial';
    const EDITORIAL_USER_ID  = 42;

    /**
     * Context of the current request:
     *   user (JUser), logged (bool), is_desk (bool), author (array|null),
     *   allowed (bool).
     */
    public static function context()
    {
        static $cache = array();
        $user = JFactory::getUser();
        $uid = ($user && !$user->guest) ? (int) $user->id : 0;
        if (isset($cache[$uid])) return $cache[$uid];

        $ctx = array('user' => $user, 'logged' => $uid > 0, 'is_desk' => false, 'author' => null, 'allowed' => false);
        if (!$ctx['logged']) { $cache[$uid] = $ctx; return $ctx; }

        if (!class_exists('AuthorsModelDashboard')) {
            $f = dirname(dirname(__FILE__)) . '/models/dashboard.php';
            if (file_exists($f)) require_once $f;
        }
        if (class_exists('AuthorsModelDashboard')) {
            $allowed = array_map('intval', explode(',', AuthorsModelDashboard::DASHBOARD_ALLOWED_USERS));
            $ctx['is_desk'] = in_array((int) $user->id, $allowed, true);
        }
        $ctx['author']  = self::authorForUser($uid);
        $ctx['allowed'] = $ctx['is_desk'] || $ctx['author'] !== null;
        $cache[$uid] = $ctx;
        return $ctx;
    }

    /** Active author profile mapped to a Joomla user id, or null. */
    public static function authorForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return null;
        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select(array('id', 'slug', 'name', 'role', 'author_type', 'avatar_url', 'joomla_user_id'))
            ->from($db->quoteName('#__casino_authors'))
            ->where($db->quoteName('joomla_user_id') . ' = ' . $userId)
            ->where('is_active = 1')
            ->order('id ASC');
        $db->setQuery($q, 0, 1);
        $row = $db->loadAssoc();
        if ($db->getErrorNum()) { error_log('AuthorsHelperBlogdesk::authorForUser: ' . $db->getErrorMsg()); return null; }
        return is_array($row) ? $row : null;
    }

    /** All active profiles (desk byline picker). */
    public static function activeAuthors()
    {
        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select(array('id', 'slug', 'name', 'author_type', 'joomla_user_id'))
            ->from($db->quoteName('#__casino_authors'))
            ->where('is_active = 1')
            ->order('FIELD(author_type, "main","reviewer","compliance","contributor") ASC, name ASC');
        $db->setQuery($q);
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    }

    /**
     * Provider map from the jReviews software field: norm(option text) =>
     * array(value, text). Cached in the session for 10 minutes.
     */
    public static function softMap()
    {
        static $map = null;
        if ($map !== null) return $map;
        $map = array();
        $db = JFactory::getDbo();
        $field = defined('FEC_BLOG_SOFT_FIELD') ? FEC_BLOG_SOFT_FIELD : 'jr_soft';
        $db->setQuery('SELECT fo.value, fo.text FROM ' . $db->quoteName('#__jreviews_fieldoptions') . ' fo'
            . ' JOIN ' . $db->quoteName('#__jreviews_fields') . ' f ON f.fieldid = fo.fieldid'
            . ' WHERE f.name = ' . $db->quote($field));
        $rows = $db->loadAssocList();
        if ($db->getErrorNum() || !is_array($rows)) {
            if ($db->getErrorNum()) error_log('AuthorsHelperBlogdesk::softMap: ' . $db->getErrorMsg());
            return $map;
        }
        foreach ($rows as $r) {
            $n = AuthorsHelperBlogtext::norm($r['text']);
            if ($n !== '' && !isset($map[$n])) $map[$n] = array('value' => (string) $r['value'], 'text' => (string) $r['text']);
        }
        return $map;
    }

    /** Provider options sorted by name, for the override select: value => text. */
    public static function providerOptions()
    {
        $out = array();
        foreach (self::softMap() as $opt) $out[$opt['value']] = $opt['text'];
        fec_php5_asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /** Desk URLs (non-SEF on purpose, like the other editorial tools). */
    public static function deskUrl($extra = '')
    {
        return JRoute::_('index.php?option=com_authors&view=blogdesk' . $extra, false);
    }

    public static function editUrl($id = 0)
    {
        return self::deskUrl('&layout=edit' . ($id > 0 ? '&id=' . (int) $id : ''));
    }

    /** Public URL of a post (SEF through fec_blog.php when installed). */
    public static function postUrl($row)
    {
        return AuthorsHelper::blogPostUrl((int) $row['id'], (string) $row['alias'], isset($row['catid']) ? (int) $row['catid'] : 0);
    }

    /** Login URL that comes back to the desk. */
    public static function loginUrl()
    {
        $return = base64_encode('index.php?option=com_authors&view=blogdesk');
        return JRoute::_('index.php?option=com_users&view=login&return=' . $return, false);
    }

    /** Kind labels. */
    public static function kindOptions()
    {
        return array('slot' => 'Slot release', 'live' => 'Live casino', 'jackpot' => 'Jackpots', 'promo' => 'Promotions', 'news' => 'News');
    }
}
