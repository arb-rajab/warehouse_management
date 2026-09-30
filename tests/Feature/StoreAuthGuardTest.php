<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\Auth;

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
