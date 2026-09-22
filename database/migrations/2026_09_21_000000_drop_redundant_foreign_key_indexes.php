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
     * constraint is added, and every explicit index() call below was
     * originally assumed to be a guaranteed second, redundant B-tree next to
     * that auto-created one -- true only if the auto-created index actually
     * exists and is separately named.
     *
     * Two dev deploys proved that assumption wrong twice over, on two
     * structurally different columns: first `cell_status_logs_cell_id_index`
     * (explicit index declared in the same Schema::create() blueprint as its
     * FK), then `pallets_product_id_index` (explicit index added by a wholly
     * separate, later migration -- previously assumed unconditionally safe
     * precisely because of that separation). Both failed with "needed in a
     * foreign key constraint", meaning on this database's actual history
     * neither ever got a second, separately-named auto-index at all -- the
     * explicit index was the *only* one MySQL had, whichever migration added
     * it. Column/migration structure is not a reliable enough signal for
     * what a given live database actually did.
     *
     * dropIndexIfRedundant() checks the live schema instead and only drops an
     * explicit index when another index still covers the column afterwards --
     * safe regardless of exactly how a given database's indexes came to
     * exist, with no exceptions carved out on the theory that some column's
     * history "must" be different.
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

        $this->dropIndexIfRedundant('pallets', 'product_id', 'pallets_product_id_index');
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
};
