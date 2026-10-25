<?php

use App\Http\Controllers\Api\V2\ProductController as ApiProductController;
use App\Http\Controllers\ProductController as LegacyProductController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Store\Admin\ProductController;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductCollection;
use App\Models\ProductTranslation;
use App\Models\Upload;
use App\Models\User;
use App\Support\StorefrontUrl;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    $this->category = Category::forceCreate(['name' => 'Oils', 'parent_id' => 0, 'level' => 0, 'slug' => 'oils', 'digital' => 0]);
    CategoryTranslation::create(['category_id' => $this->category->id, 'lang' => 'ar', 'name' => 'زيوت']);
    $this->product = createStoreProduct([
        'name' => 'Olive oil', 'ar_name' => 'زيت زيتون', 'slug' => 'olive-oil', 'added_by' => 'admin', 'digital' => 0,
        'thumbnail_img' => null, 'unit_price' => 12.5, 'current_stock' => 40, 'published' => true, 'featured' => 0,
    ]);
    DB::table('products')->where('id', $this->product->id)->update([
        'for_sale' => 1, 'mat_id' => '100027', 'api_unit_name' => 'Box', 'unit_equal' => 12, 'num_of_sale' => 7,
        'category_id' => $this->category->id, 'low_stock_quantity' => 5, 'deleted_from_api' => 0,
    ]);
});

/**
 * Every table a product list action reads or writes, in a stable order.
 *
 * @return array<string, list<object>>
 */
function productListSnapshot(): array
{
    return collect([
        'products' => 'id', 'product_translations' => 'id', 'product_stocks' => 'id', 'product_taxes' => 'id',
        'product_variations' => 'id', 'product_barcodes' => 'id', 'carts' => 'id', 'wms_product_settings' => 'product_id',
        'wms_product_barcodes' => 'id', 'pallets' => 'id', 'cell_status_logs' => 'id',
    ])->mapWithKeys(fn ($key, $table) => [$table => DB::table($table)->orderBy($key)->get()->toArray()])->all();
}

/**
 * The product switches and deletion clear the compiled views and the cache,
 * as the root ProductController endpoints do. Mocked so a test never empties
 * the compiled-view directory other parallel workers are reading.
 */
function expectProductListCachesCleared(int $times = 1): void
{
    Artisan::shouldReceive('call')->with('view:clear')->times($times)->andReturn(0);
    Artisan::shouldReceive('call')->with('cache:clear')->times($times)->andReturn(0);
}

/**
 * The ids a product list response shows, in order.
 *
 * @return list<int>
 */
function productListIds(TestResponse $response): array
{
    return array_column($response->assertOk()->inertiaProps('products.data'), 'id');
}

/**
 * Products the lists must leave out or include, beside the in-house fixture.
 *
 * @return array<string, Product>
 */
function productListNoise(): array
{
    $seller = createStoreUser('seller');

    return [
        'seller' => createStoreProduct(['name' => 'Seller oil', 'added_by' => 'seller', 'user_id' => $seller->id, 'thumbnail_img' => null]),
        'digital' => createStoreProduct(['name' => 'Oil e-book', 'digital' => 1, 'thumbnail_img' => null]),
        'auction' => tap(createStoreProduct(['name' => 'Auction oil', 'thumbnail_img' => null]), fn ($p) => DB::table('products')->where('id', $p->id)->update(['auction_product' => 1])),
        'wholesale' => tap(createStoreProduct(['name' => 'Wholesale oil', 'thumbnail_img' => null]), fn ($p) => DB::table('products')->where('id', $p->id)->update(['wholesale_product' => 1])),
    ];
}

test('both product lists render the selected Inertia page with their list type', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products', 'show_all_products'));
    $this->get(route('products.admin'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Index')->where('listType', 'admin'));
    $this->get(route('products.all'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Index')->where('listType', 'all'));
    expect(route('products.admin', [], false))->toBe('/temp/admin/products/admin')
        ->and(route('products.all', [], false))->toBe('/temp/admin/products/all')
        ->and(Route::getRoutes()->getByName('products.admin')->getActionName())->toBe(ProductController::class.'@inHouse')
        ->and(Route::getRoutes()->getByName('products.all')->getActionName())->toBe(ProductController::class.'@all');
});

foreach (['products.admin', 'products.all'] as $listRoute) {
    test("the $listRoute list refuses unauthorized callers", function (string $caller) use ($listRoute) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route($listRoute)), $caller);
    })->with('refused store admin callers');
}

test('each list asks for its own permission only', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products', 'show_seller_products', 'show_digital_products'));
    $this->get(route('products.all'))->assertForbidden();
    actingAsStoreUser(createStoreStaff('show_all_products', 'product_edit', 'product_delete'));
    $this->get(route('products.admin'))->assertForbidden();
});

