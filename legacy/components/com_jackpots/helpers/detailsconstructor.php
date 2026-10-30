<?php 

defined('_JEXEC') or die('Restricted access');


/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/


$countries = array(
    'AF' => 'Afghanistan',
    'AX' => 'Aland Islands',
    'AL' => 'Albania',
    'DZ' => 'Algeria',
    'AS' => 'American Samoa',
    'AD' => 'Andorra',
    'AO' => 'Angola',
    'AI' => 'Anguilla',
    'AQ' => 'Antarctica',
    'AG' => 'Antigua and Barbuda',
    'AR' => 'Argentina',
    'AM' => 'Armenia',
    'AW' => 'Aruba',
    'AU' => 'Australia',
    'AT' => 'Austria',
    'AZ' => 'Azerbaijan',
    'BS' => 'Bahamas',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'BB' => 'Barbados',
    'BY' => 'Belarus',
    'BE' => 'Belgium',
    'BZ' => 'Belize',
    'BJ' => 'Benin',
    'BM' => 'Bermuda',
    'BT' => 'Bhutan',
    'BO' => 'Bolivia',
    'BQ' => 'Bonaire, Saint Eustatius and Saba',
    'BA' => 'Bosnia and Herzegovina',
    'BW' => 'Botswana',
    'BV' => 'Bouvet Island',
    'BR' => 'Brazil',
    'IO' => 'British Indian Ocean Territory',
    'VG' => 'British Virgin Islands',
    'BN' => 'Brunei',
    'BG' => 'Bulgaria',
    'BF' => 'Burkina Faso',
    'BI' => 'Burundi',
    'KH' => 'Cambodia',
    'CM' => 'Cameroon',
    'CA' => 'Canada',
    'CV' => 'Cape Verde',
    'KY' => 'Cayman Islands',
    'CF' => 'Central African Republic',
    'TD' => 'Chad',
    'CL' => 'Chile',
    'CN' => 'China',
    'CX' => 'Christmas Island',
    'CC' => 'Cocos Islands',
    'CO' => 'Colombia',
    'KM' => 'Comoros',
    'CK' => 'Cook Islands',
    'CR' => 'Costa Rica',
    'HR' => 'Croatia',
    'CU' => 'Cuba',
    'CW' => 'Curacao',
    'CY' => 'Cyprus',
    'CZ' => 'Czech Republic',
    'CD' => 'Democratic Republic of the Congo',
    'DK' => 'Denmark',
    'DJ' => 'Djibouti',
    'DM' => 'Dominica',
    'DO' => 'Dominican Republic',
    'TL' => 'East Timor',
    'EC' => 'Ecuador',
    'EG' => 'Egypt',
    'SV' => 'El Salvador',
    'GQ' => 'Equatorial Guinea',
    'ER' => 'Eritrea',
    'EE' => 'Estonia',
    'ET' => 'Ethiopia',
    'FK' => 'Falkland Islands',
    'FO' => 'Faroe Islands',
    'FJ' => 'Fiji',
    'FI' => 'Finland',
    'FR' => 'France',
    'GF' => 'French Guiana',
    'PF' => 'French Polynesia',
    'TF' => 'French Southern Territories',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GE' => 'Georgia',
    'DE' => 'Germany',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GR' => 'Greece',
    'GL' => 'Greenland',
    'GD' => 'Grenada',
    'GP' => 'Guadeloupe',
    'GU' => 'Guam',
    'GT' => 'Guatemala',
    'GG' => 'Guernsey',
    'GN' => 'Guinea',
    'GW' => 'Guinea-Bissau',
    'GY' => 'Guyana',
    'HT' => 'Haiti',
    'HM' => 'Heard Island and McDonald Islands',
    'HN' => 'Honduras',
    'HK' => 'Hong Kong',
    'HU' => 'Hungary',
    'IS' => 'Iceland',
    'IN' => 'India',
    'ID' => 'Indonesia',
    'IR' => 'Iran',
    'IQ' => 'Iraq',
    'IE' => 'Ireland',
    'IM' => 'Isle of Man',
    'IL' => 'Israel',
    'IT' => 'Italy',
    'CI' => 'Ivory Coast',
    'JM' => 'Jamaica',
    'JP' => 'Japan',
    'JE' => 'Jersey',
    'JO' => 'Jordan',
    'KZ' => 'Kazakhstan',
    'KE' => 'Kenya',
    'KI' => 'Kiribati',
    'XK' => 'Kosovo',
    'KW' => 'Kuwait',
    'KG' => 'Kyrgyzstan',
    'LA' => 'Laos',
    'LV' => 'Latvia',
    'LB' => 'Lebanon',
    'LS' => 'Lesotho',
    'LR' => 'Liberia',
    'LY' => 'Libya',
    'LI' => 'Liechtenstein',
    'LT' => 'Lithuania',
    'LU' => 'Luxembourg',
    'MO' => 'Macao',
    'MK' => 'Macedonia',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'MY' => 'Malaysia',
    'MV' => 'Maldives',
    'ML' => 'Mali',
    'MT' => 'Malta',
    'MH' => 'Marshall Islands',
    'MQ' => 'Martinique',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'YT' => 'Mayotte',
    'MX' => 'Mexico',
    'FM' => 'Micronesia',
    'MD' => 'Moldova',
    'MC' => 'Monaco',
    'MN' => 'Mongolia',
    'ME' => 'Montenegro',
    'MS' => 'Montserrat',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'MM' => 'Myanmar',
    'NA' => 'Namibia',
    'NR' => 'Nauru',
    'NP' => 'Nepal',
    'NL' => 'Netherlands',
    'NC' => 'New Caledonia',
    'NZ' => 'New Zealand',
    'NI' => 'Nicaragua',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'NU' => 'Niue',
    'NF' => 'Norfolk Island',
    'KP' => 'North Korea',
    'MP' => 'Northern Mariana Islands',
    'NO' => 'Norway',
    'OM' => 'Oman',
    'PK' => 'Pakistan',
    'PW' => 'Palau',
    'PS' => 'Palestinian Territory',
    'PA' => 'Panama',
    'PG' => 'Papua New Guinea',
    'PY' => 'Paraguay',
    'PE' => 'Peru',
    'PH' => 'Philippines',
    'PN' => 'Pitcairn',
    'PL' => 'Poland',
    'PT' => 'Portugal',
    'PR' => 'Puerto Rico',
    'QA' => 'Qatar',
    'CG' => 'Republic of the Congo',
    'RE' => 'Reunion',
    'RO' => 'Romania',
    'RU' => 'Russia',
    'RW' => 'Rwanda',
    'BL' => 'Saint Barthelemy',
    'SH' => 'Saint Helena',
    'KN' => 'Saint Kitts and Nevis',
    'LC' => 'Saint Lucia',
    'MF' => 'Saint Martin',
    'PM' => 'Saint Pierre and Miquelon',
    'VC' => 'Saint Vincent and the Grenadines',
    'WS' => 'Samoa',
    'SM' => 'San Marino',
    'ST' => 'Sao Tome and Principe',
    'SA' => 'Saudi Arabia',
    'SN' => 'Senegal',
    'RS' => 'Serbia',
    'SC' => 'Seychelles',
    'SL' => 'Sierra Leone',
    'SG' => 'Singapore',
    'SX' => 'Sint Maarten',
    'SK' => 'Slovakia',
    'SI' => 'Slovenia',
    'SB' => 'Solomon Islands',
    'SO' => 'Somalia',
    'ZA' => 'South Africa',
    'GS' => 'South Georgia and the South Sandwich Islands',
    'KR' => 'South Korea',
    'SS' => 'South Sudan',
    'ES' => 'Spain',
    'LK' => 'Sri Lanka',
    'SD' => 'Sudan',
    'SR' => 'Suriname',
    'SJ' => 'Svalbard and Jan Mayen',
    'SZ' => 'Swaziland',
    'SE' => 'Sweden',
    'CH' => 'Switzerland',
    'SY' => 'Syria',
    'TW' => 'Taiwan',
    'TJ' => 'Tajikistan',
    'TZ' => 'Tanzania',
    'TH' => 'Thailand',
    'TG' => 'Togo',
    'TK' => 'Tokelau',
    'TO' => 'Tonga',
    'TT' => 'Trinidad and Tobago',
    'TN' => 'Tunisia',
    'TR' => 'Turkey',
    'TM' => 'Turkmenistan',
    'TC' => 'Turks and Caicos Islands',
    'TV' => 'Tuvalu',
    'VI' => 'U.S. Virgin Islands',
    'UG' => 'Uganda',
    'UA' => 'Ukraine',
    'AE' => 'United Arab Emirates',
    'GB' => 'United Kingdom',
    'US' => 'United States',
    'UM' => 'United States Minor Outlying Islands',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu',
    'VA' => 'Vatican',
    'VE' => 'Venezuela',
    'VN' => 'Vietnam',
    'WF' => 'Wallis and Futuna',
    'EH' => 'Western Sahara',
    'YE' => 'Yemen',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe',
);

