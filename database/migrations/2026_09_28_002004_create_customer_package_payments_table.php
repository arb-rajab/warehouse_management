<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `reciept` is spelled exactly as in the legacy dump (not `receipt`).
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_package_payments')) {
            return;
        }

        Schema::create('customer_package_payments', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('customer_package_id');
            $table->string('payment_method');
            $table->longText('payment_details');
            $table->integer('approval');
            $table->integer('offline_payment');
            $table->string('reciept', 150);
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

        Schema::dropIfExists('customer_package_payments');
    }
};
