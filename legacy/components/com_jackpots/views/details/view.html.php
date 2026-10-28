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
class JackpotsViewDetails extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
	

parent::display($tpl);

	}
}

require_once JPATH_BASE.DS.'components'.DS.'com_jackpots'.DS.'helpers'.DS.'detailsconstructor.php';


function secondsToWords($val)
{

$seconds = $val*3600;

    $ret = "";
    $times = array();
    /*** get the days ***/
    $days = intval(intval($seconds) / (3600*24));
    if($days == 1)
    {
        $ret[] = $days." day";
        
    } elseif ($days > 0 && $days != 1) {
    
       $ret[] = $days." days";
    }

    /*** get the hours ***/
    $hours = (intval($seconds) / 3600) % 24;
    if($hours == 1)
    {
        $ret[] = $hours." hour";
        
    } elseif ($hours > 0 && $hours != 1) {
    
        $ret[] = $hours." hours";
        
    }

    /*** get the minutes ***/
    $minutes = (intval($seconds) / 60) % 60;
    if($minutes == 1)
    {
        $ret[] = $minutes." min";
        
    } elseif ($minutes > 0 && $minutes != 1) {
    
        $ret[] = $minutes." mins";
    }

    /*** get the seconds ***/
    $seconds = intval($seconds) % 60;
    if ($seconds == 1) {
    
        $ret[] = $seconds." sec";
        
    } elseif ($seconds > 0 && $seconds != 1) {
    
      $ret[] = $seconds." secs";
      
    }
    
    
    $times[] = $ret;
    
    if(is_numeric($val)) {
    return implode(", ", $ret);
    } else {
    return $val;
    }
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
    $intervals = array('year','month','day','hour','min','sec');
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



$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');
$document->addStyleSheet('/css/nucleo-outline.css');
$document->addScript('https://code.highcharts.com/stock/highstock.js');
$document->addScript('https://code.highcharts.com/modules/heatmap.js');


function pluralize($val, $singular, $plural = false)
{
   if (!$plural) $plural = $singular . 's';

  return ($val == 1 ? $singular : $plural) ;
}



$pg1 = 'The <strong>"'.$jtitle.'"</strong> progressive jackpot amount is currently '.$amountp.'';

if ($hits > 0) {
$pg2 = 'Since we started tracking it on '.$startdate.', we recorded '.$hits.' ' .pluralize($hits, 'win','wins').'.
The last hit amount was '.$lasthit.' on '.$lasthitdate.' around '.$lasthittime.' UTC '.$offset.'.';
} else { 
$pg2 = 'Since we started tracking it on '.$startdate.', we did not record any winnings.';
}

if($hits > 1) {
$pg3 = 'This jackpot pays out on average '.$avgwinp.' every '.secondsToWords($avgfreq).'.';
} else {

$pg3 = '';
}

/*echo '<pre>';
print_r($startdate);
echo '</pre>';*/
;?>
<div class="item-page">
<div class="r-container">


  <!-- Nav tabs -->
  <ul class="nav nav-tabs reviews bonuses" role="tablist">
    <li role="presentation" class="active"><a href="#bonus-info" aria-controls="bonus-info" role="tab" data-toggle="tab">Bonus Details</a></li>
    <li role="presentation"><a href="#more" aria-controls="more" role="tab" data-toggle="tab">More Bonuses</a></li>
    <li role="presentation"><a href="#details" aria-controls="details" role="tab" data-toggle="tab">Browse All</a></li>
  </ul>

  <!-- Tab panes -->
<div class="tab-content review">
 <section role="tabpanel" class="sdb-block col-lg-4 col-md-3 tab-pane det-infos" id="details">
<h3>Jackpot Info</h3>
<h4 class="list-group-item-heading">Software</h4>
<div class="list-group-item-text">
<?php echo $providerwurl;?></div>

<h4 class="list-group-item-heading">Source</h4>
<div class="list-group-item-text">
<?php echo $source;?></div>

<h4 class="list-group-item-heading">Last Data Update</h4>
<div class="list-group-item-text">
<?php echo $lastupdate;?> ago</div>

<h4 class="list-group-item-heading">Start Tracking Date</h4>
<div class="list-group-item-text">
<?php echo $startdate;?></div>

<h3>Stats</h3>
<h4 class="list-group-item-heading">Average Win</h4>
<div class="list-group-item-text">
<?php echo $avgwinp;?></div>

<h4 class="list-group-item-heading">Average Frequency</h4>
<div class="list-group-item-text">
<?php echo secondsToWords($avgfreq);?></div>

<h4 class="list-group-item-heading">Time since last hit</h4>
<div class="list-group-item-text">
<?php echo $lasthitelapsed;?></div>

<h4 class="list-group-item-heading">Current Amount Status</h4>
<div class="list-group-item-text">
<?php echo $cstatus;?></div>

<h4 class="list-group-item-heading">Current Time Status</h4>
<div class="list-group-item-text">
<?php echo $tstatus;?></div>

<h4 class="list-group-item-heading">Highest Win Recorded</h4>
<div class="list-group-item-text">
<?php echo $bigwin;?></div>

<h4 class="list-group-item-heading">Lowest Win Recorded</h4>
<div class="list-group-item-text">
<?php echo $smallwin;?></div>

<h4 class="list-group-item-heading">Total Paid</h4>
<div class="list-group-item-text">
<?php echo $totalpaid;?></div>

</section>



<section role="tabpanel" class="central-block col-md-9 col-lg-8 tab-pane active rev-txt" id="bonus-info">
<h2><?php echo $jtitle;?> Progressive - Overview</h2>
<p><?php echo $pg1;?>. <?php echo $pg2;?> <?php echo $pg3;?></p>
<div id="trend-chart" style="height: 400px" data-jid="<?php echo $jid;?>" data-avg="<?php echo $avgwinraw;?>" data-curr="<?php echo $currency[$row->currency];?>" data-curraw="<?php echo $row->currency;?>"></div>
<h3>Hits History (Last 20)</h3>
<p><ol><?php echo $hitli;?></ol></p>
<div id="hits-chart" style="height: 400px;" data-jid="<?php echo $jid;?>" data-avg="<?php echo $avgwinraw;?>"></div>
<h3>Heatmaps</h3>
<div id="heatmap-a" data-jid="<?php echo $jid;?>"></div>
<div id="heatmap-b" style="height: 600px;" data-jid="<?php echo $jid;?>"></div>
<div id="chart-test" data-jid="<?php echo $jid;?>"></div>
</section>

<section role="tabpanel" class="tab-pane central-block col-md-9 col-lg-8 rev-txt" id="more">
Bottom Modules
</section>
</div>
</div>
</div>
<script>
jQuery(document).ready(function($) {
    
    var jid = $('#trend-chart').data("jid");
    var avg = $('#trend-chart').data("avg");
    var curr = $('#trend-chart').data("curr");
    var curraw = $('#trend-chart').data("curraw");
    var jname = $('h1.casname').text();
      $.getJSON('https://www.freeextrachips.com/components/com_jackpots/data/json/values/'+jid+'.json', function(datatrend) {

    function objectLength(obj) {
      var result = 0;
      for (var prop in obj) {
        if (obj.hasOwnProperty(prop)) {
          result++;
        }
      }
      return result;
    }
    
    
    
    var amount = datatrend.amount;
    var avgwin = datatrend.avgwin[0];
    var hits = datatrend.hits;
    var ymax = null;
    var hitstate = null;
    
    var maxamarr = amount.map(a => a.y);
    var maxam = Math.max.apply(null, maxamarr);
    
    if(avgwin > 0) {
    
    avgwintext = avgwin.toFixed(2).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
    
    } else {
    avgwintext = null;
    }
    
    if(maxam >= avgwin) {
    ymax = null;
    }else{
    ymax = avgwin;
    }
    
console.log(avgwin);

    if (objectLength(hits) > 0 && objectLength(hits) <=10) {
      hitstate = true;
    } else {
      hitstate = false;
    }

    $('#trend-chart').highcharts({
      chart: {
        type: 'area',
        zoomType: 'x'

      },
      title: {
        text: jname,
      },
      subtitle: {
        text: 'Jackpot Trend Graph'
      },
      rangeSelector: {
        buttons: [{
          type: 'day',
          count: 1,
          text: '24H'
        }, {
          type: 'week',
          count: 1,
          text: '1W'
        }, {
          type: 'day',
          count: 29,
          text: '1M'
        }, {
          type: 'all',
          text: 'All'
        }],
        selected: 2,
        enabled: true,
        inputEnabled: false,

        buttonPosition: {

        }
      },

      xAxis: {
        endOnTick: false,
        startOnTick: false,
        type: 'datetime',
        labels: {
          format: '{value:%e %b %y}',
          align: 'left'
        },
      },
      
      tooltip: {
            formatter: function() {
            var date = Highcharts.dateFormat('%e %b %Y, %H:%m',new Date(this.x));
            var am = curr + this.y.toFixed(2).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
                if (this.series.name == 'Jackpot') {
                return  '<small><b>' + date +'</b></small><br/><span style="color:' + this.point.color + '">\u25CF</span> Jackpot: ' + am;
                } else {
                return  '<small><b>' + date +'</b></small><br/><span style="color:' + this.point.color + '">\u25CF</span> Hit: ' + am;
                
                }
            }
        },

      yAxis: {
        endOnTick: true,
        startOnTick: false,
        max: ymax,
        labels: {
                formatter: function () {
                    return '€' + this.axis.defaultLabelFormatter.call(this);
                }
                },
        title: {
          text: 'Jackpot',
          style: {color:'#00abcd'}
        }, 
        plotLines: [{
          value: avgwin,
          color: '#f6c574',
          zIndex: 0,
          dashStyle: 'ShortDashDot',
          width: 2,
          label: {
            text: 'Average Win - ' +curr+  avgwintext,
            style: {
              color: '#f6c574'
            },
          }
        }]
        
      },


      plotOptions: {
        series: {
          lineWidth: 1,
          states: {
            hover: {
              enabled: true,
              lineWidth: 2
            }
          }
        },

        area: {

          marker: {
            radius: 2
          },
          lineWidth: 1,
          states: {
            hover: {
              lineWidth: 2
            }
          },
          threshold: null
        }
      },

      credits: {
        enabled: false
      },


      series: [{
        name: 'Jackpot',
        color: '#00abcd',
        data: datatrend.amount,
        turboThreshold: 0

      }, {
        name: 'Hits',
        visible: hitstate,
        type: 'flags',
        data: datatrend.hits,
        turboThreshold: 0,
        shape: "circlepin",
        title: "H",
        color: "#fc5652",
        fillColor: "#fc5652",
        style: {
          "color": "white"
        }
      }],
    });



  });

$.getJSON('https://www.freeextrachips.com/index.php?option=com_jackpots&task=getjson.heatmapa&jid='+jid, function(dataheata) {

    $('#heatmap-a').highcharts({

        chart: {
            type: 'heatmap',
            marginTop: 40,
            marginBottom: 80,
            plotBorderWidth: 1
        },


        title: {
            text: 'Hits per Weekday per Month'
        },

        xAxis: {
            categories: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            tickInterval:  1,
            gridLineWidth: 1,
            tickLength: 0,
            lineWidth: 0,
            min: 0, 
            max: 11
        },

        yAxis: {
            categories: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            title: null,
            tickInterval:  1,
            minPadding: 0,
            maxPadding: 0,
            startOnTick: false,
            endOnTick: false,
            tickLength: 0,
            lineWidth: 0,
            min: 0,
            max: 6
        },

        colorAxis: {
            min: 0,
            minColor: '#ffffff',
            maxColor: '#00abcd'
        },

        legend: {
            align: 'right',
            layout: 'vertical',
            margin: 0,
            verticalAlign: 'top',
            y: 25,
            symbolHeight: 280,
            enabled: false
        },

        tooltip: {
            formatter: function () {
                return '<b>' + this.series.xAxis.categories[this.point.x] + '</b> sold <br><b>' +
                    this.point.value + '</b> items on <br><b>' + this.series.yAxis.categories[this.point.y] + '</b>';
            }
        },

        series: [{
            name: 'Sales per employee',
            borderWidth: 0,
            data: dataheata,
            dataLabels: {
                enabled: true,
                color: '#000000'
            }
        }]

    });
});

$.getJSON('https://www.freeextrachips.com/index.php?option=com_jackpots&task=getjson.heatmapb&jid='+jid, function(dataheatb) {

    $('#heatmap-b').highcharts({

        chart: {
            type: 'heatmap',
            marginTop: 40,
            marginBottom: 80,
            plotBorderWidth: 0
        },


        title: {
            text: 'Hits per Hour per Day'
        },

        xAxis: {
            categories: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            tickInterval:  1,
            gridLineWidth: 1,
            tickLength: 0,
            lineWidth: 0,
            min: 0, 
            max: 6
        },

        yAxis: {
            categories: ['00', '01', '02', '03', '04', '05', '06','07','08','09', '10', '11','12','13','14','15','16','17','18','19','20','21','22','23'],
            title: null,
            tickInterval:  1,
            minPadding: 0,
            maxPadding: 0,
            startOnTick: false,
            endOnTick: false,
            tickLength: 0,
            lineWidth: 0,
            min: 0,
            max: 23
        },

        colorAxis: {
            min: 0,
            minColor: '#ffffff',
            maxColor: '#fc5652'
        },

        legend: {
            align: 'left',
            layout: 'horizontal',
            margin: 0,
            verticalAlign: 'bottom',
            y: 10,
            //symbolHeight: 280,
            enabled: true
        },

        tooltip: {
            formatter: function () {
                return '<b>' + this.series.xAxis.categories[this.point.x] + '</b> sold <br><b>' +
                    this.point.value + '</b> items on <br><b>' + this.series.yAxis.categories[this.point.y] + '</b>';
            }
        },

        series: [{
            name: 'Sales per employee',
            borderWidth: 0,
            data: dataheatb,
            dataLabels: {
                enabled: false,
                color: '#000000'
            }
        }]

    });

    
    $(function () {
    var options = { 
       chart: {
        zoomType: 'xy'
    },
    title: {
        text: 'Hits Graph'
    },
    subtitle: {
        text: 'Hits + Average Win Over Time + Average Time between hits'
    },
    xAxis: {
        categories: [],
        crosshair: true
    },
    yAxis: [{ // Primary yAxis
        labels: {
            format: '',
            style: {
                 color: '#00abcd',
            },
            enabled: false
        },
        title: {
            text: 'Average Time',
            style: {
                 color: '#00abcd',
            }
        },
        opposite: true

    }, { // Secondary yAxis
        gridLineWidth: 0,
        title: {
            text: 'Hit Amount',
            style: {
                color: '#fc5652',
            }
        },
        labels: {
            formatter: function () {
                    return curr + this.axis.defaultLabelFormatter.call(this);
                },
            style: {
                color: '#fc5652',
            }
        }

    }, { // Tertiary yAxis
        gridLineWidth: 0,
        title: {
            text: 'Average Win Over Time',
            style: {
                color: '#F6C56C',
            }
        },
        labels: {
            formatter: function () {
                    var max = Math.max.apply(null, options.series[0].data);
                    return curr + this.axis.defaultLabelFormatter.call(this);
                    
                },
            style: {
                color: '#F6C56C',
            },
            
        },
        opposite: true,

    }],
    tooltip: {
    
    formatter: function () {
            var s = '<b>' + this.x + '</b></br>';
            
         

            $.each(this.points, function () {
            
             if(this.series.name == 'Average Time' ){
            var time = this.y;
            var seconds = parseInt(time*3600);
            var days = Math.floor(seconds / (3600*24));
            seconds  -= days*3600*24;
            var hrs   = Math.floor(seconds / 3600);
            seconds  -= hrs*3600;
            var mnts = Math.floor(seconds / 60);
            seconds  -= mnts*60;
                    
           s += '<b style="color:' + this.point.color + '">'+ this.series.name +' (every): </b><br/>'+days+" days, "+hrs+" Hrs, "+mnts+" Minutes, "+seconds+" Seconds";
                    
             
            } else {
            
            var value = this.y;
            var num = curr + value.toFixed(2).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
            
                s += '<br/><b style="color:' + this.point.color + '">' + this.series.name + ': </b><br/>' +
                    num + '<br/>';
                 }   
            });

            return s;
        },
            
                shared: true
            },
    legend: {
        layout: 'horizontal',
        
        backgroundColor: (Highcharts.theme && Highcharts.theme.legendBackgroundColor) || '#FFFFFF'
    },
    series: [{
        name: 'Hit Amount',
        type: 'column',
        color: '#fc5652',
        yAxis: 1,
        data: [],
        tooltip: {
            valueSuffix: ''
        }

    }, {
        name: 'Average Win',
        type: 'area',
        color: '#F6C56C',
        fillOpacity: 0.2,
        yAxis: 2,
        data: [],
        marker: {
            enabled: true,
        },
        dashStyle: '',
        tooltip: {
            valueSuffix: ''
        }

    }, {
        name: 'Average Time',
        type: 'line',
        color: '#00abcd',
        data: [],
        tooltip: {
        shared: true
        }
    }]
    };
    $.getJSON('https://www.freeextrachips.com/components/com_jackpots/data/json/hits/'+jid+'.json', function (data) {
       var count = Object.keys(data.hits).length;
        for (var i = count - 1; i >= 0; i--) {
            options.xAxis.categories.push(data.hits[i].hitdate);
            options.series[0].data.push(data.hits[i].hitamount);
            options.series[1].data.push(data.hits[i].atmavg);
            options.series[2].data.push(data.hits[i].tatmavg);
        }
        //var count = Object.keys(data.hits).length;
        //console.log(data.heata);
        
        $('#hits-chart').highcharts(options);
        var chart = $('#hits-chart').highcharts();
        var maxawot = Math.max.apply(null, options.series[0].data);
        chart.yAxis[2].setExtremes(null,(maxawot),true,false);
        chart.yAxis[1].setExtremes(null,(maxawot),true,false);
        //console.log(maxawot);
    });
        
    
    });   
});
        

});
</script>