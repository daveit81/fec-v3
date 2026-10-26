<?php
// Definisci il percorso per raggiungere l'ambiente Joomla

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/


define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/../../..'));
define('DS', DIRECTORY_SEPARATOR); // Se non hai già definito DS
define('IMAGES_PATH', 'images'); // Definisci la tua costante IMAGES_PATH
define('EASYBLOG_IMAGES_PATH', 'easyblog_images'); // Definisci la tua costante EASYBLOG_IMAGES_PATH

// Include il framework Joomla
require_once (JPATH_BASE .'/includes/defines.php');
require_once (JPATH_BASE .'/includes/framework.php');

function get_api_data($params = []) {
    $countrycode = isset($params['countrycode']) ? $params['countrycode'] : null;
    $order = isset($params['order']) ? $params['order'] : null;
    $limit = isset($params['limit']) ? $params['limit'] : null;
    $cofm = isset($params['cofm']) ? $params['cofm'] : null;
    $featured = isset($params['featured']) ? $params['featured'] : null;
    $noclosed = isset($params['noclosed']) ? $params['noclosed'] : null;
    $vote = isset($params['vote']) ? $params['vote'] : null;
    $customField = isset($params['customField']) ? $params['customField'] : null;
    $values = isset($params['values']) ? $params['values'] : null;
    


    // Ottieni l'istanza del database di Joomla
    $db = JFactory::getDBO();

    // Crea una nuova query
    $query = $db->getQuery(true);

    // Definisci la query SQL
    $query->select(array('a.*', 'b.*', 'c.*'))
        ->from($db->quoteName('#__content', 'a'))
        ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')')
        ->join('INNER', $db->quoteName('#__frank', 'c') . ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('c.id') . ')');

    // Se è stato fornito un countrycode, aggiungi una clausola WHERE per filtrare i risultati
    if ($countrycode !== null) {
        $query->where($db->quoteName('b.jr_restrictedcountries') . ' NOT LIKE ' . $db->quote('%' . $db->escape($countrycode, true) . '%', false));
    }

    // Se cofm è impostato su 'yes', aggiungi un'altra clausola WHERE
    if ($cofm === 'yes') {
        $query->where('MONTH(' . $db->quoteName('b.jr_cofmdate') . ') = MONTH(CURDATE())');
    }
    
    // Se featured è impostato su 'yes', aggiungi un'altra clausola WHERE
        if ($featured === 'yes') {
        $query->where($db->quoteName('b.featured') . ' = 1');
    }
    
        // Se noclosed è impostato su 'yes', aggiungi un'altra clausola WHERE
    if ($noclosed === 'yes') {
        $query->where($db->quoteName('b.jr_closed') . ' != ' . $db->quote('*yes*'));
}
    // Se è stato fornito un voto, aggiungi un'altra clausola WHERE
if ($vote !== null && is_array($vote)) {
    $operator = $vote['operator'];
    $value = $vote['value'];

    if (in_array($operator, ['>=', '<=', '>', '<', '='])) {
        $query->where($db->quoteName('c.frankvalue') . ' ' . $operator . ' ' . $db->quote($value));
    }
}

    
    // Se sono stati forniti un campo personalizzato e dei valori, aggiungi una clausola WHERE per filtrare i risultati
    if ($customField !== null && $values !== null && is_array($values)) {
        $wheres = [];
        foreach ($values as $value) {
            $wheres[] = $db->quoteName('b.' . $customField) . ' LIKE ' . $db->quote('%' . $db->escape($value, true) . '%', false);
        }
        if (!empty($wheres)) {
            $query->where('(' . implode(' OR ', $wheres) . ')');
        }
    }


