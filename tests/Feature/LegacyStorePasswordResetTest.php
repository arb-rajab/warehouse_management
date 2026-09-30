<?php

use App\Mail\SecondEmailVerifyMailManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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
        'code' => $customer->fresh()->verification_code,
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
        'code' => 'not-the-code',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertOk();

    expect(Hash::check('old-password', $customer->fresh()->password))->toBeTrue()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a reset for an unknown email sends nothing', function () {
    Mail::fake();

    $this->from('/temp/password/reset')
        ->post('/temp/password/email', ['email' => 'nobody@example.com'])
        ->assertRedirect('/temp/password/reset');

    Mail::assertNothingQueued();
});
