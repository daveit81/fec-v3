<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * jos_bonus_clicks: one row per tracked "Visit casino" click written by
 * goto.php (124k rows). Revenue path: goto.php is the first v3 route (phase 1).
 */
class BonusClick extends FecModel
{
    protected $table = 'bonus_clicks';

    protected $casts = [
        'id' => 'integer',
        'cid' => 'integer',
        'bid' => 'integer',
        'is_bot' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class, 'bid', 'bid');
    }
}
