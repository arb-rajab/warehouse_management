<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `representative_packages` table, created by its own
     * `2024_02_19_123602_create_representative_packages_table` after the 2023
     * `shop.sql` dump the store's other tables here reproduce, and never
     * carried over: a sales representative's discount package.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('representative_packages')) {
            return;
        }

        Schema::create('representative_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('percentage');
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

        Schema::dropIfExists('representative_packages');
    }
};
