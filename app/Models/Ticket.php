<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @return BelongsTo<StoreUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }

    /** @return HasMany<TicketReply, $this> */
    public function ticketreplies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at', 'desc');
    }
}
