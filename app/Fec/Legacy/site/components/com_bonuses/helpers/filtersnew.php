<?php
//define constant
define( '_JEXEC', 1 );

define( 'DS', DIRECTORY_SEPARATOR );
 
//you need to adjust joomla path according to your joomla installation
define( 'JPATH_BASE', $_SERVER[ 'DOCUMENT_ROOT' ] . DS . '' );
 
//include joomla core files
require_once( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
require_once( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );
require_once( JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'factory.php' );
require_once( JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'currenciesarray.php');
require_once( JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'countriesarray.php');

defined('_JEXEC') or die('Restricted access');
//create application
$mainframe = JFactory::getApplication('site');

$filter = JRequest::getVar('filter');
$type = JRequest::getVar('type');
$refine = JRequest::getVar('refine');
$cf = JRequest::getVar('cf');
$fq = JRequest::getVar('cfq');
$meth = JRequest::getVar('meth');
$curr = JRequest::getVar('curr');
$db = JFactory::getDBO();
$query = $db->getQuery(true);

$fqsplit = (preg_split('#(?<=\d)(?=[a-z])#i', $fq));

switch($cf) {
case 'software':
$query->where($db->quoteName('b.jr_soft') . ' LIKE "%*'.trim($fq).'*%"');
break;

case 'wr':
$query->where($db->quoteName('a.wr') . ' = '.$fqsplit[0].' AND '.$db->quoteName('a.wr_meth').' LIKE "'.$fqsplit[1].'"');
break;

case 'exclusive':
$query->where($db->quoteName('a.exclusive') . ' LIKE "'.$fq.'"');
break;

case 'cashable':
$query->where($db->quoteName('a.cashable') . ' LIKE "'.$fq.'"');
break;

case 'minimum-deposit':
if($curr == '') {
$query->where($db->quoteName('a.min_dep') . ' = '.$fqsplit[0].' AND '.$db->quoteName('a.currency').' LIKE "'.$fqsplit[1].'"');
}
break;

case 'minimum-cashout':
$query->where($db->quoteName('a.min_cashout') . ' = '.$fqsplit[0].' AND '.$db->quoteName('a.currency').' LIKE "'.$fqsplit[1].'"');
break;

case 'maximum-cashout':
$query->where($db->quoteName('a.max_cashout') . ' = '.$fqsplit[0].' AND '.$db->quoteName('a.currency').' LIKE "'.$fqsplit[1].'"');
break;

case 'allowed-countries':
$query->where('('.$db->quoteName('a.country_ok').' LIKE "%*'.$fq.'*%" OR '.$db->quoteName('a.country_ok').' LIKE "") AND '.$db->quoteName('a.country_no').' NOT LIKE "%*'.$fq.'*%"');
$query->where($db->quoteName('b.jr_restrictedcountries').' NOT LIKE "%*'.$fq.'*%"');
break;

case 'restricted-countries':
$query->where($db->quoteName('a.country_no').' LIKE "%*'.$fq.'*%" OR '.$db->quoteName('a.country_no').' LIKE ""');
$query->where($db->quoteName('b.jr_restrictedcountries').' LIKE "%*'.$fq.'*%"');
break;


case 'code':
if($fq == 'required') {
$query->where($db->quoteName('a.code').' NOT LIKE ""');
} else {
$query->where($db->quoteName('a.code').' LIKE ""');
}
break;

case 'max-bonus':
$query->where($db->quoteName('a.max_bonus') . ' = '.$fqsplit[0].' AND '.$db->quoteName('a.currency').' LIKE "'.$fqsplit[1].'"');
break;
}



switch($filter) {

case 'software':
$query->select(array('GROUP_CONCAT(DISTINCT b.jr_soft SEPARATOR "") as soft'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_soft') . ' NOT LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}


$db->setQuery($query);
$sq = $db->loadRow();
$multiarray = explode("*",$sq['0']);
$multiunique = array_unique($multiarray,SORT_STRING);
$msorted = asort($multiunique);

    echo '<select class="filter" name="fq">';
    echo '<option value="">Choose a Software</option>';
   
               foreach($multiunique as $row)
  {
  
  
                $textquery = "SELECT text, value FROM jos_jreviews_fieldoptions WHERE value LIKE '$row' GROUP BY text ORDER BY text";
                
                $db->setQuery($textquery);
                                               
                $rows = $db->loadObjectList();
                
  
  
  foreach ($rows as $v ) {
  
  $softname .= $v->text;
  
  echo '<option '.$sel.' type="'.$type.'" value="'.$v->value.'">'.$v->text.'</option>';
 
  }

  
  }

                  
echo '</select>';
break;

case 'wr':
$query->select(array('a.wr as wr, a.wr_meth as wrmeth'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.wr') . ' NOT LIKE ""');
$query->group(array($db->quoteName('a.wr'), $db->quoteName('a.wr_meth')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

    echo '<select class="filter" name="fq">';
    
    
    echo '<option value="">Choose WR</option>';
                foreach($row as $wr)
  {

$wrmeth = ($wr['wrmeth'] ==  'bd' ? 'X(b+dep)' : 'X(b)');
  echo '<option value="'.$wr['wr'].'" data-meth="'.$wr['wrmeth'].'">'.$wr['wr'].''.$wrmeth.'</option>';

}               
echo '</select>';          

break;


case 'exclusive':

$query->select(array('a.exclusive'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->group(array($db->quoteName('a.exclusive')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

echo '<select class="filter" name="fq">';
    
    
    echo '<option value="">Choose Exclusive</option>';
                foreach($row as $ex)
{
  echo '<option value="'.$ex['exclusive'].'">'.ucfirst($ex['exclusive']).'</option>';
}               
echo '</select>';
break;

case 'cashable':
$query->select(array('a.cashable'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.cashable') . ' NOT LIKE \'\'');
$query->group(array($db->quoteName('a.cashable')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

echo '<select class="filter" name="fq">';
 
echo '<option value="">Choose Cashable Option</option>';
                foreach($row as $cashable)
{
echo '<option value="'.$cashable['cashable'].'">'.ucfirst($cashable['cashable']).'</option>';
}               
echo '</select>';         

break;

case 'minimum-deposit':
$query->select(array('a.min_dep as mindep, a.currency as currency'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.min_dep') . ' > 0');
$query->group(array($db->quoteName('a.min_dep'), $db->quoteName('a.currency')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

    echo '<select class="filter" name="fq">';
    
    
    echo '<option value="">Choose Minimum Deposit</option>';
                foreach($row as $mindep)
  {
  
  $curr = $mindep['currency'] == '' ? $currency['usd'] : $currency[$mindep['currency']];
  
  echo '<option value="'.$mindep['mindep'].'" data-curr="'.$mindep['currency'].'">'.$curr.''.$mindep['mindep'].'</option>';

}               
echo '</select>';          

break;

case 'minimum-cashout':

$query->select(array('a.min_cashout as mincashout, a.currency as currency'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.min_cashout') . ' > 0');
$query->group(array($db->quoteName('a.min_cashout'), $db->quoteName('a.currency')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

    echo '<select class="filter" name="fq">';
    
    
    echo '<option value="">Choose Minimum Cashout</option>';
                foreach($row as $dep)
  {
  
  $curr = $dep['currency'] == '' ? $currency['usd'] : $currency[$dep['currency']];
  
  echo '<option value="'.$dep['mincashout'].'" data-curr="'.$dep['currency'].'">'.$curr.''.$dep['mincashout'].'</option>';

}               
echo '</select>';          

break;

case 'maximum-cashout':
$query->select(array('a.max_cashout as maxcashout, a.currency as currency'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.max_cashout') . ' > 0');
$query->group(array($db->quoteName('a.max_cashout'), $db->quoteName('a.currency')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

    echo '<select class="filter" name="fq">';
    
    
    echo '<option value="">Choose Maximum Cashout</option>';
                foreach($row as $dep)
  {
  
  $curr = $dep['currency'] == '' ? $currency['usd'] : $currency[$dep['currency']];
  
  echo '<option value="'.$dep['maxcashout'].'" data-curr="'.$dep['currency'].'">'.$curr.''.$dep['maxcashout'].'</option>';

}               
echo '</select>';          

break;

case 'allowed-countries':
case 'restricted-countries':

    echo '<select class="filter" name="fq">';
    echo '<option value="">Choose Country</option>';
   
               foreach($countries_array as $code=>$country)
  {
  

  echo '<option value="'.strtolower($code).'">'.$country.'</option>';
 
  }
                  
echo '</select>';          

break;

case 'code':
    echo '<select class="filter" name="fq">';
    echo '<option value="">Choose Bonus Code Option</option>';  
    echo '<option value="required">Required</option>';
    echo '<option value="not-required">Not Required</option>';            
    echo '</select>';
break;

case 'max-bonus':
$query->select(array('a.max_bonus as maxbonus, a.currency as currency'));
$query->from($db->quoteName('#__bonuses', 'a'));
$query->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')');
$query->where($db->quoteName('b.jr_closed') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'');
$query->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'');
$query->where($db->quoteName('a.status') . ' LIKE "active"');
$query->where($db->quoteName('a.max_bonus') . ' > 0');
$query->group(array($db->quoteName('a.max_bonus'), $db->quoteName('a.currency')));

if(isset($type) && $type != '') {

$query->where($db->quoteName('a.type').' LIKE '.$db->quote($type));

}
$db->setQuery($query);
$row = $db->loadAssocList();
asort($row);

    echo '<select class="filter" name="fq">';
    
    
    echo '<option value="">Choose Max Bonus</option>';
                foreach($row as $dep)
  {
  
  $curr = $dep['currency'] == '' ? $currency['usd'] : $currency[$dep['currency']];
  
  echo '<option value="'.$dep['maxbonus'].'" data-curr="'.$dep['currency'].'">'.$curr.''.$dep['maxbonus'].'</option>';

}               
echo '</select>';          

break;

}

?>