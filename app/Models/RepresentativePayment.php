<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Table('representative_payments', keyType: 'string')]
class RepresentativePayment extends Model
{
    /** The key is a string the app assigns; the database must not overwrite it with an insert id. */
    public $incrementing = false;

    /** @var list<string> */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The primary key is a string the database does not fill in, so a payment
     * created without an id gets a ULID. Existing ids, whatever their form,
     * keep working: only a missing one is generated.
     */
    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (blank($payment->getKey())) {
                $payment->setAttribute($payment->getKeyName(), (string) Str::ulid());
            }
        });
    }

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
