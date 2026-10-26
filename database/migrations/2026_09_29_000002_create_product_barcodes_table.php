<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `product_barcodes` is the inherited store table used by
     * Product::storeBarcodes() and integrated store product writers. It is
     * separate from the warehouse's wms_product_barcodes. Mirrors the
     * store's own `create_product_barcodes_table` migration: `product_id`
     * matches `products.id`'s signed `integer` type (see
     * `create_products_table`'s own docblock on why every product_id FK in
     * this app is signed rather than Laravel's default unsigned bigint).
     * `id` stays Laravel's default `bigint unsigned` autoincrement, matching
     * the store's own migration, since nothing references this table's own
     * id as a foreign key.
     *
     * The inherited hasTable() guard preserves an existing store table;
     * up() creates it only when absent. This schema guard does not prevent
     * integrated store writers from inserting or deleting barcode rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_barcodes')) {
            return;
        }

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->string('barcode');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

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

        Schema::dropIfExists('product_barcodes');
    }
};
