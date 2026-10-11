<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $with = ['user'];

    /** @return BelongsTo<StoreUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }

    /** @return BelongsTo<SellerPackage, $this> */
    public function seller_package(): BelongsTo
    {
        return $this->belongsTo(SellerPackage::class);
    }

    /** @return HasMany<FollowSeller, $this> */
    public function followers(): HasMany
    {
        return $this->hasMany(FollowSeller::class);
    }
}
