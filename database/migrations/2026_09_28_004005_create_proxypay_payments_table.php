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
        Schema::create('proxypay_payments', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('payment_type', 20);
            $table->string('reference_id', 20);
            $table->integer('order_id')->nullable();
            $table->integer('package_id')->nullable();
            $table->integer('user_id');
            $table->double('amount', 25, 2)->default('0.00');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxypay_payments');
    }
};
