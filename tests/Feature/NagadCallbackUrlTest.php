<?php

use App\Http\Controllers\Payment\NagadController;

/**
 * Nagad's callback defaulted to `APP_URL/nagad/callback`, which has no /temp
 * prefix: Nagad sent the customer back to a 404. Unset, the callback is now the
 * storefront's own route; `NAGAD_CALLBACK_URL` still overrides it.
 */
test('the Nagad callback defaults to the storefront route, under /temp', function () {
    config(['nagad.callback_url' => null]);

    expect(NagadController::merchantCallbackUrl())->toBe(route('nagad.callback'))
        ->and(NagadController::merchantCallbackUrl())->toEndWith('/temp/nagad/callback');
});

test('the default Nagad callback URL is a route of the store that answers', function () {
    config(['nagad.callback_url' => null]);
    $path = parse_url(NagadController::merchantCallbackUrl(), PHP_URL_PATH);

    expect(app('router')->getRoutes()->match(Request::create($path, 'GET'))->getName())->toBe('nagad.callback');
});

test('a configured Nagad callback URL is used as it is', function () {
    config(['nagad.callback_url' => 'https://pay.example.test/custom/nagad']);

    expect(NagadController::merchantCallbackUrl())->toBe('https://pay.example.test/custom/nagad');
});

test('no Nagad callback URL is configured out of the box', function () {
    expect(config('nagad.callback_url'))->toBeNull();
});