test('the in-house list exposes the full narrow row for in-house physical products only', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products'));
    $noise = productListNoise();
    $collection = ProductCollection::forceCreate(['name' => 'Margin', 'serial' => 'M1', 'amount' => 10]);
    $thumbnail = Upload::factory()->create(['type' => 'image', 'external_link' => 'https://example.com/oil.png', 'file_name' => null]);
    DB::table('products')->where('id', $this->product->id)->update(['collection_id' => $collection->id, 'thumbnail_img' => $thumbnail->id]);
    $variant = createStoreProduct(['name' => 'Variant oil', 'ar_name' => '', 'slug' => null, 'thumbnail_img' => null, 'current_stock' => 2]);
    DB::table('products')->where('id', $variant->id)->update(['variant_product' => 1, 'low_stock_quantity' => 10, 'mat_id' => null, 'api_unit_name' => null, 'unit_equal' => null, 'for_sale' => 1]);
    DB::table('product_stocks')->insert(['product_id' => $variant->id, 'variant' => 'Large', 'sku' => 'L', 'price' => 1, 'qty' => 5]);
    DB::table('product_stocks')->where('product_id', $variant->id)->where('variant', '')->update(['variant' => 'Small']);
    backdate($variant, '2026-01-01 00:00:00');
    backdate($this->product, '2026-02-01 00:00:00');

    $response = $this->get(route('products.admin', ['active' => 1, 'for_sale' => 1]));
    $response->assertInertia(fn (Assert $page) => $page
        ->where('listType', 'admin')
        ->has('products.data', 2)
        ->has('products.data.0', fn (Assert $row) => $row
            ->where('id', $this->product->id)->where('name', 'Olive oil')->where('ar_name', 'زيت زيتون')
            ->where('thumbnail_url', 'https://example.com/oil.png')->where('mat_id', '100027')->where('unit', 'Box(12)')
            ->where('num_of_sale', 7)->where('base_price', single_price(12.5))->where('profit', single_price(1.25))
            ->where('variant_stocks', null)->where('stock_quantity', 40)->where('low_stock', false)
            ->where('category', 'Oils')->where('for_sale', true)->where('published', true)->where('featured', false)->where('approved', true)->where('todays_deal', false)
            ->where('deleted_from_api', false)->where('public_url', route('product', 'olive-oil')))
        ->has('products.data.1', fn (Assert $row) => $row
            ->where('id', $variant->id)->where('name', 'Variant oil')->where('ar_name', '')
            ->where('thumbnail_url', static_asset('assets/img/placeholder.jpg'))->where('mat_id', null)->where('unit', '()')
            ->where('num_of_sale', 0)->where('base_price', single_price(2.5))->where('profit', single_price(0))
            ->where('variant_stocks', [['variant' => 'Small', 'qty' => 2], ['variant' => 'Large', 'qty' => 5]])
            ->where('stock_quantity', 7)->where('low_stock', true)->where('category', null)
            ->where('for_sale', true)->where('published', true)->where('featured', false)->where('approved', true)->where('todays_deal', false)->where('deleted_from_api', false)
            ->where('public_url', StorefrontUrl::to($variant)))
        ->where('filters', ['search' => null, 'category_id' => null, 'type' => null, 'active' => true, 'for_sale' => true, 'featured' => false, 'per_page' => 20])
        ->where('categories', [['id' => $this->category->id, 'name' => 'Oils']])
        ->where('defaultLanguage', 'en'));
    expect(productListIds($response))->not->toContain($noise['seller']->id, $noise['digital']->id, $noise['auction']->id, $noise['wholesale']->id);
    $this->withSession(['locale' => 'ar'])->get(route('products.admin', ['active' => 1, 'for_sale' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('products.data.0.category', 'زيوت')->where('categories.0.name', 'Oils'));
});

test('the in-house list matches its three state switches exactly, ticked or not, as the legacy list did', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products'));
    $hidden = createStoreProduct(['name' => 'Hidden oil', 'published' => false, 'thumbnail_img' => null]);
    DB::table('products')->where('id', $hidden->id)->update(['for_sale' => 0, 'featured' => 0]);
    $featured = createStoreProduct(['name' => 'Featured oil', 'published' => true, 'featured' => 1, 'thumbnail_img' => null]);

    expect(productListIds($this->get(route('products.admin'))))->toBe([$hidden->id])
        ->and(productListIds($this->get(route('products.admin', ['active' => 1, 'for_sale' => 1]))))->toBe([$this->product->id])
        ->and(productListIds($this->get(route('products.admin', ['active' => 'on', 'for_sale' => 'true', 'featured' => '1']))))->toBe([$featured->id])
        // The in-house list never had a deleted filter.
        ->and(productListIds($this->get(route('products.admin', ['active' => 1, 'for_sale' => 1, 'deleted' => 1]))))->toBe([$this->product->id]);
    $this->getJson(route('products.admin', ['active' => 'bogus']))->assertUnprocessable()->assertJsonValidationErrors('active');
});

