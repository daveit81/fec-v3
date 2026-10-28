<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
 
// import Joomla controller library
jimport('joomla.application.component.controller');
error_reporting(E_ALL);
ini_set('display_errors', 0);





class JackpotsControllerValues extends JController
{

public function values()
  {

$profile = new stdClass();
$profile->name = 'test';
$profile->jid = 'test';
$profile->soft = $soft;
 
// Insert the object into the user profile table.
//$result = JFactory::getDbo()->insertObject('#__jackpots', $profile);

$option = array();
$option['driver']   = 'mysqli';
$option['host']     = 'localhost';
$option['user']     = 'freeextr_dfiocch';
$option['password'] = 'semovente170381.';
$option['database'] = 'freeextr_jptest';
$option['prefix']   = '';

//UNCOMMENT THE FOLLOWING LINE TO VIEW OPTIONS ARRAY CONTENTS         
//print_r($option);

$exdb = JDatabase::getInstance( $option ); 
//echo '<pre style="margin-top:200px;">';
//var_dump($exdb);
//echo '</pre>';
$db = JFactory::getDBO();
$qt = $db->getQuery(true);
$qt->select('jid');
$qt->from('jos_jackpots');
$qt->where('jid !=""');
$db->setQuery($qt);
$trows = $db->loadObjectList();

$i = 0;

foreach($trows as $table) {
$i++;

$union1[] = '(SELECT gameId,amount,update_datetime FROM '.$table->jid.' WHERE DATE_SUB( CURRENT_TIMESTAMP( ) , INTERVAL 3 DAY ) <= update_datetime ORDER BY update_datetime ASC)';

}

$query1 = $exdb->getQuery(true);
$query1 = $query1->union($union1);


$rows = $exdb->setQuery($query1)->loadObjectList();



foreach($rows as $row) {

$getpref = explode("_", $row->gameId, 2);
$pref = $getpref[0];

$values[] = array (
'jid' => $row->gameId,
'amount' => $row->amount,
//'hitdate' => date("j M Y H:i:s", strtotime($row->hit_date)),
'update' => strtotime($row->update_datetime)*1000,
);
}

$grouped = array();
foreach ($values as $item) {
    if (!isset($grouped[$item['jid']])) {
        $grouped[$item['jid']] = $item;
    }
    
    $grouped[$item['jid']]['values'][] = 
    array('x'=>$item['update'],
    'y'=>$item['amount'],);
    unset($grouped[$item['jid']]['amount']);
    unset($grouped[$item['jid']]['update']);
    unset($grouped[$item['jid']]['atmavg']);
    unset($grouped[$item['jid']]['tatmavg']);
    unset($grouped[$item['jid']]['jid']);
    unset($grouped[$item['jid']]['monthnumber']);
    unset($grouped[$item['jid']]['dayofweek']);
    unset($grouped[$item['jid']]['dayhour']);
}


foreach ($grouped as $key => $value) {
$myFile = "components/com_jackpots/data/json/values/$key.json";
$fh = fopen($myFile, 'w') or die("can't open file");
fwrite($fh, json_encode($grouped[$key],JSON_NUMERIC_CHECK));
fclose($fh);
}

echo '<pre style="margin-top:200px;">';
//print_r($union1);
print_r($grouped['0_megamoolahmega']);
//print_r($dayarr['0_megamoolahmega']);
//print_r($resultday['0_megamoolahmega']);
//print_r($heat);
echo '</pre>';
}
}
?>
