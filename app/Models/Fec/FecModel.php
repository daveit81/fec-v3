<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every table of the legacy catalogue (connection "fec",
 * prefix "jos_"). The schema is read as it is: no Laravel timestamps, no
 * mass assignment, string ids where Joomla used them. Writes come with
 * phase 1 and a read-write user; until then every model is read-only.
 */
abstract class FecModel extends Model
{
    protected $connection = 'fec';

    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * Legacy "zero dates" are not dates: normalise them to null on read so
     * that Carbon never sees 0000-00-00 00:00:00.
     */
    protected function asDateTime($value)
    {
        if (is_string($value) && str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return parent::asDateTime($value);
    }
}
