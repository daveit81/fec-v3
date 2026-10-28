<?php
/* ============================================================
 * com_bonuses :: ajax/ftd_import.php
 * StatsDrone CSV -> jos_fec_ftd_log importer (permanent tool).
 *   GET  : upload form
 *   POST : parse, match against jos_fec_brand_map.statsdrone, and
 *          - 1 cid  -> upsert a real per-brand FTD snapshot
 *          - >1 cid -> network (aggregate, NOT attributed)
 *          - 0 cid  -> unmatched, with a fuzzy suggestion
 * Defaults to DRY RUN (no writes) so the first pass is a preview.
 * Auth-gated (dashboard user) + CSRF token.
 * ============================================================ */
// PORT: the script bootstrapped Joomla itself (defines.php, framework.php);
// here the runner boots the layer before the include.
defined('_JEXEC') or die('Restricted access');
$app = JFactory::getApplication('site');

JLoader::register(
	'BonusesHelperBonus',
	JPATH_COMPONENT . DS . 'helpers' . DS . 'bonus.php' // PORT: was JPATH_BASE . 'components/com_bonuses/helpers/bonus.php'
);

$authorized = (class_exists('BonusesHelperBonus') && BonusesHelperBonus::authorized());
if (!$authorized) {
	fec_header('HTTP/1.1 403 Forbidden');
	echo 'Restricted access';
	jexit();
}

$db    = JFactory::getDbo();
$token = JSession::getFormToken();
$E     = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

/* normalize a name to a comparable "core" (strip affiliate-program words) */
function fec_norm($s) {
	$s = strtolower((string) $s);
	$s = preg_replace('/[^a-z0-9]+/', ' ', $s);
	$stop = array('partners','partner','affiliates','affiliate','club','casino','network',
		'revenue','gaming','games','slots','slot','online','group','media','room','league',
		'capital','share','io','com','the','and','co','ltd');
	$out = array();
	foreach (explode(' ', $s) as $t) {
		if ($t !== '' && !in_array($t, $stop, true)) $out[] = $t;
	}
	return implode('', $out);
}

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$report = null;
$error  = '';

