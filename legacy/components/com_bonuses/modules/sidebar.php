<?php

defined('_JEXEC') or die('Restricted access');

require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'countriesarray.php' );
require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'currenciesarray.php' );
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonustypesarray.php' );
require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonuslabelsarray.php' );

$countrycode = getenv(GEOIP_COUNTRY_CODE);
$jinput = JFactory::getApplication()->input;
$cid = JRequest::getVar('id');
$bid = JRequest::getVar('bid');
$bonusurl = JRoute::_('index.php?option=com_bonuses&cid='.$cid.'&view=casino');
// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query
    ->select(array('a.*','b.*','c.*'))
    ->from($db->quoteName('#__content', 'a'))
    ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')')
    ->join('INNER', $db->quoteName('#__frank', 'c') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('c.id') . ')')
    ->where($db->quoteName('b.jr_closed') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_restrictedcountries').' NOT LIKE '.$db->quote('%*'.strtolower($countrycode).'*%'))
    ->where($db->quoteName('a.catid').' = '.$db->quote('8'))
    ->order($db->quoteName('c.frankvalue') . ' DESC');
// Reset the query using our newly populated query object.
$db->setQuery($query,0,5);

$row = $db->loadAssocList();


foreach($row as $set) {


$li .= '<li>
<a class="top-list ellipsis" href="#0">
<img alt="logo" class="logo-lists" width="30" src="/images/casino-logos/'.$set['jr_logosmall'].'"><span class="brand">'.$set['title'].'</span>
</a>

<a class="go">Visit</a>
</li>';
}


$queryone = $db->getQuery(true);
 
$queryone->select(array('a.*', 'b.*','c.*'));
$queryone->from($db->quoteName('#__content', 'a'));
$queryone->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')');
$queryone->join('INNER', $db->quoteName('#__frank', 'c') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('c.id') . ')');
$queryone->where('MONTH(b.jr_cofmdate) = MONTH(CURDATE())');
// Reset the query using our newly populated query object.
$db->setQuery($queryone);
$cofm = $db->loadAssoc();

;?>
<div class="sidebar-inner">
<div class="widget_one">
<h3>Best Online Casinos</h3>
<ul>
<?php echo $li;?>
</ul>
</div>

<div class="widget_two">
<h3>Casino of the Month</h3>
<div class="wrapper">
<div class="item cofm" style="background-image:url(/images/casino-logos/<?php echo $cofm['jr_logosmall'];?>">
<span class="item_cofm_month"><?php echo date("F Y");?></span>
<span class="item_cofm_brand"><?php echo $cofm['title'];?></span>
</div>
<div class="item otr">
<a class="go" href="#0">Visit</a>
<a class="review" href="#0">Review</a>
</div>
</div>
</div>
</div>