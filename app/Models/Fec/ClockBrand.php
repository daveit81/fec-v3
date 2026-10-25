<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** jos_fec_clock_brands: casinos enabled for FXClock (brand_slug pk, casino_id = contentid). */
class ClockBrand extends FecModel
{
    protected $table = 'fec_clock_brands';

    protected $primaryKey = 'brand_slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'casino_id' => 'integer',
        'enabled' => 'boolean',
        'ftd_match_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function casino(): BelongsTo
    {
        return $this->belongsTo(Casino::class, 'casino_id', 'contentid');
    }
}
