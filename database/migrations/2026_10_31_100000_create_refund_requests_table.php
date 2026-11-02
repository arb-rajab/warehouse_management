<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refund requests a customer files against one delivered order line; the seller
     * and then the store approve them (`RefundRequestController`, `Seller\RefundController`).
     *
     * Skipped when the table exists: the store's own database already has it.
     * No earlier migration created it, so a database built from migrations
     * alone lacked it and the code that reads it failed with SQLSTATE[42S02].
     */
    public function up(): void
    {
        if (Schema::hasTable('refund_requests')) {
            return;
        }

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('order_id');
            $table->integer('order_detail_id');
            $table->integer('seller_id')->nullable();
            $table->integer('seller_approval')->default(0);
            $table->longText('reason')->nullable();
            $table->integer('admin_approval')->default(0);
            $table->integer('admin_seen')->default(0);
            $table->decimal('refund_amount', 25, 2)->default('0.00');
            $table->integer('refund_status')->default(0);
            $table->longText('reject_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('user_id');
            $table->index('seller_id');
            $table->index('order_id');
            $table->index('order_detail_id');
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

        Schema::dropIfExists('refund_requests');
    }
};
