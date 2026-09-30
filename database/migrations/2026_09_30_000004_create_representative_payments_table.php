<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `representative_payments` table, created by its own
     * `2024_03_14_150849_create_representative_payments_table` (plus `url`, a
     * string `id` and a string `receipt_code`, from `2024_03_24_160806`,
     * `2024_03_28_141629` and `2024_04_02_130509`) after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * payments sales representatives collect from customers.
     *
     * `date` defaulted to `now()` there, evaluated once when the migration ran;
     * `useCurrent()` is what that meant.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('representative_payments')) {
            return;
        }

        Schema::create('representative_payments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('receipt_code');
            $table->integer('invoice_code')->nullable();
            $table->integer('customer_id')->nullable();
            $table->integer('rep_id');
            $table->integer('admin_id')->nullable();
            $table->timestamp('date')->useCurrent();
            $table->integer('otajer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->enum('status', ['accepted', 'rejected', 'pending'])->default('pending');
            $table->string('url')->nullable();
            $table->timestamps();
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

        Schema::dropIfExists('representative_payments');
    }
};
