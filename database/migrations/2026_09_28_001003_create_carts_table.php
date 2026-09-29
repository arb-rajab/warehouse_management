<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `id` is `int UNSIGNED` in shop.sql (not `bigint`), so it uses
     * `unsignedInteger()->autoIncrement()` rather than `$table->id()` to keep
     * the exact 32-bit width.
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->integer('owner_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('temp_user_id')->nullable();
            $table->integer('address_id')->default(0);
            $table->integer('product_id')->nullable();
            $table->text('variation')->nullable();
            $table->decimal('price', 20, 2)->default(0.00);
            $table->decimal('tax', 20, 2)->default(0.00);
            $table->decimal('shipping_cost', 20, 2)->default(0.00);
            $table->string('shipping_type', 30)->default('');
            $table->integer('pickup_point')->nullable();
            $table->integer('carrier_id')->nullable();
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->string('product_referral_code')->nullable();
            $table->string('coupon_code')->nullable();
            $table->tinyInteger('coupon_applied')->default(0);
            $table->integer('quantity')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
