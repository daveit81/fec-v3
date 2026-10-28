<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
 
// import Joomla controller library
jimport('joomla.application.component.controller');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/


class JackpotsControllerGetjson extends JController
{

public function charta()
{




header("Content-type: application/json");
 
$jinput = JFactory::getApplication()->input;
$jid = $jinput->get('jid', '', 'raw');
$avg = $jinput->get('avg', '', 'raw');
$curraw = $jinput->get('curraw', '', 'raw');

$currency = array(
    'usd' => '$',
	'gbp' => '£',
	'euro' => '€',
	'eur' => '€',
	'cad' => 'CA$',
	'aud' => 'AU$',
	'nzd' => 'NZ$',
	'nok' => 'NOK',
	'sek' => 'SEK',
	'dkk' => 'DKK',
	'kr' => 'KR',
	'btc' => 'BTC',
	'mbtc' => 'mBTC'
);

 
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
 
// Create a new query object.
$qa = $exdb->getQuery(true);
$qa->select('*');
$qa->from($exdb->quoteName($jid));
$qa->where('DATE_SUB( CURRENT_TIMESTAMP( ) , INTERVAL 1 MONTH ) <= update_datetime');
$qa->order('update_datetime ASC');
$exdb->setQuery($qa);
$rows = $exdb->loadObjectList();



foreach($rows as $data) {

$update = strtotime($data->update_datetime)*1000;
$hitdate = strtotime($data->hit_date)*1000;
$amount = $data->amount;
$hitamount = $data->hit_amount;
$uparray[] = strtotime($data->update_datetime)*1000;

if(!empty($data->hit_date)) {
$hits[] = '{"x":'.$hitdate.',"y":'.$hitamount.',"title":"Hit","text":"Win: '.$currency[$curraw].$hitamount.'"}';
}

$value[] = '['.$update.','.$amount.']';

}

foreach ($uparray as $up) {
$average[] = '['.$up.','.$avg.']';
}

$data = join(",", $value);
$datahit = join(",", $hits);
$dataavg = join(",", $average);

//print_r($avg);

if((empty($hits)) && ($avg <= 0)) {
echo '[{"name":"Jackpot","color":"#00abcd","data":['.$data.']}]';

}elseif ((empty($hits)) && ($avg > 0)){
    
    echo '[{"name":"Jackpot","color":"#00abcd","data":['.$data.']},{"name":"Average Win","color":"#f6c574","type":"line","dashStyle": "shortdot","lineWidth": 2,"enableMouseTracking":false,"data":['.$dataavg.'],"visible":false}]';
    
} else {
echo '[{"name":"Jackpot","color":"#00abcd","data":['.$data.']},{"name":"Hits","visible":false,"type":"flags","data":['.$datahit.'],"shape":"squarepin", "color":"#fc5652", "fillColor":"#fc5652","style": {"color":"white"}},{"name":"Average Win","color":"#f6c574","type":"line","dashStyle": "shortdot","lineWidth": 2,"enableMouseTracking":false,"data":['.$dataavg.'],"visible":false}]';
}


JFactory::getApplication()->close(); // or jexit();
}

public function chartb()
{




header("Content-type: application/json");
 
$jinput = JFactory::getApplication()->input;
$jid = $jinput->get('jid', '', 'raw');
$avg = $jinput->get('avg', '', 'raw');

 
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
 
// Create a new query object.
$qb = $exdb->getQuery(true);
$qb = 'SELECT hit, hit_date, hit_amount
FROM (
SELECT hit, hit_date, hit_amount
FROM '.$jid.'
WHERE hit IS NOT NULL
ORDER BY hit_date DESC
LIMIT 20
)sub 
ORDER BY hit_date ASC';
$exdb->setQuery($qb);
$rows = $exdb->loadObjectList();


foreach($rows as $data) {

$hitdatestr = strtotime($data->hit_date);
$hitdate = date("j M y h:i:s",$hitdatestr);
$amount = $data->amount;
$hitamount = $data->hit_amount;
$uparray[] = strtotime($data->update_datetime)*1000;


$hitgraph[] = '["'.$hitdate.'",'.$hitamount.']';

}


$datahg = join(",",$hitgraph);


echo '[{"name":"Hits","type":"column","pointWidth": 10,"color":"#fc5652","data":['.$datahg.']}]';

JFactory::getApplication()->close(); // or jexit();
}

public function heatmapa()
{




header("Content-type: application/json");
 
$jinput = JFactory::getApplication()->input;
$jid = $jinput->get('jid', '', 'raw');
$avg = $jinput->get('avg', '', 'raw');

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
 
// Create a new query object.
$qb = $exdb->getQuery(true);
$qb->select(array('hit','hit_date','hit_amount'));
$qb->from($exdb->quoteName($jid));
$qb->where('hit IS NOT NULL');
$qb->order('hit_date ASC');
$exdb->setQuery($qb);
$rows = $exdb->loadObjectList();

foreach($rows as $data) {

$dayofweek = (date('N', strtotime($data->hit_date))-1);
$dayname = date('l', strtotime($data->hit_date));
$monthnumber = (date('n', strtotime($data->hit_date)) -1);
$montharr[$monthnumber][] = $dayofweek;

}


foreach($montharr as $k => $v) {
    $result[$k] = array_count_values($v);
    arsort($result[$k]);
}
//print_r($result);

foreach($result as $m => $v) {

foreach ($v as $d => $k) {

$heat[] = '['.$m.','.$d.','.$k.']';

}

}

$dataheat = join(",", $heat);

echo '['.$dataheat.']';

JFactory::getApplication()->close(); // or jexit();
}

public function heatmapb()
{




header("Content-type: application/json");
 
$jinput = JFactory::getApplication()->input;
$jid = $jinput->get('jid', '', 'raw');
$avg = $jinput->get('avg', '', 'raw');

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
 
// Create a new query object.
$qb = $exdb->getQuery(true);
$qb->select(array('hit','hit_date','hit_amount'));
$qb->from($exdb->quoteName($jid));
$qb->where('hit IS NOT NULL');
$qb->order('hit_date ASC');
$exdb->setQuery($qb);
$rows = $exdb->loadObjectList();

foreach($rows as $data) {
$dayofweek = (date('N', strtotime($data->hit_date))-1);
$dayhour = (date('G', strtotime($data->hit_date)));
$dayname = date('l', strtotime($data->hit_date));
$monthnumber = (date('n', strtotime($data->hit_date)) -1);
$arr[$dayhour][] = $dayofweek;

//echo $dayhour.'-';
}

foreach($arr as $k => $v) {
    $result[$k] = array_count_values($v);
    arsort($result[$k]);
}
//print_r($result);

foreach($result as $m => $v) {

foreach ($v as $d => $k) {

$heat[] = '['.$d.','.$m.','.$k.']';

}

}

$dataheat = join(",", $heat);

echo '['.$dataheat.']';

JFactory::getApplication()->close(); // or jexit();
}


}
?>