<?php

use Illuminate\Support\Facades\Schema;

function loadAddProductIdIndexToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_09_06_120000_add_product_id_index_to_pallets_table.php');
}

test('pallets.product_id has an index', function () {
    $indexes = collect(Schema::getIndexes('pallets'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddProductIdIndexToPalletsTableMigration();

    $migration->down();
    expect(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeFalse();

    $migration->up();
    expect(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeTrue();
});