test('the all-products list shows every owner and digital product, hides retired ones by default and applies its presets in legacy order', function () {
    actingAsStoreUser(createStoreStaff('show_all_products'));
    $noise = productListNoise();
    $retired = createStoreProduct(['name' => 'Retired oil', 'thumbnail_img' => null]);
    DB::table('products')->where('id', $retired->id)->update(['deleted_from_api' => 1, 'for_sale' => 0]);
    $featured = createStoreProduct(['name' => 'Featured oil', 'featured' => 1, 'published' => false, 'thumbnail_img' => null]);
    $visible = [$this->product->id, $noise['seller']->id, $noise['digital']->id, $featured->id];
    $everything = [$this->product->id, $noise['seller']->id, $noise['digital']->id, $retired->id, $featured->id];
    $ids = fn (array $query = []) => productListIds($this->get(route('products.all', $query)));

    expect($ids())->toBe($visible)
        ->and($ids(['allProducts' => 1]))->toBe($everything)
        ->and($ids(['allDeleted' => 1, 'allFeatured' => 1]))->toBe([$retired->id])
        // A preset ignores the retired flag, so retired products show again.
        ->and($ids(['allActive' => 1]))->toBe([$this->product->id, $noise['seller']->id, $noise['digital']->id, $retired->id])
        ->and($ids(['allForSale' => 'true']))->toBe([$this->product->id, $noise['seller']->id, $noise['digital']->id, $featured->id])
        ->and($ids(['allFeatured' => 1]))->toBe([$featured->id])
        ->and($ids(['allProducts' => 1, 'allFeatured' => 1, 'active' => 1]))->toBe($everything)
        // One ticked switch matches all four exactly, the unticked ones as off.
        ->and($ids(['deleted' => 1, 'active' => 1]))->toBe([$retired->id])
        ->and($ids(['deleted' => 1]))->toBe([])
        ->and($ids(['featured' => 1, 'for_sale' => 1]))->toBe([$featured->id])
        ->and($ids(['active' => 1, 'for_sale' => 1]))->toBe([$this->product->id, $noise['seller']->id, $noise['digital']->id])
        ->and($ids(['user_id' => $noise['seller']->user_id]))->toBe([$noise['seller']->id]);
    expect($ids())->not->toContain($noise['auction']->id, $noise['wholesale']->id);
    $this->get(route('products.all', ['allDeleted' => 1, 'user_id' => 5, 'deleted' => 1]))->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['search' => null, 'category_id' => null, 'type' => null, 'active' => false, 'for_sale' => false, 'featured' => false, 'deleted' => true, 'user_id' => 5, 'preset' => 'allDeleted', 'per_page' => 20]));
    foreach (['user_id' => 'x', 'allProducts' => 'bogus', 'deleted' => 'maybe'] as $key => $value) {
        $this->getJson(route('products.all', [$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }
});

test('the in-house search matches one phrase against both names and both barcode tables, inside the list', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products'));
    $noise = productListNoise();
    DB::table('products')->where('id', $noise['seller']->id)->update(['ar_name' => 'زيت البائع']);
    ProductBarcode::factory()->create(['product_id' => $this->product->id, 'barcode' => '8712345678901']);
    DB::table('product_barcodes')->insert(['product_id' => $this->product->id, 'barcode' => '100027']);
    $ids = fn (string $search) => productListIds($this->get(route('products.admin', ['active' => 1, 'for_sale' => 1, 'search' => $search])));

    expect($ids('Olive'))->toBe([$this->product->id])
        ->and($ids('زيتون'))->toBe([$this->product->id])
        ->and($ids('8712345'))->toBe([$this->product->id])
        ->and($ids('100027'))->toBe([$this->product->id])
        // The legacy OR leaked past the list's own conditions: a seller's
        // product matching the Arabic name was listed as in-house.
        ->and($ids('زيت'))->toBe([$this->product->id])
        ->and($ids('Olive oil'))->toBe([$this->product->id])
        ->and($ids('oil Olive'))->toBe([]);
    $this->getJson(route('products.admin', ['search' => str_repeat('s', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
    $this->getJson(route('products.admin', ['search' => ['x']]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('the all-products search needs every word to match a name, the material id or a barcode', function () {
    actingAsStoreUser(createStoreStaff('show_all_products'));
    $noise = productListNoise();
    DB::table('products')->where('id', $noise['seller']->id)->update(['mat_id' => '555001']);
    DB::table('product_barcodes')->insert(['product_id' => $noise['digital']->id, 'barcode' => '8700000000017']);
    ProductBarcode::factory()->create(['product_id' => $this->product->id, 'barcode' => '8799999999999']);
    $ids = fn (string $search) => productListIds($this->get(route('products.all', ['search' => $search])));

    expect($ids('oil Olive'))->toBe([$this->product->id])
        ->and($ids('oil'))->toBe([$this->product->id, $noise['seller']->id, $noise['digital']->id])
        ->and($ids('555001'))->toBe([$noise['seller']->id])
        ->and($ids('87000000 Oil'))->toBe([$noise['digital']->id])
        ->and($ids('879999 زيتون'))->toBe([$this->product->id])
        ->and($ids('Olive 555001'))->toBe([]);
});

test('both lists filter by category and sort only by the legacy allowlist, then by their own default order', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products', 'show_all_products'));
    $cheap = createStoreProduct(['name' => 'Cheap oil', 'unit_price' => 1, 'thumbnail_img' => null]);
    $dear = createStoreProduct(['name' => 'Dear oil', 'unit_price' => 99, 'thumbnail_img' => null]);
    DB::table('products')->where('id', $cheap->id)->update(['num_of_sale' => 30, 'rating' => 1]);
    DB::table('products')->where('id', $dear->id)->update(['num_of_sale' => 2, 'rating' => 5, 'category_id' => $this->category->id]);
    backdate($cheap, '2026-03-01 00:00:00');
    backdate($dear, '2026-02-01 00:00:00');
    backdate($this->product, '2026-01-01 00:00:00');
    $inHouse = fn (array $query = []) => productListIds($this->get(route('products.admin', ['active' => 1, 'for_sale' => 1, ...$query])));
    $all = fn (array $query = []) => productListIds($this->get(route('products.all', $query)));

    expect($inHouse())->toBe([$cheap->id, $dear->id, $this->product->id])
        ->and($all())->toBe([$this->product->id, $cheap->id, $dear->id])
        ->and($inHouse(['category_id' => $this->category->id]))->toBe([$dear->id, $this->product->id])
        ->and($all(['category_id' => $this->category->id]))->toBe([$this->product->id, $dear->id])
        ->and($inHouse(['type' => 'unit_price,asc']))->toBe([$cheap->id, $this->product->id, $dear->id])
        ->and($all(['type' => 'unit_price,desc']))->toBe([$dear->id, $this->product->id, $cheap->id])
        ->and($inHouse(['type' => 'num_of_sale,desc']))->toBe([$cheap->id, $this->product->id, $dear->id])
        ->and($all(['type' => 'num_of_sale,asc']))->toBe([$dear->id, $this->product->id, $cheap->id])
        ->and($inHouse(['type' => 'rating,desc']))->toBe([$dear->id, $cheap->id, $this->product->id])
        ->and($all(['type' => 'rating,asc']))->toBe([$this->product->id, $cheap->id, $dear->id]);
    foreach (['name,asc', 'rating', 'rating,sideways', 'rating,desc;x', 'id,desc'] as $sort) {
        foreach (['products.admin', 'products.all'] as $list) {
            $this->getJson(route($list, ['type' => $sort]))->assertUnprocessable()->assertJsonValidationErrors('type');
        }
    }
    foreach (['x', 0] as $category) {
        $this->getJson(route('products.all', ['category_id' => $category]))->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }
});

test('both lists page with the per-page selector and refuse other page sizes', function () {
    actingAsStoreUser(createStoreStaff('show_in_house_products', 'show_all_products'));
    foreach (range(1, 22) as $i) {
        backdate(createStoreProduct(['name' => sprintf('Noise %02d', $i), 'thumbnail_img' => null]), sprintf('2025-01-%02d 00:00:00', $i));
    }
    $retired = createStoreProduct(['name' => 'Retired', 'thumbnail_img' => null]);
    DB::table('products')->where('id', $retired->id)->update(['deleted_from_api' => 1]);
    assertPagesWithoutOverlap(fn ($page) => $this->get(route('products.admin', ['active' => 1, 'for_sale' => 1, 'page' => $page])), fn ($response) => productListIds($response), 24, 20);
    assertPagesWithoutOverlap(fn ($page) => $this->get(route('products.all', ['page' => $page])), fn ($response) => productListIds($response), 23, 20, [$retired->id]);
    $this->get(route('products.all', ['per_page' => 10, 'search' => 'Noise']))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 10)->where('products.meta.per_page', 10)->where('filters.per_page', 10)->where('filters.search', 'Noise')
        ->where('products.meta.links.2.url', fn (string $url) => str_contains($url, 'search=Noise') && str_contains($url, 'per_page=10')));
    foreach ([11, 0, 1000, 'many'] as $size) {
        $this->getJson(route('products.admin', ['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson(route('products.all', ['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
});

test('unpublishing from a list unfeatures the product and empties its stock, and publishing restocks it', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('products')->where('id', $this->product->id)->update(['featured' => 1]);
    DB::table('product_stocks')->insert(['product_id' => $this->product->id, 'variant' => 'large', 'sku' => 'L', 'price' => 1, 'qty' => 3]);
    $other = createStoreProduct(['featured' => 1, 'current_stock' => 9, 'thumbnail_img' => null]);
    expectProductListCachesCleared(2);
    $this->post(route('products.list.published', [$this->product, 'list' => 'admin', 'page' => 2, 'search' => 'Oli']), ['status' => 0])
        ->assertRedirect(route('products.admin', ['page' => 2, 'search' => 'Oli']));
    $product = $this->product->fresh();
    expect($product->published)->toBeFalse()->and($product->getAttribute('featured'))->toBe(0)
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->pluck('qty')->all())->toBe([0, 0]);
    $this->post(route('products.list.published', [$this->product, 'list' => 'all', 'allActive' => 1]), ['status' => '1'])
        ->assertRedirect(route('products.all', ['allActive' => 1]));
    expect($this->product->fresh()->published)->toBeTrue()
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->pluck('qty')->all())->toBe([1000, 1000])
        ->and($other->fresh()->getAttribute('featured'))->toBe(1)->and(DB::table('product_stocks')->where('product_id', $other->id)->value('qty'))->toBe(9);
});

test('taking a product off sale unfeatures it and empties its stock, and putting it on sale restocks it', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('products')->where('id', $this->product->id)->update(['featured' => 1]);
    expectProductListCachesCleared(2);
    $this->post(route('products.list.for_sale', [$this->product, 'list' => 'admin', 'per_page' => 50]), ['status' => false])
        ->assertRedirect(route('products.admin', ['per_page' => 50]));
    $product = $this->product->fresh();
    expect($product->getAttribute('for_sale'))->toBe(0)->and($product->getAttribute('featured'))->toBe(0)->and($product->published)->toBeTrue()
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->value('qty'))->toBe(0);
    $this->post(route('products.list.for_sale', [$this->product, 'list' => 'all']), ['status' => 1])->assertRedirect(route('products.all'));
    expect($this->product->fresh()->getAttribute('for_sale'))->toBe(1)
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->value('qty'))->toBe(1000);
});

test('the featured switch writes only its own column', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $before = productListSnapshot();
    expectProductListCachesCleared();
    $this->post(route('products.list.featured', [$this->product, 'list' => 'admin']), ['status' => 1])->assertRedirect(route('products.admin'));
    $after = productListSnapshot();
    expect($this->product->fresh()->getAttribute('featured'))->toBe(1)
        ->and(collect($after)->except('products')->all())->toEqual(collect($before)->except('products')->all());
});

test('publishing a seller product outside its shop package is refused before anything is written', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $seller = createStoreUser('seller');
    createShopFor($seller, 0);
    $product = createStoreProduct(['added_by' => 'seller', 'user_id' => $seller->id, 'published' => false, 'current_stock' => 0, 'thumbnail_img' => null]);
    DB::table('addons')->insert(['name' => 'Seller subscription', 'unique_identifier' => 'seller_subscription', 'activated' => 1]);
    Cache::forget('addons');
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    $this->post(route('products.list.published', [$product, 'list' => 'all']), ['status' => 1])
        ->assertSessionHasErrors(['status' => __('messages.store_product_package_limit')]);
    expect(productListSnapshot())->toEqual($before);
});

foreach (['published', 'featured', 'for_sale'] as $flag) {
    test("the $flag switch validates its status and list before any write", function () use ($flag) {
        actingAsStoreUser(createStoreStaff('product_edit'));
        Artisan::shouldReceive('call')->never();
        $before = productListSnapshot();
        foreach ([null, 'yes', 2, ['1']] as $status) {
            $this->postJson(route('products.list.'.$flag, [$this->product, 'list' => 'admin']), ['status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        foreach ([[], ['list' => 'unknown'], ['list' => ['admin']]] as $query) {
            $this->postJson(route('products.list.'.$flag, [$this->product, ...$query]), ['status' => 0])->assertUnprocessable()->assertJsonValidationErrors('list');
        }
        expect(productListSnapshot())->toEqual($before);
    });
}

test('duplicating copies the product row with a new slug and its stock and tax rows, and nothing else', function () {
    actingAsStoreUser(createStoreStaff('product_duplicate'));
    DB::table('product_taxes')->insert(['product_id' => $this->product->id, 'tax_id' => 3, 'tax' => 9, 'tax_type' => 'percent']);
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'زيت']);
    DB::table('product_variations')->insert(['product_id' => $this->product->id, 'name' => '1L']);
    DB::table('product_barcodes')->insert(['product_id' => $this->product->id, 'barcode' => '100027']);
    $this->post(route('products.list.duplicate', [$this->product, 'list' => 'admin', 'search' => 'Olive']))
        ->assertRedirect(route('products.admin', ['search' => 'Olive']));
    $copy = Product::query()->where('id', '!=', $this->product->id)->where('name', 'Olive oil')->sole();
    $source = DB::table('products')->where('id', $this->product->id)->first();
    $copied = DB::table('products')->where('id', $copy->id)->first();
    expect($copied->slug)->toMatch('/^olive-oil-[A-Za-z0-9]{5}$/')
        ->and(collect((array) $copied)->except(['id', 'slug', 'created_at', 'updated_at'])->all())
        ->toEqual(collect((array) $source)->except(['id', 'slug', 'created_at', 'updated_at'])->all())
        ->and(DB::table('product_stocks')->where('product_id', $copy->id)->get(['variant', 'sku', 'price', 'qty'])->toArray())
        ->toEqual(DB::table('product_stocks')->where('product_id', $this->product->id)->get(['variant', 'sku', 'price', 'qty'])->toArray())
        ->and(DB::table('product_taxes')->where('product_id', $copy->id)->get(['tax_id', 'tax', 'tax_type'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['tax_id' => 3, 'tax' => 9, 'tax_type' => 'percent']]);
    foreach (['product_translations', 'product_variations', 'product_barcodes', 'wms_product_settings'] as $table) {
        expect(DB::table($table)->where('product_id', $copy->id)->exists())->toBeFalse();
    }
    $this->post(route('products.list.duplicate', [$this->product, 'list' => 'all']))->assertRedirect(route('products.all'));
    expect(Product::query()->where('name', 'Olive oil')->count())->toBe(3);
});

test('deleting a product keeps removing its own rows and cart lines, and nothing of another product', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    $customer = createStoreUser('customer');
    $other = createStoreProduct(['name' => 'Other oil', 'thumbnail_img' => null]);
    foreach ([$this->product, $other] as $product) {
        ProductTranslation::create(['product_id' => $product->id, 'lang' => 'en', 'name' => $product->name]);
        DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 9, 'tax_type' => 'percent']);
        DB::table('product_variations')->insert(['product_id' => $product->id, 'name' => '1L']);
        DB::table('product_barcodes')->insert(['product_id' => $product->id, 'barcode' => 'B'.$product->id]);
        DB::table('carts')->insert(['user_id' => $customer->id, 'owner_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);
    }
    expectProductListCachesCleared();
    $this->delete(route('products.list.destroy', [$this->product, 'list' => 'admin', 'page' => 2, 'per_page' => 10]))
        ->assertRedirect(route('products.admin', ['page' => 2, 'per_page' => 10]));
    foreach (['product_translations', 'product_stocks', 'product_taxes', 'product_variations', 'product_barcodes', 'wms_product_settings'] as $table) {
        expect(DB::table($table)->where('product_id', $this->product->id)->exists())->toBeFalse("$table kept the deleted product's rows")
            ->and(DB::table($table)->where('product_id', $other->id)->exists())->toBeTrue("$table lost the other product's rows");
    }
    // Cart lines are soft-deleted (Cart uses SoftDeletes), as before.
    expect(DB::table('carts')->where('product_id', $this->product->id)->whereNull('deleted_at')->exists())->toBeFalse()
        ->and(DB::table('carts')->where('product_id', $this->product->id)->whereNotNull('deleted_at')->count())->toBe(1)
        ->and(DB::table('carts')->where('product_id', $other->id)->whereNull('deleted_at')->count())->toBe(1);
    $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
    $this->assertDatabaseHas('products', ['id' => $other->id]);
});

test('deleting a product takes DELETE only on its new route', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    expect(route('products.list.destroy', 5, false))->toBe('/temp/admin/products/5');
    $before = productListSnapshot();
    $this->get("/temp/admin/products/{$this->product->id}?list=admin")->assertMethodNotAllowed();
    $this->post("/temp/admin/products/{$this->product->id}?list=admin")->assertMethodNotAllowed();
    expect(productListSnapshot())->toEqual($before);
});

