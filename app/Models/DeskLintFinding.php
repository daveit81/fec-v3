<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Persisted Desk Lint finding (framework database). See the migration for the columns. */
class DeskLintFinding extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
        'times_seen' => 'integer',
    ];
}
