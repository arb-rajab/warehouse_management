<?php

use Illuminate\Support\Facades\RateLimiter;

/**
 * Store entry points that take a credential or probe a token, and used to
 * take unlimited tries: social login, the two "whose token is this" endpoints
 * and the web registration. They carry `throttle:store-auth`; sign-up carries
 * `store-signup` in place of the get-then-increment `rate.limit` middleware it
 * had.
 */
dataset('store auth entry points', [
    'api social login' => ['post', '/temp/api/v2/auth/social-login'],
    'api auth info' => ['post', '/temp/api/v2/auth/info'],
    'api get user by access token' => ['post', '/temp/api/v2/get-user-by-access_token'],
    'web register' => ['post', '/temp/register'],
]);

test('a store auth entry point is cut off after thirty requests from one address', function (string $method, string $uri) {
    foreach (range(1, 30) as $request) {
        expect($this->{$method}($uri)->status())->not->toBe(429);
    }

    $this->{$method}($uri)->assertTooManyRequests();
})->with('store auth entry points');

test('one address being cut off does not cut off another', function (string $method, string $uri) {
    foreach (range(1, 31) as $request) {
        $this->{$method}($uri);
    }

    expect($this->withServerVariables(['REMOTE_ADDR' => '10.7.0.1'])->{$method}($uri)->status())->not->toBe(429);
})->with('store auth entry points');

test('the api sign-up allows five attempts per address in fifteen minutes', function () {
    foreach (range(1, 5) as $attempt) {
        expect($this->postJson('/temp/api/v2/auth/signup', [])->status())->not->toBe(429);
    }

    $this->postJson('/temp/api/v2/auth/signup', [])->assertTooManyRequests();
    expect($this->withServerVariables(['REMOTE_ADDR' => '10.7.0.2'])->postJson('/temp/api/v2/auth/signup', [])->status())->not->toBe(429);

    $this->travel(16)->minutes();

    expect($this->postJson('/temp/api/v2/auth/signup', [])->status())->not->toBe(429);
});

test('the sign-up limit is a named rate limiter and the hand-rolled middleware is gone', function () {
    expect(RateLimiter::limiter('store-signup'))->not->toBeNull()
        ->and(class_exists('App\Http\Middleware\RateLimitMiddleware'))->toBeFalse()
        ->and(app('router')->getMiddleware())->not->toHaveKey('rate.limit');
});
