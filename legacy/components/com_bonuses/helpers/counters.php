<?php
//define constant
defined('_JEXEC') or die('Restricted access');

$db = JFactory::getDbo();
 
// Create a new query object.
$querycount = $db->getQuery(true);

$querycount
    ->select(array('a.type', 'COUNT(a.type) AS count', 'SUM(a.exclusive = "yes") as exclusive', 'SUM(a.code != "") as code', 'SUM(a.min_dep is NULL or a.min_dep = "") as ndr'))
    ->from($db->quoteName('#__bonuses', 'a'))
    ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')')
    ->where($db->quoteName('b.jr_closed') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'')
    ->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'')
    ->where($db->quoteName('a.status') . ' LIKE "active"')
    ->where($db->quoteName('a.bonus') . ' > 0')
    ->group($db->quoteName('a.type').' WITH rollup');
    
$db->setQuery($querycount);

$count = $db->loadAssocList('type');
