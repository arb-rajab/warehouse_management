<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the cell log record the admin's record corrections of a pallet (a bulk
 * note change and a product change), including for a received pallet that has
 * no cell yet:
 *
 * - `cell_id`, `from_state` and `to_state` become nullable. Only a correction
 *   of an unplaced pallet leaves them null; every shelf action still names
 *   its cell and states. The restricting foreign key stays (dropped around
 *   the change and re-created: MySQL does not redefine a column used by a
 *   foreign key in place; SQLite rebuilds the table and keeps it).
 * - `batch_id`: the UUID shared by the entries one bulk request wrote.
 * - `reason`: why the product was changed (required by that request).
 * - `old_product_id`: the product the pallet held before a product change
 *   (`product_id` is the new one). A signed integer with its own foreign
 *   key, like every product reference (see shared-database.md).
 * - `old_note` / `new_note`: the pallet's own note before and after.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropForeign(['cell_id']);
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cell_id')->nullable()->change();
            $table->string('from_state')->nullable()->change();
            $table->string('to_state')->nullable()->change();
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->foreign('cell_id')->references('id')->on('cells')->restrictOnDelete();

            $table->uuid('batch_id')->nullable()->after('note');
            $table->string('reason', 255)->nullable()->after('batch_id');
            $table->integer('old_product_id')->nullable();
            $table->foreign('old_product_id')->references('id')->on('products')->nullOnDelete();
            $table->string('old_note', 255)->nullable()->after('reason');
            $table->string('new_note', 255)->nullable()->after('old_note');

            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations. Restoring NOT NULL has nothing to put in place
     * of a missing cell or state, so it is refused while a correction of an
     * unplaced pallet is logged. Lossy otherwise: the correction details go.
     */
    public function down(): void
    {
        if (DB::table('cell_status_logs')->whereNull('cell_id')->orWhereNull('from_state')->orWhereNull('to_state')->exists()) {
            throw new RuntimeException('Cannot make cell_status_logs.cell_id and its states NOT NULL while logs without a cell exist; delete them first.');
        }

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropForeign(['old_product_id']);
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'reason', 'old_product_id', 'old_note', 'new_note']);
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropForeign(['cell_id']);
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cell_id')->nullable(false)->change();
            $table->string('from_state')->nullable(false)->change();
            $table->string('to_state')->nullable(false)->change();
        });

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->foreign('cell_id')->references('id')->on('cells')->restrictOnDelete();
        });
    }
};
