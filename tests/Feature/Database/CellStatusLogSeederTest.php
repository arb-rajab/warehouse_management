<?php

use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CellStatusLogSeeder;
use Database\Seeders\PalletSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RowSeeder;

test('early-returns without cells, products, users, or a pallet to seed logs against', function () {
    (new CellStatusLogSeeder)->run();

    expect(CellStatusLog::count())->toBe(0);

    (new RowSeeder)->run();
    (new ProductSeeder)->run();
    User::factory()->create();
    (new CellStatusLogSeeder)->run();

    // Rows/products/users exist but no pallet has ever been stored, so the
    // seeder still has nothing real to model logs against.
    expect(CellStatusLog::count())->toBe(0);
});

test('does not crash generating a transfer log against a single-cell warehouse', function () {
    $cell = Cell::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();

    Pallet::factory()->create([
        'cell_id' => $cell->id,
        'product_id' => $product->id,
    ]);

    // Noise: another product/user that the single-cell seeder run must not
    // need in order to succeed.
    Product::factory()->create();
    User::factory()->create();

    (new CellStatusLogSeeder)->run();

    expect(CellStatusLog::count())->toBeGreaterThan(0);
    expect(CellStatusLog::where('action', 'transferred_out')->count())->toBe(0);
});

test('running the seeder twice does not throw', function () {
    (new RowSeeder)->run();
    (new ProductSeeder)->run();
    (new PalletSeeder)->run();
    User::factory()->create();

    (new CellStatusLogSeeder)->run();
    $firstRunCount = CellStatusLog::count();

    (new CellStatusLogSeeder)->run();

    expect(CellStatusLog::count())->toBeGreaterThan($firstRunCount);
});
