<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JComponentHelper::getParams('com_x'): the component options that Joomla
 * kept in #__extensions.params. v3 keeps them in config/fec.php under
 * legacy.params.<com_x> (empty = Joomla defaults, as on the live site).
 */
class ComponentHelper
{
    public static function getParams(string $option, bool $strict = false): Registry
    {
        return new Registry((array) config('fec.legacy.params.'.$option, []));
    }

    public static function isEnabled(string $option, bool $strict = false): bool
    {
        return true;
    }

    public static function getComponent(string $option, bool $strict = false): object
    {
        return (object) ['id' => 0, 'option' => $option, 'params' => self::getParams($option), 'enabled' => 1];
    }
}
