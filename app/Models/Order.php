<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `for_customer` is declared here because the migration that adds it
 * (add_legacy_columns_to_store_tables) builds its columns from a map, which
 * Larastan's migration reader can't follow.
 *
 * @property bool $by_rep
 * @property int|null $invoice_number
 * @property string|null $delivery_date
 * @property int|null $for_customer the customer a representative placed the order for
 * @property-read StoreUser|null $customer
 * @property-read StoreUser|null $representative
 */
class Order extends Model
{
    protected $fillable = [
        'discount_percent',
        'indicator',
        'profits',
        'user_id',
        'for_customer',
        'invoice_number',
        'delivery_status',
        'additional_info',
        'shipping_address',
        'company_shipping_address',
        'seller_id',
        'order_from',
        'by_rep',
        'code',
        'date',
        'delivery_date',
        'member_serial',
        'combined_order_id',
        'payment_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'by_rep' => 'boolean',
            'for_customer' => 'integer',
            'profits' => 'float',
            'indicator' => 'float',
        ];
    }

    /**
     * @return HasMany<OrderDetail, $this>
     */
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function refund_requests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }

    /**
     * The customer the order belongs to: whoever the representative placed it
     * for, or the user who placed it. An accessor over the `rep` and `user`
     * relations rather than a relation of its own, which could not pick a
     * foreign key from `by_rep` while eager loading, when the model it is
     * called on is blank.
     *
     * @return Attribute<StoreUser|null, never>
     */
    protected function customer(): Attribute
    {
        return Attribute::get(fn (): ?StoreUser => $this->by_rep ? $this->rep : $this->user);
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'for_customer');
    }

    /**
     * The representative who placed the order, if one did. Accessor for the
     * same reason as `customer`.
     *
     * @return Attribute<StoreUser|null, never>
     */
    protected function representative(): Attribute
    {
        return Attribute::get(fn (): ?StoreUser => $this->by_rep ? $this->user : null);
    }

    public function shop()
    {
        return $this->hasOne(Shop::class, 'user_id', 'seller_id');
    }

    public function pickup_point()
    {
        return $this->belongsTo(PickupPoint::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function club_point()
    {
        return $this->hasMany(ClubPoint::class);
    }

    public function delivery_boy()
    {
        return $this->belongsTo(StoreUser::class, 'assign_delivery_boy', 'id');
    }

    public function proxy_cart_reference_id()
    {
        return $this->hasMany(ProxyPayment::class)->select('order_id', 'reference_id');
    }

    public function get_order_details_profits()
    {
        $total_profits = 0;
        $this->orderDetails->each(function ($order_detail) use (&$total_profits) {
            $total_profits += $order_detail->profits;
        });
        return $total_profits;
    }

    public function get_order_details_original_price()
    {
        $original_price = 0;
        $this->orderDetails->each(function ($order_detail) use (&$original_price) {
            $original_price += $order_detail->original_price;
        });
        return $original_price;
    }
    public function get_order_details_price()
    {
        $price = 0;
        $this->orderDetails->each(function ($order_detail) use (&$price) {
            $price += $order_detail->price;
        });
        return $price;
    }

    // Define the relation with Checkpoint
    /** @return MorphMany<Checkpoint, $this> */
    public function checkpoints(): MorphMany
    {
        return $this->morphMany(Checkpoint::class, 'relationable');
    }

    /**
     * Scope to filter orders without any related checkpoints.
     */
    public function scopeWithoutCheckpoints($query)
    {
        return $query->doesntHave('checkpoints');
    }
}
