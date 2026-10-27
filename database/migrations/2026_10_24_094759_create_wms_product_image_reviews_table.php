<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wms_product_image_reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('wms_users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('image_source')->default('');
            $table->text('image_url')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_product_image_reviews');
    }
};
