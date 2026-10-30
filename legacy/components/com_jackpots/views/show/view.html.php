<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');


/*error_reporting(E_ALL);
ini_set('display_errors', 0);
*/

// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class JackpotsViewShow extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
	

parent::display($tpl);

	}
}



$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');
//$document->addStyleSheet('/css/newstyle.css');
$document->addScript('https://code.highcharts.com/highcharts.js');

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



$db = JFactory::getDbo();
 
// Create a new query object.
$qa = $db->getQuery(true);
$qa->select('*');
$qa->from('jos_jackpots');
$qa->where('published LIKE 1');
$qa->order('amount DESC');
$db->setQuery($qa);
$rows = $db->loadObjectList();


foreach($rows as $row) {
$qb = $exdb->getQuery(true);
$qb->select(array('gameId','amount'));
$qb->from($row->jid);
$qb->where('DATE_SUB( CURRENT_TIMESTAMP( ) , INTERVAL 1 MONTH ) <= update_datetime');
$qb->order('update_datetime ASC');
$exdb->setQuery($qb);
$brows = $exdb->loadAssocList();

foreach($brows as $r) {

$data[$r['gameId']][]= $r['amount'];
}

$data_string = join(", ", $data[$row->jid]);
$amount = $currency[$row->currency].number_format($row->amount,2);
$avgwin = $currency[$row->currency].number_format($row->avg_win,2);

if($row->hits > 0) {
$percwin = (($row->amount/$row->avg_win)*100)/10;
$wndiff = strtotime('now') - strtotime($row->last_hit_date);
$perctime = (($wndiff/($row->avg_freq*3600))*100)/10;
$jindexraw = ($percwin + $perctime)/2;
$jindex = $jindexraw >= '10' ? '10' : $jindexraw = $jindexraw;
} else {
$jindex = '0';
}

$getpref = explode("_", $row->jid, 2);
$pref = $getpref[0];
if($pref == 0) {
$jurl = JRoute::_('index.php?option=com_jackpots&view=details&jid='.$row->jid.'&cid='.$pref).'?j='.urlencode(base64_encode($row->jid));
} else {
$jurl = JRoute::_('index.php?option=com_jackpots&view=details&jid='.$row->jid.'&cid='.$pref).'?j='.urlencode(base64_encode($row->jid));
}
$layout .= '<tr>
<td class="tourn">
<span class="name">'.$row->name.'</span>
<span class="game"><a href="'.$software[$row->soft]['url'].'">'.$software[$row->soft]['text'].'</a> <i class="fa fa-long-arrow-right" aria-hidden="true"></i> '.getCasino($row->cid).'</span>
</td>
<td>'.$amount.'</td>
<td>'.$row->hits.'</td>
<td>'.$avgwin.'</td>
<td>'.secondsToWords($row->avg_freq).'</td>
<td>'.number_format($jindex,1).'</td>
<td><a href="'.$jurl.'">Details</a></td>
<td>Play</td>
</tr>';

}


echo '<pre style="margin-top:200px;">';
//print_r($software);
echo '</pre>';



?>

<div id="result"></div>


<section class="grid-wrap jackpots table-responsive-vertical shadow-z-1 col-md-9" id="container">
  <table id="table" class="table table-bordered tournaments">
      <thead>
        <tr>
          <th><span class="main">Jackpot</span><span class="sec">Software <i class="fa fa-long-arrow-right" aria-hidden="true"></i> Casino</span></th>
          <th>Value</th>
          <th>Hits</th>
          <th>AVG Win</th>
          <th>Freq.</th>
          <th><i class="fa fa-flash" aria-hidden="true"></i> JIndex</th>
          <th>Details</th>
          <th>Play</th>
        </tr>
      </thead>
      <tbody>
        <?php echo $layout;?>
      </tbody>
    </table>
  </section>

