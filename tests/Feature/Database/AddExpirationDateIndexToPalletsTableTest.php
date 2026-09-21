<?php

use Illuminate\Support\Facades\Schema;

function loadAddExpirationDateIndexToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_09_03_102634_add_expiration_date_index_to_pallets_table.php');
}

test('pallets.expiration_date has an index', function () {
    $indexes = collect(Schema::getIndexes('pallets'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['expiration_date']))->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddExpirationDateIndexToPalletsTableMigration();

    $migration->down();
    expect(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['expiration_date']))->toBeFalse();

    $migration->up();
    expect(collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['columns'] === ['expiration_date']))->toBeTrue();
});
