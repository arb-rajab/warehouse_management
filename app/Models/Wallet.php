<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    /** @return BelongsTo<StoreUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }
}
