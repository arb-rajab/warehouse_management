<?php

use App\Models\Product;
use Database\Seeders\ProductSeeder;

test('seeds ten products', function () {
    (new ProductSeeder)->run();

    expect(Product::count())->toBe(10);
});

test('running the seeder twice does not throw and adds more products', function () {
    (new ProductSeeder)->run();
    (new ProductSeeder)->run();

    expect(Product::count())->toBe(20);
});
