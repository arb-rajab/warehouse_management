<?php

use App\Models\Brand;
use App\Models\Page;
use App\Models\ProductQuery;
use App\Models\Upload;
use Database\Seeders\PageSeeder;

// These checks reconstruct findings from the integrated implementation;
// they do not inherit results from the unavailable audit commit.
test('policy Blade page data retains its CSP and triggers a full Inertia fallback', function () {
    $this->seed(PageSeeder::class);
    $plain = $this->get('/temp/terms')->assertOk();
    expect($plain->headers->get('Content-Security-Policy-Report-Only') ?? $plain->headers->get('Content-Security-Policy'))->toContain("'unsafe-inline'");
    $this->get('/temp/terms', inertiaHeaders())->assertConflict()->assertHeader('X-Inertia-Location', url('/temp/terms'));
});

test('custom CMS pages render actual nullable metadata fields and preserve content', function (bool $metadata) {
    $image = Upload::factory()->create(['file_name' => 'uploads/all/cms.png']);
    Page::forceCreate([
        'type' => 'custom_page', 'slug' => 'audit-cms', 'title' => 'CMS audit title',
        'content' => '<p>Public CMS content</p>', 'meta_title' => $metadata ? 'CMS social title' : null,
        'meta_description' => $metadata ? 'CMS summary' : null,
        'keywords' => $metadata ? 'CMS keywords' : null, 'meta_image' => $metadata ? (string) $image->id : null,
    ]);
    $response = $this->get('/temp/audit-cms')->assertOk()->assertSee('Public CMS content');
    if ($metadata) {
        $response->assertSee('CMS social title')->assertSee('CMS summary')->assertSee('CMS keywords')->assertSee(uploaded_asset($image->id));
    }
    expect($response->headers->get('Content-Security-Policy-Report-Only') ?? $response->headers->get('Content-Security-Policy'))->toContain("'unsafe-inline'");
})->with([true, false]);

test('retained uploader layouts preserve the request scheme port and temp prefix', function () {
    foreach (['/temp/login', '/temp/seller/login'] as $path) {
        $this->get('https://store.example.test:8443'.$path)->assertOk()
            ->assertSee('name="app-url" content="https://store.example.test:8443/temp"', false)
            ->assertSee('name="file-base-url" content="https://store.example.test:8443/"', false);
    }
    $seller = actingAsStoreUser(createStoreUser('seller'));
    createShopFor($seller, 0);
    $this->get('https://store.example.test:8443/temp/seller/product/create')->assertOk()->assertSee('name="app-url" content="https://store.example.test:8443/temp"', false)
        ->assertSee('name="file-base-url" content="https://store.example.test:8443/"', false);
});

test('retained seller shell reference PDF and email templates render without delivery', function () {
    $seller = createStoreUser('seller');
    actingAsStoreUser($seller);
    app('auth')->shouldUse('store');
    expect(view('shop.seller.layouts.app')->render())->toContain($seller->email, 'name="app-url"');
    $brand = Brand::forceCreate(['name' => 'Audit PDF Brand', 'slug' => 'audit-pdf-brand']);
    expect(view('shop.backend.downloads.brand', ['brands' => collect([$brand])])->render())->toContain('Audit PDF Brand');
    $content = clean_html('<p><strong>Valid email content</strong><script>alert(1)</script></p>');
    expect(view('shop.emails.newsletter', ['array' => ['content' => $content]])->render())
        ->toContain('<strong>Valid email content</strong>')->not->toContain('<script>');
    expect(view('shop.emails.verification', ['array' => ['subject' => 'Verify account', 'content' => 'Verification content', 'link' => url('/temp/login')]])->render())
        ->toContain('Verify account', 'Verification content', url('/temp/login'));
});

test('retained product query read aliases redirect authorized staff and refuse customers', function () {
    $query = ProductQuery::forceCreate([
        'customer_id' => createStoreUser('customer')->id,
        'seller_id' => createStoreUser('admin')->id,
        'product_id' => createStoreProduct()->id,
        'question' => 'Audit question',
    ]);
    actingAsStoreUser(createStoreStaff('view_all_product_queries'));
    $this->get('/temp/product-queries')->assertRedirect(route('product_query.index'));
    $this->get('/temp/product-queries/'.encrypt($query->id))->assertRedirect(route('product_query.show', $query->id));
    $this->get('/temp/product-queries/invalid-ciphertext')->assertNotFound();
    $this->get('/temp/product-queries/'.encrypt([$query->id]))->assertNotFound();
    actingAsStoreUser(createStoreUser('customer'));
    $this->get('/temp/product-queries')->assertForbidden();
    $this->get('/temp/product-queries/'.encrypt($query->id))->assertForbidden();
});