// Se è stato fornito un ordine, ordina i risultati di conseguenza
if (isset($params['order'])) {
    if (is_array($params['order'])) {
        foreach ($params['order'] as $order) {
            if (isset($order['field']) && isset($order['direction'])) {
                if ($order['field'] === 'rank') {
                    $field = $db->quoteName('c.rank');
                    $query->where($db->quoteName('c.rank') . ' != 0');  // Aggiungi il filtro qui
                } elseif ($order['field'] === 'created') {
                    $field = $db->quoteName('a.created');
                } else {
                    continue;  // Skip if the field name is not recognized
                }

                $direction = strtoupper($order['direction']) === 'DESC' ? 'DESC' : 'ASC';
                $query->order($field . ' ' . $direction);
            }
        }
    } else {
        if ($params['order'] === 'rank') {
            $query->order($db->quoteName('c.rank') . ' DESC');
            $query->where($db->quoteName('c.rank') . ' != 0');  // Aggiungi il filtro qui
        } elseif ($params['order'] === 'created') {
            $query->order($db->quoteName('a.created') . ' DESC');
        } elseif ($params['order'] === 'created_asc') {
            $query->order($db->quoteName('a.created') . ' ASC');
        } elseif ($params['order'] === 'random') {
            $query->order('RAND()');
        }
    }
}



    // Se è stato fornito un limite, limita il numero di risultati restituiti
    if ($limit !== null) {
        $limits = explode(',', $limit);
        if (count($limits) == 2) {
            $db->setQuery($query, (int)$limits[0], (int)$limits[1]);
        }
    } else {
        $db->setQuery($query);
    }
// echo $query->dump();
    $results = $db->loadAssocList();

    // Calcola i valori normalizzati e le percentuali per ogni casino
    foreach ($results as &$result) {
        $maxValue = 10;

        $fvalueNormalized = min($result['fvalue'], $maxValue);
        $rvalueNormalized = min($result['rvalue'], $maxValue);

        $fvaluePercentage = ($fvalueNormalized / $maxValue) * 100;
        $rvaluePercentage = ($rvalueNormalized / $maxValue) * 100;

        $fvalueContributionPercentage = ($fvalueNormalized / ($fvalueNormalized + $rvalueNormalized)) * 100;
        $rvalueContributionPercentage = ($rvalueNormalized / ($fvalueNormalized + $rvalueNormalized)) * 100;

        $fvalueContributionPercentage = round($fvalueContributionPercentage, 2);
        $rvalueContributionPercentage = round($rvalueContributionPercentage, 2);

        $result['fvalueContributionPercentage'] = $fvalueContributionPercentage;
        $result['rvalueContributionPercentage'] = $rvalueContributionPercentage;
        
        $id = $result['contentid'];

    $id = $result['contentid'];  // Sostituisci con il nome corretto del campo

    $result['reviewurl'] = "index.php?option=com_content&Itemid=26&catid=8&id=$id&lang=en&view=article";


    }
    unset($result);  // important to unset reference when done

    // Verifica se ci sono errori nella query
    if ($error = $db->getErrorMsg()) {
        throw new Exception('Problem with the query: ' . $error, 500);
    }

    // Converte i risultati in formato JSON
    $json = json_encode($results);

    if ($json === false) {
        throw new Exception('Failed to convert results to JSON: ' . json_last_error_msg(), 500);
    }

    return $json;
}

