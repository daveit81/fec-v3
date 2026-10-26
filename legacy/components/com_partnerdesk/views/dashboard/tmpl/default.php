<?php
/**
 * com_partnerdesk - frontend dashboard template
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

$state    = $this->state;
$deskUrl  = JRoute::_('index.php?option=com_partnerdesk&view=dashboard');
$token    = JHtml::_('form.token');

$badge = function ($status) {
	switch ((int) $status)
	{
		case 1:
			return '<span class="fec-badge fec-badge-ok">Approved</span>';
		case 2:
			return '<span class="fec-badge fec-badge-no">Rejected</span>';
		default:
			return '<span class="fec-badge fec-badge-wait">Pending</span>';
	}
};

$host = function ($url) {
	$h = parse_url($url, PHP_URL_HOST);
	return $h ? preg_replace('/^www\./', '', $h) : $url;
};

// FEC pastello stroke icons.
$icoDesk   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';
$icoSearch = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>';
$icoOk     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>';
$icoNo     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/></svg>';
$icoEye    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
?>
<style>
.fecx .fec-desk { max-width: 1100px; margin: 0 auto; }
.fecx .fec-desk h1 { display: flex; align-items: center; gap: 10px; }
.fecx .fec-desk-pills { margin: 10px 0 18px; display: flex; gap: 8px; flex-wrap: wrap; }
.fecx .fec-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.82em; font-weight: 600; white-space: nowrap; }
.fecx .fec-badge-wait { background: #fdf3d7; color: #8a6d1a; }
.fecx .fec-badge-ok { background: #ddefe4; color: #2f6f4f; }
.fecx .fec-badge-no { background: #f7dcd9; color: #a33c30; }
.fecx .fec-desk-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
.fecx .fec-desk-filters input[type=text], .fecx .fec-desk-filters select {
	padding: 7px 10px; border: 1px solid #ccc; border-radius: 6px; font: inherit; background: #fff;
}
.fecx .fec-desk-filters input[type=text] { min-width: 260px; }
.fecx .fec-btn {
	display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 1px solid #ccc;
	border-radius: 6px; background: #f7f7f7; color: #333; font: inherit; font-weight: 600; cursor: pointer;
	text-decoration: none;
}
.fecx .fec-btn:hover { background: #eee; }
.fecx .fec-btn-ok { background: #ddefe4; border-color: #b9dcc8; color: #2f6f4f; }
.fecx .fec-btn-ok:hover { background: #cde6d7; }
.fecx .fec-btn-no { background: #f7dcd9; border-color: #eabfba; color: #a33c30; }
.fecx .fec-btn-no:hover { background: #f2ccc7; }
.fecx .fec-btn-sm { padding: 4px 8px; }
.fecx .fec-desk-tablewrap { overflow-x: auto; }
.fecx .fec-desk table { width: 100%; border-collapse: collapse; font-size: 0.94em; }
.fecx .fec-desk th, .fecx .fec-desk td { padding: 9px 10px; border-bottom: 1px solid #e7e7e7; text-align: left; vertical-align: top; }
.fecx .fec-desk th { background: #f7f7f7; font-weight: 600; white-space: nowrap; }
.fecx .fec-desk td small { color: #777; }
.fecx .fec-desk-actions { display: flex; gap: 5px; }
.fecx .fec-desk-actions form { margin: 0; display: inline; }
.fecx .fec-desk-empty { padding: 30px; text-align: center; color: #777; }
.fecx .fec-desk-pagination { margin-top: 14px; text-align: center; }
</style>

<div class="fecx">
	<div class="fec-desk">

		<h1><?php echo $icoDesk; ?> Partner Desk</h1>

		<div class="fec-desk-pills">
			<span class="fec-badge fec-badge-wait">Pending: <?php echo (int) $this->counts[0]; ?></span>
			<span class="fec-badge fec-badge-ok">Approved: <?php echo (int) $this->counts[1]; ?></span>
			<span class="fec-badge fec-badge-no">Rejected: <?php echo (int) $this->counts[2]; ?></span>
		</div>

		<form action="<?php echo $deskUrl; ?>" method="post" class="fec-desk-filters">
			<input type="text" name="filter_search" placeholder="Brand, AM, email, program, URL"
				value="<?php echo $this->escape($state->get('filter.search')); ?>" />
			<select name="filter_status" onchange="this.form.submit()">
				<option value=""<?php echo ($state->get('filter.status') === '' ? ' selected' : ''); ?>>All statuses</option>
				<option value="0"<?php echo ($state->get('filter.status') === '0' ? ' selected' : ''); ?>>Pending</option>
				<option value="1"<?php echo ($state->get('filter.status') === '1' ? ' selected' : ''); ?>>Approved</option>
				<option value="2"<?php echo ($state->get('filter.status') === '2' ? ' selected' : ''); ?>>Rejected</option>
			</select>
			<button type="submit" class="fec-btn"><?php echo $icoSearch; ?> Search</button>
			<input type="hidden" name="limitstart" value="0" />
		</form>

		<div class="fec-desk-tablewrap">
			<table>
				<thead>
					<tr>
						<th>#</th>
						<th>Brand</th>
						<th>Affiliate program</th>
						<th>AM</th>
						<th>Submitted</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($this->items)) : ?>
					<tr><td colspan="7" class="fec-desk-empty">No submissions found.</td></tr>
				<?php else : ?>
					<?php foreach ($this->items as $item) : ?>
						<?php $reviewUrl = JRoute::_('index.php?option=com_partnerdesk&view=review&id=' . (int) $item->id); ?>
						<tr>
							<td><?php echo (int) $item->id; ?></td>
							<td>
								<a href="<?php echo $reviewUrl; ?>"><strong><?php echo $this->escape($item->brand_name); ?></strong></a><br />
								<small><?php echo $this->escape($host($item->casino_url)); ?></small>
							</td>
							<td>
								<?php echo $this->escape($item->aff_program_name); ?><br />
								<small><?php echo $this->escape($host($item->aff_program_url)); ?></small>
							</td>
							<td>
								<?php echo $this->escape($item->am_name); ?><br />
								<small><?php echo $this->escape($item->am_email); ?></small>
							</td>
							<td><?php echo JHtml::_('date', $item->created, 'Y-m-d H:i'); ?></td>
							<td><?php echo $badge($item->status); ?></td>
							<td>
								<div class="fec-desk-actions">
									<a href="<?php echo $reviewUrl; ?>" class="fec-btn fec-btn-sm" title="Review"><?php echo $icoEye; ?></a>
									<?php if ((int) $item->status !== 1) : ?>
										<form action="<?php echo $deskUrl; ?>" method="post">
											<input type="hidden" name="option" value="com_partnerdesk" />
											<input type="hidden" name="task" value="approve" />
											<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
											<?php echo $token; ?>
											<button type="submit" class="fec-btn fec-btn-sm fec-btn-ok" title="Approve"><?php echo $icoOk; ?></button>
										</form>
									<?php endif; ?>
									<?php if ((int) $item->status !== 2) : ?>
										<form action="<?php echo $deskUrl; ?>" method="post">
											<input type="hidden" name="option" value="com_partnerdesk" />
											<input type="hidden" name="task" value="reject" />
											<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
											<?php echo $token; ?>
											<button type="submit" class="fec-btn fec-btn-sm fec-btn-no" title="Reject"><?php echo $icoNo; ?></button>
										</form>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="fec-desk-pagination">
			<?php echo $this->pagination->getPagesLinks(); ?>
		</div>

	</div>
</div>
