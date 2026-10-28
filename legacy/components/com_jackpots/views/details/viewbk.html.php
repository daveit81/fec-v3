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

$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');
//$document->addStyleSheet('/css/newstyle.css');
$document->addScript('https://code.highcharts.com/stock/highstock.js');
//$document->addScript('https://code.highcharts.com/highcharts.js');

$jinput = JFactory::getApplication()->input;
$jid = $jinput->get('jid', '', 'raw');

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
Right Panel
</section>



<section role="tabpanel" class="central-block col-md-9 col-lg-8 tab-pane active rev-txt" id="bonus-info">
<div id="trend-chart" style="height: 400px" data-jid="<?php echo $jid;?>" ></div>
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
    var jname = $('h1.casname').text();
    $.getJSON('https://www.freeextrachips.com/index.php?option=com_jackpots&task=getjson.run&jid='+jid, function(data) { 
    $('#trend-chart').highcharts({
        chart: {
               
            
            type: 'area'
            
        },
        title: {
    text: jname
    },
    subtitle: {
    text: 'Jackpot Trend Graph'
    },
        rangeSelector: {
        buttons: [{
            type: 'day',
            count: 1,
          text: '24h'
        }, {
            type: 'week',
            count: 1,
            text: '1w'
        }, {
            type: 'month',
            count: 1,
            text: '1m'
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

    yAxis: {      
            endOnTick: true,
            startOnTick: false,      
            labels: {
                formatter: function () {
                    return '€' + this.axis.defaultLabelFormatter.call(this);
                }            
            }
          
        },
        
        plotOptions: {
        series: {
            lineWidth: 2,
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
        
                
        series: data
        
        
    });

   
        
    //var chart = new Highcharts.Chart(options);  
    

        
    });
        

});
</script>