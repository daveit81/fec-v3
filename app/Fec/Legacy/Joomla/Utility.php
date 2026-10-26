<?php

namespace App\Fec\Legacy\Joomla;

/** JUtility::getToken() of Joomla 2.5: the session form token. */
class Utility
{
    public static function getToken(bool $forceNew = false): string
    {
        return Session::getFormToken($forceNew);
    }
}
