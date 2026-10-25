<?php

namespace App\Models\Fec;

/** jos_categories: Joomla nested-set categories (reviews 8, blog 154, ...). */
class Category extends FecModel
{
    protected $table = 'categories';

    protected $casts = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'level' => 'integer',
        'published' => 'integer',
    ];
}
