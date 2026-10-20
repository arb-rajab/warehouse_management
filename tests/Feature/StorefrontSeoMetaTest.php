<?php

use App\Models\Blog;
use App\Models\Page;
use App\Models\Upload;
use App\Support\Seo;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The storefront layout's canonical / og / twitter / robots head, computed by
 * App\Support\Seo (store-backoffice-migration.md §42). Every page type is
 * rendered through its real route. APP_URL is pinned to a host the test client
 * does not use, so a URL built from the request's own host fails the assertions.
 */
beforeEach(function () {
    config(['app.url' => 'https://shop.example.test']);
    seedStoreCurrency();
});

const SEO_ORIGIN = 'https://shop.example.test';

function seoCategory(string $slug = 'snacks'): int
{
    return DB::table('categories')->insertGetId(['name' => ucfirst($slug), 'slug' => $slug, 'level' => 0, 'meta_title' => 'Snacks wholesale', 'meta_description' => 'Crisps and more']);
}

function seoBlog(array $attributes = []): Blog
{
    return Blog::forceCreate([
        'category_id' => DB::table('blog_categories')->insertGetId(['category_name' => 'News', 'slug' => 'news']),
        'title' => 'Opening hours',
        'slug' => 'opening-hours',
        'short_description' => 'When we are open',
        'description' => '<p>Monday to Friday.</p>',
        'meta_title' => 'Opening hours | Shop',
        'meta_description' => 'When the warehouse shop is open',
        'status' => 1,
        ...$attributes,
    ]);
}

test('the home page names itself as its canonical and og:url, from APP_URL and the storefront prefix', function () {
    $head = storefrontHead($this->get('/temp')->assertOk());

    expect($head['canonical'])->toBe([SEO_ORIGIN.'/temp'])
        ->and($head['og']['og:url'])->toBe([SEO_ORIGIN.'/temp'])
        ->and($head['og']['og:type'])->toBe(['website'])
        ->and($head['robots'])->toBe('index, follow');
});

test('the canonical drops tracking parameters and keeps the ones that change the page', function (string $uri, string $canonical) {
    seoCategory();

    $head = storefrontHead($this->get($uri)->assertOk());

    expect($head['canonical'])->toBe([SEO_ORIGIN.$canonical])
        ->and($head['og']['og:url'])->toBe([SEO_ORIGIN.$canonical]);
})->with([
    'tracking only' => ['/temp/category/snacks?utm_source=mail&fbclid=abc&gclid=1', '/temp/category/snacks'],
    'page 1 is the plain page' => ['/temp/category/snacks?page=1&utm_campaign=x', '/temp/category/snacks'],
    'page 2' => ['/temp/category/snacks?utm_source=mail&page=2', '/temp/category/snacks?page=2'],
    'a trailing slash' => ['/temp/category/snacks/', '/temp/category/snacks'],
    'sorted' => ['/temp/category/snacks?utm_source=a&sort_by=newest', '/temp/category/snacks?sort_by=newest'],
    'parameters in a fixed order' => ['/temp/category/snacks?sort_by=newest&min_price=1&max_price=5', '/temp/category/snacks?max_price=5&min_price=1&sort_by=newest'],
    'empty values are dropped' => ['/temp/category/snacks?sort_by=&min_price=', '/temp/category/snacks'],
]);

test('a product page is a product, canonical to its own URL and escaped once', function () {
    $product = createStoreProduct(['slug' => 'salty-crisps', 'meta_title' => 'Crisps "Salty" & <b>more</b>', 'meta_description' => 'Tom & Jerry\'s crisps']);

    $response = $this->get('/temp/product/salty-crisps?utm_source=mail&product_referral_code=abc')->assertOk();
    $head = storefrontHead($response);

    expect($head['canonical'])->toBe([SEO_ORIGIN.'/temp/product/salty-crisps'])
        ->and($head['og']['og:url'])->toBe([SEO_ORIGIN.'/temp/product/salty-crisps'])
        ->and($head['og']['og:type'])->toBe(['product'])
        ->and($head['og']['og:title'])->toBe(['Crisps "Salty" & more'])
        ->and($head['twitter']['twitter:title'])->toBe(['Crisps "Salty" & more'])
        ->and($head['description'])->toBe('Tom & Jerry\'s crisps')
        ->and($head['title'])->toBe('Crisps "Salty" & more')
        ->and($head['robots'])->toBe('index, follow');
    // Once: the quote and ampersand are entities in the source, not entities of entities.
    $response->assertDontSee('&amp;amp;', false)->assertDontSee('&amp;quot;', false)->assertDontSee('<b>more</b>', false);
    expect($product->exists)->toBeTrue();
});

