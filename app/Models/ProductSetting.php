<?php

namespace App\Models;

use Database\Factories\ProductSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-product operational data this app owns, keyed by the store's product id.
 *
 * Exists because `products` belongs to the store app and has no column for any
 * of this — see .ai/rules/shared-database.md.
 *
 * @property int $product_id
 * @property int $boxes_count
 */
#[Fillable(['product_id', 'boxes_count'])]
class ProductSetting extends Model
{
    /** @use HasFactory<ProductSettingFactory> */
    use HasFactory;

    protected $table = 'wms_product_settings';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
