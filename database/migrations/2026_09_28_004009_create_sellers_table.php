<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Skipped when the table exists: the store's own database already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('sellers')) {
            return;
        }

        Schema::create('sellers', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id')->unique();
            $table->decimal('rating', 3, 2)->default('0.00');
            $table->integer('num_of_reviews')->default('0');
            $table->integer('num_of_sale')->default('0');
            $table->integer('verification_status')->default('0');
            $table->longText('verification_info')->nullable();
            $table->integer('cash_on_delivery_status')->default('0');
            $table->decimal('admin_to_pay', 20, 2)->default('0.00');
            $table->string('bank_name')->nullable();
            $table->string('bank_acc_name', 200)->nullable();
            $table->string('bank_acc_no', 50)->nullable();
            $table->integer('bank_routing_no')->nullable();
            $table->integer('bank_payment_status')->default('0');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('sellers');
    }
};
