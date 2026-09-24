<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartOffered extends Model
{
    use SoftDeletes;

    protected $table = 'carts_offered';
    protected $guarded = [];
    protected $fillable = ['address_id', 'price', 'tax', 'shipping_cost', 'discount', 'by_rep', 'for_customer', 'quantity', 'user_id', 'owner_id', 'product_id', 'variation', 'variation_qty', 'item_notes', 'offer_id', 'is_postponed'];


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
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}
