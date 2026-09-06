<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cell_verification_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_verification_round_id')->constrained('cell_verification_rounds')->restrictOnDelete();
            $table->foreignId('cell_id')->constrained('cells')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('wms_users')->restrictOnDelete();
            $table->boolean('is_correct');

            $table->string('expected_cell_state');
            $table->foreignId('expected_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('expected_boxes_count')->nullable();
            $table->date('expected_expiration_date')->nullable();

            $table->string('reported_cell_state')->nullable();
            $table->foreignId('reported_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('reported_boxes_count')->nullable();
            $table->date('reported_expiration_date')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('cell_verification_round_id');
            $table->index('cell_id');
            $table->index('user_id');
            $table->index('is_correct');
            $table->index('created_at');
            $table->index('expected_product_id');
            $table->index('reported_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_verification_reports');
    }
};
