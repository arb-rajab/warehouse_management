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
        if (Schema::hasTable('transactions')) {
            return;
        }

        Schema::create('transactions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->string('gateway')->nullable();
            $table->string('payment_type')->nullable();
            $table->text('additional_content')->nullable();
            $table->string('mpesa_request')->nullable();
            $table->string('mpesa_receipt')->nullable();
            $table->integer('status')->default(0);
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

        Schema::dropIfExists('transactions');
    }
};
