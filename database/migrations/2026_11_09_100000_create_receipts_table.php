<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A truck receipt received into one warehouse. The bill is not stored as
 * data: only its photos/PDFs (receipt_files) and the pallets the admin
 * records as written on it (receipt_pallets). `note` is the admin's only free
 * text. The truck itself is not recorded. See .ai/rules/receiving.md.
 *
 * The status column holds a `ReceiptStatus` value as a string, like the other
 * enum columns. `created_by`/`finalized_by` restrict deletion of the user:
 * users are soft-deleted (see models.md) and a receipt keeps its authors.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('created_by')->constrained('wms_users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('wms_users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index('warehouse_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('finalized_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
