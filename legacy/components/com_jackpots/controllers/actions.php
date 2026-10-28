<?php
// No direct access to this file

error_reporting(E_ALL);
ini_set('display_errors', 1);


defined('_JEXEC') or die('Restricted access');
 
// import Joomla controller library
jimport('joomla.application.component.controller');
//error_reporting(E_ALL);
//ini_set('display_errors', 1);




class JackpotsControllerActions extends JController
{

public function edit()
  {
  
  
$jinput = JFactory::getApplication()->input;

$jid = $jinput->get('pk');
$gid = $jinput->get('gid');
$name = $jinput->get('name');
$value = $jinput->get('value','','raw');


$profile = new stdClass();
$profile->jid = $jid;
$profile->gid = $gid;
$profile->$name = $value;
 
if (isset($profile->jid)) {
  $result = JFactory::getDbo()->updateObject('#__jackpots', $profile, 'jid');
}
  
  
echo '<pre style="margin-top:200px;">';
var_dump($_POST);
echo '</pre>';   
  }
  
  
public function editsoft()
  {
      
      function join_array($array) {

$deds = array();
foreach ($array as $ded) {
  $deds[] = strtolower($ded);
}

if(!empty($array)) {
// Now join them together in an SQL syntax
$deds_joined = "*".implode("*", $deds)."*";

return $deds_joined;

} else {

return;

}
}
  
  
$jinput = JFactory::getApplication()->input;

$sid = $jinput->get('pk');
$name = $jinput->get('name');
$value = $jinput->get('value','','raw');


$profile = new stdClass();
$profile->sid = $sid;
$profile->restrictions = join_array($value);
 
if (isset($profile->sid)) {
  $result = JFactory::getDbo()->updateObject('#__providers', $profile, 'sid');
}
  
  
echo '<pre style="margin-top:200px;">';
var_dump($_GET);
echo $sid;
echo '</pre>';   
  }
  
  
}
?>