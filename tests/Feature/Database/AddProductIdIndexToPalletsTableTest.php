<?php

use Illuminate\Support\Facades\Schema;

function loadAddProductIdIndexToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_09_06_120000_add_product_id_index_to_pallets_table.php');
}

function palletsHasProductIdIndex(): bool
{
    return collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['product_id']);
}

test('the migration adds an index on pallets.product_id', function () {
    // Whether the later drop_redundant_foreign_key_indexes migration leaves
    // this index in place or drops it depends on whether something else on
    // the connection also covers the column (see
    // DropRedundantForeignKeyIndexesTest) — roll this migration back first so
    // this test proves what up() itself does, regardless of that later
    // migration's live-database decision.
    $migration = loadAddProductIdIndexToPalletsTableMigration();
    $migration->down();

    $migration->up();

    expect(palletsHasProductIdIndex())->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddProductIdIndexToPalletsTableMigration();
    $migration->down();

    $migration->up();
    expect(palletsHasProductIdIndex())->toBeTrue();

    $migration->down();
    expect(palletsHasProductIdIndex())->toBeFalse();
});
