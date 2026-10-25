<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * jos_bonuses: the bonus catalogue (9.1k rows, ~2.5k live). Our own table.
 * Key columns: bid (pk), cid (casino = jreviews_content.contentid), type,
 * currency, bonus, code, wr/wr_meth, max_cashout(_kind), country_ok/no,
 * added/modified/start/end, link, tc, status, *_state (announcements).
 */
class Bonus extends FecModel
{
    protected $table = 'bonuses';

    protected $primaryKey = 'bid';

    protected $casts = [
        'bid' => 'integer',
        'cid' => 'integer',
        'author_id' => 'integer',
        'bonus' => 'integer',
        'val_spin' => 'float',
        'max_bonus' => 'float',
        'min_dep' => 'float',
        'max_dep' => 'float',
        'min_cashout' => 'float',
        'max_cashout' => 'float',
        'wr' => 'integer',
        'max_bet' => 'float',
        'added' => 'datetime',
        'modified' => 'datetime',
        'start' => 'datetime',
        'end' => 'datetime',
        'tweeted_at' => 'datetime',
    ];

    /** Bonuses the site treats as live (see config fec.bonus.active_where). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw(config('fec.bonus.active_where'));
    }

    public function casino(): BelongsTo
    {
        return $this->belongsTo(Casino::class, 'cid', 'contentid');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(BonusClick::class, 'bid', 'bid');
    }

    /** Dashboard editor URL, as used by the desk and by Desk Lint reports. */
    public function editorUrl(): string
    {
        return sprintf(config('fec.bonus.editor_url'), $this->bid);
    }
}
