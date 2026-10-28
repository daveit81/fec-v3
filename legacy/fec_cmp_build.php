<?php
/**
 * fec_cmp_build.php - Casino Comparator dataset builder (FEC Tools No. 02)
 *
 * Reads jos_content (catid 8, state 1) joined with jos_jreviews_content and
 * writes the picker/compare dataset to
 *   components/com_bonuses/data/cmp_casinos.json  (atomic tmp+rename)
 *
 * Run as the site user, never root (lock/ownership standing rule):
 *   /usr/local/bin/php /home/freeextr/public_html/fec_cmp_build.php
 * Cron: daily is plenty; the dataset changes when fact sheets change.
 *
 * DB credentials come from Joomla configuration.php - nothing hardcoded.
 * PHP 7+ (CLI). Not a web entry point.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const SITE_ROOT = '/home/freeextr/public_html';
const OUT_FILE  = SITE_ROOT . '/components/com_bonuses/data/cmp_casinos.json';
const CASINO_CATID = 8;

require SITE_ROOT . '/configuration.php';
$cfg = new JConfig();

$mysqli = @new mysqli($cfg->host, $cfg->user, $cfg->password, $cfg->db);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "DB connect failed: {$mysqli->connect_error}\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');
$pfx = $cfg->dbprefix;

function fmt_pending($v) {
    // slug tipo 0-24-hours / 24-48-hours -> 0-24 hours (en dash), altrimenti spazi
    $v = trim(trim((string) $v), '*');
    if ($v === '') return '';
    if (preg_match('/^(\\d+)-(\\d+)-(hours|days)$/', $v, $m)) {
        return $m[1] . "\xE2\x80\x93" . $m[2] . ' ' . $m[3];
    }
    return str_replace('-', ' ', $v);
}

function fmt_licence($x) {
    // presentazione: capitalizza e reintegra i diacritici noti (curacao)
    $x = ucwords(str_replace('-', ' ', $x));
    return str_ireplace('Curacao', "Cura\xC3\xA7ao", $x);
}

function multi($v) { // niente type declarations: parse-safe anche su PHP 5.6
    $out = [];
    foreach (explode('*', (string) $v) as $x) {
        $x = trim($x);
        if ($x !== '') $out[] = $x;
    }
    return $out;
}

$sql = "SELECT c.id, c.title, c.alias,
               j.jr_owner, j.jr_network, j.jr_established, j.jr_jurisdiction,
               j.jr_soft, j.jr_multiplesoftware, j.jr_languages, j.jr_currencies,
               j.jr_livesupport, j.jr_banking, j.jr_restrictedcountries,
               j.jr_pendingperiod, j.jr_flag, j.jr_flagreason,
               j.jr_suspended, j.jr_blacklisted, j.jr_closed
        FROM `{$pfx}content` c
        INNER JOIN `{$pfx}jreviews_content` j ON j.contentid = c.id
        WHERE c.catid = " . CASINO_CATID . " AND c.state = 1
        ORDER BY c.title ASC";
$res = $mysqli->query($sql);
if ($res === false) {
    fwrite(STDERR, "Query failed: {$mysqli->error}\n");
    exit(1);
}

$rows = [];
$skipped = 0;
while ($r = $res->fetch_assoc()) {
    if (trim((string) $r['jr_closed']) === '*yes*' || trim((string) $r['jr_closed']) === 'yes') { $skipped++; continue; }

    // minimum honest set: identity + the fields the table renders.
    // Rows without owner or restricted list stay out of the tool rather
    // than rendering half-empty columns.
    if (trim((string) $r['jr_owner']) === '' || trim((string) $r['jr_restrictedcountries']) === '') { $skipped++; continue; }

    // Fieldoption values verified against the live dump (standing rule):
    // jr_blacklisted uses *bl*, jr_suspended uses *susp*, jr_flag uses
    // *flagged*, jr_closed uses *yes*. Normalized defensively anyway.
    $blV   = strtolower(trim(trim((string) $r['jr_blacklisted']), '*'));
    $suspV = strtolower(trim(trim((string) $r['jr_suspended']), '*'));
    $flagV = strtolower(trim(trim((string) $r['jr_flag']), '*'));

    $status = 'ok';
    if (in_array($blV, array('bl', 'blacklisted', 'yes'), true))        $status = 'blacklisted';
    elseif (in_array($suspV, array('susp', 'suspended', 'yes'), true))  $status = 'suspended';
    elseif ($flagV === 'flagged')                                       $status = 'flagged';

    $estYear = substr((string) $r['jr_established'], 0, 4);
    if ($estYear === '0000') $estYear = '';

    $rows[] = [
        'id'          => (int) $r['id'],
        'name'        => trim((string) $r['title']),
        'slug'        => trim((string) $r['alias']),
        'owner'       => trim((string) $r['jr_owner']),
        'established' => $estYear,
        'licence'     => array_map('fmt_licence', multi($r['jr_jurisdiction'])),
        'software'    => array_merge(multi($r['jr_soft']), multi($r['jr_multiplesoftware'])),
        'languages'   => multi($r['jr_languages']),
        'currencies'  => array_map('strtoupper', multi($r['jr_currencies'])),
        'livechat'    => trim(trim((string) $r['jr_livesupport']), '*') === 'yes',
        'banking'     => multi($r['jr_banking']),
        'restricted'  => array_map('strtolower', multi($r['jr_restrictedcountries'])),
        'pending'     => fmt_pending($r['jr_pendingperiod']),
        'status'      => $status,
        'flag_reason' => trim((string) $r['jr_flagreason'])
    ];
}

$payload = json_encode([
    'generated' => gmdate('c'),
    'count'     => count($rows),
    'casinos'   => $rows
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$dir = dirname(OUT_FILE);
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    fwrite(STDERR, "Cannot create {$dir}\n");
    exit(1);
}
$tmp = OUT_FILE . '.tmp';
if (file_put_contents($tmp, $payload) === false) {
    fwrite(STDERR, "Write failed: {$tmp}\n");
    exit(1);
}
rename($tmp, OUT_FILE);
echo 'OK: ' . count($rows) . " casinos written, {$skipped} skipped, " . strlen($payload) . " bytes\n";