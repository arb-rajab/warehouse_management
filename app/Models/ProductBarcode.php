<?php

namespace App\Models;

use Database\Factories\ProductBarcodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One barcode attached to a product. A product can have any number of these —
 * this app owns the table entirely, since the store-owned `products` has no
 * barcode column of its own. See .ai/rules/shared-database.md.
 *
 * @property int $id
 * @property int $product_id
 * @property string $barcode
 */
#[Fillable(['product_id', 'barcode'])]
class ProductBarcode extends Model
{
    /** @use HasFactory<ProductBarcodeFactory> */
    use HasFactory;

    protected $table = 'wms_product_barcodes';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
