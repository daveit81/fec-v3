<?php
/**
 * FEC Review Dashboard — JSON API
 * ================================
 * Licence Watch desk actions (Oct 2026): lw_list, lw_get, lw_freeze, lw_save,
 * lw_publish, lw_delete, atlas_entities, atlas_events, atlas_event_add,
 * atlas_event_set, atlas_event_delete — thin wrappers over
 * components/com_authors/models/licencewatch.php (com_authors 2.7.61).
 * FXClock desk actions (Sept 2026): fxc_brands, fxc_brand_set, fxc_brands_sync,
 * fxc_queue, fxc_note_set, fxc_hide_set, fxc_clock — thin wrappers over
 * /home/freeextr/fxclock/lib/fxclock_desk.php.
 * Single-file router for the frontend review-management SPA.
 * Bootstraps Joomla, guards on user id 42, dispatches on ?action=.
 *
 * Placement: web root (/home/freeextr/public_html/fec_dash_api.php)
 *
 * READ actions (this file, phase 1):
 *   GET  ?action=list                  -> all catid=8 reviews (grid)
 *   GET  ?action=get&id=N              -> full editable payload for one review
 *   GET  ?action=fieldoptions&field=X  -> {slug,label} options for a jr_ field
 *
 * WRITE actions (added phase 2): save, create, toggle_state, set_cofm.
 *
 * Multi-value jr_ fields use JReviews' *slug1*slug2* storage format; read
 * via fec_parse_starlist(), written via fec_build_starlist() (added phase 2).
 * Stored values are SLUGS; labels resolve through #__jreviews_fieldoptions.
 */

// --- 1. Joomla bootstrap ---
// PORT: the script bootstrapped Joomla itself from the web root (chdir, _JEXEC, JPATH_BASE,
// includes/defines.php and framework.php); here the runner boots the layer and includes it
// (ComponentRunner::runDeskFile(), fec.legacy.desk_files, logged-in redazione only), and
// $jroot is the real docroot (JPATH_ROOT): the files it reads or serves live there.
defined('_JEXEC') or die('Restricted access');
$jroot = JPATH_ROOT;

// JSON output helper — used everywhere below.
function fec_dash_json($payload, $httpCode = 200) {
    if (!headers_sent()) {
        http_response_code($httpCode);
        fec_header('Content-Type: application/json; charset=utf-8');
        fec_header('Cache-Control: no-store, no-cache, must-revalidate');
        fec_header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload);
    jexit();
}

try {
    $app = JFactory::getApplication('site');
    // initialise() loads the session (needed for getUser auth) + plugins.
    $app->initialise();
    $db   = JFactory::getDbo();
    $user = JFactory::getUser();
} catch (Exception $e) {
    fec_dash_json(array('ok' => false, 'error' => 'joomla_init_failed'), 500);
}

// --- 2. Auth guard --------------------------------------------------------
// Hardcoded to user 42 for now. Isolated here so a future role/group check
// is a one-line swap without touching any action handler.
function fec_dash_guard($user) {
    return ($user && (int) $user->get('id') === 42);
}
if (!fec_dash_guard($user)) {
    fec_dash_json(array('ok' => false, 'error' => 'forbidden'), 403);
}

// Library (parsing helpers, fieldoptions map). Required for read + write.
$libPath = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';
if (is_readable($libPath)) {
    require_once $libPath;
}

// --- Write helpers --------------------------------------------------------

// Read + decode the JSON request body once (writes send application/json).
function fec_dash_input() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    $cache = is_array($decoded) ? $decoded : array();
    return $cache;
}

