<?php

use App\Http\Controllers\Api\V2\DigitalProductController as ApiDigitalProductController;
use App\Http\Controllers\DigitalProductController as LegacyDigitalProductController;
use App\Http\Controllers\PurchaseHistoryController;
use App\Http\Controllers\Seller\DigitalProductController as SellerDigitalProductController;
use App\Http\Controllers\Store\Admin\DigitalProductController;
use App\Models\CellStatusLog;
use App\Models\Language;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductTranslation;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->product = createStoreProduct([
        'name' => 'Recipe book', 'slug' => 'recipe-book', 'digital' => 1, 'added_by' => 'admin', 'unit_price' => 1250.5,
        'todays_deal' => 0, 'featured' => 1, 'published' => true, 'thumbnail_img' => null, 'current_stock' => 7,
    ]);
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Recipe book']);
});

function digitalProductAdminSnapshot(): array
{
    return collect(['products', 'product_translations', 'product_stocks', 'wms_product_settings', 'pallets'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

/**
 * The product switches clear the compiled views and the cache, as the shared
 * ProductController endpoints do. Mocked so a test never empties the real
 * compiled-view directory other parallel workers are reading.
 */
function expectDigitalProductCachesCleared(int $times = 1): void
{
    Artisan::shouldReceive('call')->with('view:clear')->times($times)->andReturn(0);
    Artisan::shouldReceive('call')->with('cache:clear')->times($times)->andReturn(0);
}

test('digital product list renders the selected Inertia screen', function () {
    actingAsStoreUser(createStoreStaff('show_digital_products'));
    $this->get(route('digitalproducts.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/DigitalProducts/Index'));
});

test('digital product list refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('digitalproducts.index')), $caller);
})->with('refused store admin callers');

test('digital product list exposes the full narrow row for digital products only, newest first', function () {
    actingAsStoreUser(createStoreStaff('show_digital_products'));
    $seller = createStoreUser('seller');
    $older = createStoreProduct(['name' => 'Older guide', 'digital' => 1, 'added_by' => 'seller', 'user_id' => $seller->id, 'slug' => null, 'unit_price' => 3, 'todays_deal' => 1, 'featured' => 0, 'published' => false, 'thumbnail_img' => null]);
    $physical = createStoreProduct(['name' => 'Recipe book box', 'digital' => 0]);
    backdate($older, '2026-01-01 00:00:00');
    backdate($this->product, '2026-02-01 00:00:00');
    $this->get(route('digitalproducts.index'))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 2)
        ->has('products.data.0', fn (Assert $row) => $row
            ->where('id', $this->product->id)->where('name', 'Recipe book')->where('added_by', 'admin')
            ->where('thumbnail_url', static_asset('assets/img/placeholder.jpg'))->where('unit_price', '1,250.50')
            ->where('todays_deal', false)->where('published', true)->where('featured', true)
            ->where('public_url', route('product', 'recipe-book'))
            ->where('download_token', fn (string $token) => decrypt($token) === $this->product->id))
        ->has('products.data.1', fn (Assert $row) => $row
            ->where('id', $older->id)->where('name', 'Older guide')->where('added_by', 'seller')
            ->where('thumbnail_url', static_asset('assets/img/placeholder.jpg'))->where('unit_price', '3.00')
            ->where('todays_deal', true)->where('published', false)->where('featured', false)
            ->where('public_url', null)->where('download_token', fn (string $token) => decrypt($token) === $older->id))
        ->where('filters', ['search' => null, 'per_page' => 20])->where('defaultLanguage', 'en'));
    expect(array_column($this->get(route('digitalproducts.index'))->inertiaProps('products.data'), 'id'))->not->toContain($physical->id);
});

