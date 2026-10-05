<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Role;
use App\Models\StoreUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StoreUserSeeder;
use Illuminate\Support\Facades\Hash;

test('refuses to run in production', function () {
    app()->instance('env', 'production');

    try {
        expect(fn () => (new DatabaseSeeder)->run())->toThrow(RuntimeException::class);
    } finally {
        app()->instance('env', 'testing');
    }

    expect(User::count())->toBe(0);
});

test('seeds the full chain, including pallets, when not in production', function () {
    (new DatabaseSeeder)->run();

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
    expect(Product::count())->toBeGreaterThan(0);
    expect(Pallet::count())->toBeGreaterThan(0);
});

test('seeds a store admin who holds Super Admin, beside the warehouse admin, once however often it runs', function () {
    (new DatabaseSeeder)->run();
    (new StoreUserSeeder)->run();

    $storeAdmin = StoreUser::where('email', 'store-admin@example.com')->sole();

    expect($storeAdmin->user_type)->toBe('admin')
        ->and(Hash::check('password', $storeAdmin->password))->toBeTrue()
        ->and($storeAdmin->hasRole(Role::SUPER_ADMIN, 'store'))->toBeTrue()
        ->and(Role::where('name', 'Clerk')->exists())->toBeTrue()
        ->and(User::where('email', 'store-admin@example.com')->exists())->toBeFalse();
});

test('seeds no store admin without the Super Admin role to give them', function () {
    (new StoreUserSeeder)->run();

    expect(StoreUser::where('email', 'store-admin@example.com')->exists())->toBeFalse();
});
