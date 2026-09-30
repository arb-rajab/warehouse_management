<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `product_barcodes` is a *shared* table, owned by the store app --
     * `App\Models\StoreProductBarcode`'s own docblock already named this
     * table as "future DB-merge work" (see .ai/rules/legacy-albaraka-holland.md
     * section 2), which `Product::storeBarcodes()` now needs. Mirrors the
     * store's own `create_product_barcodes_table` migration: `product_id`
     * matches `products.id`'s signed `integer` type (see
     * `create_products_table`'s own docblock on why every product_id FK in
     * this app is signed rather than Laravel's default unsigned bigint).
     * `id` stays Laravel's default `bigint unsigned` autoincrement, matching
     * the store's own migration, since nothing references this table's own
     * id as a foreign key.
     *
     * The `hasTable()` guard is what makes this safe on the shared
     * database: in production this table already exists as the store's own,
     * so `up()` is a no-op there -- this app must never alter a table it
     * only reads.
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