test('deleting a product changes nothing while a warehouse pallet references it', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Olive oil']);
    DB::table('product_variations')->insert(['product_id' => $this->product->id, 'name' => '1L']);
    DB::table('product_barcodes')->insert(['product_id' => $this->product->id, 'barcode' => '100027']);
    Pallet::factory()->create(['product_id' => $this->product->id]);
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    $this->withoutExceptionHandling();
    expect(fn () => $this->delete(route('products.list.destroy', [$this->product, 'list' => 'admin'])))->toThrow(QueryException::class);
    expect(productListSnapshot())->toEqual($before);
});

test('deleting a product removes its store barcodes and variations itself, cascades WMS rows and keeps logs and reports without it', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    DB::table('product_variations')->insert(['product_id' => $this->product->id, 'name' => '1L']);
    DB::table('product_barcodes')->insert(['product_id' => $this->product->id, 'barcode' => '100027']);
    $barcode = ProductBarcode::factory()->create(['product_id' => $this->product->id]);
    $log = CellStatusLog::factory()->create(['product_id' => $this->product->id]);
    $report = CellVerificationReport::factory()->create(['expected_product_id' => $this->product->id, 'reported_product_id' => $this->product->id]);
    expectProductListCachesCleared();
    $this->delete(route('products.list.destroy', [$this->product, 'list' => 'all']))->assertRedirect(route('products.all'));
    $this->assertDatabaseMissing('wms_product_barcodes', ['id' => $barcode->id]);
    $this->assertDatabaseMissing('wms_product_settings', ['product_id' => $this->product->id]);
    $this->assertDatabaseMissing('product_barcodes', ['product_id' => $this->product->id]);
    $this->assertDatabaseMissing('product_variations', ['product_id' => $this->product->id]);
    expect(DB::table('cell_status_logs')->where('id', $log->id)->value('product_id'))->toBeNull()
        ->and((array) DB::table('cell_verification_reports')->where('id', $report->id)->first(['expected_product_id', 'reported_product_id']))
        ->toBe(['expected_product_id' => null, 'reported_product_id' => null]);
});

