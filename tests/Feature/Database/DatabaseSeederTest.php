<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

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
