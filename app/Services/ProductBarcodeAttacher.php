<?php

namespace App\Services;

use App\Http\Requests\Admin\StoreProductBarcodeRequest;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Rules\NewProductBarcode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Attaches a new barcode to a product in `product_barcodes`, the one barcode
 * table (see .ai/rules/product-barcodes.md). The table has no unique index, so
 * the WMS rules run again under the shared `NewProductBarcode::locked()` lock
 * right before the insert: two writers adding the same value at once cannot
 * both pass. Shared by the WMS products page and the mobile API.
 */
class ProductBarcodeAttacher
{
    /**
     * @throws ValidationException when the value no longer passes under the lock
     */
    public function attach(Product $product, string $barcode): ProductBarcode
    {
        return NewProductBarcode::locked(function () use ($product, $barcode): ProductBarcode {
            Validator::make(['barcode' => $barcode], StoreProductBarcodeRequest::barcodeRules())->validate();

            return $product->barcodes()->create(['barcode' => $barcode]);
        });
    }

    /**
     * The ids, ascending, of the products other than $product that hold the
     * value as a barcode.
     *
     * @return list<int>
     */
    public function otherHolders(Product $product, string $barcode): array
    {
        return array_values(ProductBarcode::query()
            ->where('barcode', $barcode)
            ->where('product_id', '!=', $product->id)
            ->distinct()
            ->orderBy('product_id')
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }
}
