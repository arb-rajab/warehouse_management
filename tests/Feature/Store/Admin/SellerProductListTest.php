<?php

use App\Http\Controllers\ProductController;
use App\Models\Category;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\StoreUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    $this->seller = createStoreUser('seller');
    $this->product = createStoreProduct(['name' => 'Seller oil', 'ar_name' => 'زيت البائع', 'slug' => 'seller-oil', 'added_by' => 'seller', 'user_id' => $this->seller->id, 'thumbnail_img' => null, 'featured' => 1, 'current_stock' => 7]);
    $this->other = createStoreProduct(['added_by' => 'seller', 'user_id' => createStoreUser('seller')->id, 'thumbnail_img' => null]);
    Notification::fake();
});

/** @return array<string, mixed> */
function sellerListSnapshot(): array
{
    return collect(['products', 'product_stocks', 'product_taxes', 'product_translations', 'product_variations', 'product_barcodes', 'carts', 'notifications', 'wms_product_settings', 'wms_product_barcodes'])
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy($table === 'wms_product_settings' ? 'product_id' : 'id')->get()->toArray()])->all();
}

function sellerListCaches(int $times = 1): void
{
    Artisan::shouldReceive('call')->with('view:clear')->times($times)->andReturn(0);
    Artisan::shouldReceive('call')->with('cache:clear')->times($times)->andReturn(0);
}

test('seller publication and sale preserve stock and featuring effects in both directions', function (string $route, string $field) {
    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('product_stocks')->insert(['product_id' => $this->product->id, 'variant' => 'Large', 'price' => 2, 'qty' => 3]);
    sellerListCaches(2);
    foreach ([0, 1] as $value) {
        $this->post(route($route, $this->product), ['status' => $value])->assertRedirect(route('products.seller'));
        expect((bool) $this->product->fresh()->getAttribute($field))->toBe((bool) $value)
            ->and($this->product->fresh()->getAttribute('featured'))->toBe(0)
            ->and(DB::table('product_stocks')->where('product_id', $this->product->id)->pluck('qty')->all())->toBe([$value ? 1000 : 0, $value ? 1000 : 0]);
    }
    expect(DB::table('product_stocks')->where('product_id', $this->other->id)->value('qty'))->toBe(50);
    Notification::assertNothingSent();
})->with([['products.published', 'published'], ['products.update_sale', 'for_sale']]);

test('seller featuring and approval change only their own flag and clear caches both ways', function (string $route, string $field, string $input) {
    actingAsStoreUser(createStoreStaff('product_edit'));
    sellerListCaches(2);
    foreach ([0, 1] as $value) {
        $before = sellerListSnapshot();
        $this->post(route($route, $this->product), [$input => $value])->assertRedirect(route('products.seller'));
        expect((int) $this->product->fresh()->getAttribute($field))->toBe($value);
        $after = sellerListSnapshot();
        expect(collect($after)->except('products')->all())->toEqual(collect($before)->except('products')->all());
        $changed = (array) collect($after['products'])->firstWhere('id', $this->product->id);
        $original = (array) collect($before['products'])->firstWhere('id', $this->product->id);
        expect(collect($changed)->except([$field, 'updated_at'])->all())->toEqual(collect($original)->except([$field, 'updated_at'])->all());
    }
    Notification::assertNothingSent();
})->with([['products.featured', 'featured', 'status'], ['products.approved', 'approved', 'approved']]);

test('seller approval refuses even unapproval outside the subscription package without writes', function (int $approved) {
    actingAsStoreUser(createStoreStaff('product_edit'));
    createShopFor($this->seller, 0);
    DB::table('addons')->insert(['name' => 'Seller subscription', 'unique_identifier' => 'seller_subscription', 'activated' => 1]);
    Cache::forget('addons');
    Artisan::shouldReceive('call')->never();
    $before = sellerListSnapshot();
    $this->post(route('products.approved', $this->product), ['approved' => $approved])->assertSessionHasErrors(['approved' => __('messages.store_product_package_limit')]);
    expect(sellerListSnapshot())->toEqual($before);
    Notification::assertNothingSent();
})->with([0, 1]);

