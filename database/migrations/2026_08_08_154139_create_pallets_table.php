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
        Schema::create('pallets', function (Blueprint $table) {
            $table->id();
            // Signed int, not foreignId()'s bigint unsigned: `products` is the
            // store app's table and its `id` is int(11) signed, so a bigint
            // unsigned column cannot carry a real FK to it. See
            // .ai/rules/shared-database.md.
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreignId('cell_id')->unique()->constrained('cells')->restrictOnDelete();
            $table->date('expiration_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pallets');
    }
};
