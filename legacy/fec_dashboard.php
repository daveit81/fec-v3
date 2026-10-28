<?php
/**
 * FEC Review Dashboard — SPA shell
 * ================================
 * Bootstraps Joomla, guards on user 42, emits the HTML shell + CSRF token,
 * loads React + TinyMCE (CDN) and the transpiled app bundle.
 *
 * Placement: web root (/home/freeextr/public_html/fec_dashboard.php)
 * URL:       /fec_dashboard.php
 */

$here = dirname(__FILE__);
chdir($here);

define('_JEXEC', 1);
define('JPATH_BASE', $here);
require_once $here . '/includes/defines.php';
require_once $here . '/includes/framework.php';

$app  = JFactory::getApplication('site');
$app->initialise();
$user = JFactory::getUser();

// Guard — user 42 only (mirrors fec_dash_api.php).
if ((int) $user->get('id') !== 42) {
    header('HTTP/1.1 403 Forbidden');
    echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title>'
       . '<body style="font-family:sans-serif;padding:40px;color:#14110d;background:#f7f4ed">'
       . '<h1>403 — Not authorised</h1><p>You must be signed in as the dashboard user.</p>'
       . '<p><a href="/log-in/">Sign in</a></p></body>';
    exit;
}

$token   = JSession::getFormToken();
$apiBase = '/fec_dash_api.php';
// Cache-buster for the static bundle/css so deploys take effect immediately.
$ver = @filemtime($here . '/js/fec_dash_app.js');
if (!$ver) $ver = time();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Review Dashboard · FreeExtraChips</title>
<link rel="stylesheet" href="/css/fec_dash.css?v=<?php echo $ver; ?>">
<!-- React 18 (UMD production) -->
<script crossorigin src="https://cdn.jsdelivr.net/npm/react@18.3.1/umd/react.production.min.js"></script>
<script crossorigin src="https://cdn.jsdelivr.net/npm/react-dom@18.3.1/umd/react-dom.production.min.js"></script>
<!-- TinyMCE 7 (self-hosted via jsDelivr, no API key) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="fec-dash-body">
<div class="fec-dash">
  <div id="fec-dash-root">
    <div class="fdash-loading"><div class="fdash-spinner"></div>Loading dashboard…</div>
  </div>
</div>
<script>
  window.FEC_DASH = {
    token: <?php echo json_encode($token); ?>,
    apiBase: <?php echo json_encode($apiBase); ?>,
    user: <?php echo (int) $user->get('id'); ?>
  };
</script>
<script src="/js/fec_dash_app.js?v=<?php echo $ver; ?>"></script>
</body>
</html>