<?php

namespace App\Models\Fec;

/** jos_redirection: sh404SEF 301 rules (3.8k rows), same shape as the URL registry. */
class Redirect extends FecModel
{
    protected $table = 'redirection';

    protected $casts = [
        'id' => 'integer',
        'cpt' => 'integer',
        'rank' => 'integer',
        'dateadd' => 'date',
    ];
}
