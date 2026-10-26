<?php
//define constant
define( '_JEXEC', 1 );

define( 'DS', DIRECTORY_SEPARATOR );
 
//you need to adjust joomla path according to your joomla installation
define( 'JPATH_BASE', $_SERVER[ 'DOCUMENT_ROOT' ] . DS . '' );
 
//include joomla core files
require_once( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
require_once( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );
require_once( JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'factory.php' );


//create application
$mainframe =& JFactory::getApplication('site');

$currency = array(
        'usd' => '$',
	'gbp' => '£',
	'euro' => '€',
	'eur' => '€',
	'cad' => 'CA$',
	'aud' => 'AU$',
	'nzd' => 'NZ$',
	'nok' => 'NOK',
	'sek' => 'SEK',
	'dkk' => 'DKK',
	'kr' => 'KR',
	'btc' => 'BTC',
	'mbtc' => 'mBTC',
	'fun' => 'FUN',
	'usdt' => 'USDT',
	'gc'  => 'GC',   // Gold Coins  (social/sweeps - no monetary value)
    'sc'  => 'SC',   // Sweepstakes Coins (promotional entries, redeemable)
);