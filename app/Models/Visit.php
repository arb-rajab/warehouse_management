<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'customer_id');
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'rep_id');
    }

    public function get_last_visit_order_in_same_day(): ?self
    {
        $currentDate = $this->created_at->toDateString();

        return $this->whereDate('created_at', $currentDate)
            ->latest()
            ->first();
    }
}
