<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A group of pallets of one product on a receipt: a line of the bill
 * (`is_extra` false, with its expected count, boxes and expiry) or a group
 * that arrived but is not on the bill (`is_extra` true, expected_* null).
 * `expected_boxes` is boxes per pallet. The status holds a
 * `ReceiptLineStatus` value.
 *
 * Deleting a receipt removes its lines (a draft is deletable); once finalized
 * the pallets' `receipt_line_id` restricts that, see the pallets migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            // Signed int to match the store-owned `products.id` — see
            // .ai/rules/shared-database.md.
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->boolean('is_extra')->default(false);
            $table->unsignedInteger('expected_pallets_count')->nullable();
            $table->unsignedInteger('expected_boxes')->nullable();
            $table->date('expected_expiration_date')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('receipt_id');
            $table->index('product_id');
            $table->index('is_extra');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_lines');
    }
};
