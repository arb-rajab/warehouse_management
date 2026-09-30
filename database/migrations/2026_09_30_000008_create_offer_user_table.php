<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `offer_user` table, created by its own
     * `2024_04_17_131442_create_offer_user_table` after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * offers a customer has ignored or postponed, read on every page a
     * signed-in customer opens.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('offer_user')) {
            return;
        }

        Schema::create('offer_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_ignored')->default(0);
            $table->boolean('is_postponed')->default(0);
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

        Schema::dropIfExists('offer_user');
    }
};
