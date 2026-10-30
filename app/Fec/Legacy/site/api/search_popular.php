<?php
// /v2/api/search_popular.php (PHP 5.6 safe)
// Returns curated "popular searches" (privacy-safe, no cookies).
// Optional: ?cc=IT

// PORT: the script bootstrapped Joomla itself (_JEXEC, JPATH_BASE, includes/defines.php,
// includes/framework.php); here the runner boots the layer before the include
// (ComponentRunner::runPublicScript(), fec.legacy.public_scripts).
defined('_JEXEC') or die('Restricted access');

$app = JFactory::getApplication('site');
$app->initialise();

fec_header('Content-Type: application/json; charset=utf-8');
fec_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
fec_header('Pragma: no-cache');
fec_header('Expires: 0');

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