test('digital product list resolves thumbnails like the legacy uploaded asset helper and localizes names', function () {
    actingAsStoreUser(createStoreStaff('show_digital_products'));
    $thumbnail = Upload::factory()->create(['type' => 'image', 'external_link' => 'https://example.com/book.png', 'file_name' => null]);
    $this->product->forceFill(['thumbnail_img' => $thumbnail->id])->save();
    $missing = createStoreProduct(['name' => 'Missing thumbnail', 'digital' => 1, 'thumbnail_img' => 999999]);
    backdate($missing, '2020-01-01 00:00:00');
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'كتاب الوصفات']);
    $this->withSession(['locale' => 'ar'])->get(route('digitalproducts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('products.data.0.name', 'كتاب الوصفات')->where('products.data.0.thumbnail_url', 'https://example.com/book.png')
        ->where('products.data.1.name', 'Missing thumbnail')->where('products.data.1.thumbnail_url', static_asset('assets/img/placeholder.jpg')));
    $this->get(route('digitalproducts.index', ['search' => 'كتاب']))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('digital product list pages, searches the base name and validates its filters', function () {
    actingAsStoreUser(createStoreStaff('show_digital_products'));
    backdate($this->product, '2026-03-01 00:00:00');
    foreach (range(1, 22) as $i) {
        backdate(createStoreProduct(['name' => sprintf('Noise %02d', $i), 'digital' => 1]), sprintf('2026-01-%02d 00:00:00', $i));
    }
    createStoreProduct(['name' => 'Recipe physical noise', 'digital' => 0]);
    assertPagesWithoutOverlap(fn ($page) => $this->get(route('digitalproducts.index', ['page' => $page])), fn ($response) => array_column($response->inertiaProps('products.data'), 'id'), 23, 20);
    $this->get(route('digitalproducts.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('products.data', 10)
        ->where('products.data.0.id', $this->product->id)->where('products.data.1.name', 'Noise 22')->where('products.data.9.name', 'Noise 14')
        ->where('products.meta.per_page', 10)->where('filters.per_page', 10));
    $this->get(route('digitalproducts.index', ['search' => 'Recipe', 'per_page' => 50]))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 1)->where('products.data.0.id', $this->product->id)->where('filters', ['search' => 'Recipe', 'per_page' => 50]));
    foreach ([11, 0, 1000, 'many'] as $size) {
        $this->getJson(route('digitalproducts.index', ['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
    $this->getJson(route('digitalproducts.index', ['search' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('search');
    $this->getJson(route('digitalproducts.index', ['search' => str_repeat('s', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('digital product deletion keeps removing its translations, stock and product row, and nothing else', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'كتاب الوصفات']);
    $other = createStoreProduct(['name' => 'Other guide', 'digital' => 1]);
    ProductTranslation::create(['product_id' => $other->id, 'lang' => 'en', 'name' => 'Other guide']);
    $this->delete(route('digitalproducts.destroy', [$this->product, 'page' => 2, 'per_page' => 10, 'search' => 'Rec']))
        ->assertRedirect(route('digitalproducts.index', ['page' => 2, 'per_page' => 10, 'search' => 'Rec']));
    $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
    $this->assertDatabaseMissing('product_translations', ['product_id' => $this->product->id]);
    $this->assertDatabaseMissing('product_stocks', ['product_id' => $this->product->id]);
    $this->assertDatabaseMissing('wms_product_settings', ['product_id' => $this->product->id]);
    $this->assertDatabaseHas('products', ['id' => $other->id]);
    $this->assertDatabaseHas('product_translations', ['product_id' => $other->id, 'name' => 'Other guide']);
    $this->assertDatabaseHas('product_stocks', ['product_id' => $other->id]);
});

test('digital product deletion takes DELETE only on its existing route name', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    expect(route('digitalproducts.destroy', 5, false))->toBe('/temp/admin/digitalproducts/destroy/5');
    $before = digitalProductAdminSnapshot();
    $this->get("/temp/admin/digitalproducts/destroy/{$this->product->id}")->assertMethodNotAllowed();
    $this->post("/temp/admin/digitalproducts/destroy/{$this->product->id}")->assertMethodNotAllowed();
    $this->delete("/temp/admin/digitalproducts/{$this->product->id}")->assertMethodNotAllowed();
    expect(digitalProductAdminSnapshot())->toEqual($before);
});

test('digital product deletion changes nothing when a warehouse pallet still references the product', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    Pallet::factory()->create(['product_id' => $this->product->id]);
    $before = digitalProductAdminSnapshot();
    $this->withoutExceptionHandling();
    expect(fn () => $this->delete(route('digitalproducts.destroy', $this->product)))->toThrow(QueryException::class);
    expect(digitalProductAdminSnapshot())->toEqual($before);
});

test('digital product deletion changes nothing when a store barcode still references the product', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    DB::table('product_barcodes')->insert(['product_id' => $this->product->id, 'barcode' => '8710000000001']);
    $before = [...digitalProductAdminSnapshot(), 'product_barcodes' => DB::table('product_barcodes')->get()->toArray()];
    $this->withoutExceptionHandling();
    expect(fn () => $this->delete(route('digitalproducts.destroy', $this->product)))->toThrow(QueryException::class);
    expect([...digitalProductAdminSnapshot(), 'product_barcodes' => DB::table('product_barcodes')->get()->toArray()])->toEqual($before);
});

test('digital product deletion cascades WMS barcodes and settings and keeps cell logs without the product', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    $barcode = ProductBarcode::factory()->create(['product_id' => $this->product->id]);
    $log = CellStatusLog::factory()->create(['product_id' => $this->product->id]);
    $this->delete(route('digitalproducts.destroy', $this->product))->assertRedirect(route('digitalproducts.index'));
    $this->assertDatabaseMissing('wms_product_barcodes', ['id' => $barcode->id]);
    $this->assertDatabaseMissing('wms_product_settings', ['product_id' => $this->product->id]);
    expect(DB::table('cell_status_logs')->where('id', $log->id)->value('product_id'))->toBeNull();
});

test('digital product deletion rolls back every table on persistence failure', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    $before = digitalProductAdminSnapshot();
    Product::deleting(fn () => throw new RuntimeException('Delete failure'));
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->delete(route('digitalproducts.destroy', $this->product)))->toThrow(RuntimeException::class);
        expect(digitalProductAdminSnapshot())->toEqual($before);
    } finally {
        Product::flushEventListeners();
    }
});

test('unpublishing a digital product also unfeatures it and empties its stock, and publishing restocks it', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('product_stocks')->insert(['product_id' => $this->product->id, 'variant' => 'large', 'sku' => 'L', 'price' => 1, 'qty' => 3]);
    $other = createStoreProduct(['digital' => 1, 'featured' => 1, 'current_stock' => 9]);
    expectDigitalProductCachesCleared(2);
    $this->post(route('digitalproducts.published', [$this->product, 'page' => 2, 'search' => 'Rec']), ['status' => 0])
        ->assertRedirect(route('digitalproducts.index', ['page' => 2, 'search' => 'Rec']));
    $product = $this->product->fresh();
    expect($product->published)->toBeFalse()->and($product->getAttribute('featured'))->toBe(0)
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->pluck('qty')->all())->toBe([0, 0]);
    $this->post(route('digitalproducts.published', $this->product), ['status' => '1'])->assertRedirect(route('digitalproducts.index'));
    $product = $this->product->fresh();
    expect($product->published)->toBeTrue()->and($product->getAttribute('featured'))->toBe(0)
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->pluck('qty')->all())->toBe([1000, 1000]);
    expect($other->fresh()->getAttribute('featured'))->toBe(1)->and(DB::table('product_stocks')->where('product_id', $other->id)->value('qty'))->toBe(9);
});

