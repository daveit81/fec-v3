<?php
$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');

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

$softarr = $CustomFields->fieldValue('jr_soft',$listing,false,false);
$cid = $listing['Listing']['listing_id'];
$brand = $listing['Listing']['title'];
$softtag = Sanitize::getString($this->params['tag'],'value');


foreach ($softarr as $val) {
    $query_parts_soft[] = "soft = '".$val."'";
}

$string_soft = implode(' OR ', $query_parts_soft);


$db = JFactory::getDbo();
 
// Create a new query object.
$querymain = $db->getQuery(true);
$querymain->select('*');
$querymain->from($db->quoteName('#__jackpots'));
$querymain->where($db->quoteName('soft') . ' LIKE '. $db->quote($softtag));
$querymain->where($db->quoteName('published') . ' = 1');
$querymain->order($db->quoteName('amount') . ' DESC');
$db->setQuery($querymain,0,9);
 
// Load the results as a list of stdClass objects (see later for more options on retrieving data).
$jpots = $db->loadObjectList();


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




/*echo '<pre>';
print_r($jpots);
echo '</pre>';*/

foreach ($jpots as $row) {
$hits = $row->hits;
$amount = $currency[$row->currency].number_format($row->amount,2);
$avgwin = $currency[$row->currency].number_format($row->avg_win,2);

if ($row->cid == 0) {
    $jtype = 'Pooled';
} else {
    $jtype = 'Local';
}



if($hits > 0) {
$percwin = (($row->amount/$row->avg_win)*100)/10;
$wndiff = strtotime('now') - strtotime($row->last_hit_date);
$perctime = (($wndiff/($row->avg_freq*3600))*100)/10;
$jindexraw = ($percwin + $perctime)/2;
$jindex = $jindexraw >= '10' ? '10' : $jindexraw = $jindexraw;
} else {
$jindex = '0';
}


$avgperc = 95;

$new_avg = ($avgperc / 100) * $row->avg_win;


if (($row->amount >= $new_avg) && ($row->amount < $row->avg_win) && ($hits > 0)) {
    $amclass = 'nearavg';
}

elseif (($row->amount >= $row->avg_win) && ($hits > 0)) {
    $amclass = 'aboveavg';
} else {
    $amclass = '';
}

$lastupdb = strtotime($row->last_update);

$difflu = time() - $lastupdb;

if($difflu > 86400)
{
$lastupdiff = '<i class="fa fa-circle jdata poor"></i>';
}elseif ($difflu >= 43200 && $difflu <= 86400){
$lastupdiff = '<i class="fa fa-circle jdata fair"></i>';
} else {
    $lastupdiff = '<i class="fa fa-circle jdata good"></i>';
}

$jlayout .= '<tr>
<td data-title="Tournament" class="tourn">
<span class="name">'.$row->name.'</span>
<span class="game">'.$lastupdiff.' '.getCasino($row->cid).' <i class="fa fa-caret-right" aria-hidden="true"></i> '.$jtype.'</span>
</td>
<td data-title="Amount" class="'.$amclass.'">'.$amount.'</td>
<td data-title="Hits">'.$hits.'</td>
<td data-title="Avg. Win" class="'.$startclass.'">'.$avgwin.'</td>
<td data-title="Jindex" class="'.$endclass.'">'.number_format($jindex,1).'</td>
</tr>';
}

;?>
<?php if(!empty($jpots)):?>
<section id="rt-content-top" class="item-page table-responsive-vertical">
<h3><?php echo $page['title'];?> Progressive Jackpots</h3>
<p>Below there is a list of the major <?php echo $page['title'];?> progressive jackpots as tracked by our system. The list includes, in addition to the real time amount, some basic statistics such as the winnings recorded (by our system), the average winnings and the jackpot data source ("Casino Name" <i class="fa fa-caret-right" aria-hidden="true"></i> "Local" or "Pooled").</p>
<table id="table" class="table table-bordered tournaments jpots">
      <thead>
        <tr>
          <th><span class="main">Game</span><span class="sec">Casino <i class="fa fa-caret-right" aria-hidden="true"></i> Source</span></th>
          <th>Amount</th>
          <th>Hits</th>
          <th>Avg. Win</th>
          <th><i class="fa fa-flash"></i> Jindex</th>
        </tr>
      </thead>
      <tbody>
        <?php echo $jlayout;?>
      </tbody>
    </table>
<ul class="jexlegend list-unstyled">
    <li class="ttl">Data Accuracy (dot before Casino Name)</li>
    <ul class="subjex list-unstyled">
        <li><span class="good"><i class="fa fa-circle jdata"></i> Good</span> - Our system is recording data properly</li>
        <li><span class="fair"><i class="fa fa-circle jdata"></i> Fair</span> - Our system is recording data with some delays</li>
        <li><span class="poor"><i class="fa fa-circle jdata"></i> Poor</span> - Our system is recording data with strong delays or no data from source</li>
    </ul>
    <li class="ttl">Amount (colors)</li>
    <ul class="subjex list-unstyled">
    <li><span class="aboveavg"><i class="fa fa-square"></i> Amount in red</span> -  Value above the average winnings</li>
    <li><span class="nearavg"><i class="fa fa-square"></i> Amount in orange</span> - Value close to the average winnings</li>
    </ul>
</ul>
</section>
<?php endif;?>