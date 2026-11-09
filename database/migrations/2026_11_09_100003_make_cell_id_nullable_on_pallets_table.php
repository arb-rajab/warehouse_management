<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A pallet received from a truck can exist before it has a cell, so
 * `pallets.cell_id` becomes nullable. The unique index stays (MySQL and
 * SQLite both allow any number of NULLs under a unique index, so only placed
 * pallets compete for a cell) and so does the restricting foreign key.
 *
 * The foreign key is dropped around the change and re-created after it: on
 * MySQL a column used by a foreign key is not safe to redefine in place. The
 * unique index keeps serving the key meanwhile. SQLite rebuilds the table for
 * each step, which keeps its index and keys.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropForeign(['cell_id']);
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->unsignedBigInteger('cell_id')->nullable()->change();
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->foreign('cell_id')->references('id')->on('cells')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations. Restoring NOT NULL has no sentinel to put in
     * place of a missing cell, so it is refused while an unplaced pallet
     * exists: place or delete those pallets first.
     */
    public function down(): void
    {
        if (DB::table('pallets')->whereNull('cell_id')->exists()) {
            throw new RuntimeException('Cannot make pallets.cell_id NOT NULL while unplaced pallets exist; place or delete them first.');
        }

        Schema::table('pallets', function (Blueprint $table) {
            $table->dropForeign(['cell_id']);
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->unsignedBigInteger('cell_id')->nullable(false)->change();
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->foreign('cell_id')->references('id')->on('cells')->restrictOnDelete();
        });
    }
};
