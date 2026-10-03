<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tokens never expired (`sanctum.expiration` was null). They now stop working
 * after thirty days, on the guards and on the store's token-lookup endpoints,
 * which find a token themselves and so check it themselves.
 */
// The test app keeps a guard's resolved user between requests, which a real request
// (a fresh process) does not, so each guard is forgotten before the request that
// should find the token expired.
test('tokens expire after thirty days', function () {
    expect(config('sanctum.expiration'))->toBe(60 * 24 * 30);
});

test('a warehouse token works until it is thirty days old and not after', function () {
    $token = User::factory()->mobileUser()->create()->createToken('mobile')->plainTextToken;

    $this->travel(29)->days();
    $this->withToken($token)->getJson('/api/v1/user')->assertOk();

    app('auth')->forgetGuards();
    $this->travel(2)->days();
    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
});

test('a store token works until it is thirty days old and not after', function () {
    $token = storeApiToken(createStoreUser('customer'));

    $this->travel(29)->days();
    $this->withToken($token)->getJson('/temp/api/v2/auth/user')->assertOk();

    app('auth')->forgetGuards();
    $this->travel(2)->days();
    $this->withToken($token)->getJson('/temp/api/v2/auth/user')->assertUnauthorized();
});

test('the store\'s token lookup endpoints refuse an expired token', function () {
    $customer = createStoreUser('customer');
    $token = storeApiToken($customer);

    $this->postJson('/temp/api/v2/auth/info', ['access_token' => $token])->assertJsonPath('result', true);
    $this->postJson('/temp/api/v2/get-user-by-access_token', ['access_token' => $token])->assertJsonPath('result', true);

    $this->travel(31)->days();

    $this->postJson('/temp/api/v2/auth/info', ['access_token' => $token])->assertJsonPath('result', false);
    $this->postJson('/temp/api/v2/get-user-by-access_token', ['access_token' => $token])->assertJsonPath('result', false);
});

test('a token past its own expires_at is expired whatever the configured lifetime', function () {
    $user = createStoreUser('customer');
    $user->createToken('short', ['*'], now()->addMinutes(5));
    $token = PersonalAccessToken::first();

    expect($token->isExpired())->toBeFalse();

    $this->travel(6)->minutes();

    expect($token->fresh()->isExpired())->toBeTrue();
});

test('a token with no lifetime configured and no expires_at never expires', function () {
    config(['sanctum.expiration' => null]);
    createStoreUser('customer')->createToken('forever');

    $this->travel(5)->years();

    expect(PersonalAccessToken::first()->isExpired())->toBeFalse()
        ->and(DB::table('wms_personal_access_tokens')->count())->toBe(1);
});
