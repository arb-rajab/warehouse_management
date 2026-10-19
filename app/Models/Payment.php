<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @return BelongsTo<StoreUser, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'seller_id');
    }
}
