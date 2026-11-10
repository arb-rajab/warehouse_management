<?php

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the receipts table has the columns the receiving flow reads', function () {
    expect(Schema::hasColumns('receipts', [
        'id', 'warehouse_id', 'note', 'status', 'created_by', 'finalized_by', 'finalized_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('note, finalized_by and finalized_at are nullable while the warehouse, status and author are not', function () {
    $columns = collect(Schema::getColumns('receipts'))->keyBy('name');

    expect($columns['note']['nullable'])->toBeTrue()
        ->and($columns['finalized_by']['nullable'])->toBeTrue()
        ->and($columns['finalized_at']['nullable'])->toBeTrue()
        ->and($columns['warehouse_id']['nullable'])->toBeFalse()
        ->and($columns['status']['nullable'])->toBeFalse()
        ->and($columns['created_by']['nullable'])->toBeFalse();
});

test('a receipt inserted without a status is a draft', function () {
    $id = DB::table('receipts')->insertGetId([
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'created_by' => User::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('receipts')->where('id', $id)->value('status'))->toBe(ReceiptStatus::Draft->value);
});

test('a receipt needs a warehouse and an author', function () {
    $insert = fn (array $attributes) => fn () => DB::table('receipts')->insert([
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);

    expect($insert(['warehouse_id' => null, 'created_by' => User::factory()->create()->id]))->toThrow(QueryException::class);
    expect($insert(['warehouse_id' => Warehouse::firstOrCreateDefault()->id, 'created_by' => null]))->toThrow(QueryException::class);
    expect($insert(['warehouse_id' => Warehouse::firstOrCreateDefault()->id, 'created_by' => 999_999]))->toThrow(QueryException::class);
    expect(Receipt::query()->count())->toBe(0);
});

test('the receipts table indexes the columns the listings filter and sort by', function () {
    $indexed = collect(Schema::getIndexes('receipts'))->pluck('columns')->map(fn (array $columns) => implode(',', $columns));

    expect($indexed->all())->toContain('warehouse_id', 'status', 'created_by', 'finalized_by', 'created_at');
});

test('deleting a warehouse that has a receipt is restricted, leaving an unrelated warehouse deletable', function () {
    $receipt = Receipt::factory()->for(Warehouse::factory()->create())->create();
    $unrelated = Warehouse::factory()->create(); // noise

    expect(fn () => $receipt->warehouse->delete())->toThrow(QueryException::class);
    expect(Warehouse::query()->whereKey($receipt->warehouse_id)->exists())->toBeTrue();

    $unrelated->delete();
    expect(Warehouse::query()->whereKey($unrelated->id)->exists())->toBeFalse();
});

test('hard deleting the user who created a receipt is restricted', function () {
    $receipt = Receipt::factory()->create();
    $author = $receipt->createdBy;

    expect(fn () => $author->forceDelete())->toThrow(QueryException::class);
    expect(User::withTrashed()->whereKey($author->id)->exists())->toBeTrue();
});

test('hard deleting the user who finalized a receipt is restricted', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $finalizer = $receipt->finalizedBy;

    expect(fn () => $finalizer->forceDelete())->toThrow(QueryException::class);
    expect(User::withTrashed()->whereKey($finalizer->id)->exists())->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = require database_path('migrations/2026_11_09_100000_create_receipts_table.php');

    // receipt_files and receipt_pallets reference receipts, so they go first.
    Schema::dropIfExists('receipt_pallets');
    Schema::dropIfExists('receipt_files');

    $migration->down();
    expect(Schema::hasTable('receipts'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('receipts'))->toBeTrue();
});