$document = JFactory::getDocument();
$document->addStyleSheet('/css/jpt.min.css');
//$document->addStyleSheet('/css/newstyle.css');

$jinput = JFactory::getApplication()->input;
$jidn = $jinput->get('c', '', 'int');
$j = urldecode(base64_decode($jinput->get('j', '', 'raw')));



if (!empty($j)) {
$jid = $j;
$getpref = explode("_", $j, 2);
$pref = $getpref[0];
$cid = $pref;
} else {
$jid = $jinput->get('jid', '', 'raw');
$cid = $jinput->get('cid','','int');
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


$input = JFactory::getApplication()->input;
$article = JTable::getInstance('content');
$article->load($cid);
$reviewurl = JRoute::_('index.php?option=com_content&Itemid=26&catid=8&id='.$cid.'&lang=en&view=article');

if($cid == 0) {
$casino = 'All';
$source = 'Pooled';
} else {

$casinowurl = '<a href="'.$reviewurl.'">'.$article->get('title').'</a>';
$source = '<a href="'.$reviewurl.'">'.$article->get('title').'</a>';
$casino = $article->get('title');

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

$db = JFactory::getDBO();

$query = $db->getQuery(true);
$query
    ->select(array('a.text','a.value', 'b.restrictions'))
    ->from($db->quoteName('#__jreviews_fieldoptions', 'a'))
    ->join('INNER', $db->quoteName('#__providers', 'b') . ' ON (' . $db->quoteName('a.value') . ' = ' . $db->quoteName('b.value') . ')')
    ->where($db->quoteName('a.fieldid').' = '.$db->quote('311'));

$db->setQuery($query);
$sq = $db->LoadAssocList();
  
               foreach($sq as $soft)
  {
 $software[$soft['value']] = array(
 'text' => $soft['text'], 
 'url' => JRoute::_('index.php?option=com_jreviews&Itemid=426&lang=en&url=tag%2Fsoft%2F'.$soft['value'].''),
 'restr'=> $soft['restrictions']
 );
  }
  


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

 
// Create a new query object - External DB.
$qb = $exdb->getQuery(true);
$qb->select('*');
$qb->from($db->quoteName($jid));
$qb->order('update_datetime DESC');
$exdb->setQuery($qb);
$exrows = $exdb->loadObjectList();

$starttracklast = end($exrows);
$startdate = date("jS F Y", strtotime($starttracklast->date));

foreach($exrows as $obj)
{
$i++;
$hitsamount = $obj->hit_amount;

if($hitsamount > 0) {
$hitsval[] = $hitsamount;
$hitshistory[] = array('hit'=>$obj->hit_amount,'date' => $obj->hit_date);
}
}

foreach($rows as $row) {
$amount = '<sup>'.$currency[$row->currency].'</sup>'.number_format($row->amount,2);
$amountp = $currency[$row->currency].number_format($row->amount,2);
$avgwin = '<sup>'.$currency[$row->currency].'</sup>'.number_format($row->avg_win,2);
$avgwinp = $currency[$row->currency].number_format($row->avg_win,2);
$avgwinraw = $row->avg_win;
$avgwindet = '<sup>'.$currency[$row->currency].'</sup>'.number_format($row->avg_win,2);
$lasthit = '<sup>'.$currency[$row->currency].'</sup>'.number_format($row->last_hit_value,2);
$lasthitdate = date("jS F Y", strtotime($row->last_hit_date));
$lasthittime = date("H:i", strtotime($row->last_hit_date));
$avgfreq = $row->avg_freq;
$lasthitdatestr = strtotime($row->last_hit_date);
$lastupdate = dateDiff("now",strtotime($row->last_update));

$avgamdiff = $row->amount -$row->avg_win;
$avgamperc = number_format(($row->amount - $row->avg_win)/$row->avg_win*100,2);

if($avgamdiff < 0) {
$cstatus = '<i class="fa fa-caret-down"></i> '.$currency[$row->currency].number_format(abs($avgamdiff),2).' below average ('.$avgamperc.'%)';
} elseif ($avgamdiff == 0) {
$cstatus = '<i class="nc-icon-outline arrows-2_cross-horizontal"></i> '.$currency[$row->currency].number_format(abs($avgamdiff),2).' on average ('.$avgamperc.'%)';
} else {
$cstatus = '<i class="fa fa-caret-up"></i> '.$currency[$row->currency].number_format(abs($avgamdiff),2).' above average (+'.$avgamperc.'%)';
}




$dtz = new DateTimeZone('America/Chicago');
$time_hit = new DateTime('now', $dtz);
$offset = $dtz->getOffset( $time_hit ) / 3600;

if($row->hits > 0) {
$percwin = (($row->amount/$row->avg_win)*100)/10;
$wndiff = strtotime('now') - strtotime($row->last_hit_date);
$perctime = (($wndiff/($row->avg_freq*3600))*100)/10;
$jindexraw = ($percwin + $perctime)/2;
$jindex = $jindexraw >= '10' ? '10' : $jindexraw = $jindexraw;
} else {
$jindex = '0';
}

$timediffstat = ($row->avg_freq) - ($wndiff/3600);
$timediffperc = number_format(($timediffstat/$row->avg_freq)*100,2);

if($row->hits > 1) {
if(($wndiff/3600) < $row->avg_freq) {
$tstatus = secondsToWords(abs($timediffstat)).' below average (-'.abs($timediffperc).'%)';
} elseif (($wndiff/3600) == $row->avg_freq) {
$tstatus = secondsToWords(abs($timediffstat)).' on average ('.abs($timediffperc).'%)';
} elseif (($wndiff/3600) > $row->avg_freq) {
$tstatus = secondsToWords(abs($timediffstat)).' above average (+'.abs($timediffperc).'%)';
}
} else {
$tstatus = 'No data available';
}
// Data declarations for hits > 0 or > 1

if($row->hits == 0) {
$cstatus = 'Normal progression, collecting data';
$tstatus = 'Normal progression, collecting data';
$lasthitelapsed = 'No hits recorded';
$avgwinp = 'No data available';
$avgfreq = 'No data available';
} elseif ($row->hits == 1) {
$cstatus = $cstatus;
$tstatus = $tstatus;
$lasthitelapsed = dateDiff("now",$lasthitdatestr);
$avgwinp = $avgwinp;
$avgfreq = 'No data available';
} else {
$cstatus = $cstatus;
$tstatus = $tstatus;
$lasthitelapsed = dateDiff("now",$lasthitdatestr);
$avgwinp = $avgwinp;
$avgfreq = $avgfreq;
}


$jtitle = $row->name;
$jindexcircle = $jindex/10;
$providerwurl = '<a href="'.$software[$row->soft]['url'].'">'.$software[$row->soft]['text'].'</a>';
$provider = $software[$row->soft]['text'];
$providerval = $software[$row->soft]['value'];
$providerrestr = explode('*',$software[$row->soft]['restr']);


$country_code = $_SERVER['GEOIP_COUNTRY_CODE'];

if (in_array(strtolower($country_code), $providerrestr))
  {
  $countrytext = '<i class="fa fa-circle na"></i> Players from '.$countries[$country_code].' not allowed';
  } else {
  $countrytext = '<i class="fa fa-circle ok"></i> Players from '.$countries[$country_code].' allowed';  
  }

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

$bigwin = $currency[$row->currency].number_format(max($hitsval),2);
$smallwin = $currency[$row->currency].number_format(min($hitsval),2);
$totalpaid = $currency[$row->currency].number_format(array_sum($hitsval),2);

}

$hitsh = array_slice($hitshistory,0,20);

foreach($hitsh as $h) {

$hitli .= '<li><b>'.$currency[$row->currency].number_format($h['hit'],2).'</b> - '.date("jS F Y H:i", strtotime($h['date'])).' UTC -5</li>';

}

?>