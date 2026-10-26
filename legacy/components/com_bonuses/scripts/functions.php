<?php

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

defined('_JEXEC') or die('Restricted access');

$db = JFactory::getDbo();

$queryFields = $db->getQuery(true);
$queryFields->select(array('f.name', 'f.title', 'o.text', 'o.value'))
    ->from($db->quoteName('#__jreviews_fields', 'f'))
    ->join('INNER', $db->quoteName('#__jreviews_fieldoptions', 'o') . ' ON (' . $db->quoteName('f.fieldid') . ' = ' . $db->quoteName('o.fieldid') . ')');

$db->setQuery($queryFields);
$rowsfields = $db->loadAssocList();

$querysoft = $db->getQuery(true);

$querysoft->select(array('text', 'value'))
    ->from($db->quoteName('#__jreviews_fieldoptions'))
    ->where($db->quoteName('fieldid') . ' = 311');

$db->setQuery($querysoft);
$rowsoft = $db->loadAssocList();

function ordinal($number) {
    if ($number) {
        $ends = array('th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th');
        $mod100 = $number % 100;
        return $number . ($mod100 >= 11 && $mod100 <= 13 ? 'th' : $ends[$number % 10]);
    } else {
        return 'NA';
    }
}


function abbrcount($value) 
{
    if ($value == 0) {
        return '0';
    }

    $abbreviations = array(12 => 'T', 9 => 'B', 6 => 'M', 3 => 'K', 0 => '');

    foreach($abbreviations as $exponent => $abbreviation) 
    {
        if($value >= pow(10, $exponent)) 
        {
            return round(floatval($value / pow(10, $exponent)), 1) . $abbreviation;
        }
    }
}

function roundToOneDecimal($number) {
    return round($number, 1);
}

function extractYear($dateString) {
    $timestamp = strtotime($dateString);
    $year = date('Y', $timestamp);

    return $year;
}

function combineArrays($array1, $array2) {
    $newArray = [];

    foreach ($array2 as $value) {
        foreach ($array1 as $item) {
            if ($item['value'] === $value) {
                $newArray[] = ['text' => $item['text'], 'value' => $item['value']];
                break;
            }
        }
    }

    return $newArray;
}

function processSoftware($software, $rowsoft) {
    // Rimuovere il primo e l'ultimo carattere '*' dalla stringa
    $softvalues = trim($software, '*');

    // Creare un array utilizzando '*' come separatore
    $softvaluesarray = explode('*', $softvalues);

    // Combina gli array $rowsoft e $softvaluesarray
    $result = combineArrays($rowsoft, $softvaluesarray);

    // Prendi un indice casuale dall'array $result
    $randomIndex = array_rand($result);

    // Ottieni il testo corrispondente all'indice casuale
    $randomText = $result[$randomIndex]['text'];

    // Calcola il numero di elementi rimanenti
    $remainingCount = count($result) - 1;

    // Restituisci il risultato come stringa
    return $randomText . ' + ' . $remainingCount;
}


function extractFieldName($key) {
    $matches = [];
    preg_match("/^jr_(.+)$/", $key, $matches);

    return isset($matches[1]) ? $matches[1] : null;
}

function getFieldTitles($fieldName, $fieldVariable, $queryResults) {
    $mappedArray = [];

    $options = [];
    foreach ($queryResults as $row) {
        if ($row['name'] == 'jr_' . $fieldName) {
            $options[$row['value']] = $row['text'];
        }
    }

    $fieldValues = explode('*', trim($fieldVariable, '*'));

    foreach ($fieldValues as $value) {
        if (isset($options[$value])) {
            $mappedArray[$value] = $options[$value];
        }
    }

    return $mappedArray;
}

function getTitlesForField($fieldKey, $fieldValue, $queryResults) {
    // Estrai il nome del campo dalla chiave
    $fieldName = extractFieldName($fieldKey);

    // Se non è possibile estrarre il nome del campo, restituisci una stringa vuota
    if ($fieldName === null) {
        return '';
    }

    // Controlla se il valore del campo contiene asterischi
    if (strpos($fieldValue, '*') !== false) {
        $mappedArray = getFieldTitles($fieldName, $fieldValue, $queryResults);
        // Unisci i titoli in una stringa separata da virgole
        $titlesString = implode(', ', $mappedArray);
    } else {
        // Se non ci sono asterischi, restituisci il valore del campo direttamente
        $titlesString = $fieldValue;
    }

    return $titlesString;
}




