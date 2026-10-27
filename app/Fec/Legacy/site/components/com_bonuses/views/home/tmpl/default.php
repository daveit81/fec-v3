<?php
/**
 * com_bonuses :: views/home/tmpl/default.php
 * Overview / status dashboard - read-only landing page.
 *
 * Data is prepared in view.html.php. No JS on this page: everything
 * is a number, a list, or a link into the manage list with the
 * matching filter pre-applied.
 */
defined('_JEXEC') or die('Restricted access');

if (empty($this->authorized))
{
	return;
}

$E = function ($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};

$counts      = $this->counts;
$authorNames = $this->authorNames;

$manageUrl = JRoute::_('index.php?option=com_bonuses&view=manage', false);
$clicksUrl = JRoute::_('index.php?option=com_bonuses&view=clicks', false);
$addUrl    = JRoute::_('index.php?option=com_bonuses&view=add', false);

// manage-list deep links with a filter pre-applied (limitstart=0
// resets paging; the manage view reads these via
// getUserStateFromRequest so the filter sticks)
$statusUrl = function ($status) {
	return JRoute::_(
		'index.php?option=com_bonuses&view=manage&filter_status=' . urlencode($status) . '&limitstart=0',
		false
	);
};
$noAuthorUrl = JRoute::_(
	'index.php?option=com_bonuses&view=manage&filter_author=-1&limitstart=0',
	false
);
$casinoUrl = function ($cid) {
	return JRoute::_(
		'index.php?option=com_bonuses&view=manage&filter_casino=' . (int) $cid . '&limitstart=0',
		false
	);
};
$editUrl = function ($bid) {
	return JRoute::_('index.php?option=com_bonuses&view=add&bid=' . (int) $bid, false);
};

/**
 * Hybrid timestamp renderer - same behaviour as the manage list:
 * relative under 30 days, plain date beyond, exact timestamp in the
 * tooltip. Also handles FUTURE datetimes ("in 3 days") since this
 * page shows upcoming end/start dates, which the list never does.
 */
$renderWhen = function ($sqlDt) use ($E) {
	if (empty($sqlDt) || $sqlDt === '0000-00-00 00:00:00')
	{
		return '<span class="fec-muted">&mdash;</span>';
	}

	$ts = strtotime($sqlDt);

	if ($ts === false || $ts <= 0)
	{
		return '<span class="fec-muted">&mdash;</span>';
	}

	$now   = time();
	$delta = $now - $ts;
	$abs   = date('d-m-Y H:i', $ts);

	$fmt = function ($secs) {
		if ($secs < 3600)
		{
			$m = max(1, (int) floor($secs / 60));

			return $m . ' minute' . ($m === 1 ? '' : 's');
		}

		if ($secs < 86400)
		{
			$h = (int) floor($secs / 3600);

			return $h . ' hour' . ($h === 1 ? '' : 's');
		}

		$d = (int) floor($secs / 86400);

		return $d . ' day' . ($d === 1 ? '' : 's');
	};

	if ($delta >= 0)
	{
		// past
		$text = ($delta < 60)
			? 'just now'
			: (($delta < 86400 * 30) ? ($fmt($delta) . ' ago') : date('d-m-Y', $ts));
	}
	else
	{
		// future
		$ahead = -$delta;
		$text  = ($ahead < 86400 * 30) ? ('in ' . $fmt($ahead)) : date('d-m-Y', $ts);
	}

	return '<span class="fec-added" title="' . $E($abs) . '">' . $E($text) . '</span>';
};
?>

