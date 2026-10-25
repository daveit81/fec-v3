<?php

namespace App\Models\Fec;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * jos_content: Joomla articles (1.8k): casino reviews (catid 8), blog posts,
 * clusters and pages. Custom column author_id (jos_casino_authors.id).
 * Becomes the "articles" table of v3 with the same ids (phase 4).
 */
class Article extends FecModel
{
    protected $table = 'content';

    public const CATEGORY_REVIEWS = 8;

    protected $casts = [
        'id' => 'integer',
        'state' => 'integer',
        'catid' => 'integer',
        'created' => 'datetime',
        'modified' => 'datetime',
        'publish_up' => 'datetime',
        'publish_down' => 'datetime',
        'hits' => 'integer',
        'featured' => 'boolean',
        'author_id' => 'integer',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('state', 1);
    }

    public function scopeReviews(Builder $query): Builder
    {
        return $query->where('catid', self::CATEGORY_REVIEWS);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'catid', 'id');
    }

    public function casino(): HasOne
    {
        return $this->hasOne(Casino::class, 'contentid', 'id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id');
    }
}
