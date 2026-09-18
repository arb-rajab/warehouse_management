<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cell_verification_round_row', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_verification_round_id')->constrained('cell_verification_rounds')->cascadeOnDelete();
            $table->foreignId('row_id')->constrained('rows')->cascadeOnDelete();

            // One claim per row per round: the same row can never be listed
            // twice on one round, which keeps the "is this row under an active
            // round" lookup a plain exists() rather than a distinct count.
            //
            // Named explicitly because the Blueprint would derive
            // `cell_verification_round_row_cell_verification_round_id_row_id_unique`
            // from the table and both columns -- 68 characters, which MySQL
            // rejects outright at its 64-character limit (errno 1059). Sqlite has
            // no such limit, so the generated name only fails off the test
            // connection. tests/Feature/Database/SchemaIdentifierLengthTest.php
            // pins the limit so the next one is caught before a deploy.
            $table->unique(
                ['cell_verification_round_id', 'row_id'],
                'cell_verification_round_row_round_id_row_id_unique',
            );
            // Reads go the other way too — PalletActionService asks "is this
            // row claimed by any unfinished round" on every pallet action.
            $table->index('row_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_verification_round_row');
    }
};
