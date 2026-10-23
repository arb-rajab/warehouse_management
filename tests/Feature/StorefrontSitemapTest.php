<?php

use App\Models\Page;
use App\Models\Shop;
use App\Services\Sitemap;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /sitemap.xml and its files (App\Services\Sitemap): what is listed, what
 * is not, the chunking under an index, the cache, and the XML. The route used
 * to return the path of a sitemap.xml that nothing wrote.
 */
beforeEach(function () {
    config(['app.url' => 'https://shop.example.test']);
    seedStoreCurrency();
});

const SITEMAP_ORIGIN = 'https://shop.example.test';

/**
 * Every <loc> of one sitemap file, as listed.
 *
 * @return list<string>
 */
function sitemapLocs(string $document): array
{
    $xml = simplexml_load_string($document);
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    return array_map('strval', $xml->xpath('//s:loc'));
}

/**
 * The URLs of every file in the sitemap, followed from the index.
 *
 * @return list<string>
 */
function sitemapUrls(): array
{
    $test = test();
    $urls = [];

    foreach (sitemapLocs($test->get('/temp/sitemap.xml')->assertOk()->getContent()) as $file) {
        $urls = [...$urls, ...sitemapLocs($test->get(substr($file, strlen(SITEMAP_ORIGIN)))->assertOk()->getContent())];
    }

    return $urls;
}

