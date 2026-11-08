<?php

namespace App\Models;

use Database\Factories\ProductBarcodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One barcode attached to a product. A product can have any number of these.
 *
 * `product_barcodes` is the one barcode table, shared by the WMS products page,
 * the store back office's product form, the Otajer import, the storefront and
 * the scan lookups. It has no unique index (the inherited data already holds
 * values on several products), so a new value's uniqueness is checked by the
 * application (App\Rules\NewProductBarcode) and existing duplicates are listed
 * by `products:barcode-conflicts`. The former WMS table `wms_product_barcodes`
 * was copied in and is no longer read. See .ai/rules/shared-database.md.
 *
 * @property int $id
 * @property int $product_id
 * @property string $barcode
 */
#[Fillable(['product_id', 'barcode'])]
#[Table('product_barcodes')]
class ProductBarcode extends Model
{
    /** @use HasFactory<ProductBarcodeFactory> */
    use HasFactory;

    /**
     * The rows the store edit form shows: one per distinct trimmed value (the
     * first, so the oldest of the loaded relation, of a value a product holds
     * twice), blanks left out. A legacy duplicate on one product therefore
     * cannot fail the form's `distinct` rule when posted back.
     *
     * @param  Collection<int, self>  $barcodes
     * @return Collection<int, self>
     */
    public static function distinctValues(Collection $barcodes): Collection
    {
        return $barcodes
            ->reject(fn (self $barcode): bool => trim($barcode->barcode) === '')
            ->unique(fn (self $barcode): string => trim($barcode->barcode))
            ->values();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
