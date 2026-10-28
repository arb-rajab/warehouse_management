<?php

use App\Support\StoreLocale;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('all web interfaces use the configured UI default independently of content language', function (string $locale, string $direction) {
    config(['app.locale' => $locale, 'app.default_language' => $locale === 'ar' ? 'en' : 'ar']);
    DB::table('translations')->insert(['lang' => $locale, 'lang_key' => 'login', 'lang_value' => 'Localized login']);
    $this->get('/admin/login')->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);

    $this->get('/temp/users/login')->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false)
        ->assertSee($locale === 'ar' ? 'تسجيل الدخول' : 'Login')
        ->assertDontSee('Localized login')
        ->assertSessionMissing('locale');

    actingAsStoreUser(createStoreStaff('view_all_staffs'));
    $this->get('/temp/admin/staffs')->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', $locale)->where('store.rtl', $locale === 'ar'))
        ->assertSessionMissing('locale');

    expect(default_language())->toBe($locale === 'ar' ? 'en' : 'ar');
})->with([['ar', 'rtl'], ['en', 'ltr']]);

test('explicit existing session choices override the default across store interfaces', function (string $locale) {
    config(['app.locale' => $locale === 'ar' ? 'en' : 'ar']);
    $this->withSession(['locale' => $locale])->get('/temp/users/login')->assertOk()->assertSessionHas('locale', $locale);
    expect(app()->getLocale())->toBe($locale);

    actingAsStoreUser(createStoreStaff('view_all_staffs'));
    $this->get('/temp/admin/staffs')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
})->with(['ar', 'en']);

test('unknown and malformed store session locales fall back to the UI default', function (mixed $locale) {
    config(['app.locale' => 'ar', 'app.default_language' => 'en']);
    actingAsStoreUser(createStoreStaff('view_all_staffs'));

    $this->withSession(['locale' => $locale])->get('/temp/admin/staffs')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('store.rtl', true));
})->with(['unknown' => ['xx'], 'empty' => [''], 'array' => [['ar']], 'null' => [null]]);

test('Arabic and English direction override store flags while other store languages keep their direction', function () {
    DB::table('languages')->insert([
        ['code' => 'ar', 'name' => 'Arabic', 'rtl' => 0],
        ['code' => 'en', 'name' => 'English', 'rtl' => 1],
        ['code' => 'fa', 'name' => 'Persian', 'rtl' => 1],
    ]);

    foreach (['ar' => true, 'en' => false, 'fa' => true] as $locale => $rtl) {
        app()->setLocale($locale);
        expect(StoreLocale::isRtl())->toBe($rtl)->and(StoreLocale::isSupported($locale))->toBeTrue();
    }
});

test('display dates and currency numbers follow the active locale with Western digits', function (string $locale) {
    app()->setLocale($locale);
    seedStoreCurrency();
    DB::table('business_settings')->insert([
        ['type' => 'no_of_decimals', 'value' => '2'],
        ['type' => 'symbol_format', 'value' => '1'],
    ]);
    $number = new NumberFormatter($locale.'-u-nu-latn', NumberFormatter::DECIMAL);
    $number->setAttribute(NumberFormatter::FRACTION_DIGITS, 2);
    expect(format_price(1234.5))->toBe('€'.$number->format(1234.5));
    expect(StoreLocale::date(strtotime('2026-10-28 14:35:00')))
        ->toContain('2026')
        ->toContain($locale === 'ar' ? '10' : 'Oct');
    expect(StoreLocale::date(strtotime('2026-10-28 14:35:00'), true))->toContain('35');
})->with(['ar', 'en']);

test('the storefront uses Arabic direction even without a language row or with a contrary flag', function () {
    config(['app.locale' => 'ar']);
    $this->get('/temp/users/login')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSee('bootstrap-rtl.min.css');

    DB::table('languages')->insert(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 0]);
    $this->get('/temp/users/login')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSee('bootstrap-rtl.min.css');
});

test('localized price formatting preserves half-up rounding at the configured precision', function (string $locale) {
    app()->setLocale($locale);
    expect(StoreLocale::number(2.5, 0))->toBe('3')
        ->and(StoreLocale::number(2.25, 1))->toBe('2.3');
})->with(['en', 'ar']);