test('seller todays deal invalidates only its dedicated cache', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->never();
    Cache::put('unrelated-wms-cache', 'kept');
    foreach ([0, 1] as $value) {
        Cache::put('todays_deal_products', 'old');
        $before = sellerListSnapshot();
        $this->post(route('products.todays_deal', $this->product), ['status' => $value])->assertRedirect(route('products.seller'));
        expect((int) $this->product->fresh()->getAttribute('todays_deal'))->toBe($value)
            ->and(Cache::has('todays_deal_products'))->toBeFalse()->and(Cache::get('unrelated-wms-cache'))->toBe('kept')
            ->and(collect(sellerListSnapshot())->except('products')->all())->toEqual(collect($before)->except('products')->all());
    }
    Notification::assertNothingSent();
});

test('seller duplication copies ownership stock and taxes without translations variations or barcodes', function () {
    actingAsStoreUser(createStoreStaff('product_duplicate'));
    Artisan::shouldReceive('call')->never();
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Translation']);
    DB::table('product_taxes')->insert(['product_id' => $this->product->id, 'tax_id' => 1, 'tax' => 5, 'tax_type' => 'percent']);
    DB::table('product_variations')->insert(['product_id' => $this->product->id, 'name' => 'Bottle']);
    DB::table('product_barcodes')->insert(['product_id' => $this->product->id, 'barcode' => 'SELLER-1']);
    $this->post(route('products.duplicate', $this->product))->assertRedirect(route('products.seller'));
    $copy = Product::query()->whereNotIn('id', [$this->product->id, $this->other->id])->sole();
    expect($copy->getAttribute('added_by'))->toBe('seller')->and($copy->user_id)->toBe($this->seller->id)
        ->and($copy->name)->toBe('Seller oil')->and($copy->slug)->toStartWith('seller-oil-')
        ->and($copy->stocks()->count())->toBe(1)->and($copy->taxes()->count())->toBe(1)
        ->and($copy->product_translations()->count())->toBe(0)->and($copy->variations()->count())->toBe(0)->and($copy->storeBarcodes()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('product delete permission deletes seller owned products and only their dependants', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    foreach ([$this->product, $this->other] as $product) {
        ProductTranslation::create(['product_id' => $product->id, 'lang' => 'en', 'name' => 'Translation']);
        DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 5, 'tax_type' => 'percent']);
        DB::table('product_variations')->insert(['product_id' => $product->id, 'name' => 'Bottle']);
        DB::table('product_barcodes')->insert(['product_id' => $product->id, 'barcode' => 'B'.$product->id]);
        DB::table('carts')->insert(['user_id' => $this->seller->id, 'owner_id' => $this->seller->id, 'product_id' => $product->id, 'quantity' => 1]);
    }
    sellerListCaches();
    $this->delete(route('products.destroy', $this->product->id))->assertRedirect();
    foreach (['products', 'product_translations', 'product_stocks', 'product_taxes', 'product_variations', 'product_barcodes', 'wms_product_settings'] as $table) {
        $column = $table === 'products' ? 'id' : 'product_id';
        expect(DB::table($table)->where($column, $this->product->id)->exists())->toBeFalse()->and(DB::table($table)->where($column, $this->other->id)->exists())->toBeTrue();
    }
    expect(DB::table('carts')->where('product_id', $this->product->id)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('carts')->where('product_id', $this->other->id)->value('deleted_at'))->toBeNull();
    Notification::assertNothingSent();
});

test('seller bulk deletion retains products outside the selection', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    $third = createStoreProduct(['added_by' => 'seller', 'thumbnail_img' => null]);
    sellerListCaches();
    $this->delete(route('bulk-product-delete'), ['id' => [$this->product->id, $this->other->id]])->assertRedirect(route('products.seller'));
    $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
    $this->assertDatabaseMissing('products', ['id' => $this->other->id]);
    $this->assertDatabaseHas('products', ['id' => $third->id]);
    Notification::assertNothingSent();
});

