<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\StoreUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * A product's barcodes (the one `product_barcodes` table, which restricts a
 * product delete) are deleted with the product on the seller area's and the
 * seller API's delete paths, inside one transaction: a delete the database
 * refuses (a warehouse pallet) removes nothing at all.
 */
function barcodeDeletionSellerProduct(StoreUser $seller, int $digital = 0): Product
{
    $product = createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller', 'digital' => $digital, 'thumbnail_img' => null]);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'SELLER-'.$product->id]);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => (string) $product->id]);
    DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'en', 'name' => 'Seller oil']);

    return $product;
}

/** @return array<string, list<mixed>> */
function barcodeDeletionSnapshot(): array
{
    return collect(['products', 'product_translations', 'product_stocks', 'product_barcodes'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

beforeEach(function () {
    Artisan::shouldReceive('call')->andReturn(0);
    // Noise: another seller's product and barcode survive every delete.
    $this->noise = barcodeDeletionSellerProduct(createStoreUser('seller'));
});

dataset('seller delete paths', [
    'seller area, physical' => [0, fn (Product $product, StoreUser $seller) => test()->post("/temp/seller/products/destroy/{$product->id}")],
    'seller area, digital' => [1, fn (Product $product, StoreUser $seller) => test()->post("/temp/seller/digitalproducts/destroy/{$product->id}")],
    'seller API' => [0, fn (Product $product, StoreUser $seller) => test()->withToken(storeApiToken($seller))->getJson("/api/v1/store/seller/product/delete/{$product->id}")->assertJsonPath('result', true)],
]);

test('deleting a seller\'s own product deletes its barcodes and leaves other products\' alone', function (int $digital, Closure $delete) {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $product = barcodeDeletionSellerProduct($seller, $digital);

    $delete($product, $seller);

    expect(Product::find($product->id))->toBeNull()
        ->and(DB::table('product_barcodes')->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(DB::table('product_barcodes')->where('product_id', $this->noise->id)->count())->toBe(2)
        ->and(Product::find($this->noise->id))->not->toBeNull();
})->with('seller delete paths');

test('a delete the database refuses removes nothing, the barcodes included', function (int $digital, Closure $delete) {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $product = barcodeDeletionSellerProduct($seller, $digital);
    Pallet::factory()->create(['product_id' => $product->id]);
    $before = barcodeDeletionSnapshot();
    $this->withoutExceptionHandling();

    expect(fn () => $delete($product, $seller))->toThrow(QueryException::class)
        ->and(barcodeDeletionSnapshot())->toEqual($before);
})->with('seller delete paths');
