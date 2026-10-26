<?php
/* ============================================================
 * com_bonuses :: ajax/ftd_save.php
 * Saves one ad-hoc StatsDrone snapshot into #__fec_ftd_log.
 * Auth-gated (dashboard user) + CSRF token. POST only.
 * ============================================================ */
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', __DIR__ . '/ftd_save_error.log');
error_reporting(E_ALL);

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', $_SERVER['DOCUMENT_ROOT'] . DS);

require_once(JPATH_BASE . 'includes' . DS . 'defines.php');
require_once(JPATH_BASE . 'includes' . DS . 'framework.php');

$app = JFactory::getApplication('site');
header('Content-Type: application/json; charset=utf-8');

function ftd_out($arr, $code = 200) {
	http_response_code($code);
	echo json_encode($arr);
	exit;
}

// auth: same single dashboard user as every other com_bonuses dashboard view
JLoader::register(
	'BonusesHelperBonus',
	JPATH_BASE . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'bonus.php'
);
if (!class_exists('BonusesHelperBonus') || !BonusesHelperBonus::authorized()) {
	ftd_out(array('ok' => false, 'error' => 'unauthorized'), 403);
}

// CSRF
if (!JSession::checkToken('post')) {
	ftd_out(array('ok' => false, 'error' => 'token'), 403);
}

$in   = $app->input;
$cid  = (int) $in->post->get('cid', 0, 'int');
$ps   = (string) $in->post->get('period_start', '', 'string');
$pe   = (string) $in->post->get('period_end', '', 'string');
$ftd  = (int) $in->post->get('ftd', 0, 'int');
$regs = (int) $in->post->get('regs', 0, 'int');
$ngr  = (float) $in->post->get('ngr', 0, 'float');
$comm = (float) $in->post->get('commission', 0, 'float');

if ($cid <= 0) {
	ftd_out(array('ok' => false, 'error' => 'cid'), 400);
}

$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($dateRe, $ps) || !preg_match($dateRe, $pe)) {
	ftd_out(array('ok' => false, 'error' => 'period'), 400);
}

$db = JFactory::getDbo();

try {
	// Upsert on the unique (cid, period_start, period_end) key: a re-save
	// for the same brand+period overwrites with the latest figures.
	$now = $db->q(JFactory::getDate()->toSql());
	$sql = 'INSERT INTO ' . $db->qn('#__fec_ftd_log')
		. ' (' . $db->qn('cid') . ',' . $db->qn('period_start') . ',' . $db->qn('period_end')
		. ',' . $db->qn('ftd') . ',' . $db->qn('regs') . ',' . $db->qn('ngr')
		. ',' . $db->qn('commission') . ',' . $db->qn('note') . ',' . $db->qn('created_at') . ') VALUES ('
		. $cid . ',' . $db->q($ps) . ',' . $db->q($pe) . ','
		. $ftd . ',' . $regs . ',' . $ngr . ',' . $comm . ',' . $db->q('') . ',' . $now . ')'
		. ' ON DUPLICATE KEY UPDATE '
		. $db->qn('ftd') . '=VALUES(' . $db->qn('ftd') . '),'
		. $db->qn('regs') . '=VALUES(' . $db->qn('regs') . '),'
		. $db->qn('ngr') . '=VALUES(' . $db->qn('ngr') . '),'
		. $db->qn('commission') . '=VALUES(' . $db->qn('commission') . '),'
		. $db->qn('created_at') . '=' . $now;

	$db->setQuery($sql);
	$db->execute();

	ftd_out(array(
		'ok'         => true,
		'id'         => (int) $db->insertid(),
		'ftd'        => $ftd,
		'regs'       => $regs,
		'ngr'        => $ngr,
		'commission' => $comm,
	));
} catch (Exception $e) {
	ftd_out(array('ok' => false, 'error' => 'db'), 500);
}