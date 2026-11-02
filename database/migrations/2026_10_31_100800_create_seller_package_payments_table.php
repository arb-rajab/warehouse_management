<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A seller's purchase of a package, written by `seller_purchase_payment_done` and
     * the package payment controllers.
     *
     * Skipped when the table exists: the store's own database already has it.
     * No earlier migration created it, so a database built from migrations
     * alone lacked it and the code that reads it failed with SQLSTATE[42S02].
     */
    public function up(): void
    {
        if (Schema::hasTable('seller_package_payments')) {
            return;
        }

        Schema::create('seller_package_payments', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('seller_package_id');
            $table->string('payment_method')->nullable();
            $table->longText('payment_details')->nullable();
            $table->integer('approval')->default(0);
            $table->integer('offline_payment')->default(0);
            $table->string('reciept', 150)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('user_id');
            $table->index('seller_package_id');
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

        Schema::dropIfExists('seller_package_payments');
    }
};