test('the featured and today\'s deal switches write only their own column and keep their cache behavior', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    expectDigitalProductCachesCleared();
    $this->post(route('digitalproducts.featured', [$this->product, 'per_page' => 50]), ['status' => 0])
        ->assertRedirect(route('digitalproducts.index', ['per_page' => 50]));
    expect($this->product->fresh()->getAttribute('featured'))->toBe(0)->and($this->product->fresh()->published)->toBeTrue();
    Cache::put('todays_deal_products', 'stale');
    $this->post(route('digitalproducts.todays_deal', $this->product), ['status' => true])->assertRedirect(route('digitalproducts.index'));
    expect($this->product->fresh()->getAttribute('todays_deal'))->toBe(1)->and(Cache::has('todays_deal_products'))->toBeFalse();
});

foreach (['published', 'featured', 'todays_deal'] as $flag) {
    test("the $flag switch validates its status before any write", function () use ($flag) {
        actingAsStoreUser(createStoreStaff('product_edit'));
        Artisan::shouldReceive('call')->never();
        $before = digitalProductAdminSnapshot();
        foreach ([null, 'yes', 2, ['1']] as $status) {
            $this->postJson(route('digitalproducts.'.$flag, $this->product), ['status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        expect(digitalProductAdminSnapshot())->toEqual($before);
    });
}

test('publishing a seller digital product outside its shop package is refused before anything is written', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $seller = createStoreUser('seller');
    createShopFor($seller, 0);
    $product = createStoreProduct(['digital' => 1, 'added_by' => 'seller', 'user_id' => $seller->id, 'published' => false, 'current_stock' => 0]);
    DB::table('addons')->insert(['name' => 'Seller subscription', 'unique_identifier' => 'seller_subscription', 'activated' => 1]);
    Cache::forget('addons');
    Artisan::shouldReceive('call')->never();
    $before = digitalProductAdminSnapshot();
    $this->post(route('digitalproducts.published', $product), ['status' => 1])->assertSessionHasErrors(['status' => __('messages.store_product_package_limit')]);
    expect(digitalProductAdminSnapshot())->toEqual($before);
    DB::table('shops')->where('user_id', $seller->id)->update(['package_invalid_at' => now()->addMonth()->toDateString(), 'product_upload_limit' => 5]);
    expectDigitalProductCachesCleared();
    $this->post(route('digitalproducts.published', $product), ['status' => 1])->assertSessionHasNoErrors();
    expect($product->fresh()->published)->toBeTrue();
});

test('the digital product row actions refuse anything but a digital product, and missing ones, with a 404', function () {
    actingAsStoreUser(createStoreStaff('delete_digital_product', 'product_edit', 'download_digital_product'));
    $physical = createStoreProduct(['digital' => 0]);
    Artisan::shouldReceive('call')->never();
    $before = digitalProductAdminSnapshot();
    foreach ([$physical->id, 999999] as $id) {
        $this->delete(route('digitalproducts.destroy', $id))->assertNotFound();
        foreach (['published', 'featured', 'todays_deal'] as $flag) {
            $this->post(route('digitalproducts.'.$flag, $id), ['status' => 0])->assertNotFound();
        }
        $this->get(route('digitalproducts.download', encrypt($id)))->assertNotFound();
    }
    expect(digitalProductAdminSnapshot())->toEqual($before);
});

foreach (['destroy', 'published', 'featured', 'todays_deal'] as $action) {
    test("digital product $action refuses unauthorized callers without changes", function (string $caller) use ($action) {
        $before = digitalProductAdminSnapshot();
        actingAsRefusedStoreCaller($caller);
        $response = $action === 'destroy'
            ? $this->delete(route('digitalproducts.destroy', $this->product))
            : $this->post(route('digitalproducts.'.$action, $this->product), ['status' => 0]);
        assertStoreCallerRefused($response, $caller);
        expect(digitalProductAdminSnapshot())->toEqual($before);
    })->with('refused store admin callers');
}

test('digital product download refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('digitalproducts.download', encrypt($this->product->id))), $caller);
})->with('refused store admin callers');

test('each digital product permission authorizes only its own list actions', function () {
    Artisan::shouldReceive('call')->never();
    $before = digitalProductAdminSnapshot();
    $download = route('digitalproducts.download', encrypt($this->product->id));
    actingAsStoreUser(createStoreStaff('show_digital_products', 'add_digital_product', 'edit_digital_product'));
    $this->delete(route('digitalproducts.destroy', $this->product))->assertForbidden();
    $this->post(route('digitalproducts.published', $this->product), ['status' => 0])->assertForbidden();
    $this->get($download)->assertForbidden();
    actingAsStoreUser(createStoreStaff('product_edit', 'delete_digital_product', 'download_digital_product'));
    $this->get(route('digitalproducts.index'))->assertForbidden();
    actingAsStoreUser(createStoreStaff('delete_digital_product'));
    $this->post(route('digitalproducts.featured', $this->product), ['status' => 0])->assertForbidden();
    $this->post(route('digitalproducts.todays_deal', $this->product), ['status' => 1])->assertForbidden();
    actingAsStoreUser(createStoreStaff('product_edit'));
    $this->delete(route('digitalproducts.destroy', $this->product))->assertForbidden();
    expect(digitalProductAdminSnapshot())->toEqual($before);
});

test('WMS authentication grants no digital product action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = digitalProductAdminSnapshot();
    foreach ([
        ['GET', route('digitalproducts.index')], ['DELETE', route('digitalproducts.destroy', $this->product)],
        ['POST', route('digitalproducts.published', $this->product)], ['POST', route('digitalproducts.featured', $this->product)],
        ['POST', route('digitalproducts.todays_deal', $this->product)], ['GET', route('digitalproducts.download', encrypt($this->product->id))],
        ['GET', route('digitalproducts.create')], ['GET', route('digitalproducts.edit', $this->product->id)],
    ] as [$method, $url]) {
        $this->call($method, $url, ['status' => 0])->assertRedirect(route('shop.login'));
    }
    expect(digitalProductAdminSnapshot())->toEqual($before);
});

