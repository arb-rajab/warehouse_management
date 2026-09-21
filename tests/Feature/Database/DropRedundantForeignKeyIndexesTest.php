<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadDropRedundantForeignKeyIndexesMigration(): object
{
    return require database_path('migrations/2026_09_21_000000_drop_redundant_foreign_key_indexes.php');
}

/**
 * Every column this migration targets. Two separate dev deploys proved that
 * whether a column's explicit index() call sits in the same Schema::create()
 * blueprint as its FK (e.g. cell_status_logs.cell_id) or was added later by a
 * wholly separate migration (pallets.product_id) is NOT a reliable signal for
 * whether a live database actually has a second, genuinely redundant index —
 * both shapes have failed with "needed in a foreign key constraint" when the
 * explicit index turned out to be the *only* one. So every column here is
 * handled identically by dropIndexIfRedundant(), with no exceptions.
 *
 * @return array<int, array{table: string, column: string, index: string}>
 */
function foreignKeyIndexColumns(): array
{
    return [
        ['table' => 'cell_status_logs', 'column' => 'cell_id', 'index' => 'cell_status_logs_cell_id_index'],
        ['table' => 'cell_status_logs', 'column' => 'related_cell_id', 'index' => 'cell_status_logs_related_cell_id_index'],
        ['table' => 'cell_status_logs', 'column' => 'product_id', 'index' => 'cell_status_logs_product_id_index'],
        ['table' => 'cell_status_logs', 'column' => 'user_id', 'index' => 'cell_status_logs_user_id_index'],
        ['table' => 'cell_verification_reports', 'column' => 'cell_verification_round_id', 'index' => 'cell_verification_reports_cell_verification_round_id_index'],
        ['table' => 'cell_verification_reports', 'column' => 'cell_id', 'index' => 'cell_verification_reports_cell_id_index'],
        ['table' => 'cell_verification_reports', 'column' => 'user_id', 'index' => 'cell_verification_reports_user_id_index'],
        ['table' => 'cell_verification_reports', 'column' => 'expected_product_id', 'index' => 'cell_verification_reports_expected_product_id_index'],
        ['table' => 'cell_verification_reports', 'column' => 'reported_product_id', 'index' => 'cell_verification_reports_reported_product_id_index'],
        ['table' => 'cell_verification_rounds', 'column' => 'user_id', 'index' => 'cell_verification_rounds_user_id_index'],
        ['table' => 'cell_status_log_flags', 'column' => 'cell_status_log_id', 'index' => 'cell_status_log_flags_cell_status_log_id_index'],
        ['table' => 'cell_verification_round_row', 'column' => 'row_id', 'index' => 'cell_verification_round_row_row_id_index'],
        ['table' => 'pallets', 'column' => 'product_id', 'index' => 'pallets_product_id_index'],
    ];
}

function tableHasIndexOnColumns(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['columns'] === $columns);
}

function tableHasIndexNamed(string $table, string $name): bool
{
    return collect(Schema::getIndexes($table))->contains('name', $name);
}

test('every explicit index survives the full migrate, since nothing else covers those columns on sqlite', function () {
    // This is the exact shape of both bugs two dev deploys hit on MySQL:
    // cell_status_logs_cell_id_index, then pallets_product_id_index, were
    // each the ONLY index on their column, so dropping them broke the FK
    // constraint they were backing. On sqlite every column here is in the
    // same position (no auto-created FK index ever exists to make the
    // explicit one a true duplicate), so the migration must leave every one
    // of them alone.
    foreach (foreignKeyIndexColumns() as $case) {
        expect(tableHasIndexNamed($case['table'], $case['index']))->toBeTrue(
            "expected {$case['index']} to survive on {$case['table']}"
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

test('sqlite has no mysql-only auto foreign-key index to touch, and the composite uniques that would cover those FKs on any driver are untouched', function () {
    // On sqlite there is no auto-created FK index for cells.row_id or
    // cell_verification_round_row.cell_verification_round_id to begin with —
    // dropIndexIfRedundant() must not error trying to drop a
    // never-existing index, and the composite unique index that covers each
    // column as its leftmost member (which is what would let MySQL safely
    // drop a genuine auto-index of its own) must be left alone either way.
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(tableHasIndexNamed('cells', 'cells_row_id_foreign'))->toBeFalse();
    expect(tableHasIndexNamed('cell_verification_round_row', 'cell_verification_round_row_cell_verification_round_id_foreign'))->toBeFalse();
    expect(tableHasIndexOnColumns('cells', ['row_id', 'cell_number', 'flat_number']))->toBeTrue();
    expect(tableHasIndexOnColumns('cell_verification_round_row', ['cell_verification_round_id', 'row_id']))->toBeTrue();
});

test('drops an explicit index only once another index actually covers the column, proving the redundancy check works, not just that it is cautious', function () {
    // Simulates what a real duplicate looks like on MySQL: a second index on
    // the same column under a different name (standing in for the FK's own
    // auto-created index). Without this, "the migration never breaks
    // anything" would be trivially true by never dropping anything at all.
    // Covers both column shapes (same-blueprint and separately-migrated),
    // since both have turned out to need the live check in practice.
    Schema::table('cell_status_logs', fn (Blueprint $table) => $table->index('cell_id', 'cell_status_logs_cell_id_foreign'));
    Schema::table('pallets', fn (Blueprint $table) => $table->index('product_id', 'pallets_product_id_foreign'));

    loadDropRedundantForeignKeyIndexesMigration()->up();

    expect(tableHasIndexNamed('cell_status_logs', 'cell_status_logs_cell_id_index'))->toBeFalse();
    expect(tableHasIndexNamed('cell_status_logs', 'cell_status_logs_cell_id_foreign'))->toBeTrue();
    expect(tableHasIndexNamed('pallets', 'pallets_product_id_index'))->toBeFalse();
    expect(tableHasIndexNamed('pallets', 'pallets_product_id_foreign'))->toBeTrue();
});

test('every index is untouched, and left untouched again, by a down()/up() cycle when nothing was ever dropped', function () {
    $migration = loadDropRedundantForeignKeyIndexesMigration();

    $migration->down();
    $migration->up();

    foreach (foreignKeyIndexColumns() as $case) {
        expect(tableHasIndexNamed($case['table'], $case['index']))->toBeTrue(
            "expected {$case['index']} to still be there on {$case['table']}"
        );
    }
});
