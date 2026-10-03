<?php

use Illuminate\Support\Facades\Schema;

function loadAddUserIdCreatedAtIndexToCellStatusLogsTableMigration(): object
{
    return require database_path('migrations/2026_10_03_000002_add_user_id_created_at_index_to_cell_status_logs_table.php');
}

/**
 * @param  list<string>  $columns
 */
function cellStatusLogsHasIndexOn(array $columns): bool
{
    return collect(Schema::getIndexes('cell_status_logs'))->contains(fn (array $index) => $index['columns'] === $columns);
}

test('cell_status_logs has a composite (user_id, created_at) index that replaces the single-column user_id one', function () {
    expect(cellStatusLogsHasIndexOn(['user_id', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('cell_status_logs', 'cell_status_logs_user_id_index'))->toBeFalse()
        // Unrelated indexes on the same table are untouched.
        ->and(cellStatusLogsHasIndexOn(['created_at']))->toBeTrue()
        ->and(cellStatusLogsHasIndexOn(['cell_id']))->toBeTrue();
});

test('user_id stays covered by an index at every step of a down()/up() cycle', function () {
    $migration = loadAddUserIdCreatedAtIndexToCellStatusLogsTableMigration();

    $migration->down();
    expect(cellStatusLogsHasIndexOn(['user_id', 'created_at']))->toBeFalse()
        ->and(Schema::hasIndex('cell_status_logs', 'cell_status_logs_user_id_index'))->toBeTrue();

    $migration->up();
    expect(cellStatusLogsHasIndexOn(['user_id', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('cell_status_logs', 'cell_status_logs_user_id_index'))->toBeFalse();
});

test('up() does not fail when the single-column user_id index is already gone', function () {
    $migration = loadAddUserIdCreatedAtIndexToCellStatusLogsTableMigration();
    $migration->down();
    Schema::table('cell_status_logs', fn ($table) => $table->dropIndex('cell_status_logs_user_id_index'));

    $migration->up();

    expect(cellStatusLogsHasIndexOn(['user_id', 'created_at']))->toBeTrue();
});
