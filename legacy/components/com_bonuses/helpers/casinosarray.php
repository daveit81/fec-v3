<?php
defined('_JEXEC') or die('Restricted access');


// Get a db connection.
$db = JFactory::getDbo();
 
// Create a new query object.
$query = $db->getQuery(true);
 
$query
    ->select(array('a.id','a.title','b.contentid'))
    ->from($db->quoteName('#__content', 'a'))
    ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')')
    ->where($db->quoteName('a.catid').' = '.$db->quote('8'))
    ->where($db->quoteName('b.jr_closed') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'')
    ->order($db->quoteName('a.title') . ' ASC'); 
// Reset the query using our newly populated query object.
$db->setQuery($query);

$row = $db->loadAssocList();

foreach($row as $set) {

$optedit .= '<option value="'.JRoute::_('index.php?option=com_bonuses&view=edit&cid='.$set['id']).'">'.$set['title'].'</option>';

}

?>