test('deleting a product rolls back every table on persistence failure', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Olive oil']);
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    Product::deleting(fn () => throw new RuntimeException('Delete failure'));
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->delete(route('products.list.destroy', [$this->product, 'list' => 'admin'])))->toThrow(RuntimeException::class);
        expect(productListSnapshot())->toEqual($before);
    } finally {
        Product::flushEventListeners();
    }
});

test('bulk deletion removes every selected product of the list and returns to it', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    $second = createStoreProduct(['name' => 'Second oil', 'thumbnail_img' => null]);
    $kept = createStoreProduct(['name' => 'Kept oil', 'thumbnail_img' => null]);
    ProductTranslation::create(['product_id' => $second->id, 'lang' => 'en', 'name' => 'Second oil']);
    expectProductListCachesCleared();
    $this->post(route('products.list.bulk_destroy', ['list' => 'admin', 'search' => 'oil']), ['id' => [$this->product->id, $second->id]])
        ->assertRedirect(route('products.admin', ['search' => 'oil']));
    $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
    $this->assertDatabaseMissing('products', ['id' => $second->id]);
    $this->assertDatabaseMissing('product_translations', ['product_id' => $second->id]);
    $this->assertDatabaseMissing('product_stocks', ['product_id' => $second->id]);
    $this->assertDatabaseHas('products', ['id' => $kept->id]);
    $this->assertDatabaseHas('product_stocks', ['product_id' => $kept->id]);
});

