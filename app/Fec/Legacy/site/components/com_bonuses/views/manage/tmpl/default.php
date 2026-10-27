<?php
/**
 * com_bonuses :: views/manage/tmpl/default.php
 * Bonus dashboard index - filterable, sortable table with per-row
 * actions and bulk operations.
 *
 * Data is prepared in view.html.php. Presentation only here.
 *
 * Per-row actions:
 *   Edit         -> view=add&bid=NNN
 *   Toggle       -> task=actions.toggle&bid=NNN     + CSRF token
 *   Duplicate    -> task=actions.duplicate&bid=NNN  + CSRF token
 *   Delete       -> task=actions.remove&bid=NNN     + CSRF token + confirm
 *   Author       -> dropdown + Save (AJAX, task=actions.setauthor)
 *   Modified by  -> dropdown + Save (AJAX, task=actions.setmodifiedby)
 *                   Also stamps the modified timestamp under the hood.
 *
 * Bulk actions: Expire / Publish / Delete / Change author.
 */
defined('_JEXEC') or die('Restricted access');

if (empty($this->authorized))
{
	return;
}

$E = function ($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};

$filter           = $this->filter;
$orderDir         = isset($this->orderDir) ? $this->orderDir : 'desc';
$counts           = isset($this->statusCounts) ? $this->statusCounts : array(
	'active' => 0, 'pending' => 0, 'expired' => 0, 'total' => 0,
);
$noAuthorSentinel = (int) $this->filterAuthorNone;
$token            = JSession::getFormToken();
$addUrl           = JRoute::_('index.php?option=com_bonuses&view=add', false);
$listUrl          = 'index.php?option=com_bonuses&view=manage';

$statusMeta = array(
	'active'  => array('ok',      'Active'),
	'pending' => array('pending', 'Pending'),
	'expired' => array('expired', 'Expired'),
);

$toggleUrl = function ($bid) use ($token) {
	return JRoute::_(
		'index.php?option=com_bonuses&task=actions.toggle&bid=' . (int) $bid . '&' . $token . '=1',
		false
	);
};
$duplicateUrl = function ($bid) use ($token) {
	return JRoute::_(
		'index.php?option=com_bonuses&task=actions.duplicate&bid=' . (int) $bid . '&' . $token . '=1',
		false
	);
};
$removeUrl = function ($bid) use ($token) {
	return JRoute::_(
		'index.php?option=com_bonuses&task=actions.remove&bid=' . (int) $bid . '&' . $token . '=1',
		false
	);
};

$flippedDir = ($orderDir === 'asc') ? 'desc' : 'asc';
$sortUrl    = JRoute::_($listUrl . '&list_order_dir=' . $flippedDir, false);

$sortArrow  = ($orderDir === 'asc') ? '&#9650;' : '&#9660;';
$sortLabel  = ($orderDir === 'asc')
	? 'Sorted oldest first - click to sort newest first'
	: 'Sorted newest first - click to sort oldest first';

$chipUrl = function ($status) use ($listUrl) {
	return JRoute::_(
		$listUrl . '&filter_status=' . urlencode($status) . '&limitstart=0',
		false
	);
};

$clearUrl = JRoute::_(
	$listUrl . '&filter_casino=0&filter_type=&filter_status=&filter_author=0&filter_search=&filter_pkg=&limitstart=0',
	false
);

$filterPkg = isset($filter['pkg']) ? (string) $filter['pkg'] : '';
$pkgBadges = isset($this->pkgBadges) ? $this->pkgBadges : array();
$pkgLabels = array(
	'in'         => 'In a welcome package',
	'incomplete' => 'Package terms to enter',
	'out'        => 'Not in a package',
);

/**
 * Hybrid timestamp renderer.
 * Used for the Added column AND for the "edited X ago" subtitle in
 * the Modified-by column.
 */
