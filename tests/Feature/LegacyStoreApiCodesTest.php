<?php

use App\Notifications\AppEmailVerificationNotification;
use App\Services\StoreVerificationCodes;
use Illuminate\Support\Facades\Notification;

/**
 * The mobile API's code endpoints. password/forget_request and resend_code
 * answered 404 "User is not found" for an address with no account, and
 * resend_code could be called as fast as the client liked; confirm_code
 * compared the code with `==` and no limit on tries. Each now answers the same
 * whether or not an account matched, holds a repeat request inside the
 * cooldown, and checks codes through StoreVerificationCodes.
 */
dataset('code requests', [
    'forget_request' => ['/temp/api/v2/auth/password/forget_request', 'send_code_by'],
    'resend_code' => ['/temp/api/v2/auth/password/resend_code', 'verify_by'],
]);

test('a code request for an unknown address answers exactly as it does for a known one', function (string $uri, string $byField) {
    Notification::fake();
    createStoreUser('customer', 'known@example.com');

    $known = $this->postJson($uri, [$byField => 'email', 'email_or_phone' => 'known@example.com']);
    Notification::assertSentTimes(AppEmailVerificationNotification::class, 1);

    $unknown = $this->postJson($uri, [$byField => 'email', 'email_or_phone' => 'nobody@example.com']);

    $unknown->assertOk();
    expect($unknown->json())->toBe($known->json());
    Notification::assertSentTimes(AppEmailVerificationNotification::class, 1);
})->with('code requests');

test('a code request that names no address sends nothing and answers the same', function (string $uri, string $byField) {
    Notification::fake();
    // An account with no email must not match an empty identifier.
    createStoreUser('customer')->forceFill(['email' => null])->save();

    $this->postJson($uri, [$byField => 'email'])->assertOk();

    Notification::assertNothingSent();
})->with('code requests');

test('a second code request inside the cooldown sends no second code and leaves the first valid', function (string $uri, string $byField) {
    Notification::fake();
    $user = createStoreUser('customer', 'known@example.com', 'old-password');
    $request = fn () => test()->postJson($uri, [$byField => 'email', 'email_or_phone' => 'known@example.com']);

    $first = $request();
    $second = $request();

    expect($second->json())->toBe($first->json());
    Notification::assertSentTimes(AppEmailVerificationNotification::class, 1);

    $code = null;
    Notification::assertSentTo($user, AppEmailVerificationNotification::class, function (AppEmailVerificationNotification $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    $this->postJson('/temp/api/v2/auth/password/confirm_reset', [
        'email_or_phone' => 'known@example.com', 'verification_code' => $code, 'password' => 'new-password1',
    ])->assertOk();
})->with('code requests');

test('a code request is served again once the cooldown has passed', function (string $uri, string $byField) {
    Notification::fake();
    createStoreUser('customer', 'known@example.com');
    $request = fn () => test()->postJson($uri, [$byField => 'email', 'email_or_phone' => 'known@example.com'])->assertOk();

    $request();
    $this->travel(StoreVerificationCodes::RESEND_COOLDOWN_SECONDS + 1)->seconds();
    $request();

    Notification::assertSentTimes(AppEmailVerificationNotification::class, 2);
})->with('code requests');

test('confirm_code verifies the signed-in account with its code, once', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['email_verified_at' => null])->save();
    $code = app(StoreVerificationCodes::class)->issue($user);
    $token = storeApiToken($user);

    $this->withToken($token)->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => $code])
        ->assertOk()->assertJsonPath('result', true);

    expect($user->fresh()->email_verified_at)->not->toBeNull();

    $user->forceFill(['email_verified_at' => null])->save();

    $this->withToken($token)->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => $code])
        ->assertOk()->assertJsonPath('result', false);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('confirm_code refuses a wrong or expired code', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['email_verified_at' => null])->save();
    $code = app(StoreVerificationCodes::class)->issue($user);
    $token = storeApiToken($user);

    $this->withToken($token)->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => wrongCode($code)])
        ->assertJsonPath('result', false);

    $this->travel(StoreVerificationCodes::LIFETIME_MINUTES + 1)->minutes();

    $this->withToken($token)->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => $code])
        ->assertJsonPath('result', false);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('confirm_code does not take a code issued to another account', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['email_verified_at' => null])->save();
    $othersCode = app(StoreVerificationCodes::class)->issue(createStoreUser('customer'));

    $this->withToken(storeApiToken($user))->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => $othersCode])
        ->assertJsonPath('result', false);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('confirm_code is cut off after five tries from one address', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['email_verified_at' => null])->save();
    $code = app(StoreVerificationCodes::class)->issue($user);
    $token = storeApiToken($user);

    foreach (range(1, 5) as $guess) {
        $this->withToken($token)->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => wrongCode($code)])->assertOk();
    }

    $this->withToken($token)->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => $code])->assertTooManyRequests();

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('confirm_code and resend_code refuse a caller with no token', function () {
    $this->postJson('/temp/api/v2/auth/confirm_code', ['verification_code' => '123456'])->assertUnauthorized();
    $this->getJson('/temp/api/v2/auth/resend_code')->assertUnauthorized();
});

test('the signed-in account is held to the resend cooldown too', function () {
    Notification::fake();
    $user = createStoreUser('customer');
    $token = storeApiToken($user);

    $this->withToken($token)->getJson('/temp/api/v2/auth/resend_code')->assertOk()->assertJsonPath('result', true);
    $this->withToken($token)->getJson('/temp/api/v2/auth/resend_code')->assertStatus(429)->assertJsonPath('result', false);

    Notification::assertSentTimes(AppEmailVerificationNotification::class, 1);

    $this->travel(StoreVerificationCodes::RESEND_COOLDOWN_SECONDS + 1)->seconds();

    $this->withToken($token)->getJson('/temp/api/v2/auth/resend_code')->assertOk();
});
