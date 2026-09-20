<?php

use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use Database\Seeders\PalletSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RowSeeder;

test('early-returns without a cell or a product to seed against', function () {
    (new PalletSeeder)->run();

    expect(Pallet::count())->toBe(0);

    (new RowSeeder)->run();
    (new PalletSeeder)->run();

    expect(Pallet::count())->toBe(0);
});

test('running the seeder twice does not collide on the unique cell_id constraint', function () {
    (new ProductSeeder)->run();
    (new RowSeeder)->run();

    $cellCount = Cell::count();

    (new PalletSeeder)->run();

    $firstRunPalletCount = Pallet::count();
    expect($firstRunPalletCount)->toBeGreaterThan(0)->toBeLessThanOrEqual($cellCount);

    (new PalletSeeder)->run();

    // No collision, and every occupied cell still holds exactly one pallet.
    expect(Pallet::count())->toBeGreaterThanOrEqual($firstRunPalletCount);
    expect(Pallet::query()->distinct('cell_id')->count('cell_id'))->toBe(Pallet::count());
});

test('never assigns a pallet to a cell that already holds one', function () {
    (new ProductSeeder)->run();
    (new RowSeeder)->run();

    $product = Product::query()->firstOrFail();
    $preOccupiedCell = Cell::query()->firstOrFail();

    Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $preOccupiedCell->id,
    ]);

    (new PalletSeeder)->run();

    expect(Pallet::where('cell_id', $preOccupiedCell->id)->count())->toBe(1);
    expect(Pallet::query()->distinct('cell_id')->count('cell_id'))->toBe(Pallet::count());
});
