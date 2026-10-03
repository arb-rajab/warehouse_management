<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Social login for the store's mobile API (POST auth/social-login) and web
 * callbacks. The API matched an account on the `provider` field the client
 * sent, as if it were the provider's id; it overwrote `user_type` of whoever
 * it matched by email; and both linked an existing account by email whatever
 * the provider said about that email. The web callback also read the profile
 * through `stateless()`, skipping the OAuth `state` check, and kept the
 * session id it was handed across the login.
 */
function socialProfile(string $id, ?string $email, bool $emailVerified = true, ?string $name = 'Social Person'): SocialiteUser
{
    return (new SocialiteUser)
        ->setRaw($emailVerified ? ['email_verified' => true] : [])
        ->map(['id' => $id, 'name' => $name, 'email' => $email])
        ->setToken('provider-token');
}

/**
 * Stand in for a Socialite driver whose access-token lookup returns `$profile`.
 * The mock has no `stateless()`, so a controller that calls it fails here.
 */
function fakeSocialProvider(string $driver, SocialiteUser $profile): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('fields')->andReturnSelf();
    $provider->shouldReceive('userFromToken')->andReturn($profile);
    $provider->shouldReceive('user')->andReturn($profile);

    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
}

function socialLogin(array $data = []): TestResponse
{
    return test()->postJson('/temp/api/v2/auth/social-login', $data + ['social_provider' => 'google', 'access_token' => 'client-token']);
}

test('a first social login creates a verified customer from what the provider returned', function () {
    fakeSocialProvider('google', socialProfile('google-1', 'new@example.com'));

    socialLogin()->assertOk()
        ->assertJsonPath('result', true)
        ->assertJsonPath('user.type', 'customer')
        ->assertJsonPath('user.email', 'new@example.com')
        ->assertJsonPath('user.email_verified', true);

    $user = StoreUser::where('email', 'new@example.com')->first();

    expect($user->provider_id)->toBe('google-1')
        ->and($user->provider)->toBe('google')
        ->and($user->user_type)->toBe('customer');
});

test('an account is found by the id the provider returned, not by the provider field the client sent', function () {
    $victim = createStoreUser('customer', 'victim@example.com');
    $victim->forceFill(['provider_id' => 'victim-google-id', 'provider' => 'google'])->save();
    // Noise: another linked account.
    createStoreUser('customer', 'noise@example.com')->forceFill(['provider_id' => 'noise-google-id', 'provider' => 'google'])->save();
    fakeSocialProvider('google', socialProfile('attacker-google-id', 'attacker@example.com'));

    $response = socialLogin(['provider' => 'victim-google-id'])->assertOk();

    expect($response->json('user.id'))->not->toBe($victim->id)
        ->and($response->json('user.email'))->toBe('attacker@example.com')
        ->and($victim->fresh()->provider_id)->toBe('victim-google-id')
        ->and(StoreUser::where('provider_id', 'attacker-google-id')->count())->toBe(1);
});

test('a returning user is signed in by their provider id, with no provider field sent at all', function () {
    $user = createStoreUser('customer', 'returning@example.com');
    $user->forceFill(['provider_id' => 'google-9', 'provider' => 'google'])->save();
    fakeSocialProvider('google', socialProfile('google-9', 'returning@example.com'));

    socialLogin()->assertOk()->assertJsonPath('user.id', $user->id);

    expect($user->fresh()->access_token)->toBe('provider-token');
});

test('a row linked before the provider name was stored is still found by its id', function () {
    $user = createStoreUser('customer', 'older@example.com');
    $user->forceFill(['provider_id' => 'google-5', 'provider' => null])->save();
    fakeSocialProvider('google', socialProfile('google-5', 'older@example.com'));

    socialLogin()->assertOk()->assertJsonPath('user.id', $user->id);

    expect($user->fresh()->provider)->toBe('google');
});

test('one provider\'s id does not sign in an account linked through another provider', function () {
    $user = createStoreUser('customer', 'facebook@example.com');
    $user->forceFill(['provider_id' => '12345', 'provider' => 'facebook'])->save();
    fakeSocialProvider('google', socialProfile('12345', 'someone-else@example.com'));

    $response = socialLogin()->assertOk();

    expect($response->json('user.id'))->not->toBe($user->id);
});

