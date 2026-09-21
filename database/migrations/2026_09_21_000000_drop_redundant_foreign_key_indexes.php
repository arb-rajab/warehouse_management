<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL-only in effect. InnoDB auto-creates a supporting index for a
     * foreign key whenever no suitable index already exists at the time the
     * constraint is added.
     *
     * `pallets.product_id`'s explicit index was added by a later, separate
     * migration (2026_09_06_120000) rather than alongside its FK in the same
     * blueprint, so on MySQL the FK's own auto-created index and that later
     * explicit one are unambiguously two distinct B-trees -- dropping the
     * explicit one there is always safe, on every driver.
     *
     * Every other column below has its explicit `index()` call declared in
     * the *same* `Schema::create()` blueprint as its `foreignId()->constrained()`
     * (or `foreign()`) call. A 2026-09-21 dev deploy proved that combination
     * does not reliably leave two separate indexes behind on every MySQL
     * version/history the way the pallets case does -- it failed dropping
     * `cell_status_logs_cell_id_index` with "needed in a foreign key
     * constraint", meaning that explicit index, not a same-named `_foreign`
     * one, was the *only* index MySQL had on that column. Rather than assume
     * a fixed creation order that turned out to be wrong, dropIndexIfRedundant()
     * checks the live schema and only drops an explicit index when another
     * index still covers the column afterwards -- safe regardless of exactly
     * how a given database's indexes came to exist.
     *
     * sqlite does not auto-index foreign keys, so on the test connection none
     * of these columns ever has a second, genuinely redundant index to find --
     * dropIndexIfRedundant() is a no-op there for all of them, which is
     * correct (nothing to clean up) rather than a driver-specific carve-out.
     */
    public function up(): void
    {
        $this->dropIndexIfRedundant('cell_status_logs', 'cell_id', 'cell_status_logs_cell_id_index');
        $this->dropIndexIfRedundant('cell_status_logs', 'related_cell_id', 'cell_status_logs_related_cell_id_index');
        $this->dropIndexIfRedundant('cell_status_logs', 'product_id', 'cell_status_logs_product_id_index');
        $this->dropIndexIfRedundant('cell_status_logs', 'user_id', 'cell_status_logs_user_id_index');

        $this->dropIndexIfRedundant('cell_verification_reports', 'cell_verification_round_id', 'cell_verification_reports_cell_verification_round_id_index');
        $this->dropIndexIfRedundant('cell_verification_reports', 'cell_id', 'cell_verification_reports_cell_id_index');
        $this->dropIndexIfRedundant('cell_verification_reports', 'user_id', 'cell_verification_reports_user_id_index');
        $this->dropIndexIfRedundant('cell_verification_reports', 'expected_product_id', 'cell_verification_reports_expected_product_id_index');
        $this->dropIndexIfRedundant('cell_verification_reports', 'reported_product_id', 'cell_verification_reports_reported_product_id_index');

        $this->dropIndexIfRedundant('cell_verification_rounds', 'user_id', 'cell_verification_rounds_user_id_index');

        $this->dropIndexIfRedundant('cell_status_log_flags', 'cell_status_log_id', 'cell_status_log_flags_cell_status_log_id_index');

        $this->dropIndexIfRedundant('cell_verification_round_row', 'row_id', 'cell_verification_round_row_row_id_index');
        $this->dropIndexIfRedundant('cell_verification_round_row', 'cell_verification_round_id', 'cell_verification_round_row_cell_verification_round_id_foreign');

        $this->dropIndexIfRedundant('cells', 'row_id', 'cells_row_id_foreign');

        $this->dropIndexIfExists('pallets', 'pallets_product_id_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->addIndexIfMissing('pallets', 'product_id', 'pallets_product_id_index');

        $this->addIndexIfMissing('cells', 'row_id', 'cells_row_id_foreign');
        $this->addIndexIfMissing('cell_verification_round_row', 'cell_verification_round_id', 'cell_verification_round_row_cell_verification_round_id_foreign');
        $this->addIndexIfMissing('cell_verification_round_row', 'row_id', 'cell_verification_round_row_row_id_index');

        $this->addIndexIfMissing('cell_status_log_flags', 'cell_status_log_id', 'cell_status_log_flags_cell_status_log_id_index');

        $this->addIndexIfMissing('cell_verification_rounds', 'user_id', 'cell_verification_rounds_user_id_index');

        $this->addIndexIfMissing('cell_verification_reports', 'cell_verification_round_id', 'cell_verification_reports_cell_verification_round_id_index');
        $this->addIndexIfMissing('cell_verification_reports', 'cell_id', 'cell_verification_reports_cell_id_index');
        $this->addIndexIfMissing('cell_verification_reports', 'user_id', 'cell_verification_reports_user_id_index');
        $this->addIndexIfMissing('cell_verification_reports', 'expected_product_id', 'cell_verification_reports_expected_product_id_index');
        $this->addIndexIfMissing('cell_verification_reports', 'reported_product_id', 'cell_verification_reports_reported_product_id_index');

        $this->addIndexIfMissing('cell_status_logs', 'cell_id', 'cell_status_logs_cell_id_index');
        $this->addIndexIfMissing('cell_status_logs', 'related_cell_id', 'cell_status_logs_related_cell_id_index');
        $this->addIndexIfMissing('cell_status_logs', 'product_id', 'cell_status_logs_product_id_index');
        $this->addIndexIfMissing('cell_status_logs', 'user_id', 'cell_status_logs_user_id_index');
    }

    /**
     * Drop $indexName only when the live schema currently has another index
     * that also covers $column as its first column -- i.e. only when
     * $indexName is verifiably redundant right now, not merely assumed to be.
     * A no-op when $indexName doesn't exist at all (e.g. sqlite, which never
     * auto-creates a foreign key's supporting index) or when it's the only
     * index covering the column (dropping it would break the FK requirement).
     */
    private function dropIndexIfRedundant(string $table, string $column, string $indexName): void
    {
        $indexes = Schema::getIndexes($table);

        if (! collect($indexes)->contains('name', $indexName)) {
            return;
        }

        $hasAnotherCoveringIndex = collect($indexes)
            ->where('name', '!=', $indexName)
            ->contains(fn (array $index) => ($index['columns'][0] ?? null) === $column);

        if (! $hasAnotherCoveringIndex) {
            return;
        }

        Schema::table($table, fn (Blueprint $table) => $table->dropIndex($indexName));
    }

    /**
     * The down()-side mirror of dropIndexIfRedundant() -- only add $indexName
     * back when it isn't already there, since up() may have left it in place.
     */
    private function addIndexIfMissing(string $table, string $column, string $indexName): void
    {
        if (Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, fn (Blueprint $table) => $table->index($column, $indexName));
    }

    /**
     * Drop $indexName if it exists, and do nothing (rather than error) if it
     * doesn't -- used for pallets.product_id, whose redundancy doesn't need
     * dropIndexIfRedundant()'s live check (see the docblock above), but which
     * still needs to tolerate up() running again after it already dropped it.
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, fn (Blueprint $table) => $table->dropIndex($indexName));
    }
};
