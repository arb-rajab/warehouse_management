<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;

/*
 * The storefront's Direction A identity (.ai/rules/views.md): the storefront
 * layout loads its own stylesheet, resources/css/storefront.css, after the
 * legacy theme, with self-hosted IBM Plex fonts, and the retired base_color /
 * base_hov_color settings no longer inject a :root colour override. The back
 * office never loads that stylesheet.
 */

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.ui_default_locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
    seedStoreCurrency();
});

const STOREFRONT_LATIN_FONT = 'node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-latin-400-normal.woff2';
const STOREFRONT_ARABIC_FONT = 'node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2';

dataset('storefront languages', [
    'Arabic' => ['ar', 'rtl', 'اسم المتجر'],
    'English' => ['en', 'ltr', 'Store name'],
]);

test('the storefront home loads the identity stylesheet after the theme, with the Plex font, in both languages', function (string $locale, string $dir, string $brandName) {
    DB::table('business_settings')->insert([
        ['type' => 'base_color', 'value' => '#123456'],
        ['type' => 'base_hov_color', 'value' => '#654321'],
    ]);

    $html = $this->withSession(['locale' => $locale])->get('/temp')->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$dir.'"', false)
        ->assertSee('<link rel="stylesheet" href="'.Vite::asset('resources/css/storefront.css').'"', false)
        ->assertSee('<link rel="preload" as="font" type="font/woff2" crossorigin href="'.Vite::asset(STOREFRONT_LATIN_FONT).'">', false)
        ->assertSee('<span class="sf-brand-name">'.$brandName.'</span>', false)
        ->assertSee('assets/img/33logoqr.png', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('Public Sans', false)
        ->assertDontSee('--primary:', false)
        ->assertDontSee('#123456', false)
        ->assertDontSee('#654321', false)
        ->getContent();

    expect(strpos($html, Vite::asset('resources/css/storefront.css')))
        ->toBeGreaterThan(strpos($html, 'assets/css/aiz-core.css'))
        ->and(str_contains($html, Vite::asset(STOREFRONT_ARABIC_FONT)))->toBe($dir === 'rtl')
        ->and(str_contains($html, 'assets/css/bootstrap-rtl.min.css'))->toBe($dir === 'rtl')
        ->and($html)->not->toContain('Albaraka Holland');
})->with('storefront languages');

test('the identity stylesheet maps the theme variables onto the Direction A tokens and declares the Plex fonts', function () {
    $css = Vite::content('resources/css/storefront.css');

    expect($css)
        ->toContain('IBM Plex Sans Arabic')
        ->toContain('IBM Plex Mono')
        ->toContain('@font-face')
        ->toMatch('/--brand:\s*#c4170c/i')
        ->toMatch('/--primary:\s*var\(--brand\)/')
        ->toMatch('/--hov-primary:\s*var\(--brand-hover\)/')
        ->toMatch('/--dark:\s*var\(--ink\)/')
        ->toMatch('/--danger-color:\s*#b42318/i')
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('#d43533');
});

test('the brand name is one placeholder per language, not the legacy store name', function () {
    expect(trans('messages.brand_name', [], 'en'))->toBe('Store name')
        ->and(trans('messages.brand_name', [], 'ar'))->toBe('اسم المتجر');
});

test('the back office never loads the storefront stylesheet or its fonts', function () {
    $this->get('/login')->assertOk()
        ->assertSee(Vite::asset('resources/css/app.css'), false)
        ->assertDontSee(Vite::asset('resources/css/storefront.css'), false)
        ->assertDontSee('ibm-plex', false);

    expect(file_get_contents(resource_path('css/app.css')))
        ->not->toContain('storefront')
        ->not->toContain('plex');
});
