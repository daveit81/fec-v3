<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');


error_reporting(E_ALL);
ini_set('display_errors', 0);


// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class JackpotsViewDashboard extends JView
{
	// Overwriting JView display method
	function display($tpl = null) 
	{
	

parent::display($tpl);

	}
}


$document = JFactory::getDocument();
$document->addStyleSheet('https://vitalets.github.io/x-editable/assets/select2/select2.css');
$document->addStyleSheet('/css/jpt.min.css');
$document->addScript('https://vitalets.github.io/x-editable/assets/select2/select2.js');
$document->addScript('///netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js');
$document->addScript('https://vitalets.github.io/x-editable/assets/x-editable/bootstrap3-editable/js/bootstrap-editable.js');

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



// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
// Select all records from the user profile table where key begins with "custom.".
// Order it by the ordering field.
$query->select('*');
$query->from($db->quoteName('jos_jackpots'));

// Reset the query using our newly populated query object.
$db->setQuery($query);
 
// Load the results as a list of stdClass objects (see later for more options on retrieving data).
$rows = $db->loadObjectList();

$db = JFactory::getDBO();
$query = $db->getQuery(true);
$query->select(array('sid','value', 'name','restrictions'));
$query->from($db->quoteName('#__providers'));
$db->setQuery($query);
$sq = $db->LoadAssocList();

foreach ($sq as $s) {
    
$cvalr = explode("*",$s['restrictions']);
$cvals = implode(",",$cvalr);


 $layouts .= '<tr>
 <td>'.$s['name'].'</td>
 <td>'.$s['value'].'</td>
<td><a href="#" data-name="countries" data-pk="'.$s['sid'].'" data-type="checklist" data-value="'.strtoupper($cvals).'" class="editable editable-click">'.$s['restrictions'].'</a></td>
 <tr>';

}

foreach($rows as $row) {

if(empty($row->logo)) {
$logo = 'no logo';
}else{
$logo = $row->logo;
}

$lastupdb = strtotime($row->last_update);

$difflu = time() - $lastupdb;

if($difflu > 86400)
{
$lastupdiff = 'Poor';
}elseif ($difflu >= 43200 && $difflu <= 86400){
$lastupdiff = 'Fair';
} else {
    $lastupdiff = 'Good';
}

$layout .= '<tr>
<td><a href="#" data-name="name" data-type="text" data-pk="'.$row->jid.'" class="editable editable-click">'.$row->name.'</a></td>
<td>'.$row->jid.'</td>
<td></td>
<td>'.getCasino($row->cid).'</td>
<td>'.$lastupdiff.'</td>
<td><a href="#" data-name="currency" data-pk="'.$row->jid.'" data-type="select" data-value="'.$row->currency.'" class="editable editable-click">'.$row->currency.'</a></td>
<td>'.$row->amount.'</td>
<td><a href="#" data-name="soft" data-pk="'.$row->jid.'" data-type="select" data-value="'.$row->soft.'" class="editable editable-click soft">'.$row->soft.'</a></td>
<td><a href="#" data-name="logo" data-type="text" data-pk="'.$row->jid.'" class="editable editable-click">'.$logo.'</a></td>
<td><a href="#" data-name="published" data-pk="'.$row->jid.'" data-type="select" data-value="'.$row->published.'" class="editable editable-click">'.$row->published.'</a></td>
</tr>';

}


$db = JFactory::getDBO();
$query = $db->getQuery(true);
$query->select(array('value', 'text'));
$query->from($db->quoteName('#__jreviews_fieldoptions'));
$query->where($db->quoteName('fieldid') . ' = "311"');
$db->setQuery($query);
$sq2 = $db->LoadAssocList();
  
               foreach($sq2 as $soft)
  {
            
  

 $values[] = array(
        
        'value' => $soft['value'], 'text' => $soft['text']
    );

$soft = json_encode($values);
  
  }
  
  
  
  

?>

<section class="grid-wrap tournaments table-responsive-vertical shadow-z-1" id="container">
  <table id="table" class="table table-bordered tournaments">
      <thead>
        <tr>
          <th>Jackpot</th>
          <th>Jid</th>
          <th>Gid</th>
          <th>Last Up Diff</th>
          <th>Casino</th>
          <th>Currency</th>
          <th>Value</th>
          <th>Soft</th>
          <th>Logo</th>
          <th>Published</th>
        </tr>
      </thead>
      <tbody>
        <?php echo $layout;?>
      </tbody>
    </table>
  </section>
  
  
<section class="grid-wrap tournaments table-responsive-vertical shadow-z-1" id="container">
  <table id="table" class="table table-bordered tournaments">
      <thead>
        <tr>
          <th>Provider</th>
          <th>Value</th>
          <th>Restrictions</th>
        </tr>
      </thead>
      <tbody>
        <?php echo $layouts;?>
      </tbody>
    </table>
  </section>
  
