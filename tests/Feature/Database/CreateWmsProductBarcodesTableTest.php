<?php

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The retired WMS barcode table. App\Models\ProductBarcode now reads
 * `product_barcodes`; this table stays, unread, for one release, so its own
 * constraints are tested through the query builder.
 */
function wmsBarcodeRow(int $productId, string $barcode): int
{
    return DB::table('wms_product_barcodes')->insertGetId(['product_id' => $productId, 'barcode' => $barcode, 'created_at' => now(), 'updated_at' => now()]);
}

test('a barcode must be unique across every product, not just within one', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    wmsBarcodeRow($product->id, '1234567890123');

    expect(fn () => wmsBarcodeRow($otherProduct->id, '1234567890123'))->toThrow(QueryException::class)
        ->and(DB::table('wms_product_barcodes')->where('barcode', '1234567890123')->count())->toBe(1);
});

test('deleting a product cascades to its barcodes and leaves other products\' barcodes alone', function () {
    // Pallets restrict a product delete, so this product deliberately has none.
    $product = Product::factory()->create();
    $barcode = wmsBarcodeRow($product->id, 'WMS-CASCADE');

    $survivor = Product::factory()->create();
    $survivorBarcode = wmsBarcodeRow($survivor->id, 'WMS-SURVIVOR');

    $product->delete();

    expect(DB::table('wms_product_barcodes')->where('id', $barcode)->exists())->toBeFalse()
        ->and(DB::table('wms_product_barcodes')->where('id', $survivorBarcode)->exists())->toBeTrue();
});

test('the table still exists, but no model reads it any more', function () {
    expect(Schema::hasTable('wms_product_barcodes'))->toBeTrue()
        ->and((new ProductBarcode)->getTable())->toBe('product_barcodes')
        ->and((new Product)->barcodes()->getRelated()->getTable())->toBe('product_barcodes')
        ->and(collect(glob(app_path('Models/*.php')))->filter(fn (string $file): bool => str_contains((string) file_get_contents($file), "'wms_product_barcodes'"))->all())->toBe([]);
});
