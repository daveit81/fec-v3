<?php
/**
 * FEC Clusters Admin — standalone management page
 * ===============================================
 * Manage casino owner-groups (clusters) and their pages, without touching the
 * React dashboard bundle. Same Joomla bootstrap + user-42 guard as
 * fec_dashboard.php / fec_dash_api.php.
 *
 * Placement: /fec-admin/fec_clusters.php   ->   URL: /fec-admin/fec_clusters.php
 *
 * What it does:
 *   - lists every owner group (>=2 members) derived from jr_owner
 *   - set featured + slug + label + metadata  -> UPSERT jos_fec_owner_groups
 *   - "Create page" -> creates the Joomla article (alias=slug, catid=clusters,
 *     published) so /online-casinos/clusters/{slug} stops 404-ing
 *
 * Self-contained: GET (no action) renders the page; POST ?action=... is the API.
 */

// PORT: the script bootstrapped Joomla itself from the web root (chdir, _JEXEC, JPATH_BASE,
// includes/defines.php and framework.php); here the runner boots the layer and includes it
// (ComponentRunner::runDeskFile(), fec.legacy.desk_files, logged-in redazione only), and
// $jroot is the real docroot (JPATH_ROOT): the files it reads or serves live there.
defined('_JEXEC') or die('Restricted access');
$jroot = JPATH_ROOT;

$app = JFactory::getApplication('site');
$app->initialise();
$db   = JFactory::getDbo();
$user = JFactory::getUser();

// --- auth guard (user 42) --------------------------------------------------
if ((int) $user->get('id') !== 42) {
    fec_header('HTTP/1.1 403 Forbidden');
    echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title>'
       . '<body style="font-family:sans-serif;padding:40px;color:#14110d;background:#f7f4ed">'
       . '<h1>403 — Not authorised</h1><p><a href="/log-in/">Sign in</a></p></body>';
    jexit();
}

$libPath = JPATH_SITE . '/components/com_bonuses/helpers/fec_common_lib.php';
if (is_readable($libPath)) require_once $libPath;

$token   = JSession::getFormToken();
$catid   = defined('FEC_CLUSTERS_CATID') ? (int) FEC_CLUSTERS_CATID : 0;
$action  = isset($_GET['action']) ? (string) $_GET['action'] : '';
$TP      = $db->getPrefix();

