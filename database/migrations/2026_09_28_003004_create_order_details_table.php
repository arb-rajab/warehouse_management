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
        Schema::create('order_details', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('order_id');
            $table->integer('seller_id')->nullable();
            $table->integer('product_id');
            $table->longText('variation')->nullable();
            $table->decimal('price', 20, 2)->nullable();
            $table->decimal('tax', 20, 2)->default('0.00');
            $table->decimal('shipping_cost', 20, 2)->default('0.00');
            $table->integer('quantity')->nullable();
            $table->string('payment_status', 10)->default('unpaid');
            $table->string('delivery_status', 20)->nullable()->default('pending');
            $table->string('shipping_type')->nullable();
            $table->integer('pickup_point_id')->nullable();
            $table->string('product_referral_code')->nullable();
            $table->decimal('earn_point', 25, 2)->default('0.00');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