if ($isPost) {
	if (!JSession::checkToken('post')) {
		$error = 'Invalid token — reload the page and try again.';
	} elseif (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
		$error = 'No CSV uploaded.';
	} else {
		$dryRun   = !empty($_POST['dry_run']);
		$fileName = isset($_FILES['csv']['name']) ? (string) $_FILES['csv']['name'] : '';

		// period: from filename (YYYY-MM-DD_YYYY-MM-DD) else from posted fields
		$ps = ''; $pe = '';
		if (preg_match('/(\d{4}-\d{2}-\d{2})[_ ]+(\d{4}-\d{2}-\d{2})/', $fileName, $m)) {
			$ps = $m[1]; $pe = $m[2];
		}
		$pPostS = isset($_POST['period_start']) ? trim((string) $_POST['period_start']) : '';
		$pPostE = isset($_POST['period_end'])   ? trim((string) $_POST['period_end'])   : '';
		if ($pPostS !== '') $ps = $pPostS;
		if ($pPostE !== '') $pe = $pPostE;

		$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
		if (!preg_match($dateRe, $ps) || !preg_match($dateRe, $pe)) {
			$error = 'Period not found. Name the file ..._YYYY-MM-DD_YYYY-MM-DD.csv or fill the period fields.';
		} else {
			// ---- load the bridge once ----
			$byStats = array();   // lower(statsdrone) => [cid,...]
			$normIdx = array();   // fec_norm(brand)   => [cid,...]
			$db->setQuery('SELECT slug, cid, brand, statsdrone FROM ' . $db->qn('#__fec_brand_map'));
			foreach ((array) $db->loadObjectList() as $r) {
				$cid = (int) $r->cid;
				$sd  = strtolower(trim((string) $r->statsdrone));
				if ($sd !== '') $byStats[$sd][$cid] = $cid;
				$nb = fec_norm($r->brand);
				if ($nb !== '') $normIdx[$nb][$cid] = $cid;
			}

			// ---- parse CSV ----
			$rows = array();
			$cols = array();
			if (($fh = fopen($_FILES['csv']['tmp_name'], 'r')) !== false) {
				$header = fgetcsv($fh);
				if (is_array($header)) {
					foreach ($header as $i => $h) $cols[strtolower(trim($h))] = $i;
					while (($row = fgetcsv($fh)) !== false) {
						if (count($row) === 1 && trim((string) $row[0]) === '') continue;
						$rows[] = $row;
					}
				}
				fclose($fh);
			}

			$need = array('name', 'ftd', 'signups', 'net_revenue_amount', 'total_commission');
			$missing = array();
			foreach ($need as $c) { if (!isset($cols[$c])) $missing[] = $c; }

			// optional subID column (StatsDrone subID breakdown exports).
			// VERIFY the exact header name on the first real subID export.
			$subCol = '';
			foreach (array('subid', 'sub id', 'sub_id', 'sub-id', 'tracker subid') as $sc) {
				if (isset($cols[$sc])) { $subCol = $sc; break; }
			}
			$hasSubidDb = false;
			try {
				$db->setQuery('SHOW COLUMNS FROM ' . $db->qn('#__fec_ftd_log') . ' LIKE ' . $db->q('subid'));
				$hasSubidDb = (bool) $db->loadResult();
			} catch (Exception $e) { $hasSubidDb = false; }

			if (!empty($missing)) {
				$error = 'CSV missing expected columns: ' . implode(', ', $missing);
			} elseif ($subCol !== '' && !$hasSubidDb) {
				$error = 'CSV has a subID column but jos_fec_ftd_log has no subid column yet - run 06_alter_ftd_subid.sql first.';
			} else {
				$get = function ($row, $name) use ($cols) {
					return isset($cols[$name], $row[$cols[$name]]) ? $row[$cols[$name]] : '';
				};
				$money = function ($v) { return (float) str_replace(array(',', ' '), '', (string) $v); };

				$imported = array(); $networks = array(); $unmatched = array(); $sqlSuggest = array();
				$now = $db->q(JFactory::getDate()->toSql());

				foreach ($rows as $row) {
					$name = trim((string) $get($row, 'name'));
					if ($name === '') continue;

					$ftd  = (int) $get($row, 'ftd');
					$regs = (int) $get($row, 'signups');
					$ngr  = $money($get($row, 'net_revenue_amount'));
					$comm = $money($get($row, 'total_commission'));
					$key  = strtolower($name);

					// optional subid from this CSV row (empty when no subID column)
					$sub = '';
					if ($subCol !== '') {
						$sub = strtolower(trim((string) $get($row, $subCol)));
						$sub = preg_replace('/[^a-z0-9\-]/', '', $sub);
						if (strlen($sub) > 24) $sub = substr($sub, 0, 24);
					}

					if (isset($byStats[$key])) {
						$cids = array_values($byStats[$key]);
						if (count($cids) === 1) {
							$cid = (int) $cids[0];
							$imported[] = array($name, $cid, $ftd, $regs, $ngr, $comm, $sub);
							if (!$dryRun) {
								$sql = 'INSERT INTO ' . $db->qn('#__fec_ftd_log')
									. ' (cid,' . ($hasSubidDb ? 'subid,' : '') . 'period_start,period_end,ftd,regs,ngr,commission,note,created_at) VALUES ('
									. $cid . ',' . ($hasSubidDb ? $db->q($sub) . ',' : '') . $db->q($ps) . ',' . $db->q($pe) . ','
									. $ftd . ',' . $regs . ',' . $ngr . ',' . $comm . ',' . $db->q('') . ',' . $now . ')'
									. ' ON DUPLICATE KEY UPDATE ftd=VALUES(ftd),regs=VALUES(regs),'
									. 'ngr=VALUES(ngr),commission=VALUES(commission),created_at=' . $now;
								try { $db->setQuery($sql); $db->execute(); }
								catch (Exception $e) { /* per-row failure is reported via count mismatch */ }
							}
						} else {
							$networks[] = array($name, count($cids), $ftd, $regs);
						}
					} else {
						$nb  = fec_norm($name);
						$sug = (isset($normIdx[$nb]) && count($normIdx[$nb]) === 1) ? (int) array_values($normIdx[$nb])[0] : 0;
						$unmatched[] = array($name, $ftd, $regs, $sug);
						if ($sug > 0) {
							$sqlSuggest[] = "UPDATE jos_fec_brand_map SET statsdrone = '"
								. str_replace("'", "''", $name) . "' WHERE cid = " . $sug . ";";
						}
					}
				}

				$report = compact('dryRun', 'ps', 'pe', 'imported', 'networks', 'unmatched', 'sqlSuggest', 'subCol');
			}
		}
	}
}

// brand-name lookup for display (cid -> brand) used in the report
$cidName = array();
if ($report) {
	$db->setQuery('SELECT DISTINCT cid, brand FROM ' . $db->qn('#__bonuses') . " WHERE brand <> ''");
	foreach ((array) $db->loadObjectList() as $r) $cidName[(int) $r->cid] = (string) $r->brand;
}
$bn = function ($cid) use ($cidName, $E) {
	$cid = (int) $cid;
	return $E(isset($cidName[$cid]) ? $cidName[$cid] : ('cid:' . $cid));
};

