<?php

use App\Models\Pallet;
use App\Models\Receipt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function loadAddReceiptIdToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_11_09_100004_add_receipt_id_to_pallets_table.php');
}

test('pallets.receipt_id is a nullable column with an index', function () {
    $column = collect(Schema::getColumns('pallets'))->firstWhere('name', 'receipt_id');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeTrue()
        ->and(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['receipt_id']))->toBeTrue();
});

test('a pallet without a receipt is still valid', function () {
    $pallet = Pallet::factory()->create();

    expect($pallet->fresh()->receipt_id)->toBeNull();
});

test('a placed pallet keeps the receipt it arrived on', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $pallet = Pallet::factory()->create(['receipt_id' => $receipt->id]);

    expect($pallet->fresh()->receipt_id)->toBe($receipt->id)
        ->and($pallet->fresh()->cell_id)->not->toBeNull();
});

test('a pallet cannot point at a receipt that does not exist', function () {
    expect(fn () => Pallet::factory()->unplaced()->create(['receipt_id' => 999_999]))->toThrow(QueryException::class);
});

test('deleting a receipt that has pallets is restricted and the pallets keep their link', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]);

    expect(fn () => $receipt->delete())->toThrow(QueryException::class);

    expect(Receipt::query()->whereKey($receipt->id)->exists())->toBeTrue()
        ->and($pallet->fresh()->receipt_id)->toBe($receipt->id);
});

test('a receipt without pallets can be deleted while a receipt with pallets survives', function () {
    $empty = Receipt::factory()->create();
    $used = Receipt::factory()->finalized()->create(); // noise
    Pallet::factory()->unplaced()->create(['receipt_id' => $used->id]);

    $empty->delete();

    expect(Receipt::query()->whereKey($empty->id)->exists())->toBeFalse()
        ->and(Receipt::query()->whereKey($used->id)->exists())->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddReceiptIdToPalletsTableMigration();

    $migration->down();
    expect(Schema::hasColumn('pallets', 'receipt_id'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('pallets', 'receipt_id'))->toBeTrue()
        ->and(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['receipt_id']))->toBeTrue();
});
