<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pallets the admin actually received for a line, recorded while the
 * receipt is a draft. Their product is the line's product. Finalizing the
 * receipt creates one real `pallets` row per entry and stores its id in
 * `pallet_id`.
 *
 * `pallet_id` is `nullOnDelete`: a pallet can be removed later by the normal
 * pallet actions, and the receipt's record of what arrived must survive that.
 * A finalized receipt's null `pallet_id` therefore means "that pallet is gone".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_line_pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_line_id')->constrained('receipt_lines')->cascadeOnDelete();
            $table->unsignedInteger('boxes');
            $table->date('expiration_date')->nullable();
            $table->foreignId('pallet_id')->nullable()->constrained('pallets')->nullOnDelete();
            $table->timestamps();

            $table->index('receipt_line_id');
            $table->index('pallet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_line_pallets');
    }
};
