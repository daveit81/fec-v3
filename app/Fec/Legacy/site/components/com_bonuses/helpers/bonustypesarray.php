<?php

// Definisci la costante di sicurezza
defined('_JEXEC') or die('Restricted access');


// PORT: getenv(GEOIP_COUNTRY_CODE) used an undefined constant (fatal on PHP 8);
// the variables are file-scope leftovers nobody reads, kept harmless.
$countrycodeup = (string) getenv('GEOIP_COUNTRY_CODE');
$countrycode = strtolower($countrycodeup);
$countryname = isset($countries_array[$countrycodeup]) ? $countries_array[$countrycodeup] : '';

$bonustype = array(
        "nd" => "No Deposit Bonus",
        "nds" => "No Deposit Spins",
        "fs" => "Free Spins",
        "fsx" => "Extra Spins",
        "fp" => "Free Play",
        "wbp" => "Welcome Bonus",
        "su" => "1st Deposit Match",
        "2d" => "2nd Deposit Match",
        "3d" => "3rd Deposit Match",
        "4d" => "4th Deposit Match",
        "5d" => "5th Deposit Match",
        "6d" => "6th Deposit Match",
        "7d" => "7th Deposit Match",
        "8d" => "8th Deposit Match",
        "9d" => "9th Deposit Match",
        "10d" => "10th Deposit Match",
        "hr" => "High Roller Bonus",
        "sl" => "Slots Bonus",
        "bj" => "BlackJack Bonus",
        "vp" => "Video Poker Bonus",
        "rl" => "Reload Bonus",
        "cb" => "CashBack Bonus",
        "rb" => "Rakeback Bonus",
        "db" => "Deposit Bonus",
        "fc" => "Free Cash",
        "mon" => "Monday Bonus",
        "tue" => "Tuesday Bonus",
        "wed" => "Wednesday Bonus",
        "thu" => "Thursday Bonus",
        "fri" => "Friday Bonus",
        "sat" => "Saturday Bonus",
        "sun" => "Sunday Bonus",
        "wb" => "Weekend Bonus",
        "wkb" => "Weekly Bonus",
        "crypto" => "Crypto Bonuses",
        "sweeps" => "Sweeps Casino Bonuses"
);