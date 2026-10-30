#!/usr/bin/env php
<?php
/**
 * FreeExtraChips :: bonuses schedule lifecycle (cron)
 *
 * Keeps jos_bonuses.status in sync with each bonus' start/end dates.
 * Two passes, in this order:
 *
 *   PASS 1 - EXPIRE
 *     status IN ('active','pending')
 *     AND end IS NOT NULL AND end <> '0000-00-00 00:00:00'
 *     AND end < now
 *     -> status = 'expired'
 *
 *     Pending rows with a past end ARE included on purpose: a
 *     scheduled bonus whose window closed without ever going live is
 *     expired, not eternally pending.
 *
 *   PASS 2 - ACTIVATE
 *     status = 'pending'
 *     AND start IS NOT NULL AND start <> '0000-00-00 00:00:00'
 *     AND start <= now
 *     AND (end IS NULL OR end = '0000-00-00 00:00:00' OR end > now)
 *     -> status = 'active'
 *
 *     This mirrors autoStatus() in the dashboard controller (start
 *     in the future -> pending, otherwise active), which only runs
 *     at save time - without this pass a bonus scheduled for
 *     tomorrow would stay 'pending' forever unless re-saved.
 *
 *     DELIBERATELY NOT TOUCHED: pending rows with NO start date.
 *     A pending row with a start date carries an explicit "go live
 *     at X" instruction the cron must honour; one without a start
 *     gives the cron no schedule evidence at all - if it was parked
 *     on purpose, silently publishing it would be a disaster. Those
 *     rows stay visible in the Overview's "Pending to review" panel
 *     (flagged "no start date") for manual triage.
 *
 *     Running expire BEFORE activate means a pending row whose start
 *     AND end have both passed goes straight to expired and is never
 *     momentarily activated (the WHERE conditions already make the
 *     two passes mutually exclusive; the ordering is belt and
 *     braces).
 *
 *   Rows with no end date ("Always on") are never expired.
 *
 * What this script deliberately does NOT do:
 *   - it does NOT stamp jos_bonuses.modified / modified_by. Those
 *     columns track HUMAN editorial actions; this is the system
 *     catching the status up with dates already on the row.
 *     Stamping would flood the dashboard's "Recently modified"
 *     panel with bookkeeping noise.
 *   - it does NOT write jos_bonuses_history snapshots: not an edit,
 *     and a nightly pile of snapshots would bloat the journal with
 *     zero restore value.
 *
 * Design notes:
 *   - Standalone on purpose: no Joomla bootstrap, just
 *     configuration.php + mysqli. Fewer moving parts, immune to the
 *     whole HTTP/LiteSpeed/Cloudflare cache stack, runs identically
 *     under PHP 5.6 and 8.x.
 *   - The "now" cutoff is computed in PHP (date()) rather than SQL
 *     NOW(): every status decision in the dashboard codebase
 *     compares against PHP server time, so the cron must use the
 *     same clock to agree with it.
 *   - Each UPDATE re-applies its full WHERE conditions on the
 *     collected bid list, so a row that changed between the SELECT
 *     and the UPDATE (e.g. you edited it from the dashboard in that
 *     instant) is left alone.
 *
 * Usage:
 *   php bonuses_autoexpire.php [--dry-run]
 *
 *   --dry-run  report what WOULD change, change nothing. Run this
 *              first: the initial run processes the entire backlog
 *              accumulated over the years - both past-end bonuses
 *              still active AND past-start bonuses still pending.
 *
 * Cron (hourly, minute 7):
 *   7 * * * * /opt/cpanel/ea-php56/root/usr/bin/php /home/freeextr/scripts/bonuses_autoexpire.php > /dev/null
 *
 *   IMPORTANT on cPanel/LiteSpeed hosts: the bare `php` binary is
 *   usually lsphp (SAPI 'litespeed'), not a real CLI. Use one of
 *   the EasyApache CLI binaries instead: list the version dirs with
 *   "ls -d /opt/cpanel/ea-php*" - the CLI binary of each lives at
 *   <dir>/root/usr/bin/php.
 *   (The glob cannot be written in full here: a star followed by a
 *   slash would terminate this comment block.)
 *
 *   stdout is silenced (the script keeps its own log file); stderr
 *   is left attached so cPanel emails you only when something
 *   actually breaks.
 *
 * Exit codes: 0 = ok (even when nothing changed), 1 = error.
 */