$renderTimestamp = function ($sqlDt) use ($E) {
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

	if ($delta < 0)
	{
		$text = $abs;
	}
	elseif ($delta < 60)
	{
		$text = 'just now';
	}
	elseif ($delta < 3600)
	{
		$m    = (int) floor($delta / 60);
		$text = $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
	}
	elseif ($delta < 86400)
	{
		$h    = (int) floor($delta / 3600);
		$text = $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
	}
	elseif ($delta < 86400 * 30)
	{
		$d    = (int) floor($delta / 86400);
		$text = $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
	}
	else
	{
		$text = date('d-m-Y', $ts);
	}

	return '<span class="fec-added" title="' . $E($abs) . '">' . $E($text) . '</span>';
};

/* build active-filters list */
$activeFilters = array();

if ($filter['casino'] > 0)
{
	$casinoName = '';

	foreach ($this->casinos as $c)
	{
		if ((int) $c['id'] === (int) $filter['casino'])
		{
			$casinoName = (string) $c['title'];
			break;
		}
	}

	$activeFilters[] = array(
		'label' => 'Casino',
		'value' => ($casinoName !== '') ? $casinoName : ('#' . (int) $filter['casino']),
	);
}

if ($filter['type'] !== '')
{
	$typeLabel = '';

	foreach ($this->bonusType as $k => $lbl)
	{
		if (strtolower($k) === $filter['type'])
		{
			$typeLabel = (string) $lbl;
			break;
		}
	}

	$activeFilters[] = array(
		'label' => 'Type',
		'value' => ($typeLabel !== '') ? $typeLabel : $filter['type'],
	);
}

if ($filter['status'] !== '')
{
	$statusLabel = isset($statusMeta[$filter['status']])
		? $statusMeta[$filter['status']][1]
		: ucfirst($filter['status']);

	$activeFilters[] = array(
		'label' => 'Status',
		'value' => $statusLabel,
	);
}

if ((int) $filter['author'] !== 0)
{
	if ((int) $filter['author'] === $noAuthorSentinel)
	{
		$authorName = '— No author —';
	}
	else
	{
		$authorName = '';

		foreach ($this->authors as $a)
		{
			if ((int) $a['id'] === (int) $filter['author'])
			{
				$authorName = (string) $a['name'];
				break;
			}
		}

		if ($authorName === '')
		{
			$authorName = '#' . (int) $filter['author'];
		}
	}

	$activeFilters[] = array(
		'label' => 'Author',
		'value' => $authorName,
	);
}

if ($filter['search'] !== '')
{
	$activeFilters[] = array(
		'label' => 'Search',
		'value' => '"' . $filter['search'] . '"',
	);
}

if ($filterPkg !== '' && isset($pkgLabels[$filterPkg]))
{
	$activeFilters[] = array(
		'label' => 'Package',
		'value' => $pkgLabels[$filterPkg],
	);
}

$hasActiveFilters = !empty($activeFilters);
?>