test('the seller product list renders the shared Inertia page', function () {
    actingAsStoreUser(createStoreStaff('show_seller_products'));
    $this->get(route('products.seller'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Index')->where('listType', 'seller'));
    $this->get(route('products.seller'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
});

test('seller filter options are narrow seller accounts and approval follows its existing setting', function () {
    actingAsStoreUser(createStoreStaff('show_seller_products'));
    $this->seller->forceFill(['name' => 'Ada seller'])->save();
    StoreUser::query()->whereKey($this->other->user_id)->update(['name' => 'Zed seller']);
    createStoreUser('customer');
    foreach ([false, true] as $enabled) {
        DB::table('business_settings')->updateOrInsert(['type' => 'product_approve_by_admin'], ['value' => (string) (int) $enabled]);
        Cache::forget('business_settings');
        $this->get(route('products.seller'))->assertInertia(fn (Assert $page) => $page
            ->where('approvalEnabled', $enabled)->where('sellers', [
                ['id' => $this->seller->id, 'name' => 'Ada seller'],
                ['id' => $this->other->user_id, 'name' => 'Zed seller'],
            ]));
    }
});

test('seller rows expose the full shared payload and exclude every other product kind', function () {
    actingAsStoreUser(createStoreStaff('show_seller_products'));
    DB::table('products')->where('id', $this->product->id)->update([
        'mat_id' => 'S1', 'api_unit_name' => 'Box', 'unit_equal' => 12, 'num_of_sale' => 3,
        'low_stock_quantity' => 10, 'for_sale' => 1, 'approved' => 0, 'todays_deal' => 1,
    ]);
    foreach (['admin', 'customer'] as $owner) {
        createStoreProduct(['added_by' => $owner, 'featured' => 1, 'thumbnail_img' => null]);
    }
    foreach (['digital', 'auction_product', 'wholesale_product'] as $field) {
        $noise = createStoreProduct(['added_by' => 'seller', 'featured' => 1, 'thumbnail_img' => null]);
        DB::table('products')->where('id', $noise->id)->update([$field => 1, 'for_sale' => 1]);
    }
    $this->get(route('products.seller', ['active' => 1, 'for_sale' => 1, 'featured' => 1]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 1)->has('products.data.0', fn (Assert $row) => $row
        ->where('id', $this->product->id)->where('name', 'Seller oil')->where('ar_name', 'زيت البائع')
        ->where('thumbnail_url', static_asset('assets/img/placeholder.jpg'))->where('mat_id', 'S1')->where('unit', 'Box(12)')
        ->where('num_of_sale', 3)->where('base_price', single_price(2.5))->where('profit', single_price(0))
        ->where('variant_stocks', null)->where('stock_quantity', 7)->where('low_stock', true)->where('category', null)
        ->where('for_sale', true)->where('published', true)->where('featured', true)->where('approved', false)->where('todays_deal', true)
        ->where('deleted_from_api', false)->where('public_url', route('product', 'seller-oil'))));
});

test('seller filters retain exact state matching and name phrase search with owner category approval and sort', function () {
    actingAsStoreUser(createStoreStaff('show_seller_products'));
    $category = Category::forceCreate(['name' => 'Oils', 'slug' => 'seller-oils', 'parent_id' => 0, 'level' => 0]);
    DB::table('products')->whereIn('id', [$this->product->id, $this->other->id])->update(['for_sale' => 0, 'featured' => 0]);
    DB::table('products')->where('id', $this->product->id)->update(['category_id' => $category->id, 'approved' => 0]);
    $ids = fn (array $query): array => array_column($this->get(route('products.seller', $query))->assertOk()->inertiaProps('products.data'), 'id');
    expect($ids([]))->toBe([])->and($ids(['active' => 'true']))->toContain($this->product->id, $this->other->id)
        ->and($ids(['active' => 1, 'search' => 'Seller oil']))->toBe([$this->product->id])
        ->and($ids(['active' => 1, 'search' => 'زيت البائع']))->toBe([])
        ->and($ids(['active' => 1, 'user_id' => $this->seller->id]))->toBe([$this->product->id])
        ->and($ids(['active' => 1, 'category_id' => $category->id]))->toBe([$this->product->id])
        ->and($ids(['active' => 1, 'approved' => '0']))->toBe([$this->product->id])
        ->and($ids(['active' => 1, 'approved' => '1']))->toBe([$this->other->id]);
    DB::table('products')->where('id', $this->product->id)->update(['unit_price' => 10]);
    expect($ids(['active' => 1, 'type' => 'unit_price,desc']))->toBe([$this->product->id, $this->other->id]);
});

test('seller products paginate beyond one page and allow supported per page values only', function () {
    actingAsStoreUser(createStoreStaff('show_seller_products'));
    for ($i = 0; $i < 24; $i++) {
        tap(createStoreProduct(['added_by' => 'seller', 'published' => false, 'thumbnail_img' => null]), fn ($product) => DB::table('products')->where('id', $product->id)->update(['for_sale' => 0]));
    }
    $this->get(route('products.seller'))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 20)->where('products.meta.total', 24)->where('products.meta.per_page', 20));
    $this->get(route('products.seller', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('products.data', 10)->where('filters.per_page', 10));
    foreach (['per_page' => 7, 'type' => 'name,desc', 'approved' => 'bogus', 'user_id' => -1, 'category_id' => -1, 'search' => str_repeat('x', 256)] as $field => $value) {
        $this->getJson(route('products.seller', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

test('the seller product list refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('products.seller')), $caller);
})->with('refused store admin callers');

foreach (['products.published', 'products.featured', 'products.update_sale', 'products.todays_deal', 'products.approved', 'products.duplicate', 'products.destroy', 'bulk-product-delete'] as $action) {
    test("seller action $action refuses unauthorized callers without changing the database", function (string $caller) use ($action) {
        actingAsRefusedStoreCaller($caller);
        $before = sellerListSnapshot();
        $url = route($action, $action === 'bulk-product-delete' ? [] : [$this->product]);
        $response = in_array($action, ['products.destroy', 'bulk-product-delete'], true)
            ? $this->delete($url, ['id' => [$this->product->id]])
            : $this->post($url, ['status' => 0, 'approved' => 0]);
        assertStoreCallerRefused($response, $caller);
        expect(sellerListSnapshot())->toEqual($before);
        Notification::assertNothingSent();
    })->with('refused store admin callers');

    test("seller action $action is fixed to seller products even with a forged list", function () use ($action) {
        actingAsStoreUser(createStoreStaff('product_edit', 'product_delete', 'product_duplicate'));
        $admin = createStoreProduct(['added_by' => 'admin', 'thumbnail_img' => null]);
        $digital = createStoreProduct(['added_by' => 'seller', 'digital' => 1, 'thumbnail_img' => null]);
        Artisan::shouldReceive('call')->never();
        $before = sellerListSnapshot();
        foreach ([$admin->id, $digital->id, 999999] as $id) {
            $url = route($action, $action === 'bulk-product-delete' ? ['list' => 'all'] : [$id, 'list' => 'all']);
            if ($action === 'bulk-product-delete') {
                $this->deleteJson($url, ['id' => [$this->product->id, $id]])->assertUnprocessable()->assertJsonValidationErrors('id');
            } elseif ($action === 'products.destroy') {
                $this->delete($url)->assertNotFound();
            } else {
                $this->post($url, ['status' => 0, 'approved' => 0])->assertNotFound();
            }
        }
        expect(sellerListSnapshot())->toEqual($before);
    });
}

test('seller flag requests reject missing and invalid states before writing', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->never();
    $before = sellerListSnapshot();
    foreach (['products.published', 'products.featured', 'products.update_sale', 'products.todays_deal', 'products.approved'] as $route) {
        $field = $route === 'products.approved' ? 'approved' : 'status';
        foreach ([null, 'yes', 2, ['1']] as $value) {
            $this->postJson(route($route, $this->product), [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    expect(sellerListSnapshot())->toEqual($before);
});

test('seller deletion and duplication permissions remain separate from list and edit permissions', function () {
    actingAsStoreUser(createStoreStaff('show_seller_products', 'product_edit'));
    $before = sellerListSnapshot();
    $this->delete(route('products.destroy', $this->product))->assertForbidden();
    $this->delete(route('bulk-product-delete'), ['id' => [$this->product->id]])->assertForbidden();
    $this->post(route('products.duplicate', $this->product))->assertForbidden();
    actingAsStoreUser(createStoreStaff('product_delete', 'product_duplicate'));
    $this->get(route('products.seller'))->assertForbidden();
    foreach (['products.published', 'products.featured', 'products.update_sale', 'products.todays_deal', 'products.approved'] as $route) {
        $this->post(route($route, $this->product), ['status' => 0, 'approved' => 0])->assertForbidden();
    }
    actingAsStoreUser($this->seller);
    $this->delete(route('products.destroy', $this->product))->assertNotFound();
    expect(sellerListSnapshot())->toEqual($before);
});

test('seller deletes are DELETE only and retain the selected list query', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    $before = sellerListSnapshot();
    $this->post(route('products.destroy', $this->product))->assertMethodNotAllowed();
    $this->get(route('products.destroy', $this->product))->assertMethodNotAllowed();
    $this->post(route('bulk-product-delete'), ['id' => [$this->product->id]])->assertMethodNotAllowed();
    expect(sellerListSnapshot())->toEqual($before);
    sellerListCaches();
    $this->delete(route('products.destroy', [$this->product, 'search' => 'oil', 'page' => 2, 'per_page' => 10]))
        ->assertRedirect(route('products.seller', ['search' => 'oil', 'page' => 2, 'per_page' => 10]));
});

test('seller bulk deletion validates every id and rolls back all rows on a warehouse reference', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    Artisan::shouldReceive('call')->never();
    foreach ([[], ['id' => []], ['id' => 'x'], ['id' => ['x']], ['id' => [$this->product->id, $this->product->id]], ['id' => range(1, 101)]] as $payload) {
        $before = sellerListSnapshot();
        $this->deleteJson(route('bulk-product-delete'), $payload)->assertUnprocessable();
        expect(sellerListSnapshot())->toEqual($before);
    }
    Pallet::factory()->create(['product_id' => $this->other->id]);
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Retained']);
    $before = sellerListSnapshot();
    $this->withoutExceptionHandling();
    expect(fn () => $this->delete(route('bulk-product-delete'), ['id' => [$this->product->id, $this->other->id]]))->toThrow(QueryException::class);
    expect(sellerListSnapshot())->toEqual($before);
});

test('seller approval is transactional on persistence failure and leaves all caches and notifications alone', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->never();
    $before = sellerListSnapshot();
    Product::saving(fn () => throw new RuntimeException('Approval failure'));
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post(route('products.approved', $this->product), ['approved' => 0]))->toThrow(RuntimeException::class);
        expect(sellerListSnapshot())->toEqual($before);
        Notification::assertNothingSent();
    } finally {
        Product::flushEventListeners();
    }
});

test('seller publication refuses a missing expired or full package before restocking', function (?string $expires, int $limit) {
    actingAsStoreUser(createStoreStaff('product_edit'));
    createShopFor($this->seller, 0);
    DB::table('shops')->where('user_id', $this->seller->id)->update(['package_invalid_at' => $expires, 'product_upload_limit' => $limit]);
    DB::table('products')->where('id', $this->product->id)->update(['published' => 0]);
    DB::table('addons')->insert(['name' => 'Seller subscription', 'unique_identifier' => 'seller_subscription', 'activated' => 1]);
    Cache::forget('addons');
    Artisan::shouldReceive('call')->never();
    $before = sellerListSnapshot();
    $this->postJson(route('products.published', $this->product), ['status' => 1])->assertUnprocessable()->assertJsonValidationErrors('status');
    expect(sellerListSnapshot())->toEqual($before);
    Notification::assertNothingSent();
})->with([
    'no expiry' => [null, 10],
    'expired' => ['2020-01-01', 10],
    'full' => ['2099-01-01', 0],
]);

test('a current package permits approval both ways and publication without changing ownership', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    createShopFor($this->seller, 0);
    DB::table('shops')->where('user_id', $this->seller->id)->update(['package_invalid_at' => '2099-01-01', 'product_upload_limit' => 10]);
    DB::table('addons')->insert(['name' => 'Seller subscription', 'unique_identifier' => 'seller_subscription', 'activated' => 1]);
    Cache::forget('addons');
    sellerListCaches(3);
    foreach ([0, 1] as $approved) {
        $this->post(route('products.approved', $this->product), ['approved' => $approved])->assertRedirect(route('products.seller'));
        expect((int) $this->product->fresh()->getAttribute('approved'))->toBe($approved);
    }
    $this->post(route('products.published', $this->product), ['status' => 1])->assertRedirect(route('products.seller'));
    expect($this->product->fresh()->user_id)->toBe($this->seller->id);
    Notification::assertNothingSent();
});

test('moved product middleware keeps every legacy action gate and remaining root consumers', function () {
    $middleware = collect(App\Http\Controllers\Store\Admin\ProductController::middleware());
    foreach (['sellerPublished', 'sellerFeatured', 'updateSaleStatus', 'updateTodaysDeal', 'updateProductApproval'] as $method) {
        expect($middleware->first(fn ($entry) => $entry->middleware === 'permission:product_edit' && in_array($method, $entry->only ?? [], true)))->not->toBeNull();
    }
    expect(app(App\Http\Controllers\Store\Admin\ProductController::class))->toBeInstanceOf(App\Http\Controllers\Store\Admin\ProductController::class)
        ->and(Route::getRoutes()->getByName('products.seller')->getActionName())->toBe(App\Http\Controllers\Store\Admin\ProductController::class.'@seller');
    foreach (['products.fetch', 'products.sku_combination'] as $name) {
        expect(Route::getRoutes()->getByName($name)->getControllerClass())->toBe(ProductController::class);
    }
});
