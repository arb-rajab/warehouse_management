<?php

// The requests below go to auth/info, a route of the same `app_language` group that
// needs no token: `auth:store_api` runs ahead of AppLanguage, so a 401 never reaches it.
/**
 * SetLocale (the admin panel's locale, from the session) reads the locale off
 * Locale::tryFrom(), which is null whenever the session holds none; these pin
 * that a first visit still renders. AppLanguage (the store API's locale, from
 * the App-Language header) used to hand whatever the client sent to
 * App::setLocale(); it now only takes a locale the app has.
 */
test('a first web visit with no locale in the session renders', function () {
    $this->get('/admin/login')->assertOk();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

test('a locale in the session that the app does not have falls back to the default', function () {
    $this->withSession(['locale' => 'xx'])->get('/admin/login')->assertOk();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

test('a supported locale in the session is the one the page renders in', function () {
    $this->withSession(['locale' => 'ar'])->get('/admin/login')->assertOk();

    expect(app()->getLocale())->toBe('ar');
});

test('the store api honours an App-Language header the app has a locale for', function () {
    $previousLocale = app()->getLocale();
    $this->withHeaders(['App-Language' => 'ar'])->postJson('/temp/api/v2/auth/info')->assertOk()
        ->assertJsonPath('message', 'فشل تسجيل الدخول');

    expect(app()->getLocale())->toBe($previousLocale);
});

test('the store api ignores an App-Language header the app has no locale for', function (string $header) {
    config(['app.ui_default_locale' => 'en', 'app.default_language' => 'ar']);
    $previousLocale = app()->getLocale();
    $this->withHeaders(['App-Language' => $header])->postJson('/temp/api/v2/auth/info')->assertOk()
        ->assertJsonPath('message', 'Login Failed');

    expect(app()->getLocale())->toBe($previousLocale);
})->with(['unknown locale' => 'fr', 'path traversal' => '../../etc/passwd', 'wrong case' => 'AR', 'empty' => '']);

test('the store api uses the default locale when it gets no App-Language header', function () {
    config(['app.ui_default_locale' => 'ar', 'app.default_language' => 'en']);
    $previousLocale = app()->getLocale();
    $this->postJson('/temp/api/v2/auth/info')->assertOk()
        ->assertJsonPath('message', 'فشل تسجيل الدخول');

    expect(app()->getLocale())->toBe($previousLocale);
});
