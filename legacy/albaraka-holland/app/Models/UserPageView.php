<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPageView extends Model
{
    //use hasFactory;

    protected $fillable = ['user_id', 'viewable_id', 'viewable_type', 'viewed_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function viewable()
    {
        return $this->morphTo();
    }
}