<script>
$(document).ready(function() {
    $.fn.editable.defaults.mode = 'popup';
    var soft = <?php echo $soft;?>;
    $('[data-name="soft"]').each(function(s) {
     $(this).editable({
        source: soft,
        showbuttons: true,
        send: 'always',
        url: '/index.php?option=com_jackpots&lang=en&task=actions.edit',
        ajaxOptions: {
     type: 'post'
  }
  
});
});

$('[data-name="name"],[data-name="logo"]').each(function(s) {
     $(this).editable({
        showbuttons: true,
        send: 'always',
        url: '/index.php?option=com_jackpots&lang=en&task=actions.edit',
        ajaxOptions: {
     type: 'post'
  }
  
});
});

$('[data-name="currency"]').each(function(s) {
     $(this).editable({
        source: [
            {value: 'eur', text: 'Euro'},
            {value: 'gbp', text: 'Pound'},
            {value: 'usd', text: 'USD'}
        ],
        showbuttons: true,
        send: 'always',
        url: '/index.php?option=com_jackpots&lang=en&task=actions.edit',
        ajaxOptions: {
     type: 'post'
  }
  
});
});

$('[data-name="published"]').each(function(s) {
     $(this).editable({
        source: [
            {value: '0', text: 'No'},
            {value: '1', text: 'Yes'}
        ],
        showbuttons: true,
        send: 'always',
        url: '/index.php?option=com_jackpots&lang=en&task=actions.edit',
        ajaxOptions: {
     type: 'post'
  }
  
});
});

$('[data-name="gid"]').each(function(s) {
     $(this).editable({
        source: [
            {value: '0', text: 'No'},
            {value: '1', text: 'Yes'}
        ],
        showbuttons: true,
        send: 'always',
        url: '/index.php?option=com_jackpots&lang=en&task=actions.edit',
        ajaxOptions: {
     type: 'post'
  }
  
});
});

var countries = [];
    $.each({"BD": "Bangladesh", "BE": "Belgium", "BF": "Burkina Faso", "BG": "Bulgaria", "BA": "Bosnia and Herzegovina", "BB": "Barbados", "WF": "Wallis and Futuna", "BL": "Saint Bartelemey", "BM": "Bermuda", "BN": "Brunei Darussalam", "BO": "Bolivia", "BH": "Bahrain", "BI": "Burundi", "BJ": "Benin", "BT": "Bhutan", "JM": "Jamaica", "BV": "Bouvet Island", "BW": "Botswana", "WS": "Samoa", "BR": "Brazil", "BS": "Bahamas", "JE": "Jersey", "BY": "Belarus", "O1": "Other Country", "LV": "Latvia", "RW": "Rwanda", "RS": "Serbia", "TL": "Timor-Leste", "RE": "Reunion", "LU": "Luxembourg", "TJ": "Tajikistan", "RO": "Romania", "PG": "Papua New Guinea", "GW": "Guinea-Bissau", "GU": "Guam", "GT": "Guatemala", "GS": "South Georgia and the South Sandwich Islands", "GR": "Greece", "GQ": "Equatorial Guinea", "GP": "Guadeloupe", "JP": "Japan", "GY": "Guyana", "GG": "Guernsey", "GF": "French Guiana", "GE": "Georgia", "GD": "Grenada", "GB": "United Kingdom", "GA": "Gabon", "SV": "El Salvador", "GN": "Guinea", "GM": "Gambia", "GL": "Greenland", "GI": "Gibraltar", "GH": "Ghana", "OM": "Oman", "TN": "Tunisia", "JO": "Jordan", "HR": "Croatia", "HT": "Haiti", "HU": "Hungary", "HK": "Hong Kong", "HN": "Honduras", "HM": "Heard Island and McDonald Islands", "VE": "Venezuela", "PR": "Puerto Rico", "PS": "Palestinian Territory", "PW": "Palau", "PT": "Portugal", "SJ": "Svalbard and Jan Mayen", "PY": "Paraguay", "IQ": "Iraq", "PA": "Panama", "PF": "French Polynesia", "BZ": "Belize", "PE": "Peru", "PK": "Pakistan", "PH": "Philippines", "PN": "Pitcairn", "TM": "Turkmenistan", "PL": "Poland", "PM": "Saint Pierre and Miquelon", "ZM": "Zambia", "EH": "Western Sahara", "RU": "Russian Federation", "EE": "Estonia", "EG": "Egypt", "TK": "Tokelau", "ZA": "South Africa", "EC": "Ecuador", "IT": "Italy", "VN": "Vietnam", "SB": "Solomon Islands", "EU": "Europe", "ET": "Ethiopia", "SO": "Somalia", "ZW": "Zimbabwe", "SA": "Saudi Arabia", "ES": "Spain", "ER": "Eritrea", "ME": "Montenegro", "MD": "Moldova, Republic of", "MG": "Madagascar", "MF": "Saint Martin", "MA": "Morocco", "MC": "Monaco", "UZ": "Uzbekistan", "MM": "Myanmar", "ML": "Mali", "MO": "Macao", "MN": "Mongolia", "MH": "Marshall Islands", "MK": "Macedonia", "MU": "Mauritius", "MT": "Malta", "MW": "Malawi", "MV": "Maldives", "MQ": "Martinique", "MP": "Northern Mariana Islands", "MS": "Montserrat", "MR": "Mauritania", "IM": "Isle of Man", "UG": "Uganda", "TZ": "Tanzania, United Republic of", "MY": "Malaysia", "MX": "Mexico", "IL": "Israel", "FR": "France", "IO": "British Indian Ocean Territory", "FX": "France, Metropolitan", "SH": "Saint Helena", "FI": "Finland", "FJ": "Fiji", "FK": "Falkland Islands (Malvinas)", "FM": "Micronesia, Federated States of", "FO": "Faroe Islands", "NI": "Nicaragua", "NL": "Netherlands", "NO": "Norway", "NA": "Namibia", "VU": "Vanuatu", "NC": "New Caledonia", "NE": "Niger", "NF": "Norfolk Island", "NG": "Nigeria", "NZ": "New Zealand", "NP": "Nepal", "NR": "Nauru", "NU": "Niue", "CK": "Cook Islands", "CI": "Cote d'Ivoire", "CH": "Switzerland", "CO": "Colombia", "CN": "China", "CM": "Cameroon", "CL": "Chile", "CC": "Cocos (Keeling) Islands", "CA": "Canada", "CG": "Congo", "CF": "Central African Republic", "CD": "Congo, The Democratic Republic of the", "CZ": "Czech Republic", "CY": "Cyprus", "CX": "Christmas Island", "CR": "Costa Rica", "CV": "Cape Verde", "CU": "Cuba", "SZ": "Swaziland", "SY": "Syrian Arab Republic", "KG": "Kyrgyzstan", "KE": "Kenya", "SR": "Suriname", "KI": "Kiribati", "KH": "Cambodia", "KN": "Saint Kitts and Nevis", "KM": "Comoros", "ST": "Sao Tome and Principe", "SK": "Slovakia", "KR": "Korea, Republic of", "SI": "Slovenia", "KP": "Korea, Democratic People's Republic of", "KW": "Kuwait", "SN": "Senegal", "SM": "San Marino", "SL": "Sierra Leone", "SC": "Seychelles", "KZ": "Kazakhstan", "KY": "Cayman Islands", "SG": "Singapore", "SE": "Sweden", "SD": "Sudan", "DO": "Dominican Republic", "DM": "Dominica", "DJ": "Djibouti", "DK": "Denmark", "VG": "Virgin Islands, British", "DE": "Germany", "YE": "Yemen", "DZ": "Algeria", "US": "United States", "UY": "Uruguay", "YT": "Mayotte", "UM": "United States Minor Outlying Islands", "LB": "Lebanon", "LC": "Saint Lucia", "LA": "Lao People's Democratic Republic", "TV": "Tuvalu", "TW": "Taiwan", "TT": "Trinidad and Tobago", "TR": "Turkey", "LK": "Sri Lanka", "LI": "Liechtenstein", "A1": "Anonymous Proxy", "TO": "Tonga", "LT": "Lithuania", "A2": "Satellite Provider", "LR": "Liberia", "LS": "Lesotho", "TH": "Thailand", "TF": "French Southern Territories", "TG": "Togo", "TD": "Chad", "TC": "Turks and Caicos Islands", "LY": "Libyan Arab Jamahiriya", "VA": "Holy See (Vatican City State)", "VC": "Saint Vincent and the Grenadines", "AE": "United Arab Emirates", "AD": "Andorra", "AG": "Antigua and Barbuda", "AF": "Afghanistan", "AI": "Anguilla", "VI": "Virgin Islands, U.S.", "IS": "Iceland", "IR": "Iran, Islamic Republic of", "AM": "Armenia", "AL": "Albania", "AO": "Angola", "AN": "Netherlands Antilles", "AQ": "Antarctica", "AP": "Asia/Pacific Region", "AS": "American Samoa", "AR": "Argentina", "AU": "Australia", "AT": "Austria", "AW": "Aruba", "IN": "India", "AX": "Aland Islands", "AZ": "Azerbaijan", "IE": "Ireland", "ID": "Indonesia", "UA": "Ukraine", "QA": "Qatar", "MZ": "Mozambique"}, function(k, v) {
        countries.push({value: k, text: v});
    });
    
    $('[data-name="countries"]').each(function(s) {
     $(this).editable({
        source:countries,
        showbuttons: true,
        send: 'always',
        url: '/index.php?option=com_jackpots&lang=en&task=actions.editsoft',
        ajaxOptions: {
     type: 'post'
  },
  success:function(data){
  alert($(this).editable().data('value'));
}
  
});
});


});
</script>