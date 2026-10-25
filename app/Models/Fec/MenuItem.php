<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Builder;

/** jos_menu: Joomla menu items; the fec_nav menu becomes the primary navigation partial in phase 1. */
class MenuItem extends FecModel
{
    protected $table = 'menu';

    protected $casts = [
        'id' => 'integer',
        'published' => 'integer',
        'parent_id' => 'integer',
        'level' => 'integer',
        'ordering' => 'integer',
        'lft' => 'integer',
        'rgt' => 'integer',
        'home' => 'integer',
        'client_id' => 'integer',
    ];

    public function scopeSite(Builder $query): Builder
    {
        return $query->where('client_id', 0)->where('published', 1);
    }
}
