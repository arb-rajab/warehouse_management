<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreProductBarcode extends Model
{
    //use hasFactory;

    /**
     * Renamed from ProductBarcode to avoid colliding with this app's own
     * App\Models\ProductBarcode (see
     * .ai/rules/legacy-albaraka-holland.md section 2) — the table itself
     * stayed `product_barcodes` (see create_product_barcodes_table). Without
     * this, Eloquent's naming convention infers `store_product_barcodes`
     * from the class name instead, a table that doesn't exist.
     *
     * @var string
     */
    protected $table = 'product_barcodes';

    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
