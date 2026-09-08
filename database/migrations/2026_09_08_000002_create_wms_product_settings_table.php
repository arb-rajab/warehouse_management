<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Holds the per-product operational data this app needs but the store app
     * has no column for. `boxes_count` — how many boxes a full pallet of the
     * product holds — used to be added to the shared `products` table by an
     * ALTER guarded off in production, which meant the column simply did not
     * exist there while PalletActionService read it. Keeping it in a
     * WMS-owned table removes the guard, and with it the divergence between
     * environments.
     *
     * `product_id` is the primary key: at most one settings row per product,
     * with no surrogate id to keep in sync. It is a signed `integer` to match
     * the store-owned `products.id`, and cascades on delete because the row is
     * derived data with no meaning once its product is gone. See
     * .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        Schema::create('wms_product_settings', function (Blueprint $table) {
            $table->integer('product_id')->primary();
            $table->unsignedInteger('boxes_count')->default(1);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: dropping the table discards every configured box count.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_product_settings');
    }
};