test('the sitemap index is XML and lists a file per source that has URLs', function () {
    createStoreProduct(['slug' => 'crisps']);

    $response = $this->get('/temp/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($response->getContent())->toContain('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    expect(sitemapLocs($response->getContent()))->toBe([
        SITEMAP_ORIGIN.'/temp/sitemap-static-1.xml',
        SITEMAP_ORIGIN.'/temp/sitemap-products-1.xml',
    ]);
});

test('the sitemap lists the home page, the index pages, categories, brands, products, posts and pages', function () {
    $this->seed(PageSeeder::class);
    Page::forceCreate(['type' => 'custom_page', 'title' => 'About', 'slug' => 'about-us', 'content' => 'x']);
    DB::table('categories')->insert(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);
    DB::table('brands')->insert(['name' => 'Acme', 'slug' => 'acme']);
    DB::table('blogs')->insert(['category_id' => 1, 'title' => 'Post', 'slug' => 'opening-hours', 'status' => 1]);
    createStoreProduct(['slug' => 'crisps']);

    $urls = sitemapUrls();

    expect($urls)->toEqualCanonicalizing([
        SITEMAP_ORIGIN.'/temp',
        SITEMAP_ORIGIN.'/temp/brands',
        SITEMAP_ORIGIN.'/temp/categories',
        SITEMAP_ORIGIN.'/temp/blog',
        SITEMAP_ORIGIN.'/temp/terms',
        SITEMAP_ORIGIN.'/temp/privacy-policy',
        SITEMAP_ORIGIN.'/temp/return-policy',
        SITEMAP_ORIGIN.'/temp/seller-policy',
        SITEMAP_ORIGIN.'/temp/support-policy',
        SITEMAP_ORIGIN.'/temp/about-us',
        SITEMAP_ORIGIN.'/temp/category/snacks',
        SITEMAP_ORIGIN.'/temp/brand/acme',
        SITEMAP_ORIGIN.'/temp/blog/opening-hours',
        SITEMAP_ORIGIN.'/temp/product/crisps',
    ]);
});

test('a policy page is listed at its policy route, not again at its slug, and the home page row is not a page', function () {
    $this->seed(PageSeeder::class);
    Page::forceCreate(['type' => 'home_page', 'title' => 'Home', 'slug' => 'home-page', 'content' => 'x']);

    $urls = sitemapUrls();

    expect($urls)->toContain(SITEMAP_ORIGIN.'/temp/terms')
        ->not->toContain(SITEMAP_ORIGIN.'/temp/terms-conditions')
        ->not->toContain(SITEMAP_ORIGIN.'/temp/home-page');
});

test('unpublished, unapproved, auction, slugless and unverified-seller products are not listed', function () {
    storeSetting('vendor_system_activation', 1);
    $verifiedSeller = createStoreUser('seller');
    $unverifiedSeller = createStoreUser('seller');
    Shop::forceCreate(['user_id' => $verifiedSeller->id, 'name' => 'Verified', 'slug' => 'verified', 'verification_status' => 1]);
    Shop::forceCreate(['user_id' => $unverifiedSeller->id, 'name' => 'Unverified', 'slug' => 'unverified', 'verification_status' => 0]);

    createStoreProduct(['slug' => 'in-house']);
    createStoreProduct(['slug' => 'verified-seller', 'added_by' => 'seller', 'user_id' => $verifiedSeller->id]);
    createStoreProduct(['slug' => 'unverified-seller', 'added_by' => 'seller', 'user_id' => $unverifiedSeller->id]);
    createStoreProduct(['slug' => 'unpublished', 'published' => false]);
    createStoreProduct(['slug' => 'unapproved', 'approved' => 0]);
    createStoreProduct(['slug' => 'auction', 'auction_product' => 1]);
    createStoreProduct(['slug' => null]);
    createStoreProduct(['slug' => '']);

    $products = collect(sitemapUrls())->filter(fn (string $url) => str_contains($url, '/temp/product/'))->values()->all();

    expect($products)->toEqualCanonicalizing([
        SITEMAP_ORIGIN.'/temp/product/in-house',
        SITEMAP_ORIGIN.'/temp/product/verified-seller',
    ]);
});

test('without the vendor system only the shop\'s own approved products are listed', function () {
    $seller = createStoreUser('seller');
    createStoreProduct(['slug' => 'in-house']);
    createStoreProduct(['slug' => 'seller-product', 'added_by' => 'seller', 'user_id' => $seller->id]);
    createStoreProduct(['slug' => 'unapproved', 'approved' => 0]);

    $products = collect(sitemapUrls())->filter(fn (string $url) => str_contains($url, '/temp/product/'))->values()->all();

    expect($products)->toBe([SITEMAP_ORIGIN.'/temp/product/in-house']);
});

test('draft and deleted blog posts, the Uncategorized category and slugless rows are not listed', function () {
    DB::table('blogs')->insert([
        ['category_id' => 1, 'title' => 'Live', 'slug' => 'live', 'status' => 1, 'deleted_at' => null],
        ['category_id' => 1, 'title' => 'Draft', 'slug' => 'draft', 'status' => 0, 'deleted_at' => null],
        ['category_id' => 1, 'title' => 'Deleted', 'slug' => 'deleted', 'status' => 1, 'deleted_at' => now()],
    ]);
    DB::table('categories')->insert([
        ['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0],
        ['name' => 'Uncategorized', 'slug' => 'uncategorized', 'level' => 0],
        ['name' => 'No slug', 'slug' => null, 'level' => 0],
    ]);
    DB::table('brands')->insert(['name' => 'No slug', 'slug' => '']);
    Page::forceCreate(['type' => 'custom_page', 'title' => 'No slug', 'slug' => null, 'content' => 'x']);

    $urls = sitemapUrls();

    expect($urls)->toContain(SITEMAP_ORIGIN.'/temp/blog/live', SITEMAP_ORIGIN.'/temp/category/snacks')
        ->not->toContain(SITEMAP_ORIGIN.'/temp/blog/draft', SITEMAP_ORIGIN.'/temp/blog/deleted', SITEMAP_ORIGIN.'/temp/category/uncategorized');
    expect(array_filter($urls, fn (string $url) => str_ends_with($url, '/temp/category/') || str_ends_with($url, '/temp/brand/')))->toBe([]);
});

test('classified products are listed only while the classified feature is on and only when published', function () {
    DB::table('customer_products')->insert([
        ['name' => 'Chair', 'slug' => 'chair', 'published' => 1, 'status' => 1],
        ['name' => 'Hidden', 'slug' => 'hidden', 'published' => 0, 'status' => 1],
        ['name' => 'Pending', 'slug' => 'pending', 'published' => 1, 'status' => 0],
    ]);

    expect(sitemapUrls())->not->toContain(SITEMAP_ORIGIN.'/temp/customer-product/chair');

    Cache::flush();
    storeSetting('classified_product', 1);

    $urls = sitemapUrls();

    expect($urls)->toContain(SITEMAP_ORIGIN.'/temp/customer-product/chair')
        ->not->toContain(SITEMAP_ORIGIN.'/temp/customer-product/hidden', SITEMAP_ORIGIN.'/temp/customer-product/pending');
});

test('lastmod is printed from updated_at for posts, pages, categories and brands, never for products', function () {
    DB::table('blogs')->insert(['category_id' => 1, 'title' => 'Post', 'slug' => 'post', 'status' => 1, 'updated_at' => '2026-03-04 05:06:07']);
    DB::table('brands')->insert(['name' => 'Acme', 'slug' => 'acme', 'updated_at' => '2026-02-01 00:00:00']);
    createStoreProduct(['slug' => 'crisps']);
    DB::table('products')->update(['updated_at' => '2026-10-20 08:00:00']);

    $blog = $this->get('/temp/sitemap-blog-1.xml')->assertOk()->getContent();
    $brands = $this->get('/temp/sitemap-brands-1.xml')->assertOk()->getContent();
    $products = $this->get('/temp/sitemap-products-1.xml')->assertOk()->getContent();

    expect($blog)->toContain('<lastmod>'.Carbon::parse('2026-03-04 05:06:07')->toAtomString().'</lastmod>')
        ->and($brands)->toContain('<lastmod>'.Carbon::parse('2026-02-01 00:00:00')->toAtomString().'</lastmod>')
        ->and($products)->toContain('/temp/product/crisps')->not->toContain('<lastmod>');
});

test('urls with spaces, accents and XML characters are encoded and the document stays valid XML', function () {
    createStoreProduct(['slug' => "crème brûlée & tom's \"best\""]);

    $document = $this->get('/temp/sitemap-products-1.xml')->assertOk()->getContent();

    expect(simplexml_load_string($document))->not->toBeFalse();
    $locs = sitemapLocs($document);
    expect($locs)->toHaveCount(1)
        ->and($locs[0])->toStartWith(SITEMAP_ORIGIN.'/temp/product/')
        ->and($locs[0])->not->toContain(' ')
        ->and($document)->toContain('&amp;')->not->toMatch('/&(?!amp;|apos;|quot;|lt;|gt;)/');
    expect(rawurldecode($locs[0]))->toContain('crème brûlée');
});

test('a source with more URLs than the limit is split into several files under the index', function () {
    app()->bind(Sitemap::class, fn () => new Sitemap(chunkSize: 2));
    foreach (range(1, 5) as $number) {
        createStoreProduct(['slug' => "product-{$number}"]);
    }

    $files = sitemapLocs($this->get('/temp/sitemap.xml')->assertOk()->getContent());
    $productFiles = array_values(array_filter($files, fn (string $file) => str_contains($file, 'sitemap-products-')));

    expect($productFiles)->toBe([
        SITEMAP_ORIGIN.'/temp/sitemap-products-1.xml',
        SITEMAP_ORIGIN.'/temp/sitemap-products-2.xml',
        SITEMAP_ORIGIN.'/temp/sitemap-products-3.xml',
    ]);

    $seen = [];
    foreach ($productFiles as $index => $file) {
        $locs = sitemapLocs($this->get(substr($file, strlen(SITEMAP_ORIGIN)))->assertOk()->getContent());
        expect($locs)->toHaveCount($index === 2 ? 1 : 2);
        $seen = [...$seen, ...$locs];
    }
    expect($seen)->toHaveCount(5)->and(array_unique($seen))->toHaveCount(5);
});

test('a file that does not exist is a 404 and writes no cache entry', function (string $uri) {
    createStoreProduct(['slug' => 'crisps']);

    $this->get($uri)->assertNotFound();
    expect(Cache::has('sitemap.products.2'))->toBeFalse()
        ->and(Cache::has('sitemap.nothing.1'))->toBeFalse()
        ->and(Cache::has('sitemap.classifieds.1'))->toBeFalse();
})->with([
    'a page past the end' => '/temp/sitemap-products-2.xml',
    'page zero' => '/temp/sitemap-products-0.xml',
    'an unknown source' => '/temp/sitemap-nothing-1.xml',
    'an empty source' => '/temp/sitemap-classifieds-1.xml',
]);

test('the sitemap is cached for an hour and rebuilt after it', function () {
    createStoreProduct(['slug' => 'first']);
    $this->get('/temp/sitemap.xml')->assertOk();
    $this->get('/temp/sitemap-products-1.xml')->assertOk();

    expect(Cache::has('sitemap.counts'))->toBeTrue()
        ->and(Cache::has('sitemap.products.1'))->toBeTrue();

    createStoreProduct(['slug' => 'second']);
    expect(sitemapUrls())->toContain(SITEMAP_ORIGIN.'/temp/product/first')
        ->not->toContain(SITEMAP_ORIGIN.'/temp/product/second');

    $this->travel(59)->minutes();
    expect(sitemapUrls())->not->toContain(SITEMAP_ORIGIN.'/temp/product/second');

    $this->travel(2)->minutes();
    expect(sitemapUrls())->toContain(SITEMAP_ORIGIN.'/temp/product/first', SITEMAP_ORIGIN.'/temp/product/second');
});

test('a product file costs the same few queries however many products it lists', function () {
    $queries = function (): int {
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get('/temp/sitemap-products-1.xml')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    createStoreProduct(['slug' => 'one']);
    $withOne = $queries();

    foreach (range(1, 15) as $number) {
        createStoreProduct(['slug' => "more-{$number}"]);
    }

    expect($queries())->toBe($withOne);
});

test('the sitemap and the files it lists use the APP_URL origin whatever host asked', function () {
    createStoreProduct(['slug' => 'crisps']);
    config(['app.url' => 'https://www.other.test']);

    $files = sitemapLocs($this->get('http://another-host.test/temp/sitemap.xml')->assertOk()->getContent());

    expect($files)->each->toStartWith('https://www.other.test/temp/sitemap-');
});

test('robots.txt points at the sitemap and the sitemap answers there', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();
    preg_match('/^Sitemap: (.+)$/m', $robots, $match);

    expect($match[1])->toBe(SITEMAP_ORIGIN.'/temp/sitemap.xml');
    $this->get(substr($match[1], strlen(SITEMAP_ORIGIN)))->assertOk();
});
