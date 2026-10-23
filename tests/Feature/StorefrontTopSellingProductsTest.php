<?php

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The "Top Selling Products" widget of the product page built route('product',
 * $slug) for up to six products, so one without a slug threw a
 * UrlGenerationException (a 500 for the whole product page), and it
 * lazy-loaded translations, taxes and stocks per product. Product::topSelling()
 * now leaves out products without a slug and eager loads what the view reads.
 */
beforeEach(function () {
    seedStoreCurrency();
});

const TOP_SELLING_SELLER = 7;

/**
 * @param  array<string, mixed>  $attributes
 */
function topSellingProduct(string $name, int $sales, array $attributes = []): Product
{
    return createStoreProduct([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'user_id' => TOP_SELLING_SELLER,
        'num_of_sale' => $sales,
        ...$attributes,
    ]);
}

function topSellingVerifiedCustomer(): void
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => true])->save();
    actingAsStoreUser($customer->fresh());
    // Rendering the partial outside a request: the storefront middleware would pick this guard.
    Auth::shouldUse('store');
}

function topSellingWidget(Product $displayed): string
{
    return view('shop.frontend.product_details.top_selling_products', ['detailedProduct' => $displayed])->render();
}

test('a slugless product among the top sellers does not break the product page and is not listed', function () {
    // Its own category keeps the sibling "Related products" widget (which has the
    // same slug defect, listed in guide section 43) out of this page.
    topSellingProduct('Displayed', 1, ['category_id' => 5]);
    topSellingProduct('Best Seller', 90);
    topSellingProduct('Slugless Hit', 100, ['slug' => null]);
    topSellingProduct('Blank Slug Hit', 95, ['slug' => '']);

    $this->get('/temp/product/displayed')
        ->assertOk()
        ->assertSee('Best Seller')
        ->assertDontSee('Slugless Hit')
        ->assertDontSee('Blank Slug Hit');
});

test('the top sellers are the same seller\'s six best selling visible products, best first', function () {
    $displayed = topSellingProduct('Displayed', 0);
    foreach (range(1, 8) as $sales) {
        topSellingProduct("Seller Product {$sales}", $sales);
    }
    topSellingProduct('Other Sellers Hit', 500, ['user_id' => TOP_SELLING_SELLER + 1]);
    topSellingProduct('Unpublished Hit', 400, ['published' => false]);
    topSellingProduct('Auction Hit', 300, ['auction_product' => 1]);

    $html = topSellingWidget($displayed);

    preg_match_all('#/product/(seller-product-\d)#', $html, $matches);
    expect(array_values(array_unique($matches[1])))->toBe([
        'seller-product-8', 'seller-product-7', 'seller-product-6',
        'seller-product-5', 'seller-product-4', 'seller-product-3',
    ]);
    expect($html)->not->toContain('Other Sellers Hit')
        ->not->toContain('Unpublished Hit')
        ->not->toContain('Auction Hit');
});

test('the widget prints a translated name and the prices to a verified customer without lazy loading', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();

    $displayed = topSellingProduct('Displayed', 0);
    foreach (range(1, 3) as $n) {
        $product = topSellingProduct("Item {$n}", 10 - $n, ['unit_price' => 100, 'variant_product' => $n === 1 ? 1 : 0, 'tax' => 0]);
        ProductTranslation::forceCreate(['product_id' => $product->id, 'lang' => app()->getLocale(), 'name' => "Translated {$n}"]);
        DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 10, 'tax_type' => 'percent']);
    }
    topSellingVerifiedCustomer();

    $html = topSellingWidget($displayed);

    expect($html)->toContain('Translated 1', 'Translated 2', 'Translated 3')
        ->toContain('110€');
});

test('the widget runs the same number of queries for one top seller as for six', function () {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        topSellingWidget(Product::query()->where('slug', 'displayed')->firstOrFail());
        // get_setting() deliberately re-reads `business_settings` on every call (its cache
        // TTL is 0), once per price the helpers format: shared helper code, not this widget's query.
        $count = collect(DB::getQueryLog())->reject(fn (array $query) => str_contains($query['query'], '"business_settings"'))->count();
        DB::disableQueryLog();

        return $count;
    };

    topSellingProduct('Displayed', 0);
    topSellingVerifiedCustomer();
    topSellingProduct('Item 1', 1);
    $queries();
    $withOne = $queries();

    foreach (range(2, 6) as $n) {
        $product = topSellingProduct("Item {$n}", $n);
        ProductTranslation::forceCreate(['product_id' => $product->id, 'lang' => app()->getLocale(), 'name' => "Translated {$n}"]);
    }

    expect($queries())->toBe($withOne);
});
