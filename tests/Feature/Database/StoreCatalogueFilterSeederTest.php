<?php

use App\Models\StoreUser;
use Database\Seeders\StoreCatalogueFilterSeeder;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

test('catalogue fixtures satisfy real admin switches presets category and seller filters', function () {
    $category = DB::table('categories')->insertGetId(['name' => 'Existing category', 'slug' => 'existing-category']);
    $seller = createStoreUser('seller');
    $this->seed(StoreCatalogueFilterSeeder::class);
    actingAsStoreUser(createStoreStaff('show_in_house_products', 'show_all_products', 'show_seller_products', 'show_digital_products'));

    foreach (['products.admin', 'products.seller'] as $route) {
        foreach ([0, 1] as $active) {
            foreach ([0, 1] as $forSale) {
                foreach ([0, 1] as $featured) {
                    $response = $this->get(route($route, ['active' => $active, 'for_sale' => $forSale, 'featured' => $featured, 'page' => 2]));
                    $response->assertOk();
                    expect($response->inertiaProps('products.meta.total'))->toBeGreaterThan(20)
                        ->and($response->inertiaProps('products.meta.current_page'))->toBe(2)
                        ->and($response->inertiaProps('products.data'))->not->toBeEmpty();
                    foreach ($response->inertiaProps('products.data') as $product) {
                        expect((int) $product['published'])->toBe($active)
                            ->and((int) $product['for_sale'])->toBe($forSale)
                            ->and((int) $product['featured'])->toBe($featured);
                    }
                }
            }
        }
        expect($this->get(route($route, ['category_id' => $category, 'page' => 2]))->assertOk()->inertiaProps('products.data'))->not->toBeEmpty();
    }
    foreach (['allProducts', 'allDeleted', 'allActive', 'allForSale', 'allFeatured'] as $preset) {
        expect($this->get(route('products.all', [$preset => 1, 'page' => 2]))->assertOk()->inertiaProps('products.data'))->not->toBeEmpty();
    }
    foreach (['rating', 'num_of_sale', 'unit_price'] as $column) {
        foreach (['asc', 'desc'] as $direction) {
            expect($this->get(route('products.all', ['type' => $column.','.$direction, 'page' => 2]))->assertOk()->inertiaProps('products.data'))->not->toBeEmpty();
        }
    }
    foreach ([0, 1] as $approved) {
        expect($this->get(route('products.seller', ['approved' => $approved, 'page' => 2]))->assertOk()->inertiaProps('products.data'))->not->toBeEmpty();
    }
    expect($this->get(route('products.seller', ['user_id' => $seller->id, 'page' => 2]))->assertOk()->inertiaProps('products.data'))->not->toBeEmpty();
});

test('catalogue fixtures satisfy real public category brand color attribute price and sort filters', function () {
    $category = DB::table('categories')->insertGetId(['name' => 'Existing category', 'slug' => 'existing-category']);
    $brand = DB::table('brands')->insertGetId(['name' => 'Existing brand', 'slug' => 'existing-brand']);
    DB::table('colors')->insert(['name' => 'Existing red', 'code' => '#FF0000']);
    $attribute = DB::table('attributes')->insertGetId(['name' => 'Size']);
    DB::table('attribute_values')->insert(['attribute_id' => $attribute, 'value' => 'كبير']);
    $this->seed(StoreCatalogueFilterSeeder::class);

    foreach ([
        ['brand' => 'existing-brand'], ['color' => '#FF0000'], ['selected_attribute_values' => ['كبير']],
        ['min_price' => 20, 'max_price' => 30], ['keyword' => 'pantry'],
        ...array_map(fn ($sort) => ['sort_by' => $sort], ['newest', 'oldest', 'price-asc', 'price-desc']),
    ] as $filters) {
        $this->get(route('search', [...$filters, 'page' => 2]))->assertOk()->assertViewHas('products', fn ($products) => $products->total() > $products->perPage() && $products->currentPage() === 2 && $products->isNotEmpty());
    }
    $this->get(route('products.category', ['category_slug' => 'existing-category', 'page' => 2]))->assertOk()
        ->assertViewHas('products', fn ($products) => $products->contains(fn ($product) => (int) $product->category_id === $category));
    $this->get(route('products.brand', ['brand_slug' => 'existing-brand', 'page' => 2]))->assertOk()
        ->assertViewHas('products', fn ($products) => $products->contains(fn ($product) => (int) $product->brand_id === $brand));
});

test('classified fixtures match condition category brand and every sort option', function () {
    DB::table('business_settings')->insert(['type' => 'classified_product', 'value' => '1']);
    $this->seed(StoreCatalogueFilterSeeder::class);
    foreach ([['condition' => 'new'], ['condition' => 'used'], ...array_map(fn ($sort) => ['sort_by' => $sort], range(1, 6))] as $filters) {
        $this->get(route('customer.products', [...$filters, 'page' => 2]))->assertOk()
            ->assertViewHas('customer_products', fn ($products) => $products->total() > $products->perPage() && $products->currentPage() === 2 && $products->isNotEmpty());
    }
});

test('catalogue fixtures return results through real API search sorts ranges and barcode lookup', function () {
    $this->seed(StoreCatalogueFilterSeeder::class);
    foreach (['price_low_to_high', 'price_high_to_low', 'new_arrival', 'popularity', 'top_rated'] as $sort) {
        $this->getJson('/temp/api/v2/products/search?'.http_build_query(['sort_key' => $sort, 'min' => 20, 'max' => 30, 'page' => 2]))
            ->assertOk()->assertJson(fn ($json) => $json->has('data')->where('success', true)->etc());
        expect($this->getJson('/temp/api/v2/products/search?'.http_build_query(['sort_key' => $sort]))->json('data'))->not->toBeEmpty();
    }
    $this->postJson('/temp/api/v2/products/search-barcode', ['barcode' => '2000000000015'])
        ->assertOk()->assertJson(fn ($json) => $json->has('data', 1)->etc());
});

test('catalogue reruns preserve existing products and users and do not duplicate fixtures', function () {
    $user = createStoreUser('admin');
    $product = createStoreProduct(['user_id' => $user->id, 'name' => 'Preserved product']);
    $userBefore = $user->fresh()->getAttributes();
    $productBefore = $product->fresh()->getAttributes();
    $this->seed(StoreCatalogueFilterSeeder::class);
    $counts = collect(['products', 'users', 'categories', 'brands', 'colors', 'product_stocks', 'shops', 'sellers'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
    $this->seed(StoreCatalogueFilterSeeder::class);

    expect($user->fresh()->getAttributes())->toBe($userBefore)
        ->and($product->fresh()->getAttributes())->toBe($productBefore);
    foreach ($counts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    $data = app(StoreFilterSeedData::class);
    expect(StoreUser::query()->find($data->find('users', 'seller')))->not->toBeNull();
});
