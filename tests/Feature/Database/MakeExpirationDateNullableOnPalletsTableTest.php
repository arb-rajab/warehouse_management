<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadMakeExpirationDateNullableOnPalletsTableMigration(): object
{
    return require database_path('migrations/2026_09_16_040000_make_expiration_date_nullable_on_pallets_table.php');
}

test('down() backfills a null expiration_date before restoring NOT NULL', function () {
    // RowObserver auto-generates cells_count x flats_count empty cells, so
    // creating them here directly would collide with the generated rows.
    $row = Row::create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);
    [$cell, $secondCell] = $row->cells()->orderBy('cell_number')->get();
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
        'cell_id' => $secondCell->id,
        'expiration_date' => null,
        'remaining_boxes' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    loadMakeExpirationDateNullableOnPalletsTableMigration()->up();

    expect(Schema::hasColumn('pallets', 'expiration_date'))->toBeTrue();
});