// ===========================================================================
// API MODE
// ===========================================================================
if ($action !== '') {
    fec_header('Content-Type: application/json; charset=utf-8');
    fec_header('Cache-Control: no-store');

    $jsonOut = function ($p, $code = 200) { http_response_code($code); echo json_encode($p); jexit(); };
    $input   = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = array();

    $checkCsrf = function () use ($input, $jsonOut) {
        $sent = isset($input['_token']) ? (string) $input['_token'] : '';
        if (!JSession::checkToken('post') && !($sent !== '' && $sent === JSession::getFormToken())) {
            $jsonOut(array('ok' => false, 'error' => 'bad_csrf'), 403);
        }
    };

    if ($action === 'list') {
        $clusters = function_exists('fec_get_casino_clusters') ? fec_get_casino_clusters($db) : array();
        // which featured slugs already have an article in the clusters category
        $articles = array();
        if ($catid > 0) {
            $aq = $db->getQuery(true);
            $aq->select(array('id', 'alias', 'state'))->from($db->quoteName('#__content'))
               ->where('catid = ' . $catid);
            $db->setQuery($aq);
            foreach ((array) $db->loadAssocList() as $a) { $articles[$a['alias']] = $a; }
        }
        $out = array();
        foreach ($clusters as $c) {
            $art = ($c['slug'] && isset($articles[$c['slug']])) ? $articles[$c['slug']] : null;
            $chips = array();
            foreach (array_slice($c['members'], 0, 8) as $m) {
                $cl = fec_sister_node_color($m);
                $chips[] = array('title' => $m['title'], 'alias' => $m['alias'], 'color' => $cl[0], 'status' => $cl[1]);
            }
            $out[] = array(
                'norm'     => $c['norm'],
                'label'    => $c['label'],
                'count'    => $c['count'],
                'slug'     => $c['slug'],
                'featured' => $c['featured'],
                'chips'    => $chips,
                'extra'    => max(0, $c['count'] - count($chips)),
                'article'  => $art ? array('id' => (int) $art['id'], 'state' => (int) $art['state']) : null,
            );
        }
        $jsonOut(array('ok' => true, 'catid' => $catid, 'clusters' => $out));
    }

    if ($action === 'save') {
        $checkCsrf();
        $norm = isset($input['norm_key']) ? trim((string) $input['norm_key']) : '';
        if ($norm === '') $jsonOut(array('ok' => false, 'error' => 'norm_required'), 400);
        $slug = strtolower(trim((string) (isset($input['slug']) ? $input['slug'] : '')));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug); $slug = trim($slug, '-');
        $featured = !empty($input['featured']) ? 1 : 0;
        if ($featured && $slug === '') $jsonOut(array('ok' => false, 'error' => 'slug_required_for_featured'), 400);

        $fields = array('label', 'operator', 'licence', 'platform', 'historic_owner', 'current_owner', 'notes');
        $vals = array();
        foreach ($fields as $f) { $vals[$f] = isset($input[$f]) ? trim((string) $input[$f]) : ''; }
        $now = JFactory::getDate()->toSql();

        // slug uniqueness (except this norm_key)
        if ($slug !== '') {
            $sq = $db->getQuery(true);
            $sq->select('id')->from($db->quoteName('#__fec_owner_groups'))
               ->where('slug = ' . $db->quote($slug))->where('norm_key <> ' . $db->quote($norm));
            $db->setQuery($sq);
            if ($db->loadResult()) $jsonOut(array('ok' => false, 'error' => 'slug_taken'), 409);
        }

        // exists?
        $eq = $db->getQuery(true);
        $eq->select('id')->from($db->quoteName('#__fec_owner_groups'))->where('norm_key = ' . $db->quote($norm));
        $db->setQuery($eq);
        $existId = (int) $db->loadResult();

        $row = new stdClass();
        $row->norm_key = $norm;
        $row->slug = ($slug !== '') ? $slug : ('group-' . substr(md5($norm), 0, 8));
        $row->label = $vals['label'];
        $row->operator = $vals['operator']; $row->licence = $vals['licence']; $row->platform = $vals['platform'];
        $row->historic_owner = $vals['historic_owner']; $row->current_owner = $vals['current_owner'];
        $row->notes = $vals['notes']; $row->featured = $featured; $row->status = 'active';
        $row->updated_at = $now;
        try {
            if ($existId) { $row->id = $existId; $db->updateObject('#__fec_owner_groups', $row, 'id'); }
            else { $row->created_at = $now; $db->insertObject('#__fec_owner_groups', $row); }
        } catch (Exception $e) {
            $jsonOut(array('ok' => false, 'error' => 'save_failed', 'detail' => $e->getMessage()), 500);
        }
        $jsonOut(array('ok' => true, 'slug' => $row->slug, 'featured' => $featured));
    }

    if ($action === 'create_article') {
        $checkCsrf();
        if ($catid <= 0) $jsonOut(array('ok' => false, 'error' => 'no_clusters_catid'), 400);
        $slug  = strtolower(trim((string) (isset($input['slug']) ? $input['slug'] : '')));
        $label = trim((string) (isset($input['label']) ? $input['label'] : ''));
        if ($slug === '' || $label === '') $jsonOut(array('ok' => false, 'error' => 'slug_label_required'), 400);

        // already there?
        $eq = $db->getQuery(true);
        $eq->select('id')->from($db->quoteName('#__content'))
           ->where('catid = ' . $catid)->where('alias = ' . $db->quote($slug));
        $db->setQuery($eq);
        $existing = (int) $db->loadResult();
        if ($existing) $jsonOut(array('ok' => true, 'id' => $existing, 'existed' => true));

        JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');
        $table = JTable::getInstance('Content');
        if (!$table) $jsonOut(array('ok' => false, 'error' => 'jtable_unavailable'), 500);
        $now = JFactory::getDate()->toSql();
        $bind = array(
            'title' => $label, 'alias' => $slug,
            'introtext' => "<!-- cluster page: intro authored in backend -->\n<p></p>",
            'fulltext' => '', 'state' => 1, 'catid' => $catid,
            'created' => $now, 'created_by' => 42, 'modified' => $now, 'modified_by' => 42,
            'publish_up' => $now, 'language' => '*', 'access' => 1,
        );
        if (!$table->bind($bind) || !$table->check() || !$table->store()) {
            $jsonOut(array('ok' => false, 'error' => 'create_failed', 'detail' => $table->getError()), 500);
        }
        $jsonOut(array('ok' => true, 'id' => (int) $table->id, 'created' => true));
    }

    $jsonOut(array('ok' => false, 'error' => 'unknown_action'), 400);
}

