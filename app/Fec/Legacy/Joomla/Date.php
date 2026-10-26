<?php

namespace App\Fec\Legacy\Joomla;

use DateTime;
use DateTimeZone;

/**
 * JDate: a DateTime that prints as MySQL datetime. toSql()/toMySQL()
 * (UTC), toUnix(), toISO8601(), format() and __toString() are what our
 * code calls. Joomla 2.5 stores UTC; the site offset is UTC too.
 */
class Date extends DateTime
{
    public function __construct(mixed $date = 'now', mixed $tz = null)
    {
        $zone = $tz instanceof DateTimeZone ? $tz : new DateTimeZone(is_string($tz) && $tz !== '' ? $tz : 'UTC');
        if (is_numeric($date)) {
            $date = '@'.(int) $date;
        }
        parent::__construct((string) $date, $zone);
        if (is_numeric(ltrim((string) $date, '@'))) {
            $this->setTimezone($zone);
        }
    }

    public function toSql(bool $local = false): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    /** Joomla 1.5/2.5 name of toSql(). */
    public function toMySQL(bool $local = false): string
    {
        return $this->toSql($local);
    }

    public function toUnix(): int
    {
        return $this->getTimestamp();
    }

    public function toISO8601(bool $local = false): string
    {
        return $this->format('c');
    }

    public function toFormat(string $format = '%Y-%m-%d %H:%M:%S'): string
    {
        // strftime-style formats of Joomla 1.5 code, mapped to date() tokens.
        $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%M' => 'i', '%S' => 's', '%y' => 'y', '%B' => 'F', '%b' => 'M', '%A' => 'l', '%a' => 'D', '%e' => 'j'];

        return $this->format(strtr($format, $map));
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}
