<?php 

defined('_JEXEC') or die('Restricted access');

/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');
//$document->addStyleSheet('/css/newstyle.css');
$document->addScript('https://code.highcharts.com/highcharts.js');

$jinput = JFactory::getApplication()->input;
$jid = $jinput->get('jid', '', 'raw');

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

function getCasino ($id) {
$input = JFactory::getApplication()->input;
$article = JTable::getInstance('content');
$article->load($id);
$reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$id.'&lang=en&view=article');

if($id == 0) {
$casino = 'All';
} else {

$casino = '<a href="'.$reviewurl.'">'.$article->get('title').'</a>';

}
 return $casino;
}


$db = JFactory::getDBO();
$query = $db->getQuery(true);
$query->select(array('value', 'text'));
$query->from($db->quoteName('#__jreviews_fieldoptions'));
$query->where($db->quoteName('fieldid') . ' = "311"');
$db->setQuery($query);
$sq = $db->LoadAssocList();
  
               foreach($sq as $soft)
  {
 $software[$soft['value']] = array(
 'text' => $soft['text'], 
 'url' => JRoute::_('index.php?option=com_jreviews&Itemid=426&lang=en&url=tag%2Fsoft%2F'.$soft['value'].'')
 );
  }


function secondsToWords($val)
{

$seconds = $val*3600;

    $ret = "";

    /*** get the days ***/
    $days = intval(intval($seconds) / (3600*24));
    if($days> 0)
    {
        $ret .= $days."d ";
    }

    /*** get the hours ***/
    $hours = (intval($seconds) / 3600) % 24;
    if($hours > 0)
    {
        $ret .= $hours."h ";
    }

    /*** get the minutes ***/
    $minutes = (intval($seconds) / 60) % 60;
    if($minutes > 0)
    {
        $ret .= $minutes."m ";
    }

    /*** get the seconds ***/
    $seconds = intval($seconds) % 60;
    if ($seconds > 0) {
        $ret .= $seconds."s";
    }

    return $ret;
}

function dateDiff($time1, $time2, $precision = 6) {
    // If not numeric then convert texts to unix timestamps
    if (!is_int($time1)) {
      $time1 = strtotime($time1);
    }
    if (!is_int($time2)) {
      $time2 = strtotime($time2);
    }
 
    // If time1 is bigger than time2
    // Then swap time1 and time2
    if ($time1 > $time2) {
      $ttime = $time1;
      $time1 = $time2;
      $time2 = $ttime;
    }
 
    // Set up intervals and diffs arrays
    $intervals = array('year','month','day','hour','min','seconds');
    $diffs = array();
 
    // Loop thru all intervals
    foreach ($intervals as $interval) {
      // Create temp time from time1 and interval
      $ttime = strtotime('+1 ' . $interval, $time1);
      // Set initial values
      $add = 1;
      $looped = 0;
      // Loop until temp time is smaller than time2
      while ($time2 >= $ttime) {
        // Create new temp time from time1 and interval
        $add++;
        $ttime = strtotime("+" . $add . " " . $interval, $time1);
        $looped++;
      }
 
      $time1 = strtotime("+" . $looped . " " . $interval, $time1);
      $diffs[$interval] = $looped;
    }
    
    $count = 0;
    $times = array();
    // Loop thru all diffs
    foreach ($diffs as $interval => $value) {
      // Break if we have needed precission
      if ($count >= $precision) {
        break;
      }
      // Add value and interval 
      // if value is bigger than 0
      if ($value > 0) {
        // Add s if value is not 1
        if ($value != 1) {
          $interval .= "s";
        }
        // Add value and interval to times array
        $times[] = $value . " " . $interval;
        $count++;
      }
    }
 
    // Return string with times
    return implode(", ", $times);
  }


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

$qt = $exdb->getQuery(true);
$qt->select('TABLE_NAME');
$qt->from('information_schema.TABLES');
$qt->where('TABLE_SCHEMA = "freeextr_jptest"');
$exdb->setQuery($qt);
$trows = $exdb->loadObjectList();



$db = JFactory::getDbo();
 
// Create a new query object.
$qa = $db->getQuery(true);
$qa->select('*');
$qa->from('jos_jackpots');
$qa->where('jid LIKE '.$db->quote($jid));
$qa->where('published LIKE 1');
$qa->order('amount DESC');
$db->setQuery($qa);
$rows = $db->loadObjectList();


foreach($rows as $row) {
$qb = $exdb->getQuery(true);
$qb->select(array('gameId','amount'));
$qb->from($row->jid);
$qb->where('DATE_SUB( CURRENT_TIMESTAMP( ) , INTERVAL 7 DAY ) <= update_datetime');
$qb->order('update_datetime ASC');
$exdb->setQuery($qb);
$brows = $exdb->loadAssocList();

foreach($brows as $r) {

$data[$r['gameId']][]= $r['amount'];
}

$data_string = join(", ", $data[$row->jid]);
$amount = '<sup>'.$currency[$row->currency].'</sup>'.number_format($row->amount,2);
$avgwin = '<sup>'.$currency[$row->currency].'</sup>'.number_format($row->avg_win,2);

if($row->hits > 0) {
$percwin = (($row->amount/$row->avg_win)*100)/10;
$wndiff = strtotime('now') - strtotime($row->last_hit_date);
$perctime = (($wndiff/($row->avg_freq*3600))*100)/10;
$jindexraw = ($percwin + $perctime)/2;
$jindex = $jindexraw >= '10' ? '10' : $jindexraw = $jindexraw;
} else {
$jindex = '0';
}


$title = $row->name;
$jindexcircle = $jindex/10;
$provider = '<a href="'.$software[$row->soft]['url'].'">'.$software[$row->soft]['text'].'</a>';

$casino = getCasino($row->cid);
$hits = $row->hits;

$jindexscore = number_format($jindex, 2);
$jindexunit = floor($jindexscore);
$decimalextr = $jindexscore - $jindexunit;

if($decimalextr > 0.0) {
$jindexdecimal = strstr($decimalextr, '.');

}elseif ($jindexunit == '10') {
$jindexdecimal = '.0';
} else {
$jindexdecimal = '.00';
}

$iconurl = '/components/com_jackpots/media/logo/'.$row->jid.'.png';

$hotness = $jindex*10;
$percwincircle = $percwin/10;
$percwinbar = $percwin*10 >= '100' ? '100' : $percwin*10;
$perctimecircle = $perctime/10;
$perctimebar = $perctime*10 >= '100' ? '100' : $perctime*10;



}



?>