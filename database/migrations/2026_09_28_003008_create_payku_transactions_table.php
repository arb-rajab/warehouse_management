<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `id` is a string primary key (not auto-increment) — the Payku gateway
     * assigns the transaction id. This table must exist before
     * `payku_payments`, which carries the one real foreign key in the whole
     * legacy dump.
     */
    public function up(): void
    {
        if (Schema::hasTable('payku_transactions')) {
            return;
        }

        Schema::create('payku_transactions', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('status', 191)->nullable();
            $table->string('order', 191)->nullable()->unique();
            $table->string('email', 191)->nullable();
            $table->string('subject', 191)->nullable();
            $table->text('url')->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
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

        Schema::dropIfExists('payku_transactions');
    }
};
