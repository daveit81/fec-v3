<?php
/**
 * com_bonuses :: views/clicks/tmpl/default.php
 * Click report + FTD reconciliation. Presentation only; data prepared
 * in view.html.php. Inline FTD entry posts to ajax/ftd_save.php.
 */
defined('_JEXEC') or die('Restricted access');

if (empty($this->authorized))
{
	return;
}

$E = function ($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};

$days        = isset($this->days) ? (int) $this->days : 14;
$allowedDays = isset($this->allowedDays) ? $this->allowedDays : array(7, 14, 30, 90);
$rows        = isset($this->rows) ? $this->rows : array();
$totals      = isset($this->totals) ? $this->totals : (object) array('clicks' => 0, 'mobile' => 0, 'geos' => 0, 'brands' => 0);
$periodStart = isset($this->periodStart) ? $this->periodStart : date('Y-m-d', strtotime('-14 days'));
$periodEnd   = isset($this->periodEnd) ? $this->periodEnd : date('Y-m-d');
$token       = isset($this->token) ? $this->token : '';

$baseUrl = 'index.php?option=com_bonuses&view=clicks';

$rangeUrl = function ($d) use ($baseUrl) {
	return JRoute::_($baseUrl . '&filter_days=' . (int) $d, false);
};

$pct = function ($part, $whole) {
	$whole = (int) $whole;
	if ($whole <= 0) return '0%';
	return round(($part / $whole) * 100) . '%';
};

