<?php
// Definisci il percorso per raggiungere l'ambiente Joomla
define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/../../..'));
define('DS', DIRECTORY_SEPARATOR);

require_once (JPATH_BASE .'/includes/defines.php');
require_once (JPATH_BASE .'/includes/framework.php');
require_once (JPATH_BASE .'/components/com_bonuses/chron/api.php');

// Ottieni i parametri dalla richiesta GET
$params = array(
    'status' => isset($_GET['status']) ? $_GET['status'] : 'active',
    'start' => isset($_GET['start']) ? (int)$_GET['start'] : 0,
    'limit' => isset($_GET['limit']) ? $_GET['limit'] : '24',
    'order' => isset($_GET['order']) ? explode(',', $_GET['order']) : array('added', 'DESC'),
    'countrycode' => isset($_GET['countrycode']) ? $_GET['countrycode'] : '',
    'type' => isset($_GET['type']) ? $_GET['type'] : null,
    isset($_GET['filter']) ? $_GET['filter'] : null => isset($_GET['fq']) ? $_GET['fq'] : null,
);

// Imposta l'intestazione per l'output JSON
header('Content-Type: application/json');
$data = get_api_data_bonus($params);
echo $data;
?>
