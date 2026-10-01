<?php

use Illuminate\Support\Facades\Hash;

/**
 * The store's password-reset and verification codes are 6 digits: 900,000
 * guesses. The API's confirm_reset found the account from the code alone, so a
 * guessed code reset the password of whichever user held it at that moment (a
 * registration code included), nothing limited how often a code or a password
 * could be tried, the codes came from rand(), and the web reset left the code
 * valid after using it.
 */
function storeUserWithCode(string $email, string $code, ?string $phone = null): App\Models\StoreUser
{
    $user = createStoreUser('customer', $email, 'old-password');
    $user->forceFill(['verification_code' => $code, 'phone' => $phone ?? $user->phone])->save();

    return $user->fresh();
}

function confirmReset(array $data): Illuminate\Testing\TestResponse
{
    return test()->postJson('/temp/api/v2/auth/password/confirm_reset', $data + ['password' => 'hacked-password1']);
}

test('confirm_reset needs the account as well as the code', function () {
    $victim = storeUserWithCode('victim@example.com', '123456');

    confirmReset(['verification_code' => '123456'])->assertUnprocessable();
    confirmReset(['email_or_phone' => 'victim@example.com'])->assertUnprocessable();

    expect(Hash::check('old-password', $victim->fresh()->password))->toBeTrue();
});

test('confirm_reset does not accept one account\'s code for another account', function () {
    $victim = storeUserWithCode('victim@example.com', '123456');
    $bystander = storeUserWithCode('bystander@example.com', '654321');

    confirmReset(['email_or_phone' => 'bystander@example.com', 'verification_code' => '123456'])->assertNotFound();

    expect(Hash::check('old-password', $victim->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $bystander->fresh()->password))->toBeTrue()
        ->and($victim->fresh()->verification_code)->toBe('123456');
});

test('confirm_reset resets the named account, by email or by phone, once', function (string $identifier) {
    $user = storeUserWithCode('victim@example.com', '123456', '31612345678');

    confirmReset(['email_or_phone' => $identifier, 'verification_code' => '123456'])->assertOk();

    expect(Hash::check('hacked-password1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->verification_code)->toBeNull();

    confirmReset(['email_or_phone' => $identifier, 'verification_code' => '123456', 'password' => 'again-password1'])->assertNotFound();

    expect(Hash::check('again-password1', $user->fresh()->password))->toBeFalse();
})->with(['by email' => ['victim@example.com'], 'by phone' => ['31612345678']]);

test('guessing a reset code is cut off after five tries from one address', function () {
    $victim = storeUserWithCode('victim@example.com', '123456');

    foreach (range(1, 5) as $guess) {
        confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => (string) (100000 + $guess)])->assertNotFound();
    }

    // Even the right code is refused now: the limit counts attempts.
    confirmReset(['email_or_phone' => 'victim@example.com', 'verification_code' => '123456'])->assertTooManyRequests();

    expect(Hash::check('old-password', $victim->fresh()->password))->toBeTrue();
});

test('guessing a reset code is cut off across addresses too, and anonymous callers do not lock each other out', function () {
    storeUserWithCode('victim@example.com', '123456');

    foreach (range(1, 10) as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.9.0.$address"])
            ->postJson('/temp/api/v2/auth/password/confirm_reset', ['email_or_phone' => 'victim@example.com', 'verification_code' => (string) (100000 + $address), 'password' => 'hacked-password1'])
            ->assertNotFound();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.99'])
        ->postJson('/temp/api/v2/auth/password/confirm_reset', ['email_or_phone' => 'victim@example.com', 'verification_code' => '123456', 'password' => 'hacked-password1'])
        ->assertTooManyRequests();

    // Requests naming no account share no per-account key: ten addresses, none limited.
    foreach (range(1, 10) as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.8.0.$address"])
            ->postJson('/temp/api/v2/auth/password/confirm_reset', [])
            ->assertUnprocessable();
    }
});

test('the web reset code works once', function () {
    $user = storeUserWithCode('web@example.com', '111222');
    $reset = fn (string $password) => test()->post(route('shop.password.update'), [
        'email' => 'web@example.com', 'code' => '111222', 'password' => $password, 'password_confirmation' => $password,
    ]);

    $reset('new-password1')->assertRedirect(route('home'));

    expect($user->fresh()->verification_code)->toBeNull();

    $reset('again-password1')->assertOk();

    expect(Hash::check('new-password1', $user->fresh()->password))->toBeTrue();
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