test('digital product download keeps the encrypted id and serves the local file', function () {
    actingAsStoreUser(createStoreStaff('download_digital_product'));
    $fileName = 'uploads/tests/'.Str::random(8).'.txt';
    File::ensureDirectoryExists(dirname(public_path($fileName)));
    File::put(public_path($fileName), 'the digital product');
    $upload = Upload::factory()->create(['file_name' => $fileName, 'external_link' => null]);
    $this->product->forceFill(['file_name' => (string) $upload->id])->save();
    try {
        expect(route('digitalproducts.download', 'token', false))->toBe('/temp/admin/digitalproducts/download/token');
        $response = $this->get(route('digitalproducts.download', encrypt($this->product->id)))->assertOk()->assertDownload(basename($fileName));
        expect($response->baseResponse->getFile()->getContent())->toBe('the digital product');
        $this->get(route('digitalproducts.download', $this->product->id))->assertNotFound();
        $this->get(route('digitalproducts.download', encrypt('not a product id')))->assertNotFound();
        $this->get(route('digitalproducts.download', encrypt([$this->product->id])))->assertNotFound();
    } finally {
        File::delete(public_path($fileName));
    }
    $this->get(route('digitalproducts.download', encrypt($this->product->id)))->assertNotFound();
    $this->product->forceFill(['file_name' => '999999'])->save();
    $this->get(route('digitalproducts.download', encrypt($this->product->id)))->assertNotFound();
    $this->product->forceFill(['file_name' => null])->save();
    $this->get(route('digitalproducts.download', encrypt($this->product->id)))->assertNotFound();
});

