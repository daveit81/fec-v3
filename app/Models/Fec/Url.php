<?php

namespace App\Models\Fec;

/**
 * jos_sh404sef_urls: the SEF registry (26k rows). oldurl is the public path
 * (no leading slash), newurl the Joomla query (index.php?option=...&id=...).
 * Phase 1 imports it into the v3 router as the URL registry: no URL changes.
 */
class Url extends FecModel
{
    protected $table = 'sh404sef_urls';

    protected $casts = [
        'id' => 'integer',
        'cpt' => 'integer',
        'rank' => 'integer',
        'dateadd' => 'date',
    ];
}
