<?php
/**
 * sh404SEF - SEO extension for Joomla!
 *
 * @author      Yannick Gaultier
 * @copyright   (c) Yannick Gaultier 2014
 * @package     sh404SEF
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @version     4.3.0.1671
 * @date		2014-01-23
 */

defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );

/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

require JPATH_BASE.DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonustypesarray.php';


$sefConfig = & Sh404sefFactory::getConfig();

if(isset($task)) {
if ($dosef == false) return;
$dosef = false;

}

$db = JFactory::getDbo();
$query = $db->getQuery(true);
$query->select($db->quoteName(array('bid', 'label', 'bonus', 'currency', 'type')));
$query->from($db->quoteName('#__bonuses'));
$query->where($db->quoteName('bid')." = ".$db->quote($bid));
 
$db->setQuery($query);
$result = $db->loadAssoc();

$item = JTable::getInstance("content");
$item->load($cid);
$itemurl = $item->get("title");

$shName = shGetComponentPrefix($option);
$title[] = 'bonuses';
$title[] = '';


switch ($view) {

case 'show':
switch($type) {

case 'fs':
$title[] = '/';
$title[] = $bonustype[$type];
switch($subtype) {
case 'nd':
$title[] = '/';
$title[] = 'no-deposit/';
break;
case 'dr':
$title[] = '/';
$title[] = 'deposit-required/';
break;
}
break;

case 'nd':
$title[] = '/';
$title[] = 'no-deposit-casinos';
break;

case 'crypto':
  // URL: /bonuses/crypto
  // Interno: crypto=yes (NON type=crypto)
  $vars['crypto'] = 'yes';
  unset($vars['type']);

  $title[] = '/';
  $title[] = 'crypto';
break;

case 'hr':
$title[] = '/';
$title[] = 'high-roller-bonuses';
break;

case 'sl':
$title[] = '/';
$title[] = 'slots-bonuses';
break;

case 'bj':
$title[] = '/';
$title[] = 'blackjack-bonuses';
break;

case 'vp':
$title[] = '/';
$title[] = 'video-poker-bonuses';
break;

default:
$title[] = '/';
$title[] = $bonustype[$type];
break;
}
break;


case 'details':

switch($type) {

case 'fs':
$currttl = '';
$percttl = '';
break;

case 'fp':
$currttl = '';
$percttl = '';
break;

case 'nd':
case 'fc':
$currttl = $result['currency'];
$percttl = '';
break;

default:
$currttl = '';
$percttl = 'percent';
break;

}


$title[] = '/';
$title[] = $itemurl.'/';
$title[] = $currttl.''.$result['bonus'].''.$percttl.'-'.$result['label'].'-id'.$result['bid'];
break;

case 'add':
// private dashboard - never SEF this, leave it as a raw URL
if ($dosef == false) return;
$dosef = false;
break;

case 'edit':
if ($dosef == false) return;
$dosef = false;
break;

case 'manage':
// private dashboard - never SEF this, leave it as a raw URL
if ($dosef == false) return;
$dosef = false;
break;

case 'clicks':
// private dashboard - never SEF this, leave it as a raw URL
if ($dosef == false) return;
$dosef = false;
break;

case 'home':
// private dashboard overview - never SEF this, raw URL like manage/add
if ($dosef == false) return;
$dosef = false;
break;

case 'wcalc':
// public tool page: /tools/wagering-calculator
$title = array();
$title[] = 'tools';
$title[] = '';
$title[] = '/';
$title[] = 'wagering-calculator';
break;

case 'cmp':
// public tool page: /tools/casino-comparator
$title = array();
$title[] = 'tools';
$title[] = '';
$title[] = '/';
$title[] = 'casino-comparator';
break;

default:
break;

case 'casino':
$title[] = '/';
$title[] = $itemurl.'/';
break;

case 'ads':
$title[] = '/';
$title[] = 'ctr/'.$cid.'/'.$bid.'/';
break;
case 'tc':
$title[] = '/';
$title[] = 'tc/'.$cid.'/'.$bid.'/';
break;

case 'search':

switch($filter) {
case 'software':
case 'exclusive':
case 'cashable':
case 'allowed-countries':
case 'restricted-countries':
case 'code':
$url = $filter.'/'.$fq.'/';
break;

case 'wr':
$url = $filter.'/'.$fq.''.$meth.'/';
break;

case 'currency':
$url = $filter.'/'.$fq.'/';
break;

default:
$url = $filter.'/'.($curr == '' ? 'usd' : $curr).''.$fq.'/';
break;
}

if(isset($filter) && !isset($type) || empty($type)) {
$title[] = '/';
$title[] = $url;

} elseif(isset($type) && isset($filter)) {

switch($type) {

case 'nd':
$title[] = '/';
$title[] = 'no-deposit-casinos/'.$url.'/';
break;

case 'hr':
$title[] = '/';
$title[] = 'high-roller-bonuses/'.$url.'/';
break;

default:
$title[] = '/';
$title[] = $bonustype[$type].'/'.$url.'/';
break;
}
}

break;

}

if (count($title) > 0) $string = sef_404::sefGetLocation($string, $title,null);