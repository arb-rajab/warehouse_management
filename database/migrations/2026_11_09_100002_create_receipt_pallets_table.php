<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pallets of a receipt as written on the bill by the merchant (and
 * physically checked by the admin): one row per pallet with its product,
 * boxes and optional expiry, in the order the admin typed them (`position`).
 * The bill's own details are not stored; the admin compares the pallets with
 * the bill's photos/PDFs by eye. Finalizing the receipt creates one real
 * `pallets` row per entry and stores its id in `pallet_id`.
 *
 * `pallet_id` is `nullOnDelete`: a pallet can be removed later by the normal
 * pallet actions, and the receipt's record of what arrived must survive that.
 * Deleting a receipt removes its entries (a draft is deletable); once
 * finalized the pallets' `receipt_id` restricts that, see the pallets
 * migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            // Signed int to match the store-owned `products.id` — see
            // .ai/rules/shared-database.md.
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->unsignedInteger('boxes');
            $table->date('expiration_date')->nullable();
            $table->foreignId('pallet_id')->nullable()->constrained('pallets')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index('receipt_id');
            $table->index('product_id');
            $table->index('pallet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_pallets');
    }
};
