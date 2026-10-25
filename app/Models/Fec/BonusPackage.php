<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** jos_fec_bonus_packages: FXRate welcome packages (one per casino offer, legs below). */
class BonusPackage extends FecModel
{
    protected $table = 'fec_bonus_packages';

    protected $primaryKey = 'pkg_id';

    protected $casts = [
        'pkg_id' => 'integer',
        'cid' => 'integer',
        'opener_bid' => 'integer',
        'deposits' => 'integer',
        'spins_total' => 'integer',
        'window_days' => 'integer',
        'sequential' => 'boolean',
        'created' => 'datetime',
        'modified' => 'datetime',
    ];

    public function legs(): HasMany
    {
        return $this->hasMany(BonusPackageLeg::class, 'pkg_id', 'pkg_id')->orderBy('position');
    }
}
