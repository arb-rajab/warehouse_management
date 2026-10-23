<?php

use App\Models\CustomerProduct;
use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The 14 storefront views that override @section('meta') / the meta_* sections
 * each used to print their own og:url, twitter tags and placeholder handles.
 * Several named another page (the classified product pointed at the ordinary
 * product route, CMS and policy pages lacked the storefront prefix). The
 * layout now owns those tags (App\Support\Seo), so none of the 14 may print a
 * canonical or og:url for a URL other than the one it was served at, nor a
 * second copy of a tag the layout prints.
 *
 * Views: product_details, digital_product_details, customer_product_details,
 * product_listing, customer_product_listing, seller_shop,
 * seller_shop_without_verification, custom_page, blog/details and the five
 * policy pages. digital_product_details has no route (nothing renders it
 * today), so it is served from a route the test registers.
 */
beforeEach(function () {
    config(['app.url' => 'https://shop.example.test']);
    seedStoreCurrency();
});

/** The URL the page is served at, as a canonical must name it. */
function metaViewUrl(string $uri): string
{
    return 'https://shop.example.test'.$uri;
}

/**
 * @return array<string, array{0: string, 1: Closure(): void}> URI => fixture
 */
function metaViewPages(): array
{
    return [
        'product_details' => ['/temp/product/the-product', fn () => createStoreProduct(['slug' => 'the-product', 'meta_title' => 'The product'])],
        'product_listing (category)' => ['/temp/category/the-category', fn () => DB::table('categories')->insert(['name' => 'Cat', 'slug' => 'the-category', 'level' => 0])],
        'product_listing (brand)' => ['/temp/brand/the-brand', fn () => DB::table('brands')->insert(['name' => 'Brand', 'slug' => 'the-brand'])],
        'product_listing (search)' => ['/temp/search?keyword=x', fn () => null],
        'customer_product_details' => ['/temp/customer-product/used-chair', function () {
            storeSetting('classified_product', 1);
            metaViewClassified(['meta_title' => 'Used chair']);
        }],
        'customer_product_listing' => ['/temp/customer-products', fn () => storeSetting('classified_product', 1)],
        'seller_shop' => ['/temp/shop/the-shop', fn () => metaViewShop(verified: 1)],
        'seller_shop (all products)' => ['/temp/shop/the-shop/all-products', fn () => metaViewShop(verified: 1)],
        'seller_shop_without_verification' => ['/temp/shop/the-shop', fn () => metaViewShop(verified: 0)],
        'custom_page' => ['/temp/about-us', fn () => Page::forceCreate(['type' => 'custom_page', 'title' => 'About', 'slug' => 'about-us', 'content' => '<p>x</p>', 'meta_title' => 'About'])],
        'blog/details' => ['/temp/blog/the-post', function () {
            DB::table('blogs')->insert(['category_id' => 1, 'title' => 'Post', 'slug' => 'the-post', 'meta_title' => 'Post', 'status' => 1]);
        }],
        'terms' => ['/temp/terms', fn () => test()->seed(PageSeeder::class)],
        'privacypolicy' => ['/temp/privacy-policy', fn () => test()->seed(PageSeeder::class)],
        'returnpolicy' => ['/temp/return-policy', fn () => test()->seed(PageSeeder::class)],
        'sellerpolicy' => ['/temp/seller-policy', fn () => test()->seed(PageSeeder::class)],
        'supportpolicy' => ['/temp/support-policy', fn () => test()->seed(PageSeeder::class)],
    ];
}

/** @param  array<string, mixed>  $attributes */
function metaViewClassified(array $attributes = []): void
{
    $seller = createStoreUser('customer');
    $category = DB::table('categories')->insertGetId(['name' => 'Furniture', 'slug' => 'furniture', 'level' => 0]);
    DB::table('customer_products')->insert([
        'name' => 'Used chair', 'slug' => 'used-chair', 'published' => 1, 'status' => 1, 'unit_price' => 5,
        'user_id' => $seller->id, 'category_id' => $category, ...$attributes,
    ]);
}

function metaViewShop(int $verified): void
{
    $seller = createStoreUser('seller');
    Shop::forceCreate(['user_id' => $seller->id, 'name' => 'The shop', 'slug' => 'the-shop', 'verification_status' => $verified, 'meta_title' => 'The shop']);
}

test('every view that overrides the meta sections names its own URL as canonical and og:url, once', function (string $uri, Closure $fixture) {
    $fixture();

    $response = $this->get($uri)->assertOk();
    $head = storefrontHead($response);

    expect($head['canonical'])->toBe([metaViewUrl($uri)])
        ->and($head['og']['og:url'])->toBe([metaViewUrl($uri)])
        ->and($head['og']['og:title'])->toHaveCount(1)
        ->and($head['og']['og:type'])->toHaveCount(1)
        ->and($head['og']['og:site_name'])->toHaveCount(1)
        ->and($head['twitter']['twitter:card'])->toHaveCount(1)
        ->and($head['twitter']['twitter:title'])->toHaveCount(1)
        ->and($head['twitter'])->not->toHaveKeys(['twitter:site', 'twitter:creator']);
    $response->assertDontSee('@publisher_handle')->assertDontSee('@author_handle');
    expect(substr_count($response->getContent(), 'itemprop="name"'))->toBe(1);
})->with(fn () => collect(metaViewPages())->map(fn (array $page) => $page)->all());

test('digital_product_details, which no route renders, also leaves the URL to the layout', function () {
    $product = createStoreProduct(['slug' => 'ebook', 'digital' => 1, 'meta_title' => 'E-book']);
    Route::middleware('web')->get('/temp/__digital/{slug}', fn (string $slug) => view('shop.frontend.digital_product_details', [
        'detailedProduct' => Product::with('stocks', 'reviews', 'brand', 'user')->where('slug', $slug)->firstOrFail(),
        'product_queries' => collect(), 'total_query' => 0, 'reviews' => collect(), 'review_status' => 0, 'variations' => null,
    ]));

    $head = storefrontHead($this->get('/temp/__digital/ebook')->assertOk());

    expect($head['canonical'])->toBe([metaViewUrl('/temp/__digital/ebook')])
        ->and($head['og']['og:url'])->toBe([metaViewUrl('/temp/__digital/ebook')])
        ->and($head['og']['og:type'])->toBe(['product'])
        ->and($head['og']['og:title'])->toBe(['E-book']);
    expect($product->exists)->toBeTrue();
});

test('the classified product page no longer points at the ordinary product route', function () {
    storeSetting('classified_product', 1);
    metaViewClassified();
    expect(CustomerProduct::where('slug', 'used-chair')->exists())->toBeTrue();

    $head = storefrontHead($this->get('/temp/customer-product/used-chair')->assertOk());

    expect($head['og']['og:url'])->toBe(['https://shop.example.test/temp/customer-product/used-chair'])
        ->and($head['og']['og:type'])->toBe(['product']);
});
