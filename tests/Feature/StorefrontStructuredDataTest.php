<?php

use App\Models\Blog;
use App\Models\Product;
use App\Models\Upload;
use App\Support\StructuredData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The JSON-LD in the storefront's <head> (App\Support\StructuredData), read
 * back from the real pages.
 */
beforeEach(function () {
    config(['app.url' => 'https://shop.example.test']);
    seedStoreCurrency();
});

const LD_ORIGIN = 'https://shop.example.test';

/** A product with a brand, a two-level category, an image and one stock row. */
function ldProduct(array $attributes = []): Product
{
    $parent = DB::table('categories')->insertGetId(['name' => 'Food', 'slug' => 'food', 'level' => 0]);
    $category = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 1, 'parent_id' => $parent]);
    $brand = DB::table('brands')->insertGetId(['name' => 'Acme', 'slug' => 'acme']);
    $image = Upload::forceCreate(['external_link' => 'https://cdn.example.test/crisps.jpg']);

    return createStoreProduct([
        'name' => 'Salty Crisps',
        'slug' => 'salty-crisps',
        'description' => '<p>Crunchy <b>crisps</b> &amp; more</p><script>alert(1)</script>',
        'category_id' => $category,
        'brand_id' => $brand,
        'thumbnail_img' => $image->id,
        ...$attributes,
    ]);
}

function ldVerifiedCustomer(): void
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => true])->save();
    actingAsStoreUser($customer->fresh());
}

/**
 * @param  list<array<string, mixed>>  $blocks
 * @return array<string, mixed>
 */
function ldOfType(array $blocks, string $type): array
{
    return collect($blocks)->firstWhere('@type', $type) ?? [];
}

test('a product page prints its Product and breadcrumb, with no price or rating for a visitor who is shown neither', function () {
    ldProduct();

    $head = storefrontHead($this->get('/temp/product/salty-crisps')->assertOk());

    expect($head['jsonLd'])->toHaveCount(2)
        ->and(ldOfType($head['jsonLd'], 'Product'))->toBe([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Salty Crisps',
            'url' => LD_ORIGIN.'/temp/product/salty-crisps',
            'image' => ['https://cdn.example.test/crisps.jpg'],
            'description' => 'Crunchy crisps & more',
            'sku' => 'SKU-1',
            'brand' => ['@type' => 'Brand', 'name' => 'Acme'],
        ])
        ->and(ldOfType($head['jsonLd'], 'BreadcrumbList'))->toBe([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => LD_ORIGIN.'/temp'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Food', 'item' => LD_ORIGIN.'/temp/category/food'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Snacks', 'item' => LD_ORIGIN.'/temp/category/snacks'],
                ['@type' => 'ListItem', 'position' => 4, 'name' => 'Salty Crisps', 'item' => LD_ORIGIN.'/temp/product/salty-crisps'],
            ],
        ]);
});

test('the exact JSON of a product without reviews, offered to a visitor the page shows prices to', function () {
    ldProduct();
    ldVerifiedCustomer();

    $head = storefrontHead($this->get('/temp/product/salty-crisps')->assertOk());

    expect($head['jsonLdRaw'][0])->toBe('{"@context":"https://schema.org","@type":"Product","name":"Salty Crisps","url":"https://shop.example.test/temp/product/salty-crisps","image":["https://cdn.example.test/crisps.jpg"],"description":"Crunchy crisps \u0026 more","sku":"SKU-1","brand":{"@type":"Brand","name":"Acme"},"offers":{"@type":"Offer","url":"https://shop.example.test/temp/product/salty-crisps","priceCurrency":"EUR","price":"2.50","availability":"https://schema.org/InStock"}}')
        ->and(ldOfType($head['jsonLd'], 'Product'))->not->toHaveKey('aggregateRating');
});

