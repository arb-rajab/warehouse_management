<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsImport;
use Illuminate\Support\Facades\DB;

/** @return array<string, mixed> */
function importModelRow(array $overrides = []): array
{
    return [
        'name' => 'تمور مستوردة', 'description' => '<p>تمور طازجة</p><script>bad()</script>',
        'category_id' => Category::forceCreate(['name' => 'Food'])->id, 'brand_id' => null,
        'video_provider' => null, 'video_link' => null, 'tags' => 'تمور',
        'unit_price' => 5.5, 'unit' => 'صندوق', 'meta_title' => 'تمور', 'meta_description' => 'طازجة',
        'slug' => 'dates', 'thumbnail_img' => null, 'photos' => null, 'current_stock' => 7, 'sku' => 'AR-DATES',
        ...$overrides,
    ];
}

test('the shared importer preserves seller ownership and translated data without modifying existing products', function () {
    $seller = createStoreUser('seller');
    $other = createStoreProduct(['name' => 'Existing dates']);
    DB::table('product_translations')->insert(['product_id' => $other->id, 'lang' => 'ar', 'name' => 'اسم قائم']);
    $uploads = DB::table('uploads')->count();
    actingAsStoreUser($seller);
    Auth::shouldUse('store');
    $importer = new ProductsImport;
    $importer->collection(collect([importModelRow()]));
    $product = Product::query()->where('name', 'تمور مستوردة')->firstOrFail();
    expect(session()->has('flash_notification'))->toBeTrue();
    expect($product->user_id)->toBe($seller->id)->and($product->getAttribute('added_by'))->toBe('seller')
        ->and($product->getAttribute('description'))->toBe('<p>تمور طازجة</p>')
        ->and($product->getAttribute('tags'))->toBe('تمور')
        ->and($product->getAttribute('unit'))->toBe('صندوق')
        ->and($product->getAttribute('meta_title'))->toBe('تمور')
        ->and($product->getAttribute('meta_description'))->toBe('طازجة')
        ->and($other->fresh()->name)->toBe('Existing dates');
    $this->assertDatabaseHas('product_translations', ['product_id' => $other->id, 'lang' => 'ar', 'name' => 'اسم قائم']);
    $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'qty' => 7, 'price' => 5.5]);
    expect(DB::table('uploads')->count())->toBe($uploads);
});

test('optional empty importer images do not create bogus upload rows', function () {
    $importer = new ProductsImport;
    expect($importer->downloadThumbnail(null))->toBeNull()->and($importer->downloadThumbnail(''))->toBeNull()
        ->and($importer->downloadGalleryImages(null))->toBeNull()->and($importer->downloadGalleryImages(''))->toBeNull();
    expect(DB::table('uploads')->count())->toBe(0);
});
