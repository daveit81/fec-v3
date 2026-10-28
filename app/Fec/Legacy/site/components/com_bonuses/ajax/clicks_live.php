<?php
/* ============================================================
 * com_bonuses :: ajax/clicks_live.php
 * Polling endpoint for the live click feed. Read-only, auth-gated.
 *   GET ?since=<lastId>  -> new clicks since that id (+ counters)
 * ============================================================ */
// PORT: the script bootstrapped Joomla itself (defines.php, framework.php);
// here the runner boots the layer before the include.
defined('_JEXEC') or die('Restricted access');
$app = JFactory::getApplication('site');

JLoader::register(
	'BonusesHelperBonus',
	JPATH_COMPONENT . DS . 'helpers' . DS . 'bonus.php' // PORT: was JPATH_BASE . 'components/com_bonuses/helpers/bonus.php'
);

fec_header('Content-Type: application/json; charset=utf-8');
fec_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
fec_header('Pragma: no-cache');
fec_header('Expires: 0');
fec_header('X-LiteSpeed-Cache-Control: no-cache');

if (!class_exists('BonusesHelperBonus') || !BonusesHelperBonus::authorized()) {
	http_response_code(403);
	echo json_encode(array('ok' => false, 'error' => 'unauthorized'));
	jexit();
}

$db    = JFactory::getDbo();
$since = (int) $app->input->get->get('since', 0, 'int');

try {
	$ck = $db->qn('#__bonus_clicks');

	$db->setQuery('SELECT COUNT(*) FROM ' . $ck . ' WHERE ' . $db->qn('is_bot')
		. ' = 0 AND ' . $db->qn('created_at') . ' >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)');
	$last60 = (int) $db->loadResult();

	$db->setQuery('SELECT COUNT(*) FROM ' . $ck . ' WHERE ' . $db->qn('is_bot')
		. ' = 0 AND ' . $db->qn('created_at') . ' >= UTC_DATE()');
	$today = (int) $db->loadResult();

	$db->setQuery('SELECT MAX(' . $db->qn('id') . ') FROM ' . $ck);
	$maxId = (int) $db->loadResult();

	$brand    = "COALESCE(NULLIF(m.brand, ''), NULLIF(b.brand, ''), NULLIF(c.slug, ''), CONCAT('cid:', c.cid))";
	$brandSub = '(SELECT DISTINCT ' . $db->qn('cid') . ', ' . $db->qn('brand')
		. ' FROM ' . $db->qn('#__bonuses') . " WHERE " . $db->qn('brand') . " <> '')";

	$q = $db->getQuery(true)
		->select(array(
			'c.id',
			'c.country',
			'c.device',
			'TIMESTAMPDIFF(SECOND, c.created_at, UTC_TIMESTAMP()) AS ago',
			$brand . ' AS brand',
		))
		->from($db->qn('#__bonus_clicks', 'c'))
		->join('LEFT', $db->qn('#__fec_brand_map', 'm') . " ON (c.slug <> '' AND m.slug = c.slug)")
		->join('LEFT', $brandSub . ' AS b ON b.cid = COALESCE(m.cid, NULLIF(c.cid, 0))')
		->where('c.' . $db->qn('is_bot') . ' = 0');

	if ($since > 0) {
		$q->where('c.' . $db->qn('id') . ' > ' . $since)->order('c.id DESC');
		$db->setQuery($q, 0, 50);
	} else {
		$q->order('c.id DESC');
		$db->setQuery($q, 0, 30);
	}

	$events = array();
	foreach ((array) $db->loadObjectList() as $r) {
		$events[] = array(
			'id'      => (int) $r->id,
			'brand'   => (string) $r->brand,
			'country' => (string) $r->country,
			'device'  => (string) $r->device,
			'ago'     => (int) $r->ago,
		);
	}

	// window totals (for the live cards): clicks/mobile/geos/brands, selected window, UTC
	$days = (int) $app->input->get->get('days', 14, 'int');
	if (!in_array($days, array(7, 14, 30, 90), true)) $days = 14;

	$canonKey = "CASE WHEN m.cid IS NOT NULL THEN CONCAT('cid:', m.cid) "
		. "WHEN c.cid > 0 THEN CONCAT('cid:', c.cid) "
		. "WHEN c.slug <> '' THEN CONCAT('slug:', c.slug) ELSE 'unknown' END";

	$tq = $db->getQuery(true)
		->select(array(
			'COUNT(*) AS clicks',
			"SUM(CASE WHEN c.device = 'm' THEN 1 ELSE 0 END) AS mobile",
			"COUNT(DISTINCT NULLIF(c.country, '')) AS geos",
			'COUNT(DISTINCT ' . $canonKey . ') AS brands',
		))
		->from($db->qn('#__bonus_clicks', 'c'))
		->join('LEFT', $db->qn('#__fec_brand_map', 'm') . " ON (c.slug <> '' AND m.slug = c.slug)")
		->where('c.' . $db->qn('is_bot') . ' = 0')
		->where('c.' . $db->qn('created_at') . ' >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)');
	$db->setQuery($tq);
	$tot = $db->loadObject();
	$totals = $tot
		? array('clicks' => (int) $tot->clicks, 'mobile' => (int) $tot->mobile, 'geos' => (int) $tot->geos, 'brands' => (int) $tot->brands)
		: array('clicks' => 0, 'mobile' => 0, 'geos' => 0, 'brands' => 0);

	echo json_encode(array(
		'ok'     => true,
		'now'    => time(),
		'max_id' => $maxId,
		'last60' => $last60,
		'today'  => $today,
		'totals' => $totals,
		'events' => $events,
	));
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(array('ok' => false, 'error' => 'db'));
}