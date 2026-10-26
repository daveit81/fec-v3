<?php
// Definisci il percorso per raggiungere l'ambiente Joomla

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/../../..'));
define('DS', DIRECTORY_SEPARATOR); // Se non hai già definito DS
define('IMAGES_PATH', 'images'); // Definisci la tua costante IMAGES_PATH
define('EASYBLOG_IMAGES_PATH', 'easyblog_images'); // Definisci la tua costante EASYBLOG_IMAGES_PATH

// Include il framework Joomla
require_once (JPATH_BASE .'/includes/defines.php');
require_once (JPATH_BASE .'/includes/framework.php');
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonustypesarray.php' );
require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'scripts'.DS.'functions.php' );
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'countriesarray.php' );
// Crea un'applicazione
$mainframe = JFactory::getApplication('site');

$db = JFactory::getDbo();

// Query per i software
$querysoft = $db->getQuery(true);
$querysoft->select(array('text', 'value'))
    ->from($db->quoteName('#__jreviews_fieldoptions'))
    ->where($db->quoteName('fieldid') . ' = 311');

$db->setQuery($querysoft);
$rowsoft = $db->loadAssocList();

$softCounts = array();

foreach ($rowsoft as $soft) {
    $queryCount = $db->getQuery(true);
    $queryCount->select('COUNT(*)')
        ->from($db->quoteName('#__jreviews_content'))
        ->where($db->quoteName('jr_soft') . ' LIKE ' . $db->quote('%*' . $soft['value'] . '*%'));

    $db->setQuery($queryCount);
    $count = $db->loadResult();
  
    $softCounts[] = array(
        'text' => $soft['text'],
        'value' => $soft['value'],
        'url' => 'index.php?option=com_jreviews&Itemid=426&lang=en&url=tag%2Fsoft%2F'.$soft['value'],
        'count' => $count,
    );
}

// Aggiungi la nuova query qui
$cascount = $db->getQuery(true);
$cascount->select('COUNT(' . $db->quoteName('id') . ')')
    ->from($db->quoteName('#__frank'));

$db->setQuery($cascount);
$castotal = $db->loadResult();



// Aggiungi le query per i paesi


foreach ($countries_array as $code => $country) {
    $countercountryquery = "SELECT COUNT(contentid) FROM jos_jreviews_content WHERE jr_restrictedcountries NOT LIKE '%*".strtolower($code)."*%' AND jr_restrictedcountries != ''";
    $db->setQuery($countercountryquery);
    $countercountry = $db->loadResult();

    // Aggiungi il risultato della query al tuo array
    $softCounts['countries'][] = array(
        'code' => $code,
        'country' => $country,
        'count' => $countercountry,
        'url' => 'index.php?option=com_jreviews&lang=en&url=tag%2Frestrictedcountries%2F' . strtolower($code),  // Aggiunta dell'URL
    );
}

// Aggiungi il risultato della nuova query all'array dei risultati
$softCounts['totalCasinos'] = $castotal;

// Converti l'array in JSON
$jsonData = json_encode($softCounts, JSON_PRETTY_PRINT);

// Scrivi i dati JSON in un file
file_put_contents('totals.json', $jsonData);




$query = $db->getQuery(true);

$query->select(array(
            $db->quoteName('b.type'),
            'COUNT(*) as count',
            'SUM(CASE WHEN ' . $db->quoteName('b.min_dep') . ' IS NULL OR ' . $db->quoteName('b.min_dep') . ' = 0 THEN 1 ELSE 0 END) as no_deposit_count'
        ))
        ->from($db->quoteName('#__bonuses', 'b'))
        ->join('INNER', $db->quoteName('#__jreviews_content', 'jrc') . ' ON (' . $db->quoteName('b.cid') . ' = ' . $db->quoteName('jrc.contentid') . ')')
        ->where($db->quoteName('b.status') . ' = ' . $db->quote('active'))
        ->where($db->quoteName('jrc.jr_closed') . ' != ' . $db->quote('*yes*'))
        ->where('(' . $db->quoteName('jrc.jr_suspended') . ' IS NULL OR ' . $db->quoteName('jrc.jr_suspended') . ' = "")')
        ->where('(' . $db->quoteName('jrc.jr_blacklisted') . ' IS NULL OR ' . $db->quoteName('jrc.jr_blacklisted') . ' = "")')
        ->group($db->quoteName('b.type'));

