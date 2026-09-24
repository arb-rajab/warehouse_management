<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'for_customer',
        'code',
        'date',
        'delivery_date',
        'member_serial',
        'combined_order_id',
        'payment_type'
    ];

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function refund_requests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        if ($this->by_rep) {
            return $this->belongsTo(User::class, 'for_customer', 'id');
        } else {
            return $this->belongsTo(User::class, 'user_id', 'id');
        }
    }

    public function rep()
    {
        return $this->belongsTo(User::class, 'for_customer');
    }

    public function representative()
    {
        if ($this->by_rep) {
            return $this->belongsTo(User::class, 'user_id');
        } else {
            // Returning a dummy relationship instance (e.g., an empty BelongsTo)
            return $this->belongsTo(User::class, 'user_id')->where('id', '=', null);
        }
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

    public function affiliate_log()
    {
        return $this->hasMany(AffiliateLog::class);
    }

    public function club_point()
    {
        return $this->hasMany(ClubPoint::class);
    }

    public function delivery_boy()
    {
        return $this->belongsTo(User::class, 'assign_delivery_boy', 'id');
    }

    public function proxy_cart_reference_id()
    {
        return $this->hasMany(ProxyPayment::class)->select('reference_id');
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
    public function checkpoints()
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
