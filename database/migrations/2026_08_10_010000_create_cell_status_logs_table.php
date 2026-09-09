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
        Schema::create('cell_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_id')->constrained('cells')->restrictOnDelete();
            $table->foreignId('related_cell_id')->nullable()->constrained('cells')->restrictOnDelete();
            $table->string('action');
            $table->string('from_state');
            $table->string('to_state');
            // Signed int, not foreignId()'s bigint unsigned: `products` is the
            // store app's table and its `id` is int(11) signed. See
            // .ai/rules/shared-database.md.
            $table->integer('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreignId('user_id')->constrained('wms_users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('cell_id');
            $table->index('related_cell_id');
            $table->index('product_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_status_logs');
    }
};