<div class="fec-dash fec-dash-home">

	<header class="fec-dash-head">
		<div>
			<h1>Overview</h1>
			<p class="fec-dash-sub">
				<?php echo (int) $counts['total']; ?>
				bonus<?php echo ((int) $counts['total'] === 1) ? '' : 'es'; ?> in the catalog
			</p>
		</div>
		<div class="fec-dash-head-actions">
			<a class="fec-btn fec-btn-ghost" href="<?php echo $manageUrl; ?>">Bonus list</a>
			<a class="fec-btn fec-btn-ghost" href="<?php echo $clicksUrl; ?>">Click report</a>
			<a class="fec-btn fec-btn-ghost" href="/components/com_bonuses/ajax/ftd_import.php">Import FTD</a>
			<a class="fec-btn fec-btn-primary" href="<?php echo $addUrl; ?>">+ Add a bonus</a>
		</div>
	</header>

	<!-- ================= KPI TILES ================= -->
	<div class="fec-kpis">
		<a class="fec-kpi" href="<?php echo $statusUrl(''); ?>">
			<span class="fec-kpi-num"><?php echo (int) $counts['total']; ?></span>
			<span class="fec-kpi-label">Total</span>
		</a>
		<a class="fec-kpi fec-kpi-active" href="<?php echo $statusUrl('active'); ?>">
			<span class="fec-kpi-num"><?php echo (int) $counts['active']; ?></span>
			<span class="fec-kpi-label">Active</span>
		</a>
		<a class="fec-kpi fec-kpi-pending" href="<?php echo $statusUrl('pending'); ?>">
			<span class="fec-kpi-num"><?php echo (int) $counts['pending']; ?></span>
			<span class="fec-kpi-label">Pending</span>
		</a>
		<a class="fec-kpi fec-kpi-expired" href="<?php echo $statusUrl('expired'); ?>">
			<span class="fec-kpi-num"><?php echo (int) $counts['expired']; ?></span>
			<span class="fec-kpi-label">Expired</span>
		</a>
		<a class="fec-kpi fec-kpi-noauthor" href="<?php echo $noAuthorUrl; ?>">
			<span class="fec-kpi-num"><?php echo (int) $this->noAuthorCount; ?></span>
			<span class="fec-kpi-label">No author</span>
		</a>
	</div>

	<!-- ================= PANELS ================= -->
	<div class="fec-home-grid">

		<!-- Expiring soon -->
		<section class="fec-panel">
			<h2 class="fec-panel-title">
				Expiring within 7 days
				<span class="fec-panel-count"><?php echo count($this->expiringSoon); ?></span>
			</h2>
			<?php if (empty($this->expiringSoon)) : ?>
				<p class="fec-panel-empty">Nothing ends in the next 7 days.</p>
			<?php else : ?>
				<ul class="fec-panel-list">
					<?php foreach ($this->expiringSoon as $r) : ?>
						<li>
							<a href="<?php echo $editUrl($r->bid); ?>">
								<strong><?php echo $E($r->brand); ?></strong>
								<span class="fec-muted"><?php echo $E($r->label); ?></span>
							</a>
							<span class="fec-panel-when"><?php echo $renderWhen($r->end); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<!-- Pending to review -->
		<section class="fec-panel">
			<h2 class="fec-panel-title">
				Pending to review
				<span class="fec-panel-count"><?php echo (int) $counts['pending']; ?></span>
			</h2>
			<?php if (empty($this->pendingList)) : ?>
				<p class="fec-panel-empty">No pending bonuses.</p>
			<?php else : ?>
				<ul class="fec-panel-list">
					<?php foreach ($this->pendingList as $r) : ?>
						<li>
							<a href="<?php echo $editUrl($r->bid); ?>">
								<strong><?php echo $E($r->brand); ?></strong>
								<span class="fec-muted"><?php echo $E($r->label); ?></span>
							</a>
							<span class="fec-panel-when">
								<?php if (!empty($r->start)) : ?>
									goes live <?php echo $renderWhen($r->start); ?>
								<?php else : ?>
									<span class="fec-muted">no start date</span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ((int) $counts['pending'] > count($this->pendingList)) : ?>
					<p class="fec-panel-more">
						<a href="<?php echo $statusUrl('pending'); ?>">
							See all <?php echo (int) $counts['pending']; ?> pending &rarr;
						</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<!-- Top casinos -->
		<section class="fec-panel">
			<h2 class="fec-panel-title">Top casinos by active bonuses</h2>
			<?php if (empty($this->topCasinos)) : ?>
				<p class="fec-panel-empty">No active bonuses yet.</p>
			<?php else : ?>
				<ol class="fec-panel-list fec-panel-ranked">
					<?php foreach ($this->topCasinos as $r) : ?>
						<li>
							<a href="<?php echo $casinoUrl($r->cid); ?>">
								<strong><?php echo $E($r->brand); ?></strong>
							</a>
							<span class="fec-panel-when">
								<?php echo (int) $r->cnt; ?> active
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</section>

		<!-- Recently modified -->
		<section class="fec-panel">
			<h2 class="fec-panel-title">Recently modified</h2>
			<?php if (empty($this->recentlyModified)) : ?>
				<p class="fec-panel-empty">
					Nothing tracked yet &mdash; the modified column fills in
					as bonuses get edited.
				</p>
			<?php else : ?>
				<ul class="fec-panel-list">
					<?php foreach ($this->recentlyModified as $r) : ?>
						<?php
						$byId   = isset($r->modified_by) ? (int) $r->modified_by : 0;
						$byName = ($byId > 0 && isset($authorNames[$byId])) ? $authorNames[$byId] : '';
						?>
						<li>
							<a href="<?php echo $editUrl($r->bid); ?>">
								<strong><?php echo $E($r->brand); ?></strong>
								<span class="fec-muted"><?php echo $E($r->label); ?></span>
							</a>
							<span class="fec-panel-when">
								<?php echo $renderWhen($r->modified); ?>
								<?php if ($byName !== '') : ?>
									<span class="fec-muted">by <?php echo $E($byName); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<!-- Expired in the last 30 days -->
		<section class="fec-panel">
			<h2 class="fec-panel-title">
				Expired in the last 30 days
				<span class="fec-panel-count"><?php echo (int) $this->expired30Count; ?></span>
			</h2>
			<?php
			/*
			 * Proxy note, also documented in view.html.php: "expired in
			 * the last 30 days" means status='expired' AND the end date
			 * fell in that window. There is no expired_at column, so
			 * bonuses expired manually without an end date cannot be
			 * dated and are not listed here (they remain in the
			 * Expired KPI tile above).
			 */
			?>
			<?php if (empty($this->expired30)) : ?>
				<p class="fec-panel-empty">No bonuses ended in the last 30 days.</p>
			<?php else : ?>
				<ul class="fec-panel-list">
					<?php foreach ($this->expired30 as $r) : ?>
						<li>
							<a href="<?php echo $editUrl($r->bid); ?>">
								<strong><?php echo $E($r->brand); ?></strong>
								<span class="fec-muted"><?php echo $E($r->label); ?></span>
							</a>
							<span class="fec-panel-when">ended <?php echo $renderWhen($r->end); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ((int) $this->expired30Count > count($this->expired30)) : ?>
					<p class="fec-panel-more">
						<a href="<?php echo $statusUrl('expired'); ?>">
							See all expired &rarr;
						</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

	</div><!-- /.fec-home-grid -->

</div>