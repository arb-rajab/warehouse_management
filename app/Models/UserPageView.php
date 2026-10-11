<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $viewable_id
 * @property string $viewable_type
 * @property int $view_count
 * @property string $last_viewed_at
 */
class UserPageView extends Model
{
    // use hasFactory;

    protected $fillable = ['user_id', 'viewable_id', 'viewable_type', 'viewed_at'];

    public function user()
    {
        return $this->belongsTo(StoreUser::class);
    }

    public function viewable()
    {
        return $this->morphTo();
    }
}
