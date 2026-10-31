<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A category pinned to the mobile app's home screen.
 *
 * @property int $id
 * @property int $category_id
 */
class HomeCategory extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
