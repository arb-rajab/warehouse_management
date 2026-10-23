<?php

use App\Mail\SecondEmailVerifyMailManager;
use App\Notifications\PasswordResetRequest;
use App\Services\StoreVerificationCodes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * The six-digit code in the last reset email the test queued.
 */
function emailedResetCode(): string
{
    $mail = Mail::queued(SecondEmailVerifyMailManager::class)->last();

    preg_match('/\d{6}/', $mail->array['content'], $matches);

    return $matches[0];
}

/**
 * The store's own password reset: a code is emailed, and the reset form
 * posts it back to the route that is now `shop.password.update` (this app's
 * own `password.update` is its admin password change). Its "forgot password"
 * page used to 500: laravel/ui's trait rendered an unprefixed view.
 */
test('the forgot-password page renders', function () {
    $this->get('/temp/password/reset')->assertOk();
});

test('a customer can reset a forgotten password with the emailed code', function () {
    Mail::fake();
    $customer = createStoreUser('customer', 'forgot@example.com', 'old-password');

    $this->post('/temp/password/email', ['email' => 'forgot@example.com'])
        ->assertOk()
        ->assertSee('action="'.route('shop.password.update').'"', false);

    Mail::assertQueued(SecondEmailVerifyMailManager::class, fn (SecondEmailVerifyMailManager $mail) => $mail->hasTo('forgot@example.com'));

    $this->post(route('shop.password.update'), [
        'email' => 'forgot@example.com',
        'code' => emailedResetCode(),
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertRedirect(route('home'));

    expect(Hash::check('new-password1', $customer->fresh()->password))->toBeTrue()
        ->and(Auth::guard('store')->id())->toBe($customer->id);
});

test('a wrong reset code leaves the password alone', function () {
    Mail::fake();
    $customer = createStoreUser('customer', 'forgot@example.com', 'old-password');
    $this->post('/temp/password/email', ['email' => 'forgot@example.com'])->assertOk();

    $this->post(route('shop.password.update'), [
        'email' => 'forgot@example.com',
        'code' => wrongCode(emailedResetCode()),
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertOk();

    expect(Hash::check('old-password', $customer->fresh()->password))->toBeTrue()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a reset for an unknown email sends nothing and answers as it does for a known one', function () {
    Mail::fake();
    createStoreUser('customer', 'known@example.com');

    $known = $this->post('/temp/password/email', ['email' => 'known@example.com']);
    Mail::assertQueuedCount(1);

    $unknown = $this->post('/temp/password/email', ['email' => 'nobody@example.com']);

    // Nothing more was sent, and nothing in the answer tells the two apart.
    Mail::assertQueuedCount(1);
    $unknown->assertOk()->assertSee('action="'.route('shop.password.update').'"', false)->assertDontSee('No account exists');
    expect($unknown->getContent())->toBe($known->getContent());
});

test('a reset for an unknown phone number goes back to the form without naming an account', function () {
    $this->from('/temp/password/reset')
        ->post('/temp/password/email', ['country_code' => '31', 'phone' => '612345678'])
        ->assertRedirect('/temp/password/reset')
        ->assertSessionHas('flash_notification', fn ($notices) => ! str_contains(json_encode($notices), 'No account exists'));
});

test('asking for a reset code again inside the cooldown sends no second email and keeps the first code', function () {
    Mail::fake();
    $customer = createStoreUser('customer', 'forgot@example.com', 'old-password');

    $this->post('/temp/password/email', ['email' => 'forgot@example.com'])->assertOk();
    $firstCode = emailedResetCode();
    $this->post('/temp/password/email', ['email' => 'forgot@example.com'])->assertOk();

    Mail::assertQueuedCount(1);

    $this->post(route('shop.password.update'), [
        'email' => 'forgot@example.com', 'code' => $firstCode, 'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ])->assertRedirect(route('home'));

    expect(Hash::check('new-password1', $customer->fresh()->password))->toBeTrue();
});

test('a reset code can be asked for again once the cooldown has passed, and replaces the first', function () {
    Mail::fake();
    $customer = createStoreUser('customer', 'forgot@example.com', 'old-password');

    $this->post('/temp/password/email', ['email' => 'forgot@example.com'])->assertOk();
    $firstCode = emailedResetCode();

    $this->travel(StoreVerificationCodes::RESEND_COOLDOWN_SECONDS + 1)->seconds();
    $this->post('/temp/password/email', ['email' => 'forgot@example.com'])->assertOk();

    Mail::assertQueuedCount(2);
    $secondCode = emailedResetCode();

    $this->post(route('shop.password.update'), [
        'email' => 'forgot@example.com', // The first code is dead once a second was issued (unless the two collide, 1 in 900,000).
        'code' => $firstCode === $secondCode ? wrongCode($secondCode) : $firstCode,
        'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ])->assertOk();

    expect(Hash::check('old-password', $customer->fresh()->password))->toBeTrue();
});

/**
 * Read through env(), the link's host was null once `config:cache` had run.
 */
test('the emailed password reset link is built from the configured app url', function () {
    config(['app.url' => 'https://shop.example.test']);

    $mail = (new PasswordResetRequest('abc123'))->toMail(createStoreUser());

    expect($mail->actionUrl)->toBe('https://shop.example.test/password/reset/abc123');
});
