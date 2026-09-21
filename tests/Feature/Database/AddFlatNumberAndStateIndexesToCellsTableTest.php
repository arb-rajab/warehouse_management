<?php

use Illuminate\Support\Facades\Schema;

function loadAddFlatNumberAndStateIndexesToCellsTableMigration(): object
{
    return require database_path('migrations/2026_09_03_102635_add_flat_number_and_state_indexes_to_cells_table.php');
}

test('cells has indexes on flat_number and state', function () {
    $indexes = collect(Schema::getIndexes('cells'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['flat_number']))->toBeTrue();
    expect($indexes->contains(fn (array $index) => $index['columns'] === ['state']))->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadAddFlatNumberAndStateIndexesToCellsTableMigration();

    $migration->down();
    $indexes = collect(Schema::getIndexes('cells'));
    expect($indexes->contains(fn (array $index) => $index['columns'] === ['flat_number']))->toBeFalse();
    expect($indexes->contains(fn (array $index) => $index['columns'] === ['state']))->toBeFalse();

    $migration->up();
    $indexes = collect(Schema::getIndexes('cells'));
    expect($indexes->contains(fn (array $index) => $index['columns'] === ['flat_number']))->toBeTrue();
    expect($indexes->contains(fn (array $index) => $index['columns'] === ['state']))->toBeTrue();
});
