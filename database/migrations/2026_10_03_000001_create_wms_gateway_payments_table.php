<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment a store gateway reported, keyed by the gateway's own
     * reference, so a callback that arrives twice (a replay, a refresh of the
     * success page, a second notify) credits nothing the second time.
     *
     * The mobile API's SSLCommerz and Nagad flows have no session or token at
     * their callback, so their `begin` writes the row first, as pending
     * (`completed_at` null), carrying the payer, what is being paid for and the
     * amount, all from the database; the callback completes it once. Every
     * other gateway writes the row completed, in the same transaction that
     * credits the payment.
     */
    public function up(): void
    {
        Schema::create('wms_gateway_payments', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('reference', 191);
            $table->integer('user_id');
            $table->string('payment_type', 32);
            $table->integer('combined_order_id')->nullable();
            $table->integer('package_id')->nullable();
            $table->decimal('amount', 20, 2);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_gateway_payments');
    }
};
