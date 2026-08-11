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
        Schema::table('cell_status_logs', function (Blueprint $table) {
            // No FK constraint: `empty` hard-deletes the pallet, and an `onDelete`
            // rule (even `nullOnDelete`) would wipe this id from every earlier log
            // row for that pallet the moment it's emptied — defeating "view all
            // logs for this pallet" for the exact pallets people look up most.
            $table->unsignedBigInteger('pallet_id')->nullable()->after('related_cell_id');
            $table->index('pallet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropIndex(['pallet_id']);
            $table->dropColumn('pallet_id');
        });
    }
};
