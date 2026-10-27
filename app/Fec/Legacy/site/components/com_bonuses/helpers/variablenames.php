<?php
//define constant
defined('_JEXEC') or die('Restricted access'); 
//create application

$db = JFactory::getDBO();
$query = $db->getQuery(true);
$query->select(array('value', 'text'));
$query->from($db->quoteName('#__jreviews_fieldoptions'));
$query->where($db->quoteName('fieldid') . ' = "311"');
$db->setQuery($query);
$sq = $db->LoadAssocList();
  
               foreach($sq as $soft)
  {
            
  

 $software[$soft['value']] = $soft['text']; 


  
  }



?>