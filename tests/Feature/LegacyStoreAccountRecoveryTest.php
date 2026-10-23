<?php

use App\Models\StoreUser;
use App\Services\StoreVerificationCodes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * The store's password-reset and verification codes are 6 digits: 900,000
 * guesses. The API's confirm_reset found the account from the code alone, so a
 * guessed code reset the password of whichever user held it at that moment (a
 * registration code included), nothing limited how often a code or a password
 * could be tried, the codes came from rand(), and the web reset left the code
 * valid after using it. The codes are now issued by StoreVerificationCodes
 * (hashed, expiring, capped; see StoreVerificationCodesTest) and these tests
 * drive them through the routes that accept them.
 *
 * @return array{0: StoreUser, 1: string} The user, and the clear-text code issued to them.
 */
function storeUserWithCode(string $email, ?string $phone = null): array
{
    $user = createStoreUser('customer', $email, 'old-password');
    $user->forceFill(['phone' => $phone ?? $user->phone])->save();

    return [$user->fresh(), app(StoreVerificationCodes::class)->issue($user)];
}

function confirmReset(array $data): TestResponse
{
    return test()->postJson('/temp/api/v2/auth/password/confirm_reset', $data + ['password' => 'hacked-password1']);
}

test('confirm_reset needs the account as well as the code', function () {
    [$victim, $code] = storeUserWithCode('victim@example.com');

    confirmReset(['verification_code' => $code])->assertUnprocessable();
    confirmReset(['email_or_phone' => 'victim@example.com'])->assertUnprocessable();

    expect(Hash::check('old-password', $victim->fresh()->password))->toBeTrue();
});

test('confirm_reset does not accept one account\'s code for another account', function () {
    [$victim, $victimCode] = storeUserWithCode('victim@example.com');
    [$bystander, $bystanderCode] = storeUserWithCode('bystander@example.com');

    confirmReset(['email_or_phone' => 'bystander@example.com', 'verification_code' => $victimCode])->assertNotFound();

    expect(Hash::check('old-password', $victim->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $bystander->fresh()->password))->toBeTrue();

    // The failed try used up nothing of the victim's code, and the bystander's still works.
    confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => $victimCode])->assertOk();
    confirmReset(['email_or_phone' => 'bystander@example.com', 'verification_code' => $bystanderCode])->assertOk();
});

test('confirm_reset resets the named account, by email or by phone, once', function (string $identifier) {
    [$user, $code] = storeUserWithCode('victim@example.com', '31612345678');

    confirmReset(['email_or_phone' => $identifier, 'verification_code' => $code])->assertOk();

    expect(Hash::check('hacked-password1', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->exists())->toBeFalse();

    confirmReset(['email_or_phone' => $identifier, 'verification_code' => $code, 'password' => 'again-password1'])->assertNotFound();

    expect(Hash::check('again-password1', $user->fresh()->password))->toBeFalse();
})->with(['by email' => ['victim@example.com'], 'by phone' => ['31612345678']]);

test('confirm_reset refuses an expired code', function () {
    [$user, $code] = storeUserWithCode('victim@example.com');

    $this->travel(StoreVerificationCodes::LIFETIME_MINUTES + 1)->minutes();

    confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => $code])->assertNotFound();

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('confirm_reset answers an unknown account exactly as it answers a wrong code', function () {
    [, $code] = storeUserWithCode('victim@example.com');

    $unknownAccount = confirmReset(['email_or_phone' => 'nobody@example.com', 'verification_code' => $code]);
    $wrongCode = confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => wrongCode($code)]);

    expect($unknownAccount->status())->toBe(404)
        ->and($unknownAccount->json())->toBe($wrongCode->json());
});

test('confirm_reset validates the password before it says anything about the account', function () {
    [, $code] = storeUserWithCode('victim@example.com');

    $known = confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => $code, 'password' => 'short']);
    $unknown = confirmReset(['email_or_phone' => 'nobody@example.com', 'verification_code' => $code, 'password' => 'short']);

    $known->assertUnprocessable();
    expect($unknown->status())->toBe(422)
        ->and($unknown->json())->toBe($known->json());
});

