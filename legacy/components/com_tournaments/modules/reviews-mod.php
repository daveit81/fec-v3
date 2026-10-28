<?php

defined('_JEXEC') or die('Restricted access');

require_once( JPATH_BASE .DS.'components'.DS.'com_tournaments'.DS.'helpers'.DS.'modules-render.php' );


;?>
<?php if(!empty($tournaments)):?>
<h3>Current Tournaments</h3>
<table id="table" class="table table-bordered tournaments">
      <thead>
        <tr>
          <th><span class="main">Tournament</span><span class="sec"><i class="fa fa-long-arrow-right" aria-hidden="true"></i> Game</span></th>
          <th>Entry fee</th>
          <th>Prize Pool</th>
          <th>Start</th>
          <th>End</th>
        </tr>
      </thead>
      <tbody>
        <?php echo $layout;?>
      </tbody>
    </table>
<div class="show-all">
<a href="https://www.freeextrachips.com/tournaments" class="show-bonuses">All Tournaments <i class="fa fa-angle-double-right"></i></a>
</div>

<?php endif;?>