test('digital product download streams from s3 under its original name when that disk is the default', function () {
    actingAsStoreUser(createStoreStaff('download_digital_product'));
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Storage::disk('s3')->put('uploads/all/guide-file.pdf', 'the s3 product');
    $upload = Upload::factory()->create(['file_name' => 'uploads/all/guide-file.pdf', 'external_link' => null]);
    DB::table('uploads')->where('id', $upload->id)->update(['file_original_name' => 'Guide', 'extension' => 'pdf']);
    $this->product->forceFill(['file_name' => (string) $upload->id])->save();
    $this->get(route('digitalproducts.download', encrypt($this->product->id)))->assertOk()->assertDownload('Guide.pdf');
});

test('the retained Blade create and edit forms keep their routes, gates and redirects back to the list', function () {
    $staff = createStoreStaff('add_digital_product', 'edit_digital_product', 'show_digital_products');
    actingAsStoreUser($staff);
    expect(route('digitalproducts.create', [], false))->toBe('/temp/admin/digitalproducts/create')
        ->and(route('digitalproducts.store', [], false))->toBe('/temp/admin/digitalproducts')
        ->and(route('digitalproducts.edit', 5, false))->toBe('/temp/admin/digitalproducts/edit/5')
        ->and(route('digitalproducts.update', 5, false))->toBe('/temp/admin/digitalproducts/5')
        ->and(Route::getRoutes()->getByName('digitalproducts.store')->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('digitalproducts.update')->methods())->toBe(['PUT', 'PATCH'])
        ->and(Route::getRoutes()->getByName('digitalproducts.create')->getActionName())->toBe(LegacyDigitalProductController::class.'@create')
        ->and(Route::getRoutes()->getByName('digitalproducts.edit')->getActionName())->toBe(LegacyDigitalProductController::class.'@edit')
        ->and(Route::getRoutes()->getByName('digitalproducts.index')->getActionName())->toBe(DigitalProductController::class.'@index');
    $this->get(route('digitalproducts.create'))->assertOk()->assertViewIs('shop.backend.product.digital_products.create');
    $this->get(route('digitalproducts.edit', ['id' => $this->product->id, 'lang' => 'en']))->assertOk()->assertViewIs('shop.backend.product.digital_products.edit');
    $this->get(route('digitalproducts.create'), inertiaHeaders())->assertConflict()->assertHeader('X-Inertia-Location', route('digitalproducts.create'));
    $this->post(route('digitalproducts.store'), ['name' => 'New guide', 'unit_price' => 4, 'tags' => [null]])->assertRedirect(route('digitalproducts.index'));
    $new = Product::query()->where('name', 'New guide')->firstOrFail();
    expect($new->getAttribute('digital'))->toBe(1)->and($new->getAttribute('added_by'))->toBe('admin');
    $this->assertDatabaseHas('product_translations', ['product_id' => $new->id, 'lang' => 'en', 'name' => 'New guide']);
    $this->from(route('digitalproducts.edit', ['id' => $new->id, 'lang' => 'en']))
        ->patch(route('digitalproducts.update', $new->id), ['name' => 'Renamed guide', 'lang' => 'en', 'unit_price' => 5, 'slug' => 'Renamed-Guide', 'tags' => [null]])
        ->assertRedirect(route('digitalproducts.edit', ['id' => $new->id, 'lang' => 'en']));
    expect($new->fresh()->name)->toBe('Renamed guide')->and($new->fresh()->slug)->toBe('renamed-guide');
    actingAsStoreUser(createStoreStaff('show_digital_products'));
    $this->get(route('digitalproducts.create'))->assertForbidden();
    $this->get(route('digitalproducts.edit', $this->product->id))->assertForbidden();
});

