<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferProduct extends Model
{
    protected $fillable = ['offer_id', 'product_id', 'quantity', 'is_parent_product'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
