<?php

use Illuminate\Support\Facades\Schema;

function loadDropRedundantForeignKeyIndexesMigration(): object
{
    return require database_path('migrations/2026_09_21_000000_drop_redundant_foreign_key_indexes.php');
}

/**
 * @return array<int, array{table: string, columns: array<int, string>}>
 */
function redundantForeignKeyIndexColumns(): array
{
    return [
        ['table' => 'cell_status_logs', 'columns' => ['cell_id']],
        ['table' => 'cell_status_logs', 'columns' => ['related_cell_id']],
        ['table' => 'cell_status_logs', 'columns' => ['product_id']],
        ['table' => 'cell_status_logs', 'columns' => ['user_id']],
        ['table' => 'cell_verification_reports', 'columns' => ['cell_verification_round_id']],
        ['table' => 'cell_verification_reports', 'columns' => ['cell_id']],
        ['table' => 'cell_verification_reports', 'columns' => ['user_id']],
        ['table' => 'cell_verification_reports', 'columns' => ['expected_product_id']],
        ['table' => 'cell_verification_reports', 'columns' => ['reported_product_id']],
        ['table' => 'cell_verification_rounds', 'columns' => ['user_id']],
        ['table' => 'cell_status_log_flags', 'columns' => ['cell_status_log_id']],
        ['table' => 'cell_verification_round_row', 'columns' => ['row_id']],
        ['table' => 'pallets', 'columns' => ['product_id']],
    ];
}

function tableHasIndexOnColumns(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['columns'] === $columns);
}

test('the redundant explicit indexes are already dropped by the full migrate', function () {
    // RefreshDatabase has already run the whole migration stack, including
    // this one, by the time the test body runs — so the columns below should
    // already be bare rather than needing another up() call.
    foreach (redundantForeignKeyIndexColumns() as $case) {
        expect(tableHasIndexOnColumns($case['table'], $case['columns']))->toBeFalse(
            "expected no index on {$case['table']}.".implode(',', $case['columns'])
        );
    }
});

test('unrelated indexes on the same tables survive', function () {
    expect(tableHasIndexOnColumns('cell_status_logs', ['action']))->toBeTrue();
    expect(tableHasIndexOnColumns('cell_status_logs', ['created_at']))->toBeTrue();
    expect(tableHasIndexOnColumns('cell_verification_reports', ['is_correct']))->toBeTrue();
    expect(tableHasIndexOnColumns('cell_verification_rounds', ['completed_at']))->toBeTrue();
    expect(tableHasIndexOnColumns('cell_verification_round_row', ['cell_verification_round_id', 'row_id']))->toBeTrue();
});

test('sqlite has no mysql-only auto foreign-key index to touch', function () {
    // On sqlite there is no auto-created FK index for cells.row_id or
    // cell_verification_round_row.cell_verification_round_id to begin with —
    // the migration's mysql-only branch must not attempt to drop what was
    // never there, and the full migrate above already proves it doesn't.
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(Schema::hasTable('cells'))->toBeTrue();
    expect(Schema::hasTable('cell_verification_round_row'))->toBeTrue();
});

test('the migration is reversible', function () {
    $migration = loadDropRedundantForeignKeyIndexesMigration();

    $migration->down();
    foreach (redundantForeignKeyIndexColumns() as $case) {
        expect(tableHasIndexOnColumns($case['table'], $case['columns']))->toBeTrue(
            "expected an index on {$case['table']}.".implode(',', $case['columns']).' after rollback'
        );
    }

    $migration->up();
    foreach (redundantForeignKeyIndexColumns() as $case) {
        expect(tableHasIndexOnColumns($case['table'], $case['columns']))->toBeFalse();
    }
});
