<?php

use App\Http\Middleware\UseStoreAuthGuard;
use App\Models\StoreUser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Regression coverage for the legacy store's login flow, which was
 * completely broken end to end before two fixes landed together:
 *
 * 1. app/Http/Middleware/UseStoreAuthGuard.php: LoginController (via
 *    AuthenticatesUsers) and every custom role-gate middleware (IsAdmin,
 *    IsUser, IsCustomer, IsSeller) call the *bare* Auth::check()/
 *    Auth::user()/$this->guard(), none of which name a guard — so all of
 *    them resolved to config('auth.defaults.guard') ('web', provider
 *    App\Models\User, this app's own admin model). No StoreUser could ever
 *    authenticate through any of them; the 'store' guard existed in
 *    config/auth.php but nothing under /temp actually used it.
 * 2. LoginController/RegisterController/ForgotPasswordController/
 *    ResetPasswordController/VerificationController all called
 *    $this->middleware(...) in their constructors — a laravel/ui scaffold
 *    pattern removed from the base Controller in Laravel 11. Every one of
 *    them threw "Call to undefined method ::middleware()" the moment it was
 *    instantiated, before any action method ran, independently of the guard
 *    bug above.
 *
 * Together these meant every request to POST /temp/login 500'd (bug 2)
 * even before it could reach the point where bug 1 would have rejected
 * valid credentials anyway.
 */
test('a StoreUser can log in through the legacy login form and is authenticated on the store guard', function () {
    $user = StoreUser::create([
        'name' => 'Login Test', 'email' => 'logintest@example.com', 'user_type' => 'customer', 'phone' => '9998887777',
    ]);
    $user->password = Hash::make('correct-password');
    $user->save();

    $response = $this->post('/temp/login', [
        'email' => 'logintest@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertRedirect();
    expect(Auth::guard('store')->check())->toBeTrue()
        ->and(Auth::guard('store')->id())->toBe($user->id);
});

test('a wrong password is rejected and leaves the store guard unauthenticated', function () {
    $user = StoreUser::create([
        'name' => 'Login Test', 'email' => 'logintest2@example.com', 'user_type' => 'customer', 'phone' => '9998887778',
    ]);
    $user->password = Hash::make('correct-password');
    $user->save();

    $response = $this->post('/temp/login', [
        'email' => 'logintest2@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect();
    expect(Auth::guard('store')->check())->toBeFalse();
});

test('UseStoreAuthGuard makes the bare Auth facade resolve to the store guard', function () {
    $middleware = new UseStoreAuthGuard;

    $called = false;
    $middleware->handle(request(), function () use (&$called) {
        $called = true;

        return new Response;
    });

    expect($called)->toBeTrue()
        ->and(Auth::getDefaultDriver())->toBe('store');
});
