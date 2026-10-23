<?php

use App\Models\Blog;
use App\Support\Seo;
use Database\Seeders\PageSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;

/**
 * Which storefront pages may be indexed (App\Support\Seo::robots()), the
 * X-Robots-Tag header on the back offices and the API (SendNoIndexHeader) and
 * the generated /robots.txt (RobotsController). The rule for listings is in
 * store-backoffice-migration.md §42.
 */
beforeEach(function () {
    config(['app.url' => 'https://shop.example.test']);
    seedStoreCurrency();
});

/** The directive Seo gives a GET to $uri, resolved through the real route table without rendering. */
function seoRobotsFor(string $uri): string
{
    $request = Request::create($uri);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    return (new Seo($request))->robots();
}

test('pages that are for an account or a transaction are noindex, nofollow', function (string $uri) {
    expect(seoRobotsFor($uri))->toBe('noindex, nofollow');
})->with([
    'login' => '/temp/login',
    'register' => '/temp/register',
    'password reset request' => '/temp/password/reset',
    'password reset form' => '/temp/password/reset/some-token',
    'email verification' => '/temp/email/verify',
    'users/login' => '/temp/users/login',
    'users/registration' => '/temp/users/registration',
    'seller login' => '/temp/seller/login',
    'delivery boy login' => '/temp/deliveryboy/login',
    'cart' => '/temp/cart',
    'compare' => '/temp/compare',
    'order tracking' => '/temp/track-your-order',
    'checkout' => '/temp/checkout',
    'checkout, choose customer' => '/temp/checkout/choose_customer',
    'checkout, payment' => '/temp/checkout/payment-select',
    'order confirmation' => '/temp/checkout/order-confirmed',
    'customer dashboard' => '/temp/dashboard',
    'profile' => '/temp/profile',
    'purchase history' => '/temp/purchase_history',
    'wallet' => '/temp/wallet',
    'wishlist' => '/temp/wishlists',
    'support tickets' => '/temp/support_ticket',
    'notifications' => '/temp/all-notifications',
    'addresses' => '/temp/addresses',
    'conversations' => '/temp/conversations',
]);

test('public content pages are indexable, and so is a later page of a plain listing', function (string $uri) {
    expect(seoRobotsFor($uri))->toBe('index, follow');
})->with([
    'home' => '/temp',
    'a product' => '/temp/product/crisps',
    'a product with tracking' => '/temp/product/crisps?utm_source=mail&fbclid=1',
    'a category' => '/temp/category/snacks',
    'page 2 of a category' => '/temp/category/snacks?page=2',
    'a brand' => '/temp/brand/acme',
    'all brands' => '/temp/brands',
    'all categories' => '/temp/categories',
    'the blog' => '/temp/blog',
    'a blog post' => '/temp/blog/opening-hours',
    'a custom page' => '/temp/about-us',
    'terms' => '/temp/terms',
    'a shop' => '/temp/shop/acme',
    'classified listing' => '/temp/customer-products',
]);

test('internal search and filtered or sorted listing variants are noindex, follow', function (string $uri) {
    expect(seoRobotsFor($uri))->toBe('noindex, follow');
})->with([
    'search with a keyword' => '/temp/search?keyword=crisps',
    'search without one' => '/temp/search',
    'search, page 2' => '/temp/search?keyword=crisps&page=2',
    'category sorted' => '/temp/category/snacks?sort_by=price-asc',
    'category with a price range' => '/temp/category/snacks?min_price=1&max_price=5&page=2',
    'category with a brand filter' => '/temp/category/snacks?brand=acme',
    'category with an attribute filter' => '/temp/category/snacks?selected_attribute_values[]=red',
    'brand sorted' => '/temp/brand/acme?sort_by=newest',
    'shop products filtered' => '/temp/shop/acme/all-products?rating=4',
    'blog search' => '/temp/blog?search=hours',
    'blog category filter' => '/temp/blog?selected_categories[]=news',
    'classified filter' => '/temp/customer-products?condition=used',
]);

test('a tracking-only or empty filter does not make a listing a variant', function () {
    expect(seoRobotsFor('/temp/category/snacks?utm_source=mail&gclid=1'))->toBe('index, follow')
        ->and(seoRobotsFor('/temp/category/snacks?sort_by=&min_price='))->toBe('index, follow');
});

test('a view can set its own robots directive and it wins', function () {
    $this->seed(PageSeeder::class);
    DB::table('blogs')->insert(['category_id' => 1, 'title' => 'Draft', 'slug' => 'draft', 'status' => 0]);

    // Rendered directly: the controller answers 404 for an unpublished post. The $errors bag is the session middleware's.
    view()->share('errors', new ViewErrorBag);
    expect(storefrontHead(view('shop.frontend.blog.details', ['blog' => Blog::where('slug', 'draft')->firstOrFail(), 'recent_blogs' => collect()])->render())['robots'])->toBe('noindex, nofollow');
});

