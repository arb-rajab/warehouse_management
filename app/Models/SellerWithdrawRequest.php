<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read StoreUser|null $user */
class SellerWithdrawRequest extends Model
{
    /** @return BelongsTo<StoreUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
