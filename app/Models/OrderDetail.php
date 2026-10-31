<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    /**
     * Eager loads for PurchaseHistoryItemsCollection: the product's translated
     * name and refundability, and the line's refund request.
     *
     * @var list<string>
     */
    public const array WITH_ITEM_DETAILS = ['product.product_translations', 'refund_request'];

    protected $fillable = [
        'order_id',
        'seller_id',
        'product_id',
        'product_serial',
        'product_matId',
        'product_name',
        'variation',
        'variation_qty',
        'price',
        'original_price',
        'is_price_changed',
        'quantity',
        'tax',
        'shipping_type',
        'shipping_cost',
        'pickup_point_id',
        'product_referral_code',
        'item_notes',
        'earn_point',
        'payment_status',
        'delivery_status',
        'profits',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_serial', 'serial');
    }

    public function pickup_point()
    {
        return $this->belongsTo(PickupPoint::class);
    }

    public function refund_request()
    {
        return $this->hasOne(RefundRequest::class);
    }

}
