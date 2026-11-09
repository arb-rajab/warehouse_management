<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The back-office sidebar shows the same owner-editable placeholder brand
 * name as the storefront header (`messages.brand_name`), shared by
 * HandleInertiaRequests in the page's own language (.ai/rules/layouts.md).
 */
test('a warehouse page shares the placeholder brand name in its language', function (string $locale, string $brand) {
    actingAsAdmin();

    $this->withSession(['locale' => $locale])->get('/admin/rows')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('brandName', $brand));
})->with([
    'english' => ['en', 'Store name'],
    'arabic' => ['ar', 'اسم المتجر'],
]);

test('a store control panel page shares the same placeholder brand name', function () {
    DB::table('languages')->insert([['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]]);
    actingAsStoreUser(createStoreStaff('view_all_staffs'));

    $this->get('/temp/admin/staffs')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('brandName', trans('messages.brand_name', [], 'en')));

    $this->withSession(['locale' => 'ar'])->get('/temp/admin/staffs')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('brandName', trans('messages.brand_name', [], 'ar')));
});

test('the back office preloads the Arabic Plex file only on right-to-left pages', function () {
    actingAsAdmin();
    $arabic = Vite::asset('node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2');

    $this->get('/admin/rows')->assertOk()->assertDontSee($arabic, false);
    $this->withSession(['locale' => 'ar'])->get('/admin/rows')->assertOk()
        ->assertSee('<link rel="preload" as="font" type="font/woff2" crossorigin href="'.$arabic.'">', false);
});