test('a product without a meta title or image falls back to the settings instead of printing empty tags', function () {
    $upload = Upload::forceCreate(['external_link' => 'https://cdn.example.test/default.jpg']);
    storeSetting('meta_title', 'Albaraka Holland');
    storeSetting('meta_description', 'Wholesale for shops');
    storeSetting('meta_image', $upload->id);
    createStoreProduct(['slug' => 'plain', 'meta_title' => null, 'meta_description' => null, 'meta_img' => null, 'thumbnail_img' => null]);

    $head = storefrontHead($this->get('/temp/product/plain')->assertOk());

    expect($head['title'])->toBe('Albaraka Holland')
        ->and($head['description'])->toBe('Wholesale for shops')
        ->and($head['og']['og:title'])->toBe(['Albaraka Holland'])
        ->and($head['og']['og:image'])->toBe(['https://cdn.example.test/default.jpg'])
        ->and($head['twitter']['twitter:image'])->toBe(['https://cdn.example.test/default.jpg']);
});

test('the twitter card is large only when there is an image, and no placeholder handle is printed', function () {
    $withoutImage = storefrontHead($this->get('/temp')->assertOk());

    expect($withoutImage['twitter']['twitter:card'])->toBe(['summary'])
        ->and($withoutImage['twitter'])->not->toHaveKeys(['twitter:image', 'twitter:site', 'twitter:creator'])
        ->and($withoutImage['og'])->not->toHaveKey('og:image');

    $upload = Upload::forceCreate(['external_link' => 'https://cdn.example.test/share.jpg']);
    storeSetting('meta_image', $upload->id);

    $withImage = storefrontHead($this->get('/temp')->assertOk());

    expect($withImage['twitter']['twitter:card'])->toBe(['summary_large_image'])
        ->and($withImage['twitter']['twitter:image'])->toBe(['https://cdn.example.test/share.jpg'])
        ->and($withImage['og']['og:image'])->toBe(['https://cdn.example.test/share.jpg'])
        ->and($withImage['twitter'])->not->toHaveKeys(['twitter:site', 'twitter:creator']);

    $this->get('/temp')->assertDontSee('@publisher_handle')->assertDontSee('@author_handle');
});

test('listing, search, blog, custom page and policy page each carry their own URL, not the home page', function () {
    $this->seed(PageSeeder::class);
    Page::forceCreate(['type' => 'custom_page', 'title' => 'About', 'slug' => 'about-us', 'content' => '<p>About</p>', 'meta_title' => 'About us']);
    seoCategory();
    seoBlog();

    $pages = [
        'listing' => ['/temp/category/snacks', '/temp/category/snacks'],
        'search' => ['/temp/search?keyword=crisps', '/temp/search?keyword=crisps'],
        'blog' => ['/temp/blog/opening-hours', '/temp/blog/opening-hours'],
        'custom page' => ['/temp/about-us', '/temp/about-us'],
        'terms' => ['/temp/terms', '/temp/terms'],
        'privacy' => ['/temp/privacy-policy', '/temp/privacy-policy'],
    ];

    foreach ($pages as $name => [$uri, $expected]) {
        $head = storefrontHead($this->get($uri)->assertOk());

        expect($head['canonical'])->toBe([SEO_ORIGIN.$expected], $name)
            ->and($head['og']['og:url'])->toBe([SEO_ORIGIN.$expected], $name);
    }
});

test('a blog post is an article and an unpublished one is not indexable', function () {
    seoBlog();
    seoBlog(['slug' => 'draft', 'status' => 0]);

    $published = storefrontHead($this->get('/temp/blog/opening-hours')->assertOk());
    $draft = storefrontHead($this->get('/temp/blog/draft')->assertOk());

    expect($published['og']['og:type'])->toBe(['article'])
        ->and($published['robots'])->toBe('index, follow')
        ->and($draft['robots'])->toBe('noindex, nofollow');
});

test('a policy page served at its slug points its canonical at the policy route', function () {
    $this->seed(PageSeeder::class);

    $atSlug = storefrontHead($this->get('/temp/terms-conditions')->assertOk());
    $atRoute = storefrontHead($this->get('/temp/terms')->assertOk());

    expect($atSlug['canonical'])->toBe([SEO_ORIGIN.'/temp/terms'])
        ->and($atSlug['og']['og:url'])->toBe([SEO_ORIGIN.'/temp/terms'])
        ->and($atRoute['canonical'])->toBe([SEO_ORIGIN.'/temp/terms']);
});

test('the origin falls back to the request when APP_URL has no host', function () {
    config(['app.url' => 'not a url']);

    $head = storefrontHead($this->get('/temp')->assertOk());

    expect($head['canonical'])->toHaveCount(1)
        ->and($head['canonical'][0])->toStartWith('http://localhost')->toEndWith('/temp');
});

test('Seo::text decodes a section once and strips tags', function () {
    expect(Seo::text('Tom &amp; Jerry&#039;s &quot;best&quot;'))->toBe('Tom & Jerry\'s "best"')
        ->and(Seo::text('Crisps &lt;b&gt;more&lt;/b&gt;'))->toBe('Crisps more')
        ->and(Seo::text("  a \n  <b>b</b>  "))->toBe('a b')
        ->and(Seo::text(null))->toBe('');
});
