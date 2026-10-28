<?php
/* ============================================================
 * FEC brand bridge — BUILDER + VERIFIER (temporary)
 * Place in web root (same level as goto.php), open in a browser,
 * read the report, then DELETE this file.
 *
 * What it does:
 *   1. Creates jos_fec_brand_map if missing (idempotent).
 *   2. Scans jos_jreviews_content.jr_gourl, extracts the goto.php
 *      slug (com_shortlink phrase), upserts slug -> cid -> brand as
 *      source='auto'. Rows you later mark source='manual' are never
 *      overwritten.
 *   3. Prints a verification report, cross-checking the auto map
 *      against the distinct slugs actually seen in jos_bonus_clicks.
 * ============================================================ */
define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', dirname(__FILE__));
require_once (JPATH_BASE . DS . 'includes' . DS . 'defines.php');
require_once (JPATH_BASE . DS . 'includes' . DS . 'framework.php');
$app = JFactory::getApplication('site');

header('Content-Type: text/plain; charset=utf-8');
$db = JFactory::getDbo();

echo "=== FEC brand bridge — build + verify ===\n\n";

/* ---- 1) ensure table ------------------------------------------------ */
try {
    $db->setQuery(
        "CREATE TABLE IF NOT EXISTS " . $db->qn('#__fec_brand_map') . " (\n"
      . "  `slug`       VARCHAR(64)  NOT NULL,\n"
      . "  `cid`        INT UNSIGNED NOT NULL DEFAULT 0,\n"
      . "  `brand`      VARCHAR(190) NOT NULL DEFAULT '',\n"
      . "  `statsdrone` VARCHAR(190) NOT NULL DEFAULT '',\n"
      . "  `source`     ENUM('auto','manual') NOT NULL DEFAULT 'auto',\n"
      . "  `updated_at` DATETIME NOT NULL,\n"
      . "  PRIMARY KEY (`slug`),\n"
      . "  KEY `idx_cid` (`cid`)\n"
      . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->execute();
    echo "1) Table jos_fec_brand_map ready.\n\n";
} catch (Exception $e) {
    echo "1) Table create FAILED: " . $e->getMessage() . "\n";
    exit;
}

/* ---- shortlink paramname(s) (default 'link') ------------------------ */
$paramNames = array('link');
try {
    $slp = JComponentHelper::getParams('com_shortlink');
    $raw = (string) $slp->get('paramname', 'link');
    $tmp = array();
    foreach (explode(',', $raw) as $p) { $p = trim($p); if ($p !== '') $tmp[] = $p; }
    if (!empty($tmp)) $paramNames = $tmp;
} catch (Exception $e) { /* keep default */ }
echo "   shortlink param name(s): " . implode(', ', $paramNames) . "\n\n";

/* ---- cid -> brand lookup from #__bonuses ---------------------------- */
$cidBrand = array();
try {
    $db->setQuery(
        "SELECT DISTINCT " . $db->qn('cid') . ", " . $db->qn('brand')
      . " FROM " . $db->qn('#__bonuses')
      . " WHERE " . $db->qn('brand') . " <> ''"
    );
    foreach ((array) $db->loadObjectList() as $r) {
        $cidBrand[(int) $r->cid] = (string) $r->brand;
    }
} catch (Exception $e) {
    echo "   WARN: brand lookup failed: " . $e->getMessage() . "\n";
}

/* ---- helper: pull the slug out of a jr_gourl ------------------------ */
$extractSlug = function ($gourl) use ($paramNames) {
    $gourl = (string) $gourl;
    if (stripos($gourl, 'goto.php') === false && strpos($gourl, '?') === false) return '';
    $qs = parse_url($gourl, PHP_URL_QUERY);
    if (!is_string($qs) || $qs === '') return '';
    parse_str($qs, $q);
    foreach ($paramNames as $pn) {
        if (!empty($q[$pn])) return (string) $q[$pn];
    }
    return '';
};

/* ---- 2) scan jr_gourl, upsert auto rows ----------------------------- */
$scanned = 0; $extracted = 0; $noSlug = array(); $mapped = array();
try {
    $db->setQuery(
        "SELECT " . $db->qn('contentid') . " AS cid, " . $db->qn('jr_gourl') . " AS gourl"
      . " FROM " . $db->qn('#__jreviews_content')
      . " WHERE " . $db->qn('jr_gourl') . " IS NOT NULL AND " . $db->qn('jr_gourl') . " <> ''"
    );
    $rows = (array) $db->loadObjectList();
} catch (Exception $e) {
    echo "2) Scan FAILED: " . $e->getMessage() . "\n";
    exit;
}

foreach ($rows as $r) {
    $scanned++;
    $cid  = (int) $r->cid;
    $slug = $extractSlug($r->gourl);
    if ($slug === '') { if (count($noSlug) < 20) $noSlug[] = $r->gourl; continue; }
    $extracted++;
    $brand = isset($cidBrand[$cid]) ? $cidBrand[$cid] : '';
    $mapped[$slug] = array('cid' => $cid, 'brand' => $brand);

    // upsert; never clobber rows a human marked source='manual'
    try {
        $db->setQuery(
            "INSERT INTO " . $db->qn('#__fec_brand_map')
          . " (slug, cid, brand, source, updated_at) VALUES ("
          . $db->q($slug) . "," . $cid . "," . $db->q($brand) . ",'auto',NOW())"
          . " ON DUPLICATE KEY UPDATE"
          . " cid = IF(source='manual', cid, VALUES(cid)),"
          . " brand = IF(source='manual', brand, VALUES(brand)),"
          . " updated_at = IF(source='manual', updated_at, NOW())"
        );
        $db->execute();
    } catch (Exception $e) {
        echo "   upsert error for '{$slug}': " . $e->getMessage() . "\n";
    }
}

echo "2) Scanned {$scanned} casino rows with jr_gourl; extracted slug from {$extracted}.\n";
if (!empty($noSlug)) {
    echo "   " . count($noSlug) . "+ had jr_gourl but no extractable slug (likely direct/external URLs). Sample:\n";
    foreach ($noSlug as $u) echo "     - " . $u . "\n";
}
echo "\n";

/* ---- 3) cross-check vs slugs actually seen in clicks ---------------- */
$clickSlugs = array();
try {
    $db->setQuery(
        "SELECT " . $db->qn('slug') . " AS slug, COUNT(*) AS c"
      . " FROM " . $db->qn('#__bonus_clicks')
      . " WHERE " . $db->qn('slug') . " <> ''"
      . " GROUP BY " . $db->qn('slug') . " ORDER BY c DESC"
    );
    foreach ((array) $db->loadObjectList() as $r) $clickSlugs[(string) $r->slug] = (int) $r->c;
} catch (Exception $e) {
    echo "3) Click-slug check failed: " . $e->getMessage() . "\n";
}

$haveMap = array();
try {
    $db->setQuery("SELECT slug, cid, brand, source FROM " . $db->qn('#__fec_brand_map'));
    foreach ((array) $db->loadObjectList() as $r) $haveMap[(string) $r->slug] = $r;
} catch (Exception $e) {}

$mappedClicks = 0; $unmapped = array();
foreach ($clickSlugs as $s => $c) {
    if (isset($haveMap[$s])) $mappedClicks++;
    else $unmapped[$s] = $c;
}

echo "3) Click slugs seen: " . count($clickSlugs)
   . "  |  mapped: {$mappedClicks}  |  unmapped: " . count($unmapped) . "\n";
if (!empty($unmapped)) {
    echo "   Unmapped slugs in clicks (add a manual row for each, or check jr_gourl):\n";
    arsort($unmapped);
    $n = 0;
    foreach ($unmapped as $s => $c) { echo "     - {$s}  ({$c} clicks)\n"; if (++$n >= 20) break; }
}
echo "\n";

/* ---- sample of the map for eyeballing ------------------------------- */
echo "Sample mappings (slug -> cid -> brand):\n";
$shown = 0;
foreach ($haveMap as $s => $r) {
    echo "   {$s}  ->  {$r->cid}  ->  " . ($r->brand !== '' ? $r->brand : '(no brand)') . "  [{$r->source}]\n";
    if (++$shown >= 25) break;
}

echo "\n=== done — review the pairs above, then DELETE this file ===\n";
echo "Manual fix examples (run in phpMyAdmin):\n";
echo "  -- correct a wrong/ missing mapping and protect it from re-sync:\n";
echo "  INSERT INTO jos_fec_brand_map (slug,cid,brand,source,updated_at)\n";
echo "    VALUES ('someslug', 123, 'Some Brand', 'manual', NOW())\n";
echo "    ON DUPLICATE KEY UPDATE cid=VALUES(cid), brand=VALUES(brand), source='manual', updated_at=NOW();\n";