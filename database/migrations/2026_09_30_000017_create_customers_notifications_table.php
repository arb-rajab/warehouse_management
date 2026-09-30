<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `customers_notifications` table, created by its own
     * `2025_03_26_163206_create_notifications_table` after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * push notifications the admin panel sends customers.
     *
     * There `user_id` also called `constrained('users')->onDelete('cascade')`,
     * which only means something on a `foreignId()` column: on this plain one
     * it created no foreign key, and none is created here.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('customers_notifications')) {
            return;
        }

        Schema::create('customers_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->json('response')->nullable();
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

        Schema::dropIfExists('customers_notifications');
    }
};
