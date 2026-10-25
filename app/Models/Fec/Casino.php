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

    /*
    | JReviews stores a ticked checkbox as its option value wrapped in
    | asterisks ("*yes*", "*susp*", "*bl*") and an unticked one as an empty
    | string (probe of 25 Oct 2026: jr_closed 376, jr_suspended 123,
    | jr_blacklisted 29). A flag is therefore "set" when not empty.
    */
    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->whereRaw("TRIM(COALESCE(jr_blacklisted, '')) <> ''");
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereRaw("TRIM(COALESCE(jr_closed, '')) <> ''");
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->whereRaw("TRIM(COALESCE(jr_suspended, '')) <> ''");
    }

    public function isBlacklisted(): bool
    {
        return trim((string) $this->jr_blacklisted) !== '';
    }

    public function isClosed(): bool
    {
        return trim((string) $this->jr_closed) !== '';
    }

    public function isSuspended(): bool
    {
        return trim((string) $this->jr_suspended) !== '';
    }
}