test('bulk deletion checks every member against the list before deleting any', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    $noise = productListNoise();
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    foreach ([
        ['admin', $noise['seller']->id], ['admin', $noise['digital']->id], ['all', $noise['auction']->id],
        ['all', $noise['wholesale']->id], ['all', 999999],
    ] as [$list, $outsider]) {
        $this->postJson(route('products.list.bulk_destroy', ['list' => $list]), ['id' => [$this->product->id, $outsider]])
            ->assertUnprocessable()->assertJsonValidationErrors('id');
    }
    foreach ([[], ['id' => []], ['id' => 'x'], ['id' => ['a']], ['id' => [$this->product->id, $this->product->id]], ['id' => range(1, 101)]] as $payload) {
        $this->postJson(route('products.list.bulk_destroy', ['list' => 'all']), $payload)->assertUnprocessable();
    }
    $this->postJson(route('products.list.bulk_destroy'), ['id' => [$this->product->id]])->assertUnprocessable()->assertJsonValidationErrors('list');
    expect(productListSnapshot())->toEqual($before);
});

test('bulk deletion changes nothing when one member is still referenced by a pallet', function () {
    actingAsStoreUser(createStoreStaff('product_delete'));
    $referenced = createStoreProduct(['name' => 'Stocked oil', 'thumbnail_img' => null]);
    Pallet::factory()->create(['product_id' => $referenced->id]);
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('products.list.bulk_destroy', ['list' => 'all']), ['id' => [$this->product->id, $referenced->id]]))
        ->toThrow(QueryException::class);
    expect(productListSnapshot())->toEqual($before);
});

