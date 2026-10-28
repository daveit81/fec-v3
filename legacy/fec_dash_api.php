<?php
/**
 * FEC Review Dashboard — JSON API
 * ================================
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

$here = dirname(__FILE__);
chdir($here);

// --- 1. Joomla bootstrap ---
define('_JEXEC', 1);
define('JPATH_BASE', $here);

$defines   = $here . '/includes/defines.php';
$framework = $here . '/includes/framework.php';
if (!is_readable($defines) || !is_readable($framework)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'bootstrap_missing'));
    exit;
}
require_once $defines;
require_once $framework;

// JSON output helper — used everywhere below.
function fec_dash_json($payload, $httpCode = 200) {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload);
    exit;
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
    'jr_established', 'jr_owner', 'jr_officialwebsite', 'jr_gourl',
    'jr_logosmall', 'jr_logowebp',
    'jr_livesupport',
    'jr_warning', 'jr_warningtitle', 'jr_cofmdate',
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
            'jr_pendingperiod',
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

        $now = JFactory::getDate()->toSql();
        $bind = array(
            'title'       => $title,
            'alias'       => $alias,
            'introtext'   => isset($contentIn['introtext']) ? (string) $contentIn['introtext'] : '',
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

        $destDir = JPATH_SITE . '/images/casino-logos/';
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
        usort($opts, function ($a, $b) { return strcmp($a['label'], $b['label']); });
        fec_dash_json(array('ok' => true, 'options' => $opts));
        break;

    default:
        fec_dash_json(array('ok' => false, 'error' => 'unknown_action'), 400);
}