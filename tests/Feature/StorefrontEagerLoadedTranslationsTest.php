<?php

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Strict mode (local and testing) refuses a lazy load on a result set of two or
 * more models. The storefront sections below read each product's translated
 * name from several products at once, so each one must eager load
 * `product_translations` itself: they threw only in dev and CI, while
 * production quietly ran one extra query per product.
 */
beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

/**
 * @param  array<string, mixed>  $attributes
 * @return list<Product>
 */
function translatedProducts(int $count, array $attributes = []): array
{
    $products = [];

    foreach (range(1, $count) as $i) {
        $product = createStoreProduct([
            'published' => 1,
            'approved' => 1,
            'added_by' => 'admin',
            ...$attributes,
            'name' => ($attributes['name'] ?? 'Eager product').' '.$i,
        ]);
        DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'ar', 'name' => 'منتج '.$i]);
        $products[] = $product;
    }

    return $products;
}

test('the featured section lists several featured products', function () {
    translatedProducts(3, ['featured' => 1]);
    translatedProducts(1, ['featured' => 0, 'name' => 'Not featured']);

    $this->post(route('home.section.featured'))
        ->assertOk()
        ->assertSee('Eager product 1')
        ->assertSee('Eager product 3')
        ->assertDontSee('Not featured');
});

test('the best selling section lists several offer products', function () {
    storeSetting('best_selling', 1);
    translatedProducts(3, ['isOfferProduct' => 1]);
    translatedProducts(1, ['isOfferProduct' => 0, 'name' => 'Not an offer']);

    $this->post(route('home.section.best_selling'))
        ->assertOk()
        ->assertSee('Eager product 2')
        ->assertDontSee('Not an offer');
});

test('adding to the cart shows the added product and several frequently bought together products', function () {
    $category = 7;
    [$product] = translatedProducts(1, ['category_id' => $category, 'name' => 'Added product']);
    translatedProducts(3, ['category_id' => $category, 'featured' => 1, 'name' => 'Sibling']);
    translatedProducts(1, ['category_id' => 8, 'featured' => 1, 'name' => 'Other category']);

    $response = $this->post(route('cart.addToCart'), ['id' => $product->id, 'quantity' => 1])->assertOk();

    expect($response->json('status'))->toBe(1)
        ->and($response->json('modal_view'))->toContain('Added product')
        ->and($response->json('modal_view'))->toContain('Sibling')
        ->and($response->json('modal_view'))->not->toContain('Other category');
});

test('adding to the cart as a signed-in customer shows the frequently bought together products with their prices', function () {
    $category = 7;
    [$product] = translatedProducts(1, ['category_id' => $category, 'name' => 'Added product']);
    translatedProducts(2, ['category_id' => $category, 'featured' => 1, 'name' => 'Sibling', 'unit_price' => 4]);
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => 1])->save();
    actingAsStoreUser($customer->fresh());

    $response = $this->post(route('cart.addToCart'), ['id' => $product->id, 'quantity' => 1])->assertOk();

    // A guest never reaches the price line, so the verified customer's view is what pins the attribute it reads.
    expect($response->json('status'))->toBe(1)
        ->and($response->json('modal_view'))->toContain('Sibling 1')
        ->and($response->json('modal_view'))->toContain('Sibling 2');
});

test('a verified seller shop lists several of its products and none of another shop', function () {
    $seller = sellerAccount();
    $shopId = createShopFor($seller, 0);
    DB::table('shops')->where('id', $shopId)->update(['slug' => 'eager-shop', 'verification_status' => 1]);
    translatedProducts(3, ['user_id' => $seller->id, 'added_by' => 'seller', 'name' => 'Shop product']);
    translatedProducts(1, ['name' => 'Another shop product']);

    $this->get(route('shop.visit', 'eager-shop'))
        ->assertOk()
        ->assertSee('Shop product 1')
        ->assertSee('Shop product 3')
        ->assertDontSee('Another shop product');
});

test('a product page lists the several products of its active offers', function () {
    [$base] = translatedProducts(1, ['name' => 'Base product']);
    $offered = translatedProducts(3, ['name' => 'Offered product']);

    // Two active offers on the base product: the guard only trips on a result of two or more.
    foreach ([1, 2] as $n) {
        $offer = Offer::forceCreate([
            'title' => 'Eager offer '.$n,
            'offer_type' => 'products_offer',
            'is_active' => 1,
            'start_date' => now()->subDay()->timestamp,
            'end_date' => now()->addDay()->timestamp,
        ]);
        DB::table('offer_products')->insert([
            ['offer_id' => $offer->id, 'product_id' => $base->id, 'is_base_product' => 1, 'quantity' => 1, 'discount' => 0, 'discount_type' => null],
            ...array_map(fn (Product $product) => ['offer_id' => $offer->id, 'product_id' => $product->id, 'is_base_product' => 0, 'quantity' => 1, 'discount' => 10, 'discount_type' => 'percent'], $offered),
        ]);
    }

    $this->get(route('product', $base->slug))
        ->assertOk()
        ->assertSee('Offered product 1')
        ->assertSee('Offered product 3');
});
