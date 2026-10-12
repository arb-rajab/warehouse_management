<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Skipped when the table exists: the store's own database already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('carrier_ranges')) {
            return;
        }

        Schema::create('carrier_ranges', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('carrier_id');
            $table->string('billing_type', 20);
            $table->decimal('delimiter1', 25, 2);
            $table->decimal('delimiter2', 25, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('carrier_ranges');
    }
};