function get_thumb($thumb) {
$picextr = preg_replace('/[\[{\(].*[\]}\)]/U' , '',strstr($thumb, '|', true));
$pic = preg_replace('/\s/', '', $picextr);
return $pic;
}


function get_text($content) {
$pos=strpos($content, ' ', 300);
$shorten = substr($content,0,$pos );
$contentstr = preg_replace("/<h([1-6]{1})>.*?<\/h\\1>/si", '', $shorten);
$stripped = strip_tags($contentstr);
return $stripped;
}

function render_join_btn($code,$country,$href,$countryname) {
if (fnmatch('*'.$code.'*', $country)) {
$button = '<p class="btn btn-base btn-gray-dark mt-auto w-100">No '.$countryname.'</p>';
} elseif (fnmatch('*no-restrictions*', $country)) {
$button = '<a href="'.$href.'" class="btn btn-base btn-blue w-100" rel="nofollow" target="_blank">Play Now</a>';
} else {
$button = '<a href="'.$href.'" class="btn btn-base btn-blue w-100" rel="nofollow" target="_blank">Play Now</a>';
}
return $button;
}

function render_join_btn_sm($code, $country, $href, $countryname) {
    if (fnmatch('*'.$code.'*', $country)) {
        $button = '<p class="btn btn-disabled"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="25" viewBox="0 0 26 25" style="enable-background:new 0 0 26 25" xml:space="preserve"><path d="M13 0C5.8 0 0 5.6 0 12.5S5.8 25 13 25s13-5.6 13-12.5S20.2 0 13 0zm10 12.5c0 2.1-.7 4.1-2 5.6L7.5 4.6C9.1 3.6 11 3 13 3c5.5 0 10 4.3 10 9.5zm-20 0c0-2.1.7-4.1 2-5.6l13.6 13.6C16.9 21.4 15 22 13 22c-5.5 0-10-4.2-10-9.5z" style="fill:#d1d1d1"/></svg></p>';
    } elseif (fnmatch('*no-restrictions*', $country)) {
        $button = '<button class="btn btn-green" onclick="window.open(\''.$href.'\', \'_blank\')" rel="nofollow">Play</button>';
    } else {
        $button = '<button class="btn btn-green" onclick="window.open(\''.$href.'\', \'_blank\')" rel="nofollow">Play</button>';
    }
    return $button;
}


function render_rev_btn($cid) {
$button = '<a href="'.JRoute::_('index.php?option=com_content&catid=8&id='.$cid.'&lang=en&view=article').'" class="cd-btn secondary">Read More</a>';
return $button;
}

function render_getbonus_btn($href) {
if (fnmatch('*'.$code.'*', $country)) {
$button = '<p class="btn btn-base btn-gray-dark mt-auto w-100">No '.$countryname.'</p>';
} elseif (fnmatch('*no-restrictions*', $country)) {
$button = '<a href="'.$href.'" class="btn btn-green" target="_blank" rel="nofollow">Play</a>';
} else {
$button = '<a href="'.$href.'" class="btn btn-green" target="_blank" rel="nofollow">Play</a>';
}
return $button;
}

function render_getbonus_btn_card($href) {
if (fnmatch('*'.$code.'*', $country)) {
$button = '<p class="btn btn-base btn-gray-dark mt-auto w-100">No '.$countryname.'</p>';
} elseif (fnmatch('*no-restrictions*', $country)) {
$button = '<a href="'.$href.'" class="btn btn-base btn-green w-100">Get Bonus</a>';
} else {
$button = '<a href="'.$href.'" class="btn btn-green" target="_blank" rel="nofollow">Play</a>';
}
return $button;
}


function render_bonusdet_btn($bid,$cid,$type) {
$button = '<a href="'.JRoute::_('index.php?option=com_bonuses&bid='.$bid.'&cid='.$cid.'&lang=en&type='.$type.'&view=details').'" class="cd-btn secondary">Details</a>';
return $button;
}


function resizeAndConvertToWebp($originalImage, $toWidth, $toHeight, $output) {
    // Carica l'immagine originale
    $sourceImage = imagecreatefromjpeg($originalImage);
    
    // Crea una nuova immagine vuota
    $newImage = imagecreatetruecolor($toWidth, $toHeight);
    
    // Ridimensiona l'immagine originale e copiala nella nuova immagine
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $toWidth, $toHeight, imagesx($sourceImage), imagesy($sourceImage));
    
    // Converti l'immagine in WebP e salvala
    imagewebp($newImage, $output, 80); // 80 è la qualità dell'immagine (da 0 a 100)
    
    // Libera la memoria
    imagedestroy($sourceImage);
    imagedestroy($newImage);
}
