<?php
/**
 * com_partnerdesk - submissions list template
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

JHtml::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

$badge = function ($status) {
	switch ((int) $status)
	{
		case 1:
			return '<span class="label label-success">Approved</span>';
		case 2:
			return '<span class="label label-important">Rejected</span>';
		default:
			return '<span class="label label-warning">Pending</span>';
	}
};

$host = function ($url) {
	$h = parse_url($url, PHP_URL_HOST);
	return $h ? $h : $url;
};
?>
<form action="<?php echo JRoute::_('index.php?option=com_partnerdesk&view=submissions'); ?>" method="post" name="adminForm" id="adminForm">

	<div style="margin: 0 0 12px;">
		<span class="label label-warning">Pending: <?php echo (int) $this->counts[0]; ?></span>
		<span class="label label-success">Approved: <?php echo (int) $this->counts[1]; ?></span>
		<span class="label label-important">Rejected: <?php echo (int) $this->counts[2]; ?></span>
	</div>

	<fieldset id="filter-bar" style="margin-bottom: 10px;">
		<div class="filter-search" style="float: left;">
			<input type="text" name="filter_search" id="filter_search"
				placeholder="Brand, AM, email, program, URL"
				value="<?php echo $this->escape($this->state->get('filter.search')); ?>" />
			<button type="submit" class="btn">Search</button>
			<button type="button" class="btn"
				onclick="document.getElementById('filter_search').value='';this.form.submit();">Clear</button>
		</div>
		<div class="filter-select" style="float: right;">
			<select name="filter_status" onchange="this.form.submit()">
				<option value=""<?php echo ($this->state->get('filter.status') === '' ? ' selected' : ''); ?>>- All statuses -</option>
				<option value="0"<?php echo ($this->state->get('filter.status') === '0' ? ' selected' : ''); ?>>Pending</option>
				<option value="1"<?php echo ($this->state->get('filter.status') === '1' ? ' selected' : ''); ?>>Approved</option>
				<option value="2"<?php echo ($this->state->get('filter.status') === '2' ? ' selected' : ''); ?>>Rejected</option>
			</select>
		</div>
		<div style="clear: both;"></div>
	</fieldset>

	<table class="table table-striped adminlist">
		<thead>
			<tr>
				<th width="20"><input type="checkbox" name="checkall-toggle" value=""
					onclick="Joomla.checkAll(this)" /></th>
				<th width="40"><?php echo JHtml::_('grid.sort', 'ID', 'a.id', $listDirn, $listOrder); ?></th>
				<th><?php echo JHtml::_('grid.sort', 'Brand', 'a.brand_name', $listDirn, $listOrder); ?></th>
				<th>Casino URL</th>
				<th>Affiliate program</th>
				<th><?php echo JHtml::_('grid.sort', 'AM', 'a.am_name', $listDirn, $listOrder); ?></th>
				<th width="130"><?php echo JHtml::_('grid.sort', 'Submitted', 'a.created', $listDirn, $listOrder); ?></th>
				<th width="90"><?php echo JHtml::_('grid.sort', 'Status', 'a.status', $listDirn, $listOrder); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($this->items)) : ?>
			<tr><td colspan="8" align="center">No submissions found.</td></tr>
		<?php else : ?>
			<?php foreach ($this->items as $i => $item) : ?>
				<tr class="row<?php echo $i % 2; ?>">
					<td><?php echo JHtml::_('grid.id', $i, $item->id); ?></td>
					<td><?php echo (int) $item->id; ?></td>
					<td>
						<a href="<?php echo JRoute::_('index.php?option=com_partnerdesk&view=submission&id=' . (int) $item->id); ?>">
							<strong><?php echo $this->escape($item->brand_name); ?></strong>
						</a>
					</td>
					<td>
						<a href="<?php echo $this->escape($item->casino_url); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo $this->escape($host($item->casino_url)); ?>
						</a>
					</td>
					<td>
						<?php echo $this->escape($item->aff_program_name); ?><br />
						<a href="<?php echo $this->escape($item->aff_program_url); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo $this->escape($host($item->aff_program_url)); ?>
						</a>
					</td>
					<td>
						<?php echo $this->escape($item->am_name); ?><br />
						<small><?php echo $this->escape($item->am_email); ?></small>
					</td>
					<td><?php echo JHtml::_('date', $item->created, 'Y-m-d H:i'); ?></td>
					<td><?php echo $badge($item->status); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="8"><?php echo $this->pagination->getListFooter(); ?></td>
			</tr>
		</tfoot>
	</table>

	<input type="hidden" name="task" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
	<?php echo JHtml::_('form.token'); ?>
</form>
