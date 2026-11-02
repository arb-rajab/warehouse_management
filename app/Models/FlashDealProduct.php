<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashDealProduct extends Model
{
    protected $fillable=['flash_deal_id', 'product_id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Load each deal product with what ProductMiniCollection reads off it.
     */
    public function scopeWithListedProduct($query)
    {
        $query->with(['product' => fn ($product) => $product->with(Product::WITH_STORE_LIST)]);
    }
}
