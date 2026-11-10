<?php

use App\Models\Receipt;
use App\Models\ReceiptFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the receipt_files table has the columns the bill download reads', function () {
    expect(Schema::hasColumns('receipt_files', [
        'id', 'receipt_id', 'path', 'original_name', 'mime_type', 'size', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('every column of a bill file is required', function () {
    $columns = collect(Schema::getColumns('receipt_files'))->keyBy('name');

    foreach (['receipt_id', 'path', 'original_name', 'mime_type', 'size'] as $column) {
        expect($columns[$column]['nullable'])->toBeFalse($column);
    }
});

test('receipt_files.receipt_id has an index', function () {
    expect(collect(Schema::getIndexes('receipt_files'))->contains(fn (array $index) => $index['columns'] === ['receipt_id']))->toBeTrue();
});

test('a bill file cannot point at a receipt that does not exist', function () {
    expect(fn () => ReceiptFile::factory()->create(['receipt_id' => 999_999]))->toThrow(QueryException::class);
    expect(ReceiptFile::query()->count())->toBe(0);
});

test('a receipt can hold several files', function () {
    $receipt = Receipt::factory()->create();

    ReceiptFile::factory()->for($receipt)->count(3)->create();

    expect(ReceiptFile::query()->where('receipt_id', $receipt->id)->count())->toBe(3);
});

test('deleting a receipt deletes its files, leaving another receipts files intact', function () {
    $receipt = Receipt::factory()->create();
    $survivor = Receipt::factory()->create(); // noise
    ReceiptFile::factory()->for($receipt)->count(2)->create();
    ReceiptFile::factory()->for($survivor)->create();

    $receipt->delete();

    expect(DB::table('receipt_files')->where('receipt_id', $receipt->id)->count())->toBe(0);
    expect(DB::table('receipt_files')->where('receipt_id', $survivor->id)->count())->toBe(1);
});

test('the migration is reversible', function () {
    $migration = require database_path('migrations/2026_11_09_100001_create_receipt_files_table.php');

    $migration->down();
    expect(Schema::hasTable('receipt_files'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('receipt_files'))->toBeTrue();
});