// ------------------------------------------------------------------
// Hard guard: command-line only.
//
// NOT a simple PHP_SAPI === 'cli' check: on this LiteSpeed/cPanel
// host the default `php` binary is lsphp, whose SAPI reports
// 'litespeed' even when invoked from cron - the strict check would
// (and did) block a legitimate cron run. The real threat model is a
// WEB request, and the evidence of one is the HTTP superglobals
// that every web SAPI populates and no command-line invocation has.
// (The script lives outside public_html anyway - defence in depth.)
// ------------------------------------------------------------------
$fecLooksLikeWeb = isset($_SERVER['REQUEST_METHOD'])
	|| isset($_SERVER['REMOTE_ADDR'])
	|| isset($_SERVER['HTTP_HOST']);

if ($fecLooksLikeWeb)
{
	if (!headers_sent())
	{
		header('HTTP/1.1 403 Forbidden');
	}

	exit('CLI only.');
}

// ------------------------------------------------------------------
// Command-line args must be READABLE. Under lsphp/php-cgi,
// register_argc_argv may be off, leaving $argv unset - which would
// make a '--dry-run' flag silently invisible and turn an intended
// dry run into a REAL run. If we cannot inspect the flags, refuse
// loudly (stderr -> cron emails it) instead of guessing.
// ------------------------------------------------------------------
if (!isset($argv) && isset($_SERVER['argv']))
{
	$argv = $_SERVER['argv'];
}

if (PHP_SAPI !== 'cli' && !isset($argv))
{
	fwrite(
		STDERR,
		"bonuses_autoexpire: running under SAPI '" . PHP_SAPI . "' without readable\n"
		. "command-line arguments (register_argc_argv is off), so flags like\n"
		. "--dry-run cannot be detected reliably. Refusing to run.\n"
		. "Fix: point the cron at a real CLI binary, e.g. one of:\n"
		. "  ls -d /opt/cpanel/ea-php*/root/usr/bin/php\n"
	);

	exit(1);
}

// ------------------------------------------------------------------
// Settings - adjust the config path if the site ever moves.
// ------------------------------------------------------------------
define('FEC_JOOMLA_CONFIG', '/home/freeextr/public_html/configuration.php');
define('FEC_LOG_FILE', __DIR__ . '/bonuses_autoexpire.log');

// cap of per-bonus details in one log/output line; the first backlog
// run may touch hundreds of rows and a 50-entry list is enough to
// audit while staying readable. Counts are always exact.
define('FEC_LOG_DETAIL_CAP', 50);

// ------------------------------------------------------------------
// Tiny output helpers. Normal chatter -> stdout (silenced by the
// suggested crontab); failures -> stderr (cron emails those).
// Every run also appends one line to the log file so a quiet cron
// can still be verified.
// ------------------------------------------------------------------
function fec_out($msg)
{
	fwrite(STDOUT, $msg . "\n");
}

function fec_err($msg)
{
	fwrite(STDERR, $msg . "\n");
}

