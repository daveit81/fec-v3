<?php
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

ob_start();

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// Crea app ma NON la inizializzare (eviterebbe la chain di plugin buggati)
$app = JFactory::getApplication('site');

// Avvio sessione manualmente
$session = JFactory::getSession();
if ($session->getState() !== 'active') {
    $session->start();
}

$user  = JFactory::getUser();
$token = JSession::getFormToken();

$debug = array(
    'token'        => $token,
    'session_id'   => $session->getId(),
    'session_name' => $session->getName(),
    'user_id'      => (int) $user->get('id', 0),
    'user_guest'   => (bool) $user->guest,
    'cookies_recv' => array_keys($_COOKIE),
);

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('CDN-Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo json_encode($debug);
exit;