function get_api_data_bonus($params = []) {
    require_once(JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'bonustypesarray.php');
    require(JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'currenciesarray.php');
    require(JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'bonuslabelsarray.php');
    require(JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'countriesarray.php');

    // Ottieni l'oggetto database
    $db = JFactory::getDBO();

    // Crea una nuova query
    $query = $db->getQuery(true);

    // Imposta la query SQL base
    $query->select(array('a.*', 'b.*'))
        ->from($db->quoteName('#__bonuses', 'a'))
        ->join('INNER', $db->quoteName('#__jreviews_content', 'b') . ' ON (' . $db->quoteName('a.cid') . ' = ' . $db->quoteName('b.contentid') . ')')
        ->where($db->quoteName('b.jr_closed') . ' LIKE \'\'')
        ->where($db->quoteName('b.jr_suspended') . ' LIKE \'\'')
        ->where($db->quoteName('b.jr_blacklisted') . ' LIKE \'\'')
        ->where($db->quoteName('a.bonus') . ' > 0')
        ->where($db->quoteName('a.status') . ' NOT LIKE "pending"');

    // Assegna i parametri a delle variabili, se presenti
    $status = isset($params['status']) ? $params['status'] : null;
    $countrycode = isset($params['countrycode']) ? $params['countrycode'] : null;
    $order = isset($params['order']) ? $params['order'] : null;
    $limit = isset($params['limit']) ? $params['limit'] : null;
    $exclusive = isset($params['exclusive']) ? $params['exclusive'] : null;
    $type = isset($params['type']) ? $params['type'] : null;
    $subtype = isset($params['subtype']) ? $params['subtype'] : null;
    $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

    // Aggiungi la clausola WHERE per lo stato, se fornito
    if ($status !== null) {
        $query->where($db->quoteName('a.status') . ' = ' . $db->quote($status));
    }

    // Aggiungi la clausola WHERE per 'exclusive', se fornito
    if ($exclusive !== null) {
        $query->where($db->quoteName('a.exclusive') . ' = ' . $db->quote($exclusive));
    }

    // Aggiungi le clausole ORDER per il countrycode, se fornito
    if ($countrycode !== null) {
        $query->order('CASE WHEN ' . $db->quoteName('b.jr_restrictedcountries') . ' LIKE "%*' . $countrycode . '*%" then 1 else 0 end');
        $query->order('CASE WHEN ' . $db->quoteName('a.country_ok') . ' LIKE "%*' . $countrycode . '*%" OR ' . $db->quoteName('a.country_ok') . ' LIKE "" then 0 else 1 end');
        $query->order('CASE WHEN ' . $db->quoteName('a.country_no') . ' NOT LIKE "%*' . $countrycode . '*%" OR ' . $db->quoteName('a.country_no') . ' LIKE "" then 0 else 1 end');
    }

    $query->order('CASE WHEN ' . $db->quoteName('a.status') . ' LIKE "expired" then 1 else 0 end');

    // Aggiungi la clausola ORDER per l'ordine, se fornito
    if ($order !== null && is_array($order)) {
        $field = isset($order[0]) ? $order[0] : null;
        $orderType = isset($order[1]) ? $order[1] : null;

        if ($field !== null && $orderType !== null) {
            if (strtoupper($orderType) === 'RAND') {
                $query->order('RAND()');
            } else {
                $query->order($db->quoteName('a.' . $field) . ' ' . ($orderType === 'DESC' ? 'DESC' : 'ASC'));
            }
        }
    }

    // Aggiungi la clausola WHERE per type, se fornito
    if ($type !== null) {
        $query->where($db->quoteName('a.type') . ' = ' . $db->quote($type));
    }

    // Aggiungi la clausola WHERE per type e subtype, se forniti
    if (isset($type) && $type == 'fs' && isset($subtype) && $subtype == 'nd') {
        $query->where($db->quoteName('a.type') . ' LIKE ' . $db->quote($type) . ' AND (' . $db->quoteName('a.min_dep') . ' = "" OR ' . $db->quoteName('a.min_dep') . ' IS NULL)');
    }

    if (isset($type) && $type == 'fs' && isset($subtype) && $subtype == 'dr') {
        $query->where($db->quoteName('a.type') . ' LIKE ' . $db->quote($type));
        $query->where($db->quoteName('a.min_dep') . ' > 0');
    }

    // Imposta la query SQL nel database con il limite e l'offset
    if ($limit !== null) {
        $db->setQuery($query, $offset, (int)$limit);
    } else {
        $db->setQuery($query);
    }

    // Esegui la query
    $results = $db->loadAssocList();
    
    //Elaborazione dei Risultati
    foreach($results as $index => $result) {
        $type = $result['type'];
        $bonus = $result['bonus'];
        $maxbonus = $result['max_bonus'];
        $brand = $result['brand'];
        $game = $result['game'];
        $label = $result['label'];
        $cur = $result['currency'];
        $curr = (empty($cur) ? '$' : $currency[$cur]);
        $mindep = (empty($result['min_dep']) ? 'No Deposit Required' : 'Minimum deposit '.$curr.''.$result['min_dep'].'.');
        $code = (empty($result['code']) ? 'Not Required' : $result['code']);
        $wr = ($result['wr_meth'] == 'b' ? $result['wr'].'X(B)' : $result['wr'].'X(B+D)');
        $cid = $result['cid'];
        $bid = $result['bid'];
        
        $codecard = $result['code'];

if(empty($codecard )) {
    $codecard  = "Not Required";
}

        if($result['link'] === NULL || $result['link'] == '') {
            $resultPlay = $result['jr_gourl'];
        }else{
            $resultPlay = JRoute::_('index.php?option=com_bonuses&view=ads&cid='.$cid.'&bid='.$bid.'&tmpl=component');
        }
        
        if (!empty($closed)) 
			        
			        {
			    
			    
			   $btngetbonus = '<p class="btn btn-base btn-gray-dark mt-auto w-100">'.$result['brand'].' is closed</p>';
			   
			   }
			   
			  elseif (fnmatch('*susp*', $result['jr_suspended']) && empty($closed)) {
			
               $btngetbonus = '<p class="btn btn-base btn-gray-dark mt-auto w-100">Suspended</p>';

			
} elseif (fnmatch('*bl*', $result['jr_blacklisted']) && empty($closed)) {
			
			$btngetbonus = '<p class="btn btn-base btn-gray-dark mt-auto w-100">'.$result['brand'].' is blacklisted</p>';
}

elseif($result['status'] == 'expired' && empty($closed)) {
			   
			$btngetbonus = '<p class="btn btn-base btn-gray-dark mt-auto w-100">Offer Expired</p>';
			   
			            			        }

elseif (fnmatch('*'.$countrycode.'*', $result['jr_restrictedcountries'])) {

			$btngetbonus = '<p class="btn btn-base btn-gray-dark mt-auto w-100">No '.$countries_array[strtoupper($countrycode)].'</p>';

} elseif (($result['country_no'] != '' && fnmatch('*'.$countrycode.'*', $result['country_no'])) || ($result['country_ok'] != '' && !fnmatch('*'.$countrycode.'*', $result['country_ok']))) {

			$btngetbonus = '<p class="btn btn-base btn-gray-dark mt-auto w-100">Not available in '.$countries_array[strtoupper($countrycode)].'</p>';

} elseif (fnmatch('*no-restrictions*', $result['jr_restrictedcountries'])) {


           $btngetbonus = '<a href="'.$resultPlay.'" class="btn btn-base btn-green w-100">Get Bonus</a>';


} else {

          $btngetbonus = '<a href="'.$resultPlay.'" class="btn btn-base btn-green w-100">Get Bonus</a>';
}

        if ($countrycode === 'gb' && $type == 'fs' && $result['min_dep'] !== '') {
            $label = 'Extra Spins';
            $shlabel[$type] = 'Extra Spins';
        } else{
            $label = $result['label'];
            $shlabel[$type] = $shlabel[$type];
        }

        if($type == 'nd' || $type == 'fp' || $type == 'fc') {
            $bonus = $curr.''.$bonus;
        } elseif ($type == 'fs') {
            $bonus = $bonus;
        } else {
            $bonus = $bonus.'%';
        }

        if($type == 'nd') {
            $titletag = $bonus.' '.$label;
            $desc = 'Get '.$bonus.' '.strtolower($label).' at '.$brand.'. '.$code.'. Wagering requirements '.$wr.'.';
        } elseif ($type == 'fp') {
            $titletag = $brand.' '.$bonus.' - '.$result['fp_time'].' '.$fptimeden.' '.$label.''.$statustitle;
        } elseif ($type == 'fs') {
            $titletag = $bonus.' '.$label.' '.(empty($game) ? '': 'on '.$game);
            $desc = 'Get '.$bonus.' '.strtolower($label).' '.(empty($game) ? '': 'on '.$game).' at '.$brand.'. '.$mindep.' '.$code.'. Wagering requirements '.$wr.'.';
        } else {
            $titletag = $bonus.' '.$label.' '.(empty($maxbonus) ? '': 'up to '.$curr.''.$maxbonus);
            $desc = 'Get '.$bonus.' '.strtolower($label).' '.(empty($maxbonus) ? '': 'up to '.$curr.''.$maxbonus).' at '.$brand.'. '.$mindep.' '.$code.'. Wagering requirements '.$wr.'.';
        }
        
        $bonusdeturl = JRoute::_('index.php?option=com_bonuses&bid='.$bid.'&cid='.$cid.'&lang=en&type='.$type.'&view=details');
        
$queryrel = $db->getQuery(true);

$queryrel
    ->select($db->quoteName(array('bonus', 'label','type', 'game','bid','related','currency')))
    ->from($db->quoteName('#__bonuses'))
    ->where($db->quoteName('related') . ' = '. $db->quote($result['bid']))
    ->where($db->quoteName('status') . ' LIKE "active"');
    
$db->setQuery($queryrel);

$related = $db->loadAssoc();

if ($countrycode === 'gb' && $related['type'] == 'fs' && $related['min_dep'] !== '') {
    
$related['label'] = 'Extra Spins';

} else{
    $related['label'] = $related['label'];
}

if ($countrycode === 'gb' && $related['type'] == 'fc') {
    
$related['label'] = 'Extra Cash';
} else{
    $related['label'] = $related['label'];
}

if($related['type'] == 'fs') {
$relcurr = '';
} else {
$relcurr = $currency[$related['currency']];
}

if($result['bid'] == $related['related']) {

$relatedtext = ' + '.$relcurr.''.$related['bonus'].' '.$related['label'].' '.($related['game']==='' ? ' ' : ' on '. $related['game']).'';

$relatedrawtext = '+ '.$relcurr.''.$related['bonus'].' '.$related['label'].' '.($related['game']==='' ? ' ' : ' on '. $related['game']).'';

$relatedtextcard = ' + '.$relcurr.''.$related['bonus'].' '.$related['label'];


} else {

$relatedtext = '';
$relatedtextcard = '';

}

        if ($type == 'fs' && (empty($result['min_dep']) || $result['min_dep'] == 0)) {
    $labelcard = "No Deposit Required";
} else {
    $labelcard = $label;
}

$excbadge = '';
if ($result['exclusive'] == 'yes') {
    $excbadge = '<p class="bonus-exc px-md-15 px-sm-10 px-15 b-1 b-green text-green position-absolute rounded-pill">
        <strong class="d-lg-block d-md-none">Exclusive</strong>
        <strong class="d-lg-none d-md-block d-none">Exc.</strong>
    </p>';
}


$titletagcard = $titletag.$relatedtextcard;
$titletag = $titletag.$relatedtext;

//badge expiration
$endDate = new DateTime($result['end']);
$now = new DateTime(); // Oggi

$interval = $now->diff($endDate); // Ottieni la differenza tra le due date

if ($interval->invert == 1) {
    // La data di fine è già passata, fai qualcosa
} else {
    $remainingDays = $interval->days; // Ottieni il numero di giorni di differenza

    if ($remainingDays <= 3) {
        // Fai qualcosa se mancano meno di 3 giorni
    } else {
        // Fai qualcos'altro se mancano più di 3 giorni
    }
}


        // Aggiungi il titolo del bonus alla lista di output

        $results[$index]['BonusTitle'] = $titletag;
        $results[$index]['BonusTitleCard'] = $titletagcard; 
        $results[$index]['BonusUrl'] = $bonusdeturl;
        $results[$index]['BonusPlay'] = $btngetbonus;
        $results[$index]['Wager'] = $wr;
        $results[$index]['CodeCard'] = $codecard;
        $results[$index]['LabelCard'] = $labelcard;
        $results[$index]['ExcBadge'] = $excbadge;
    }


    // Verifica se ci sono errori nella query
    if ($error = $db->getErrorMsg()) {
        throw new Exception('Problem with the query: ' . $error, 500);
    }

    // Converte i risultati in formato JSON
    $json = json_encode($results);

    // Restituisci i dati JSON
    return $json;
}