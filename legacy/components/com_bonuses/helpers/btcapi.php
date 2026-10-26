<?php
//define constant
defined('_JEXEC') or die('Restricted access'); 
//create application


  $url = "https://bitpay.com/api/rates";
  $jsonbtc = json_decode(file_get_contents($url));
  $dollar = $btc = 0;
   
   $btcusd = $jsonbtc['2']->rate;
   $btceur = $jsonbtc['3']->rate;
   ?>