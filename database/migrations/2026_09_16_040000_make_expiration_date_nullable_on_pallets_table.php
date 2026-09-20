<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->date('expiration_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: a pallet with no expiration date (a supported state this
     * migration's `up()` introduced) is backfilled with this sentinel, since
     * restoring NOT NULL would otherwise fail outright on such a row and
     * there is no real expiration date to put back.
     */
    public function down(): void
    {
        DB::table('pallets')
            ->whereNull('expiration_date')
            ->update(['expiration_date' => '1970-01-01']);

        Schema::table('pallets', function (Blueprint $table) {
            $table->date('expiration_date')->nullable(false)->change();
        });
    }
};