test('a verified provider email links an existing account without touching its user_type', function () {
    $seller = createStoreUser('seller', 'seller@example.com');
    fakeSocialProvider('google', socialProfile('google-2', 'seller@example.com'));

    socialLogin()->assertOk()->assertJsonPath('user.id', $seller->id)->assertJsonPath('user.type', 'seller');

    $fresh = $seller->fresh();

    expect($fresh->user_type)->toBe('seller')
        ->and($fresh->provider_id)->toBe('google-2')
        ->and($fresh->provider)->toBe('google');
});

test('an email the provider has not verified is not linked to the existing account', function () {
    $victim = createStoreUser('customer', 'victim@example.com');
    fakeSocialProvider('google', socialProfile('google-3', 'victim@example.com', emailVerified: false));

    socialLogin()->assertForbidden()->assertJsonPath('result', false)->assertJsonPath('user', null);

    expect($victim->fresh()->provider_id)->toBeNull()
        ->and(StoreUser::count())->toBe(1)
        ->and(DB::table('wms_personal_access_tokens')->count())->toBe(0);
});

test('a provider that does not report email verification never links an existing account', function () {
    $victim = createStoreUser('customer', 'victim@example.com');
    fakeSocialProvider('facebook', socialProfile('fb-1', 'victim@example.com', emailVerified: false));

    socialLogin(['social_provider' => 'facebook'])->assertForbidden();

    expect($victim->fresh()->provider_id)->toBeNull();
});

test('an account already linked to another identity is not relinked by its email', function () {
    $user = createStoreUser('customer', 'linked@example.com');
    $user->forceFill(['provider_id' => 'first-identity', 'provider' => 'google'])->save();
    fakeSocialProvider('google', socialProfile('second-identity', 'linked@example.com'));

    socialLogin()->assertForbidden();

    expect($user->fresh()->provider_id)->toBe('first-identity');
});

test('a new account from an unverified provider email is created unverified', function () {
    fakeSocialProvider('facebook', socialProfile('fb-2', 'fresh@example.com', emailVerified: false));

    socialLogin(['social_provider' => 'facebook'])->assertOk()->assertJsonPath('user.email_verified', false);

    expect(StoreUser::where('email', 'fresh@example.com')->first()->email_verified_at)->toBeNull();
});

test('a banned account cannot sign in through a provider', function () {
    $user = createStoreUser('customer', 'banned@example.com');
    $user->forceFill(['provider_id' => 'google-7', 'provider' => 'google', 'banned' => 1])->save();
    fakeSocialProvider('google', socialProfile('google-7', 'banned@example.com'));

    socialLogin()->assertUnauthorized()->assertJsonPath('result', false);

    expect(DB::table('wms_personal_access_tokens')->count())->toBe(0);
});

test('an unknown provider name is refused before any lookup', function () {
    socialLogin(['social_provider' => 'myspace'])->assertOk()->assertJsonPath('result', false);

    expect(StoreUser::count())->toBe(0);
});

test('the web callback signs a new user in and replaces the session token', function () {
    fakeSocialProvider('google', socialProfile('google-4', 'web@example.com'));

    $this->withSession(['_token' => 'token-before-login'])
        ->get('/temp/social-login/google/callback')
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('store')->user()->email)->toBe('web@example.com')
        ->and(session('_token'))->not->toBe('token-before-login');
});

test('the web callback sends a linked seller to the seller dashboard and keeps them a seller', function () {
    $seller = createStoreUser('seller', 'webseller@example.com');
    fakeSocialProvider('google', socialProfile('google-6', 'webseller@example.com'));

    $this->get('/temp/social-login/google/callback')->assertRedirect(route('seller.dashboard'));

    expect($seller->fresh()->user_type)->toBe('seller')
        ->and(Auth::guard('store')->id())->toBe($seller->id);
});

test('the web callback refuses to link an unverified email and signs nobody in', function () {
    $victim = createStoreUser('customer', 'victim@example.com');
    fakeSocialProvider('google', socialProfile('google-8', 'victim@example.com', emailVerified: false));

    $this->get('/temp/social-login/google/callback')->assertRedirect(route('user.login'));

    expect($victim->fresh()->provider_id)->toBeNull()
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('a failing provider callback goes back to the login page', function () {
    Socialite::shouldReceive('driver')->with('google')->andThrow(new RuntimeException('state mismatch'));

    $this->get('/temp/social-login/google/callback')->assertRedirect(route('user.login'));

    expect(Auth::guard('store')->check())->toBeFalse();
});