// CSRF: the SPA shell emits JSession::getFormToken() into the page; the React
// client echoes it back as `_token` in the JSON body (or X-CSRF-Token header).
// hash_equals (PHP 5.6+) avoids timing leaks.
function fec_dash_check_csrf() {
    $input = fec_dash_input();
    $sent  = isset($input['_token']) ? (string) $input['_token'] : '';
    if ($sent === '' && isset($_POST['_token'])) {
        $sent = (string) $_POST['_token']; // multipart uploads
    }
    if ($sent === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $sent = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    $expected = JSession::getFormToken();
    return ($sent !== '' && hash_equals($expected, $sent));
}

// Whitelists — only these columns are ever written.
$FEC_DASH_CONTENT_FIELDS = array(
    'title', 'alias', 'introtext', 'fulltext',
    'state', 'language', 'publish_up', 'metadesc', 'metakey',
);
// jr_ fields that hold *slug*slug* multi-value lists (serialized on save)
$FEC_DASH_JR_MULTI = array(
    'jr_soft', 'jr_banking', 'jr_languages', 'jr_currencies',
    'jr_casinotype', 'jr_jurisdiction', 'jr_restrictedcountries',
);
// jr_ scalar fields (written as-is)
$FEC_DASH_JR_SCALAR = array(
    'jr_established', 'jr_owner', 'jr_network', 'jr_officialwebsite', 'jr_gourl',
    'jr_logosmall', 'jr_logowebp',
    'jr_livesupport',
    'jr_warning', 'jr_warningtitle', 'jr_cofmdate',
    'jr_flag', 'jr_flagreason', 'jr_flagexpl',
    'jr_suspreason', 'jr_susexpl', 'jr_blreason', 'jr_blexplanation',
    'jr_pendingperiod',
    'jr_ewalletswithdrawaltimes', 'jr_ewalletswithdrawallimits',
    'jr_cardswithdrawaltimes',    'jr_cardswithdrawallimits',
    'jr_bankwithdrawaltimes',     'jr_bankwithdrawallimits',
    'jr_bitcoinwithdrawaltimes',  'jr_bitcoinwithdrawallimits',
    'jr_checkwithdrawaltimes',    'jr_checkwithdrawallimits',
);

// State flag "magic" storage values — confirmed via inspect_flags against the
// live DB. jr_closed reads as non-empty; jr_suspended must match *susp*;
// jr_blacklisted must match *bl* (the reader uses case-sensitive fnmatch on
// these exact lowercase tokens). Mutually exclusive: setting one clears others.
$FEC_DASH_STATE_MAP = array(
    'active'      => array('jr_closed' => '',      'jr_suspended' => '',       'jr_blacklisted' => ''),
    'closed'      => array('jr_closed' => '*yes*', 'jr_suspended' => '',       'jr_blacklisted' => ''),
    'suspended'   => array('jr_closed' => '',      'jr_suspended' => '*susp*', 'jr_blacklisted' => ''),
    'blacklisted' => array('jr_closed' => '',      'jr_suspended' => '',       'jr_blacklisted' => '*bl*'),
);

// --- 3. Dispatch ----------------------------------------------------------
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

switch ($action) {

    // ---- LIST: all catid=8 reviews for the grid --------------------------
    case 'list':
        // Deliberately NOT filtered on state=1 — the dashboard manages
        // unpublished / closed / blacklisted casinos too.
        $q = $db->getQuery(true)
            ->select(array(
                'c.id', 'c.title', 'c.alias', 'c.state', 'c.modified',
                'jc.jr_established',
                'jc.jr_closed', 'jc.jr_suspended', 'jc.jr_blacklisted',
                'jc.jr_cofmdate',
                'f.frankvalue AS frank_total',
                'f.rank AS frank_rank',
                'jt.user_comment_count',
            ))
            ->from($db->quoteName('#__content', 'c'))
            ->join('INNER', $db->quoteName('#__jreviews_content', 'jc') . ' ON jc.contentid = c.id')
            ->join('LEFT',  $db->quoteName('#__frank', 'f') . ' ON f.id = c.id')
            ->join('LEFT',  $db->quoteName('#__jreviews_listing_totals', 'jt')
                . ' ON (jt.listing_id = c.id AND jt.extension = ' . $db->quote('com_content') . ')')
            ->where('c.catid = 8')
            ->order('c.title ASC');
        $db->setQuery($q);
        $rows = $db->loadAssocList();
        if ($db->getErrorNum()) {
            fec_dash_json(array('ok' => false, 'error' => 'query_failed', 'detail' => $db->getErrorMsg()), 500);
        }

        $reviews = array();
        foreach ((array) $rows as $r) {
            $cofm = (string) (isset($r['jr_cofmdate']) ? $r['jr_cofmdate'] : '');
            $cofmClean = ($cofm !== '' && strpos($cofm, '0000-00-00') !== 0) ? $cofm : null;
            // founded: parse 4-digit year out of jr_established (may be "2013"
            // or "2013-04-15" or datetime)
            $est = (string) (isset($r['jr_established']) ? $r['jr_established'] : '');
            $founded = null;
            if ($est !== '' && preg_match('/(\d{4})/', $est, $m)) {
                $founded = (int) $m[1];
            }
            $reviews[] = array(
                'id'        => (int) $r['id'],
                'title'     => (string) $r['title'],
                'alias'     => (string) $r['alias'],
                'state'     => (int) $r['state'],
                'founded'   => $founded,
                'frank'     => isset($r['frank_total']) ? (float) $r['frank_total'] : null,
                'rank'      => isset($r['frank_rank']) ? (int) $r['frank_rank'] : null,
                'reviews'   => isset($r['user_comment_count']) ? (int) $r['user_comment_count'] : 0,
                'cofm_date' => $cofmClean,
                'flags'     => array(
                    'closed'      => !empty($r['jr_closed']),
                    'suspended'   => !empty($r['jr_suspended']),
                    'blacklisted' => !empty($r['jr_blacklisted']),
                ),
                'modified'  => (string) $r['modified'],
            );
        }
        fec_dash_json(array('ok' => true, 'reviews' => $reviews, 'total' => count($reviews)));
        break;

    // ---- GET: full editable payload for one review -----------------------
    case 'get':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_id'), 400);
        }

        // content row
        $qc = $db->getQuery(true)
            ->select(array(
                'c.id', 'c.title', 'c.alias', 'c.introtext', 'c.fulltext',
                'c.state', 'c.language', 'c.publish_up', 'c.created', 'c.modified',
                'c.images', 'c.metadesc', 'c.metakey', 'c.author_id',
            ))
            ->from($db->quoteName('#__content', 'c'))
            ->where('c.id = ' . $id)
            ->where('c.catid = 8');
        $db->setQuery($qc);
        $content = $db->loadAssoc();
        if ($db->getErrorNum()) {
            fec_dash_json(array('ok' => false, 'error' => 'query_failed', 'detail' => $db->getErrorMsg()), 500);
        }
        if (!$content) {
            fec_dash_json(array('ok' => false, 'error' => 'not_found'), 404);
        }

        // jreviews_content row (all jr_* columns)
        $qj = $db->getQuery(true)
            ->select('jc.*')
            ->from($db->quoteName('#__jreviews_content', 'jc'))
            ->where('jc.contentid = ' . $id);
        $db->setQuery($qj);
        $jr = $db->loadAssoc();
        if (!$jr) $jr = array();
        // contentid is internal plumbing, drop from the editable payload
        unset($jr['contentid']);

        // read-only computed values (frank + ratings)
        $qf = $db->getQuery(true)
            ->select(array(
                'f.frankvalue AS frank_total', 'f.fvalue AS frank_facts',
                'f.rvalue AS frank_reviews', 'f.rcount AS frank_review_count',
                'f.rank AS frank_rank',
            ))
            ->from($db->quoteName('#__frank', 'f'))
            ->where('f.id = ' . $id);
        $db->setQuery($qf);
        $frank = $db->loadAssoc();
        if (!$frank) $frank = array();

        $qt = $db->getQuery(true)
            ->select(array('jt.user_rating', 'jt.user_rating_count', 'jt.user_comment_count'))
            ->from($db->quoteName('#__jreviews_listing_totals', 'jt'))
            ->where('jt.listing_id = ' . $id)
            ->where('jt.extension = ' . $db->quote('com_content'));
        $db->setQuery($qt);
        $totals = $db->loadAssoc();
        if (!$totals) $totals = array();

        fec_dash_json(array(
            'ok'      => true,
            'review'  => array(
                'id'       => $id,
                'content'  => $content,
                'jr'       => $jr,
                'readonly' => array(
                    'frank_total'        => isset($frank['frank_total']) ? (float) $frank['frank_total'] : null,
                    'frank_facts'        => isset($frank['frank_facts']) ? (float) $frank['frank_facts'] : null,
                    'frank_reviews'      => isset($frank['frank_reviews']) ? (float) $frank['frank_reviews'] : null,
                    'frank_review_count' => isset($frank['frank_review_count']) ? (int) $frank['frank_review_count'] : 0,
                    'frank_rank'         => isset($frank['frank_rank']) ? (int) $frank['frank_rank'] : null,
                    'user_rating'        => isset($totals['user_rating']) ? (float) $totals['user_rating'] : null,
                    'user_rating_count'  => isset($totals['user_rating_count']) ? (int) $totals['user_rating_count'] : 0,
                    'user_comment_count' => isset($totals['user_comment_count']) ? (int) $totals['user_comment_count'] : 0,
                ),
            ),
        ));
        break;

    // ---- FIELDOPTIONS: {slug,label} list for a jr_ multi-select ----------
    case 'fieldoptions':
        $field = isset($_GET['field']) ? (string) $_GET['field'] : '';
        // whitelist — only fields we actually edit
        $allowed = array(
            'jr_soft', 'jr_banking', 'jr_languages', 'jr_currencies',
            'jr_jurisdiction', 'jr_casinotype', 'jr_multiplesoftware',
            'jr_pendingperiod', 'jr_withdrawaltimes',
        );
        if (!in_array($field, $allowed, true)) {
            fec_dash_json(array('ok' => false, 'error' => 'field_not_allowed'), 400);
        }
        if (!function_exists('fec_get_fieldoptions_map')) {
            fec_dash_json(array('ok' => false, 'error' => 'lib_unavailable'), 500);
        }
        $map = fec_get_fieldoptions_map($db, array($field));
        $opts = array();
        if (isset($map[$field]) && is_array($map[$field])) {
            foreach ($map[$field] as $slug => $label) {
                $opts[] = array('slug' => (string) $slug, 'label' => (string) $label);
            }
        }
        fec_dash_json(array('ok' => true, 'field' => $field, 'options' => $opts));
        break;

    // ---- INSPECT_FLAGS: diagnostic - reveal real DB values for state flags
    // (jr_closed/suspended/blacklisted use "magic" values the toggle must
    // match exactly; this surfaces them without manual SQL). Read-only.
    case 'inspect_flags':
        $out = array();
        foreach (array('jr_closed', 'jr_suspended', 'jr_blacklisted') as $col) {
            $q = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName($col))
                ->from($db->quoteName('#__jreviews_content'))
                ->where($db->quoteName($col) . ' <> ' . $db->quote(''));
            // Joomla 2.5: limit is the 3rd arg of setQuery (no $query->setLimit)
            $db->setQuery($q, 0, 15);
            $out[$col] = $db->loadColumn();
        }
        // sample cofm date format
        $q2 = $db->getQuery(true)
            ->select('DISTINCT jr_cofmdate')
            ->from($db->quoteName('#__jreviews_content'))
            ->where($db->quoteName('jr_cofmdate') . ' <> ' . $db->quote(''))
            ->where($db->quoteName('jr_cofmdate') . ' NOT LIKE ' . $db->quote('0000%'));
        $db->setQuery($q2, 0, 5);
        $out['jr_cofmdate'] = $db->loadColumn();
        fec_dash_json(array('ok' => true, 'flag_values' => $out));
        break;

    // ---- SAVE: update #__content + #__jreviews_content -------------------
    case 'save':
        if ($method !== 'POST') {
            fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        }
        if (!fec_dash_check_csrf()) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        $input  = fec_dash_input();
        $id     = isset($input['id']) ? (int) $input['id'] : 0;
        $dryrun = !empty($input['dryrun']);
        if ($id <= 0) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_id'), 400);
        }

        $contentIn = isset($input['content']) && is_array($input['content']) ? $input['content'] : array();
        $jrIn      = isset($input['jr']) && is_array($input['jr']) ? $input['jr'] : array();

        // Build #__content update object from whitelist
        $cObj = new stdClass();
        $cObj->id = $id;
        foreach ($FEC_DASH_CONTENT_FIELDS as $f) {
            if (array_key_exists($f, $contentIn)) {
                $cObj->$f = is_array($contentIn[$f]) ? '' : (string) $contentIn[$f];
            }
        }
        // audit fields
        $cObj->modified    = JFactory::getDate()->toSql();
        $cObj->modified_by = 42;
        // author_id (FK to casino_authors) — explicit int, 0 = none
        if (array_key_exists('author_id', $contentIn)) {
            $cObj->author_id = (int) $contentIn['author_id'];
        }

        // Build #__jreviews_content update object from whitelist
        $jObj = new stdClass();
        $jObj->contentid = $id;
        foreach ($FEC_DASH_JR_MULTI as $f) {
            if (array_key_exists($f, $jrIn)) {
                // accept either an array (preferred) or a pre-serialized string
                $jObj->$f = is_array($jrIn[$f])
                    ? fec_build_starlist($jrIn[$f])
                    : (string) $jrIn[$f];
            }
        }
        foreach ($FEC_DASH_JR_SCALAR as $f) {
            if (array_key_exists($f, $jrIn)) {
                $jObj->$f = is_array($jrIn[$f]) ? '' : (string) $jrIn[$f];
            }
        }

        if ($dryrun) {
            // Return exactly what WOULD be written, without touching the DB.
            // Lets the client compare serialized multi-value fields against
            // the real stored format before committing destructive UPDATEs.
            fec_dash_json(array(
                'ok'      => true,
                'dryrun'  => true,
                'content' => $cObj,
                'jr'      => $jObj,
            ));
        }

        $db->transactionStart();
        try {
            $db->updateObject('#__content', $cObj, 'id');
            // jreviews row may not have every column; updateObject only sets
            // the properties present on $jObj, so partial updates are safe.
            $db->updateObject('#__jreviews_content', $jObj, 'contentid');
            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            fec_dash_json(array('ok' => false, 'error' => 'write_failed', 'detail' => $e->getMessage()), 500);
        }
        fec_dash_json(array('ok' => true, 'id' => $id, 'saved' => true));
        break;

    // ---- SET_COFM: assign/clear Casino-of-the-Month date (single or bulk)
    case 'set_cofm':
        if ($method !== 'POST') {
            fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        }
        if (!fec_dash_check_csrf()) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        $input = fec_dash_input();
        // ids[] for bulk, or single id
        $ids = array();
        if (!empty($input['ids']) && is_array($input['ids'])) {
            foreach ($input['ids'] as $v) { $v = (int) $v; if ($v > 0) $ids[] = $v; }
        } elseif (!empty($input['id'])) {
            $ids[] = (int) $input['id'];
        }
        if (empty($ids)) {
            fec_dash_json(array('ok' => false, 'error' => 'no_ids'), 400);
        }
        // date: 'YYYY-MM-DD' to set, or '' to clear
        $date = isset($input['date']) ? trim((string) $input['date']) : '';
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_date'), 400);
        }
        // Stored as datetime (YYYY-MM-DD 00:00:00) - confirmed via inspect_flags
        $stored = ($date === '') ? '' : ($date . ' 00:00:00');

        $db->transactionStart();
        try {
            foreach ($ids as $cid) {
                $o = new stdClass();
                $o->contentid  = $cid;
                $o->jr_cofmdate = $stored; // '' clears it
                $db->updateObject('#__jreviews_content', $o, 'contentid');
            }
            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            fec_dash_json(array('ok' => false, 'error' => 'write_failed', 'detail' => $e->getMessage()), 500);
        }
        fec_dash_json(array('ok' => true, 'updated' => count($ids), 'date' => $stored));
        break;

    // ---- TOGGLE_STATE: set active/closed/suspended/blacklisted (single/bulk)
    case 'toggle_state':
        if ($method !== 'POST') {
            fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        }
        if (!fec_dash_check_csrf()) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        $input  = fec_dash_input();
        $target = isset($input['target']) ? (string) $input['target'] : '';
        if (!isset($FEC_DASH_STATE_MAP[$target])) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_target'), 400);
        }
        $ids = array();
        if (!empty($input['ids']) && is_array($input['ids'])) {
            foreach ($input['ids'] as $v) { $v = (int) $v; if ($v > 0) $ids[] = $v; }
        } elseif (!empty($input['id'])) {
            $ids[] = (int) $input['id'];
        }
        if (empty($ids)) {
            fec_dash_json(array('ok' => false, 'error' => 'no_ids'), 400);
        }
        $flags = $FEC_DASH_STATE_MAP[$target];

        $db->transactionStart();
        try {
            foreach ($ids as $cid) {
                $o = new stdClass();
                $o->contentid      = $cid;
                $o->jr_closed      = $flags['jr_closed'];
                $o->jr_suspended   = $flags['jr_suspended'];
                $o->jr_blacklisted = $flags['jr_blacklisted'];
                $db->updateObject('#__jreviews_content', $o, 'contentid');
            }
            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            fec_dash_json(array('ok' => false, 'error' => 'write_failed', 'detail' => $e->getMessage()), 500);
        }
        fec_dash_json(array('ok' => true, 'updated' => count($ids), 'target' => $target));
        break;

    // ---- CREATE: new article (catid=8) + jreviews_content row ------------
    case 'create':
        if ($method !== 'POST') {
            fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        }
        if (!fec_dash_check_csrf()) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        $input     = fec_dash_input();
        $contentIn = isset($input['content']) && is_array($input['content']) ? $input['content'] : array();

        $title = isset($contentIn['title']) ? trim((string) $contentIn['title']) : '';
        if ($title === '') {
            fec_dash_json(array('ok' => false, 'error' => 'title_required'), 400);
        }
        // Alias: provided or slugify the title; ensure uniqueness in catid=8
        $alias = isset($contentIn['alias']) ? trim((string) $contentIn['alias']) : '';
        if ($alias === '') {
            $alias = JFilterOutput::stringURLSafe($title);
        }
        if ($alias === '') {
            $alias = 'casino-' . time();
        }

        // Use JTableContent so all NOT-NULL columns + asset_id are handled.
        JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');
        $table = JTable::getInstance('Content');
        if (!$table) {
            fec_dash_json(array('ok' => false, 'error' => 'jtable_unavailable'), 500);
        }

        $introtext = isset($contentIn['introtext']) ? (string) $contentIn['introtext'] : '';
        if (trim($introtext) === '') {
            // JTableContent::check() rejects an article with both introtext and
            // fulltext empty ("Article must have some content"). Seed a tiny,
            // invisible placeholder so the create call passes; the body is then
            // authored normally in the edit view.
            $introtext = "<!-- new draft -->\n<p></p>";
        }

        $now = JFactory::getDate()->toSql();
        $bind = array(
            'title'       => $title,
            'alias'       => $alias,
            'introtext'   => $introtext,
            'fulltext'    => isset($contentIn['fulltext']) ? (string) $contentIn['fulltext'] : '',
            'state'       => isset($contentIn['state']) ? (int) $contentIn['state'] : 0, // default unpublished
            'catid'       => 8,
            'created'     => $now,
            'created_by'  => 42,
            'modified'    => $now,
            'modified_by' => 42,
            'publish_up'  => $now,
            'language'    => isset($contentIn['language']) ? (string) $contentIn['language'] : '*',
            'access'      => 1,
            'metadesc'    => isset($contentIn['metadesc']) ? (string) $contentIn['metadesc'] : '',
            'metakey'     => isset($contentIn['metakey']) ? (string) $contentIn['metakey'] : '',
            'author_id'   => isset($contentIn['author_id']) ? (int) $contentIn['author_id'] : 0,
        );
        if (!$table->bind($bind) || !$table->check() || !$table->store()) {
            fec_dash_json(array('ok' => false, 'error' => 'content_create_failed', 'detail' => $table->getError()), 500);
        }
        $newId = (int) $table->id;

        // jreviews_content row — minimal, contentid links it to the article.
        // Other jr_ fields default empty; the editor fills them via save.
        $db->transactionStart();
        try {
            // Some JReviews installs require a row keyed on contentid.
            $jc = new stdClass();
            $jc->contentid = $newId;
            $db->insertObject('#__jreviews_content', $jc);
            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            // Content row exists but jreviews row failed — report so the
            // operator can decide (the article is editable in backend anyway).
            fec_dash_json(array(
                'ok'    => false,
                'error' => 'jreviews_row_failed',
                'detail' => $e->getMessage(),
                'content_id' => $newId,
            ), 500);
        }

        fec_dash_json(array('ok' => true, 'id' => $newId, 'alias' => $alias, 'created' => true));
        break;

    // ---- INSPECT_SCHEMA: diagnostic - all #__jreviews_content columns with
    // a sample value from one populated record, to map field types
    // (starlist vs scalar vs date) and surface fields not yet in the form.
    case 'inspect_schema':
        $sampleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        // pick a well-populated record if none given
        if ($sampleId <= 0) $sampleId = 789;
        $q = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jreviews_content'))
            ->where('contentid = ' . $sampleId);
        $db->setQuery($q);
        $row = $db->loadAssoc();
        if (!$row) {
            fec_dash_json(array('ok' => false, 'error' => 'sample_not_found', 'sample_id' => $sampleId), 404);
        }
        $cols = array();
        foreach ($row as $col => $val) {
            $v = (string) $val;
            $isStar = (strlen($v) >= 2 && $v[0] === '*' && substr($v, -1) === '*');
            $isDate = (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $v);
            $cols[] = array(
                'col'     => $col,
                'sample'  => (strlen($v) > 80 ? substr($v, 0, 80) . '…' : $v),
                'empty'   => ($v === ''),
                'starlist'=> $isStar,
                'datelike'=> $isDate,
            );
        }
        fec_dash_json(array('ok' => true, 'sample_id' => $sampleId, 'columns' => $cols, 'count' => count($cols)));
        break;

    // ---- UPLOAD: logo image to /images/casino-logos/ --------------------
    case 'upload':
        if ($method !== 'POST') {
            fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        }
        if (!fec_dash_check_csrf()) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            fec_dash_json(array('ok' => false, 'error' => 'no_file'), 400);
        }
        $f = $_FILES['file'];
        if (!empty($f['error'])) {
            fec_dash_json(array('ok' => false, 'error' => 'upload_error', 'code' => (int) $f['error']), 400);
        }
        if ($f['size'] > 2 * 1024 * 1024) {
            fec_dash_json(array('ok' => false, 'error' => 'too_large', 'max' => '2MB'), 400);
        }
        // extension + real MIME must agree
        $allowed = array(
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        );
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_extension', 'allowed' => array_keys($allowed)), 400);
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $f['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('getimagesize')) {
            $gi = @getimagesize($f['tmp_name']);
            $mime = $gi ? $gi['mime'] : '';
        }
        // jpeg/jpg both map to image/jpeg; allow image/* match against table
        if ($mime !== '' && $mime !== $allowed[$ext]) {
            fec_dash_json(array('ok' => false, 'error' => 'mime_mismatch', 'detected' => $mime, 'ext' => $ext), 400);
        }

        // sanitize filename; optional ?name= overrides the base (e.g. brand slug)
        $rawBase = isset($_POST['name']) && $_POST['name'] !== ''
            ? $_POST['name']
            : pathinfo($f['name'], PATHINFO_FILENAME);
        $base = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $rawBase));
        if ($base === '') $base = 'logo-' . time();
        $filename = $base . '.' . $ext;

        $destDir = JPATH_ROOT . '/images/casino-logos/'; // PORT: was JPATH_SITE (the docroot under Joomla)
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        if (!is_writable($destDir)) {
            fec_dash_json(array('ok' => false, 'error' => 'dir_not_writable', 'dir' => '/images/casino-logos/'), 500);
        }
        $dest = $destDir . $filename;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            fec_dash_json(array('ok' => false, 'error' => 'move_failed'), 500);
        }
        @chmod($dest, 0644);

        fec_dash_json(array(
            'ok'       => true,
            'filename' => $filename,
            'url'      => '/images/casino-logos/' . $filename,
        ));
        break;

    // ---- AUTHORS: list active casino_authors for the author select ------
    case 'authors':
        $q = $db->getQuery(true)
            ->select(array('a.id', 'a.slug', 'a.name', 'a.role'))
            ->from($db->quoteName('#__casino_authors', 'a'))
            ->where('a.is_active = 1')
            ->order('a.name ASC');
        $db->setQuery($q);
        $rows = $db->loadAssocList();
        if ($db->getErrorNum()) {
            fec_dash_json(array('ok' => false, 'error' => 'query_failed', 'detail' => $db->getErrorMsg()), 500);
        }
        $authors = array();
        foreach ((array) $rows as $r) {
            $authors[] = array(
                'id'   => (int) $r['id'],
                'slug' => (string) $r['slug'],
                'name' => (string) $r['name'],
                'role' => (string) $r['role'],
            );
        }
        fec_dash_json(array('ok' => true, 'authors' => $authors));
        break;

    // ---- COUNTRIES: option list for the restricted-countries picker -----
    // countriesarray.php keys are UPPERCASE (US => "United States"); the
    // jr_restrictedcountries field stores LOWERCASE codes (*us*gb*). We return
    // {slug: lowercase, label: name} so the multi-select shows names and
    // saves the codes that match storage.
    case 'countries':
        $caFile = JPATH_SITE . '/components/com_bonuses/helpers/countriesarray.php';
        $countries_array = array();
        if (is_readable($caFile)) {
            require $caFile;
        }
        $opts = array();
        if (is_array($countries_array)) {
            foreach ($countries_array as $code => $name) {
                $opts[] = array('slug' => strtolower((string) $code), 'label' => (string) $name);
            }
        }
        // sort by display name
        fec_php5_usort($opts, function ($a, $b) { return strcmp($a['label'], $b['label']); });
        fec_dash_json(array('ok' => true, 'options' => $opts));
        break;

    // ---- COFM: list rows for a period (or current+future) --------------
    case 'cofm_list':
        $period = isset($_GET['period']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['period']) : '';
        $q = $db->getQuery(true)
            ->select(array(
                'm.id', 'm.period', 'm.country_code', 'm.content_id', 'm.status',
                'm.frank_snapshot', 'm.rank_snapshot', 'm.reason',
                'm.proposed_at', 'm.decided_at', 'c.title',
            ))
            ->from($db->quoteName('#__fec_cofm', 'm'))
            ->join('LEFT', $db->quoteName('#__content', 'c') . ' ON c.id = m.content_id');
        if ($period !== '') {
            $q->where('m.period = ' . $db->quote($period));
        } else {
            $q->where('m.period >= ' . $db->quote(fec_cofm_period_now()));
        }
        $q->order('m.period DESC, m.country_code ASC');
        $db->setQuery($q);
        $rows = $db->loadAssocList();
        if ($db->getErrorNum()) {
            fec_dash_json(array('ok' => false, 'error' => 'query_failed', 'detail' => $db->getErrorMsg()), 500);
        }
        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array(
                'id'           => (int) $r['id'],
                'period'       => (string) $r['period'],
                'country_code' => (string) $r['country_code'],
                'content_id'   => (int) $r['content_id'],
                'title'        => (string) $r['title'],
                'status'       => (string) $r['status'],
                'frank'        => isset($r['frank_snapshot']) ? (float) $r['frank_snapshot'] : null,
                'rank'         => isset($r['rank_snapshot']) ? (int) $r['rank_snapshot'] : null,
                'proposed_at'  => (string) $r['proposed_at'],
                'decided_at'   => (string) $r['decided_at'],
            );
        }
        $cfg = fec_cofm_config();
        fec_dash_json(array(
            'ok'      => true,
            'rows'    => $out,
            'current' => fec_cofm_period_now(),
            'next'    => fec_cofm_period_next(),
            'targets' => $cfg['target_countries'],
        ));
        break;

    // ---- COFM: shortlist of candidates for a period/scope --------------
    case 'cofm_shortlist':
        $period  = isset($_GET['period']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['period']) : fec_cofm_period_next();
        $country = isset($_GET['country']) ? strtolower(preg_replace('/[^a-zA-Z]/', '', (string) $_GET['country'])) : '';
        $res = fec_cofm_elect($db, $period, $country, fec_cofm_config());
        fec_dash_json(array('ok' => true, 'pick' => $res['pick'], 'shortlist' => $res['shortlist']));
        break;

    // ---- COFM: generate proposals (global + target countries) ----------
    case 'cofm_generate':
        if ($method !== 'POST')         fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf())     fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in     = fec_dash_input();
        $period = isset($in['period']) ? preg_replace('/[^0-9\-]/', '', (string) $in['period']) : fec_cofm_period_next();
        $cfg    = fec_cofm_config();
        $scopes = array_merge(array(''), $cfg['target_countries']);
        $results = array();
        foreach ($scopes as $cc) {
            $res = fec_cofm_elect($db, $period, $cc, $cfg);
            $written = false;
            if ($res['pick']) $written = fec_cofm_record_proposal($db, $period, $cc, $res['pick']);
            $results[] = array(
                'country_code' => $cc,
                'pick'         => $res['pick'],
                'written'      => $written,
            );
        }
        fec_dash_json(array('ok' => true, 'period' => $period, 'results' => $results));
        break;

    // ---- COFM: approve / change a pick (status -> approved) -------------
    case 'cofm_decide':
        if ($method !== 'POST')         fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf())     fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in        = fec_dash_input();
        $period    = isset($in['period']) ? preg_replace('/[^0-9\-]/', '', (string) $in['period']) : '';
        $country   = isset($in['country']) ? strtolower(preg_replace('/[^a-zA-Z]/', '', (string) $in['country'])) : '';
        $contentId = isset($in['content_id']) ? (int) $in['content_id'] : 0;
        if ($period === '' || $contentId <= 0) {
            fec_dash_json(array('ok' => false, 'error' => 'bad_params'), 400);
        }
        // existing row?
        $q = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__fec_cofm'))
            ->where('period = ' . $db->quote($period))
            ->where('country_code = ' . $db->quote($country));
        $db->setQuery($q, 0, 1);
        $existingId = (int) $db->loadResult();
        $now = JFactory::getDate()->toSql();
        $o = new stdClass();
        $o->period       = $period;
        $o->country_code = $country;
        $o->content_id   = $contentId;
        $o->status       = 'approved';
        $o->decided_by   = 42;
        $o->decided_at   = $now;
        if ($existingId > 0) {
            $o->id = $existingId;
            $ok = $db->updateObject('#__fec_cofm', $o, 'id');
        } else {
            $o->proposed_at = $now;
            $ok = $db->insertObject('#__fec_cofm', $o);
        }
        fec_dash_json(array('ok' => (bool) $ok));
        break;

    // ---- COFM: publish a period (proposed/approved -> published) --------
    case 'cofm_publish':
        if ($method !== 'POST')         fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf())     fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in     = fec_dash_input();
        $period = isset($in['period']) ? preg_replace('/[^0-9\-]/', '', (string) $in['period']) : '';
        if ($period === '') fec_dash_json(array('ok' => false, 'error' => 'bad_params'), 400);
        $n = fec_cofm_publish_period($db, $period);
        fec_dash_json(array('ok' => true, 'published' => $n));
        break;

    // ---- COFM: remove a pick row ---------------------------------------
    case 'cofm_remove':
        if ($method !== 'POST')         fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf())     fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in      = fec_dash_input();
        $period  = isset($in['period']) ? preg_replace('/[^0-9\-]/', '', (string) $in['period']) : '';
        $country = isset($in['country']) ? strtolower(preg_replace('/[^a-zA-Z]/', '', (string) $in['country'])) : '';
        if ($period === '') fec_dash_json(array('ok' => false, 'error' => 'bad_params'), 400);
        $q = $db->getQuery(true)
            ->delete($db->quoteName('#__fec_cofm'))
            ->where('period = ' . $db->quote($period))
            ->where('country_code = ' . $db->quote($country));
        $db->setQuery($q);
        $db->execute();
        fec_dash_json(array('ok' => true));
        break;

    // ---- CLUSTERS: list owner groups (from jr_owner) + metadata + article state
    case 'clusters_list':
        $catidC = defined('FEC_CLUSTERS_CATID') ? (int) FEC_CLUSTERS_CATID : 0;
        $clusters = function_exists('fec_get_casino_clusters') ? fec_get_casino_clusters($db) : array();
        $articles = array();
        if ($catidC > 0) {
            $aq = $db->getQuery(true);
            $aq->select(array('id', 'alias', 'state'))->from($db->quoteName('#__content'))->where('catid = ' . $catidC);
            $db->setQuery($aq);
            foreach ((array) $db->loadAssocList() as $a) { $articles[$a['alias']] = $a; }
        }
        $metaRows = array();
        try {
            $mq = $db->getQuery(true);
            $mq->select('*')->from($db->quoteName('#__fec_owner_groups'));
            $db->setQuery($mq);
            foreach ((array) $db->loadAssocList('norm_key') as $nk => $rowm) { $metaRows[$nk] = $rowm; }
        } catch (Exception $e) { $metaRows = array(); }
        $out = array();
        foreach ($clusters as $c) {
            $art = ($c['slug'] && isset($articles[$c['slug']])) ? $articles[$c['slug']] : null;
            $chips = array();
            foreach (array_slice($c['members'], 0, 8) as $m) {
                $cl = fec_sister_node_color($m);
                $chips[] = array('title' => $m['title'], 'alias' => $m['alias'], 'color' => $cl[0]);
            }
            $md = isset($metaRows[$c['norm']]) ? $metaRows[$c['norm']] : array();
            $out[] = array(
                'norm' => $c['norm'], 'label' => $c['label'], 'count' => $c['count'],
                'slug' => $c['slug'], 'featured' => $c['featured'],
                'chips' => $chips, 'extra' => max(0, $c['count'] - count($chips)),
                'article' => $art ? array('id' => (int) $art['id'], 'state' => (int) $art['state']) : null,
                'meta' => array(
                    'operator'       => isset($md['operator']) ? $md['operator'] : '',
                    'licence'        => isset($md['licence']) ? $md['licence'] : '',
                    'platform'       => isset($md['platform']) ? $md['platform'] : '',
                    'historic_owner' => isset($md['historic_owner']) ? $md['historic_owner'] : '',
                    'current_owner'  => isset($md['current_owner']) ? $md['current_owner'] : '',
                    'notes'          => isset($md['notes']) ? $md['notes'] : '',
                ),
                'author_id' => isset($md['author_id']) ? (int) $md['author_id'] : 0,
            );
        }
        // Orphaned featured rows: featured in owner_groups but no live casino
        // resolves to that norm_key anymore (typical after a network
        // reassignment moved the brands into a different group).
        $liveNorms = array();
        foreach ($out as $oc) { $liveNorms[$oc['norm']] = true; }
        $orphans = array();
        foreach ($metaRows as $nk => $rowm) {
            if ((int) (isset($rowm['featured']) ? $rowm['featured'] : 0) !== 1) continue;
            if (isset($liveNorms[$nk])) continue;
            $oslug = isset($rowm['slug']) ? (string) $rowm['slug'] : '';
            $oart  = ($oslug !== '' && isset($articles[$oslug])) ? $articles[$oslug] : null;
            $orphans[] = array(
                'norm'    => $nk,
                'slug'    => $oslug,
                'label'   => isset($rowm['label']) && (string) $rowm['label'] !== '' ? (string) $rowm['label'] : $oslug,
                'article' => $oart ? array('id' => (int) $oart['id'], 'state' => (int) $oart['state']) : null,
            );
        }
        fec_dash_json(array('ok' => true, 'catid' => $catidC, 'clusters' => $out, 'orphans' => $orphans));
        break;

    // ---- CLUSTERS: unfeature only (no metadata overwrite) ----------------
    case 'clusters_unfeature':
        if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in   = fec_dash_input();
        $norm = isset($in['norm_key']) ? trim((string) $in['norm_key']) : '';
        if ($norm === '') fec_dash_json(array('ok' => false, 'error' => 'norm_required'), 400);
        try {
            $uq = $db->getQuery(true);
            $uq->update($db->quoteName('#__fec_owner_groups'))
               ->set($db->quoteName('featured') . ' = 0')
               ->set($db->quoteName('updated_at') . ' = ' . $db->quote(JFactory::getDate()->toSql()))
               ->where('norm_key = ' . $db->quote($norm));
            $db->setQuery($uq);
            $db->execute();
        } catch (Exception $e) {
            fec_dash_json(array('ok' => false, 'error' => 'save_failed', 'detail' => $e->getMessage()), 500);
        }
        fec_dash_json(array('ok' => true));
        break;

    // ---- CLUSTERS: upsert one owner-group metadata row (by norm_key) -----
    case 'clusters_save':
        if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in   = fec_dash_input();
        $norm = isset($in['norm_key']) ? trim((string) $in['norm_key']) : '';
        if ($norm === '') fec_dash_json(array('ok' => false, 'error' => 'norm_required'), 400);
        $slug = strtolower(trim((string) (isset($in['slug']) ? $in['slug'] : '')));
        $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
        $featured = !empty($in['featured']) ? 1 : 0;
        if ($featured && $slug === '') fec_dash_json(array('ok' => false, 'error' => 'slug_required_for_featured'), 400);
        $now = JFactory::getDate()->toSql();
        if ($slug !== '') {
            $sq = $db->getQuery(true);
            $sq->select('id')->from($db->quoteName('#__fec_owner_groups'))
               ->where('slug = ' . $db->quote($slug))->where('norm_key <> ' . $db->quote($norm));
            $db->setQuery($sq);
            if ($db->loadResult()) fec_dash_json(array('ok' => false, 'error' => 'slug_taken'), 409);
        }
        $eq = $db->getQuery(true);
        $eq->select('id')->from($db->quoteName('#__fec_owner_groups'))->where('norm_key = ' . $db->quote($norm));
        $db->setQuery($eq);
        $existId = (int) $db->loadResult();
        $row = new stdClass();
        $row->norm_key       = $norm;
        $row->slug           = ($slug !== '') ? $slug : ('group-' . substr(md5($norm), 0, 8));
        $row->label          = isset($in['label']) ? trim((string) $in['label']) : '';
        $row->operator       = isset($in['operator']) ? trim((string) $in['operator']) : '';
        $row->licence        = isset($in['licence']) ? trim((string) $in['licence']) : '';
        $row->platform       = isset($in['platform']) ? trim((string) $in['platform']) : '';
        $row->historic_owner = isset($in['historic_owner']) ? trim((string) $in['historic_owner']) : '';
        $row->current_owner  = isset($in['current_owner']) ? trim((string) $in['current_owner']) : '';
        $row->notes          = isset($in['notes']) ? trim((string) $in['notes']) : '';
        $row->author_id      = isset($in['author_id']) ? (int) $in['author_id'] : 0;
        $row->featured       = $featured;
        $row->status         = 'active';
        $row->updated_at     = $now;
        try {
            if ($existId) { $row->id = $existId; $db->updateObject('#__fec_owner_groups', $row, 'id'); }
            else { $row->created_at = $now; $db->insertObject('#__fec_owner_groups', $row); }
        } catch (Exception $e) {
            fec_dash_json(array('ok' => false, 'error' => 'save_failed', 'detail' => $e->getMessage()), 500);
        }
        fec_dash_json(array('ok' => true, 'slug' => $row->slug, 'featured' => $featured));
        break;

    // ---- CLUSTERS: create the Joomla cluster article (alias = slug) ------
    case 'clusters_create_article':
        if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $catidC = defined('FEC_CLUSTERS_CATID') ? (int) FEC_CLUSTERS_CATID : 0;
        if ($catidC <= 0) fec_dash_json(array('ok' => false, 'error' => 'no_clusters_catid'), 400);
        $in    = fec_dash_input();
        $slug  = strtolower(trim((string) (isset($in['slug']) ? $in['slug'] : '')));
        $label = trim((string) (isset($in['label']) ? $in['label'] : ''));
        if ($slug === '' || $label === '') fec_dash_json(array('ok' => false, 'error' => 'slug_label_required'), 400);
        $eq = $db->getQuery(true);
        $eq->select('id')->from($db->quoteName('#__content'))
           ->where('catid = ' . $catidC)->where('alias = ' . $db->quote($slug));
        $db->setQuery($eq);
        $existing = (int) $db->loadResult();
        if ($existing) fec_dash_json(array('ok' => true, 'id' => $existing, 'existed' => true));
        JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');
        $table = JTable::getInstance('Content');
        if (!$table) fec_dash_json(array('ok' => false, 'error' => 'jtable_unavailable'), 500);
        $now  = JFactory::getDate()->toSql();
        $bind = array(
            'title' => $label, 'alias' => $slug,
            'introtext' => "<!-- cluster page: intro authored in backend -->\n<p></p>",
            'fulltext' => '', 'state' => 1, 'catid' => $catidC,
            'created' => $now, 'created_by' => 42, 'modified' => $now, 'modified_by' => 42,
            'publish_up' => $now, 'language' => '*', 'access' => 1,
        );
        if (!$table->bind($bind) || !$table->check() || !$table->store()) {
            fec_dash_json(array('ok' => false, 'error' => 'create_failed', 'detail' => $table->getError()), 500);
        }
        fec_dash_json(array('ok' => true, 'id' => (int) $table->id, 'created' => true));
        break;

    // ---- CLUSTERS: load a cluster article for editing -------------------
    case 'cluster_article_get':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) fec_dash_json(array('ok' => false, 'error' => 'id_required'), 400);
        $q = $db->getQuery(true);
        $q->select(array('id', 'title', 'alias', 'introtext', 'state', 'catid'))
          ->from($db->quoteName('#__content'))->where('id = ' . $id);
        $db->setQuery($q);
        $row = $db->loadAssoc();
        if (!$row) fec_dash_json(array('ok' => false, 'error' => 'not_found'), 404);
        $authorId = 0;
        try {
            $gq = $db->getQuery(true);
            $gq->select('author_id')->from($db->quoteName('#__fec_owner_groups'))
               ->where('slug = ' . $db->quote((string) $row['alias']));
            $db->setQuery($gq, 0, 1);
            $authorId = (int) $db->loadResult();
        } catch (Exception $e) { $authorId = 0; }
        fec_dash_json(array('ok' => true, 'article' => array(
            'id' => (int) $row['id'], 'title' => $row['title'], 'alias' => $row['alias'],
            'introtext' => $row['introtext'], 'state' => (int) $row['state'], 'catid' => (int) $row['catid'],
            'author_id' => $authorId,
        )));
        break;

    // ---- CLUSTERS: save a cluster article (title + intro body) ----------
    case 'cluster_article_save':
        if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in = fec_dash_input();
        $id = isset($in['id']) ? (int) $in['id'] : 0;
        if ($id <= 0) fec_dash_json(array('ok' => false, 'error' => 'id_required'), 400);
        JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');
        $table = JTable::getInstance('Content');
        if (!$table || !$table->load($id)) fec_dash_json(array('ok' => false, 'error' => 'not_found'), 404);
        $now = JFactory::getDate()->toSql();
        if (isset($in['title']) && trim((string) $in['title']) !== '') $table->title = trim((string) $in['title']);
        $intro = isset($in['introtext']) ? (string) $in['introtext'] : (string) $table->introtext;
        if (trim($intro) === '') $intro = "<!-- cluster page -->\n<p></p>"; // JTableContent rejects empty body
        $table->introtext   = $intro;
        $table->modified     = $now;
        $table->modified_by  = 42;
        if (isset($in['state'])) $table->state = !empty($in['state']) ? 1 : 0;
        if (!$table->check() || !$table->store()) {
            fec_dash_json(array('ok' => false, 'error' => 'save_failed', 'detail' => $table->getError()), 500);
        }
        if (isset($in['author_id'])) {
            $aid = (int) $in['author_id'];
            try {
                $uq = $db->getQuery(true);
                $uq->update($db->quoteName('#__fec_owner_groups'))
                   ->set($db->quoteName('author_id') . ' = ' . $aid)
                   ->where('slug = ' . $db->quote((string) $table->alias));
                $db->setQuery($uq);
                $db->execute();
            } catch (Exception $e) {}
        }
        fec_dash_json(array('ok' => true, 'id' => $id, 'alias' => $table->alias));
        break;

    // ---- AUTHORS: active author roster for the cluster author picker -----
    case 'authors_list':
        $authors = array();
        try {
            $q = $db->getQuery(true);
            $q->select(array('id', 'name', 'role'))
              ->from($db->quoteName('#__casino_authors'))
              ->where('is_active = 1')
              ->order('name ASC');
            $db->setQuery($q);
            foreach ((array) $db->loadAssocList() as $a) {
                $authors[] = array('id' => (int) $a['id'], 'name' => $a['name'],
                                   'role' => isset($a['role']) ? $a['role'] : '');
            }
        } catch (Exception $e) { $authors = array(); }
        fec_dash_json(array('ok' => true, 'authors' => $authors));
        break;

    // ---- CLUSTERS INDEX: load the base-page settings (single row) -------
    case 'clusters_index_get':
        $row = null;
        try {
            $q = $db->getQuery(true);
            $q->select(array('title', 'intro_html', 'author_id'))
              ->from($db->quoteName('#__fec_clusters_index'))->where('id = 1');
            $db->setQuery($q, 0, 1);
            $row = $db->loadAssoc();
        } catch (Exception $e) { $row = null; }
        fec_dash_json(array('ok' => true, 'index' => array(
            'title'      => ($row && isset($row['title'])) ? $row['title'] : '',
            'intro_html' => ($row && isset($row['intro_html'])) ? $row['intro_html'] : '',
            'author_id'  => ($row && isset($row['author_id'])) ? (int) $row['author_id'] : 0,
        )));
        break;

    // ---- CLUSTERS INDEX: save the base-page settings (single row) -------
    case 'clusters_index_save':
        if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
        if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        $in  = fec_dash_input();
        $now = JFactory::getDate()->toSql();
        $row = new stdClass();
        $row->id         = 1;
        $row->title      = isset($in['title']) ? trim((string) $in['title']) : '';
        $row->intro_html = isset($in['intro_html']) ? (string) $in['intro_html'] : '';
        $row->author_id  = isset($in['author_id']) ? (int) $in['author_id'] : 0;
        $row->updated_at = $now;
        try {
            $eq = $db->getQuery(true);
            $eq->select('id')->from($db->quoteName('#__fec_clusters_index'))->where('id = 1');
            $db->setQuery($eq);
            if ((int) $db->loadResult()) {
                $db->updateObject('#__fec_clusters_index', $row, 'id');
            } else {
                $db->insertObject('#__fec_clusters_index', $row);
            }
        } catch (Exception $e) {
            fec_dash_json(array('ok' => false, 'error' => 'save_failed', 'detail' => $e->getMessage()), 500);
        }
        fec_dash_json(array('ok' => true));
        break;

    // ---- FXCLOCK (FEC Tools No. 04): desk view ---------------------------
    // The FXClock lib lives outside the webroot (/home/freeextr/fxclock). The
    // desk can toggle brand flags, approve/reject a note, hide/unhide a clock
    // with a reason; it can never start, stop, re-time or re-grade a clock.
    case 'fxc_brands':
    case 'fxc_brand_set':
    case 'fxc_brands_sync':
    case 'fxc_queue':
    case 'fxc_note_set':
    case 'fxc_hide_set':
    case 'fxc_clock':
        $fxcBoot = dirname(JPATH_SITE) . '/fxclock/bootstrap.php';
        if (!is_readable($fxcBoot)) $fxcBoot = '/home/freeextr/fxclock/bootstrap.php';
        if (!is_readable($fxcBoot)) fec_dash_json(array('ok' => false, 'error' => 'fxclock_missing'), 500);
        require_once $fxcBoot;
        require_once FXC_ROOT . '/lib/fxclock_desk.php';
        $fxcWrite = in_array($action, array('fxc_brand_set', 'fxc_brands_sync', 'fxc_note_set', 'fxc_hide_set'), true);
        if ($fxcWrite) {
            if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
            if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        $in = $fxcWrite ? fec_dash_input() : $_GET;
        try {
            switch ($action) {
                case 'fxc_brands':
                    fec_dash_json(array('ok' => true, 'brands' => fxc_desk_brands()));
                    break;
                case 'fxc_brand_set':
                    fec_dash_json(array('ok' => true, 'brand' => fxc_desk_brand_set(
                        isset($in['brand_slug']) ? (string) $in['brand_slug'] : '',
                        array_key_exists('enabled', $in) ? (bool) (int) $in['enabled'] : null,
                        array_key_exists('ftd_match_enabled', $in) ? (bool) (int) $in['ftd_match_enabled'] : null)));
                    break;
                case 'fxc_brands_sync':
                    fec_dash_json(array('ok' => true, 'report' => fxc_brands_sync(8, !empty($in['dry']))));
                    break;
                case 'fxc_queue':
                    $q = fxc_desk_queue();
                    fec_dash_json(array('ok' => true, 'notes' => $q['notes'], 'flagged' => $q['flagged']));
                    break;
                case 'fxc_note_set':
                    fec_dash_json(array('ok' => true, 'note' => fxc_desk_note_set(
                        isset($in['id']) ? (int) $in['id'] : 0, isset($in['status']) ? (string) $in['status'] : '')));
                    break;
                case 'fxc_hide_set':
                    fec_dash_json(array('ok' => true, 'clock' => fxc_desk_hide_set(
                        isset($in['id']) ? (int) $in['id'] : 0, !empty($in['hidden']), isset($in['reason']) ? (string) $in['reason'] : '')));
                    break;
                case 'fxc_clock':
                    fec_dash_json(array('ok' => true, 'clock' => fxc_desk_clock(isset($in['public_id']) ? (string) $in['public_id'] : '')));
                    break;
            }
        } catch (FxcError $e) {
            fec_dash_json(array('ok' => false, 'error' => $e->getMessage(), 'code' => $e->code_str), $e->http);
        } catch (Exception $e) {
            fec_dash_json(array('ok' => false, 'error' => 'fxclock_failed', 'detail' => $e->getMessage()), 500);
        }
        break;

    // ---- LICENCE WATCH (com_authors 2.7.61): quarterly licence register ----
    // Editions are frozen from the live DB (lw_freeze), the desk writes three
    // HTML sections (lw_save) and publishes (lw_publish). Atlas licence events
    // feed the next edition's "what moved" (atlas_event_*). The model lives in
    // com_authors so the public page and the desk compute the same numbers.
    case 'lw_list':
    case 'lw_get':
    case 'lw_freeze':
    case 'lw_save':
    case 'lw_publish':
    case 'lw_delete':
    case 'atlas_entities':
    case 'atlas_events':
    case 'atlas_event_add':
    case 'atlas_event_set':
    case 'atlas_event_delete':
        $lwModel = JPATH_SITE . '/components/com_authors/models/licencewatch.php';
        if (!is_readable($lwModel)) fec_dash_json(array('ok' => false, 'error' => 'licencewatch_model_missing'), 500);
        if (!class_exists('JModel')) jimport('joomla.application.component.model');
        require_once $lwModel;
        $lwWrite = in_array($action, array('lw_freeze', 'lw_save', 'lw_publish', 'lw_delete', 'atlas_event_add', 'atlas_event_set', 'atlas_event_delete'), true);
        if ($lwWrite) {
            if ($method !== 'POST')     fec_dash_json(array('ok' => false, 'error' => 'method_not_allowed'), 405);
            if (!fec_dash_check_csrf()) fec_dash_json(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
        $in = $lwWrite ? fec_dash_input() : $_GET;
        try {
            switch ($action) {
                case 'lw_list':
                    $rows = AuthorsModelLicencewatch::editions(true);
                    foreach ($rows as $k => $r) {
                        $rows[$k]['path'] = AuthorsModelLicencewatch::editionPath($r['edition']);
                        $rows[$k]['label'] = AuthorsModelLicencewatch::editionNumberLabel($r['number']) . ' · ' . AuthorsModelLicencewatch::editionMonthLabel($r['edition']);
                    }
                    fec_dash_json(array('ok' => true, 'editions' => $rows, 'current_month' => gmdate('Y-m')));
                    break;
                case 'lw_get':
                    $ed = AuthorsModelLicencewatch::edition(isset($in['edition']) ? (string) $in['edition'] : '', true);
                    if (!$ed) fec_dash_json(array('ok' => false, 'error' => 'not_found'), 404);
                    // The payload is large; the desk needs the headline figures and the mislabelled list.
                    $p = $ed['payload'];
                    $facts = array(
                        'as_of' => $p['as_of'], 'totals' => $p['totals'], 'declared' => array_slice($p['declared'], 0, 12),
                        'verified' => array('brands' => $p['verified']['brands'], 'operators' => $p['verified']['operators'], 'licences' => $p['verified']['licences']),
                        'curacao' => $p['curacao'], 'rails' => $p['rails'], 'no_regulator' => $p['no_regulator'],
                        'mislabelled' => $p['mislabelled'],
                    );
                    fec_dash_json(array('ok' => true, 'edition' => array(
                        'edition' => $ed['edition'], 'number' => (int) $ed['number'], 'status' => $ed['status'],
                        'frozen_at' => $ed['frozen_at'], 'published_at' => $ed['published_at'],
                        'author_slug' => $ed['author_slug'], 'reviewer_slug' => $ed['reviewer_slug'],
                        'sections' => $ed['sections'], 'diff' => $ed['diff'], 'events' => $ed['events'], 'facts' => $facts,
                        'path' => AuthorsModelLicencewatch::editionPath($ed['edition']),
                        'label' => AuthorsModelLicencewatch::editionNumberLabel($ed['number']) . ' · ' . AuthorsModelLicencewatch::editionMonthLabel($ed['edition']),
                    )));
                    break;
                case 'lw_freeze':
                    @set_time_limit(120);
                    $r = AuthorsModelLicencewatch::snapshot(isset($in['edition']) && $in['edition'] !== '' ? (string) $in['edition'] : null, !empty($in['force']));
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
                case 'lw_save':
                    $r = AuthorsModelLicencewatch::saveSections(
                        isset($in['edition']) ? (string) $in['edition'] : '',
                        isset($in['sections']) && is_array($in['sections']) ? $in['sections'] : array(),
                        isset($in['author_slug']) ? (string) $in['author_slug'] : null,
                        isset($in['reviewer_slug']) ? (string) $in['reviewer_slug'] : null);
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
                case 'lw_publish':
                    $r = AuthorsModelLicencewatch::setStatus(isset($in['edition']) ? (string) $in['edition'] : '', !empty($in['published']) ? 'published' : 'draft');
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
                case 'lw_delete':
                    $r = AuthorsModelLicencewatch::deleteDraft(isset($in['edition']) ? (string) $in['edition'] : '');
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
                case 'atlas_entities':
                    fec_dash_json(array('ok' => true, 'entities' => AuthorsModelLicencewatch::entitiesList()));
                    break;
                case 'atlas_events':
                    $from = isset($in['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['from']) ? (string) $in['from'] : date('Y-m-d', strtotime('-12 months'));
                    $to   = isset($in['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['to'])   ? (string) $in['to']   : gmdate('Y-m-d');
                    fec_dash_json(array('ok' => true, 'events' => array_reverse(AuthorsModelLicencewatch::events($from, $to, false))));
                    break;
                case 'atlas_event_add':
                    $r = AuthorsModelLicencewatch::addEvent($in, 42);
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
                case 'atlas_event_set':
                    $r = AuthorsModelLicencewatch::setEventPublished(isset($in['id']) ? (int) $in['id'] : 0, !empty($in['published']));
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
                case 'atlas_event_delete':
                    $r = AuthorsModelLicencewatch::deleteEvent(isset($in['id']) ? (int) $in['id'] : 0);
                    fec_dash_json($r, !empty($r['ok']) ? 200 : 400);
                    break;
            }
        } catch (Exception $e) {
            fec_dash_json(array('ok' => false, 'error' => 'licencewatch_failed', 'detail' => $e->getMessage()), 500);
        }
        break;

    default:
        fec_dash_json(array('ok' => false, 'error' => 'unknown_action'), 400);
}