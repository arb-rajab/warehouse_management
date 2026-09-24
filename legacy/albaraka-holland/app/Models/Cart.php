<?php

namespace App\Models;

use App\Models\User;
use App\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{

    use SoftDeletes;

    protected $guarded = [];
    protected $fillable = ['address_id', 'price', 'tax', 'shipping_cost', 'discount', 'product_referral_code', 'coupon_code', 'by_rep', 'for_customer', 'coupon_applied', 'quantity', 'user_id', 'temp_user_id', 'owner_id', 'product_id', 'variation', 'variation_qty', 'item_notes', 'rep_price','is_price_changed', 'is_admin_notified'];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rep_customer()
    {
        return $this->belongsTo(User::class, 'for_customer', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
    public function carts_offer()
    {
        return $this->hasOne(CartOffered::class, 'product_id', 'product_id')->whereColumn('user_id', 'user_id');
    }
}