test('the rendered login, registration and password pages print noindex, nofollow', function (string $uri) {
    expect(storefrontHead($this->get($uri)->assertOk())['robots'])->toBe('noindex, nofollow');
})->with(['/temp/login', '/temp/register', '/temp/password/reset', '/temp/users/login', '/temp/users/registration', '/temp/cart']);

test('a signed-in customer\'s dashboard and profile print noindex, nofollow', function (string $uri) {
    actingAsStoreUser(createStoreUser('customer'));

    expect(storefrontHead($this->get($uri)->assertOk())['robots'])->toBe('noindex, nofollow');
})->with(['/temp/dashboard', '/temp/profile']);

test('the rendered search and a filtered listing print noindex, follow, a later plain page prints index, follow', function () {
    DB::table('categories')->insert(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);

    expect(storefrontHead($this->get('/temp/search?keyword=crisps')->assertOk())['robots'])->toBe('noindex, follow')
        ->and(storefrontHead($this->get('/temp/category/snacks?sort_by=newest')->assertOk())['robots'])->toBe('noindex, follow')
        ->and(storefrontHead($this->get('/temp/category/snacks?page=2')->assertOk())['robots'])->toBe('index, follow')
        ->and(storefrontHead($this->get('/temp/category/snacks')->assertOk())['robots'])->toBe('index, follow');
});

test('the seller login page, the seller panel and the maintenance page carry a robots meta', function () {
    $this->get('/temp/seller/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/temp/deliveryboy/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    expect(view('shop.errors.503')->render())->toContain('<meta name="robots" content="noindex, nofollow">');
    expect(view('shop.errors.404')->render())->toContain('<meta name="robots" content="noindex, nofollow">');
});

test('the admin pages, both back offices and the API send X-Robots-Tag: noindex, nofollow', function () {
    // WMS back office, Inertia
    $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    // A guest sent away from the store back office (the WMS one is only marked on its Inertia pages)
    $this->get('/temp/admin')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    // The API groups: success, validation failure and rejection
    $this->postJson('/api/v1/login', [])->assertUnprocessable()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->getJson('/temp/api/v2/auth/user')->assertUnauthorized()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->getJson('/temp/api/v2/currencies')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    // The seller area
    $this->get('/temp/seller/dashboard')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('signed-in Inertia pages of both back offices send X-Robots-Tag, full loads and visits alike', function () {
    actingAsAdmin();
    $this->get('/admin')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/admin/rows', inertiaHeaders())->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    auth()->logout();
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(route('shop.admin.dashboard'), inertiaHeaders())->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('the public storefront does not send X-Robots-Tag', function (string $uri) {
    DB::table('categories')->insert(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);
    $this->seed(PageSeeder::class);

    $this->get($uri)->assertOk()->assertHeaderMissing('X-Robots-Tag');
})->with(['/temp', '/temp/category/snacks', '/temp/terms', '/temp/login']);

test('robots.txt keeps the storefront open, disallows the private areas and names the sitemap from APP_URL', function () {
    $response = $this->get('/robots.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    $lines = explode("\n", trim($response->getContent()));

    expect($lines[0])->toBe('User-agent: *')
        ->and($lines)->toContain('Disallow: /admin')
        ->toContain('Disallow: /temp/admin')
        ->toContain('Disallow: /temp/cart')
        ->toContain('Disallow: /temp/checkout')
        ->toContain('Disallow: /temp/dashboard')
        ->toContain('Disallow: /temp/wishlists')
        ->toContain('Disallow: /temp/seller/')
        ->toContain('Disallow: /temp/api/')
        ->toContain('Disallow: /api/')
        ->toContain('Sitemap: https://shop.example.test/temp/sitemap.xml')
        // The storefront, the policy pages (/seller-policy shares a prefix with
        // /seller/) and the noindex search pages stay crawlable.
        ->not->toContain('Disallow:')
        ->not->toContain('Disallow: /')
        ->not->toContain('Disallow: /temp')
        ->not->toContain('Disallow: /temp/seller')
        ->not->toContain('Disallow: /temp/search')
        ->not->toContain('Disallow: /temp/category');
    expect(file_exists(public_path('robots.txt')))->toBeFalse();
});

test('the Sitemap line in robots.txt follows APP_URL', function () {
    config(['app.url' => 'https://www.other.test:8443']);

    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://www.other.test:8443/temp/sitemap.xml', false);
});
