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

test('the migration backfills remaining_boxes from the pallet\'s product boxes_count', function () {
    $migration = loadAddRemainingBoxesToPalletsTableMigration();

    Schema::table('pallets', fn (Blueprint $table) => $table->dropColumn('remaining_boxes'));

    $product = Product::factory()->create(['boxes_count' => 7]);
    $noiseProduct = Product::factory()->create(['boxes_count' => 3]);
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

test('the migration down() drops remaining_boxes cleanly, losing only that column\'s data', function () {
    $migration = loadAddRemainingBoxesToPalletsTableMigration();
    $pallet = Pallet::factory()->create(['remaining_boxes' => 4]);

    $migration->down();

    expect(Schema::hasColumn('pallets', 'remaining_boxes'))->toBeFalse();
    expect(DB::table('pallets')->where('id', $pallet->id)->exists())->toBeTrue();
});
