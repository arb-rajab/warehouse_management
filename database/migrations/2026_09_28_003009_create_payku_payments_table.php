<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `transaction_id` is both the primary key and the ONE real foreign key
     * declared anywhere in the legacy shop.sql dump, referencing
     * `payku_transactions.id`. No delete rule is given in shop.sql, so
     * default MySQL behavior applies (RESTRICT).
     */
    public function up(): void
    {
        if (Schema::hasTable('payku_payments')) {
            return;
        }

        Schema::create('payku_payments', function (Blueprint $table) {
            $table->string('transaction_id', 191)->primary();
            $table->date('start');
            $table->date('end');
            $table->string('media', 191);
            $table->string('transaction_key')->nullable();
            $table->string('payment_key')->nullable();
            $table->string('verification_key', 191);
            $table->string('authorization_code', 191);
            $table->unsignedInteger('last_4_digits')->nullable();
            $table->string('installments', 191)->nullable();
            $table->string('card_type', 191)->nullable();
            $table->string('additional_parameters', 191)->nullable();
            $table->string('currency', 191);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('transaction_id')->references('id')->on('payku_transactions')->restrictOnDelete();
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

        Schema::dropIfExists('payku_payments');
    }
};
