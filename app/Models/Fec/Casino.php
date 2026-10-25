<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * jos_jreviews_content: the casino sheet (900 rows, 158 columns, ~50 live).
 * Kept as is until Joomla is switched off; the Filament form of phase 4 sits
 * on top of it. contentid = jos_content.id of the review article.
 * Status flags are Joomla-style strings ("yes"/"" etc.): see the scopes.
 */
class Casino extends FecModel
{
    protected $table = 'jreviews_content';

    protected $primaryKey = 'contentid';

    protected $casts = [
        'contentid' => 'integer',
        'featured' => 'boolean',
        'jr_established' => 'datetime',
        'jr_closeddate' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'contentid', 'id');
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class, 'cid', 'contentid');
    }

    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->whereIn('jr_blacklisted', self::TRUTHY);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('jr_closed', self::TRUTHY);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->whereIn('jr_suspended', self::TRUTHY);
    }

    /** Values the legacy forms store for a ticked yes/no flag. */
    public const TRUTHY = ['yes', 'Yes', 'YES', '1', 'true'];

    public function isBlacklisted(): bool
    {
        return in_array((string) $this->jr_blacklisted, self::TRUTHY, true);
    }

    public function isClosed(): bool
    {
        return in_array((string) $this->jr_closed, self::TRUTHY, true);
    }
}
