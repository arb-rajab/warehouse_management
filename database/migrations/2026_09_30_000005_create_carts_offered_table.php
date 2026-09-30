<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `carts_offered` table, created by its own
     * `2024_04_09_145757_create_carts_offered_table` after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * cart page reads the offers a customer has in it.
     *
     * `deleted_at` is in no legacy migration, but CartOffered has soft-deleted
     * since the legacy repo's first commit and the cart page filters on it for
     * every signed-in customer, so production has it.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('carts_offered')) {
            return;
        }

        Schema::create('carts_offered', function (Blueprint $table) {
            $table->id();
            $table->integer('owner_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('address_id');
            $table->integer('product_id')->nullable();
            $table->string('product_serial')->nullable();
            $table->text('variation')->nullable();
            $table->longText('variation_qty')->nullable();
            $table->decimal('price', 20, 2)->nullable();
            $table->decimal('tax', 20, 2)->nullable();
            $table->decimal('shipping_cost', 20, 2)->default(0);
            $table->string('shipping_type')->default('home_delivery');
            $table->integer('quantity')->default(0);
            $table->boolean('by_rep')->default(0);
            $table->integer('for_customer')->nullable();
            $table->string('item_notes')->nullable();
            $table->integer('offer_id')->nullable();
            $table->boolean('is_postponed')->default(0);
            $table->timestamps();
            $table->softDeletes();
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

        Schema::dropIfExists('carts_offered');
    }
};
