<?php
/**
 * com_partnerdesk - single submission template
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

$item    = $this->item;
$payload = $item->payload_data;

$get = function ($key) use ($payload) {
	return (isset($payload[$key]) && trim($payload[$key]) !== '')
		? htmlspecialchars($payload[$key], ENT_QUOTES, 'UTF-8')
		: '<em style="color:#999;">pending</em>';
};

$isUrl = function ($v) {
	return (bool) preg_match('#^https?://#i', trim($v));
};

switch ((int) $item->status)
{
	case 1:
		$statusHtml = '<span class="label label-success">Approved</span>';
		break;
	case 2:
		$statusHtml = '<span class="label label-important">Rejected</span>';
		break;
	default:
		$statusHtml = '<span class="label label-warning">Pending</span>';
}
?>
<form action="<?php echo JRoute::_('index.php?option=com_partnerdesk&view=submission&id=' . (int) $item->id); ?>"
	method="post" name="adminForm" id="adminForm">

	<div style="margin-bottom: 15px; padding: 12px; background: #f5f5f5; border-radius: 6px;">
		<strong>Status:</strong> <?php echo $statusHtml; ?>
		&nbsp;|&nbsp; <strong>Submitted:</strong> <?php echo JHtml::_('date', $item->created, 'Y-m-d H:i'); ?> UTC
		<?php if ($item->reviewed) : ?>
			&nbsp;|&nbsp; <strong>Reviewed:</strong> <?php echo JHtml::_('date', $item->reviewed, 'Y-m-d H:i'); ?> UTC
		<?php endif; ?>
		&nbsp;|&nbsp; <strong>IP:</strong> <?php echo $this->escape($item->ip); ?>
		<br />
		<small style="color: #777;"><strong>UA:</strong> <?php echo $this->escape($item->user_agent); ?></small>
	</div>

	<div style="margin-bottom: 20px;">
		<button type="submit" class="btn btn-success" name="setstatus" value="approve">Approve</button>
		<button type="submit" class="btn btn-danger" name="setstatus" value="reject">Reject</button>
		<button type="submit" class="btn" name="setstatus" value="pending">Back to pending</button>
		<span style="margin-left: 10px; color: #777;">Decision buttons also save the internal notes below.</span>
	</div>

	<div class="row-fluid">
		<div class="span8">
			<?php foreach ($this->sections as $section) : ?>
				<h3 style="border-bottom: 1px solid #ddd; padding-bottom: 4px;"><?php echo $section['title']; ?></h3>
				<table class="table table-condensed">
					<tbody>
					<?php foreach ($section['fields'] as $field) : ?>
						<tr>
							<td width="38%"><strong><?php echo $field['label']; ?></strong></td>
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

		<div class="span4">
			<h3>Internal notes (private)</h3>
			<textarea name="admin_notes" rows="14" style="width: 100%; box-sizing: border-box;"><?php echo $this->escape($item->admin_notes); ?></textarea>
			<p style="color: #777; font-size: 0.9em; margin-top: 6px;">
				Editorial desk only. FXShield metrics, verification results and decision rationale go here,
				never into publishable fields. Use Export to generate the single-source fact-sheet draft
				for the verification workflow.
			</p>
		</div>
	</div>

	<input type="hidden" name="option" value="com_partnerdesk" />
	<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
	<input type="hidden" name="task" value="submission.save" />
	<?php echo JHtml::_('form.token'); ?>
</form>