$ratio = function ($ftd, $clicks) {
	$clicks = (int) $clicks;
	if ($clicks <= 0) return '&mdash;';
	return number_format(($ftd / $clicks) * 100, 1) . '%';
};
?>
<style>
.fec-clk { max-width: 1100px; margin: 0 auto; padding: 18px; font: 14px/1.45 -apple-system,Segoe UI,Roboto,Arial,sans-serif; color: #1c2430; }
.fec-clk__head { display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
.fec-clk__title { font-size:20px; font-weight:700; margin:0; }
.fec-clk__title small { color:#6b7785; font-weight:500; font-size:13px; }
.fec-clk__range { display:flex; gap:6px; }
.fec-clk__range a { padding:5px 11px; border:1px solid #d7dee6; border-radius:7px; color:#3a4a5a; text-decoration:none; font-size:13px; }
.fec-clk__range a.is-on { background:#0e7c66; border-color:#0e7c66; color:#fff; font-weight:600; }
.fec-clk__cards { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.fec-clk__card { flex:1 1 130px; background:#f6f8fa; border:1px solid #e7ecf1; border-radius:10px; padding:12px 14px; }
.fec-clk__card .n { font-size:22px; font-weight:700; }
.fec-clk__card .l { color:#6b7785; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
.fec-clk__note { font-size:12px; color:#6b7785; margin:-6px 0 14px; }
.fec-clk__wrap { overflow-x:auto; }
.fec-clk__table { width:100%; border-collapse:collapse; min-width:760px; }
.fec-clk__table th, .fec-clk__table td { text-align:left; padding:8px 9px; border-bottom:1px solid #eef2f5; vertical-align:middle; }
.fec-clk__table th { font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:#6b7785; }
.fec-clk__table td.num, .fec-clk__table th.num { text-align:right; font-variant-numeric:tabular-nums; }
.fec-clk__brand { font-weight:600; }
.fec-clk__sep { border-left:2px solid #eef2f5; }
.fec-clk__in { width:66px; padding:4px 6px; border:1px solid #d7dee6; border-radius:6px; font:inherit; text-align:right; }
.fec-clk__in--wide { width:84px; }
.fec-clk__cr { font-weight:700; }
.fec-clk__save { padding:4px 10px; border:1px solid #0e7c66; background:#0e7c66; color:#fff; border-radius:6px; cursor:pointer; font:inherit; font-size:13px; }
.fec-clk__save:disabled { opacity:.45; cursor:default; }
.fec-clk__ok { color:#0e7c66; font-size:12px; margin-left:6px; opacity:0; transition:opacity .2s; }
.fec-clk__ok.show { opacity:1; }
.fec-clk__muted { color:#9aa6b2; }
.fec-clk__empty { padding:30px; text-align:center; color:#6b7785; background:#f6f8fa; border-radius:10px; }
.fec-clk__live { background:#0e1726; color:#d6e2f0; border-radius:10px; padding:12px 14px; margin-bottom:16px; }
.fec-clk__live-head { display:flex; align-items:center; gap:16px; font-size:13px; margin-bottom:8px; }
.fec-clk__pulse { width:9px; height:9px; border-radius:50%; background:#27d796; display:inline-block; margin-right:6px; vertical-align:middle; animation:fecpulse 1.8s infinite; }
@keyframes fecpulse { 0%{box-shadow:0 0 0 0 rgba(39,215,150,.6);} 70%{box-shadow:0 0 0 8px rgba(39,215,150,0);} 100%{box-shadow:0 0 0 0 rgba(39,215,150,0);} }
.fec-clk__live-stat b { font-size:16px; }
.fec-clk__feed { list-style:none; margin:0; padding:0; max-height:210px; overflow-y:auto; }
.fec-clk__ev { display:flex; gap:12px; align-items:baseline; padding:4px 0; border-bottom:1px solid rgba(255,255,255,.07); }
.fec-clk__ev .b { font-weight:600; flex:1; }
.fec-clk__ev .m { color:#8aa0b8; font-size:12px; }
.fec-clk__ev .t { color:#6b8199; font-size:12px; min-width:66px; text-align:right; }
.fec-clk__ev.is-new { animation:fecflash 1.2s ease-out; }
@keyframes fecflash { from{background:rgba(39,215,150,.18);} to{background:transparent;} }
.fec-clk__feed:empty::after { content:"waiting for clicks\2026"; color:#6b8199; font-size:12px; }
</style>

<div class="fec-clk"
	data-endpoint="/components/com_bonuses/ajax/ftd_save.php"
	data-token="<?php echo $E($token); ?>"
	data-pstart="<?php echo $E($periodStart); ?>"
	data-pend="<?php echo $E($periodEnd); ?>">

	<div class="fec-clk__head">
		<h1 class="fec-clk__title">Click report <small>last <?php echo (int) $days; ?> days &middot; bots excluded</small></h1>
		<div class="fec-clk__range">
			<?php foreach ($allowedDays as $d) : ?>
				<a class="<?php echo ($d === $days) ? 'is-on' : ''; ?>" href="<?php echo $E($rangeUrl($d)); ?>"><?php echo (int) $d; ?>d</a>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="fec-clk__cards">
		<div class="fec-clk__card"><div class="n js-card-clicks"><?php echo number_format((int) $totals->clicks); ?></div><div class="l">Clicks</div></div>
		<div class="fec-clk__card"><div class="n js-card-brands"><?php echo (int) $totals->brands; ?></div><div class="l">Brands</div></div>
		<div class="fec-clk__card"><div class="n js-card-mobile"><?php echo $pct((int) $totals->mobile, (int) $totals->clicks); ?></div><div class="l">Mobile</div></div>
		<div class="fec-clk__card"><div class="n js-card-geos"><?php echo (int) $totals->geos; ?></div><div class="l">Countries</div></div>
	</div>

	<div class="fec-clk__live" data-live="/components/com_bonuses/ajax/clicks_live.php" data-days="<?php echo (int) $days; ?>">
		<div class="fec-clk__live-head">
			<span><span class="fec-clk__pulse"></span>Live</span>
			<span class="fec-clk__live-stat"><b class="js-last60">&ndash;</b> last 60 min</span>
			<span class="fec-clk__live-stat"><b class="js-today">&ndash;</b> today</span>
		</div>
		<ul class="fec-clk__feed js-feed"></ul>
	</div>

	<p class="fec-clk__note">FTD / Regs / NGR / Commission are StatsDrone numbers you enter for <strong><?php echo $E($periodStart); ?> &rarr; <?php echo $E($periodEnd); ?></strong> (the current window). Set StatsDrone to a matching range, type, Save. CR = click&rarr;FTD.</p>

	<?php if (empty($rows)) : ?>
		<div class="fec-clk__empty">No clicks recorded in this window yet.</div>
	<?php else : ?>
		<div class="fec-clk__wrap">
		<table class="fec-clk__table">
			<thead>
				<tr>
					<th>Brand</th>
					<th class="num">Clicks</th>
					<th class="num fec-clk__sep">FTD</th>
					<th class="num">Regs</th>
					<th class="num">NGR</th>
					<th class="num">Comm.</th>
					<th class="num">CR</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $r) :
					$clicks = (int) $r->clicks;
					$cid    = (int) $r->canon_cid;
					$snap   = isset($r->ftd) ? $r->ftd : null;
					$vFtd   = $snap ? (int) $snap->ftd : '';
					$vRegs  = $snap ? (int) $snap->regs : '';
					$vNgr   = $snap ? number_format((float) $snap->ngr, 2, '.', '') : '';
					$vComm  = $snap ? number_format((float) $snap->commission, 2, '.', '') : '';
					$crInit = $snap ? $ratio((int) $snap->ftd, $clicks) : '&mdash;';
				?>
				<tr data-cid="<?php echo $cid; ?>" data-clicks="<?php echo $clicks; ?>">
					<td><span class="fec-clk__brand"><?php echo $E($r->brand_name); ?></span></td>
					<td class="num"><?php echo number_format($clicks); ?></td>
					<?php if ($cid > 0) : ?>
						<td class="num fec-clk__sep"><input class="fec-clk__in js-ftd"  type="number" min="0" step="1"    value="<?php echo $vFtd; ?>"></td>
						<td class="num"><input class="fec-clk__in js-regs" type="number" min="0" step="1"    value="<?php echo $vRegs; ?>"></td>
						<td class="num"><input class="fec-clk__in fec-clk__in--wide js-ngr"  type="number" min="0" step="0.01" value="<?php echo $vNgr; ?>"></td>
						<td class="num"><input class="fec-clk__in fec-clk__in--wide js-comm" type="number" min="0" step="0.01" value="<?php echo $vComm; ?>"></td>
						<td class="num fec-clk__cr js-cr"><?php echo $crInit; ?></td>
						<td><button type="button" class="fec-clk__save js-save">Save</button><span class="fec-clk__ok js-ok">saved</span></td>
					<?php else : ?>
						<td class="fec-clk__sep fec-clk__muted" colspan="6">unmapped brand &mdash; add a manual row in jos_fec_brand_map to enable FTD entry</td>
					<?php endif; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>
</div>

<script>
(function () {
	var root = document.querySelector('.fec-clk');
	if (!root) return;

	var endpoint = root.getAttribute('data-endpoint');
	var token    = root.getAttribute('data-token');
	var pStart   = root.getAttribute('data-pstart');
	var pEnd     = root.getAttribute('data-pend');

	function num(el) {
		if (!el) return 0;
		var v = parseFloat(el.value);
		return isNaN(v) ? 0 : v;
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('.js-save');
		if (!btn) return;

		var tr     = btn.closest('tr');
		var cid    = parseInt(tr.getAttribute('data-cid'), 10) || 0;
		var clicks = parseInt(tr.getAttribute('data-clicks'), 10) || 0;
		if (cid <= 0) return;

		var ftd  = Math.round(num(tr.querySelector('.js-ftd')));
		var regs = Math.round(num(tr.querySelector('.js-regs')));
		var ngr  = num(tr.querySelector('.js-ngr'));
		var comm = num(tr.querySelector('.js-comm'));

		var fd = new FormData();
		fd.append('cid', cid);
		fd.append('period_start', pStart);
		fd.append('period_end', pEnd);
		fd.append('ftd', ftd);
		fd.append('regs', regs);
		fd.append('ngr', ngr);
		fd.append('commission', comm);
		if (token) fd.append(token, '1');

		btn.disabled = true;

		fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				btn.disabled = false;
				if (data && data.ok) {
					var cr = tr.querySelector('.js-cr');
					cr.innerHTML = (clicks > 0) ? ((ftd / clicks) * 100).toFixed(1) + '%' : '&mdash;';
					var ok = tr.querySelector('.js-ok');
					ok.classList.add('show');
					setTimeout(function () { ok.classList.remove('show'); }, 1500);
				} else {
					alert('Save failed: ' + (data && data.error ? data.error : 'unknown'));
				}
			})
			.catch(function () {
				btn.disabled = false;
				alert('Save failed (network).');
			});
	});
})();
</script>

<script>
(function () {
	var panel = document.querySelector('.fec-clk__live');
	if (!panel) return;

	var url   = panel.getAttribute('data-live');
	var days  = panel.getAttribute('data-days') || '14';
	var feed  = panel.querySelector('.js-feed');
	var elL60 = panel.querySelector('.js-last60');
	var elTd  = panel.querySelector('.js-today');
	var cClicks = document.querySelector('.js-card-clicks');
	var cBrands = document.querySelector('.js-card-brands');
	var cMobile = document.querySelector('.js-card-mobile');
	var cGeos   = document.querySelector('.js-card-geos');
	var since = 0;
	var CAP   = 120;
	var STEP  = 20000;

	function devLabel(d) { return ({ d: 'desktop', m: 'mobile', t: 'tablet' })[d] || d; }

	function ago(s) {
		s = parseInt(s, 10) || 0;
		if (s < 0) s = 0;
		if (s < 60) return s + 's ago';
		var m = Math.floor(s / 60);
		if (m < 60) return m + 'm ago';
		var h = Math.floor(m / 60);
		if (h < 24) return h + 'h ago';
		return Math.floor(h / 24) + 'd ago';
	}

	function esc(t) {
		var d = document.createElement('div');
		d.textContent = (t == null) ? '' : t;
		return d.innerHTML;
	}

	function addEvent(e) {
		var li = document.createElement('li');
		li.className = 'fec-clk__ev is-new';
		li.innerHTML = '<span class="b">' + esc(e.brand) + '</span>'
			+ '<span class="m">' + esc(e.country || '??') + ' &middot; ' + esc(devLabel(e.device)) + '</span>'
			+ '<span class="t" data-ago="' + (parseInt(e.ago, 10) || 0) + '">' + ago(e.ago) + '</span>';
		feed.insertBefore(li, feed.firstChild);
	}

	function poll() {
		fetch(url + '?since=' + since + '&days=' + days + '&_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d || !d.ok) return;
				if (elL60) elL60.textContent = d.last60;
				if (elTd)  elTd.textContent  = d.today;

				if (d.totals) {
					if (cClicks) cClicks.textContent = Number(d.totals.clicks).toLocaleString('en-US');
					if (cBrands) cBrands.textContent = d.totals.brands;
					if (cMobile) cMobile.textContent = (d.totals.clicks > 0 ? Math.round(d.totals.mobile / d.totals.clicks * 100) : 0) + '%';
					if (cGeos)   cGeos.textContent   = d.totals.geos;
				}

				if (d.events && d.events.length) {
					d.events.slice().reverse().forEach(addEvent);
					since = d.max_id;
					while (feed.children.length > CAP) feed.removeChild(feed.lastChild);
					setTimeout(function () {
						var n = feed.querySelectorAll('.is-new');
						for (var i = 0; i < n.length; i++) n[i].classList.remove('is-new');
					}, 1200);
				} else if (d.max_id && since === 0) {
					since = d.max_id;
				}
			})
			.catch(function () {});
	}

	// age the already-shown rows between polls
	setInterval(function () {
		var t = feed.querySelectorAll('.t');
		for (var i = 0; i < t.length; i++) {
			var a = (parseInt(t[i].getAttribute('data-ago'), 10) || 0) + (STEP / 1000);
			t[i].setAttribute('data-ago', a);
			t[i].textContent = ago(a);
		}
	}, STEP);

	poll();
	setInterval(poll, STEP);
})();
</script>