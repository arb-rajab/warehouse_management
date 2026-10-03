<?php

/**
 * SetLocale (the admin panel's locale, from the session) read `->value` off
 * Locale::tryFrom(), which is null whenever the session holds no locale, so
 * every first visit to the panel was a 500. AppLanguage (the store API's
 * locale, from the App-Language header) handed whatever the client sent to
 * App::setLocale().
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
    $this->withHeaders(['App-Language' => 'ar'])->getJson('/temp/api/v2/auth/user')->assertUnauthorized();

    expect(app()->getLocale())->toBe('ar');
});

test('the store api ignores an App-Language header the app has no locale for', function (string $header) {
    $this->withHeaders(['App-Language' => $header])->getJson('/temp/api/v2/auth/user')->assertUnauthorized();

    expect(app()->getLocale())->toBe(config('app.default_language'));
})->with(['unknown locale' => 'fr', 'path traversal' => '../../etc/passwd', 'wrong case' => 'AR', 'empty' => '']);

test('the store api uses the default locale when it gets no App-Language header', function () {
    $this->getJson('/temp/api/v2/auth/user')->assertUnauthorized();

    expect(app()->getLocale())->toBe(config('app.default_language'));
});
