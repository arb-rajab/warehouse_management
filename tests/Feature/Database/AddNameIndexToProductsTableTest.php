<?php

use Illuminate\Support\Facades\Schema;

function loadAddNameIndexToProductsTableMigration(): object
{
    return require database_path('migrations/2026_09_21_000001_add_name_index_to_products_table.php');
}

test('products.name has an index', function () {
    $indexes = collect(Schema::getIndexes('products'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['name']))->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddNameIndexToProductsTableMigration();

    $migration->down();
    expect(collect(Schema::getIndexes('products'))->contains(fn (array $index) => $index['columns'] === ['name']))->toBeFalse();

    $migration->up();
    expect(collect(Schema::getIndexes('products'))->contains(fn (array $index) => $index['columns'] === ['name']))->toBeTrue();
});
