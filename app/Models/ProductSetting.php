<?php

namespace App\Models;

use App\Observers\ProductSettingObserver;
use Database\Factories\ProductSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Warehouse operational settings, keyed by the integrated product id.
 *
 * Box counts and minimum-pallet thresholds live here rather than in the
 * inherited store catalogue columns — see .ai/rules/shared-database.md.
 *
 * @property int $product_id
 * @property int $boxes_count
 * @property int|null $minimum_pallets
 */
#[Fillable(['product_id', 'boxes_count', 'minimum_pallets'])]
#[ObservedBy(ProductSettingObserver::class)]
#[Table('wms_product_settings', key: 'product_id', incrementing: false)]
class ProductSetting extends Model
{
    /** @use HasFactory<ProductSettingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
