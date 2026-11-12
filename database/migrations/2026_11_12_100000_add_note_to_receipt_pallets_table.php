<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A note per pallet entry of a receipt (for example the real product name
 * written on a pallet recorded under a stand-in product). Finalizing copies it
 * onto the pallet it creates (`pallets.note`). At most 255 characters, as
 * the request allows.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receipt_pallets', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('expiration_date');
        });
    }

    /**
     * Reverse the migrations. Lossy: the entries' notes are dropped.
     */
    public function down(): void
    {
        Schema::table('receipt_pallets', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