// ===========================================================================
// PAGE MODE
// ===========================================================================
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Clusters Admin · FreeExtraChips</title>
<style>
  :root{--ink:#14110d;--ink3:#6b6760;--bg:#f7f4ed;--card:#fff;--rule:#e3ddd0;--accent:#1c7a4a;--warn:#a8631c}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}
  .wrap{max-width:1100px;margin:0 auto;padding:28px 22px 80px}
  header.top{display:flex;align-items:baseline;justify-content:space-between;border-bottom:2px solid var(--ink);padding-bottom:12px;margin-bottom:6px}
  header.top h1{font-size:20px;margin:0}
  header.top a{font-size:13px;color:var(--accent)}
  .sub{color:var(--ink3);font-size:13px;margin:0 0 18px}
  .banner{background:#fbf1de;border:1px solid #e4c778;border-radius:8px;padding:10px 14px;font-size:13px;color:#5c4708;margin-bottom:16px}
  .tools{display:flex;gap:10px;margin-bottom:14px}
  input,button{font:inherit}
  input[type=text],input.search{padding:8px 10px;border:1px solid var(--rule);border-radius:7px;background:#fff}
  input.search{width:280px}
  .grp{background:var(--card);border:1px solid var(--rule);border-radius:12px;padding:14px 16px;margin-bottom:12px}
  .grp-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .grp-label{font-weight:600;font-size:16px;flex:1;min-width:200px}
  .badge{font:11px ui-monospace,Menlo,Consolas,monospace;color:var(--ink3)}
  .chips{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0}
  .chip{font-size:12px;padding:2px 9px;border-radius:999px;border:1px solid;text-decoration:none}
  .row2{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px}
  label.ck{display:flex;align-items:center;gap:6px;font-size:13px}
  .slug{width:180px}
  button.btn{padding:8px 14px;border:1px solid var(--ink);background:var(--ink);color:#fff;border-radius:7px;cursor:pointer}
  button.btn.ghost{background:transparent;color:var(--ink)}
  button.btn:disabled{opacity:.4;cursor:not-allowed}
  .pagelink{font-size:13px;color:var(--accent)}
  .more{display:none;margin-top:10px;padding-top:10px;border-top:1px dashed var(--rule)}
  .more.open{display:block}
  .more .fld{display:flex;gap:8px;align-items:center;margin:6px 0}
  .more .fld label{width:120px;font-size:12px;color:var(--ink3)}
  .more .fld input{flex:1}
  .ok{color:var(--accent);font-size:12px;margin-left:8px}
  .err{color:#9a2828;font-size:12px;margin-left:8px}
  .muted{color:var(--ink3)}
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <h1>Clusters Admin</h1>
    <a href="/fec-admin/fec_dashboard.php">&larr; Review dashboard</a>
  </header>
  <p class="sub">Owner groups derived from <code>jr_owner</code>. Mark a group <strong>featured</strong> + give it a slug, save, then create its page.</p>

  <?php if ($catid <= 0): ?>
  <div class="banner"><strong>FEC_CLUSTERS_CATID is not set.</strong> You can mark groups featured, but "Create page" is disabled until you set the clusters category id in fec_common_lib.php.</div>
  <?php endif; ?>

  <div class="tools">
    <input type="text" class="search" id="q" placeholder="Filter groups…">
    <span class="badge" id="count"></span>
  </div>

  <div id="list"><p class="muted">Loading…</p></div>
</div>

<script>
const TOKEN = <?php echo json_encode($token); ?>;
const CATID = <?php echo (int) $catid; ?>;
const API = 'fec_clusters.php';
const esc = s => String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

async function api(action, body){
  const r = await fetch(API+'?action='+action, {
    method: body?'POST':'GET',
    headers: body?{'Content-Type':'application/json'}:{},
    body: body?JSON.stringify(Object.assign({_token:TOKEN}, body)):undefined
  });
  return r.json();
}

let DATA = [];
function render(){
  const q = document.getElementById('q').value.toLowerCase();
  const list = document.getElementById('list');
  const rows = DATA.filter(c => c.label.toLowerCase().includes(q));
  document.getElementById('count').textContent = rows.length+' / '+DATA.length+' groups';
  list.innerHTML = rows.map((c,i)=>{
    const idx = DATA.indexOf(c);
    const chips = c.chips.map(ch=>`<a class="chip" href="/online-casinos/${esc(ch.alias)}" style="border-color:${ch.color};color:${ch.color}" target="_blank">${esc(ch.title)}</a>`).join('')
      + (c.extra>0?`<span class="badge">+${c.extra} more</span>`:'');
    const hasPage = c.article && c.article.id;
    const pageCol = hasPage
      ? `<a class="pagelink" href="/online-casinos/clusters/${esc(c.slug)}" target="_blank">Open page ↗</a>`
      : `<button class="btn ghost" data-create="${idx}" ${(!c.featured||!c.slug||CATID<=0)?'disabled':''}>Create page</button>`;
    return `<div class="grp" data-i="${idx}">
      <div class="grp-head">
        <div class="grp-label">${esc(c.label)} <span class="badge">${c.count} brands</span></div>
        ${pageCol}
        <button class="btn ghost" data-more="${idx}">Details ▾</button>
      </div>
      <div class="chips">${chips}</div>
      <div class="row2">
        <label class="ck"><input type="checkbox" data-f="${idx}" ${c.featured?'checked':''}> Featured</label>
        <input type="text" class="slug" data-slug="${idx}" placeholder="slug" value="${esc(c.slug||'')}">
        <button class="btn" data-save="${idx}">Save</button>
        <span class="msg" data-msg="${idx}"></span>
      </div>
      <div class="more" data-detail="${idx}">
        <div class="fld"><label>Label</label><input type="text" data-m="label" value="${esc(c.label)}"></div>
        <div class="fld"><label>Operator</label><input type="text" data-m="operator" value=""></div>
        <div class="fld"><label>Licence</label><input type="text" data-m="licence" value=""></div>
        <div class="fld"><label>Platform</label><input type="text" data-m="platform" value=""></div>
        <div class="fld"><label>Historic owner</label><input type="text" data-m="historic_owner" value=""></div>
        <div class="fld"><label>Current owner</label><input type="text" data-m="current_owner" value=""></div>
        <div class="fld"><label>Notes</label><input type="text" data-m="notes" value=""></div>
      </div>
    </div>`;
  }).join('') || '<p class="muted">No groups match.</p>';
}

function detailVals(card){
  const o = {};
  card.querySelectorAll('[data-m]').forEach(el=> o[el.getAttribute('data-m')] = el.value);
  return o;
}

document.addEventListener('click', async (e)=>{
  const card = e.target.closest('.grp');
  if (e.target.matches('[data-more]')) {
    card.querySelector('.more').classList.toggle('open'); return;
  }
  if (e.target.matches('[data-save]')) {
    const i = +e.target.getAttribute('data-save'); const c = DATA[i];
    const featured = card.querySelector('[data-f]').checked;
    const slug = card.querySelector('[data-slug]').value;
    const msg = card.querySelector('[data-msg]');
    const body = Object.assign({norm_key:c.norm, slug, featured:featured?1:0}, detailVals(card));
    const res = await api('save', body);
    if (res.ok){ c.featured=featured?1:0; c.slug=res.slug; msg.innerHTML='<span class="ok">Saved ✓</span>'; render(); }
    else msg.innerHTML='<span class="err">'+esc(res.error)+'</span>';
    return;
  }
  if (e.target.matches('[data-create]')) {
    const i = +e.target.getAttribute('data-create'); const c = DATA[i];
    e.target.disabled = true; e.target.textContent='Creating…';
    const res = await api('create_article', {slug:c.slug, label:c.label});
    if (res.ok){ c.article={id:res.id,state:1}; render(); }
    else { e.target.disabled=false; e.target.textContent='Create page'; alert('Error: '+res.error); }
    return;
  }
});
document.getElementById('q').addEventListener('input', render);

(async ()=>{ const res = await api('list'); if(res.ok){ DATA = res.clusters; render(); } else document.getElementById('list').innerHTML='<p class="err">'+esc(res.error)+'</p>'; })();
</script>
</body>
</html>