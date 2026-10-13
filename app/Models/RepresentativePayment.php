<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('representative_payments', keyType: 'string')]
class RepresentativePayment extends Model
{
    /** @var list<string> */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @return BelongsTo<StoreUser, $this> */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'rep_id');
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'customer_id');
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'admin_id');
    }
}
