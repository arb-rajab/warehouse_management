<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptPallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the receipt_pallets table has the columns the receipt detail reads', function () {
    expect(Schema::hasColumns('receipt_pallets', [
        'id', 'receipt_id', 'product_id', 'boxes', 'expiration_date', 'pallet_id', 'position', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('only the expiration date and the finalized pallet link are nullable', function () {
    $columns = collect(Schema::getColumns('receipt_pallets'))->keyBy('name');

    expect($columns['expiration_date']['nullable'])->toBeTrue()
        ->and($columns['pallet_id']['nullable'])->toBeTrue();

    foreach (['receipt_id', 'product_id', 'boxes', 'position'] as $column) {
        expect($columns[$column]['nullable'])->toBeFalse($column);
    }
});

test('the receipt_pallets table indexes its three foreign keys', function () {
    $indexed = collect(Schema::getIndexes('receipt_pallets'))->pluck('columns')->map(fn (array $columns) => implode(',', $columns));

    expect($indexed->all())->toContain('receipt_id', 'product_id', 'pallet_id');
});

test('an entry can be stored without an expiry or a pallet', function () {
    $receipt = Receipt::factory()->create();

    $entry = ReceiptPallet::factory()->for($receipt)->create(['expiration_date' => null, 'pallet_id' => null]);

    expect($entry->fresh()->expiration_date)->toBeNull()
        ->and($entry->fresh()->pallet_id)->toBeNull();
});

test('deleting a receipt deletes its pallet entries, leaving another receipts entries intact', function () {
    $receipt = Receipt::factory()->create();
    $survivor = Receipt::factory()->create(); // noise
    ReceiptPallet::factory()->for($receipt)->count(2)->create();
    ReceiptPallet::factory()->for($survivor)->create();

    $receipt->delete();

    expect(DB::table('receipt_pallets')->where('receipt_id', $receipt->id)->count())->toBe(0);
    expect(DB::table('receipt_pallets')->where('receipt_id', $survivor->id)->count())->toBe(1);
});

test('deleting a product named by an entry is restricted and the product survives', function () {
    $entry = ReceiptPallet::factory()->create();
    $product = $entry->product;

    expect(fn () => $product->delete())->toThrow(QueryException::class);
    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

test('deleting the pallet an entry produced nulls the link and keeps the entry', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]);
    $other = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]); // noise
    $entry = ReceiptPallet::factory()->for($receipt)->create(['pallet_id' => $pallet->id]);
    $otherEntry = ReceiptPallet::factory()->for($receipt)->create(['pallet_id' => $other->id]);

    $pallet->delete();

    expect($entry->fresh())->not->toBeNull()
        ->and($entry->fresh()->pallet_id)->toBeNull()
        ->and($otherEntry->fresh()->pallet_id)->toBe($other->id);
});

test('the migration is reversible', function () {
    $migration = require database_path('migrations/2026_11_09_100002_create_receipt_pallets_table.php');

    $migration->down();
    expect(Schema::hasTable('receipt_pallets'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('receipt_pallets'))->toBeTrue();
});
