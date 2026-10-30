<?php
/**
 * com_partnerdesk - frontend review template
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

$item     = $this->item;
$payload  = $item->payload_data;
$deskUrl  = JRoute::_('index.php?option=com_partnerdesk&view=dashboard');
$selfUrl  = JRoute::_('index.php?option=com_partnerdesk&view=review&id=' . (int) $item->id);
$token    = JHtml::_('form.token');

$get = function ($key) use ($payload) {
	return (isset($payload[$key]) && trim($payload[$key]) !== '')
		? htmlspecialchars($payload[$key], ENT_QUOTES, 'UTF-8')
		: '<em class="fec-pending">pending</em>';
};

$isUrl = function ($v) {
	return (bool) preg_match('#^https?://#i', trim($v));
};

switch ((int) $item->status)
{
	case 1:
		$statusHtml = '<span class="fec-badge fec-badge-ok">Approved</span>';
		break;
	case 2:
		$statusHtml = '<span class="fec-badge fec-badge-no">Rejected</span>';
		break;
	default:
		$statusHtml = '<span class="fec-badge fec-badge-wait">Pending</span>';
}

// FEC pastello stroke icons.
$icoBack = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>';
$icoOk   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>';
$icoNo   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/></svg>';
$icoWait = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';
$icoDl   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>';
?>
<style>
.fecx .fec-review { max-width: 1100px; margin: 0 auto; }
.fecx .fec-review h1 { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.fecx .fec-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.6em; font-weight: 600; vertical-align: middle; white-space: nowrap; }
.fecx .fec-badge-wait { background: #fdf3d7; color: #8a6d1a; }
.fecx .fec-badge-ok { background: #ddefe4; color: #2f6f4f; }
.fecx .fec-badge-no { background: #f7dcd9; color: #a33c30; }
.fecx .fec-review-meta { background: #f7f7f7; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 0.92em; color: #444; }
.fecx .fec-review-meta small { color: #888; }
.fecx .fec-btn {
	display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #ccc;
	border-radius: 6px; background: #f7f7f7; color: #333; font: inherit; font-weight: 600; cursor: pointer;
	text-decoration: none;
}
.fecx .fec-btn:hover { background: #eee; }
.fecx .fec-btn-ok { background: #ddefe4; border-color: #b9dcc8; color: #2f6f4f; }
.fecx .fec-btn-ok:hover { background: #cde6d7; }
.fecx .fec-btn-no { background: #f7dcd9; border-color: #eabfba; color: #a33c30; }
.fecx .fec-btn-no:hover { background: #f2ccc7; }
.fecx .fec-review-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
.fecx .fec-review-bar form { margin: 0; display: inline; }
.fecx .fec-review-cols { display: flex; gap: 24px; flex-wrap: wrap; }
.fecx .fec-review-sheet { flex: 2 1 560px; min-width: 0; }
.fecx .fec-review-side { flex: 1 1 300px; min-width: 280px; }
.fecx .fec-review-sheet h3 { border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-top: 22px; }
.fecx .fec-review-sheet table { width: 100%; border-collapse: collapse; font-size: 0.94em; }
.fecx .fec-review-sheet td { padding: 7px 8px; border-bottom: 1px solid #efefef; vertical-align: top; word-break: break-word; }
.fecx .fec-review-sheet td:first-child { width: 38%; font-weight: 600; }
.fecx .fec-pending { color: #999; }
.fecx .fec-review-side textarea {
	width: 100%; box-sizing: border-box; min-height: 260px; padding: 10px; border: 1px solid #ccc;
	border-radius: 6px; font: inherit; resize: vertical;
}
.fecx .fec-review-side .fec-side-hint { color: #777; font-size: 0.88em; margin-top: 6px; }
</style>

<div class="fecx">
	<div class="fec-review">

		<p><a href="<?php echo $deskUrl; ?>" class="fec-btn"><?php echo $icoBack; ?> Back to desk</a></p>

		<h1>#<?php echo (int) $item->id; ?> <?php echo $this->escape($item->brand_name); ?> <?php echo $statusHtml; ?></h1>

		<div class="fec-review-meta">
			<strong>Submitted:</strong> <?php echo JHtml::_('date', $item->created, 'Y-m-d H:i'); ?> UTC
			<?php if ($item->reviewed) : ?>
				&nbsp;|&nbsp; <strong>Reviewed:</strong> <?php echo JHtml::_('date', $item->reviewed, 'Y-m-d H:i'); ?> UTC
			<?php endif; ?>
			&nbsp;|&nbsp; <strong>IP:</strong> <?php echo $this->escape($item->ip); ?>
			<br />
			<small><strong>UA:</strong> <?php echo $this->escape($item->user_agent); ?></small>
		</div>

		<div class="fec-review-bar">
			<form action="<?php echo $selfUrl; ?>" method="post">
				<input type="hidden" name="option" value="com_partnerdesk" />
				<input type="hidden" name="task" value="exportmd" />
				<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
				<?php echo $token; ?>
				<button type="submit" class="fec-btn"><?php echo $icoDl; ?> Export .md draft</button>
			</form>
		</div>

		<form action="<?php echo $selfUrl; ?>" method="post">
			<div class="fec-review-cols">

				<div class="fec-review-sheet">
					<?php foreach ($this->sections as $section) : ?>
						<h3><?php echo $section['title']; ?></h3>
						<table>
							<tbody>
							<?php foreach ($section['fields'] as $field) : ?>
								<tr>
									<td><?php echo $field['label']; ?></td>
									<td>
										<?php
										$raw = isset($payload[$field['key']]) ? trim($payload[$field['key']]) : '';

										if ($raw !== '' && $isUrl($raw))
										{
											echo '<a href="' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8')
												. '" target="_blank" rel="noopener noreferrer">'
												. htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '</a>';
										}
										else
										{
											echo nl2br($get($field['key']));
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
				</div>

				<div class="fec-review-side">
					<h3>Decision</h3>
					<p>
						<button type="submit" class="fec-btn fec-btn-ok" name="setstatus" value="approve"><?php echo $icoOk; ?> Approve</button>
						<button type="submit" class="fec-btn fec-btn-no" name="setstatus" value="reject"><?php echo $icoNo; ?> Reject</button>
						<button type="submit" class="fec-btn" name="setstatus" value="pending"><?php echo $icoWait; ?> Pending</button>
					</p>
					<p class="fec-side-hint">Decision buttons also save the notes below. Save before exporting if you changed the notes.</p>

					<h3>Internal notes (private)</h3>
					<textarea name="admin_notes"><?php echo $this->escape($item->admin_notes); ?></textarea>
					<p class="fec-side-hint">
						Editorial desk only. FXShield metrics, verification results and decision rationale go here,
						never into publishable fields. Export generates the single-source fact-sheet draft for the
						verification workflow.
					</p>
					<p>
						<button type="submit" class="fec-btn">Save notes</button>
					</p>
				</div>

			</div>

			<input type="hidden" name="option" value="com_partnerdesk" />
			<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
			<input type="hidden" name="task" value="savenotes" />
			<?php echo $token; ?>
		</form>

	</div>
</div>
