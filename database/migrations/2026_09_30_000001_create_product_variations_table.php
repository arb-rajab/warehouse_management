<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `product_variations` table, created by its own
     * `2023_10_30_120829_create_table_product_variation` (plus
     * `product_serial`, added by `2023_12_13_170823`) after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * product page checks it for every product it shows.
     *
     * `product_id` is a signed `integer`, as every product FK here is (see
     * .ai/rules/shared-database.md).
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_variations')) {
            return;
        }

        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->string('name');
            $table->string('product_serial')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
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

        Schema::dropIfExists('product_variations');
    }
};
