<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
$userid = JFactory::getUser()->id;
$authuser = '42';
?>

<?php if($userid != 0 && $authuser == $userid):?>


<div class="form-group casino ">
        <label class="col-xs-3 control-label">Casino</label>
        <div class="col-xs-5 selectContainer">
            <select name="casino" class="form-control">
                <option value="">Select Casino</option>
                <option value="<?php echo JRoute::_('index.php?option=com_bonuses&view=edit');?>">All Casinos</option>
                <?php echo $this->option;?>
            </select>
        </div>
</div>

<div class="form-group status">
        <label class="col-xs-3 control-label">Casino</label>
        <div class="col-xs-5 selectContainer">
            <select name="status" class="form-control">
                <option value="">Select Status</option>
                <option value="<?php echo JRoute::_('index.php?option=com_bonuses&view=edit');?>">All Statuses</option>
                <option value="<?php echo JRoute::_('index.php?option=com_bonuses&view=edit&status=active');?>">Active</option>
                <option value="<?php echo JRoute::_('index.php?option=com_bonuses&view=edit&status=expired');?>">Expired</option>
                <option value="<?php echo JRoute::_('index.php?option=com_bonuses&view=edit&status=pending');?>">Pending</option>
            </select>
        </div>
</div>


<div class="container">           
  <table class="table">
    <thead>
      <tr>
        <th>Bonus ID</th>
        <th>Casino ID</th>
        <th>Casino Name</th>
        <th>Bonus type</th>
        <th>Bonus Label</th>
        <th>Bonus Value</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php echo $this->outputedit;?>
    </tbody>
  </table>
<?php echo $this->paginate->render();?>
<?php endif;?>
<script>
jQuery(document).ready(function() {

$('select[name="casino"],select[name="status"]').change(function() {
    window.location.replace($(this).val());
});          
});
</script>