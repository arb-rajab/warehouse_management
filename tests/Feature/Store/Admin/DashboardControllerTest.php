<?php

use App\Http\Controllers\Store\Admin\DashboardController;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\StoreUser;
use App\Support\StorefrontUrl;
use App\Utility\CategoryUtility;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
});

test('the dashboard renders an empty shop with typed empty series under strict CSP', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $response = $this->get(route('shop.admin.dashboard'))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Dashboard/Index')
        ->where('stats', ['customers' => 0, 'orders' => 0, 'categories' => 0, 'brands' => 0])
        ->where('series', [
            'products' => [0, 0], 'sources' => [0, 0],
            'sales' => ['labels' => [], 'values' => []], 'stock' => ['labels' => [], 'values' => []],
            'visits' => ['labels' => [], 'values' => []],
        ])->where('topProducts', [])->where('showSmtpAlert', false));
    expect($response->headers->get('Content-Security-Policy-Report-Only') ?? $response->headers->get('Content-Security-Policy'))->toContain("script-src 'self';");
    $this->get(route('shop.admin.dashboard'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
});

test('the dashboard payload preserves totals prices translations and product filtering without N plus one queries', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $customer = createStoreUser();
    createStoreUser()->forceFill(['email_verified_at' => null])->save();
    createStoreUser('seller');
    createStoreOrder($customer);
    DB::table('brands')->insert(['name' => 'Brand', 'slug' => 'brand']);
    $root = Category::forceCreate(['name' => 'Root', 'parent_id' => 0, 'level' => 0]);
    CategoryTranslation::create(['category_id' => $root->id, 'lang' => 'en', 'name' => 'Translated root']);
    $child = Category::forceCreate(['name' => 'Child', 'parent_id' => $root->id, 'level' => 1]);
    $product = createStoreProduct(['name' => 'Oil', 'slug' => 'oil', 'thumbnail_img' => null, 'added_by' => 'admin', 'category_id' => $child->id, 'num_of_sale' => 7, 'current_stock' => 9, 'from_api' => 1, 'unit_price' => 20, 'discount' => 10, 'discount_type' => 'percent', 'rating' => 4.5]);
    ProductTranslation::create(['product_id' => $product->id, 'lang' => 'en', 'name' => 'Translated oil']);
    DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 10, 'tax_type' => 'percent']);
    DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => 'large', 'qty' => 3, 'price' => 20]);
    createStoreProduct(['name' => 'Hidden', 'published' => false, 'added_by' => 'admin', 'category_id' => $root->id, 'num_of_sale' => 2, 'current_stock' => 5, 'from_api' => 0]);
    createStoreProduct(['name' => 'Auction', 'added_by' => 'admin', 'auction_product' => 1, 'category_id' => $root->id, 'num_of_sale' => 3, 'current_stock' => 6, 'from_api' => 0]);
    createStoreProduct(['name' => 'Seller', 'added_by' => 'seller', 'category_id' => $root->id, 'num_of_sale' => 4, 'current_stock' => 8, 'from_api' => 0]);
    DB::table('business_settings')->insert(['type' => 'total_visits', 'value' => '{"NL":8,"SY":3}']);
    Cache::forget('business_settings');
    Cache::put('cached_graph_data', ['num_of_sale_data' => '999,', 'qty_data' => '999,'], 86400);
    DB::enableQueryLog();
    $response = $this->get(route('shop.admin.dashboard'))->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats', ['customers' => 1, 'orders' => 1, 'categories' => 2, 'brands' => 1])
        ->where('series', [
            'products' => [4, 3], 'sources' => [1, 3],
            'sales' => ['labels' => ['Translated root'], 'values' => [16]],
            'stock' => ['labels' => ['Translated root'], 'values' => [31]],
            'visits' => ['labels' => ['NL', 'SY'], 'values' => [8, 3]],
        ])->has('topProducts', 1)->has('topProducts.0', fn (Assert $p) => $p
        ->where('id', $product->id)->where('name', 'Translated oil')->where('public_url', route('product', 'oil'))
        ->where('thumbnail_url', static_asset('assets/img/placeholder.jpg'))
        ->where('base_price', single_price(22))->where('discounted_price', single_price(19.8))->where('rating', 4.5))
        ->where('showSmtpAlert', false));
    expect($queries->filter(fn ($q) => str_contains($q['query'], 'from "product_translations"')))->toHaveCount(1)
        ->and($queries->filter(fn ($q) => str_contains($q['query'], 'from "product_taxes"')))->toHaveCount(1);
});

test('category aggregates equal the former per category product and variant totals', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $roots = collect(range(1, 2))->map(fn ($i) => Category::forceCreate(['name' => 'Root '.$i, 'parent_id' => 0, 'level' => 0]));
    foreach ($roots as $root) {
        $child = Category::forceCreate(['name' => 'Child', 'parent_id' => $root->id, 'level' => 1]);
        $grandchild = Category::forceCreate(['name' => 'Grandchild', 'parent_id' => $child->id, 'level' => 2]);
        foreach ([$root, $child, $grandchild] as $category) {
            $product = createStoreProduct(['category_id' => $category->id, 'num_of_sale' => $category->id, 'current_stock' => $category->id * 2, 'published' => false]);
            DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => 'other', 'qty' => 3, 'price' => 1]);
        }
        Product::factory()->create(['category_id' => $root->id, 'num_of_sale' => 6, 'published' => false]);
    }
    createStoreProduct(['category_id' => 987654, 'num_of_sale' => 1000, 'current_stock' => 1000, 'published' => false]);
    $expected = $roots->map(function (Category $root): array {
        $products = Product::query()->with('stocks')->whereIn('category_id', [...CategoryUtility::children_ids($root->id), $root->id])->get();

        return ['sales' => (int) $products->sum('num_of_sale'), 'stock' => (int) $products->sum(fn ($product) => $product->stocks->sum('qty'))];
    });
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('series.sales.values', $expected->pluck('sales')->all())
        ->where('series.stock.values', $expected->pluck('stock')->all()));
});

