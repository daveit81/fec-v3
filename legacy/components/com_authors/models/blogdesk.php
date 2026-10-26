<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');
jimport('joomla.database.table');
jimport('joomla.filter.filteroutput');

require_once dirname(dirname(__FILE__)) . '/helpers/blogdesk.php';

/**
 * Blog desk model (V2.7.66) — list, load and save Casino Bonus Blog posts
 * from the front end.
 *
 * A save takes a title, a body (pasted HTML or plain text) and an optional
 * image, and writes everything the longform page needs: #__content row
 * through JTableContent (alias, intro/fulltext split, meta description,
 * images JSON, byline, dates, asset) and the #__fec_blog_meta row (kind,
 * provider, Game facts, the source text for round-trip editing).
 */
class AuthorsModelBlogdesk extends JModel
{
    const LIST_LIMIT      = 25;
    const MAX_IMAGE_BYTES = 10485760;   // 10 MB upload cap
    const MAX_LONG_EDGE   = 1600;
    const JPEG_QUALITY    = 85;
    // Upload folder (site-relative). Not "images/blog": a rewrite rule on the
    // host sends every path containing "blog/" to /casino-bonus-blog/..., so
    // /images/blog/2026/x.jpg 301-ed to a 404 (2.7.68). Override with
    // define('FEC_BLOG_IMAGE_DIR', ...) before the component loads if needed.
    const IMAGE_DIR       = 'images/posts';

    /* ---------------------------------------------------------------------
     * Ownership
     * ------------------------------------------------------------------- */

    /** SQL predicate on alias $c for the posts the context may see/edit. */
    public static function ownerWhere($ctx, $c = 'c')
    {
        if (!empty($ctx['is_desk'])) return '1';
        $a = isset($ctx['author']) ? $ctx['author'] : null;
        if (!$a) return '0';
        $pfx = $c !== '' ? $c . '.' : '';
        $parts = array();
        if (AuthorsHelper::contentHasAuthorColumn()) $parts[] = $pfx . 'author_id = ' . (int) $a['id'];
        if ((int) $a['joomla_user_id'] > 0) $parts[] = $pfx . 'created_by = ' . (int) $a['joomla_user_id'];
        return $parts ? '(' . implode(' OR ', $parts) . ')' : '0';
    }

    public static function canEdit($ctx, $row)
    {
        if (!empty($ctx['is_desk'])) return true;
        $a = isset($ctx['author']) ? $ctx['author'] : null;
        if (!$a || !is_array($row)) return false;
        if (isset($row['author_id']) && (int) $row['author_id'] > 0) return (int) $row['author_id'] === (int) $a['id'];
        return (int) $row['created_by'] === (int) $a['joomla_user_id'] && (int) $a['joomla_user_id'] > 0;
    }

    /* ---------------------------------------------------------------------
     * Read
     * ------------------------------------------------------------------- */

    /** Posts visible to the context, newest first. Returns array(rows, total). */
    public function listPosts($ctx, $search = '', $filter = 'all', $limit = self::LIST_LIMIT, $start = 0)
    {
        $db = JFactory::getDbo();
        $owner = self::ownerWhere($ctx, 'c');
        if ($owner === '0') return array(array(), 0);
        $now = JFactory::getDate()->toSql();
        $null = $db->getNullDate();

        $where = array($owner, 'c.catid = ' . AuthorsHelper::blogCatid(), 'c.state >= 0');
        $search = trim((string) $search);
        if ($search !== '') $where[] = 'c.title LIKE ' . $db->quote('%' . $db->escape($search, true) . '%', false);
        switch ($filter) {
            case 'draft':     $where[] = 'c.state = 0'; break;
            case 'scheduled': $where[] = 'c.state = 1 AND c.publish_up > ' . $db->quote($now); break;
            case 'published': $where[] = 'c.state = 1 AND (c.publish_up = ' . $db->quote($null) . ' OR c.publish_up <= ' . $db->quote($now) . ')'; break;
        }
        $w = ' WHERE (' . implode(') AND (', $where) . ')';

        $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__content', 'c') . $w);
        $total = (int) $db->loadResult();
        if ($db->getErrorNum()) { error_log('AuthorsModelBlogdesk::listPosts count: ' . $db->getErrorMsg()); return array(array(), 0); }

        $cols = 'c.id, c.title, c.alias, c.catid, c.state, c.created, c.publish_up, c.modified, c.hits, c.images, c.created_by, c.created_by_alias'
              . (AuthorsHelper::contentHasAuthorColumn() ? ', c.author_id' : ', NULL AS author_id');
        foreach (array(true, false) as $withMeta) {
            $sql = 'SELECT ' . $cols . ($withMeta ? ', m.kind, m.provider_name, m.provider_slug' : '')
                 . ' FROM ' . $db->quoteName('#__content', 'c')
                 . ($withMeta ? ' LEFT JOIN ' . $db->quoteName('#__fec_blog_meta', 'm') . ' ON m.content_id = c.id' : '')
                 . $w . ' ORDER BY c.created DESC, c.id DESC';
            $db->setQuery($sql, max(0, (int) $start), max(1, (int) $limit));
            $rows = $db->loadAssocList();
            if (!$db->getErrorNum()) return array(is_array($rows) ? $rows : array(), $total);
            if (!$withMeta) error_log('AuthorsModelBlogdesk::listPosts: ' . $db->getErrorMsg());
        }
        return array(array(), $total);
    }

