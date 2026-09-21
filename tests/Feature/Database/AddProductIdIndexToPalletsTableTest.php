<?php

use Illuminate\Support\Facades\Schema;

function loadAddProductIdIndexToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_09_06_120000_add_product_id_index_to_pallets_table.php');
}

test('the migration adds an index on pallets.product_id', function () {
    // The later drop_redundant_foreign_key_indexes migration removes this
    // index from the full stack as redundant with MySQL's auto-created FK
    // index (see DropRedundantForeignKeyIndexesTest), so the schema here
    // already starts bare — run this migration's up() in isolation to prove
    // it still adds a working index on its own.
    loadAddProductIdIndexToPalletsTableMigration()->up();

    $indexes = collect(Schema::getIndexes('pallets'));
    expect($indexes->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddProductIdIndexToPalletsTableMigration();

    $migration->up();
    expect(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeTrue();

    $migration->down();
    expect(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeFalse();
});
