<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Superseded by cell_status_logs, which logs both sides of a transfer
     * (and every other cell status change) in one table.
     */
    public function up(): void
    {
        Schema::dropIfExists('pallet_transfers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('pallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->cascadeOnDelete();
            $table->foreignId('from_cell_id')->constrained('cells')->restrictOnDelete();
            $table->foreignId('to_cell_id')->constrained('cells')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('pallet_id');
            $table->index('from_cell_id');
            $table->index('to_cell_id');
            $table->index('user_id');
        });
    }
};
