<?php

use Illuminate\Support\Facades\DB;

/*
 * The Direction A home sections (.ai/rules/views.md): a hero, four trust tiles
 * and a "Run a shop?" banner, all placeholder copy from lang/{en,ar}/storefront.php
 * with no images, and the labelled two-line price on product cards. The hero
 * yields to an admin-managed slider; the sliders, banners and deals stay as they were.
 */

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.ui_default_locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
    seedStoreCurrency();
});

dataset('home section languages', [
    'English' => ['en', 'ltr', [
        'hero' => 'PLACEHOLDER: hero headline',
        'tiles' => ['PLACEHOLDER: trust point 1', 'PLACEHOLDER: trust point 2', 'PLACEHOLDER: trust point 3', 'PLACEHOLDER: trust point 4'],
        'banner' => 'PLACEHOLDER: Run a shop?',
        'banner_cta' => 'PLACEHOLDER: register button',
    ]],
    'Arabic' => ['ar', 'rtl', [
        'hero' => 'نص تجريبي: عنوان الواجهة',
        'tiles' => ['نص تجريبي: ميزة 1', 'نص تجريبي: ميزة 2', 'نص تجريبي: ميزة 3', 'نص تجريبي: ميزة 4'],
        'banner' => 'نص تجريبي: هل لديك متجر؟',
        'banner_cta' => 'نص تجريبي: زر التسجيل',
    ]],
]);

test('the home page shows the hero, four trust tiles and the shop banner in both languages', function (string $locale, string $dir, array $copy) {
    $response = $this->withSession(['locale' => $locale])->get('/temp')->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$dir.'"', false)
        ->assertSee('class="sf-hero"', false)
        ->assertSee($copy['hero'])
        ->assertSee($copy['banner'])
        ->assertSee($copy['banner_cta'])
        ->assertSee('href="'.route('shops.create').'"', false);

    foreach ($copy['tiles'] as $tile) {
        $response->assertSee($tile);
    }

    expect(substr_count($response->getContent(), 'class="sf-trust-tile"'))->toBe(4);
})->with('home section languages');

test('the home sections are pure CSS and inline SVG, with no images', function () {
    $html = $this->withSession(['locale' => 'en'])->get('/temp')->assertOk()->getContent();

    foreach (['sf-hero', 'sf-trust', 'sf-shop-banner-wrap'] as $class) {
        $start = strpos($html, 'class="'.$class.'"');
        expect($start)->not->toBeFalse();
        $end = strpos($html, '</section>', $start);
        expect(substr($html, $start, $end - $start))->not->toContain('<img');
    }
});

test('the hero is hidden when the admin has configured a home slider, and the slider still renders', function () {
    storeSetting('home_slider_images', json_encode(['1']));
    storeSetting('home_slider_links', json_encode(['/offers']));

    $this->withSession(['locale' => 'en'])->get('/temp')->assertOk()
        ->assertDontSee('class="sf-hero"', false)
        ->assertDontSee('PLACEHOLDER: hero headline')
        ->assertSee('class="home-slider"', false)
        ->assertSee('aiz-carousel dots-inside-bottom', false)
        // The trust tiles and the shop banner do not clash with a slider.
        ->assertSee('class="sf-trust-tile"', false)
        ->assertSee('PLACEHOLDER: Run a shop?');
});

test('an empty slider setting, as the admin form can store, still shows the hero', function (string $value) {
    // The admin form stores the images and their links together.
    storeSetting('home_slider_images', $value);
    storeSetting('home_slider_links', $value);

    $this->withSession(['locale' => 'en'])->get('/temp')->assertOk()
        ->assertSee('class="sf-hero"', false);
})->with(['empty list' => '[]', 'blank entry' => '[""]']);

test('a signed-in seller does not see the shop banner', function () {
    actingAsStoreUser(createStoreUser('seller'));

    $this->withSession(['locale' => 'en'])->get('/temp')->assertOk()
        ->assertDontSee('PLACEHOLDER: Run a shop?');
});

test('a signed-in customer still sees the shop banner', function () {
    actingAsStoreUser(createStoreUser('customer'));

    $this->withSession(['locale' => 'en'])->get('/temp')->assertOk()
        ->assertSee('PLACEHOLDER: Run a shop?');
});

test('the home sections are styled in the storefront sheet with logical properties', function () {
    $css = file_get_contents(resource_path('css/storefront.css'));

    expect($css)
        ->toContain('.sf-hero-card')
        ->toContain('.sf-trust-grid')
        ->toContain('.sf-shop-banner')
        ->toContain('.sf-price-piece')
        ->toContain("[dir='rtl'] .sf-hero-art");
});

function homeCardCustomer(): void
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => true])->save();
    actingAsStoreUser($customer->fresh());
}

function homeCardProduct(array $attributes = []): void
{
    $category = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);

    createStoreProduct([
        'slug' => 'salty-crisps',
        'unit_price' => 12.5,
        'unit' => 'Box',
        'unit_equal' => 4,
        'category_id' => $category,
        ...$attributes,
    ]);
}

test('product cards print two labelled prices instead of a || separator, in both languages', function (string $locale, string $dir, string $label) {
    homeCardProduct();
    homeCardCustomer();

    $html = $this->withSession(['locale' => $locale])->get('/temp/category/snacks')->assertOk()
        ->assertSee('/temp/product/salty-crisps')
        ->assertSee('class="sf-price-main mx-2 fw-400 text-primary"', false)
        ->assertSee('<span class="sf-price-piece-label">'.$label.'</span>', false)
        // The per-piece span is the exact markup StorefrontUnitEqualTest pins.
        ->assertSee('<span class="mx-2 fw-800 text-primary"> €3.125</span>', false)
        ->assertSeeInOrder(['€12.50', $label, '€3.125'], false)
        ->getContent();

    expect($html)->not->toContain('||');
})->with([
    'English' => ['en', 'ltr', 'Per piece'],
    'Arabic' => ['ar', 'rtl', 'للقطعة الواحدة'],
]);

test('a card with no usable unit_equal prints the main price only, with no per-piece label', function () {
    homeCardProduct(['unit_equal' => 0]);
    homeCardCustomer();

    $this->withSession(['locale' => 'en'])->get('/temp/category/snacks')->assertOk()
        ->assertSee('class="sf-price-main mx-2 fw-400 text-primary"', false)
        ->assertDontSee('sf-price-piece-label', false)
        ->assertDontSee('fw-800 text-primary', false);
});

test('a guest sees no price on a card, so no price label either', function () {
    homeCardProduct();

    $this->withSession(['locale' => 'en'])->get('/temp/category/snacks')->assertOk()
        ->assertSee('/temp/product/salty-crisps')
        ->assertDontSee('sf-price-piece-label', false);
});
