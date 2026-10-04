<?php

use App\Http\Middleware\UseStoreAuthGuard;
use App\Models\StoreUser;
use App\Notifications\EmailVerificationNotification;
use App\Services\StoreVerificationCodes;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

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
    $user = createStoreUser('customer', 'logintest@example.com');

    $response = $this->post('/temp/login', [
        'email' => 'logintest@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertRedirect();
    expect(Auth::guard('store')->check())->toBeTrue()
        ->and(Auth::guard('store')->id())->toBe($user->id);
});

test('a wrong password is rejected and leaves the store guard unauthenticated', function () {
    createStoreUser('customer', 'logintest2@example.com');

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

/**
 * `login` is also this app's own route name (its admin login), and that one
 * wins, so the store's form used to post a customer's credentials to
 * /admin/login. Posting straight to /temp/login, as the tests above do,
 * never noticed.
 */
test("the store's login form posts to the store's own login route", function () {
    $response = $this->get('/temp/users/login');

    $response->assertOk()
        ->assertSee('action="'.route('shop.login').'"', false)
        ->assertDontSee('action="'.route('login').'"', false);
});

/**
 * The failed login flashes its message through laracasts/flash, which puts a
 * Collection in the session. This app serializes sessions as JSON
 * (config/session.php), so the next request gets it back as a plain array,
 * and the layout's `->toArray()` on it 500'd the very page the user was sent
 * back to. The test harness otherwise keeps session objects in memory between
 * requests; sending the session cookie makes it reload them from the store.
 */
test('a failed login lands back on a login page that renders the error', function () {
    createStoreUser('customer', 'failed-login@example.com');

    $this->from('/temp/users/login')
        ->post('/temp/login', ['email' => 'failed-login@example.com', 'password' => 'wrong-password'])
        ->assertRedirect('/temp/users/login');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/temp/users/login');

    $response->assertOk()
        ->assertSee("AIZ.plugins.notify('danger', 'Invalid login credentials')", false);
});

/**
 * Every logout link in the store's layouts is a plain anchor; the legacy app
 * served them from its own GET /logout, next to the POST one Auth::routes()
 * registers.
 */
test('a customer can log out through the GET link the store layouts use', function () {
    actingAsStoreUser(createStoreUser('customer', 'logout@example.com'));

    $response = $this->get('/temp/logout');

    $response->assertRedirect(route('home'));
    expect(Auth::guard('store')->check())->toBeFalse();
});

test('a store admin logging out is sent to the store login, not this app\'s', function () {
    actingAsStoreUser(createStoreUser('admin', 'logout-admin@example.com'));

    $this->post('/temp/logout')->assertRedirect(route('shop.login'));

    expect(Auth::guard('store')->check())->toBeFalse();
});

test('a guest on a sign-in-only store page is sent to the store login, not this app\'s', function () {
    $this->get('/temp/admin')->assertRedirect(route('shop.login'));
});

test('a signed-in store user opening the store login is sent to the storefront, not this app\'s admin', function () {
    actingAsStoreUser(createStoreUser('customer', 'already-in@example.com'));

    $this->get('/temp/login')->assertRedirect(route('home'));
});

/**
 * VerificationController had `auth` commented out whole in the legacy app, so
 * verification_confirmation()'s emailed code link works for a guest, which
 * left show() reading a null $request->user() and 500ing.
 */
test('a guest opening the email verification notice is sent to the store login', function () {
    $this->get('/temp/email/verify')->assertRedirect(route('shop.login'));
});

/**
 * The link EmailVerificationNotification mails: signed, expiring and bound to
 * the address it was sent to.
 */
function emailVerificationLink(StoreUser $user, ?DateTimeInterface $expires = null): string
{
    return URL::temporarySignedRoute(
        'email.verification.confirmation',
        $expires ?? now()->addHours(EmailVerificationNotification::LINK_LIFETIME_HOURS),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );
}

function unverifiedStoreUser(string $email): StoreUser
{
    $user = createStoreUser('customer', $email);
    $user->forceFill(['email_verified_at' => null])->save();

    return $user->fresh();
}

test('a guest can confirm their email with the emailed link', function () {
    $user = unverifiedStoreUser('confirm@example.com');

    $this->get(emailVerificationLink($user))->assertRedirect(route('dashboard'));

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('store')->id())->toBe($user->id);
});

test('the link the verification email carries is the signed one', function () {
    $user = unverifiedStoreUser('mailed@example.com');

    $mail = (new EmailVerificationNotification)->toMail($user);

    $this->get($mail->viewData['array']['link'])->assertRedirect(route('dashboard'));

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($user->fresh()->verification_code)->toBeNull();
});

test('opening the emailed link a second time signs nobody in', function () {
    $user = unverifiedStoreUser('replay@example.com');
    $link = emailVerificationLink($user);

    $this->get($link)->assertRedirect(route('dashboard'));
    Auth::guard('store')->logout();

    $this->get($link)->assertRedirect(route('dashboard'));

    expect(Auth::guard('store')->check())->toBeFalse();
});

test('an expired link is refused without verifying or signing anyone in', function () {
    $user = unverifiedStoreUser('expired@example.com');
    $link = emailVerificationLink($user);

    $this->travel(EmailVerificationNotification::LINK_LIFETIME_HOURS + 1)->hours();

    $this->get($link)->assertForbidden();

    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a link for another account\'s id, or with its hash altered, is refused', function () {
    $user = unverifiedStoreUser('victim@example.com');
    $other = unverifiedStoreUser('other@example.com');

    $this->get(str_replace('/'.$user->id.'?', '/'.$other->id.'?', emailVerificationLink($user)))->assertForbidden();
    $this->get(emailVerificationLink($user).'&x=1')->assertForbidden();

    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and($other->fresh()->email_verified_at)->toBeNull()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a link sent to an address the user has since changed away from confirms nothing', function () {
    $user = unverifiedStoreUser('old@example.com');
    $link = emailVerificationLink($user);
    $user->forceFill(['email' => 'new@example.com'])->save();

    $this->get($link)->assertRedirect(route('dashboard'));

    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a reset or verification code is no way in through the verification route', function () {
    $user = unverifiedStoreUser('victim@example.com');
    $code = app(StoreVerificationCodes::class)->issue($user);

    // The old route took the code itself, from a guest, and signed in whoever held it.
    $this->get('/temp/verification-confirmation/'.$code)->assertForbidden();
    $this->get('/temp/verification-confirmation/'.$user->id)->assertForbidden();

    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('the verification route is throttled per address', function () {
    foreach (range(1, 30) as $attempt) {
        $this->get('/temp/verification-confirmation/1')->assertForbidden();
    }

    $this->get('/temp/verification-confirmation/1')->assertTooManyRequests();
});

test('a signed-in customer can request another verification email through the GET link the notice uses', function () {
    Notification::fake();
    $customer = createStoreUser('customer', 'resend@example.com');
    $customer->forceFill(['email_verified_at' => null])->save();
    actingAsStoreUser($customer);

    $this->from('/temp/email/verify')->get('/temp/email/resend')->assertRedirect('/temp/email/verify');

    Notification::assertSentTo($customer, EmailVerificationNotification::class);
});
