<?php

use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CombinedOrder;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\Wishlist;
use App\Support\StorefrontUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

/** @return array{Category, Brand, Product} */
function nullSlugCatalogue(?string $slug = null): array
{
    $category = Category::forceCreate(['name' => 'D15 Category', 'slug' => 'd15-category', 'level' => 0, 'parent_id' => 0]);
    $brand = Brand::forceCreate(['name' => 'D15 Brand', 'slug' => 'd15-brand']);
    $product = createStoreProduct(['name' => 'D15 Product', 'serial' => 'd15-'.Str::uuid(), 'slug' => $slug, 'category_id' => $category->id, 'brand_id' => $brand->id]);

    return [$category->fresh(), $brand->fresh(), $product->fresh()];
}

function nullSlugPath(string $kind, int $id): string
{
    return "/temp/{$kind}/~id-{$id}?catalog_id={$id}";
}

function nullSlugOffer(Product $base, Product ...$products): void
{
    $offer = Offer::forceCreate(['title' => 'D15 Offer', 'offer_type' => 'products_offer', 'is_active' => 1]);
    foreach ([$base, ...$products] as $item) {
        OfferProduct::forceCreate(['offer_id' => $offer->id, 'product_id' => $item->id, 'quantity' => 1, 'is_base_product' => $item->is($base) ? 1 : 0]);
    }
}

