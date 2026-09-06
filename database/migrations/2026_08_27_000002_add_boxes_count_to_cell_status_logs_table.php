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
            // Nullable by design: only pallet-quantity actions (store/open/removeBoxes/
            // transfer/empty) log a box count — cell-only actions like activate/deactivate
            // have none to record, so this stays null rather than a fabricated 0.
            $table->unsignedInteger('boxes_count')->nullable()->after('pallet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropColumn('boxes_count');
        });
    }
};
