<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `add_barcode_index_to_product_barcodes_table`: a plain index on `barcode`,
 * added only when the column has none, never dropped in production. Also
 * meant for the MySQL lane, where DDL commits: every test leaves the index as
 * the migration made it.
 */
function loadAddBarcodeIndexMigration(): object
{
    return require database_path('migrations/2026_11_08_100000_add_barcode_index_to_product_barcodes_table.php');
}

/** @return list<array<string, mixed>> */
function barcodeColumnIndexes(): array
{
    return array_values(array_filter(Schema::getIndexes('product_barcodes'), fn (array $index): bool => $index['columns'] === ['barcode']));
}

function dropBarcodeIndexes(): void
{
    foreach (barcodeColumnIndexes() as $index) {
        Schema::table('product_barcodes', fn (Blueprint $table) => $table->dropIndex($index['name']));
    }
}

afterEach(function () {
    if (barcodeColumnIndexes() === [] || ! Schema::hasIndex('product_barcodes', 'product_barcodes_barcode_index')) {
        dropBarcodeIndexes();
        loadAddBarcodeIndexMigration()->up();
    }
});

test('the migration adds one plain, non-unique index on the barcode column', function () {
    $indexes = barcodeColumnIndexes();

    expect($indexes)->toHaveCount(1)
        ->and($indexes[0]['name'])->toBe('product_barcodes_barcode_index')
        ->and($indexes[0]['unique'])->toBeFalse()
        ->and($indexes[0]['primary'])->toBeFalse();
});

test('running up() again adds no second index', function () {
    loadAddBarcodeIndexMigration()->up();

    expect(barcodeColumnIndexes())->toHaveCount(1);
});

test('an existing index on the column, under any name, is left alone', function () {
    dropBarcodeIndexes();
    Schema::table('product_barcodes', fn (Blueprint $table) => $table->index('barcode', 'store_barcode_lookup'));

    loadAddBarcodeIndexMigration()->up();

    expect(array_column(barcodeColumnIndexes(), 'name'))->toBe(['store_barcode_lookup']);
});

test('down() drops the index outside production and keeps it in production', function () {
    $migration = loadAddBarcodeIndexMigration();

    app()->instance('env', 'production');
    $migration->down();
    app()->instance('env', 'testing');
    expect(Schema::hasIndex('product_barcodes', 'product_barcodes_barcode_index'))->toBeTrue();

    $migration->down();
    expect(barcodeColumnIndexes())->toBe([]);
});

test('down() leaves an index of another name alone', function () {
    dropBarcodeIndexes();
    Schema::table('product_barcodes', fn (Blueprint $table) => $table->index('barcode', 'store_barcode_lookup'));

    loadAddBarcodeIndexMigration()->down();

    expect(array_column(barcodeColumnIndexes(), 'name'))->toBe(['store_barcode_lookup']);
});

test('up() is a no-op without the table', function () {
    Schema::rename('product_barcodes', 'product_barcodes_away');

    try {
        loadAddBarcodeIndexMigration()->up();
        expect(Schema::hasTable('product_barcodes'))->toBeFalse();
    } finally {
        Schema::rename('product_barcodes_away', 'product_barcodes');
    }
});
