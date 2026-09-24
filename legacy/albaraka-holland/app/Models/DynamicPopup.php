<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DynamicPopup extends Model
{
    //use hasFactory;

    protected $guarded = ['id'];

    protected $fillable = [
        'status',
        'title',
        'summary',
        'banner',
        'btn_link',
        'btn_text',
        'btn_text_color',
        'btn_background_color',
        'delay_seconds',
        'offer_id',
        'show_page'
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function scopeForAuthUser($query)
    {
        $authUser = Auth::user();
        if ($authUser) {
            return $query->whereHas('offer', function ($query) use ($authUser) {
                $query->active()->whereDoesntHave('users', function ($query) use ($authUser) {
                    $query->where('user_id', $authUser->id);
                });
            });
        } else {
            // If there's no authenticated user, return an empty query
            return $query->where('id', null);
        }
    }
}
