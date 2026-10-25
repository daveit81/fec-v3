<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** jos_fec_bonus_package_legs: one deposit step of a welcome package (pct, cap, wr, spins). */
class BonusPackageLeg extends FecModel
{
    protected $table = 'fec_bonus_package_legs';

    protected $primaryKey = 'leg_id';

    protected $casts = [
        'leg_id' => 'integer',
        'pkg_id' => 'integer',
        'position' => 'integer',
        'bid' => 'integer',
        'pct' => 'float',
        'cap' => 'float',
        'min_dep' => 'float',
        'max_dep' => 'float',
        'wr' => 'float',
        'spins' => 'integer',
        'spins_wr' => 'float',
        'max_cashout' => 'float',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(BonusPackage::class, 'pkg_id', 'pkg_id');
    }
}
