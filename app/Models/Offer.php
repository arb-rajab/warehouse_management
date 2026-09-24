<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Offer extends Model
{
    //use hasFactory;
    protected $guarded = [];

    public function base_product()
    {
        return $this->hasOne(OfferProduct::class)->where('is_base_product', 1);
    }
    public function offer_products()
    {
        return $this->hasMany(OfferProduct::class)->where('is_base_product', 0);
    }
    public function users()
    {
        return $this->belongsToMany(User::class, 'offer_user', 'offer_id', 'user_id');
    }

    public function dynamicPopup()
    {
        return $this->hasOne(DynamicPopup::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class)->where('offer_type', 'brand_offer');
    }

    public function user_got_offer()
    {
        $authUser = Auth::user();
        if ($authUser) {
            return $authUser->offers->contains($this);
        } else {
            return false;
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)
            ->where(function ($query) {
                $current_date = now()->timestamp;
                $query->whereNull('start_date')
                    ->orWhere(function ($query) use ($current_date) {
                        $query->where('start_date', '<=', $current_date)
                            ->where('end_date', '>=', $current_date);
                    });
            });
    }

    public function scopeForAuthUser($query, $user = null)
    {
        $authUser = auth()->user();
        if (!$authUser) {
            $authUser = $user;
        }

        if ($authUser) {
            return $query->active()->whereDoesntHave('users', function ($query) use ($authUser) {
                $query->where('user_id', $authUser->id);
            });
        } else {
            // If there's no authenticated user, return an empty query
            return $query->where('id', null);
        }
    }

    public function scopeProductsOffers($query)
    {
        return $query->where('offer_type', 'products_offer');
    }
}
