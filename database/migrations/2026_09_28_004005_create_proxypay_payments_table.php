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
        if (Schema::hasTable('proxypay_payments')) {
            return;
        }

        Schema::create('proxypay_payments', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('payment_type', 20);
            $table->string('reference_id', 20);
            $table->integer('order_id')->nullable();
            $table->integer('package_id')->nullable();
            $table->integer('user_id');
            $table->decimal('amount', 25, 2)->default('0.00');
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

        Schema::dropIfExists('proxypay_payments');
    }
};
