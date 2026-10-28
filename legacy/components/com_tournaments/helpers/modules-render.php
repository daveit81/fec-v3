<?php 

defined('_JEXEC') or die('Restricted access');

$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');

$jinput = JFactory::getApplication()->input;
$cid = $jinput->get('id');

$db = JFactory::getDbo();
$query = $db->getQuery(true);
$query->select(array('SQL_CALC_FOUND_ROWS a.*', 'b.contentid','b.jr_restrictedcountries','b.jr_gourl','b.jr_logosmall'));
$query->from($db->quoteName('tournaments', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where('a.cid = '.$db->quote($cid));
$query->where('a.end >= NOW()');
$query->order('start ASC,end ASC');
$db->setQuery($query,0,5);
$tournaments = $db->loadObjectList();
$db->setQuery('SELECT FOUND_ROWS();');
$totalt = $db->loadResult();

// Time format is UNIX timestamp or
  // PHP strtotime compatible strings
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
    $intervals = array('year','month','day','hour','min');
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

$n = 0;
foreach ($tournaments as $row) {
$n++;
$dnow = new DateTime();
$dend = new DateTime($row->end);
$dstart = new DateTime($row->start);

$sdate = date("d M y h:i a", strtotime($row->start));
$edate = date("d M y h:i a", strtotime($row->end));

if($dnow >= $dstart) {
$start = 'Running';
$startclass = 'run';
$startc = '';
} elseif ($dnow->add(new DateInterval('P1D')) >= $dstart) {
$start = dateDiff("now", $row->start);
$startc = 'data-time="'.$row->start.'"';
$startclass = 'imm';
} else {
$start = $sdate;
$startc = '';
$startclass = '';
}

if(($start == 'Running') && ($dend->sub(new DateInterval('P2D')) <= $dnow)) {
$end = dateDiff("now", $row->end);
$endc = 'data-time="'.$row->end.'"';
$endclass = 'imme';
}else{
$end = $edate;
$endc = '';
$endclass = '';
}

if(is_numeric($row->prize)) {
$prize = $row->currency.$row->prize;
} else {
$prize = $row->prize;
}

if(fnmatch('*'.$countrycode.'*', $row->jr_restrictedcountries)) {
$play = '<i class="fa fa-ban"></i>';
$dis = 'disabled';
} else {
$play = '<span>Join</span>';
$dis = '';
}



$fee = $row->currency.$row->fee;
if($row->cid == 1000) {
$fee = '&euro;'.$row->fee; 
} else {
    $fee = $fee;
}


$logo = '/images/casino-logos/'.$row->jr_logosmall;
$reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$row->cid.'&lang=en&view=article');

$gaexturl = $row->casino.' Tournaments Play';
$onclickplay = "ga('send', 'event', 'Tournaments', 'Play Button', '$gaexturl')";

$layout .= '<tr>
<td data-title="Tournament" class="tourn">
<span class="name">'.$row->tournament.'</span>
<span class="game"><i class="fa fa-long-arrow-right" aria-hidden="true"></i> '.$row->game.'</span>
</td>
<td data-title="Entry Fee">'.$fee.'</td>
<td data-title="Prize Pool">'.$prize.'</td>
<td data-title="Start" class="'.$startclass.'">'.$start.'</td>
<td data-title="End" class="'.$endclass.'">'.$end.'</td>
</tr>';

}

;?>