test('the exact JSON of a product with reviews: rating from the published reviews only, price with tax and discount, stock decides availability', function () {
    storeSetting('Product_Reviews_Section', 1);
    $product = ldProduct(['unit_price' => 10, 'unit_equal' => 1, 'discount' => 10, 'discount_type' => 'percent', 'current_stock' => 0]);
    DB::table('product_stocks')->where('product_id', $product->id)->update(['qty' => 0]);
    DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 20, 'tax_type' => 'percent']);
    $reviewer = createStoreUser('customer');
    DB::table('reviews')->insert([
        ['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 5, 'comment' => 'Great', 'status' => 1],
        ['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 4, 'comment' => 'Good', 'status' => 1],
        ['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 1, 'comment' => 'Hidden', 'status' => 0],
    ]);
    ldVerifiedCustomer();

    $head = storefrontHead($this->get('/temp/product/salty-crisps')->assertOk());

    // 10 - 10% = 9, + 20% tax = 10.80
    expect($head['jsonLdRaw'][0])->toBe('{"@context":"https://schema.org","@type":"Product","name":"Salty Crisps","url":"https://shop.example.test/temp/product/salty-crisps","image":["https://cdn.example.test/crisps.jpg"],"description":"Crunchy crisps \u0026 more","sku":"SKU-1","brand":{"@type":"Brand","name":"Acme"},"offers":{"@type":"Offer","url":"https://shop.example.test/temp/product/salty-crisps","priceCurrency":"EUR","price":"10.80","availability":"https://schema.org/OutOfStock"},"aggregateRating":{"@type":"AggregateRating","ratingValue":4.5,"reviewCount":2,"bestRating":5,"worstRating":1}}');
});

test('no rating is printed when the reviews section is off, and no price for variants', function () {
    $product = ldProduct(['variant_product' => 1]);
    DB::table('reviews')->insert(['product_id' => $product->id, 'user_id' => createStoreUser()->id, 'rating' => 5, 'comment' => 'Great', 'status' => 1]);
    ldVerifiedCustomer();

    $schema = ldOfType(storefrontHead($this->get('/temp/product/salty-crisps')->assertOk())['jsonLd'], 'Product');

    expect($schema)->not->toHaveKeys(['aggregateRating', 'offers']);
});

test('a hostile product, brand and category name cannot break out of the script or the JSON', function () {
    $name = '</script><script>alert(1)</script> "quoted" \'single\' & <b>bold</b>';
    $parent = DB::table('categories')->insertGetId(['name' => $name, 'slug' => 'evil', 'level' => 0]);
    $brand = DB::table('brands')->insertGetId(['name' => $name, 'slug' => 'evil-brand']);
    createStoreProduct(['name' => $name, 'slug' => 'evil-product', 'description' => '<p>'.$name.'</p>', 'category_id' => $parent, 'brand_id' => $brand]);

    $response = $this->get('/temp/product/evil-product')->assertOk();
    $html = $response->getContent();
    $head = storefrontHead($html);

    expect(substr_count($html, '<script type="application/ld+json">'))->toBe(2)
        ->and($head['jsonLd'])->toHaveCount(2);
    // Nothing inside a JSON-LD block closes it early or opens a tag.
    foreach ($head['jsonLdRaw'] as $raw) {
        expect($raw)->not->toContain('<')->not->toContain('>')->not->toContain('&')->not->toContain("'");
    }
    expect(ldOfType($head['jsonLd'], 'Product')['name'])->toBe($name)
        ->and(ldOfType($head['jsonLd'], 'Product')['brand']['name'])->toBe($name);
    $response->assertDontSee('<script>alert(1)</script>', false);
});

test('the home page prints the WebSite with its search and the Organization with its logo', function () {
    storeSetting('website_name', 'Albaraka Holland');
    storeSetting('header_logo', Upload::forceCreate(['external_link' => 'https://cdn.example.test/logo.png'])->id);

    $head = storefrontHead($this->get('/temp')->assertOk());

    expect($head['jsonLdRaw'])->toBe([
        '{"@context":"https://schema.org","@type":"WebSite","name":"Albaraka Holland","url":"https://shop.example.test/temp","potentialAction":{"@type":"SearchAction","target":{"@type":"EntryPoint","urlTemplate":"https://shop.example.test/temp/search?keyword={search_term_string}"},"query-input":"required name=search_term_string"}}',
        '{"@context":"https://schema.org","@type":"Organization","name":"Albaraka Holland","url":"https://shop.example.test/temp","logo":"https://cdn.example.test/logo.png"}',
    ]);
});

test('the search target of the WebSite is a real GET route', function () {
    $this->get('/temp/search?keyword=crisps')->assertOk();
});

test('the home page without a logo setting prints an Organization without a logo', function () {
    $organization = ldOfType(storefrontHead($this->get('/temp')->assertOk())['jsonLd'], 'Organization');

    expect($organization)->not->toHaveKey('logo')
        ->and($organization['name'])->toBe(config('app.name'));
});

test('category and brand listings print a breadcrumb, search and the plain listing do not', function () {
    $parent = DB::table('categories')->insertGetId(['name' => 'Food', 'slug' => 'food', 'level' => 0]);
    DB::table('categories')->insert(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 1, 'parent_id' => $parent]);
    DB::table('brands')->insert(['name' => 'Acme', 'slug' => 'acme']);

    $category = storefrontHead($this->get('/temp/category/snacks')->assertOk())['jsonLd'];
    $brand = storefrontHead($this->get('/temp/brand/acme')->assertOk())['jsonLd'];

    expect(collect($category[0]['itemListElement'])->pluck('name')->all())->toBe(['Home', 'Food', 'Snacks'])
        ->and(collect($category[0]['itemListElement'])->pluck('item')->all())->toBe([LD_ORIGIN.'/temp', LD_ORIGIN.'/temp/category/food', LD_ORIGIN.'/temp/category/snacks'])
        ->and(collect($brand[0]['itemListElement'])->pluck('name')->all())->toBe(['Home', 'Brands', 'Acme'])
        ->and(storefrontHead($this->get('/temp/search?keyword=x')->assertOk())['jsonLd'])->toBe([]);
});

