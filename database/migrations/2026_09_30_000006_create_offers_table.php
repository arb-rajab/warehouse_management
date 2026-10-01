<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `offers` table, created by its own
     * `2024_04_09_151207_create_offers_table` (plus `offer_type` and
     * `brand_id`, from `2024_05_09_105736`) after the 2023 `shop.sql` dump the
     * store's other tables here reproduce, and never carried over: every
     * product card checks whether its product is in one.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('offers')) {
            return;
        }

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->integer('start_date')->nullable();
            $table->integer('end_date')->nullable();
            $table->boolean('is_active')->default(1);
            $table->boolean('multiple_usage')->default(0);
            $table->boolean('is_repeatable')->default(0);
            $table->string('banner')->nullable();
            $table->timestamps();
            $table->enum('offer_type', ['brand_offer', 'products_offer'])->default('products_offer');
            $table->integer('brand_id')->nullable();
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

        Schema::dropIfExists('offers');
    }
};
