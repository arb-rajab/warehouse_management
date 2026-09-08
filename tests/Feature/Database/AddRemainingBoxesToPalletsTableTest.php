<?php

use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddRemainingBoxesToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_08_27_000001_add_remaining_boxes_to_pallets_table.php');
}

test('the migration backfills remaining_boxes from the legacy products.boxes_count column', function () {
    $migration = loadAddRemainingBoxesToPalletsTableMigration();

    Schema::table('pallets', fn (Blueprint $table) => $table->dropColumn('remaining_boxes'));
    // Reconstruct the schema this migration was written against: `boxes_count`
    // was a column on `products` before it moved to wms_product_settings.
    Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('boxes_count')->default(1));

    $product = Product::factory()->create();
    $noiseProduct = Product::factory()->create();
    DB::table('products')->where('id', $product->id)->update(['boxes_count' => 7]);
    DB::table('products')->where('id', $noiseProduct->id)->update(['boxes_count' => 3]);
    $cell = Cell::factory()->create();
    $noiseCell = Cell::factory()->create();

    $palletId = DB::table('pallets')->insertGetId([
        'product_id' => $product->id,
        'cell_id' => $cell->id,
        'expiration_date' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $noisePalletId = DB::table('pallets')->insertGetId([
        'product_id' => $noiseProduct->id,
        'cell_id' => $noiseCell->id,
        'expiration_date' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(Pallet::find($palletId)->remaining_boxes)->toBe(7);
    expect(Pallet::find($noisePalletId)->remaining_boxes)->toBe(3);
});

test('the migration skips the backfill once the legacy products.boxes_count column is gone', function () {
    // The current schema: `products` is the store's table and has never had a
    // `boxes_count` column, so there is nothing to read and the added column
    // keeps its default. Without this guard the migration errors out with
    // "no such column: boxes_count" on every fresh install.
    $migration = loadAddRemainingBoxesToPalletsTableMigration();

    Schema::table('pallets', fn (Blueprint $table) => $table->dropColumn('remaining_boxes'));
    expect(Schema::hasColumn('products', 'boxes_count'))->toBeFalse();

    $product = Product::factory()->boxesCount(7)->create();
    $cell = Cell::factory()->create();
    $palletId = DB::table('pallets')->insertGetId([
        'product_id' => $product->id,
        'cell_id' => $cell->id,
        'expiration_date' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(Pallet::find($palletId)->remaining_boxes)->toBe(0);
});

test('the migration down() drops remaining_boxes cleanly, losing only that column\'s data', function () {
    $migration = loadAddRemainingBoxesToPalletsTableMigration();
    $pallet = Pallet::factory()->create(['remaining_boxes' => 4]);

    $migration->down();

    expect(Schema::hasColumn('pallets', 'remaining_boxes'))->toBeFalse();
    expect(DB::table('pallets')->where('id', $pallet->id)->exists())->toBeTrue();
});