    /** One post with its meta row, or null when missing / not a blog post. */
    public function loadPost($id)
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $db = JFactory::getDbo();
        $db->setQuery('SELECT c.* FROM ' . $db->quoteName('#__content', 'c') . ' WHERE c.id = ' . $id . ' AND c.catid = ' . AuthorsHelper::blogCatid());
        $row = $db->loadAssoc();
        if ($db->getErrorNum() || !is_array($row)) return null;
        if (!isset($row['author_id'])) $row['author_id'] = 0;
        $row['meta'] = $this->loadMeta($id);
        return $row;
    }

    public function loadMeta($contentId)
    {
        $db = JFactory::getDbo();
        $db->setQuery('SELECT * FROM ' . $db->quoteName('#__fec_blog_meta') . ' WHERE content_id = ' . (int) $contentId);
        $m = $db->loadAssoc();
        if ($db->getErrorNum()) { error_log('AuthorsModelBlogdesk::loadMeta: ' . $db->getErrorMsg()); return array(); }
        return is_array($m) ? $m : array();
    }

    /** Body as the author should see it again: the source text when kept, else the stored HTML. */
    public static function editableBody($row)
    {
        if (!empty($row['meta']['source_text'])) return (string) $row['meta']['source_text'];
        $intro = trim((string) $row['introtext']); $full = trim((string) $row['fulltext']);
        return trim($intro . ($intro !== '' && $full !== '' ? "\n" : '') . $full);
    }

    /* ---------------------------------------------------------------------
     * Save
     * ------------------------------------------------------------------- */

    /**
     * Save a post. $data keys: id, title, body, mode (auto|html|plain),
     * state (1|0), publish_up (site-local 'Y-m-d H:i' or ''), kind
     * ('' = auto), provider ('' = auto, '-' = none, else jr_soft value),
     * byline ('' | 'editorial' | author id; desk users only), remove_image.
     * $file: the uploaded image entry from $_FILES or null.
     * Returns array(ok, id, error, detected, url).
     */
    public function save($data, $ctx, $file = null)
    {
        $db   = JFactory::getDbo();
        $user = $ctx['user'];
        $id   = isset($data['id']) ? (int) $data['id'] : 0;

        $title = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $data['title'])));
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        if (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') < 4 : strlen($title) < 4) return $this->fail('The title is too short.');
        if (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') > 250 : strlen($title) > 250) return $this->fail('The title is too long (250 characters max).');

        $mode = in_array($data['mode'], array('auto', 'html', 'plain'), true) ? $data['mode'] : 'auto';
        $rawBody = (string) $data['body'];
        $usedMode = ($mode === 'auto') ? (AuthorsHelperBlogtext::looksLikeHtml($rawBody) ? 'html' : 'plain') : $mode;
        $html = AuthorsHelperBlogtext::normalize($rawBody, $usedMode);
        if (AuthorsHelperBlogtext::textFromHtml($html) === '') return $this->fail('The post has no text.');
        list($intro, $full) = AuthorsHelperBlogtext::splitIntro($html);
        $introText = AuthorsHelperBlogtext::textFromHtml($intro);
        $fullText  = AuthorsHelperBlogtext::textFromHtml($full);

        // Existing post?
        $existing = null;
        if ($id > 0) {
            $existing = $this->loadPost($id);
            if (!$existing) return $this->fail('Post not found.');
            if (!self::canEdit($ctx, $existing)) return $this->fail('You cannot edit this post.');
        }

        // Byline
        $byline = $this->resolveByline($ctx, isset($data['byline']) ? (string) $data['byline'] : '', $existing);
        if ($byline === null) return $this->fail('Choose a byline.');

        // Dates (site timezone in, UTC out)
        $state = ((int) $data['state'] === 0) ? 0 : 1;
        $publishUp = $this->toUtcSql(isset($data['publish_up']) ? (string) $data['publish_up'] : '');
        if ($publishUp === null) {
            $publishUp = $existing ? (string) $existing['publish_up'] : JFactory::getDate()->toSql();
            if ($publishUp === '' || $publishUp === $db->getNullDate()) $publishUp = JFactory::getDate()->toSql();
        }
        $publishTs = strtotime($publishUp . ' UTC');

        // Alias: kept on edits (URLs are promises), unique on create
        $alias = $existing ? (string) $existing['alias'] : $this->uniqueAlias($title);

        // Image
        $images = $existing ? json_decode((string) $existing['images'], true) : array();
        if (!is_array($images)) $images = array();
        $removeImage = !empty($data['remove_image']);
        if ($removeImage) $images = array();
        if ($file && isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $saved = $this->saveImage($file, $alias, $publishTs);
            if (!$saved['ok']) return $this->fail($saved['error']);
            $images = array('image_intro' => $saved['path'], 'float_intro' => '', 'image_intro_alt' => $title, 'image_intro_caption' => '',
                            'image_fulltext' => $saved['path'], 'float_fulltext' => '', 'image_fulltext_alt' => $title, 'image_fulltext_caption' => '');
        } elseif ($images) {
            $images['image_intro_alt'] = $title;
            if (isset($images['image_fulltext']) && $images['image_fulltext'] !== '') $images['image_fulltext_alt'] = $title;
        }

        // Meta: kind, provider, Game facts
        $kindOpt = isset($data['kind']) ? (string) $data['kind'] : '';
        $kind = array_key_exists($kindOpt, AuthorsHelperBlogdesk::kindOptions()) ? $kindOpt : AuthorsHelperBlogtext::classifyKind($title);
        $provOpt = isset($data['provider']) ? (string) $data['provider'] : '';
        $provSlug = null; $provName = null;
        if ($provOpt === '-') {
            // explicitly none
        } elseif ($provOpt !== '') {
            $opts = AuthorsHelperBlogdesk::providerOptions();
            if (isset($opts[$provOpt])) { $provSlug = $provOpt; $provName = $opts[$provOpt]; }
        } else {
            list($provSlug, $provName) = AuthorsHelperBlogtext::resolveProvider($title, $introText, AuthorsHelperBlogdesk::softMap());
        }
        $slot = AuthorsHelperBlogtext::extractSlotFields($introText, $fullText, $publishTs);
        $meta = array_merge(array('kind' => $kind, 'provider_slug' => $provSlug, 'provider_name' => $provName), $slot);

        // Verified slot (2.7.72): slot = '' auto-detect from the title, '-' none, gid forced;
        // slot_rel = '' auto, game, family
        $slotLink = $this->resolveSlotLink($title, isset($data['slot']) ? (string) $data['slot'] : '', isset($data['slot_rel']) ? (string) $data['slot_rel'] : '');

        // #__content through the table class (asset, dates, alias check)
        $table = JTable::getInstance('Content', 'JTable');
        if ($existing) {
            if (!$table->load($id)) return $this->fail('Could not load the post: ' . $table->getError());
        }
        $bind = array(
            'title'       => $title,
            'alias'       => $alias,
            'introtext'   => $intro,
            'fulltext'    => $full,
            'state'       => $state,
            'catid'       => AuthorsHelper::blogCatid(),
            'publish_up'  => $publishUp,
            'images'      => json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'metadesc'    => AuthorsHelperBlogtext::metaDescription($intro, $full),
            'language'    => '*',
            'created_by'       => (int) $byline['user_id'],
            'created_by_alias' => (string) $byline['alias'],
            'modified_by' => (int) $user->id,
        );
        if (AuthorsHelper::contentHasAuthorColumn()) $bind['author_id'] = $byline['author_id'] > 0 ? (int) $byline['author_id'] : null;
        $metadata = $existing ? json_decode((string) $existing['metadata'], true) : array();
        if (!is_array($metadata)) $metadata = array();
        $metadata = array_merge(array('robots' => '', 'rights' => '', 'xreference' => ''), $metadata, array('author' => (string) $byline['alias']));
        $bind['metadata'] = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // the visible date is the publication date: a draft, a scheduled post,
        // or a post being pushed to a future date takes publish_up as created
        if ($existing && ((int) $existing['state'] === 0 || strtotime($existing['publish_up'] . ' UTC') > time() || $publishTs > time())) {
            $bind['created'] = $publishUp;
        }
        if (!$existing) {
            $bind += array(
                'created'      => $publishUp,
                'publish_down' => $db->getNullDate(),
                'access'       => 1,
                'featured'     => 0,
                'ordering'     => 0,
                'hits'         => 0,
                'attribs'      => '{}',
                'metakey'      => '',
                'urls'         => '{}',
                'version'      => 1,
            );
        }
        if (!$table->bind($bind)) return $this->fail('Could not bind the post: ' . $table->getError());
        if (!$table->check()) return $this->fail('Post rejected: ' . $table->getError());
        if (!$table->store()) return $this->fail('Could not save the post: ' . $table->getError());
        $id = (int) $table->id;

        // author_id may be NULL: JTable skips nulls unless updateNulls, set it explicitly
        if (AuthorsHelper::contentHasAuthorColumn()) {
            $db->setQuery('UPDATE ' . $db->quoteName('#__content') . ' SET author_id = ' . ($byline['author_id'] > 0 ? (int) $byline['author_id'] : 'NULL') . ' WHERE id = ' . $id);
            $db->query();
        }

        // Side table (kind, provider, Game facts, source text for re-editing)
        $metaOk = $this->upsertMeta($id, $meta, array('source_mode' => $usedMode, 'source_text' => ($usedMode === 'plain') ? $rawBody : null,
                                                      'slot_gid' => $slotLink['gid'], 'slot_rel' => $slotLink['rel']));

        $this->clearCaches();

        $row = array('id' => $id, 'alias' => $alias, 'catid' => AuthorsHelper::blogCatid());
        return array('ok' => true, 'id' => $id, 'error' => '', 'detected' => AuthorsHelperBlogtext::describeMeta($meta + array('slot_gid' => $slotLink['gid'], 'slot_rel' => $slotLink['rel'])),
                     'url' => AuthorsHelperBlogdesk::postUrl($row), 'state' => $state, 'scheduled' => ($state === 1 && $publishTs > time()),
                     'warning' => $metaOk ? '' : 'The post is saved but its Game facts were not (blog meta table): tell the desk.');
    }

    protected function fail($msg)
    {
        return array('ok' => false, 'id' => 0, 'error' => $msg, 'detected' => '', 'url' => '', 'warning' => '');
    }

    /**
     * Which RTP-verified slot the post belongs to: array(gid, rel), both null
     * for none. $slotOpt: '' auto-detect from the title, '-' none, a gid to
     * force; $relOpt: '' auto, 'game', 'family' (2.7.72).
     */
    protected function resolveSlotLink($title, $slotOpt, $relOpt)
    {
        $none = array('gid' => null, 'rel' => null);
        if (!class_exists('AuthorsHelperBlogslot')) {
            $f = JPATH_COMPONENT . '/helpers/blogslot.php';
            if (!file_exists($f)) return $none;
            require_once $f;
        }
        $relOpt = in_array($relOpt, array('game', 'family'), true) ? $relOpt : '';
        if ($slotOpt === '-') return $none;
        if ($slotOpt !== '' && ctype_digit($slotOpt)) {
            $slot = AuthorsHelperBlogslot::find((int) $slotOpt);
            if (!$slot) return $none;
            if ($relOpt === '') {
                $m = AuthorsHelperBlogslot::match($title);
                $relOpt = ($m && $m['gid'] === (int) $slot['gid']) ? $m['rel'] : 'family';
            }
            return array('gid' => (int) $slot['gid'], 'rel' => $relOpt);
        }
        $m = AuthorsHelperBlogslot::match($title);
        if (!$m) return $none;
        return array('gid' => $m['gid'], 'rel' => $relOpt !== '' ? $relOpt : $m['rel']);
    }

    /** Who signs the post: array(user_id, alias, author_id) or null. */
    protected function resolveByline($ctx, $choice, $existing)
    {
        $author = isset($ctx['author']) ? $ctx['author'] : null;
        if (empty($ctx['is_desk'])) {
            if (!$author) return null;
            return array('user_id' => (int) $author['joomla_user_id'] > 0 ? (int) $author['joomla_user_id'] : (int) $ctx['user']->id,
                         'alias' => $author['name'], 'author_id' => (int) $author['id']);
        }
        // desk user: explicit choice, else keep the existing byline, else the desk user's own profile, else Editorial
        if ($choice === 'editorial') {
            return array('user_id' => AuthorsHelperBlogdesk::EDITORIAL_USER_ID, 'alias' => AuthorsHelperBlogdesk::EDITORIAL_ALIAS, 'author_id' => 0);
        }
        if ($choice !== '' && ctype_digit($choice)) {
            foreach (AuthorsHelperBlogdesk::activeAuthors() as $a) {
                if ((int) $a['id'] === (int) $choice) {
                    return array('user_id' => (int) $a['joomla_user_id'] > 0 ? (int) $a['joomla_user_id'] : AuthorsHelperBlogdesk::EDITORIAL_USER_ID,
                                 'alias' => $a['name'], 'author_id' => (int) $a['id']);
                }
            }
        }
        if ($existing) {
            return array('user_id' => (int) $existing['created_by'], 'alias' => (string) $existing['created_by_alias'], 'author_id' => (int) $existing['author_id']);
        }
        if ($author) {
            return array('user_id' => (int) $ctx['user']->id, 'alias' => $author['name'], 'author_id' => (int) $author['id']);
        }
        return array('user_id' => AuthorsHelperBlogdesk::EDITORIAL_USER_ID, 'alias' => AuthorsHelperBlogdesk::EDITORIAL_ALIAS, 'author_id' => 0);
    }

    /** 'Y-m-d H:i' (or datetime-local 'Y-m-dTH:i') in the site timezone -> UTC SQL; null when empty/invalid. */
    protected function toUtcSql($local)
    {
        $local = trim(str_replace('T', ' ', (string) $local));
        if ($local === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $local)) return null;
        $tz = new DateTimeZone(JFactory::getConfig()->get('offset', 'UTC'));
        try {
            $d = new DateTime($local, $tz);
        } catch (Exception $e) { return null; }
        $d->setTimezone(new DateTimeZone('UTC'));
        return $d->format('Y-m-d H:i:s');
    }

    /** URL-safe alias from the title, unique within the blog category. */
    public function uniqueAlias($title)
    {
        $alias = JFilterOutput::stringURLSafe($title);
        if (trim(str_replace('-', '', $alias)) === '') $alias = JFactory::getDate()->format('Y-m-d-H-i-s');
        $db = JFactory::getDbo();
        $base = $alias; $n = 2;
        while (true) {
            $db->setQuery('SELECT id FROM ' . $db->quoteName('#__content') . ' WHERE alias = ' . $db->quote($alias) . ' AND catid = ' . AuthorsHelper::blogCatid());
            if (!$db->loadResult()) return $alias;
            $alias = $base . '-' . $n++;
        }
    }

    /** Insert/update the #__fec_blog_meta row. */
    public function upsertMeta($contentId, $meta, $extra = array())
    {
        $db = JFactory::getDbo();
        $now = JFactory::getDate()->toSql();
        $cols = array(
            'kind'          => $meta['kind'],
            'provider_slug' => $meta['provider_slug'],
            'provider_name' => $meta['provider_name'],
            'release_date'  => $meta['release_date'],
            'rtp'           => $meta['rtp'],
            'volatility'    => $meta['volatility'],
            'max_win_x'     => $meta['max_win_x'],
            'grid'          => $meta['grid'],
            'import_flags'  => 'desk',
            'updated_at'    => $now,
        );
        // optional columns added by 2.7.66.sql
        foreach ($extra as $k => $v) $cols[$k] = $v;
        $q = function ($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); };
        $names = array(); $vals = array(); $upd = array();
        foreach ($cols as $k => $v) { $names[] = $db->quoteName($k); $vals[] = $q($v); $upd[] = $db->quoteName($k) . ' = VALUES(' . $db->quoteName($k) . ')'; }
        $sql = 'INSERT INTO ' . $db->quoteName('#__fec_blog_meta') . ' (content_id, imported_at, ' . implode(', ', $names) . ')'
             . ' VALUES (' . (int) $contentId . ', ' . $db->quote($now) . ', ' . implode(', ', $vals) . ')'
             . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $upd);
        $db->setQuery($sql);
        $db->query();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelBlogdesk::upsertMeta: ' . $db->getErrorMsg());
            // retry without the optional columns (migration not applied yet)
            if ($extra) return $this->upsertMeta($contentId, $meta, array());
            return false;
        }
        return true;
    }

    /** Re-encode the upload through GD into images/blog/YYYY/<alias>.jpg. Returns array(ok, path|error). */
    public function saveImage($file, $alias, $ts)
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) return array('ok' => false, 'error' => 'Image upload failed (code ' . (int) $file['error'] . ').');
        if ((int) $file['size'] > self::MAX_IMAGE_BYTES) return array('ok' => false, 'error' => 'Image too big (10 MB max).');
        if (!is_uploaded_file($file['tmp_name'])) return array('ok' => false, 'error' => 'Image upload failed.');
        $info = @getimagesize($file['tmp_name']);
        if (!$info || empty($info['mime'])) return array('ok' => false, 'error' => 'The file is not an image.');
        $mime = $info['mime'];
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) return array('ok' => false, 'error' => 'Use a JPG, PNG, WebP or GIF image.');
        if (!function_exists('imagecreatetruecolor')) return array('ok' => false, 'error' => 'GD is not available on this server.');

        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']); break;
            case 'image/gif':  $src = @imagecreatefromgif($file['tmp_name']); break;
            case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : null; break;
            default: $src = null;
        }
        if (!$src) return array('ok' => false, 'error' => 'The image could not be read.');
        $w = imagesx($src); $h = imagesy($src);
        if ($w < 400 || $h < 200) { imagedestroy($src); return array('ok' => false, 'error' => 'The image is too small (at least 400x200).'); }
        $long = max($w, $h);
        if ($long > self::MAX_LONG_EDGE) {
            $ratio = self::MAX_LONG_EDGE / $long;
            $nw = (int) round($w * $ratio); $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            $bg = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $bg);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src); $src = $dst;
        } elseif ($mime !== 'image/jpeg') {
            // flatten transparency on white for the JPEG
            $dst = imagecreatetruecolor($w, $h);
            $bg = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $w, $h, $bg);
            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
            imagedestroy($src); $src = $dst;
        }
        $year = date('Y', $ts ? $ts : time());
        $relDir = (defined('FEC_BLOG_IMAGE_DIR') ? trim(FEC_BLOG_IMAGE_DIR, '/') : self::IMAGE_DIR) . '/' . $year;
        $absDir = JPATH_ROOT . '/' . $relDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) { imagedestroy($src); return array('ok' => false, 'error' => 'Cannot create the image folder.'); }
        $stub = preg_replace('/[^a-z0-9-]/', '', strtolower($alias));
        if ($stub === '') $stub = 'post';
        $rel = $relDir . '/' . $stub . '.jpg';
        if (file_exists(JPATH_ROOT . '/' . $rel)) $rel = $relDir . '/' . $stub . '-' . date('His') . '.jpg';
        $ok = imagejpeg($src, JPATH_ROOT . '/' . $rel, self::JPEG_QUALITY);
        imagedestroy($src);
        if (!$ok) return array('ok' => false, 'error' => 'The image could not be written.');
        @chmod(JPATH_ROOT . '/' . $rel, 0644);
        return array('ok' => true, 'path' => $rel);
    }

    /** Content + page caches, so the listing, the home section and the post are fresh. */
    public function clearCaches()
    {
        foreach (array('com_content', 'page', 'com_authors') as $group) {
            try {
                $cache = JFactory::getCache($group);
                if ($cache) $cache->clean();
            } catch (Exception $e) { /* cache handler may be off */ }
        }
    }
}
