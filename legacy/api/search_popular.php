<?php
// /v2/api/search_popular.php (PHP 5.6 safe)
// Returns curated "popular searches" (privacy-safe, no cookies).
// Optional: ?cc=IT

define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/..'));
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$app = JFactory::getApplication('site');
$app->initialise();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$cc = isset($_GET['cc']) ? strtoupper(trim((string)$_GET['cc'])) : '';
if (!preg_match('/^[A-Z]{2}$/', $cc)) $cc = '';

// Base curated list (default)
$items = array(
  array('label' => 'No Deposit',        'q' => 'no deposit'),
  array('label' => 'Free Spins',        'q' => 'free spins'),
  array('label' => 'No Wagering',       'q' => 'no wagering'),
  array('label' => 'Crypto',            'q' => 'crypto'),
  array('label' => 'Exclusive',         'q' => 'exclusive'),
  array('label' => 'High Roller',       'q' => 'high roller'),
  array('label' => 'Fast Withdrawals',  'q' => 'fast withdrawals'),
  array('label' => 'New Casinos',       'q' => 'new casinos'),
);

// Optional country tweaks (keep it minimal / intent-based)
if ($cc === 'IT') {
  // example: keep it neutral; swap 1-2 items only
  $items = array(
    array('label' => 'No Deposit',        'q' => 'no deposit'),
    array('label' => 'Free Spins',        'q' => 'free spins'),
    array('label' => 'Low Wagering',      'q' => 'low wagering'),
    array('label' => 'Crypto',            'q' => 'crypto'),
    array('label' => 'Exclusive',         'q' => 'exclusive'),
    array('label' => 'Fast Withdrawals',  'q' => 'fast withdrawals'),
    array('label' => 'New Casinos',       'q' => 'new casinos'),
    array('label' => 'High Roller',       'q' => 'high roller'),
  );
}

echo json_encode(array(
  'cc' => $cc,
  'items' => $items,
));
