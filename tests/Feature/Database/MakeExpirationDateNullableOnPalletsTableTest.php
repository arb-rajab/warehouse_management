<?php

use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadMakeExpirationDateNullableOnPalletsTableMigration(): object
{
    return require database_path('migrations/2026_09_16_040000_make_expiration_date_nullable_on_pallets_table.php');
}

test('down() backfills a null expiration_date before restoring NOT NULL', function () {
    $row = Row::create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = Cell::create(['row_id' => $row->id, 'cell_number' => 1, 'flat_number' => 1, 'state' => 'empty']);
    $product = Product::create(['name' => 'Widgets', 'ar_name' => '', 'published' => 1]);
    $pallet = Pallet::create([
        'product_id' => $product->id,
        'cell_id' => $cell->id,
        'expiration_date' => null,
        'remaining_boxes' => 0,
    ]);

    loadMakeExpirationDateNullableOnPalletsTableMigration()->down();

    expect(DB::table('pallets')->where('id', $pallet->id)->value('expiration_date'))->not->toBeNull();

    // Restoring NOT NULL must actually succeed rather than throw.
    expect(fn () => DB::table('pallets')->insert([
        'product_id' => $product->id,
        'cell_id' => Cell::create(['row_id' => $row->id, 'cell_number' => 2, 'flat_number' => 1, 'state' => 'empty'])->id,
        'expiration_date' => null,
        'remaining_boxes' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);

    loadMakeExpirationDateNullableOnPalletsTableMigration()->up();

    expect(Schema::hasColumn('pallets', 'expiration_date'))->toBeTrue();
});