test('every row action is a 404 for a product outside its list or missing, with nothing changed', function () {
    actingAsStoreUser(createStoreStaff('product_edit', 'product_duplicate', 'product_delete'));
    $noise = productListNoise();
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    foreach ([
        ['admin', $noise['seller']->id], ['admin', $noise['digital']->id], ['admin', $noise['auction']->id],
        ['all', $noise['auction']->id], ['all', $noise['wholesale']->id], ['admin', 999999], ['all', 999999],
    ] as [$list, $id]) {
        foreach (['published', 'featured', 'for_sale'] as $flag) {
            $this->post(route('products.list.'.$flag, [$id, 'list' => $list]), ['status' => 1])->assertNotFound();
        }
        $this->post(route('products.list.duplicate', [$id, 'list' => $list]))->assertNotFound();
        $this->delete(route('products.list.destroy', [$id, 'list' => $list]))->assertNotFound();
    }
    expect(productListSnapshot())->toEqual($before);
});

foreach (['published', 'featured', 'for_sale', 'duplicate', 'destroy', 'bulk_destroy'] as $action) {
    test("the product list $action action refuses unauthorized callers without changes", function (string $caller) use ($action) {
        $before = productListSnapshot();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'destroy' => $this->delete(route('products.list.destroy', [$this->product, 'list' => 'admin'])),
            'duplicate' => $this->post(route('products.list.duplicate', [$this->product, 'list' => 'admin'])),
            'bulk_destroy' => $this->post(route('products.list.bulk_destroy', ['list' => 'admin']), ['id' => [$this->product->id]]),
            default => $this->post(route('products.list.'.$action, [$this->product, 'list' => 'admin']), ['status' => 0]),
        };
        assertStoreCallerRefused($response, $caller);
        expect(productListSnapshot())->toEqual($before);
    })->with('refused store admin callers');
}

test('each product permission authorizes only its own list actions', function () {
    Artisan::shouldReceive('call')->never();
    $before = productListSnapshot();
    actingAsStoreUser(createStoreStaff('show_in_house_products', 'show_all_products', 'add_new_product', 'product_duplicate'));
    $this->post(route('products.list.published', [$this->product, 'list' => 'admin']), ['status' => 0])->assertForbidden();
    $this->delete(route('products.list.destroy', [$this->product, 'list' => 'admin']))->assertForbidden();
    $this->post(route('products.list.bulk_destroy', ['list' => 'admin']), ['id' => [$this->product->id]])->assertForbidden();
    actingAsStoreUser(createStoreStaff('product_edit', 'product_delete'));
    $this->post(route('products.list.duplicate', [$this->product, 'list' => 'admin']))->assertForbidden();
    actingAsStoreUser(createStoreStaff('product_duplicate', 'product_delete'));
    foreach (['published', 'featured', 'for_sale'] as $flag) {
        $this->post(route('products.list.'.$flag, [$this->product, 'list' => 'admin']), ['status' => 0])->assertForbidden();
    }
    expect(productListSnapshot())->toEqual($before);
});