<section class="col-md-3">
<h3>Jackpot stats here</h3>
</section>
<script>
$(function () {
    /**
     * Create a constructor for sparklines that takes some sensible defaults and merges in the individual
     * chart options. This function is also available from the jQuery plugin as $(element).highcharts('SparkLine').
     */
    Highcharts.SparkLine = function (a, b, c) {
        var hasRenderToArg = typeof a === 'string' || a.nodeName,
            options = arguments[hasRenderToArg ? 1 : 0],
            defaultOptions = {
                chart: {
                    renderTo: (options.chart && options.chart.renderTo) || this,
                    backgroundColor: null,
                    borderWidth: 0,
                    type: 'area',
                    margin: [0, 0, 0, 0],
                    width: 90,
                    height: 45,
                    style: {
                        overflow: 'hidden'
                    },
                    skipClone: true
                },
                title: {
                    text: ''
                },
                credits: {
                    enabled: false
                },
                xAxis: {
                    labels: {
                        enabled: false
                    },
                    title: {
                        text: null
                    },
                    startOnTick: false,
                    endOnTick: false,
                    tickPositions: []
                },
                yAxis: {
                    endOnTick: false,
                    startOnTick: false,
                    labels: {
                        enabled: false
                    },
                    title: {
                        text: null
                    },
                    tickPositions: [0]
                },
                legend: {
                    enabled: false
                },
                tooltip: {
                    enabled: false,
                    backgroundColor: null,
                    borderWidth: 0,
                    shadow: false,
                    useHTML: true,
                    hideDelay: 0,
                    shared: true,
                    padding: 0,
                    positioner: function (w, h, point) {
                        return { x: point.plotX - w / 2, y: point.plotY - h };
                    }
                },
                plotOptions: {
                series: {
                marker: {
                    states: {
                        hover: {
                            enabled: false
                        }
                    }
                }
            },
                area: {
                    color: {
    linearGradient: { x1: 0, x2: 0, y1: 0, y2: 1 },
    stops: [
        [0, '#E85657'],
        [1, '#E85657']
    ]
},
                    marker: {
                        radius: 2,
                        enabled: false
                    },
                    lineWidth: 1,
                    states: {
                        hover: {
                            lineWidth: 1
                        }
                    },
                    threshold: null
                }
            },
            };

        options = Highcharts.merge(defaultOptions, options);

        return hasRenderToArg ?
            new Highcharts.Chart(a, options, c) :
            new Highcharts.Chart(options, b);
    };

    var start = +new Date(),
        $tds = $('td[data-sparkline]'),
        fullLen = $tds.length,
        n = 0;

    // Creating 153 sparkline charts is quite fast in modern browsers, but IE8 and mobile
    // can take some seconds, so we split the input into chunks and apply them in timeouts
    // in order avoid locking up the browser process and allow interaction.
    function doChunk() {
        var time = +new Date(),
            i,
            len = $tds.length,
            $td,
            stringdata,
            arr,
            data,
            chart;

        for (i = 0; i < len; i += 1) {
            $td = $($tds[i]);
            stringdata = $td.data('sparkline');
            arr = stringdata.split('; ');
            data = $.map(arr[0].split(', '), parseFloat);
            chart = {};

            if (arr[1]) {
                chart.type = arr[1];
            }
            $td.highcharts('SparkLine', {
                series: [{
                    data: data,
                    pointStart: 1
                }],
                tooltip: {
                    headerFormat: '<span style="font-size: 10px">' + $td.parent().find('th').html() + ', Q{point.x}:</span><br/>',
                    pointFormat: '<b>{point.y}</b> USD'
                },
                chart: chart
            });

            n += 1;

            // If the process takes too much time, run a timeout to allow interaction with the browser
            if (new Date() - time > 500) {
                $tds.splice(0, i + 1);
                setTimeout(doChunk, 0);
                break;
            }

            // Print a feedback on the performance
            if (n === fullLen) {
                $('#result').html('Generated ' + fullLen + ' sparklines in ' + (new Date() - start) + ' ms');
            }
        }
    }
    doChunk();

});
</script>