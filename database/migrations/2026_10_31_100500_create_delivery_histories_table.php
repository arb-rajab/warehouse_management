<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per delivery status change of a delivery boy's order.
     *
     * Skipped when the table exists: the store's own database already has it.
     * No earlier migration created it, so a database built from migrations
     * alone lacked it and the code that reads it failed with SQLSTATE[42S02].
     */
    public function up(): void
    {
        if (Schema::hasTable('delivery_histories')) {
            return;
        }

        Schema::create('delivery_histories', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('delivery_boy_id');
            $table->integer('order_id');
            $table->string('delivery_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('earning', 20, 2)->default('0.00');
            $table->decimal('collection', 20, 2)->default('0.00');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('delivery_boy_id');
            $table->index('order_id');
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

        Schema::dropIfExists('delivery_histories');
    }
};