$db->setQuery($query);
$results = $db->loadObjectList();





$typeCounts = array();

foreach ($results as $result) {
    if ($result->type == 'fs') {
        $typeCounts['fs'] = $result->count - $result->no_deposit_count;
        $typeCounts['nds'] = $result->no_deposit_count;
    } else {
        $typeCounts[$result->type] = $result->count;
    }
}






$combinedBonusArray = array();

foreach ($bonustype as $key => $name) {
    $count = isset($typeCounts[$key]) ? $typeCounts[$key] : 0;

    if ($key == 'nds') {
        $url = "index.php?option=com_bonuses&lang=en&subtype=nd&type=fs&view=show";
        $newurl = "index.php?option=com_bonuses&lang=en&subtype=nd&type=fs&view=bonuses";
    } else {
        $url = "index.php?option=com_bonuses&lang=en&type=" . $key . "&view=show";
        $newurl = "index.php?option=com_bonuses&lang=en&type=" . $key . "&view=bonuses";
    }

    $combinedBonusArray[$key] = array(
        'title' => $name,
        'count' => $count,
        'url' => $url,
        'newurl' => $url
    );
}


$jsonData = json_encode($combinedBonusArray, JSON_PRETTY_PRINT);
file_put_contents('bonusTypes.json', $jsonData);



$queryBlog = $db->getQuery(true);
$queryBlog->select($db->quoteName(array('id', 'created', 'title', 'image', 'intro')))
           ->from($db->quoteName('jos_easyblog_post'))
           ->order('created DESC');
$db->setQuery($queryBlog, 0, 5);

$rowsBlog = $db->loadObjectList();


$baseDirectory = '/home/freeextr/public_html/';
$baseUrl = 'https://www.freeextrachips.com/';

foreach ($rowsBlog as $row) {
    $jsonImage = json_decode($row->image, true);
    $imageUrl = $jsonImage['url'];
    $id = $row->id;
    $introTextRaw = $row->intro;
    $url = 'index.php?option=com_easyblog&Itemid=416&id=' . $id . '&lang=en&view=entry';

    // Ridimensiona e converte l'immagine
    $outputImageDirectory = JPATH_BASE .DS. 'images' .DS. 'easyblog_images' .DS;
    $imageName = basename($imageUrl, pathinfo($imageUrl, PATHINFO_EXTENSION)) . 'webp';
    $outputImagePath = $outputImageDirectory . $id .$imageName; 

    resizeAndConvertToWebp($imageUrl, 256, 185, $outputImagePath);

    // Converti il percorso del file in un URL
    $imageURL = str_replace($baseDirectory, $baseUrl, $outputImagePath);

    // Aggiungi il percorso dell'immagine ridimensionata all'oggetto row
    $row->resizedImageUrl = $imageURL;
}






$file_path = "latestblog.json"; // Modifica il percorso per adattarlo alla struttura delle directory del tuo server

$fp = fopen($file_path, 'w');
fwrite($fp, json_encode($rowsBlog));
fclose($fp);


// Decodifica i contenuti dei file JSON
$bonusTypes = json_decode(file_get_contents('bonusTypes.json'), true);
$totals = json_decode(file_get_contents('totals.json'), true);
$latestblog = json_decode(file_get_contents('latestblog.json'), true);

// Unisci gli array insieme
$homePageData = array(
    'bonusTypes' => $bonusTypes,
    'totals' => $totals,
    'latestBlog' => $latestblog
);

// Codifica l'array combinato come JSON e scrivilo nel file homePageData.json
file_put_contents('homePageData.json', json_encode($homePageData));
