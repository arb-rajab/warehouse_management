<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `offer_products` table, created by its own
     * `2024_04_09_151800_create_offer_products_table` after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * products an offer is made of, read by every product card.
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
        if (Schema::hasTable('offer_products')) {
            return;
        }

        Schema::create('offer_products', function (Blueprint $table) {
            $table->id();
            $table->integer('offer_id');
            $table->integer('product_id');
            $table->boolean('is_base_product');
            $table->integer('quantity')->default(0);
            $table->decimal('discount', 20, 2)->default(0);
            $table->string('discount_type')->nullable();
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

        Schema::dropIfExists('offer_products');
    }
};
