<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
 
// import Joomla controller library
jimport('joomla.application.component.controller');
error_reporting(E_ALL);
ini_set('display_errors', 0);





class JackpotsControllerHits extends JController
{

public function hits()
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
$option['password'] = '<REDACTED:password>';
$option['database'] = 'freeextr_jptest';
$option['prefix']   = '';

//UNCOMMENT THE FOLLOWING LINE TO VIEW OPTIONS ARRAY CONTENTS         
//print_r($option);

$exdb = JDatabase::getInstance( $option ); 
//echo '<pre style="margin-top:200px;">';
//var_dump($exdb);
//echo '</pre>';

$qt = $exdb->getQuery(true);
$qt->select('TABLE_NAME');
$qt->from('information_schema.TABLES');
$qt->where('TABLE_SCHEMA = "freeextr_jptest"');
$exdb->setQuery($qt);
$trows = $exdb->loadObjectList();

$i = 0;
foreach($trows as $table) {
$i++;
$union1[] = '(select t.gameId,t.gameName,t.hit_amount,t.hit_date, (atmavg / cnt) as atmavg, (tatmavg / cntt) as tatmavg from (select t.*, (@rn'.$i.' := @rn'.$i.' + 1) as cnt, (@rnt'.$i.' := @rnt'.$i.' + 1) as cntt, (@s'.$i.' := @s'.$i.' + hit_amount) as atmavg, (SELECT TIMESTAMPDIFF(HOUR, MIN(hit_date), MAX(hit_date)) from '.$table->TABLE_NAME.' WHERE hit_date IS NOT NULL) as tatmavg from '.$table->TABLE_NAME.' t cross join (select @rn'.$i.' := 0, @s'.$i.' := 0,@rnt'.$i.' := -1, @st'.$i.' := 0) params WHERE hit = 1) t ORDER BY hit_date DESC LIMIT 20)';

}

$query1 = $exdb->getQuery(true);
$query1 = $query1->union($union1);


$rows = $exdb->setQuery($query1)->loadObjectList();



foreach($rows as $row) {

$getpref = explode("_", $row->gameId, 2);
$pref = $getpref[0];
$gameid[] = $row->gameId;

$dayofweek = (date('N', strtotime($row->hit_date))-1);
$dayname = date('l', strtotime($row->hit_date));
$dayhour = (date('G', strtotime($row->hit_date)));
$monthnumber = (date('n', strtotime($row->hit_date))-1);


$hits[] = array (
'jid' => $row->gameId,
'hitamount' => $row->hit_amount,
'hitdate' => date("j M Y H:i:s", strtotime($row->hit_date)),
'atmavg' => $row->atmavg,
'tatmavg' => $row->tatmavg,
'monthnumber'=>$monthnumber,
'dayofweek' => $dayofweek,
'dayhour' => $dayhour,
);
}


foreach($hits as $v){
$montharr[$v['jid']][$v['monthnumber']][] = $v['dayofweek'];
$dayarr[$v['jid']][$v['dayhour']][] = $v['dayofweek'];
}

foreach(array_unique($gameid) as $id){
    
foreach($montharr[$id] as $k => $v) {
    $result[$id][$k] = array_count_values($v);
    arsort($result[$k]);
  }
  
foreach($dayarr[$id] as $kd => $vd) {
    $resultday[$id][$kd] = array_count_values($vd);
    arsort($resultday[$kd]);
  } 
}

foreach(array_unique($gameid) as $id){

foreach($result[$id] as $m => $v) {

foreach ($v as $d => $k) {


$heatobja[$id][] = array('x'=>$m,'y'=>$d,'value'=>$k);

}
}

foreach($resultday[$id] as $md => $vd) {

foreach ($vd as $dd => $kd) {
$heatobjb[$id][] = array('x'=>$dd,'y'=>$md,'value'=>$kd);

}
}
}


foreach($rows as $row) {

$getpref = explode("_", $row->gameId, 2);
$pref = $getpref[0];
$gameid[] = $row->gameId;

$dayofweek = (date('N', strtotime($row->hit_date))-1);
$dayname = date('l', strtotime($row->hit_date));
$monthnumber = (date('n', strtotime($row->hit_date))-1);


$hitsall[] = array (
'jid' => $row->gameId,
'hitamount' => $row->hit_amount,
'hitdate' => date("j M Y H:i:s", strtotime($row->hit_date)),
'atmavg' => $row->atmavg,
'tatmavg' => $row->tatmavg,
'monthnumber'=>$monthnumber,
'dayofweek' => $dayofweek,
'dayhour' => $dayhour,
'heata' => $heatobja[$row->gameId],
'heatb' => $heatobjb[$row->gameId],
);
}

$grouped = array();
foreach ($hitsall as $item) {
    if (!isset($grouped[$item['jid']])) {
        $grouped[$item['jid']] = $item;
    }
    
    $grouped[$item['jid']]['hits'][] = 
    array('hitdate'=>$item['hitdate'],
    'hitamount'=>$item['hitamount'],
    'atmavg'=>$item['atmavg'],
    'tatmavg'=>$item['tatmavg']);
    unset($grouped[$item['jid']]['hitdate']);
    unset($grouped[$item['jid']]['hitamount']);
    unset($grouped[$item['jid']]['atmavg']);
    unset($grouped[$item['jid']]['tatmavg']);
    unset($grouped[$item['jid']]['jid']);
    unset($grouped[$item['jid']]['monthnumber']);
    unset($grouped[$item['jid']]['dayofweek']);
    unset($grouped[$item['jid']]['dayhour']);
}


foreach ($grouped as $key => $value) {
$myFile = "components/com_jackpots/data/json/hits/$key.json";
$fh = fopen($myFile, 'w') or die("can't open file");
fwrite($fh, json_encode($grouped[$key],JSON_NUMERIC_CHECK));
fclose($fh);
}

echo '<pre style="margin-top:200px;">';
print_r($grouped['0_megamoolahmega']);
//print_r($dayarr['0_megamoolahmega']);
//print_r($resultday['0_megamoolahmega']);
//print_r($heat);
echo '</pre>';
}
}
?>
