<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Builder;

/**
 * jos_casino_authors: the editorial team (com_authors). Our own table.
 * joomla_user_id links the persona to the Joomla login during the convivenza.
 */
class Author extends FecModel
{
    protected $table = 'casino_authors';

    protected $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'joined_year' => 'integer',
        'joomla_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }
}
