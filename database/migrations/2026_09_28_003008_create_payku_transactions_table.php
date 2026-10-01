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
     */
    public function down(): void
    {
        Schema::dropIfExists('payku_transactions');
    }
};