test('the list migration drops only the unused resource routes and the list view, keeping seller, app and storefront contracts', function () {
    expect(Route::has('digitalproducts.show'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.digital_products.index'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.digital_products.create'))->toBeTrue()
        ->and(view()->exists('shop.backend.product.digital_products.edit'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('seller.digitalproducts.download')->getActionName())->toBe(SellerDigitalProductController::class.'@download')
        ->and(route('seller.digitalproducts.download', 'token', false))->toBe('/temp/seller/digitalproducts/download/token')
        ->and(Route::getRoutes()->getByName('seller.digitalproducts.destroy')->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('digital-products.download')->getActionName())->toBe(PurchaseHistoryController::class.'@download')
        ->and(Route::getRoutes()->match(Request::create('/temp/api/v2/purchased-products/download/5'))->getActionName())
        ->toBe(ApiDigitalProductController::class.'@download');
    actingAsStoreUser(createStoreStaff('show_digital_products', 'edit_digital_product'));
    $this->get("/temp/admin/digitalproducts/{$this->product->id}")->assertMethodNotAllowed();
    $this->get("/temp/admin/digitalproducts/{$this->product->id}/edit")->assertNotFound();
    $this->get(route('product', 'recipe-book'))->assertOk()->assertSee('Recipe book');
});