test('slugless products retain links on storefront pages', function (string $page) {
    [$category, $brand, $product] = nullSlugCatalogue();
    $customer = createStoreUser('customer');
    $path = match ($page) {
        'listing' => '/temp/search',
        'category' => '/temp/category/d15-category',
        'brand' => '/temp/brand/d15-brand',
        'search' => '/temp/search?keyword=D15',
        'ajax search' => '/temp/ajax-search?search=D15',
        'compare' => '/temp/compare',
        'wishlist' => '/temp/wishlists',
        'customer order' => '',
        'confirmation' => '/temp/checkout/order-confirmed',
    };
    if ($page === 'compare') {
        $this->withSession(['compare' => [$product->id]]);
    }
    if (in_array($page, ['wishlist', 'customer order', 'confirmation'], true)) {
        actingAsStoreUser($customer);
    }
    if ($page === 'wishlist') {
        Wishlist::forceCreate(['user_id' => $customer->id, 'product_id' => $product->id]);
    }
    if (in_array($page, ['customer order', 'confirmation'], true)) {
        $line = orderStoreProduct($customer, $product);
        $line->forceFill(['product_serial' => $product->serial])->save();
        $line->order->forceFill(['shipping_address' => json_encode(['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone, 'address' => 'Test Street', 'city' => 'Test City', 'state' => 'Test State', 'country' => 'Netherlands', 'postal_code' => '1234AB'])])->save();
        if ($page === 'customer order') {
            $path = route('purchase_history.details', encrypt($line->order_id), false);
        } else {
            $combined = CombinedOrder::forceCreate(['user_id' => $customer->id, 'grand_total' => 5, 'shipping_address' => '{}']);
            $line->order->forceFill(['combined_order_id' => $combined->id])->save();
            $this->withSession(['combined_order_id' => $combined->id]);
        }
    }

    if ($page === 'confirmation') {
        $this->get($path)->assertOk()->assertSee('Thank You for Your Order!')->assertViewHas('combined_order', fn (CombinedOrder $resolved) => $resolved->is($combined));

        return;
    }
    ($page === 'ajax search' ? $this->post('/temp/ajax-search', ['search' => 'D15']) : $this->get($path))->assertOk()->assertSee('D15 Product')->assertSee(nullSlugPath('product', $product->id));
})->with(['listing', 'category', 'brand', 'search', 'ajax search', 'compare', 'wishlist', 'customer order', 'confirmation']);

test('slugless categories and brands keep their index links', function (string $kind, string $index) {
    [$category, $brand] = nullSlugCatalogue('normal-product');
    $record = $kind === 'category' ? $category : $brand;
    $record->forceFill(['slug' => null])->save();

    $this->get('/temp/'.$index)->assertOk()->assertSee(nullSlugPath($kind, $record->id));
})->with(['category' => ['category', 'categories'], 'brand' => ['brand', 'brands']]);

test('related slugless products do not break a product page', function () {
    [$category, $brand, $related] = nullSlugCatalogue();
    createStoreProduct(['name' => 'Displayed', 'slug' => 'displayed', 'category_id' => $category->id]);

    $this->get('/temp/product/displayed')->assertOk()->assertSee('Related products')->assertSee(nullSlugPath('product', $related->id));
});

test('offered slugless products do not break a product page', function () {
    [$category, $brand, $offered] = nullSlugCatalogue();
    $displayed = createStoreProduct(['name' => 'Displayed', 'slug' => 'displayed', 'category_id' => null]);
    nullSlugOffer($displayed, $offered);

    $this->get('/temp/product/displayed')->assertOk()->assertSee('Offered products')->assertSee(nullSlugPath('product', $offered->id));
});

test('the cart renders a slugless product without discarding it', function () {
    [$category, $brand, $product] = nullSlugCatalogue();
    $customer = createStoreUser('customer');
    actingAsStoreUser($customer);
    Cart::forceCreate(['user_id' => $customer->id, 'owner_id' => $product->user_id, 'product_id' => $product->id, 'quantity' => 1, 'variation' => '', 'price' => 2.5, 'tax' => 0, 'shipping_cost' => 0]);

    $this->get('/temp/cart')->assertOk()->assertSee('D15 Product');
});

test('slugless catalogue destinations resolve with matching canonical and structured data', function (string $kind, ?string $slug) {
    [$category, $brand, $product] = nullSlugCatalogue('normal-product');
    $record = match ($kind) {
        'product' => $product, 'category' => $category, 'brand' => $brand
    };
    $record->forceFill(['slug' => $slug])->save();
    $before = $record->fresh()->getRawOriginal();
    $path = nullSlugPath($kind, $record->id);
    $response = $this->get($path)->assertOk()->assertSee('D15 '.ucfirst($kind));
    $xpath = htmlXPath($response);
    $canonical = $xpath->evaluate('string(//link[@rel="canonical"]/@href)');
    expect($canonical)->toBe(config('app.url').$path)
        ->and($xpath->evaluate('string(//meta[@property="og:url"]/@content)'))->toBe($canonical);
    $schemas = [];
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
        $schemas[] = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
    }
    expect(json_encode($schemas, JSON_UNESCAPED_SLASHES))->toContain($canonical)
        ->and($record->fresh()->getRawOriginal())->toBe($before);
})->with(['product null' => ['product', null], 'product blank' => ['product', ''], 'category null' => ['category', null], 'category blank' => ['category', ''], 'brand null' => ['brand', null], 'brand blank' => ['brand', '']]);

test('the sitemap explicitly excludes null and blank catalogue slugs and still lists normal rows', function () {
    foreach ([null, ''] as $slug) {
        [$category, $brand] = nullSlugCatalogue($slug);
        $category->forceFill(['slug' => $slug])->save();
        $brand->forceFill(['slug' => $slug])->save();
    }
    nullSlugCatalogue('sitemap-product');
    $index = $this->get('/temp/sitemap.xml')->assertOk();
    $xml = simplexml_load_string($index->getContent());
    foreach ($xml->sitemap as $sitemap) {
        $this->get(parse_url((string) $sitemap->loc, PHP_URL_PATH))->assertOk()->assertDontSee('~id-')->assertDontSee('catalog_id');
    }
    $this->get('/temp/sitemap-products-1.xml')->assertOk()->assertSee('/temp/product/sitemap-product');
});

test('the URL helper preserves exact URLs for normal and numeric slugs without querying or writing', function (string $slug) {
    [$category, $brand, $product] = nullSlugCatalogue($slug);
    $category->forceFill(['slug' => $slug])->save();
    $brand->forceFill(['slug' => $slug])->save();
    DB::enableQueryLog();
    DB::flushQueryLog();
    foreach (['product' => $product, 'products.category' => $category, 'products.brand' => $brand] as $route => $record) {
        expect(StorefrontUrl::to($record))->toBe(route($route, $slug))
            ->and(StorefrontUrl::to($record, false))->toBe(route($route, $slug, false));
    }
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
})->with(['a-normal-slug', '123', '0', '~id-123']);

test('fallback addresses cannot collide with existing literal slugs', function () {
    [$category, $brand, $product] = nullSlugCatalogue();
    $other = createStoreProduct(['name' => 'Literal token', 'slug' => '~id-'.$product->id, 'category_id' => null]);
    $this->get(nullSlugPath('product', $product->id))->assertOk()->assertViewHas('detailedProduct', fn (Product $resolved) => $resolved->is($product));
    $this->get('/temp/product/~id-'.$product->id)->assertOk()->assertViewHas('detailedProduct', fn (Product $resolved) => $resolved->is($other));
});

test('fallback lookup retains product visibility and rejects missing or mismatched ids', function () {
    [$category, $brand, $product] = nullSlugCatalogue();
    foreach (['published' => 0, 'approved' => 0, 'auction_product' => 1] as $column => $value) {
        $hidden = createStoreProduct(['slug' => null, $column => $value]);
        $this->get(nullSlugPath('product', $hidden->id))->assertNotFound();
    }
    foreach (['product', 'category', 'brand'] as $kind) {
        $this->get(nullSlugPath($kind, 2147483647))->assertNotFound();
    }
    $this->get('/temp/product/~id-'.$product->id.'?catalog_id=2147483647')->assertNotFound();
});

test('home and category navigation keep slugless catalogue links at every category level', function () {
    [$category, $brand, $product] = nullSlugCatalogue();
    $category->forceFill(['slug' => null, 'featured' => 1])->save();
    $brand->forceFill(['slug' => null])->save();
    $child = Category::forceCreate(['name' => 'D15 Child', 'slug' => null, 'parent_id' => $category->id, 'level' => 1]);
    $grandchild = Category::forceCreate(['name' => 'D15 Grandchild', 'slug' => null, 'parent_id' => $child->id, 'level' => 2]);
    storeSetting('top_brands', json_encode([$brand->id]));
    $this->get('/temp')->assertOk()->assertSee(nullSlugPath('category', $category->id));
    $this->get('/temp/categories')->assertOk()->assertSee(nullSlugPath('category', $child->id))->assertSee(nullSlugPath('category', $grandchild->id));
});

test('related cards render multiple slugless products for a verified customer', function () {
    [$category, $brand, $related] = nullSlugCatalogue();
    $other = createStoreProduct(['slug' => '', 'category_id' => $category->id]);
    createStoreProduct(['name' => 'Displayed', 'slug' => 'displayed', 'category_id' => $category->id]);
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => 1])->save();
    actingAsStoreUser($customer->fresh());

    $this->get('/temp/product/displayed')->assertOk()->assertSee(nullSlugPath('product', $related->id))->assertSee(nullSlugPath('product', $other->id));
});

test('rendering a feed-origin slugless product preserves every product column', function () {
    $product = Product::factory()->feedOrigin()->create(['slug' => null, 'approved' => 1, 'auction_product' => 0, 'category_id' => null, 'brand_id' => null]);
    $before = $product->fresh()->getRawOriginal();

    $this->get(StorefrontUrl::to($product, false))->assertOk()->assertSee($product->name);
    expect($product->fresh()->getRawOriginal())->toBe($before);
});

test('multiple offered products retain links and prices for a verified customer', function () {
    [$category, $brand, $offered] = nullSlugCatalogue();
    $other = createStoreProduct(['slug' => '', 'category_id' => $category->id]);
    $displayed = createStoreProduct(['slug' => 'displayed-offer', 'category_id' => null]);
    nullSlugOffer($displayed, $offered, $other);
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => 1])->save();
    actingAsStoreUser($customer->fresh());

    $this->get('/temp/product/displayed-offer')->assertOk()->assertSee('Offered products')->assertSee('You will get')->assertSee(nullSlugPath('product', $offered->id))->assertSee(nullSlugPath('product', $other->id));
});

test('fallback canonicals preserve their identity alongside content queries and discard tracking', function () {
    [$category] = nullSlugCatalogue('normal-product');
    $category->forceFill(['slug' => null])->save();
    $path = nullSlugPath('category', $category->id);
    $response = $this->get($path.'&sort_by=newest&utm_source=D15')->assertOk();
    $xpath = htmlXPath($response);

    expect($xpath->evaluate('string(//link[@rel="canonical"]/@href)'))->toBe(config('app.url').$path.'&sort_by=newest')
        ->and($xpath->evaluate('string(//meta[@name="robots"]/@content)'))->toBe('noindex, follow');
});