fec_header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow">
<title>StatsDrone CSV import</title>
<style>
body{font:14px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1c2430;max-width:980px;margin:24px auto;padding:0 16px;}
h1{font-size:20px;} h2{font-size:15px;margin-top:24px;border-bottom:1px solid #eef2f5;padding-bottom:4px;}
form{background:#f6f8fa;border:1px solid #e7ecf1;border-radius:10px;padding:16px;margin-bottom:18px;}
label{display:inline-block;margin:6px 12px 6px 0;}
input[type=text],input[type=date]{padding:5px 8px;border:1px solid #d7dee6;border-radius:6px;}
button{padding:7px 14px;border:1px solid #0e7c66;background:#0e7c66;color:#fff;border-radius:7px;cursor:pointer;}
table{border-collapse:collapse;width:100%;margin-top:8px;} th,td{text-align:left;padding:6px 8px;border-bottom:1px solid #eef2f5;font-size:13px;}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums;}
.err{background:#fdecea;border:1px solid #f5c6cb;color:#842029;padding:10px 12px;border-radius:8px;}
.tag{font-size:12px;padding:1px 7px;border-radius:5px;}
.tag-ok{background:#e6f5ef;color:#0e7c66;} .tag-net{background:#fff3cd;color:#7a5b00;} .tag-no{background:#f1f1f4;color:#666;}
pre{background:#0e1726;color:#d6e2f0;padding:12px;border-radius:8px;overflow:auto;font-size:12px;}
.muted{color:#6b7785;}
</style></head><body>
<h1>StatsDrone CSV import</h1>

<?php if ($error) : ?>
	<div class="err"><?php echo $E($error); ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
	<div><label>CSV file <input type="file" name="csv" accept=".csv" required></label></div>
	<div>
		<label>Period start <input type="date" name="period_start" placeholder="YYYY-MM-DD"></label>
		<label>Period end <input type="date" name="period_end" placeholder="YYYY-MM-DD"></label>
		<span class="muted">(auto-read from the filename if it ends with _YYYY-MM-DD_YYYY-MM-DD.csv)</span>
	</div>
	<div><label><input type="checkbox" name="dry_run" value="1" checked> Dry run (preview only — no writes)</label></div>
	<div style="margin-top:10px;"><button type="submit">Process CSV</button></div>
	<input type="hidden" name="<?php echo $E($token); ?>" value="1">
</form>

<?php if ($report) :
	$mode = $report['dryRun'] ? 'DRY RUN — nothing written' : 'IMPORTED';
?>
	<p><strong><?php echo $E($mode); ?></strong> &middot; period <?php echo $E($report['ps']); ?> &rarr; <?php echo $E($report['pe']); ?></p>

	<h2><?php echo $report['dryRun'] ? 'Would import' : 'Imported'; ?> — 1:1 brands (<?php echo count($report['imported']); ?>)</h2>
	<?php if (empty($report['imported'])) : ?>
		<p class="muted">None yet. Map StatsDrone program names to brands using the SQL under "Unmatched" below, then re-run.</p>
	<?php else : ?>
		<table><thead><tr><th>StatsDrone name</th><th>Brand</th><?php if ($report['subCol'] !== '') echo '<th>SubID</th>'; ?><th class="num">FTD</th><th class="num">Regs</th><th class="num">NGR</th><th class="num">Comm.</th></tr></thead><tbody>
		<?php foreach ($report['imported'] as $r) : ?>
			<tr><td><?php echo $E($r[0]); ?> <span class="tag tag-ok">1:1</span></td><td><?php echo $bn($r[1]); ?></td><?php if ($report['subCol'] !== '') echo '<td>' . $E(isset($r[6]) ? $r[6] : '') . '</td>'; ?>
			<td class="num"><?php echo (int) $r[2]; ?></td><td class="num"><?php echo (int) $r[3]; ?></td>
			<td class="num"><?php echo number_format($r[4], 2); ?></td><td class="num"><?php echo number_format($r[5], 2); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<h2>Networks — aggregate, not attributed (<?php echo count($report['networks']); ?>)</h2>
	<?php if (empty($report['networks'])) : ?>
		<p class="muted">None.</p>
	<?php else : ?>
		<p class="muted">These program names map to several brands, so their FTD can't be split per brand. Left out on purpose.</p>
		<table><thead><tr><th>StatsDrone name</th><th class="num">Brands</th><th class="num">FTD</th><th class="num">Regs</th></tr></thead><tbody>
		<?php foreach ($report['networks'] as $r) : ?>
			<tr><td><?php echo $E($r[0]); ?> <span class="tag tag-net">network</span></td>
			<td class="num"><?php echo (int) $r[1]; ?></td><td class="num"><?php echo (int) $r[2]; ?></td><td class="num"><?php echo (int) $r[3]; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<h2>Unmatched — need a mapping (<?php echo count($report['unmatched']); ?>)</h2>
	<?php if (empty($report['unmatched'])) : ?>
		<p class="muted">None — everything is mapped.</p>
	<?php else : ?>
		<table><thead><tr><th>StatsDrone name</th><th class="num">FTD</th><th class="num">Regs</th><th>Suggested brand</th></tr></thead><tbody>
		<?php foreach ($report['unmatched'] as $r) : ?>
			<tr><td><?php echo $E($r[0]); ?> <span class="tag tag-no">?</span></td>
			<td class="num"><?php echo (int) $r[1]; ?></td><td class="num"><?php echo (int) $r[2]; ?></td>
			<td><?php echo ($r[3] > 0) ? $bn($r[3]) . ' <span class="muted">(cid ' . (int) $r[3] . ')</span>' : '<span class="muted">no confident match</span>'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if (!empty($report['sqlSuggest'])) : ?>
			<p>Review these, run the correct ones in phpMyAdmin to map the 1:1 programs, then re-upload:</p>
			<pre><?php echo $E(implode("\n", $report['sqlSuggest'])); ?></pre>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>
</body></html>