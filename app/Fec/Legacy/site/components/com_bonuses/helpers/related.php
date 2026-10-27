<?php
//define constant
define( '_JEXEC', 1 );

define( 'DS', DIRECTORY_SEPARATOR );
 
//you need to adjust joomla path according to your joomla installation
define( 'JPATH_BASE', $_SERVER[ 'DOCUMENT_ROOT' ] . DS . '' );
 
//include joomla core files
require_once( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
require_once( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );
require_once( JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'factory.php' );


//create application
$mainframe =& JFactory::getApplication('site');
$cid = $_GET['cid'];

$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);


$query
    ->select('*')
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('cid') . ' = '. $db->quote($cid))
    ->where($db->quoteName('status') . ' NOT LIKE '. $db->quote('expired'));
    
// Reset the query using our newly populated query object.
$db->setQuery($query);
 
// Load the results as a list of stdClass objects (see later for more options on retrieving data).
$results = $db->loadAssocList();

?>
<?php if(!empty($results)):?>
<label class="col-xs-3 control-label">Is "sub-bonus" of</label>
        <div class="col-xs-5 selectContainer">
            <select name="related" class="form-control">
                <option value=""></option>
                
                <?php foreach($results as $set) {
                
                if($set['currency'] !='') {
                
                $currency = $set['currency'];
                
                } else {
                
                $currency = '$/€/£';
                
                }
                
                
                
                if($set['type'] == 'nd') {
               
                    $option = '<option value="'.$set['bid'].'">'.$set['brand'].' - '.$currency.''.$set['bonus'].' '.$set['label'].'</option>';

              
                } elseif($set['type'] == 'fs') {
               
                    $option = '<option value="'.$set['bid'].'">'.$set['brand'].' - '.$set['bonus'].' '.$set['label'].' on '.$set['game'].'</option>';

                 } elseif($set['type'] == 'fp') {
               
                    $option = '<option value="'.$set['bid'].'">'.$set['brand'].' - '.$currency.''.$set['bonus'].' '.$set['fp_time'].' minutes '.$set['label'].'</option>';
             
              
                } else {
                
                
                
                $option = '<option value="'.$set['bid'].'">'.$set['brand'].' - '.$set['bonus'].'% '.$set['label'].' up to '.$currency.''.$set['max_bonus'].'</option>';
                
                }
                
                
                echo $option;
                
                }
                ?>              
                
            </select>
        </div>
<?php else:?>
<?php endif;?>