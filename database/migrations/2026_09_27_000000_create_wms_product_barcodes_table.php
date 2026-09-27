<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Holds the barcodes an admin attaches to a product — WMS-owned data, the
     * same as `wms_product_settings`, since the store's `products` has no
     * barcode column of its own (see .ai/rules/shared-database.md). Unlike
     * that table, a product can have any number of barcodes, so this is a
     * proper child table with its own auto-incrementing `id` rather than
     * `product_id` as the primary key.
     *
     * `product_id` is a signed `integer` to match the store-owned
     * `products.id`, and cascades on delete because a barcode has no meaning
     * once its product is gone. No explicit index on `product_id`: the
     * foreign key constraint declared in the same `Schema::create()` call
     * already gets InnoDB's auto-created supporting index — see the
     * "Two tables are shared" section of .ai/rules/migrations.md.
     *
     * `barcode` is globally unique — it identifies a physical product for
     * scanning, so the same code must not resolve to two different products.
     */
    public function up(): void
    {
        Schema::create('wms_product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->string('barcode')->unique();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: dropping the table discards every attached barcode.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_product_barcodes');
    }
};