function fec_log($msg)
{
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";

	// @ on purpose: a broken log file must not break the run.
	@file_put_contents(FEC_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * "#bid Brand (label Y-m-d H:i), ..." detail string, capped.
 *
 * @param   array   $rows     each: array with bid, brand and a date col
 * @param   string  $dateKey  which column to show ('end' or 'start')
 * @param   string  $verb     short verb for the date ('ended', 'started')
 * @return  string
 */
function fec_detail($rows, $dateKey, $verb)
{
	$out = array();

	foreach ($rows as $i => $row)
	{
		if ($i >= FEC_LOG_DETAIL_CAP)
		{
			$out[] = '... and ' . (count($rows) - FEC_LOG_DETAIL_CAP) . ' more';
			break;
		}

		$out[] = '#' . (int) $row['bid'] . ' ' . $row['brand']
			. ' (' . $verb . ' ' . $row[$dateKey] . ')';
	}

	return implode(', ', $out);
}

// ------------------------------------------------------------------
// Args
// ------------------------------------------------------------------
$dryRun = isset($argv) && is_array($argv) && in_array('--dry-run', $argv, true);

// ------------------------------------------------------------------
// Load DB credentials from the Joomla configuration file.
// configuration.php defines class JConfig; in a standalone script
// nothing else has declared it, so a plain require + new is safe.
// ------------------------------------------------------------------
if (!is_readable(FEC_JOOMLA_CONFIG))
{
	fec_err('bonuses_autoexpire: cannot read ' . FEC_JOOMLA_CONFIG);
	fec_log('ERROR cannot read Joomla configuration file');
	exit(1);
}

require FEC_JOOMLA_CONFIG;

if (!class_exists('JConfig'))
{
	fec_err('bonuses_autoexpire: JConfig class not found in configuration file');
	fec_log('ERROR JConfig class not found');
	exit(1);
}

$cfg    = new JConfig();
$prefix = isset($cfg->dbprefix) ? $cfg->dbprefix : 'jos_';
$table  = $prefix . 'bonuses';

// ------------------------------------------------------------------
// Connect. Joomla's $host may carry a port ("host:3306") or a
// socket path ("localhost:/path/to/mysql.sock") - split it the way
// Joomla itself does.
// ------------------------------------------------------------------
if (!class_exists('mysqli'))
{
	fec_err('bonuses_autoexpire: mysqli extension not available in this PHP CLI');
	fec_log('ERROR mysqli extension missing');
	exit(1);
}

$host   = $cfg->host;
$port   = 3306;
$socket = null;

if (strpos($host, ':') !== false)
{
	list($host, $extra) = explode(':', $host, 2);

	if (is_numeric($extra))
	{
		$port = (int) $extra;
	}
	else
	{
		$socket = $extra;
	}
}

// mysqli_report: force exceptions OFF so connect errors surface as
// return values we can log (PHP 8.1 flipped the default to ON).
mysqli_report(MYSQLI_REPORT_OFF);

$db = @new mysqli($host, $cfg->user, $cfg->password, $cfg->db, $port, $socket);

if ($db->connect_errno)
{
	fec_err('bonuses_autoexpire: DB connection failed: ' . $db->connect_error);
	fec_log('ERROR DB connection failed: ' . $db->connect_error);
	exit(1);
}

$db->set_charset('utf8');

// PHP clock, see header note. One cutoff shared by both passes so a
// single run is internally consistent.
$now    = date('Y-m-d H:i:s');
$nowSql = "'" . $db->real_escape_string($now) . "'";

// ==================================================================
// PASS 1 - EXPIRE
// ==================================================================
$sql = "SELECT bid, brand, end"
	 . " FROM `" . $table . "`"
	 . " WHERE status IN ('active','pending')"
	 . " AND end IS NOT NULL"
	 . " AND end <> '0000-00-00 00:00:00'"
	 . " AND end < " . $nowSql
	 . " ORDER BY end ASC";

$res = $db->query($sql);

if ($res === false)
{
	fec_err('bonuses_autoexpire: expire SELECT failed: ' . $db->error);
	fec_log('ERROR expire SELECT failed: ' . $db->error);
	exit(1);
}

$dueExpire = array();

while ($row = $res->fetch_assoc())
{
	$dueExpire[] = $row;
}

$res->free();

// ==================================================================
// PASS 2 - ACTIVATE
// (selected with the same $now cutoff; rows caught by pass 1 cannot
// match here because the end conditions are mutually exclusive)
// ==================================================================
$sql = "SELECT bid, brand, start"
	 . " FROM `" . $table . "`"
	 . " WHERE status = 'pending'"
	 . " AND start IS NOT NULL"
	 . " AND start <> '0000-00-00 00:00:00'"
	 . " AND start <= " . $nowSql
	 . " AND (end IS NULL OR end = '0000-00-00 00:00:00' OR end > " . $nowSql . ")"
	 . " ORDER BY start ASC";

$res = $db->query($sql);

if ($res === false)
{
	fec_err('bonuses_autoexpire: activate SELECT failed: ' . $db->error);
	fec_log('ERROR activate SELECT failed: ' . $db->error);
	exit(1);
}

$dueActivate = array();

while ($row = $res->fetch_assoc())
{
	$dueActivate[] = $row;
}

$res->free();

// ------------------------------------------------------------------
// Nothing due at all?
// ------------------------------------------------------------------
if (empty($dueExpire) && empty($dueActivate))
{
	fec_out('Nothing to do.');
	fec_log(($dryRun ? 'DRY RUN - ' : '') . 'nothing to do');
	$db->close();
	exit(0);
}

$expireDetail   = fec_detail($dueExpire, 'end', 'ended');
$activateDetail = fec_detail($dueActivate, 'start', 'started');

// ------------------------------------------------------------------
// Dry run stops here.
// ------------------------------------------------------------------
if ($dryRun)
{
	fec_out('DRY RUN - would expire ' . count($dueExpire) . ', activate ' . count($dueActivate) . '.');

	if (!empty($dueExpire))
	{
		fec_out('  expire:   ' . $expireDetail);
	}

	if (!empty($dueActivate))
	{
		fec_out('  activate: ' . $activateDetail);
	}

	fec_log(
		'DRY RUN - would expire ' . count($dueExpire)
		. (!empty($dueExpire) ? ' (' . $expireDetail . ')' : '')
		. ', activate ' . count($dueActivate)
		. (!empty($dueActivate) ? ' (' . $activateDetail . ')' : '')
	);

	$db->close();
	exit(0);
}

// ------------------------------------------------------------------
// Apply pass 1. The bid list pins exactly what we logged; the
// repeated WHERE conditions skip anything that changed mid-run.
// NOTE: status only - modified / modified_by intentionally NOT
// touched in either pass (see header).
// ------------------------------------------------------------------
$expired = 0;

if (!empty($dueExpire))
{
	$bids = array();

	foreach ($dueExpire as $row)
	{
		$bids[] = (int) $row['bid'];
	}

	$sql = "UPDATE `" . $table . "`"
		 . " SET status = 'expired'"
		 . " WHERE bid IN (" . implode(',', $bids) . ")"
		 . " AND status IN ('active','pending')"
		 . " AND end IS NOT NULL"
		 . " AND end <> '0000-00-00 00:00:00'"
		 . " AND end < " . $nowSql;

	if ($db->query($sql) === false)
	{
		fec_err('bonuses_autoexpire: expire UPDATE failed: ' . $db->error);
		fec_log('ERROR expire UPDATE failed: ' . $db->error);
		$db->close();
		exit(1);
	}

	$expired = (int) $db->affected_rows;
}

// ------------------------------------------------------------------
// Apply pass 2.
// ------------------------------------------------------------------
$activated = 0;

if (!empty($dueActivate))
{
	$bids = array();

	foreach ($dueActivate as $row)
	{
		$bids[] = (int) $row['bid'];
	}

	$sql = "UPDATE `" . $table . "`"
		 . " SET status = 'active'"
		 . " WHERE bid IN (" . implode(',', $bids) . ")"
		 . " AND status = 'pending'"
		 . " AND start IS NOT NULL"
		 . " AND start <> '0000-00-00 00:00:00'"
		 . " AND start <= " . $nowSql
		 . " AND (end IS NULL OR end = '0000-00-00 00:00:00' OR end > " . $nowSql . ")";

	if ($db->query($sql) === false)
	{
		fec_err('bonuses_autoexpire: activate UPDATE failed: ' . $db->error);
		fec_log('ERROR activate UPDATE failed: ' . $db->error);
		$db->close();
		exit(1);
	}

	$activated = (int) $db->affected_rows;
}

// ------------------------------------------------------------------
// Report
// ------------------------------------------------------------------
fec_out('Expired ' . $expired . ', activated ' . $activated . '.');

if (!empty($dueExpire))
{
	fec_out('  expired:   ' . $expireDetail);
}

if (!empty($dueActivate))
{
	fec_out('  activated: ' . $activateDetail);
}

$mismatch = ($expired !== count($dueExpire)) || ($activated !== count($dueActivate));

if ($mismatch)
{
	// somebody touched a row between SELECT and UPDATE - harmless,
	// just worth a trace
	fec_out('  (note: ' . count($dueExpire) . '/' . count($dueActivate)
		. ' were due, ' . $expired . '/' . $activated
		. ' actually updated - the rest changed mid-run)');
}

fec_log(
	'expired ' . $expired
	. (!empty($dueExpire) ? ' (' . $expireDetail . ')' : '')
	. ', activated ' . $activated
	. (!empty($dueActivate) ? ' (' . $activateDetail . ')' : '')
	. ($mismatch ? ' [mid-run changes detected]' : '')
);

$db->close();
exit(0);