<div class="fec-dash fec-dash-list">

	<script>
	if (window.location && document.head) {
		var meta = document.createElement('meta');
		meta.setAttribute('name', 'cf-speedbrain');
		meta.setAttribute('content', 'off');
		document.head.appendChild(meta);
	}
	</script>

	<header class="fec-dash-head">
		<div>
			<h1>Bonus dashboard</h1>
			<p class="fec-dash-sub">
				<?php echo (int) $this->total; ?>
				bonus<?php echo ((int) $this->total === 1) ? '' : 'es'; ?>
				<?php if ($hasActiveFilters) { echo ' matching the current filters'; } ?>
			</p>
		</div>
		<div class="fec-dash-head-actions">
			<a class="fec-btn fec-btn-ghost"
			   href="<?php echo JRoute::_('index.php?option=com_bonuses&view=home', false); ?>">
				Overview
			</a>
			<a class="fec-btn fec-btn-primary" href="<?php echo $addUrl; ?>">+ Add a bonus</a>
		</div>
	</header>

	<?php
	$msgs = JFactory::getApplication()->getMessageQueue();
	if (!empty($msgs)) :
		foreach ($msgs as $m) :
			$cls = ($m['type'] === 'error') ? 'is-error' : 'is-success';
			?>
			<div class="fec-banner <?php echo $cls; ?>"><?php echo $E($m['message']); ?></div>
			<?php
		endforeach;
	endif;
	?>

	<div id="fec-list-banner" class="fec-banner" style="display:none;"></div>

	<!-- ================= FILTER BAR ================= -->
	<form method="post" action="<?php echo JRoute::_($listUrl, false); ?>" class="fec-filterbar">

		<div class="fec-filter">
			<label>Casino</label>
			<select name="filter_casino" class="form-control" onchange="this.form.submit()">
				<option value="0">All casinos</option>
				<?php foreach ($this->casinos as $c) : ?>
					<option value="<?php echo (int) $c['id']; ?>"
						<?php echo ((int) $filter['casino'] === (int) $c['id']) ? ' selected="selected"' : ''; ?>>
						<?php echo $E($c['title']); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="fec-filter">
			<label>Type</label>
			<select name="filter_type" class="form-control" onchange="this.form.submit()">
				<option value="">All types</option>
				<?php foreach ($this->bonusType as $key => $label) : ?>
					<option value="<?php echo $E(strtolower($key)); ?>"
						<?php echo ($filter['type'] === strtolower($key)) ? ' selected="selected"' : ''; ?>>
						<?php echo $E($label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="fec-filter">
			<label>Status</label>
			<select name="filter_status" class="form-control" onchange="this.form.submit()">
				<option value="">All statuses</option>
				<option value="active"<?php echo ($filter['status'] === 'active') ? ' selected="selected"' : ''; ?>>Active</option>
				<option value="pending"<?php echo ($filter['status'] === 'pending') ? ' selected="selected"' : ''; ?>>Pending</option>
				<option value="expired"<?php echo ($filter['status'] === 'expired') ? ' selected="selected"' : ''; ?>>Expired</option>
			</select>
		</div>

		<div class="fec-filter">
			<label>Author</label>
			<select name="filter_author" class="form-control" onchange="this.form.submit()">
				<option value="0"<?php echo ((int) $filter['author'] === 0) ? ' selected="selected"' : ''; ?>>All authors</option>
				<option value="<?php echo $noAuthorSentinel; ?>"<?php echo ((int) $filter['author'] === $noAuthorSentinel) ? ' selected="selected"' : ''; ?>>
					&mdash; No author &mdash;
				</option>
				<?php foreach ($this->authors as $a) : ?>
					<option value="<?php echo (int) $a['id']; ?>"
						<?php echo ((int) $filter['author'] === (int) $a['id']) ? ' selected="selected"' : ''; ?>>
						<?php echo $E($a['name']); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="fec-filter">
			<label>Package</label>
			<select name="filter_pkg" class="form-control" onchange="this.form.submit()">
				<option value=""<?php echo ($filterPkg === '') ? ' selected="selected"' : ''; ?>>All</option>
				<?php foreach ($pkgLabels as $k => $lbl) : ?>
					<option value="<?php echo $E($k); ?>"<?php echo ($filterPkg === $k) ? ' selected="selected"' : ''; ?>><?php echo $E($lbl); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="fec-filter fec-filter-grow">
			<label>Search</label>
			<input type="text" name="filter_search" class="form-control"
				value="<?php echo $E($filter['search']); ?>"
				placeholder="Brand, label or code&hellip;" />
		</div>

		<div class="fec-filter fec-filter-actions">
			<button type="submit" class="fec-btn fec-btn-primary">Filter</button>
			<a class="fec-btn fec-btn-ghost" href="<?php echo $clearUrl; ?>">Reset</a>
		</div>
	</form>

	<!-- ================= STATUS CHIPS ================= -->
	<?php
	$chips = array(
		array('key' => '',        'label' => 'All',     'count' => (int) $counts['total']),
		array('key' => 'active',  'label' => 'Active',  'count' => (int) $counts['active']),
		array('key' => 'pending', 'label' => 'Pending', 'count' => (int) $counts['pending']),
		array('key' => 'expired', 'label' => 'Expired', 'count' => (int) $counts['expired']),
	);
	?>
	<div class="fec-chips" role="tablist" aria-label="Filter bonuses by status">
		<?php foreach ($chips as $chip) : ?>
			<?php
			$isActive  = ($filter['status'] === $chip['key']);
			$cssClass  = 'fec-chip';
			$cssClass .= ($chip['key'] !== '') ? (' fec-chip-' . $E($chip['key'])) : ' fec-chip-all';
			if ($isActive) { $cssClass .= ' is-active'; }
			?>
			<a class="<?php echo $cssClass; ?>"
			   href="<?php echo $chipUrl($chip['key']); ?>"
			   role="tab"
			   aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
			   rel="nofollow noprefetch noopener">
				<span class="fec-chip-label"><?php echo $E($chip['label']); ?></span>
				<span class="fec-chip-count"><?php echo (int) $chip['count']; ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- ================= ACTIVE-FILTERS SUMMARY ================= -->
	<?php if ($hasActiveFilters) : ?>
		<div class="fec-active-filters" role="status" aria-live="polite">
			<span class="fec-active-filters-label">Filtering by:</span>
			<span class="fec-active-filters-list">
				<?php
				$tags = array();
				foreach ($activeFilters as $af)
				{
					$tags[] = '<span class="fec-active-filter">'
						. '<span class="fec-active-filter-key">' . $E($af['label']) . ':</span> '
						. '<span class="fec-active-filter-val">' . $E($af['value']) . '</span>'
						. '</span>';
				}
				echo implode(' <span class="fec-active-filter-sep">&middot;</span> ', $tags);
				?>
			</span>
			<a class="fec-btn fec-btn-sm fec-btn-clear"
			   href="<?php echo $clearUrl; ?>"
			   rel="nofollow noprefetch noopener">
				Clear all (<?php echo (int) count($activeFilters); ?>)
			</a>
		</div>
	<?php endif; ?>

	<!-- ================= BULK ACTIONS TOOLBAR ================= -->
	<div id="fec-bulk-toolbar" class="fec-bulk-toolbar" style="display:none;" aria-live="polite">
		<div class="fec-bulk-summary">
			<strong><span id="fec-bulk-count">0</span> selected</strong>
			<button type="button" id="fec-bulk-clear" class="fec-btn fec-btn-sm fec-btn-ghost">
				Clear selection
			</button>
		</div>

		<div class="fec-bulk-actions">
			<button type="button" class="fec-btn fec-btn-sm fec-btn-warn fec-bulk-btn"
					data-bulk-action="expire">Expire</button>
			<button type="button" class="fec-btn fec-btn-sm fec-btn-ok fec-bulk-btn"
					data-bulk-action="publish">Publish</button>
			<button type="button" class="fec-btn fec-btn-sm fec-btn-danger fec-bulk-btn"
					data-bulk-action="delete">Delete</button>

			<span class="fec-bulk-sep">|</span>

			<label class="fec-bulk-author-label">Change author:</label>
			<select id="fec-bulk-author-select" class="form-control fec-bulk-author-select">
				<option value="0">&mdash; No author &mdash;</option>
				<?php foreach ($this->authors as $a) : ?>
					<option value="<?php echo (int) $a['id']; ?>"><?php echo $E($a['name']); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="fec-btn fec-btn-sm fec-btn-primary fec-bulk-btn"
					data-bulk-action="setauthor">Apply</button>
		</div>
	</div>

	<!-- ================= TABLE ================= -->
	<?php if (empty($this->items)) : ?>

		<div class="fec-empty">
			<p>No bonuses found.</p>
			<a class="fec-btn fec-btn-primary" href="<?php echo $addUrl; ?>">Add the first one</a>
		</div>

	<?php else : ?>

		<table class="fec-table">
			<thead>
				<tr>
					<th class="fec-col-check">
						<input type="checkbox" id="fec-master-check"
							aria-label="Select all rows on this page" />
					</th>
					<th class="fec-col-id">#</th>
					<th>Casino</th>
					<th>Type</th>
					<th>Bonus</th>
					<th>Code</th>
					<th>Window</th>
					<th>Status</th>
					<th class="fec-col-sortable fec-col-sorted">
						<a href="<?php echo $sortUrl; ?>"
						   title="<?php echo $E($sortLabel); ?>"
						   rel="nofollow noprefetch noopener"
						   class="fec-sort-link">
							Added
							<span class="fec-sort-arrow"><?php echo $sortArrow; ?></span>
						</a>
					</th>
					<th>Author</th>
					<th>Modified by</th>
					<th class="fec-col-actions">Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($this->items as $row) : ?>
				<?php
				$sKey  = strtolower((string) $row->status);
				$sMeta = isset($statusMeta[$sKey]) ? $statusMeta[$sKey] : array('', ucfirst($sKey));

				$startTxt = BonusesHelperBonus::dbToFormDate($row->start);
				$endTxt   = BonusesHelperBonus::dbToFormDate($row->end);

				$isExpired = ($sKey === 'expired');
				$toggleLbl = $isExpired ? 'Publish' : 'Expire';
				$toggleCls = $isExpired ? 'fec-btn-ok' : 'fec-btn-warn';

				$rowAuthorId     = isset($row->author_id)   ? (int) $row->author_id   : 0;
				$rowModifiedById = isset($row->modified_by) ? (int) $row->modified_by : 0;
				$rowModified     = isset($row->modified)    ? $row->modified          : '';
				?>
				<tr id="bonus-<?php echo (int) $row->bid; ?>">
					<td class="fec-col-check">
						<input type="checkbox"
							class="fec-row-check"
							value="<?php echo (int) $row->bid; ?>"
							aria-label="Select bonus <?php echo (int) $row->bid; ?>" />
					</td>

					<td class="fec-col-id"><?php echo (int) $row->bid; ?></td>

					<td>
						<strong><?php echo $E($row->brand); ?></strong>
						<?php if ((string) $row->exclusive === 'yes') : ?>
							<span class="fec-tag fec-tag-excl">Exclusive</span>
						<?php endif; ?>
					</td>

					<td>
						<?php echo $E($row->label !== '' ? $row->label : BonusesHelperBonus::typeLabel($row->type)); ?>
						<?php if (isset($pkgBadges[(int) $row->bid])) : ?>
							<?php
							$pb  = $pkgBadges[(int) $row->bid];
							$cls = 'fec-tag fec-tag-pkg';
							if ($pb['incomplete']) { $cls .= ' is-incomplete'; }
							if ($pb['status'] !== 'active') { $cls .= ' is-paused'; }
							$ttl = $pb['title'] . ' #' . $pb['pkg_id'] . ($pb['incomplete'] ? ' - some legs still need their terms' : '');
							?>
							<br /><span class="<?php echo $cls; ?>" title="<?php echo $E($ttl); ?>">PKG <?php echo (int) $pb['position']; ?>/<?php echo (int) $pb['deposits']; ?></span>
						<?php endif; ?>
					</td>

					<td>
						<?php
						if ($row->bonus !== null && $row->bonus !== '')
						{
							echo $E($row->bonus);
							if ((string) $row->currency !== '')
							{
								echo ' <span class="fec-muted">' . $E(strtoupper($row->currency)) . '</span>';
							}
						}
						else
						{
							echo '<span class="fec-muted">&mdash;</span>';
						}
						?>
					</td>

					<td>
						<?php echo ((string) $row->code !== '')
							? '<code>' . $E($row->code) . '</code>'
							: '<span class="fec-muted">&mdash;</span>'; ?>
					</td>

					<td class="fec-col-window">
						<?php if ($startTxt === '' && $endTxt === '') : ?>
							<span class="fec-muted">Always on</span>
						<?php else : ?>
							<span class="fec-muted">From</span> <?php echo $E($startTxt !== '' ? $startTxt : '&mdash;'); ?><br />
							<span class="fec-muted">To</span> <?php echo $E($endTxt !== '' ? $endTxt : '&mdash;'); ?>
						<?php endif; ?>
					</td>

					<td>
						<span class="fec-status fec-status-<?php echo $E($sMeta[0]); ?>">
							<?php echo $E($sMeta[1]); ?>
						</span>
					</td>

					<td class="fec-col-added">
						<?php echo $renderTimestamp(isset($row->added) ? $row->added : ''); ?>
					</td>

					<td class="fec-col-author">
						<div class="fec-author-cell"
							 data-bid="<?php echo (int) $row->bid; ?>"
							 data-initial="<?php echo $rowAuthorId; ?>">
							<select class="form-control fec-author-select">
								<option value="0"<?php echo ($rowAuthorId === 0) ? ' selected="selected"' : ''; ?>>
									&mdash; No author &mdash;
								</option>
								<?php foreach ($this->authors as $a) : ?>
									<option value="<?php echo (int) $a['id']; ?>"
										<?php echo ($rowAuthorId === (int) $a['id']) ? ' selected="selected"' : ''; ?>>
										<?php echo $E($a['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button"
									class="fec-btn fec-btn-sm fec-btn-primary fec-author-save"
									style="display:none;">
								Save
							</button>
						</div>
					</td>

					<td class="fec-col-modified">
						<div class="fec-modified-cell"
							 data-bid="<?php echo (int) $row->bid; ?>"
							 data-initial="<?php echo $rowModifiedById; ?>">
							<select class="form-control fec-modified-select">
								<option value="0"<?php echo ($rowModifiedById === 0) ? ' selected="selected"' : ''; ?>>
									&mdash; No editor &mdash;
								</option>
								<?php foreach ($this->authors as $a) : ?>
									<option value="<?php echo (int) $a['id']; ?>"
										<?php echo ($rowModifiedById === (int) $a['id']) ? ' selected="selected"' : ''; ?>>
										<?php echo $E($a['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button"
									class="fec-btn fec-btn-sm fec-btn-primary fec-modified-save"
									style="display:none;">
								Save
							</button>
						</div>
						<div class="fec-modified-when">
							<?php if (!empty($rowModified)) : ?>
								edited <?php echo $renderTimestamp($rowModified); ?>
							<?php else : ?>
								<span class="fec-muted">never edited</span>
							<?php endif; ?>
						</div>
					</td>

					<td class="fec-col-actions">
						<a class="fec-btn fec-btn-sm fec-btn-ghost"
						   href="<?php echo JRoute::_('index.php?option=com_bonuses&view=add&bid=' . (int) $row->bid, false); ?>">
							Edit
						</a>

						<a class="fec-btn fec-btn-sm <?php echo $toggleCls; ?>"
						   href="<?php echo $toggleUrl($row->bid); ?>"
						   rel="nofollow noprefetch noopener"
						   data-no-instant>
							<?php echo $toggleLbl; ?>
						</a>

						<a class="fec-btn fec-btn-sm fec-btn-ghost js-confirm-duplicate"
						   href="<?php echo $duplicateUrl($row->bid); ?>"
						   data-bid="<?php echo (int) $row->bid; ?>"
						   rel="nofollow noprefetch noopener"
						   data-no-instant>
							Duplicate
						</a>

						<a class="fec-btn fec-btn-sm fec-btn-danger js-confirm-delete"
						   href="<?php echo $removeUrl($row->bid); ?>"
						   data-bid="<?php echo (int) $row->bid; ?>"
						   rel="nofollow noprefetch noopener"
						   data-no-instant>
							Delete
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="fec-pagination">
			<?php echo $this->pagination->getListFooter(); ?>
		</div>

	<?php endif; ?>

</div>

<script>
jQuery(document).ready(function ($) {

	var CSRF_TOKEN = <?php echo json_encode($token); ?>;

	$('.js-confirm-delete').on('click', function (e) {
		var bid = $(this).data('bid');
		if (!window.confirm('Delete bonus #' + bid + '? This cannot be undone.')) {
			e.preventDefault();
		}
	});

	$('.js-confirm-duplicate').on('click', function (e) {
		var bid = $(this).data('bid');
		if (!window.confirm('Duplicate bonus #' + bid + ' as a new pending bonus?')) {
			e.preventDefault();
		}
	});

	/* --------------------------------------------------------------
	 * Shared banner helpers (used by setauthor / setmodifiedby /
	 * bulk* AJAX endpoints).
	 * -------------------------------------------------------------- */
	var $banner = $('#fec-list-banner');

	function showBanner(ok, msg) {
		$banner
			.removeClass('is-success is-error is-busy')
			.addClass(ok ? 'is-success' : 'is-error')
			.text(msg)
			.show();
	}

	function hideBanner() {
		$banner.hide().text('').removeClass('is-success is-error is-busy');
	}

	/* --------------------------------------------------------------
	 * INLINE AUTHOR CHANGE  (per-row dropdown + Save)
	 * -------------------------------------------------------------- */
	$('.fec-author-cell').each(function () {
		var $cell    = $(this);
		var $select  = $cell.find('.fec-author-select');
		var $btn     = $cell.find('.fec-author-save');

		$select.on('change', function () {
			if (String($select.val()) === String($cell.data('initial'))) {
				$btn.hide();
			} else {
				$btn.show();
			}
		});

		$btn.on('click', function () {
			var bid      = parseInt($cell.data('bid'), 10);
			var authorId = parseInt($select.val(), 10) || 0;

			$btn.prop('disabled', true).text('Saving\u2026');
			hideBanner();

			var data = {
				option:    'com_bonuses',
				task:      'actions.setauthor',
				bid:       bid,
				author_id: authorId
			};
			data[CSRF_TOKEN] = 1;

			$.ajax({
				url:      'index.php',
				type:     'POST',
				dataType: 'json',
				data:     data,
				success: function (res) {
					if (res && res.ok) {
						$cell.data('initial', String(authorId));
						$btn.hide().prop('disabled', false).text('Save');
						showBanner(true,
							'Author updated for bonus #' + bid +
							(res.author_name ? ' (' + res.author_name + ')' : '') + '.'
						);
					} else {
						$btn.prop('disabled', false).text('Save');
						showBanner(false,
							(res && res.message) ? res.message : 'Could not update author.'
						);
					}
				},
				error: function (xhr) {
					$btn.prop('disabled', false).text('Save');
					showBanner(false,
						'Server error (' + xhr.status + '). Nothing was saved.'
					);
				}
			});
		});
	});

	/* --------------------------------------------------------------
	 * INLINE MODIFIED_BY CHANGE  (per-row dropdown + Save)
	 *
	 * Same UX as the author cell but POSTs to actions.setmodifiedby.
	 * The controller stamps modified=now alongside writing modified_by,
	 * so the timestamp shown under the dropdown becomes stale until
	 * the page is reloaded. We do NOT live-update the "edited X ago"
	 * subtitle here - same trade-off as the author cell (the banner
	 * is informational, the page refresh shows the new state).
	 * -------------------------------------------------------------- */
	$('.fec-modified-cell').each(function () {
		var $cell    = $(this);
		var $select  = $cell.find('.fec-modified-select');
		var $btn     = $cell.find('.fec-modified-save');

		$select.on('change', function () {
			if (String($select.val()) === String($cell.data('initial'))) {
				$btn.hide();
			} else {
				$btn.show();
			}
		});

		$btn.on('click', function () {
			var bid        = parseInt($cell.data('bid'), 10);
			var modifiedBy = parseInt($select.val(), 10) || 0;

			$btn.prop('disabled', true).text('Saving\u2026');
			hideBanner();

			var data = {
				option:      'com_bonuses',
				task:        'actions.setmodifiedby',
				bid:         bid,
				modified_by: modifiedBy
			};
			data[CSRF_TOKEN] = 1;

			$.ajax({
				url:      'index.php',
				type:     'POST',
				dataType: 'json',
				data:     data,
				success: function (res) {
					if (res && res.ok) {
						$cell.data('initial', String(modifiedBy));
						$btn.hide().prop('disabled', false).text('Save');
						showBanner(true,
							'Last editor updated for bonus #' + bid +
							(res.author_name ? ' (' + res.author_name + ')' : '') + '.'
						);
					} else {
						$btn.prop('disabled', false).text('Save');
						showBanner(false,
							(res && res.message) ? res.message : 'Could not update last editor.'
						);
					}
				},
				error: function (xhr) {
					$btn.prop('disabled', false).text('Save');
					showBanner(false,
						'Server error (' + xhr.status + '). Nothing was saved.'
					);
				}
			});
		});
	});

	/* --------------------------------------------------------------
	 * BULK ACTIONS
	 * -------------------------------------------------------------- */
	var $rowChecks    = $('.fec-row-check');
	var $master       = $('#fec-master-check');
	var $toolbar      = $('#fec-bulk-toolbar');
	var $count        = $('#fec-bulk-count');
	var $clearSelBtn  = $('#fec-bulk-clear');
	var $bulkButtons  = $('.fec-bulk-btn');
	var $bulkAuthor   = $('#fec-bulk-author-select');

	function getSelectedBids() {
		var bids = [];
		$rowChecks.filter(':checked').each(function () {
			var n = parseInt($(this).val(), 10);
			if (n > 0) { bids.push(n); }
		});
		return bids;
	}

	function refreshSelectionUI() {
		var total    = $rowChecks.length;
		var selected = $rowChecks.filter(':checked').length;

		$count.text(selected);

		if (selected === 0) {
			$toolbar.hide();
			$master.prop('checked', false).prop('indeterminate', false);
		} else {
			$toolbar.show();
			if (selected === total) {
				$master.prop('checked', true).prop('indeterminate', false);
			} else {
				$master.prop('checked', false).prop('indeterminate', true);
			}
		}
	}

	$rowChecks.on('change', refreshSelectionUI);

	$master.on('change', function () {
		var checked = $(this).prop('checked');
		$rowChecks.prop('checked', checked);
		refreshSelectionUI();
	});

	$clearSelBtn.on('click', function () {
		$rowChecks.prop('checked', false);
		refreshSelectionUI();
	});

	function setBulkBusy(busy) {
		$bulkButtons.prop('disabled', busy);
		$bulkAuthor.prop('disabled', busy);
		$rowChecks.prop('disabled', busy);
		$master.prop('disabled', busy);
		$clearSelBtn.prop('disabled', busy);
	}

	function runBulk(taskTail, extra, busyText, onSuccess) {
		var bids = getSelectedBids();

		if (bids.length === 0) {
			showBanner(false, 'No bonuses selected.');
			return;
		}

		hideBanner();
		setBulkBusy(true);
		showBanner(true, busyText + '\u2026');
		$banner.removeClass('is-success is-error').addClass('is-busy');

		var data = $.extend({
			option:  'com_bonuses',
			task:    'actions.' + taskTail,
			'bids[]': bids
		}, extra || {});
		data[CSRF_TOKEN] = 1;

		$.ajax({
			url:      'index.php',
			type:     'POST',
			dataType: 'json',
			traditional: true,
			data:     data,
			success: function (res) {
				setBulkBusy(false);
				if (res && res.ok) {
					showBanner(true, (res.message || 'Done.'));
					if (typeof onSuccess === 'function') { onSuccess(res); }
					setTimeout(function () { window.location.reload(); }, 600);
				} else {
					showBanner(false,
						(res && res.message) ? res.message : 'Bulk action failed.'
					);
				}
			},
			error: function (xhr) {
				setBulkBusy(false);
				showBanner(false,
					'Server error (' + xhr.status + '). Nothing was changed.'
				);
			}
		});
	}

	$bulkButtons.on('click', function () {
		var action = $(this).data('bulk-action');
		var bids   = getSelectedBids();
		var n      = bids.length;

		if (n === 0) {
			showBanner(false, 'No bonuses selected.');
			return;
		}

		switch (action) {
			case 'expire':
				if (!window.confirm('Expire ' + n + ' bonus' + (n === 1 ? '' : 'es') + '?')) {
					return;
				}
				runBulk('bulksetstatus', { action: 'expire' }, 'Expiring ' + n);
				break;

			case 'publish':
				if (!window.confirm('Publish ' + n + ' bonus' + (n === 1 ? '' : 'es') + '?')) {
					return;
				}
				runBulk('bulksetstatus', { action: 'publish' }, 'Publishing ' + n);
				break;

			case 'delete':
				if (!window.confirm(
					'Delete ' + n + ' bonus' + (n === 1 ? '' : 'es') +
					'? This cannot be undone.'
				)) {
					return;
				}
				runBulk('bulkdelete', {}, 'Deleting ' + n);
				break;

			case 'setauthor':
				var authorId = parseInt($bulkAuthor.val(), 10) || 0;
				runBulk(
					'bulksetauthor',
					{ author_id: authorId },
					'Reassigning ' + n
				);
				break;
		}
	});

	refreshSelectionUI();
});
</script>