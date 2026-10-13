<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'sender_id');
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'receiver_id');
    }
}
