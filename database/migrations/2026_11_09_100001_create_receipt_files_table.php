<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The photos/PDFs of a receipt's bill. Like the product reference image
 * (`products.warehouse_image_path`), the file lives on a Laravel filesystem
 * disk and the row keeps its relative `path`; the store's `uploads` table is
 * not used (it belongs to the integrated store's upload flows). A receipt
 * needs one or more files before it can be finalized; that is enforced by the
 * service and the request, not here.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('receipt_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_files');
    }
};
