<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A note on the pallet itself, distinct from the action notes the cell log
 * keeps: set from its receipt entry at finalize or by the bulk note action,
 * kept by every pallet action, and cleared by a product change unless asked
 * to keep it. At most 255 characters.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('remaining_boxes');
        });
    }

    /**
     * Reverse the migrations. Lossy: the pallets' notes are dropped.
     */
    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