test('dashboard totals expire after 24 hours and cache visits labels together with values', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $root = Category::forceCreate(['name' => 'Root', 'parent_id' => 0, 'level' => 0]);
    $product = createStoreProduct(['category_id' => $root->id, 'num_of_sale' => 3, 'published' => false]);
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('series.sales.values', [3]));
    $product->forceFill(['num_of_sale' => 9])->save();
    DB::table('business_settings')->insert(['type' => 'total_visits', 'value' => '{"SY":12}']);
    Cache::forget('business_settings');
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('series.sales.values', [3])->where('series.visits', ['labels' => [], 'values' => []]));
    $this->travel(24)->hours();
    $this->travel(1)->seconds();
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('series.sales.values', [9])->where('series.visits', ['labels' => ['SY'], 'values' => [12]]));
});

test('top products are limited to twelve ranked published products and translations are eager loaded for all', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $products = collect(range(1, 14))->map(fn ($i) => createStoreProduct(['name' => 'Product '.$i, 'added_by' => 'admin', 'num_of_sale' => $i]));
    createStoreProduct(['name' => 'Unpublished', 'published' => false, 'added_by' => 'admin', 'num_of_sale' => 100]);
    DB::enableQueryLog();
    $response = $this->get(route('shop.admin.dashboard'))->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    $response->assertInertia(fn (Assert $p) => $p->has('topProducts', 12)->where('topProducts.0.id', $products->last()->id)->where('topProducts.11.id', $products[2]->id));
    expect($queries->filter(fn ($q) => str_contains($q['query'], 'from "product_translations"')))->toHaveCount(1);
});

test('SMTP warning keeps its exact permission and both empty credentials condition', function (?string $username, ?string $password, bool $permission, bool $expected) {
    actingAsStoreUser(createStoreStaff(...($permission ? ['admin_dashboard', 'smtp_settings'] : ['admin_dashboard'])));
    config(['mail.mailers.smtp.username' => $username, 'mail.mailers.smtp.password' => $password]);
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('showSmtpAlert', $expected));
})->with([
    [null, null, true, true], ['', '', true, true], [null, null, false, false],
    ['user', null, true, false], [null, 'pass', true, false], ['user', 'pass', true, false],
]);

test('dashboard refuses unauthorized callers without changing database rows', function (string $caller) {
    createStoreProduct(['name' => 'Retained inventory', 'published' => false, 'thumbnail_img' => null]);
    actingAsRefusedStoreCaller($caller);
    $before = DB::table('products')->get()->toArray();
    $stocks = DB::table('product_stocks')->get()->toArray();
    assertStoreCallerRefused($this->get(route('shop.admin.dashboard')), $caller);
    expect(DB::table('products')->get()->toArray())->toEqual($before)
        ->and(DB::table('product_stocks')->get()->toArray())->toEqual($stocks)
        ->and(Cache::has(DashboardController::GRAPH_CACHE_KEY))->toBeFalse();
})->with('refused store admin callers');

test('dashboard route keeps its URI and auth boundary and the obsolete controller and view are gone', function () {
    $route = app('router')->getRoutes()->getByName('shop.admin.dashboard');
    expect($route->uri())->toBe('temp/admin')->and($route->methods())->toBe(['GET', 'HEAD'])
        ->and($route->getActionName())->toBe(DashboardController::class.'@index')
        ->and($route->gatherMiddleware())->toContain('auth', 'admin', 'permission:admin_dashboard')
        ->and(base_path('app/Http/Controllers/AdminController.php'))->not->toBeFile()
        ->and(base_path('resources/views/shop/backend/dashboard.blade.php'))->not->toBeFile();
});

test('dashboard shell exposes the current account and only its notifications', function () {
    $admin = actingAsStoreUser(createStoreAdmin());
    $other = createStoreAdmin();
    foreach ([[$admin, 'ORD-MINE'], [$other, 'ORD-THEIRS']] as [$recipient, $code]) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'App\Notifications\OrderNotification',
            'notifiable_type' => StoreUser::class, 'notifiable_id' => $recipient->id,
            'data' => json_encode(['order_id' => 1, 'order_code' => $code, 'status' => 'placed']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('auth.user.name', $admin->name)->has('store.notifications.items', 1)
        ->where('store.notifications.items.0.order_code', 'ORD-MINE'));
});

test('dashboard shell identifies vendor cache clear access without granting it to ordinary admins', function (bool $vendor) {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: $vendor));
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('store.isRaizerAdmin', $vendor));
})->with([true, false]);

test('published products without a storefront slug do not break the dashboard', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $product = createStoreProduct(['name' => 'Warehouse product', 'slug' => null, 'added_by' => 'admin', 'thumbnail_img' => null]);
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->has('topProducts', 1)->where('topProducts.0.id', $product->id)->where('topProducts.0.public_url', StorefrontUrl::to($product)));
});
