<?php

//define constant
defined('_JEXEC') or die('Restricted access');




$filtername = array(
        "exclusive" => array("yes" => "exclusive deals", "no" => "regular promotions"),
        "code" => array("required" => "code required", "not-required" => "code not required"),
        "cashable" => array("yes" => "cashable", "no" => "not cashable"),
        "wr" => array("meth" => "b", "no" => "not cashable"),
        "max-bonus" => array("name" => "max bonus", "no" => "not cashable")
);