test('guessing a reset code is cut off after five tries from one address', function () {
    [$victim, $code] = storeUserWithCode('victim@example.com');

    foreach (range(1, 5) as $guess) {
        confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => wrongCode($code)])->assertNotFound();
    }

    // Even the right code is refused now: the limit counts attempts.
    confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => $code])->assertTooManyRequests();

    expect(Hash::check('old-password', $victim->fresh()->password))->toBeTrue();
});

test('guessing a reset code is cut off across addresses too, and anonymous callers do not lock each other out', function () {
    [, $code] = storeUserWithCode('victim@example.com');

    foreach (range(1, 10) as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.9.0.$address"])
            ->postJson('/temp/api/v2/auth/password/confirm_reset', ['email_or_phone' => 'victim@example.com', 'verification_code' => wrongCode($code), 'password' => 'hacked-password1'])
            ->assertNotFound();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.99'])
        ->postJson('/temp/api/v2/auth/password/confirm_reset', ['email_or_phone' => 'victim@example.com', 'verification_code' => $code, 'password' => 'hacked-password1'])
        ->assertTooManyRequests();

    // Requests naming no account share no per-account key: ten addresses, none limited.
    foreach (range(1, 10) as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.8.0.$address"])
            ->postJson('/temp/api/v2/auth/password/confirm_reset', [])
            ->assertUnprocessable();
    }
});

test('the web reset code works once', function () {
    [$user, $code] = storeUserWithCode('web@example.com');
    $reset = fn (string $password) => test()->post(route('shop.password.update'), [
        'email' => 'web@example.com', 'code' => $code, 'password' => $password, 'password_confirmation' => $password,
    ]);

    $reset('new-password1')->assertRedirect(route('home'));

    expect(DB::table('wms_store_verification_codes')->where('user_id', $user->id)->exists())->toBeFalse();

    $reset('again-password1')->assertOk();

    expect(Hash::check('new-password1', $user->fresh()->password))->toBeTrue();
});

test('the web reset refuses an expired code', function () {
    [$user, $code] = storeUserWithCode('web@example.com');

    $this->travel(StoreVerificationCodes::LIFETIME_MINUTES + 1)->minutes();

    $this->post(route('shop.password.update'), [
        'email' => 'web@example.com', 'code' => $code, 'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ])->assertOk();

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a mismatched confirmation on the web reset leaves the code usable', function () {
    [$user, $code] = storeUserWithCode('web@example.com');

    $this->post(route('shop.password.update'), [
        'email' => 'web@example.com', 'code' => $code, 'password' => 'new-password1', 'password_confirmation' => 'different-password1',
    ])->assertUnprocessable(); // ResetPasswordWithCodeRequest's `confirmed`, checked before the code is tried.

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();

    $this->post(route('shop.password.update'), [
        'email' => 'web@example.com', 'code' => $code, 'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ])->assertRedirect(route('home'));
});

test('the web reset is cut off after five tries from one address, even for the right code', function () {
    [$user, $code] = storeUserWithCode('web@example.com');
    $attempt = fn (string $code) => test()->post(route('shop.password.update'), [
        'email' => 'web@example.com', 'code' => $code, 'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ]);

    foreach (range(1, 5) as $guess) {
        $attempt(wrongCode($code))->assertOk();
    }

    $attempt($code)->assertTooManyRequests();

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('the store login stops accepting attempts after five failures from one address', function () {
    createStoreUser('customer', 'shopper@example.com', 'correct-password');

    foreach (range(1, 5) as $attempt) {
        $this->post('/temp/login', ['email' => 'shopper@example.com', 'password' => "wrong-$attempt"])->assertRedirect();
    }

    $this->post('/temp/login', ['email' => 'shopper@example.com', 'password' => 'correct-password'])->assertTooManyRequests();
});

test('no verification code comes from rand()', function () {
    $offenders = [];

    foreach (sourceFilesUnder(['app']) as $path => $source) {
        if (preg_match('/\brand\(\s*100000\b/', $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
