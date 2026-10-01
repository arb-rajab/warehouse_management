<?php

use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Guard;

/**
 * Regression coverage for config/auth.php's 'store' guard/provider pair,
 * merged from the legacy store's own config (see
 * .ai/rules/legacy-albaraka-holland.md sections 2 and 3). A distinct guard
 * is what keeps hasRole() unambiguous between this app's own User (guard
 * web) and the store's StoreUser once their spatie roles share the same
 * permission tables — a wiring mistake here (wrong provider/model) would
 * only surface the first time something actually logs a StoreUser in.
 */
test('the store guard resolves a StoreUser through its own provider', function () {
    $storeUser = StoreUser::create([
        'name' => 'Store Customer',
        'email' => 'store-customer@example.com',
        'user_type' => 'customer',
        'phone' => '5555555555',
    ]);

    Auth::guard('store')->login($storeUser);

    expect(Auth::guard('store')->user())
        ->toBeInstanceOf(StoreUser::class)
        ->id->toBe($storeUser->id);
});

test('logging into the store guard does not also authenticate the default web guard', function () {
    $storeUser = StoreUser::create([
        'name' => 'Store Customer',
        'email' => 'store-customer-2@example.com',
        'user_type' => 'customer',
        'phone' => '5555555555',
    ]);

    Auth::guard('store')->login($storeUser);

    expect(Auth::guard('web')->check())->toBeFalse();
});

test('each api token guard is bound to its own user provider, and neither trusts a session guard', function () {
    expect(config('auth.guards.sanctum'))->toBe(['driver' => 'sanctum', 'provider' => 'users'])
        ->and(config('auth.guards.store_api'))->toBe(['driver' => 'sanctum', 'provider' => 'store_users'])
        ->and(config('auth.providers.users.model'))->toBe(User::class)
        ->and(config('auth.providers.store_users.model'))->toBe(StoreUser::class)
        ->and(config('sanctum.guard'))->toBe([]);
});

/**
 * Both user models are named by two guards' providers (`web` and `sanctum` for
 * User, `store` and `store_api` for StoreUser), and Auth::shouldUse() moves
 * auth.defaults.guard to whichever guard a request authenticated on. Left to
 * derive spatie's guard from that, assignRole('x') and hasPermissionTo('x')
 * would look for a role on `store_api` or `sanctum`, which no row has.
 */
test('spatie resolves each user model to one guard whichever guard is the default', function (string $defaultGuard) {
    Auth::shouldUse($defaultGuard);

    expect(Guard::getNames(User::class)->all())->toBe(['web'])
        ->and(Guard::getNames(StoreUser::class)->all())->toBe(['store'])
        ->and(Guard::getDefaultName(new User))->toBe('web')
        ->and(Guard::getDefaultName(new StoreUser))->toBe('store');
})->with(['web', 'store', 'sanctum', 'store_api']);
