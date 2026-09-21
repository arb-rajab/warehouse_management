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
     * foreign key whenever no suitable index already exists, and
     * `foreignId()->constrained()` registers its `foreign` command at
     * column-definition time — before the explicit `index()` calls each of
     * these tables makes at the bottom of its closure. So on MySQL the FK's
     * auto-created index is created first, and the explicit index that
     * follows is a second, identical B-tree on the same column(s) — pure
     * write overhead with zero read benefit, since the optimiser will never
     * choose a duplicate over the FK index.
     *
     * sqlite does not auto-index foreign keys, so on the test connection
     * these explicit indexes are the *only* index on their columns; dropping
     * them there trades a bit of local/query-planning benefit (irrelevant at
     * sqlite's scale here) for keeping this migration identical, and safe, on
     * both drivers.
     */
    public function up(): void
    {
        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropIndex(['cell_id']);
            $table->dropIndex(['related_cell_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('cell_verification_reports', function (Blueprint $table) {
            $table->dropIndex(['cell_verification_round_id']);
            $table->dropIndex(['cell_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['expected_product_id']);
            $table->dropIndex(['reported_product_id']);
        });

        Schema::table('cell_verification_rounds', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('cell_status_log_flags', function (Blueprint $table) {
            $table->dropIndex(['cell_status_log_id']);
        });

        Schema::table('cell_verification_round_row', function (Blueprint $table) {
            // row_id's own explicit index, redundant with MySQL's
            // auto-created row_id_foreign index.
            $table->dropIndex(['row_id']);
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // These two columns have no explicit index() call at all — the
            // redundancy here is entirely between MySQL's own auto-created FK
            // index and a composite unique that already covers the column as
            // its leftmost member, so nothing exists to drop on sqlite.
            Schema::table('cells', function (Blueprint $table) {
                $table->dropIndex('cells_row_id_foreign');
            });

            Schema::table('cell_verification_round_row', function (Blueprint $table) {
                $table->dropIndex('cell_verification_round_row_cell_verification_round_id_foreign');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('cell_verification_round_row', function (Blueprint $table) {
                $table->index('cell_verification_round_id', 'cell_verification_round_row_cell_verification_round_id_foreign');
            });

            Schema::table('cells', function (Blueprint $table) {
                $table->index('row_id', 'cells_row_id_foreign');
            });
        }

        Schema::table('pallets', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('cell_verification_round_row', function (Blueprint $table) {
            $table->index('row_id');
        });

        Schema::table('cell_status_log_flags', function (Blueprint $table) {
            $table->index('cell_status_log_id');
        });

        Schema::table('cell_verification_rounds', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('cell_verification_reports', function (Blueprint $table) {
            $table->index('cell_verification_round_id');
            $table->index('cell_id');
            $table->index('user_id');
            $table->index('expected_product_id');
            $table->index('reported_product_id');
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->index('cell_id');
            $table->index('related_cell_id');
            $table->index('product_id');
            $table->index('user_id');
        });
    }
};
