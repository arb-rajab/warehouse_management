<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `trucks` table, created by its own
     * `2024_11_26_125207_create_trucks_table` (with `capacity` renamed to
     * `max_pallets_number` by `2024_12_18_153936`) after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * delivery module's trucks.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('trucks')) {
            return;
        }

        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('max_pallets_number')->nullable();
            $table->text('description')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('photos')->nullable();
            $table->boolean('is_available')->default(true);
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

        Schema::dropIfExists('trucks');
    }
};