test('WMS authentication grants no product list action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = productListSnapshot();
    foreach ([
        ['GET', route('products.admin')], ['GET', route('products.all')],
        ['POST', route('products.list.published', [$this->product, 'list' => 'admin'])],
        ['POST', route('products.list.featured', [$this->product, 'list' => 'admin'])],
        ['POST', route('products.list.for_sale', [$this->product, 'list' => 'admin'])],
        ['POST', route('products.list.duplicate', [$this->product, 'list' => 'admin'])],
        ['DELETE', route('products.list.destroy', [$this->product, 'list' => 'admin'])],
        ['POST', route('products.list.bulk_destroy', ['list' => 'admin'])],
        ['GET', route('products.create')], ['GET', route('products.admin.edit', $this->product->id)], ['GET', route('products.seller')],
    ] as [$method, $url]) {
        $this->call($method, $url, ['status' => 0, 'id' => [$this->product->id]])->assertRedirect(route('shop.login'));
    }
    expect(productListSnapshot())->toEqual($before);
});

test('the create and edit forms are Inertia on the store controller with their route names and gates', function () {
    actingAsStoreUser(createStoreStaff('add_new_product', 'product_edit'));
    expect(route('products.create', [], false))->toBe('/temp/admin/products/create')
        ->and(route('products.admin.edit', ['product' => 5, 'lang' => 'en'], false))->toBe('/temp/admin/products/admin/5/edit?lang=en')
        ->and(route('products.seller.edit', ['product' => 5], false))->toBe('/temp/admin/products/seller/5/edit')
        ->and(route('products.update', 5, false))->toBe('/temp/admin/products/update/5')
        ->and(route('products.edit_variant_combinations', 5, false))->toBe('/temp/admin/products/5/variant-combinations')
        ->and(Route::getRoutes()->getByName('products.update')->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('products.edit_variant_combinations')->methods())->toBe(['POST'])
        ->and(Route::has('products.sku_combination_edit'))->toBeFalse();
    foreach (['products.create' => 'create', 'products.admin.edit' => 'edit', 'products.seller.edit' => 'sellerEdit', 'products.update' => 'update', 'products.edit_variant_combinations' => 'editVariantCombinations'] as $name => $action) {
        expect(Route::getRoutes()->getByName($name)->getActionName())->toBe(ProductController::class.'@'.$action);
    }
    expect(collect(ProductController::middleware())->map(fn ($entry) => [$entry->middleware, $entry->only]))
        ->toContain(['permission:product_edit', ['edit', 'sellerEdit', 'update', 'editVariantCombinations']]);
    foreach (['admin_product_edit', 'seller_product_edit', 'update', 'sku_combination_edit'] as $method) {
        expect(method_exists(LegacyProductController::class, $method))->toBeFalse();
    }
    $this->get(route('products.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/Products/Create'));
    $this->get(route('products.create'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'en']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/Products/Edit')->where('listType', 'admin'));
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'en']), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
    actingAsStoreUser(createStoreStaff('show_in_house_products'));
    $this->get(route('products.create'))->assertForbidden();
    $this->get(route('products.admin.edit', $this->product->id))->assertForbidden();
});

test('the product fetch button keeps the legacy JSON endpoint and its one-request-at-a-time answer', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $this->postJson(route('products.fetch'))->assertOk()->assertExactJson(['success' => true]);
    $this->assertDatabaseHas('cron_jobs', ['command' => 'fetching-products-by-admin', 'status' => 'pending']);
    $this->postJson(route('products.fetch'))->assertServerError()->assertExactJson(['success' => false]);
    expect(DB::table('cron_jobs')->count())->toBe(1);
});

test('the product fetch import is refused to a caller without add_new_product and queues nothing', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('products.fetch')), $caller);

    expect(DB::table('cron_jobs')->count())->toBe(0);
})->with('refused store admin callers');

test('the product fetch import answers a guest JSON request with 401 and queues nothing', function () {
    $this->postJson(route('products.fetch'))->assertUnauthorized();

    expect(DB::table('cron_jobs')->count())->toBe(0);
});

test('the product fetch import is refused to staff holding other product permissions only', function () {
    actingAsStoreUser(createStoreStaff('show_all_products', 'product_edit', 'product_bulk_import'));

    $this->postJson(route('products.fetch'))->assertForbidden();

    expect(DB::table('cron_jobs')->count())->toBe(0);
});

test('the product fetch import is allowed for a Super Admin', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->postJson(route('products.fetch'))->assertOk()->assertExactJson(['success' => true]);

    $this->assertDatabaseHas('cron_jobs', ['command' => 'fetching-products-by-admin', 'status' => 'pending']);
});

test('the list migration keeps storefront, seller and app product contracts', function () {
    expect(view()->exists('shop.backend.product.products.index'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.products.sku_combinations'))->toBeTrue()
        ->and(view()->exists('shop.backend.product.products.sku_combinations_edit'))->toBeTrue()
        // Only the deleted Blade edit form included the choice script.
        ->and(view()->exists('shop.backend.product.products.choice_options_script'))->toBeFalse()
        ->and(Route::getRoutes()->getByName('seller.products')->getActionName())->toBe(SellerProductController::class.'@index')
        ->and(Route::getRoutes()->getByName('seller.products.published')->getActionName())->toBe(SellerProductController::class.'@updatePublished')
        ->and(Route::getRoutes()->match(Request::create('/temp/api/v2/products/'.$this->product->id))->getActionName())
        ->toBe(ApiProductController::class.'@show');
    $this->get(route('product', 'olive-oil'))->assertOk()->assertSee('Olive oil');
});