test('the exact JSON of a blog post: dates, image, publisher, and no author because the blog records none', function () {
    $image = Upload::forceCreate(['external_link' => 'https://cdn.example.test/post.jpg']);
    storeSetting('website_name', 'Albaraka Holland');
    DB::table('blogs')->insert([
        'category_id' => 1, 'title' => 'Opening hours', 'slug' => 'opening-hours', 'short_description' => 'Short', 'meta_description' => 'When <b>we</b> are open',
        'meta_img' => $image->id, 'status' => 1, 'created_at' => '2026-03-04 05:06:07', 'updated_at' => '2026-04-05 06:07:08',
    ]);

    $raw = storefrontHead($this->get('/temp/blog/opening-hours')->assertOk())['jsonLdRaw'];
    $created = Carbon::parse('2026-03-04 05:06:07')->toAtomString();
    $updated = Carbon::parse('2026-04-05 06:07:08')->toAtomString();

    expect($raw)->toHaveCount(1)
        ->and($raw[0])->toBe('{"@context":"https://schema.org","@type":"BlogPosting","headline":"Opening hours","description":"When we are open","datePublished":"'.$created.'","dateModified":"'.$updated.'","image":["https://cdn.example.test/post.jpg"],"mainEntityOfPage":{"@type":"WebPage","@id":"https://shop.example.test/temp/blog/opening-hours"},"publisher":{"@type":"Organization","name":"Albaraka Holland","url":"https://shop.example.test/temp"}}');
    expect(Blog::where('slug', 'opening-hours')->exists())->toBeTrue();
});

test('plainText sanitizes, strips, squeezes and truncates', function () {
    expect(StructuredData::plainText("<p>One\n\n  <b>two</b></p><script>alert(1)</script><style>p{}</style> &amp; three"))->toBe('One two & three')
        ->and(StructuredData::plainText(str_repeat('word ', 200), 20))->toBe('word word word word...')
        ->and(StructuredData::plainText(null))->toBe('');
});

test('encode escapes tags, ampersands and quotes and keeps slashes', function () {
    expect(StructuredData::encode(['a' => '</script><x>"\'&/']))->toBe('{"a":"\\u003C/script\\u003E\\u003Cx\\u003E\\u0022\\u0027\\u0026/"}');
});
