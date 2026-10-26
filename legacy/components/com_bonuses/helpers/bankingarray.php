<?php
defined('_JEXEC') or die('Restricted access');


$db = JFactory::getDbo();
$querybank = $db->getQuery(true);
$querybank
    ->select(array('text','value'))
    ->from($db->quoteName('#__jreviews_fieldoptions'))
    ->where($db->quoteName('fieldid').' = '.$db->quote('137'))
    ->order($db->quoteName('text') . ' ASC'); 
// Reset the query using our newly populated query object.
$db->setQuery($querybank);

$rowbank = $db->loadAssocList();

print_r($rowbank);

?>