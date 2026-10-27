<?php
//define constant
defined('_JEXEC') or die('Restricted access'); 

$optionfilter .= (isset($filter) && $filter == 'exclusive' ? '' : '<option value="exclusive">Exclusive'.$filters_count['exclusive'].'</option>');
$optionfilter .= (isset($filter) && $filter == 'code' ? '' : '<option value="code">Bonus Code '.$filters_count['exclusive'].'</option>');
$optionfilter .= (isset($filter) && $filter == 'software' ? '' : '<option value="software">Software</option>');
$optionfilter .= (isset($filter) && $filter == 'wr' ? '' : '<option value="wr">Wager</option>');
$optionfilter .= (isset($filter) && $filter == 'cashable' ? '' : '<option value="cashable">Cashable</option>');
$optionfilter .= (isset($filter) && $filter == 'max-bonus') || (isset($filter) && empty($countvalues['maxbonus']))  ? '' : '<option value="max-bonus">Max Bonus</option>';
$optionfilter .= (isset($filter) && $filter == 'minimum-deposit') || (isset($filter) && empty($countvalues['mindep'])) ? '' : '<option value="minimum-deposit">Minimum Deposit</option>';
$optionfilter .= (isset($filter) && $filter == 'minimum-cashout') || (isset($filter) && empty($countvalues['mincash'])) ? '' : '<option value="minimum-cashout">Minimum Cashout</option>';
$optionfilter .= (isset($filter) && $filter == 'maximum-cashout') || (isset($filter) && empty($countvalues['maxcash']))  ? '' : '<option value="maximum-cashout">Maximum Cashout</option>';
$optionfilter .= (isset($filter) ? '' : '<option value="allowed-countries">Allowed Countries</option>');
$optionfilter .= (isset($filter) ? '' : '<option value="restricted-countries">Restricted Countries</option>');
$optionfilter .= (isset($filter) ? '' : '<option value="currency">Currency</option>');

$type = JRequest::getVar('type');
$filter = JRequest::getVar('filter');
$refine = JRequest::getVar('refine');
$fq = JRequest::getVar('fq');
$meth = JRequest::getVar('meth');
$curr = JRequest::getVar('curr');
$page = JRequest::getVar('page');


if(($filter == 'currency') && ($fq == 'btc') ){
    $filtertrigger = '';
} else {

$filtertrigger = '
<div class="cd-filter">
			<form id="filters">
		          <div class="cd-filter-block">
				<h4>'.(isset($filter) ? 'Refine' : 'Filter').' By</h4>
				<div class="cd-filter-content">
					<div class="cd-select cd-filters">
					<label for="filter-trig" class="sr-only">Filter</label>
			<select id="filter-trig" name="filter" data-type="'.$type.'" data-trigger="'.(isset($filter) ? 'refine' : 'filter').'" data-query="'.(isset($fq) ? $fq.''.$meth.''.$curr : '').'"  data-filter="'.(isset($filter) ? $filter : '').'" data-page="'.(isset($page) ? 'true' : '').'">
								<option value="">Choose an option</option>
									'.$optionfilter.'						
							</select>
						</div>
					</div>
				</div>
				
				<div class="cd-filter-block" id="step2">
				</div>
				
				<div class="cd-filter-block" id="go-filter">
				</div>
			</form>

			<span class="cd-close">X Close</span>
		</div> <!-- cd-filter -->
<span class="cd-filter-trigger"><i class="fa fa-sliders"></i> '.(isset($filter) ? 'Refine' : 'Filters').'</span>';
}

$subfilters = '<div class="subfilters-wrapper">
<div class="subfilters">
  <a class="fbtn '.(isset($subtype) ? '' : 'active').'" href="'.JRoute::_('index.php?option=com_bonuses&view=show&type=fs').'">All</a>
  <a class="fbtn '.(isset($subtype) && $subtype == 'nd' ? 'active' : '').'" href="'.JRoute::_('index.php?option=com_bonuses&view=show&type=fs&subtype=nd').'">No Deposit</a>
  <a class="fbtn '.(isset($subtype) && $subtype == 'dr' ? 'active' : '').'" href="'.JRoute::_('index.php?option=com_bonuses&view=show&type=fs&subtype=dr').'">Dep. Required</a>
</div>

</div>
';
?>